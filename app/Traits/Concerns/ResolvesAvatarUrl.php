<?php

namespace App\Traits\Concerns;

trait ResolvesAvatarUrl
{
    protected function resolveAvatarUrl(?string $path): ?string
    {
        if (! $path) return null;
        $filePath = storage_path('app/public/' . $path);
        if (! file_exists($filePath)) return null;
        return rtrim(config('app.url'), '/') . '/storage/' . $path;
    }
}