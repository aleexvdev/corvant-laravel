<?php

declare(strict_types=1);

namespace Corvant\Domain\Mfa\Services;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\ValueObjects\MfaChallenge;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Domain\Mfa\Exceptions\MfaAlreadyEnabledException;
use Corvant\Domain\Mfa\Exceptions\MfaNotEnabledException;
use Corvant\Domain\Mfa\ValueObjects\TotpCode;
use Corvant\Ports\MfaChallengePort;
use Corvant\Ports\MfaProviderPort;
use Corvant\Ports\MfaRecoveryCodePort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\UserRepositoryPort;
use LogicException;

final class MfaService
{
    public function __construct(
        private UserRepositoryPort $users,
        private MfaProviderPort $mfaProvider,
        private MfaChallengePort $challenges,
        private SessionStorePort $sessions,
        private PasswordHasherPort $hasher,
        private MfaRecoveryCodePort $recoveryCodes,
        private int $recoveryCodesCount,
    ) {}

    /**
     * @return array{secret: string, otpauth_uri: string}
     */
    public function enableTotp(User $user): array
    {
        if ($user->hasMfaEnabled()) {
            throw new MfaAlreadyEnabledException();
        }

        $secret = $this->mfaProvider->generateSecret();
        $saved = $this->users->save($user->withPendingTotpSecret($secret));
        $uri = $this->mfaProvider->otpauthUri($secret, $saved->email()->value());

        return [
            'secret' => $secret,
            'otpauth_uri' => $uri,
        ];
    }

    public function confirmTotp(User $user, string $code): void
    {
        $pending = $user->pendingTotpSecret();
        if ($pending === null) {
            throw new InvalidOrExpiredTokenException();
        }

        $totpCode = new TotpCode($code);
        if (! $this->mfaProvider->verifyCode($pending, $totpCode->value())) {
            throw new InvalidCredentialsException();
        }

        $this->users->save($user->withConfirmedTotpSecret($pending));
    }

    public function verifyMfaChallenge(string $challengeToken, string $code): Session
    {
        $challenge = $this->resolveChallenge($challengeToken);
        $user = $this->requireMfaUser($challenge->userId());

        $totpCode = new TotpCode($code);
        $secret = $user->totpSecret();
        if ($secret === null || ! $this->mfaProvider->verifyCode($secret, $totpCode->value())) {
            throw new InvalidCredentialsException();
        }

        $session = $this->sessions->create($user);
        $this->challenges->revoke($challengeToken);

        return $session;
    }

    public function useMfaRecoveryCode(string $challengeToken, string $plainCode): Session
    {
        $challenge = $this->resolveChallenge($challengeToken);
        $user = $this->requireMfaUser($challenge->userId());

        $userId = $user->id();
        if ($userId === null) {
            throw new InvalidOrExpiredTokenException();
        }

        $candidate = trim($plainCode);
        foreach ($this->recoveryCodes->unusedForUser($userId) as $stored) {
            if ($this->hasher->verify($candidate, $stored->codeHash())) {
                $this->recoveryCodes->markUsed($stored->id());
                $session = $this->sessions->create($user);
                $this->challenges->revoke($challengeToken);

                return $session;
            }
        }

        throw new InvalidCredentialsException();
    }

    public function disableMfa(User $user, string $currentPlainPassword): void
    {
        if (! $this->hasher->verify($currentPlainPassword, $user->password()->hash())) {
            throw new InvalidCredentialsException();
        }

        $userId = $user->id();
        if ($userId !== null) {
            $this->recoveryCodes->invalidateAllUnusedForUser($userId);
        }

        $this->users->save($user->withMfaDisabled());
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(User $user): array
    {
        if (! $user->hasMfaEnabled()) {
            throw new MfaNotEnabledException();
        }

        $userId = $user->id();
        if ($userId === null) {
            throw new LogicException('Cannot generate recovery codes for a user without an id.');
        }

        $plainCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < $this->recoveryCodesCount; $i++) {
            $plain = $this->generateRecoveryCodePlaintext();
            $plainCodes[] = $plain;
            $hashedCodes[] = $this->hasher->hash($plain);
        }

        $this->recoveryCodes->replaceAllForUser($userId, $hashedCodes);

        return $plainCodes;
    }

    private function resolveChallenge(string $challengeToken): MfaChallenge
    {
        $challenge = $this->challenges->find($challengeToken);
        if ($challenge === null) {
            throw new InvalidOrExpiredTokenException();
        }

        return $challenge;
    }

    private function requireMfaUser(int $userId): User
    {
        $user = $this->users->findById($userId);
        if ($user === null || ! $user->hasMfaEnabled()) {
            throw new InvalidOrExpiredTokenException();
        }

        return $user;
    }

    private function generateRecoveryCodePlaintext(): string
    {
        return strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(4)));
    }
}
