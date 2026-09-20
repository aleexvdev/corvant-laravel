<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Domain\Mfa\Exceptions\InvalidTotpCodeException;
use Corvant\Domain\Mfa\Exceptions\MfaAlreadyEnabledException;
use Corvant\Domain\Mfa\Exceptions\MfaNotEnabledException;
use Corvant\Domain\Mfa\Services\MfaService;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Http\Requests\ConfirmMfaRequest;
use Corvant\Infrastructure\Http\Requests\DisableMfaRequest;
use Corvant\Infrastructure\Http\Requests\MfaVerifyRequest;
use Corvant\Infrastructure\Http\Requests\UseRecoveryCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class MfaController extends Controller
{
    public function __construct(
        private MfaService $mfa,
    ) {}

    public function enable(CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $result = $this->mfa->enableTotp($user);
        } catch (MfaAlreadyEnabledException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function confirm(ConfirmMfaRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $this->mfa->confirmTotp($user, $request->validated('code'));
        } catch (InvalidTotpCodeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json(['message' => 'Multi-factor authentication enabled.']);
    }

    public function verify(MfaVerifyRequest $request): JsonResponse
    {
        try {
            $session = $this->mfa->verifyMfaChallenge(
                $request->validated('challenge_token'),
                $request->validated('code'),
            );
        } catch (InvalidTotpCodeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json($this->sessionPayload($session));
    }

    public function disable(DisableMfaRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $this->mfa->disableMfa($user, $request->validated('password'));
        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json(['message' => 'Multi-factor authentication disabled.']);
    }

    public function recoveryCodes(CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $codes = $this->mfa->generateRecoveryCodes($user);
        } catch (MfaNotEnabledException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['recovery_codes' => $codes]);
    }

    public function useRecoveryCode(UseRecoveryCodeRequest $request): JsonResponse
    {
        try {
            $session = $this->mfa->useMfaRecoveryCode(
                $request->validated('challenge_token'),
                $request->validated('code'),
            );
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json($this->sessionPayload($session));
    }

    /**
     * @return array{token: string, expires_at: string}
     */
    private function sessionPayload(Session $session): array
    {
        return [
            'token' => $session->token(),
            'expires_at' => $session->expiresAt()->format(DATE_ATOM),
        ];
    }

    private function requireUser(CurrentUser $currentUser): \Corvant\Domain\Authentication\Entities\User
    {
        $user = $currentUser->get();
        if ($user === null || $user->id() === null) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }
}
