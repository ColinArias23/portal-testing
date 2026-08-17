<?php

namespace App\Services\Auth;

use App\DTOs\LoginDTO;
use App\Support\Broadcasting\SafeBroadcast;
use App\Events\RBAC\UserLoggedInElsewhere;
use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Shared\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginService
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$C6UzMDM.H6dfI/f/IKcEeOKLxU5CZzXm2H.jgqM8B0IHxbLLDzWLu';

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserFormatterService $formatter,
    ) {
    }

    public function attempt(LoginDTO $credentials, Request $request): array
    {
        $user = $this->users->findByUsernameForAuth($credentials->username);

        $passwordValid = Hash::check(
            $credentials->password,
            $user->password ?? self::DUMMY_PASSWORD_HASH
        );

        if (!$user || !$passwordValid) {
            $this->logFailedAttempt($credentials->username, $request);

            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        if ($user->approval_status !== 'APPROVED') {
            $this->logBlockedAttempt($user, $request);

            throw new AccountNotActivatedException();
        }

        $this->invalidateExistingTokens($user);

        $token = $user->createToken($user->username)->plainTextToken;

        ActivityLog::record(
            action: 'auth.login',
            userId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->users->loadProfile($user);

        return [
            'token' => $token,
            'user'  => $this->formatter->format($user),
        ];
    }

    private function invalidateExistingTokens(User $user): void
    {
        if ($user->tokens()->exists()) {
            SafeBroadcast::send(new UserLoggedInElsewhere($user->id));
            $user->tokens()->delete();
        }
    }

    private function logFailedAttempt(string $username, Request $request): void
    {
        ActivityLog::record(
            action: 'auth.login_failed',
            payload: ['username' => $username],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    private function logBlockedAttempt(User $user, Request $request): void
    {
        ActivityLog::record(
            action: 'auth.login_blocked',
            userId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            payload: ['reason' => 'account_not_approved'],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}