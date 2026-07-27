<?php

namespace App\DTOs;

final class DivisionDTO
{
    public function __construct(
        public readonly array $attributes,
    ) {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(attributes: $validated);
    }
}
