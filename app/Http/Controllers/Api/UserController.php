<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function () use ($user) {
            $this->linkEmployee($user);

            DB::table('users')->where('id', $user->id)->update([
                'approval_status' => 'APPROVED',
                'approved_at'     => now()->toDateTimeString(),
            ]);
        });

        return response()->json([
            'message' => 'User activated successfully.',
            'user'    => User::with('employee.info')->find($user->id),
        ]);
    }

    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['exists:users,id'],
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = User::whereIn('id', $request->ids)
            ->where('approval_status', 'PENDING')
            ->get();

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                /** @var User $user */
                $this->linkEmployee($user);

                DB::table('users')->where('id', $user->id)->update([
                    'approval_status' => 'APPROVED',
                    'approved_at'     => now()->toDateTimeString(),
                ]);
            }
        });

        return response()->json([
            'message' => "{$users->count()} account(s) activated successfully.",
        ]);
    }

    private function linkEmployee(User $user): void
    {
        if ($user->employee_id) return;

        $now = now()->toDateTimeString();

        $employeeId = DB::table('employees')->insertGetId([
            'first_name'      => null,
            'last_name'       => null,
            'employment_type' => 'Contract of Service',
            'aligned'         => false,
            'expanded'        => false,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        DB::table('employee_info')->insert([
            'employee_id' => $employeeId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        DB::table('users')->where('id', $user->id)->update([
            'employee_id' => $employeeId,
        ]);
    }
}