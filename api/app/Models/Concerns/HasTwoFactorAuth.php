<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

trait HasTwoFactorAuth
{
    public function enableTwoFactorAuth(): string
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $this->update([
            "two_factor_secret" => encrypt($secret),
            "two_factor_enabled" => false, // Will be enabled after confirmation
            "two_factor_recovery_codes" => $this->generateRecoveryCodes(),
        ]);

        return $secret;
    }

    public function confirmTwoFactorAuth(string $code): bool
    {
        if (!$this->validateTwoFactorCode($code)) {
            return false;
        }

        $this->update([
            "two_factor_enabled" => true,
            "two_factor_confirmed_at" => now(),
        ]);

        return true;
    }

    public function disableTwoFactorAuth(): void
    {
        $this->update([
            "two_factor_enabled" => false,
            "two_factor_secret" => null,
            "two_factor_recovery_codes" => null,
            "two_factor_confirmed_at" => null,
        ]);
    }

    public function validateTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_secret) {
            return false;
        }

        $google2fa = new Google2FA();
        $secret = decrypt($this->two_factor_secret);

        return $google2fa->verifyKey($secret, $code);
    }

    public function validateRecoveryCode(string $code): bool
    {
        if (!$this->two_factor_recovery_codes) {
            return false;
        }

        $codes = $this->two_factor_recovery_codes;

        if (($key = array_search($code, $codes)) !== false) {
            unset($codes[$key]);
            $this->update([
                "two_factor_recovery_codes" => array_values($codes),
            ]);
            return true;
        }

        return false;
    }

    public function getTwoFactorQrCodeSvg(): string
    {
        $secret = decrypt($this->two_factor_secret);
        $url = $this->getTwoFactorUrl($secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );

        return new Writer($renderer)->writeString($url);
    }

    public function regenerateRecoveryCodes(): array
    {
        $codes = $this->generateRecoveryCodes();
        $this->update(["two_factor_recovery_codes" => $codes]);
        return $codes;
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(function () {
                return Str::random(10);
            })
            ->toArray();
    }

    private function getTwoFactorUrl(string $secret): string
    {
        return sprintf(
            "otpauth://totp/%s:%s?secret=%s&issuer=%s",
            urlencode(config("app.name")),
            urlencode($this->email),
            $secret,
            urlencode(config("app.name")),
        );
    }
}
