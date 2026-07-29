<?php

namespace App\Services\Recruitment\PlantillaPosting;

use App\Models\PlantillaPosition;
use App\Models\PlantillaPosting;
use App\Repositories\Recruitment\PlantillaPosting\PlantillaPostingRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlantillaPostingService
{
    private const DOC_KEYS = ['letter_of_intent', 'pds', 'work_experience', 'performance_rating', 'eligibility', 'transcript', 'diploma'];

    public function __construct(
        private readonly PlantillaPostingRepository $postings,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        $query = $this->postings->newQuery();
        $this->applyFilters($query, $request);

        return $this->postings->paginate($query, (int) $request->get('per_page', 10));
    }

    public function available(Request $request): LengthAwarePaginator
    {
        $query = $this->postings->newAdminOpenQuery();
        $this->applyFilters($query, $request);

        $candidates = $this->postings->paginate($query, (int) $request->get('per_page', 10));

        $filtered = $candidates->getCollection()
            ->filter(fn (PlantillaPosting $p) => $p->isAvailableForApplication())
            ->values();

        $employeeId = $request->user()?->employee_id ?? $request->user()?->employee?->id;

        $appliedPostingIds = $employeeId
            ? $this->postings->appliedPostingIds($employeeId, $filtered->pluck('id'))
            : [];

        $filtered->each(function (PlantillaPosting $posting) use ($appliedPostingIds) {
            $posting->already_applied = in_array($posting->id, $appliedPostingIds, true);
        });

        $candidates->setCollection($filtered);

        return $candidates;
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('base_item_number', 'like', "%{$search}%")
                    ->orWhere('position_slot_name', 'like', "%{$search}%");
            });
        }
        if ($departmentId = $request->get('department_id')) {
            $query->where('display_department_id', $departmentId);
        }
        if ($divisionId = $request->get('division_id')) {
            $query->where('display_division_id', $divisionId);
        }
        if ($salaryGradeId = $request->get('salary_grade_id')) {
            $query->where('salary_grade_id', $salaryGradeId);
        }
        if ($employmentStatus = $request->get('employment_status')) {
            $query->where('employment_status', $employmentStatus);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
    }

    public function vacantItems(): Collection
    {
        $baseItemNumbers = $this->postings->distinctVacantBaseItemNumbers();
        $data = $this->postings->vacantItemsData($baseItemNumbers);

        return $baseItemNumbers->map(function (string $baseItemNumber) use ($data) {
            $position = $data['representatives']->get($baseItemNumber);

            if (! $position) {
                return null;
            }

            $vacantSlots = ($data['vacantSlotsByItem']->get($baseItemNumber) ?? collect())
                ->map(fn (PlantillaPosition $slot) => [
                    'id'                 => $slot->id,
                    'position_slot_name' => $slot->position_slot_name,
                    'status'             => $slot->status,
                    'salary_grade_id'    => $slot->salary_grade_id,
                    'step_increment_id'  => $slot->step_increment_id,
                    'monthly_salary'     => $slot->stepIncrement?->monthly_salary,
                    'annual_salary'      => $slot->stepIncrement?->annual_salary,
                ]);

            $steps = $position->salary_grade_id
                ? ($data['stepsByGrade']->get($position->salary_grade_id) ?? collect())
                : collect();

            $defaultStep = $position->stepIncrement ?? $steps->first();

            $supervisor = $position->displayDepartment?->head ?? $position->displayDivision?->head;

            return [
                'base_item_number'      => $position->base_item_number,
                'title'                 => $position->title,
                'vacant_count'          => $vacantSlots->count(),

                'display_department_id' => $position->display_department_id,
                'display_department'    => $position->displayDepartment
                    ? $position->displayDepartment->only(['id', 'name', 'code'])
                    : null,
                'display_division_id'   => $position->display_division_id,
                'display_division'      => $position->displayDivision
                    ? $position->displayDivision->only(['id', 'name', 'code'])
                    : null,

                'salary_grade_id' => $position->salary_grade_id,
                'salary_grade'    => $position->salaryGrade ? [
                    'id'           => $position->salaryGrade->id,
                    'salary_grade' => $position->salaryGrade->salary_grade,
                ] : null,

                'step_increment_id' => $defaultStep?->id,
                'step_increments'   => $steps,

                'monthly_salary' => $defaultStep?->monthly_salary,
                'annual_salary'  => $defaultStep?->annual_salary,

                'immediate_supervisor' => $supervisor?->full_name,

                'slots' => $vacantSlots,
            ];
        })->filter()->values();
    }

    public function validatePosting(Request $request, $ignoreId = null): array
    {
        $validated = $request->validate([
            'base_item_number'          => 'required|string|max:50',
            'plantilla_position_ids'    => $ignoreId ? 'sometimes|array' : 'required|array|min:1',
            'plantilla_position_ids.*'  => 'integer|exists:plantilla_positions,id',
            'title'                     => 'required|string|max:255',
            'description'               => 'nullable|string',
            'job_description'           => 'nullable|string',
            'display_department_id'     => 'nullable|exists:departments,id',
            'display_division_id'       => 'nullable|exists:divisions,id',
            'section'                   => 'nullable|string|max:255',
            'salary_grade_id'           => 'nullable|exists:salary_grades,id',
            'step_increment_id'         => 'nullable|exists:step_increments,id',
            'monthly_salary'            => 'nullable|numeric|min:0',
            'annual_salary'             => 'nullable|numeric|min:0',
            'employment_status'         => ['required', Rule::in(['Permanent'])],
            'vacancies'                 => 'required|integer|min:1|max:99',
            'immediate_supervisor'      => 'nullable|string|max:255',
            'qualification_education'   => 'nullable|string',
            'qualification_experience'  => 'nullable|string',
            'qualification_training'    => 'nullable|string',
            'qualification_eligibility' => 'nullable|string',
            'qualification_competency'  => 'nullable|string',
            'required_documents'        => 'required|array',
            'required_documents.*'      => 'boolean',
            'date_posted'               => 'required|date',
            'closing_date'              => 'required|date|after_or_equal:date_posted',
            'expected_appointment_date' => 'nullable|date',
            'status'                    => ['required', Rule::in(['Open', 'Closing Soon', 'Closed'])],
        ]);

        $validated['required_documents'] = collect(self::DOC_KEYS)
            ->mapWithKeys(fn ($key) => [$key => (bool) ($validated['required_documents'][$key] ?? false)])
            ->toArray();

        return $validated;
    }

    public function store(Request $request, array $data): PlantillaPosting
    {
        $slotIds = $data['plantilla_position_ids'];
        unset($data['plantilla_position_ids']);

        $slots = $this->postings->positionsForBaseItem($data['base_item_number'], $slotIds);

        if ($slots->count() !== count($slotIds)) {
            throw ValidationException::withMessages([
                'plantilla_position_ids' => 'One or more selected item numbers could not be matched to this position.',
            ]);
        }

        if ($slots->contains(fn (PlantillaPosition $s) => ! $s->isVacant())) {
            throw ValidationException::withMessages([
                'plantilla_position_ids' => 'One or more selected item numbers is no longer vacant.',
            ]);
        }

        if (count($slotIds) !== (int) $data['vacancies']) {
            throw ValidationException::withMessages([
                'vacancies' => 'Vacancies must equal the number of selected item numbers.',
            ]);
        }

        if ($this->postings->slotsAlreadyPosted($slotIds)) {
            throw ValidationException::withMessages([
                'plantilla_position_ids' => 'May aktibong posting na ang napiling item number. I-close muna ang kasalukuyang posting bago gumawa ng bago para dito.',
            ]);
        }

        $data['position_slot_name'] = $slots->pluck('position_slot_name')->implode(', ');
        $data['created_by']         = $request->user()?->id;

        $posting = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $slots) {
            $posting = $this->postings->create($data);
            $this->postings->syncPositions($posting, $slots->pluck('id'));

            return $posting;
        });

        return $this->postings->loadListingRelations($posting);
    }

    public function show(int $id): PlantillaPosting
    {
        return $this->postings->findOrFail($id);
    }

    public function update(Request $request, int $id): PlantillaPosting
    {
        $posting = $this->postings->find($id);
        $data    = $this->validatePosting($request, $posting->id);

        unset($data['plantilla_position_ids']);
        unset($data['position_slot_name']);

        $this->postings->update($posting, $data);

        return $this->postings->loadListingRelations($posting);
    }

    public function destroy(int $id): void
    {
        $posting = $this->postings->find($id);
        $this->postings->delete($posting);
    }
}