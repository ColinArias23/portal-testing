<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Services\Profile\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profile,
    ) {
        $this->middleware('auth:sanctum');
    }

    // ── Status ────────────────────────────────────────────────────────────────
    public function status(Request $request)
    {
        return response()->json($this->profile->status($request->user()));
    }

    // ── Complete ──────────────────────────────────────────────────────────────
    public function complete(Request $request)
    {
        $result = $this->profile->complete($request);

        return response()->json($result['body'], $result['status']);
    }
}
