<?php

namespace App\DTOs;

class LoginUserDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $twoFactorCode = null,
        public readonly ?string $recoveryCode = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            email: $data["email"],
            password: $data["password"],
            twoFactorCode: $data["two_factor_code"] ?? null,
            recoveryCode: $data["recovery_code"] ?? null,
        );
    }
}
