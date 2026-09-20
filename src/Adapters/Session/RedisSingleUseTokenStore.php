<?php

declare(strict_types=1);

namespace Corvant\Adapters\Session;

use Corvant\Ports\SingleUseTokenPort;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

final class RedisSingleUseTokenStore implements SingleUseTokenPort
{
    private const KEY_PREFIX = 'corvant:single-use:';

    public function __construct(
        private RedisFactory $redis,
    ) {}

    public function issue(string $subject, string $purpose, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        $key = $this->key($purpose, $token);

        $this->connection()->setex($key, $ttlSeconds, $subject);

        return $token;
    }

    public function consume(string $token, string $purpose): ?string
    {
        $key = $this->key($purpose, $token);
        $subject = $this->connection()->get($key);

        if ($subject === null || $subject === false) {
            return null;
        }

        $this->connection()->del($key);

        return $subject;
    }

    private function key(string $purpose, string $token): string
    {
        return self::KEY_PREFIX.$purpose.':'.$token;
    }

    private function connection(): \Illuminate\Redis\Connections\Connection
    {
        return $this->redis->connection();
    }
}
