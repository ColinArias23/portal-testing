<?php

namespace App\Http\Controllers\Api\AiChat;

use App\DTOs\AiChatMessageDTO;
use App\Exceptions\AiChat\OllamaUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiChat\SendChatMessageRequest;
use App\Services\AiChat\Contracts\ChatServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    public function __construct(private readonly ChatServiceInterface $chatService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:ai_chat.use');
    }

    public function health(): JsonResponse
    {
        return response()->json(['available' => $this->chatService->ping()]);
    }

    public function send(SendChatMessageRequest $request): StreamedResponse
    {
        $data = AiChatMessageDTO::fromArray($request->validated());
        $chatService = $this->chatService;

        return response()->stream(function () use ($data, $chatService): void {
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }

            try {
                foreach ($chatService->streamChat($data->message, $data->conversationHistory) as $chunk) {
                    $this->emit(['content' => $chunk]);

                    if (connection_aborted()) {
                        return;
                    }
                }

                $this->emitDone();
            } catch (OllamaUnavailableException $exception) {
                $this->emit(['error' => $exception->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', 
            'Connection' => 'keep-alive',
        ]);
    }

    private function emit(array $payload): void
    {
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
        $this->flushOutput();
    }

    private function emitDone(): void
    {
        echo "data: [DONE]\n\n";
        $this->flushOutput();
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
