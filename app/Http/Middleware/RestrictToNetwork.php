<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictToNetwork
{
    public function handle(Request $request, Closure $next, string $zone): Response
    {
        $ranges = match ($zone) {
            'kiosk'    => (array) config('dtr.kiosk_ips'),
            'intranet' => (array) config('dtr.intranet_cidrs'),
            default    => [],
        };

        $ip = $request->ip();

        if ($ip === null || $ranges === [] || ! IpUtils::checkIp($ip, $ranges)) {
            return response()->json(['message' => 'This action is not allowed from this device or network.'], 403);
        }

        return $next($request);
    }
}
