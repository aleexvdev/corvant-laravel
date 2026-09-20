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

    private const USER_INDEX_PREFIX = 'corvant:user-sessions:';

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

        $this->connection()->sadd($this->userSessionsKey($userId), $token);

        return new Session($token, $userId, $expiresAt);
    }

    public function find(string $token): ?Session
    {
        return $this->read($token, extendIdleTimeout: true);
    }

    public function revoke(string $token): void
    {
        $session = $this->read($token, extendIdleTimeout: false);
        if ($session === null) {
            return;
        }

        $this->connection()->del(self::KEY_PREFIX.$token);
        $this->connection()->srem($this->userSessionsKey($session->userId()), $token);
    }

    public function allForUser(int $userId): array
    {
        $tokens = $this->connection()->smembers($this->userSessionsKey($userId));
        if ($tokens === false || $tokens === []) {
            return [];
        }

        $sessions = [];

        foreach ($tokens as $token) {
            $session = $this->read((string) $token, extendIdleTimeout: false);
            if ($session === null) {
                $this->connection()->srem($this->userSessionsKey($userId), (string) $token);

                continue;
            }

            $sessions[] = $session;
        }

        return $sessions;
    }

    private function read(string $token, bool $extendIdleTimeout): ?Session
    {
        $key = self::KEY_PREFIX.$token;
        $raw = $this->connection()->get($key);
        if ($raw === null || $raw === false) {
            return null;
        }

        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $userId = (int) $data['user_id'];

        if ($extendIdleTimeout) {
            $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', $this->ttlSeconds));
            $payload = json_encode([
                'user_id' => $userId,
                'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
            ], JSON_THROW_ON_ERROR);

            $this->connection()->setex($key, $this->ttlSeconds, $payload);

            return new Session($token, $userId, $expiresAt);
        }

        return new Session(
            $token,
            $userId,
            new DateTimeImmutable($data['expires_at']),
        );
    }

    private function userSessionsKey(int $userId): string
    {
        return self::USER_INDEX_PREFIX.$userId;
    }

    private function connection(): \Illuminate\Redis\Connections\Connection
    {
        return $this->redis->connection();
    }
}
