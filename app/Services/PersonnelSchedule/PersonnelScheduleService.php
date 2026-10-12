<?php

namespace App\Services\PersonnelSchedule;

use App\Models\Employee;
use App\Models\PschedDivision;
use App\Models\PschedLine;
use App\Models\PschedSlot;
use App\Models\PschedUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonnelScheduleService
{
    private const EMPLOYEE_SEARCH_LIMIT = 20;

    public function snapshot(): array
    {
        $divisions = PschedDivision::query()->orderBy('sort_order')->orderBy('id')->get();
        $units = PschedUnit::query()->orderBy('sort_order')->orderBy('id')->get();
        $lines = PschedLine::query()->with('slots')->orderBy('sort_order')->orderBy('id')->get();

        $linkedToUnit = [];
        $linkedToDivision = [];

        foreach ($lines as $line) {
            $count = $line->slots->count();

            if ($line->unit_id) {
                $linkedToUnit[$line->unit_id] = ($linkedToUnit[$line->unit_id] ?? 0) + $count;
            } else {
                $linkedToDivision[$line->division_id] = ($linkedToDivision[$line->division_id] ?? 0) + $count;
            }
        }

        return [
            'divisions' => $divisions->map(fn (PschedDivision $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type,
                'kind' => 'division',
                'status' => $d->status,
                'linked_count' => $linkedToDivision[$d->id] ?? 0,
            ])->values()->all(),

            'departments' => $units->map(fn (PschedUnit $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'type' => $u->type,
                'kind' => 'department',
                'division_id' => $u->division_id,
                'status' => $u->status,
                'linked_count' => $linkedToUnit[$u->id] ?? 0,
            ])->values()->all(),

            'lines' => $lines->map(fn (PschedLine $l) => $this->formatLine($l))->values()->all(),
        ];
    }

    public function updateLine(PschedLine $line, array $data): array
    {
        return DB::transaction(function () use ($line, $data) {
            $existing = $line->slots()->get()->keyBy('id');
            $incoming = collect($data['slots'])->values();
            $incomingIds = $incoming->pluck('id')->filter()->map(fn ($id) => (int) $id);

            if ($incomingIds->diff($existing->keys())->isNotEmpty()
                || $incomingIds->count() !== $incomingIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'slots' => ['One or more slots do not belong to this position.'],
                ]);
            }

            $removed = $existing->reject(fn (PschedSlot $s) => $incomingIds->contains($s->id));
            $assigned = $removed->first(fn (PschedSlot $s) => $s->employee_id !== null);

            if ($assigned) {
                throw ValidationException::withMessages([
                    'slots' => ["Remove the assigned employee from Item {$assigned->item_number} before deleting that slot."],
                ]);
            }

            $line->update([
                'title' => trim(preg_replace('/\s+/', ' ', $data['title'])),
                'salary_grade' => $data['salary_grade'] ?? null,
            ]);

            foreach ($removed as $slot) {
                $slot->delete();
            }

            foreach ($incoming as $index => $row) {
                $itemNumber = trim((string) ($row['item_number'] ?? ''));
                $attributes = [
                    'item_number' => $itemNumber === '' ? null : $itemNumber,
                    'sort_order' => $index + 1,
                ];

                if (! empty($row['id'])) {
                    $existing[(int) $row['id']]->update($attributes);
                } else {
                    $line->slots()->create($attributes);
                }
            }

            return $this->formatLine($line->unsetRelation('slots')->load('slots'));
        });
    }

    public function searchEmployees(?string $search): array
    {
        $terms = collect(preg_split('/\s+/', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY))->take(4);

        $query = Employee::query()->select([
            'id', 'employee_number', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix',
        ]);

        foreach ($terms as $term) {
            $like = '%' . addcslashes($term, '\\%_') . '%';

            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like);
            });
        }

        $employees = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::EMPLOYEE_SEARCH_LIMIT)
            ->get();

        $existing = PschedSlot::query()
            ->with('line:id,title')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        return $employees->map(fn (Employee $e) => [
            'id' => $e->id,
            'employee_number' => $e->employee_number,
            'full_name' => $e->full_name,
            'assigned_to' => $existing->get($e->id, collect())->map(fn (PschedSlot $s) => [
                'slot_id' => $s->id,
                'item_number' => $s->item_number,
                'title' => $s->line?->title,
            ])->values()->all(),
        ])->all();
    }

    public function assign(PschedSlot $slot, int $employeeId, ?int $userId): array
    {
        $employee = Employee::query()->findOrFail($employeeId);

        if ((int) $slot->employee_id === $employee->id) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is already assigned to this item.'],
            ]);
        }

        $slot->update([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'assigned_at' => now(),
            'assigned_by' => $userId,
        ]);

        return $this->formatSlot($slot->refresh());
    }

    public function unassign(PschedSlot $slot): array
    {
        $slot->update([
            'employee_id' => null,
            'employee_number' => null,
            'employee_name' => null,
            'assigned_at' => null,
            'assigned_by' => null,
        ]);

        return $this->formatSlot($slot->refresh());
    }

    private function formatLine(PschedLine $line): array
    {
        return [
            'id' => $line->id,
            'division_id' => $line->division_id,
            'unit_id' => $line->unit_id,
            'title' => $line->title,
            'sg' => $line->salary_grade,
            'slots' => $line->slots->map(fn (PschedSlot $s) => $this->formatSlot($s))->values()->all(),
        ];
    }

    private function formatSlot(PschedSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'line_id' => $slot->line_id,
            'item_number' => $slot->item_number,
            'employee_id' => $slot->employee_id,
            'employee_number' => $slot->employee_number,
            'employee_name' => $slot->employee_name,
            'assigned_at' => $slot->assigned_at?->toIso8601String(),
        ];
    }
}
