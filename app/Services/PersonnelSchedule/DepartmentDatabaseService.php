<?php

namespace App\Services\PersonnelSchedule;

use App\Models\PschedDivision;
use App\Models\PschedUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentDatabaseService
{
    public function createDivision(array $data): PschedDivision
    {
        $name = $this->cleanName($data['name']);
        $this->assertDivisionNameFree($name);

        return PschedDivision::create([
            'name' => $name,
            'type' => $data['type'],
            'status' => $data['status'] ?? 'ACTIVE',
            'sort_order' => ((int) PschedDivision::max('sort_order')) + 1,
        ]);
    }

    public function updateDivision(PschedDivision $division, array $data): PschedDivision
    {
        $name = $this->cleanName($data['name']);
        $this->assertDivisionNameFree($name, $division->id);

        return DB::transaction(function () use ($division, $data, $name) {
            $division->update([
                'name' => $name,
                'type' => $data['type'],
                'status' => $data['status'],
            ]);

            if ($data['status'] === 'INACTIVE') {
                $division->units()->update(['status' => 'INACTIVE']);
            }

            return $division->refresh();
        });
    }

    public function deleteDivision(PschedDivision $division): void
    {
        if ($division->units()->exists() || $division->lines()->exists()) {
            throw ValidationException::withMessages([
                'division' => ['This Directorate / Division still has units or linked plantilla records.'],
            ]);
        }

        $division->delete();
    }

    public function createUnit(array $data): PschedUnit
    {
        $name = $this->cleanName($data['name']);
        $divisionId = (int) $data['division_id'];
        $status = $data['status'] ?? 'ACTIVE';

        $this->assertUnitNameFree($name, $divisionId);
        $this->assertParentAllowsStatus($divisionId, $status);

        return PschedUnit::create([
            'division_id' => $divisionId,
            'name' => $name,
            'type' => $data['type'],
            'status' => $status,
            'sort_order' => ((int) PschedUnit::max('sort_order')) + 1,
        ]);
    }

    public function updateUnit(PschedUnit $unit, array $data): PschedUnit
    {
        $name = $this->cleanName($data['name']);
        $divisionId = (int) $data['division_id'];

        $this->assertUnitNameFree($name, $divisionId, $unit->id);
        $this->assertParentAllowsStatus($divisionId, $data['status']);

        if ($divisionId !== (int) $unit->division_id && $unit->lines()->exists()) {
            throw ValidationException::withMessages([
                'division_id' => ["This unit has plantilla records, so it can't be moved to another Directorate / Division."],
            ]);
        }

        $unit->update([
            'division_id' => $divisionId,
            'name' => $name,
            'type' => $data['type'],
            'status' => $data['status'],
        ]);

        return $unit->refresh();
    }

    public function deleteUnit(PschedUnit $unit): void
    {
        if ($unit->lines()->exists()) {
            throw ValidationException::withMessages([
                'unit' => ['Linked to plantilla records — mark it Inactive instead.'],
            ]);
        }

        $unit->delete();
    }

    private function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function assertDivisionNameFree(string $name, ?int $ignoreId = null): void
    {
        $taken = PschedDivision::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => ['A Directorate / Division with this name already exists.'],
            ]);
        }
    }

    private function assertUnitNameFree(string $name, int $divisionId, ?int $ignoreId = null): void
    {
        $taken = PschedUnit::query()
            ->where('division_id', $divisionId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => ['This Directorate / Division already has a unit with that name.'],
            ]);
        }
    }

    private function assertParentAllowsStatus(int $divisionId, string $status): void
    {
        if ($status !== 'ACTIVE') {
            return;
        }

        $parentInactive = PschedDivision::query()
            ->where('id', $divisionId)
            ->where('status', 'INACTIVE')
            ->exists();

        if ($parentInactive) {
            throw ValidationException::withMessages([
                'status' => ['The parent Directorate / Division is Inactive. Reactivate it first.'],
            ]);
        }
    }
}
