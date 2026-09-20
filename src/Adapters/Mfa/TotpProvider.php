<?php

declare(strict_types=1);

namespace Corvant\Adapters\Mfa;

use Corvant\Ports\MfaProviderPort;
use PragmaRX\Google2FA\Google2FA;

final class TotpProvider implements MfaProviderPort
{
    public function __construct(
        private Google2FA $google2fa,
        private string $issuer,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpauthUri(string $secret, string $accountLabel): string
    {
        return $this->google2fa->getQRCodeUrl($this->issuer, $accountLabel, $secret);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }
}
