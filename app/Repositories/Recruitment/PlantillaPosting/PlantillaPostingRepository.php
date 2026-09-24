<?php

namespace App\Repositories\Recruitment\PlantillaPosting;

use App\Models\PlantillaPosition;
use App\Models\PlantillaPosting;
use App\Models\StepIncrement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PlantillaPostingRepository
{
    private const LISTING_RELATIONS = ['department.division', 'division', 'salaryGrade', 'stepIncrement'];

    public function newQuery(): Builder
    {
        return PlantillaPosting::withCount('applications')->with(self::LISTING_RELATIONS);
    }

    public function newAdminOpenQuery(): Builder
    {
        return PlantillaPosting::adminOpen()->withCount('applications')->with(self::LISTING_RELATIONS);
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->latest('date_posted')->paginate($perPage);
    }

    public function appliedPostingIds(int $employeeId, Collection $postingIds): array
    {
        return \App\Models\PlantillaPostingApplication::where('employee_id', $employeeId)
            ->whereIn('plantilla_posting_id', $postingIds)
            ->pluck('plantilla_posting_id')
            ->all();
    }

    public function distinctVacantBaseItemNumbers(): Collection
    {
        $activeSlotIds = $this->activePostingSlotIds();

        return PlantillaPosition::selectRaw('DISTINCT base_item_number')
            ->vacant()
            ->when($activeSlotIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $activeSlotIds))
            ->pluck('base_item_number');
    }

    public function vacantItemsData(Collection $baseItemNumbers): array
    {
        if ($baseItemNumbers->isEmpty()) {
            return ['representatives' => collect(), 'vacantSlotsByItem' => collect(), 'stepsByGrade' => collect()];
        }

        $activeSlotIds = $this->activePostingSlotIds();

        $allForItems = PlantillaPosition::whereIn('base_item_number', $baseItemNumbers)
            ->with([
                'displayDepartment:id,name,code,type,division_id,parent_id,head_employee_id',
                'displayDepartment.head:id,first_name,last_name,prefix,suffix',
                'displayDepartment.division:id,name,code,type',
                'displayDepartment.parent:id,name,code,type,division_id,parent_id,head_employee_id',
                'displayDepartment.parent.head:id,first_name,last_name,prefix,suffix',
                'displayDepartment.parent.parent:id,name,code,type,division_id,parent_id,head_employee_id',
                'displayDepartment.parent.parent.head:id,first_name,last_name,prefix,suffix',
                'displayDivision:id,name,code,type,head_employee_id',
                'displayDivision.head:id,first_name,last_name,prefix,suffix',
                'salaryGrade',
                'stepIncrement',
            ])
            ->get();

        $representatives = $allForItems->groupBy('base_item_number')->map->first();

        $vacantSlotsByItem = PlantillaPosition::whereIn('base_item_number', $baseItemNumbers)
            ->vacant()
            ->when($activeSlotIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $activeSlotIds))
            ->with('stepIncrement:id,monthly_salary,annual_salary')
            ->orderBy('position_slot_name')
            ->get(['id', 'base_item_number', 'position_slot_name', 'status', 'salary_grade_id', 'step_increment_id'])
            ->groupBy('base_item_number');

        $gradeIds = $representatives->pluck('salary_grade_id')->filter()->unique()->values();

        $stepsByGrade = $gradeIds->isEmpty()
            ? collect()
            : StepIncrement::whereIn('salary_grade_id', $gradeIds)
                ->orderBy('step')
                ->get(['id', 'salary_grade_id', 'step', 'monthly_salary', 'annual_salary'])
                ->groupBy('salary_grade_id');

        return [
            'representatives'   => $representatives,
            'vacantSlotsByItem' => $vacantSlotsByItem,
            'stepsByGrade'      => $stepsByGrade,
        ];
    }

    public function positionsForBaseItem(string $baseItemNumber, array $ids): Collection
    {
        return PlantillaPosition::where('base_item_number', $baseItemNumber)
            ->whereIn('id', $ids)
            ->orderBy('position_slot_name')
            ->get();
    }

    public function activePostingSlotIds(): Collection
    {
        return \Illuminate\Support\Facades\DB::table('plantilla_posting_slots')
            ->join('plantilla_postings', 'plantilla_postings.id', '=', 'plantilla_posting_slots.plantilla_posting_id')
            ->whereNull('plantilla_postings.deleted_at')
            ->where('plantilla_postings.status', '!=', 'Closed')
            ->whereDate('plantilla_postings.closing_date', '>=', now()->toDateString())
            ->pluck('plantilla_posting_slots.plantilla_position_id')
            ->unique()
            ->values();
    }

    public function slotsAlreadyPosted(array $positionIds): bool
    {
        if (empty($positionIds)) {
            return false;
        }

        return $this->activePostingSlotIds()
            ->intersect($positionIds)
            ->isNotEmpty();
    }

    public function create(array $data): PlantillaPosting
    {
        return PlantillaPosting::create($data);
    }

    public function syncPositions(PlantillaPosting $posting, Collection $positionIds): void
    {
        $posting->plantillaPositions()->sync($positionIds);
    }

    public function loadListingRelations(PlantillaPosting $posting): PlantillaPosting
    {
        return $posting->fresh(self::LISTING_RELATIONS);
    }

    public function findOrFail(int $id): PlantillaPosting
    {
        return PlantillaPosting::withCount('applications')
            ->with([...self::LISTING_RELATIONS, 'stepIncrement'])
            ->findOrFail($id);
    }

    public function find(int $id): PlantillaPosting
    {
        return PlantillaPosting::findOrFail($id);
    }

    public function update(PlantillaPosting $posting, array $data): void
    {
        $posting->update($data);
    }

    public function delete(PlantillaPosting $posting): void
    {
        $posting->delete();
    }
}