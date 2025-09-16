<?php

namespace App\DTOs;

class UpdateUserDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $surname = null,
        public readonly ?string $phone = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?int $profilePhotoId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data["name"] ?? null,
            surname: $data["surname"] ?? null,
            phone: $data["phone"] ?? null,
            dateOfBirth: $data["date_of_birth"] ?? null,
            profilePhotoId: $data["profile_photo_id"] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter(
            [
                "name" => $this->name,
                "surname" => $this->surname,
                "phone" => $this->phone,
                "date_of_birth" => $this->dateOfBirth,
                "profile_photo_id" => $this->profilePhotoId,
            ],
            fn($value) => $value !== null,
        );
    }
}
