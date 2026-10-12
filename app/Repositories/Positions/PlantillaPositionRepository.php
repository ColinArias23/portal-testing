<?php

namespace App\Repositories\Positions;

use App\Models\PlantillaPosition;
use App\Models\PlantillaPositionHistory;
use App\Models\StepIncrement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PlantillaPositionRepository
{
    private const RELATIONS = [
        'displayDepartment:id,name,code',
        'displayDivision:id,name,code',
        'salaryGrade',
        'stepIncrement',
        'activeAssignments.employee:id,prefix,first_name,middle_name,last_name,suffix,title',
    ];

    public function newQuery(): Builder
    {
        return PlantillaPosition::query()
            ->with(self::RELATIONS)
            ->orderBy('base_item_number')
            ->orderBy('slot_number');
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage);
    }

    public function loadShowRelations(PlantillaPosition $position): PlantillaPosition
    {
        return $position->load([
            'displayDepartment:id,name,code',
            'displayDivision:id,name,code',
            'salaryGrade.stepIncrements',
            'stepIncrement',
            'activeAssignments.employee',
        ]);
    }

    public function loadBasicRelations(PlantillaPosition $position): PlantillaPosition
    {
        return $position->fresh(['displayDepartment', 'displayDivision', 'salaryGrade', 'stepIncrement']);
    }

        public function findSlotNameHolder(string $name, int $exceptId): ?PlantillaPosition
    {
        return PlantillaPosition::withTrashed()
            ->where('position_slot_name', $name)
            ->where('id', '!=', $exceptId)
            ->first();
    }

    public function stepsForSalaryGrade(int $salaryGradeId): Collection
    {
        return StepIncrement::where('salary_grade_id', $salaryGradeId)
            ->orderBy('step')
            ->get(['id', 'step', 'monthly_salary', 'annual_salary']);
    }

    public function purgeTrashedForBaseItem(string $baseItemNumber): void
    {
        PlantillaPosition::onlyTrashed()
            ->where('base_item_number', $baseItemNumber)
            ->forceDelete();
    }

    public function create(array $attributes): PlantillaPosition
    {
        return PlantillaPosition::create($attributes);
    }

    public function findByBaseItemNumber(string $baseItemNumber): ?PlantillaPosition
    {
        return PlantillaPosition::where('base_item_number', $baseItemNumber)->first();
    }

    public function highestSlotNumber(string $baseItemNumber): int
    {
        return PlantillaPosition::where('base_item_number', $baseItemNumber)->max('slot_number') ?? 0;
    }

    public function lockedBaseItemNumbers(): array
    {
        return PlantillaPosition::query()
            ->lockForUpdate()
            ->pluck('base_item_number')
            ->unique()
            ->values()
            ->all();
    }

    public function baseItemNumberExists(string $baseItemNumber): bool
    {
        return PlantillaPosition::where('base_item_number', $baseItemNumber)->exists();
    }

    public function activeForBaseItemNumber(string $baseItemNumber): Collection
    {
        return PlantillaPosition::where('base_item_number', $baseItemNumber)
            ->orderBy('slot_number')
            ->get();
    }

    public function withTrashedForBaseItemNumber(string $baseItemNumber): Collection
    {
        return PlantillaPosition::withTrashed()
            ->where('base_item_number', $baseItemNumber)
            ->orderBy('slot_number')
            ->get();
    }

    public function allForBaseItemNumber(string $baseItemNumber): Collection
    {
        return PlantillaPosition::where('base_item_number', $baseItemNumber)
            ->with(['displayDepartment', 'displayDivision', 'salaryGrade', 'stepIncrement'])
            ->get();
    }

    public function findByIds(array $ids): Collection
    {
        return PlantillaPosition::whereIn('id', $ids)->get();
    }

    public function findTrashed(int $id): ?PlantillaPosition
    {
        return PlantillaPosition::onlyTrashed()->find($id);
    }

    public function findTrashedByIds(array $ids): Collection
    {
        return PlantillaPosition::onlyTrashed()->whereIn('id', $ids)->get();
    }

    public function counts(): array
    {
        return [
            'total_positions' => PlantillaPosition::count(),
            'filled'          => PlantillaPosition::filled()->count(),
            'unfilled'        => PlantillaPosition::unfilled()->count(),
            'overstaffed'     => PlantillaPosition::overstaffed()->count(),
            'deleted'         => PlantillaPosition::onlyTrashed()->count(),
        ];
    }

    public function countsByDepartment(): Collection
    {
        return PlantillaPosition::groupBy('display_department_id')
            ->selectRaw('display_department_id, count(*) as count')
            ->with('displayDepartment:id,name')
            ->get();
    }

    public function distinctUnfilledBaseItems(): Collection
    {
        return PlantillaPosition::selectRaw('DISTINCT base_item_number, title, role, display_department_id, display_division_id')
            ->with(['displayDepartment:id,name,code', 'displayDivision:id,name,code'])
            ->unfilled()
            ->get()
            ->unique('base_item_number');
    }

    public function unfilledCountsByBaseItemNumber(): array
    {
        return PlantillaPosition::unfilled()
            ->selectRaw('base_item_number, count(*) as cnt')
            ->groupBy('base_item_number')
            ->pluck('cnt', 'base_item_number')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function historyFor(PlantillaPosition $position): Collection
    {
        return PlantillaPositionHistory::where('plantilla_position_id', $position->id)
            ->with([
                'employee:id,prefix,first_name,middle_name,last_name,suffix',
                'relatedPosition:id,position_slot_name',
                'application:id,plantilla_posting_id',
                'application.posting:id,title,base_item_number,position_slot_name',
            ])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
    }
}
