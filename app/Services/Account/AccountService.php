<?php

namespace App\Services\Account;

use App\Enums\SignatureLogAction;
use App\Enums\SignatureType;
use App\Models\Employee;
use App\Models\EmployeeSignature;
use App\Models\EmployeeSignatureLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountService
{
    public function employeeFor(User $user): Employee
    {
        $employee = $user->employee;

        abort_unless($employee, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }

    public function updateContactNumber(User $user, string $mobileNo): void
    {
        $employee = $this->employeeFor($user);

        $employee->info()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['mobile_no' => $mobileNo]
        );
    }

    public function signatures(User $user): Collection
    {
        $employee = $this->employeeFor($user);

        return $employee->signatures()->get()->keyBy(fn (EmployeeSignature $s) => $s->type->value);
    }

    public function signatureOfType(User $user, SignatureType $type): ?EmployeeSignature
    {
        return $this->employeeFor($user)->signatures()->where('type', $type->value)->first();
    }

    public function uploadSignature(User $user, SignatureType $type, UploadedFile $file): EmployeeSignature
    {
        $employee = $this->employeeFor($user);
        $disk = config('account.signatures.disk');

        return DB::transaction(function () use ($employee, $type, $file, $disk, $user) {
            $existing = EmployeeSignature::query()
                ->where('employee_id', $employee->id)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            $storedPath = $file->storeAs(
                "employees/{$employee->id}",
                Str::uuid() . '.png',
                $disk
            );

            $signature = EmployeeSignature::updateOrCreate(
                ['employee_id' => $employee->id, 'type' => $type->value],
                [
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'disk' => $disk,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $user->id,
                ]
            );

            if ($existing && $existing->stored_path !== $storedPath) {
                Storage::disk($existing->disk)->delete($existing->stored_path);
            }

            $this->logActivity(
                $employee,
                $user,
                $type,
                $existing ? SignatureLogAction::Updated : SignatureLogAction::Uploaded,
                $file->getClientOriginalName(),
            );

            return $signature;
        });
    }

    public function deleteSignature(User $user, SignatureType $type): void
    {
        $employee = $this->employeeFor($user);

        DB::transaction(function () use ($employee, $type, $user) {
            $signature = EmployeeSignature::query()
                ->where('employee_id', $employee->id)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if (! $signature) {
                return;
            }

            $fileName = $signature->original_filename;

            Storage::disk($signature->disk)->delete($signature->stored_path);
            $signature->delete();

            $this->logActivity($employee, $user, $type, SignatureLogAction::Deleted, $fileName);
        });
    }

    public function streamSignature(User $user, SignatureType $type)
    {
        $signature = $this->signatureOfType($user, $type);

        if (! $signature || ! Storage::disk($signature->disk)->exists($signature->stored_path)) {
            return null;
        }

        return Storage::disk($signature->disk)->response(
            $signature->stored_path,
            $signature->original_filename,
            ['Content-Type' => $signature->mime_type]
        );
    }

    public function activityLog(User $user, int $limit): Collection
    {
        $employee = $this->employeeFor($user);
        $max = config('account.activity_log.max_limit');

        return $employee->signatureLogs()
            ->limit(min($limit, $max))
            ->get();
    }

    private function logActivity(
        Employee $employee,
        User $actor,
        SignatureType $type,
        SignatureLogAction $action,
        ?string $fileName
    ): void {
        EmployeeSignatureLog::create([
            'employee_id' => $employee->id,
            'actor_id' => $actor->id,
            'signature_type' => $type->value,
            'action' => $action->value,
            'original_filename' => $fileName,
        ]);
    }
}
