<?php

namespace App\DTOs;

final class UserDTO
{
    public function __construct(
        public readonly string $givenName,
        public readonly ?string $middleName,
        public readonly string $lastName,
        public readonly string $username,
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            givenName: $data['given_name'],
            middleName: $data['middle_name'] ?? null,
            lastName: $data['last_name'],
            username: strtoupper($data['username']),
            email: $data['email'],
            password: $data['password'],
        );
    }
}
