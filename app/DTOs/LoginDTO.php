<?php

namespace App\DTOs;

final class LoginDTO
{
    public readonly string $username;
    public readonly string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = mb_strtoupper(trim($username));
        $this->password = $password;
    }

    public static function fromArray(array $data): self
    {
        return new self($data['username'], $data['password']);
    }
}
