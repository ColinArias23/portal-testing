<?php

namespace App\Repositories\Profile;

use App\Models\Employee;
use App\Models\PdsChild;
use App\Models\PdsEducation;
use App\Models\PdsEligibility;
use App\Models\PdsNonAcademicDistinction;
use App\Models\PdsOrganizationMembership;
use App\Models\PdsReference;
use App\Models\PdsSpecialSkill;
use App\Models\PdsTraining;
use App\Models\PdsVoluntaryWork;
use App\Models\PdsWorkExperience;
use App\Models\User;

class ProfileRepository
{
    public function userWithEmployeeInfo(User $user): User
    {
        return $user->load('employee.info');
    }

    public function updateInfo(Employee $employee, array $infoFields): void
    {
        $employee->info()->updateOrCreate(['employee_id' => $employee->id], $infoFields);
    }

    public function updateEmployeeNameFields(Employee $employee, array $nameFields): void
    {
        if ($nameFields) {
            $employee->update($nameFields);
        }
    }

    public function storeAvatar(Employee $employee, \Illuminate\Http\UploadedFile $file): void
    {
        $oldPath = $employee->getRawOriginal('avatar_url');

        if ($oldPath && file_exists(storage_path('app/public/' . $oldPath))) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
        }

        $path = 'avatars/' . \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $employee->update(['avatar_url' => $path]);
    }

    public function syncRows(string $modelClass, int $employeeId, array $rows, callable $map): void
    {
        $modelClass::where('employee_id', $employeeId)->delete();

        foreach ($rows as $i => $row) {
            $modelClass::create(array_merge(
                ['employee_id' => $employeeId, 'order' => $i],
                $map($row)
            ));
        }
    }

    public function syncEducation(int $employeeId, array $rowsByLevel): void
    {
        PdsEducation::where('employee_id', $employeeId)->delete();

        foreach ($rowsByLevel as $level => $rows) {
            foreach ($rows as $i => $row) {
                PdsEducation::create([
                    'employee_id'    => $employeeId,
                    'level'          => $level,
                    'school'         => $row['school']         ?? null,
                    'degree'         => $row['degree']         ?? null,
                    'period_from'    => $row['period_from']    ?? null,
                    'period_to'      => $row['period_to']      ?? null,
                    'highest_level'  => $row['highest_level']  ?? null,
                    'year_graduated' => $row['year_graduated'] ?? null,
                    'honors'         => $row['honors']         ?? null,
                    'order'          => $i,
                ]);
            }
        }
    }
    
    public function childModel(): string { return PdsChild::class; }
    public function eligibilityModel(): string { return PdsEligibility::class; }
    public function workExperienceModel(): string { return PdsWorkExperience::class; }
    public function voluntaryWorkModel(): string { return PdsVoluntaryWork::class; }
    public function trainingModel(): string { return PdsTraining::class; }
    public function specialSkillModel(): string { return PdsSpecialSkill::class; }
    public function nonAcademicDistinctionModel(): string { return PdsNonAcademicDistinction::class; }
    public function organizationMembershipModel(): string { return PdsOrganizationMembership::class; }
    public function referenceModel(): string { return PdsReference::class; }
}
