<?php

namespace App\Services\Dtr;

use App\Exceptions\Dtr\DtrException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceEngineClient
{
    public function register(array $images, string $label): array
    {
        $data = $this->post('/register', ['name' => $label, 'images' => $images]);

        $encodings = $data['encodings'] ?? (isset($data['encoding']) ? [$data['encoding']] : []);
        if ($encodings === []) {
            throw new DtrException('Face registration failed. Please retake with better lighting.', 422);
        }

        return [
            'encodings'        => $encodings,
            'frames_used'      => (int) ($data['frames_used'] ?? count($encodings)),
            'frames_submitted' => (int) ($data['frames_submitted'] ?? count($images)),
        ];
    }

    public function recognize(array $images, array $knownFaces): array
    {
        if ($knownFaces === []) {
            return ['match' => false];
        }

        $data = $this->post('/recognize', ['images' => $images, 'known_faces' => $knownFaces]);

        if (! ($data['match'] ?? false)) {
            return ['match' => false];
        }

        return [
            'match'      => true,
            'name'       => (string) $data['name'],
            'confidence' => (float) ($data['confidence'] ?? 0),
        ];
    }

    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->http()->post($path, $payload);
        } catch (ConnectionException $e) {
            Log::error('DTR face engine unreachable', ['error' => $e->getMessage()]);
            throw new DtrException('Face recognition service is unavailable. Please try again shortly.', 503);
        }

        if ($response->serverError()) {
            Log::error('DTR face engine error', ['status' => $response->status()]);
            throw new DtrException('Face recognition service error. Please try again.', 503);
        }

        if ($response->failed()) {
            $message = (string) ($response->json('error') ?? 'Face could not be processed.');
            throw new DtrException($message, 422);
        }

        return (array) $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('dtr.engine.url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('dtr.engine.timeout'))
            ->connectTimeout((int) config('dtr.engine.connect_timeout'));
    }
}
