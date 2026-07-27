<?php

namespace App\DTOs;

final class AnnouncementDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority,
        public readonly string $category,
        public readonly array $plantillaPostingIds,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            priority: $data['priority'],
            category: $data['category'],
            plantillaPostingIds: $data['plantilla_posting_ids'] ?? [],
        );
    }

    public function toModelAttributes(): array
    {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'priority'    => $this->priority,
            'category'    => $this->category,
        ];
    }
}
