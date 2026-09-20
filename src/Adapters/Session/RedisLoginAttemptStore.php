<?php

declare(strict_types=1);

namespace Corvant\Adapters\Session;

use Corvant\Ports\LoginAttemptPort;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

final class RedisLoginAttemptStore implements LoginAttemptPort
{
    private const ATTEMPTS_PREFIX = 'corvant:login-attempts:';

    private const LOCKED_PREFIX = 'corvant:login-locked:';

    public function __construct(
        private RedisFactory $redis,
        private int $maxFailedAttempts,
        private int $lockoutDurationSeconds,
    ) {}

    public function isLocked(string $email): bool
    {
        $locked = $this->connection()->get($this->lockedKey($email));

        return $locked !== null && $locked !== false;
    }

    public function recordFailure(string $email): void
    {
        $attemptsKey = $this->attemptsKey($email);
        $count = (int) $this->connection()->incr($attemptsKey);

        if ($count === 1) {
            $this->connection()->expire($attemptsKey, $this->lockoutDurationSeconds);
        }

        if ($count >= $this->maxFailedAttempts) {
            $this->connection()->setex(
                $this->lockedKey($email),
                $this->lockoutDurationSeconds,
                '1',
            );
        }
    }

    public function clear(string $email): void
    {
        $this->connection()->del($this->attemptsKey($email), $this->lockedKey($email));
    }

    private function attemptsKey(string $email): string
    {
        return self::ATTEMPTS_PREFIX.$this->normalizeEmail($email);
    }

    private function lockedKey(string $email): string
    {
        return self::LOCKED_PREFIX.$this->normalizeEmail($email);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function connection(): \Illuminate\Redis\Connections\Connection
    {
        return $this->redis->connection();
    }
}
