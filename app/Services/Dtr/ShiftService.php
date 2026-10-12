<?php

namespace App\Services\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Models\DtrEmployeeShift;
use App\Models\DtrShift;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function forEmployee(int $employeeId): ?DtrShift
    {
        $assigned = DtrShift::query()
            ->whereIn('id', DtrEmployeeShift::query()->where('employee_id', $employeeId)->select('shift_id'))
            ->where('is_active', true)
            ->first();

        return $assigned ?? $this->default();
    }

    public function default(): ?DtrShift
    {
        return DtrShift::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function snapshot(?DtrShift $shift): array
    {
        return [
            'shift_id'            => $shift?->id,
            'shift_start'         => $shift?->start_time,
            'shift_end'           => $shift?->end_time,
            'shift_grace_minutes' => $shift?->grace_minutes ?? 0,
        ];
    }

    public function create(array $data): DtrShift
    {
        return DB::transaction(function () use ($data) {
            $this->lockDefaults();

            $shift = DtrShift::create($this->attributes($data));

            if ($shift->is_default) {
                $this->clearOtherDefaults($shift);
            }

            return $shift;
        });
    }

    public function update(DtrShift $shift, array $data): DtrShift
    {
        return DB::transaction(function () use ($shift, $data) {
            $this->lockDefaults();

            $row = DtrShift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail();
            $row->update($this->attributes($data));

            if ($row->is_default) {
                $this->clearOtherDefaults($row);
            }

            return $row;
        });
    }

    public function delete(DtrShift $shift): void
    {
        DB::transaction(function () use ($shift) {
            $row = DtrShift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail();

            if ($row->employeeShifts()->exists() || $row->attendances()->exists()) {
                throw new DtrException('This shift is already in use. Deactivate it instead of deleting it.', 409);
            }

            $row->delete();
        });
    }

    public function assign(array $employeeIds, ?int $shiftId): int
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));

        return DB::transaction(function () use ($ids, $shiftId) {
            if ($shiftId === null) {
                return DtrEmployeeShift::query()->whereIn('employee_id', $ids)->delete();
            }

            $shift = DtrShift::query()->whereKey($shiftId)->where('is_active', true)->lockForUpdate()->first();

            if (! $shift) {
                throw new DtrException('The selected shift is not available.', 422);
            }

            $now = now();

            DtrEmployeeShift::upsert(
                array_map(fn (int $id) => [
                    'employee_id' => $id,
                    'shift_id'    => $shift->id,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ], $ids),
                ['employee_id'],
                ['shift_id', 'updated_at'],
            );

            return count($ids);
        });
    }

    public function label(?DtrShift $shift): ?array
    {
        return $shift ? [
            'id'    => $shift->id,
            'name'  => $shift->name,
            'start' => $shift->start_time,
            'end'   => $shift->end_time,
        ] : null;
    }

    private function attributes(array $data): array
    {
        return [
            'name'          => trim($data['name']),
            'start_time'    => $data['start_time'].':00',
            'end_time'      => $data['end_time'].':00',
            'grace_minutes' => (int) ($data['grace_minutes'] ?? config('dtr.shift.default_grace_minutes')),
            'is_default'    => (bool) ($data['is_default'] ?? false),
            'is_active'     => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function lockDefaults(): void
    {
        DtrShift::query()->where('is_default', true)->lockForUpdate()->get(['id']);
    }

    private function clearOtherDefaults(DtrShift $keep): void
    {
        DtrShift::query()
            ->where('is_default', true)
            ->whereKeyNot($keep->getKey())
            ->update(['is_default' => false]);
    }
}
