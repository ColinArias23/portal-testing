<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    public function pendingCount()
    {
        $count = User::where('approval_status', 'PENDING')->count();
        return response()->json(['count' => $count]);
    }

    public function pendingUsers()
    {
        $users = User::where('approval_status', 'PENDING')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    public function activate(User $user)
    {
        $user->approval_status = 'APPROVED';
        $user->approved_at = now();
        $user->save();

        return response()->json([
            'message' => 'User activated successfully',
            'user' => $user
        ]);
    }
}