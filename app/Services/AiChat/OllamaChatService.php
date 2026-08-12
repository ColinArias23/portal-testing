<?php

namespace App\Services\AiChat;

use App\Exceptions\AiChat\OllamaUnavailableException;
use App\Services\AiChat\Contracts\ChatServiceInterface;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OllamaChatService implements ChatServiceInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are the HR Assistant embedded in the hospital's HR Information System.
        You help staff with general HR process questions (leave requests, benefits,
        training enrollment, how to use the portal, PSB/hiring process overview, and
        similar). You do not have access to any employee's personal records, salary,
        or account data, and you must never claim to look any up. If asked for
        specific personal or confidential information, explain that you can't access
        individual records and suggest they check the relevant module or contact HR
        directly. Keep responses concise and professional.
        PROMPT;

    private readonly string $baseUrl;

    private readonly string $model;

    private readonly int $connectTimeout;

    private readonly int $requestTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        $this->model = (string) config('services.ollama.model', 'llama3.1');
        $this->connectTimeout = (int) config('services.ollama.connect_timeout', 5);
        $this->requestTimeout = (int) config('services.ollama.request_timeout', 120);
    }

    public function streamChat(string $message, array $conversationHistory): Generator
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->buildMessages($message, $conversationHistory),
            'stream' => true,
            'options' => [
                'temperature' => 0.4,
            ],
        ];

        try {
            $response = Http::withOptions(['stream' => true])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->requestTimeout)
                ->post("{$this->baseUrl}/api/chat", $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Ollama connection failed', ['error' => $exception->getMessage()]);

            throw new OllamaUnavailableException(
                'The AI assistant is temporarily unavailable. Please try again shortly.',
                503,
            );
        }

        if ($response->failed()) {
            Log::error('Ollama returned an error response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new OllamaUnavailableException(
                'The AI assistant is temporarily unavailable. Please try again shortly.',
                502,
            );
        }

        yield from $this->parseNdjsonStream($response->toPsrResponse()->getBody());
    }

    public function ping(): bool
    {
        try {
            return Http::connectTimeout(2)->timeout(3)
                ->get("{$this->baseUrl}/api/tags")
                ->successful();
        } catch (ConnectionException) {
            return false;
        } catch (Throwable $exception) {
            Log::warning('Ollama health check failed unexpectedly', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    private function buildMessages(string $message, array $conversationHistory): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        foreach ($conversationHistory as $turn) {
            if (! in_array($turn['role'], ['user', 'assistant'], true)) {
                continue;
            }

            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function parseNdjsonStream($body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            if (connection_aborted()) {
                return;
            }

            $buffer .= $body->read(1024);

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (! is_array($decoded)) {
                    Log::warning('Ollama emitted a non-JSON line, skipping', ['line' => $line]);

                    continue;
                }

                if (isset($decoded['error'])) {
                    Log::error('Ollama streamed an error payload', ['error' => $decoded['error']]);

                    throw new OllamaUnavailableException(
                        'The AI assistant ran into a problem generating a response.',
                        502,
                    );
                }

                $content = $decoded['message']['content'] ?? '';

                if ($content !== '') {
                    yield $content;
                }

                if (($decoded['done'] ?? false) === true) {
                    return;
                }
            }
        }
    }
}
