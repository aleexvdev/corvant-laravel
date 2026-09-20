<?php

declare(strict_types=1);

namespace Corvant\Adapters\Session;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\MfaChallenge;
use Corvant\Ports\MfaChallengePort;
use DateTimeImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

final class RedisMfaChallengeStore implements MfaChallengePort
{
    private const KEY_PREFIX = 'corvant:mfa-challenge:';

    public function __construct(
        private RedisFactory $redis,
        private int $ttlSeconds,
    ) {}

    public function create(User $user): MfaChallenge
    {
        $userId = $user->id();
        if ($userId === null) {
            throw new \LogicException('Cannot create an MFA challenge for a user without an id.');
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', $this->ttlSeconds));

        $payload = json_encode([
            'user_id' => $userId,
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ], JSON_THROW_ON_ERROR);

        $this->connection()->setex(
            self::KEY_PREFIX.$token,
            $this->ttlSeconds,
            $payload,
        );

        return new MfaChallenge($token, $userId, $expiresAt);
    }

    public function find(string $token): ?MfaChallenge
    {
        $raw = $this->connection()->get(self::KEY_PREFIX.$token);
        if ($raw === null || $raw === false) {
            return null;
        }

        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $expiresAt = new DateTimeImmutable($data['expires_at']);

        if ($expiresAt < new DateTimeImmutable()) {
            return null;
        }

        return new MfaChallenge($token, (int) $data['user_id'], $expiresAt);
    }

    public function revoke(string $token): void
    {
        $this->connection()->del(self::KEY_PREFIX.$token);
    }

    private function connection(): \Illuminate\Redis\Connections\Connection
    {
        return $this->redis->connection();
    }
}
