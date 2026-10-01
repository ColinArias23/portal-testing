<?php

namespace App\Services\Dtr;

use App\Exceptions\Dtr\DtrException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PunchTicketService
{
    public const KIOSK = 'kiosk';
    public const PERSONAL = 'personal';

    public function issue(int $employeeId, string $channel, ?int $userId = null): string
    {
        return Crypt::encryptString(json_encode([
            'jti' => (string) Str::uuid(),
            'eid' => $employeeId,
            'ch'  => $channel,
            'uid' => $userId,
            'exp' => now()->getTimestamp() + (int) config('dtr.punch.ticket_ttl_seconds'),
        ], JSON_THROW_ON_ERROR));
    }

    public function consume(string $ticket, string $channel, ?int $userId = null): int
    {
        try {
            $claims = json_decode(Crypt::decryptString($ticket), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw new DtrException('Invalid verification. Please scan your face again.');
        }

        if (($claims['exp'] ?? 0) < now()->getTimestamp()) {
            throw new DtrException('Verification expired. Please scan your face again.');
        }
        if (($claims['ch'] ?? null) !== $channel || ($claims['uid'] ?? null) !== $userId) {
            throw new DtrException('Invalid verification. Please scan your face again.', 403);
        }

        $ttl = max(1, (int) $claims['exp'] - now()->getTimestamp());
        if (! Cache::add('dtr:ticket:'.$claims['jti'], 1, $ttl)) { 
            throw new DtrException('Verification already used. Please scan your face again.');
        }

        return (int) $claims['eid'];
    }
}
