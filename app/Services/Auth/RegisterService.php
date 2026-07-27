<?php

namespace App\Services\Auth;

use App\DTOs\UserDTO;
use App\Events\RBAC\UserRegistered;
use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Shared\UserRepository;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserFormatterService $formatter,
    ) {
    }

    public function register(UserDTO $data, Request $request): User
    {
        $user = $this->users->create([
            'username'        => $data->username,
            'email'           => $data->email,
            'password'        => Hash::make($data->password),
            'given_name'      => strtoupper($data->givenName),
            'middle_name'     => $data->middleName ? strtoupper($data->middleName) : null,
            'last_name'       => strtoupper($data->lastName),
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

        return $user;
    }

    public function format(User $user): array
    {
        return $this->formatter->format($user);
    }
}
