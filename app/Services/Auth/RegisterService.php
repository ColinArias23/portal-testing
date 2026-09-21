<?php

namespace App\Services\Auth;

use App\DTOs\UserDTO;
use App\Support\Broadcasting\SafeBroadcast;
use App\Events\RBAC\UserRegistered;
use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Shared\EmployeeRepository;
use App\Repositories\Shared\UserRepository;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EmployeeRepository $employees,
        private readonly UserFormatterService $formatter,
        private readonly NotificationService $notifications,
    ) {
    }

    public function register(UserDTO $data, Request $request): User
    {
        $user = DB::transaction(function () use ($data) {
            $employeeId = $this->employees->createBareEmployee([
                'first_name'        => strtoupper($data->givenName),
                'middle_name'       => $data->middleName ? strtoupper($data->middleName) : null,
                'last_name'         => strtoupper($data->lastName),
                'employment_type'   => 'Contract of Service',
                'employment_status' => 'pending',
                'aligned'           => false,
                'expanded'          => false,
            ]);

            return $this->users->create([
                'employee_id'     => $employeeId,
                'username'        => $data->username,
                'email'           => $data->email,
                'password'        => Hash::make($data->password),
                'approval_status' => 'PENDING',
            ]);
        });

        SafeBroadcast::send(new UserRegistered($user->id));
        $this->notifications->notifyAccountPending($user);

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