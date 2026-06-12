<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\UserRegistered;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            ActivityLog::record(
                action: 'auth.login_failed',
                payload: ['username' => $data['username']],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        if ($user->approval_status !== 'APPROVED') {
            ActivityLog::record(
                action: 'auth.login_blocked',
                userId: $user->id,
                subjectType: User::class,
                subjectId: $user->id,
                payload: ['reason' => 'account_not_approved'],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'message' => 'Your account is not yet activated. Please contact the HR Department.',
            ], 403);
        }

        if ($user->tokens()->exists()) {
            broadcast(new \App\Events\UserLoggedInElsewhere($user->id));
            $user->tokens()->delete();
        }

        $token = $user->createToken('api-token')->plainTextToken;

        ActivityLog::record(
            action: 'auth.login',
            userId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $user->load([
            'employee.info',
            'employee.division',
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'given_name'  => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'username'    => ['required', 'string', 'unique:users,username', 'min:2', 'max:100'],
            'email'       => ['required', 'string', 'email', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ]);

        $user = User::create([
            'username'      => strtoupper($data['username']),
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'given_name'    => strtoupper($data['given_name']),
            'middle_name'   => isset($data['middle_name']) ? strtoupper($data['middle_name']) : null,
            'last_name'     => strtoupper($data['last_name']),
            'approval_status' => 'PENDING',
        ]);

        broadcast(new UserRegistered($user->id));
        NotificationService::accountPending($user->username);

        ActivityLog::record(
            action: 'auth.register',
            userId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please wait for HR to activate your account.',
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function checkUsername(Request $request)
    {
        $request->validate([
            'username'    => ['required', 'string', 'max:100'],
            'given_name'  => ['nullable', 'string'],
            'middle_name' => ['nullable', 'string'],
            'last_name'   => ['nullable', 'string'],
        ]);

        $username = mb_strtoupper(trim($request->username));
        $exists = User::where('username', $username)->exists();

        $suggestions = [];

        if ($exists) {
            $givenName  = (string) ($request->given_name ?? '');
            $middleName = (string) ($request->middle_name ?? '');
            $lastName   = (string) ($request->last_name ?? '');

            $givenInitials = collect(preg_split('/\s+/', trim($givenName)))
                ->filter()
                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                ->implode('');

            $middleInitial = $middleName !== ''
                ? mb_strtoupper(mb_substr(trim($middleName), 0, 1))
                : '';

            $lastUpper  = mb_strtoupper(preg_replace('/\s+/', '', trim($lastName)));
            $givenUpper = mb_strtoupper(preg_replace('/\s+/', '', trim($givenName)));

            $candidates = [];

            if ($middleInitial !== '') {
                $candidates[] = $givenInitials . $middleInitial . $lastUpper;
            }

            if ($givenUpper !== '' && $lastUpper !== '') {
                $candidates[] = $givenUpper . mb_substr($lastUpper, 0, 1);
            }

            for ($i = 1; $i <= 9; $i++) {
                $candidates[] = $username . $i;
            }

            if ($givenUpper !== '' && $lastUpper !== '') {
                $candidates[] = $givenUpper . $lastUpper;
            }

            foreach ($candidates as $candidate) {
                if (count($suggestions) >= 4) {
                    break;
                }

                if (
                    $candidate !== $username
                    && !in_array($candidate, $suggestions)
                    && !User::where('username', $candidate)->exists()
                ) {
                    $suggestions[] = $candidate;
                }
            }
        }

        return response()->json([
            'available'   => !$exists,
            'suggestions' => $suggestions,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load([
            'employee.info',
            'employee.division',
        ]);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        ActivityLog::record(
            action: 'auth.logout',
            userId: $user?->id,
            subjectType: $user ? User::class : null,
            subjectId: $user?->id,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $user?->currentAccessToken()?->delete();

        return response()->json(['success' => true]);
    }

    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $filePath = storage_path('app/public/' . $path);
        if (!file_exists($filePath)) {
            return null;
        }

        return rtrim(config('app.url'), '/') . '/storage/' . $path;
    }

    private function formatUser(User $user): array
    {
        $employee = $user->employee;
        $division = $employee?->division;

        $departments = [];
        if ($employee && !empty($employee->department_ids)) {
            $departments = \App\Models\Department::whereIn('id', $employee->department_ids)
                ->get(['id', 'name'])
                ->toArray();
        }

        $avatarUrl = $this->resolveAvatarUrl(
            $employee?->getRawOriginal('avatar_url')
        );

        $roles = $user->getRoleNames();
        $permissions = $user->getAllPermissions()->pluck('name')->values();

        return [
            'id'              => $user->id,
            'username'        => $user->username,
            'email'           => $user->email,
            'is_active'       => $user->approval_status === 'APPROVED',
            'approval_status' => $user->approval_status,
            'has_completed_orientation' => (bool) $user->has_completed_orientation,
            'first_name'      => $employee?->first_name ?? $user->given_name ?? null,
            'last_name'       => $employee?->last_name  ?? $user->last_name  ?? null,
            'roles'           => $roles,
            'permissions'     => $permissions,
            'employee'        => $employee,
            'avatar_url'      => $avatarUrl,
            'departments'     => $departments,
            'department_ids'  => $employee?->department_ids ?? [],
            'division'        => $division?->name,
            'position'        => null,
            'employee_id'     => $employee?->id ?? null,
        ];
    }
}