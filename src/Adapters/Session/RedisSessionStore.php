<?php

declare(strict_types=1);

namespace Corvant\Adapters\Session;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Ports\SessionStorePort;
use DateTimeImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

final class RedisSessionStore implements SessionStorePort
{
    private const KEY_PREFIX = 'corvant:session:';

    public function __construct(
        private RedisFactory $redis,
        private int $ttlSeconds,
    ) {}

    public function create(User $user): Session
    {
        $userId = $user->id();
        if ($userId === null) {
            throw new \LogicException('Cannot create a session for a user without an id.');
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

        return new Session($token, $userId, $expiresAt);
    }

    public function find(string $token): ?Session
    {
        $raw = $this->connection()->get(self::KEY_PREFIX.$token);
        if ($raw === null || $raw === false) {
            return null;
        }

        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        return new Session(
            $token,
            (int) $data['user_id'],
            new DateTimeImmutable($data['expires_at']),
        );
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
