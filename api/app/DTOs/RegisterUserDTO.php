<?php

namespace App\DTOs;

class RegisterUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $surname,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $phone,
        public readonly ?string $dateOfBirth,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data["name"],
            surname: $data["surname"] ?? null,
            email: $data["email"],
            password: $data["password"],
            phone: $data["phone"] ?? null,
            dateOfBirth: $data["date_of_birth"] ?? null,
        );
    }
}
