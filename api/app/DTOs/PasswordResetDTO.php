<?php

namespace App\DTOs;

class PasswordResetDTO
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly string $password,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            token: $data["token"],
            email: $data["email"],
            password: $data["password"],
        );
    }
}
