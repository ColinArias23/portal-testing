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
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with([
            'employee.info',
            'employee.division',
            'employee.primaryAssignment.plantillaItem.salaryGrade',
        ])->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if ($user->approval_status !== 'APPROVED') {
            return response()->json([
                'message' => 'Your account is not yet activated. Please contact the HR Department.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'approval_status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please wait for HR to activate your account.',
            'user'    => $this->formatUser($user),
        ], 201);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load([
            'employee.info',
            'employee.division',
            'employee.primaryAssignment.plantillaItem.salaryGrade',
        ]);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['success' => true]);
    }

    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) return null;
        $filePath = storage_path('app/public/' . $path);
        if (!file_exists($filePath)) return null;
        return rtrim(config('app.url'), '/') . '/storage/' . $path;
    }

    private function formatUser(User $user): array
    {
        $employee          = $user->employee;
        $primaryAssignment = $employee?->primaryAssignment;
        $division          = $employee?->division;

        $departments = [];
        if ($employee && !empty($employee->department_ids)) {
            $departments = \App\Models\Department::whereIn('id', $employee->department_ids)
                ->get(['id', 'name'])
                ->toArray();
        }

        $avatarUrl = $this->resolveAvatarUrl(
            $employee?->getRawOriginal('avatar_url')
        );

        return [
            'id'              => $user->id,
            'email'           => $user->email,
            'is_active'       => $user->approval_status === 'APPROVED',
            'approval_status' => $user->approval_status,
            'first_name'      => $employee?->first_name ?? null,
            'last_name'       => $employee?->last_name  ?? null,
            'roles'           => $user->getRoleNames(),
            'permissions'     => $user->getAllPermissions()->pluck('name'),
            'employee'        => $employee,
            'avatar_url'      => $avatarUrl,
            'departments'     => $departments,
            'department_ids'  => $employee?->department_ids ?? [],
            'division'        => $division?->name,
            'position'        => $primaryAssignment?->plantillaItem?->title,
        ];
    }
}