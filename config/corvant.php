<?php

declare(strict_types=1);

return [
    'password' => [
        'min_length' => (int) env('CORVANT_PASSWORD_MIN_LENGTH', 8),
    ],

    'session' => [
        'ttl_seconds' => (int) env('CORVANT_SESSION_TTL_SECONDS', 3600),
    ],

    'password_reset' => [
        'ttl_seconds' => (int) env('CORVANT_PASSWORD_RESET_TTL_SECONDS', 3600),
    ],

    'email_verification' => [
        'ttl_seconds' => (int) env('CORVANT_EMAIL_VERIFICATION_TTL_SECONDS', 86400),
    ],

    'tenancy' => [
        'header' => env('CORVANT_TENANT_HEADER', 'X-Tenant-ID'),
    ],
];
