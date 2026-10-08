<?php

namespace App\Services\Dtr;

use App\Exceptions\Dtr\DtrException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStore
{
    private const PATTERN = '#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=\r\n]+)$#';

    public function putDataUrl(string $dataUrl, string $subdir): string
    {
        if (! preg_match(self::PATTERN, $dataUrl, $m)) {
            throw new DtrException('Invalid image data.');
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || $binary === '' || strlen($binary) > (int) config('dtr.images.max_bytes')) {
            throw new DtrException('Invalid or oversized image.');
        }

        return $this->putBinary($binary, $subdir);
    }

    public function putUploadedFile(UploadedFile $file, string $subdir): string
    {
        $binary = $file->get();
        if ($binary === false || $binary === '' || strlen($binary) > (int) config('dtr.images.max_bytes')) {
            throw new DtrException('Invalid or oversized image.');
        }

        return $this->putBinary($binary, $subdir);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path && Storage::disk($this->disk())->exists($path);
    }

    public function response(string $path)
    {
        return Storage::disk($this->disk())->response($path, null, [
            'Cache-Control'          => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function putBinary(string $binary, string $subdir): string
    {
        $info = @getimagesizefromstring($binary);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new DtrException('Invalid image content.');
        }

        $ext  = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $path = trim((string) config('dtr.images.directory'), '/')."/{$subdir}/".Str::uuid().".{$ext}";

        Storage::disk($this->disk())->put($path, $binary);

        return $path;
    }

    private function disk(): string
    {
        return (string) config('dtr.images.disk');
    }
}