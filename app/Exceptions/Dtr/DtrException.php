<?php

namespace App\Exceptions\Dtr;

use RuntimeException;

class DtrException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render()
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
