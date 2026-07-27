<?php

namespace App\Http\Controllers\Api\Auth;

use App\DTOs\LoginDTO;
use App\DTOs\UserDTO;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Shared\UserRepository;
use App\Services\Auth\AccountNotActivatedException;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegisterService;
use App\Services\Auth\UserFormatterService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly RegisterService $registerService,
        private readonly UserFormatterService $formatter,
        private readonly UserRepository $users,
    ) {
    }

    // ─── Login ─────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->loginService->attempt(LoginDTO::fromArray($data), $request);
        } catch (AccountNotActivatedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'success' => true,
            'token'   => $result['token'],
            'user'    => $result['user'],
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

        $user = $this->registerService->register(UserDTO::fromArray($data), $request);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please wait for HR to activate your account.',
            'user'    => $this->registerService->format($user),
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
        $exists = $this->users->usernameExists($username);

        $suggestions = [];

        if ($exists) {
            $suggestions = $this->buildUsernameSuggestions(
                $username,
                (string) ($request->given_name ?? ''),
                (string) ($request->middle_name ?? ''),
                (string) ($request->last_name ?? ''),
            );
        }

        return response()->json([
            'available'   => !$exists,
            'suggestions' => $suggestions,
        ]);
    }

    private function buildUsernameSuggestions(
        string $username,
        string $givenName,
        string $middleName,
        string $lastName
    ): array {
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

        $taken = $this->users->usernamesTaken($candidates->all());

        return $candidates
            ->reject(fn ($c) => in_array($c, $taken, true))
            ->take(4)
            ->values()
            ->all();
    }

    // ─── Me ─────────────────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $this->users->loadProfile($request->user());

        return response()->json([
            'success' => true,
            'user'    => $this->formatter->format($user),
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
}
