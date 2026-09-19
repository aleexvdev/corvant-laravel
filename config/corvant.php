<?php

declare(strict_types=1);

return [
    'password' => [
        'min_length' => (int) env('CORVANT_PASSWORD_MIN_LENGTH', 8),
    ],

    'session' => [
        'ttl_seconds' => (int) env('CORVANT_SESSION_TTL_SECONDS', 3600),
    ],
];
