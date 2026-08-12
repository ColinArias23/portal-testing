<?php

namespace App\DTOs;

final class AiChatMessageDTO
{
    public function __construct(
        public readonly string $message,
        public readonly array $conversationHistory,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: trim($data['message']),
            conversationHistory: array_map(
                static fn (array $turn): array => [
                    'role' => $turn['role'],
                    'content' => trim($turn['content']),
                ],
                $data['conversation_history'] ?? [],
            ),
        );
    }
}
