<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with([
                'employee',
                // ✅ primary division via pivot
                'employee.divisions' => fn ($q) => $q->wherePivot('is_primary', true)->with('department'),
            ])
            ->where('email', $data['email'])
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        // optional: enforce approval
        // if ($user->approval_status !== 'APPROVED') {
        //     throw ValidationException::withMessages([
        //         'email' => ['Account not yet approved.'],
        //     ]);
        // }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        $primaryDivision = $user->employee?->divisions?->first();

        $landing = $user->can('dashboard.view') ? 'dashboard' : (
            $user->can('mapping.view') ? 'mapping' : 'profile'
        );

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
                'permissions' => method_exists($user, 'getAllPermissions')
                    ? $user->getAllPermissions()->pluck('name')
                    : [],
                'landing' => $landing,

                'employee' => $user->employee ? [
                    'id' => $user->employee->id,
                    'employee_number' => $user->employee->employee_number,
                    'full_name' => trim(($user->employee->first_name ?? '') . ' ' . ($user->employee->last_name ?? '')),
                    'employment_type' => $user->employee->employment_type,
                    'employment_status' => $user->employee->employment_status,
                ] : null,

                'primary_division' => $primaryDivision ? [
                    'id' => $primaryDivision->id,
                    'code' => $primaryDivision->code,
                    'name' => $primaryDivision->name,
                    'department' => $primaryDivision->department ? [
                        'id' => $primaryDivision->department->id,
                        'code' => $primaryDivision->department->code,
                        'name' => $primaryDivision->department->name,
                    ] : null,
                    'is_primary' => (bool) $primaryDivision->pivot?->is_primary,
                ] : null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load([
            'employee',
            'employee.divisions' => fn ($q) => $q->wherePivot('is_primary', true)->with('department'),
        ]);

        $primaryDivision = $user->employee?->divisions?->first();

        $landing = $user->can('dashboard.view') ? 'dashboard' : (
            $user->can('mapping.view') ? 'mapping' : 'profile'
        );

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
                'permissions' => method_exists($user, 'getAllPermissions')
                    ? $user->getAllPermissions()->pluck('name')
                    : [],
                'landing' => $landing,
                'employee' => $user->employee,
                'primary_division' => $primaryDivision,
            ],
        ]);
    }
}
