<?php

namespace App\Exceptions\Users;

use Exception;

class PermissionUpdateForbiddenException extends Exception
{
    public function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
