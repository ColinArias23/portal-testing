<?php

namespace App\Services\AiChat\Contracts;

use App\Exceptions\AiChat\OllamaUnavailableException;
use Generator;

interface ChatServiceInterface
{
    public function streamChat(string $message, array $conversationHistory): Generator;

    public function ping(): bool;
}
