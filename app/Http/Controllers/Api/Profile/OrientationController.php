<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrientationController extends Controller
{
// ─── Complete ─────────────────────────────────────────────────────
    public function complete(Request $request): JsonResponse
    {
        $request->user()->update([
            'has_completed_orientation' => true,
        ]);

        return response()->json(['success' => true]);
    }
}
