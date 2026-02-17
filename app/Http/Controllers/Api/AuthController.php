<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
  public function login(Request $request)
  {
    $data = $request->validate([
      'email' => ['required','email'],
      'password' => ['required','string'],
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user || !Hash::check($data['password'], $user->password)) {
      throw ValidationException::withMessages([
        'email' => ['Invalid email or password.'],
      ]);
    }

    // revoke old tokens (optional)
    $user->tokens()->delete();

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
      'token' => $token,
      'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'roles' => $user->getRoleNames(),
        'permissions' => $user->getAllPermissions()->pluck('name'),
      ],
    ]);
  }

  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()?->delete();
    return response()->json(['message' => 'Logged out']);
  }

  public function me(Request $request)
  {
    $user = $request->user();

    return response()->json([
      'id' => $user->id,
      'name' => $user->name,
      'email' => $user->email,
      'roles' => $user->getRoleNames(),
      'permissions' => $user->getAllPermissions()->pluck('name'),
    ]);
  }
}
