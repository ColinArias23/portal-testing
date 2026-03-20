<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function status(Request $request)
    {
        $user     = $request->user()->load('employee.info');
        $employee = $user->employee;

        $missing = [];

        if (!$employee || !$employee->first_name) {
            $missing['first_name'] = 'First Name';
        }
        if (!$employee || !$employee->middle_name) {
            $missing['middle_name'] = 'Middle Name';
        }
        if (!$employee || !$employee->last_name) {
            $missing['last_name'] = 'Last Name';
        }
        if (!$employee?->info?->birthdate) {
            $missing['date_of_birth'] = 'Date of Birth';
        }
        if (!$employee?->info?->address) {
            $missing['address'] = 'Address';
        }
        if (!$employee?->info?->contact) {
            $missing['phone'] = 'Phone Number';
        }
        if (!$employee?->info?->gender) {
            $missing['gender'] = 'Gender';
        }

        $rawAvatarPath = $employee?->getRawOriginal('avatar_url');
        $avatarExists  = $rawAvatarPath && file_exists(storage_path('app/public/' . $rawAvatarPath));
        if (!$avatarExists) {
            $missing['avatar'] = 'Avatar';
        }

        return response()->json([
            'is_complete'    => empty($missing),
            'missing_fields' => $missing,
            'status'         => $employee?->info?->status ?? 'EMPTY',
            'user'           => $user,
        ]);
    }

    public function complete(Request $request)
    {
        $data = $request->validate([
            'first_name'    => ['sometimes', 'string', 'max:255'],
            'middle_name'   => ['sometimes', 'string', 'max:255'],
            'last_name'     => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'date'],
            'address'       => ['sometimes', 'string', 'max:500'],
            'phone'         => ['sometimes', 'string', 'max:50'],
            'gender'        => ['sometimes', 'string', 'in:MALE,FEMALE'],
            'avatar'        => ['sometimes', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $user     = $request->user()->fresh()->load('employee.info');
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to this account.'], 422);
        }

        if ($request->hasFile('avatar')) {
            $oldPath = $employee->getRawOriginal('avatar_url');
            if ($oldPath && file_exists(storage_path('app/public/' . $oldPath))) {
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('avatar');
            $path = 'avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

            $employee->update(['avatar_url' => $path]);
        }

        $employeeFields = array_filter([
            'first_name'  => $data['first_name']  ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'last_name'   => $data['last_name']   ?? null,
        ]);

        if ($employeeFields) {
            $employee->update($employeeFields);
        }

        $infoFields = array_filter([
            'birthdate' => $data['date_of_birth'] ?? null,
            'address'   => $data['address']       ?? null,
            'contact'   => $data['phone']         ?? null,
            'gender'    => $data['gender']        ?? null,
        ]);

        $employee->info()->updateOrCreate(
            ['employee_id' => $employee->id],
            $infoFields
        );

        $employee->refresh()->load('info');
        $info = $employee->info;

        if (!$info) {
            return response()->json([
                'message' => 'Profile updated successfully.',
                'status'  => 'INCOMPLETE',
            ]);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'status'  => $info->status ?? 'EMPTY',
        ]);
    }
}