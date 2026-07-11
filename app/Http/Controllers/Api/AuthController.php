<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\UserLoggedInElsewhere;
use App\Events\UserRegistered;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$C6UzMDM.H6dfI/f/IKcEeOKLxU5CZzXm2H.jgqM8B0IHxbLLDzWLu';
// ─── Login ─────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = mb_strtoupper(trim($data['username']));

        $user = User::where('username', $username)->first();

        $passwordValid = Hash::check(
            $data['password'],
            $user->password ?? self::DUMMY_PASSWORD_HASH
        );

        if (!$user || !$passwordValid) {
            ActivityLog::record(
                action: 'auth.login_failed',
                payload: ['username' => $username],
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
            broadcast(new UserLoggedInElsewhere($user->id));
            $user->tokens()->delete();
        }

        $token = $user->createToken($user->username)->plainTextToken;

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
            'employee.primaryDepartmentAssignment.department',
            'employee.primaryAssignment.plantillaPosition',
            'employee.cosPosition',
            'employee.consultantPosition',
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }
// ─── Register ─────────────────────────────────────────────────────
    public function register(Request $request)
    {
        $data = $request->validate([
            'given_name'  => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'username'    => ['required', 'string', 'unique:users,username', 'min:2', 'max:100'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ]);

        $user = User::create([
            'username'         => strtoupper($data['username']),
            'email'            => $data['email'],
            'password'         => Hash::make($data['password']),
            'given_name'       => strtoupper($data['given_name']),
            'middle_name'      => isset($data['middle_name']) ? strtoupper($data['middle_name']) : null,
            'last_name'        => strtoupper($data['last_name']),
            'approval_status'  => 'PENDING',
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
// ─── CheckUsername ─────────────────────────────────────────────────────
    public function checkUsername(Request $request)
    {
        $request->validate([
            'username'    => ['required', 'string', 'max:100'],
            'given_name'  => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['nullable', 'string', 'max:100'],
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

            $candidates = collect($candidates)
                ->unique()
                ->reject(fn ($c) => $c === $username || $c === '')
                ->values();

            $taken = User::whereIn('username', $candidates)->pluck('username')->all();

            $suggestions = $candidates
                ->reject(fn ($c) => in_array($c, $taken, true))
                ->take(4)
                ->values()
                ->all();
        }

        return response()->json([
            'available'   => !$exists,
            'suggestions' => $suggestions,
        ]);
    }
// ─── Me ─────────────────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $request->user()->load([
            'employee.info',
            'employee.division',
            'employee.primaryDepartmentAssignment.department',
            'employee.primaryAssignment.plantillaPosition',
            'employee.cosPosition',
            'employee.consultantPosition',
        ]);

        return response()->json([
            'success' => true,
            'user' => $this->formatUser($user),
        ]);
    }
// ─── Logout ─────────────────────────────────────────────────────
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
// ─── ResolveAvatarUrl ─────────────────────────────────────────────────────
    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $baseDir = realpath(storage_path('app/public'));
        $filePath = realpath(storage_path('app/public/' . $path));

        if (!$baseDir || !$filePath || !str_starts_with($filePath, $baseDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return rtrim(config('app.url'), '/') . '/storage/' . $path;
    }
// ─── FormatUser ─────────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        $employee = $user->employee;
        $division = $employee?->division;
        $primaryDepartment = $employee?->primaryDepartmentAssignment?->department;

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

        $positionLabel = $employee?->position_title;

        $departmentLabel = $primaryDepartment;
        if (!$departmentLabel && !empty($departments)) {
            $departmentLabel = (object) $departments[0];
        }

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
            'employee'            => $employee ? [
                'id'              => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name'       => trim(
                    ($employee->first_name ?? '') . ' ' .
                    ($employee->middle_name ?? '') . ' ' .
                    ($employee->last_name ?? '')
                ),
                'first_name'         => $employee->first_name,
                'middle_name'        => $employee->middle_name,
                'last_name'          => $employee->last_name,
                'employment_status' => $employee->employment_status ?? null,
                'employment_type'   => $employee->employment_type ?? null,
                'position'   => $positionLabel,
                'position_designation' => $employee->position_designation ?? [],
                'role_position'         => $employee->role_position ?? [],
                'department' => $departmentLabel ? [
                    'id'     => $departmentLabel->id,
                    'name'   => $departmentLabel->name,
                ] : null,
            ] : null,
            'avatar_url'      => $avatarUrl,
            'departments'     => $departments,
            'department_ids'  => $employee?->department_ids ?? [],
            'division'        => $division?->name,
            'position'        => $positionLabel,
            'employee_number' => $employee?->employee_number ?? null,
        ];
    }
}