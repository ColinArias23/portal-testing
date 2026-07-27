<?php

namespace App\Repositories\Training;

use App\Models\Employee;
use App\Models\Training;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingRepository
{
    public function newQuery(?string $search, ?string $status, ?string $department): Builder
    {
        return Training::with('participants')
            ->search($search)
            ->byStatus($status)
            ->byDepartment($department)
            ->orderByDesc('created_at');
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage);
    }

    public function employeeIdForUser(?int $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        return DB::table('users')->where('id', $userId)->value('employee_id');
    }

    public function create(array $data): Training
    {
        return Training::create($data)->load('participants');
    }

    public function update(Training $training, array $data): void
    {
        $training->update($data);
        $training->load('participants');
    }

    public function delete(Training $training): void
    {
        $training->delete();
    }

    public function employeesByIds(array $ids): Collection
    {
        return Employee::whereIn('id', $ids)->get();
    }

    public function syncParticipants(Training $training, array $sync): void
    {
        $training->participants()->sync($sync);
    }

    public function isParticipant(Training $training, int $employeeId): bool
    {
        return $training->participants()->where('employee_id', $employeeId)->exists();
    }

    public function updateParticipantPivot(Training $training, int $employeeId, array $data): void
    {
        $training->participants()->updateExistingPivot($employeeId, $data);
    }

    public function detachParticipant(Training $training, int $employeeId): void
    {
        $training->participants()->detach($employeeId);
    }

    public function attachParticipant(Training $training, int $employeeId, array $pivotData): void
    {
        $training->participants()->attach($employeeId, $pivotData);
    }

    public function hasAttended(Training $training, int $employeeId): bool
    {
        return $training->participants()
            ->where('employee_id', $employeeId)
            ->whereNotNull('training_participants.attended_at')
            ->exists();
    }

    public function participantCount(Training $training): int
    {
        return $training->participants()->count();
    }

    public function loadParticipants(Training $training): Training
    {
        return $training->load('participants');
    }

    public function allWithParticipants(): Collection
    {
        return Training::with('participants')->get();
    }

    public function certificatesIssuedCount(): int
    {
        return DB::table('training_participants')->where('certificate_issued', true)->count();
    }
}
