<?php

declare(strict_types=1);

return [
    'password' => [
        'min_length' => (int) env('CORVANT_PASSWORD_MIN_LENGTH', 8),
        'require_mixed_case' => filter_var(env('CORVANT_PASSWORD_REQUIRE_MIXED_CASE', true), FILTER_VALIDATE_BOOL),
        'require_numbers' => filter_var(env('CORVANT_PASSWORD_REQUIRE_NUMBERS', true), FILTER_VALIDATE_BOOL),
        'require_symbols' => filter_var(env('CORVANT_PASSWORD_REQUIRE_SYMBOLS', true), FILTER_VALIDATE_BOOL),
    ],

    'session' => [
        // Idle timeout: each authenticated use of a session extends Redis TTL by this many seconds.
        'ttl_seconds' => (int) env('CORVANT_SESSION_TTL_SECONDS', 3600),
    ],

    'rate_limiting' => [
        'login_attempts_per_minute' => (int) env('CORVANT_LOGIN_ATTEMPTS_PER_MINUTE', 5),
    ],

    'account_lockout' => [
        'max_failed_attempts' => (int) env('CORVANT_ACCOUNT_LOCKOUT_MAX_FAILED_ATTEMPTS', 5),
        'lockout_duration_seconds' => (int) env('CORVANT_ACCOUNT_LOCKOUT_DURATION_SECONDS', 900),
    ],

    'password_reset' => [
        'ttl_seconds' => (int) env('CORVANT_PASSWORD_RESET_TTL_SECONDS', 3600),
    ],

    'email_verification' => [
        'ttl_seconds' => (int) env('CORVANT_EMAIL_VERIFICATION_TTL_SECONDS', 86400),
    ],

    'email_change' => [
        'ttl_seconds' => (int) env('CORVANT_EMAIL_CHANGE_TTL_SECONDS', 86400),
    ],

    'tenancy' => [
        'header' => env('CORVANT_TENANT_HEADER', 'X-Tenant-ID'),
    ],

    'mfa' => [
        'challenge_ttl_seconds' => (int) env('CORVANT_MFA_CHALLENGE_TTL_SECONDS', 300),
        'recovery_codes_count' => (int) env('CORVANT_MFA_RECOVERY_CODES_COUNT', 10),
        'issuer' => env('CORVANT_MFA_ISSUER', 'Corvant'),
    ],
];
