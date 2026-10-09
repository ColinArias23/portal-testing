<?php

namespace App\Services\Positions;

use App\Models\ActivityLog;
use App\Models\PlantillaPosition;
use App\Repositories\Positions\PlantillaPositionRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantillaPositionService
{
    public function __construct(
        private readonly PlantillaPositionRepository $positions,
        private readonly PlantillaPositionFormatterService $formatter,
        private readonly PlantillaItemNumberService $itemNumbers,
    ) {
    }

    public function index(Request $request): array
    {
        $query = $this->positions->newQuery();

        if ($request->filled('base_item_number')) {
            $query->where('base_item_number', $request->base_item_number);
        }
        if ($request->filled('department_id')) {
            $query->forDepartment((int) $request->department_id);
        }
        if ($request->filled('display_department_id')) {
            $query->where('display_department_id', $request->display_department_id);
        }
        if ($request->filled('status')) {
            match (strtoupper($request->status)) {
                'VACANT'   => $query->vacant(),
                'FILLED'   => $query->filled(),
                'UNFILLED' => $query->unfilled(),
                default    => null,
            };
        }
        if ($request->filled('salary_grade_id')) {
            $query->where('salary_grade_id', $request->salary_grade_id);
        }
        if ($request->filled('cti')) {
            $query->where('cti', strtoupper($request->cti));
        }
        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        $positions = $this->positions->paginate($query, (int) ($request->per_page ?? 20));

        return [
            'data' => $positions->getCollection()->map(fn (PlantillaPosition $pos) => $this->formatter->format($pos)),
            'pagination' => [
                'total'        => $positions->total(),
                'per_page'     => $positions->perPage(),
                'current_page' => $positions->currentPage(),
                'last_page'    => $positions->lastPage(),
            ],
        ];
    }

    public function show(PlantillaPosition $position): array
    {
        return $this->formatter->format($this->positions->loadShowRelations($position));
    }

    public function steps(PlantillaPosition $position): array
    {
        if (! $position->salary_grade_id) {
            return [];
        }

        return $this->positions->stepsForSalaryGrade($position->salary_grade_id)->all();
    }

    public function store(array $data, Request $request): array
    {
        $slots = $data['slots'];
        unset($data['slots']);

        $renumbered = DB::transaction(function () use ($data, $slots, $request) {
            $renumbered = $this->itemNumbers->makeRoomFor($data['base_item_number']);

            $this->positions->purgeTrashedForBaseItem($data['base_item_number']);

            for ($i = 1; $i <= $slots; $i++) {
                $this->positions->create([
                    ...$data,
                    'slot_number'        => $i,
                    'position_slot_name' => $this->formatter->buildSlotName($data['base_item_number'], $i, $slots),
                    'position_title'     => $data['title'],
                    'status'             => 'VACANT',
                    'requires_approval'  => false,
                ]);
            }

            ActivityLog::record(
                action: 'plantilla_position.batch_created',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectLabel: "{$data['title']} ({$slots} position" . ($slots > 1 ? 's' : '') . ')',
                payload: [
                    'base_item_number'      => $data['base_item_number'],
                    'title'                 => $data['title'],
                    'slots'                 => $slots,
                    'display_department_id' => $data['display_department_id'] ?? null,
                    'display_division_id'   => $data['display_division_id'] ?? null,
                    'role'                  => $data['role'] ?? null,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            if ($renumbered !== []) {
                ActivityLog::record(
                    action: 'plantilla_position.items_renumbered',
                    userId: $request->user()?->id,
                    subjectType: PlantillaPosition::class,
                    subjectLabel: "Item {$data['base_item_number']} inserted",
                    payload: [
                        'inserted_item_number' => $data['base_item_number'],
                        'renumbered'           => $renumbered,
                    ],
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            }

            return $renumbered;
        });

        $positions = $this->positions->allForBaseItemNumber($data['base_item_number']);

        return [
            'message'    => "Created {$slots} position" . ($slots > 1 ? 's' : '') . " for {$data['title']}." . $this->renumberNotice($renumbered),
            'renumbered' => $renumbered,
            'positions'  => $positions->map(fn (PlantillaPosition $pos) => $this->formatter->format($pos)),
        ];
    }

    private function renumberNotice(array $renumbered): string
    {
        if ($renumbered === []) {
            return '';
        }

        $from = min(array_map('intval', array_keys($renumbered)));
        $to   = max(array_map('intval', array_keys($renumbered)));

        return $from === $to
            ? " Existing item {$from} moved to {$renumbered[(string) $from]}."
            : " Existing items {$from}-{$to} moved down by one (now " . ($from + 1) . '-' . ($to + 1) . ').';
    }

    /**
     * @return array{status:int, body:array}
     */
    public function addSlots(array $data, Request $request): array
    {
        $highest  = $this->positions->highestSlotNumber($data['base_item_number']);
        $template = $this->positions->findByBaseItemNumber($data['base_item_number']);

        if (! $template) {
            return ['status' => 404, 'body' => ['message' => 'Plantilla item not found.']];
        }

        $totalSlotsAfterAdd = $highest + $data['count'];

        DB::transaction(function () use ($template, $data, $highest, $totalSlotsAfterAdd, $request) {
            $this->positions->purgeTrashedForBaseItem($template->base_item_number);

            for ($i = 1; $i <= $data['count']; $i++) {
                $newSlotNumber = $highest + $i;

                $this->positions->create([
                    'base_item_number'      => $template->base_item_number,
                    'title'                 => $template->title,
                    'position_title'        => $template->title,
                    'description'           => $template->description,
                    'slot_number'           => $newSlotNumber,
                    'position_slot_name'    => $this->formatter->buildSlotName($template->base_item_number, $newSlotNumber, $totalSlotsAfterAdd),
                    'display_department_id' => $template->display_department_id,
                    'display_division_id'   => $template->display_division_id,
                    'role'                  => $template->role,
                    'cti'                   => $template->cti ?? 'NOT_SPECIFIED',
                    'salary_grade_id'       => $template->salary_grade_id,
                    'step_increment_id'     => $template->step_increment_id,
                    'status'                => 'VACANT',
                    'requires_approval'     => false,
                ]);
            }

            $this->itemNumbers->syncSlotNames($template->base_item_number);

            ActivityLog::record(
                action: 'plantilla_position.slots_added',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectLabel: "{$template->title}",
                payload: [
                    'base_item_number' => $template->base_item_number,
                    'slots_added'      => $data['count'],
                    'new_total'        => $highest + $data['count'],
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return [
            'status' => 200,
            'body' => ['message' => "Added {$data['count']} new slot(s) to {$template->title}."],
        ];
    }

    public function update(Request $request, PlantillaPosition $position, array $data): array
    {
        $before = [
            'salary_grade_id'       => $position->salary_grade_id,
            'step_increment_id'     => $position->step_increment_id,
            'position_title'        => $position->position_title,
            'role'                  => $position->role,
            'cti'                   => $position->cti,
            'display_department_id' => $position->display_department_id,
            'display_division_id'   => $position->display_division_id,
        ];

        if (isset($data['requires_approval'])) {
            $data['status']     = $data['requires_approval'] ? 'UNFILLED' : 'VACANT';
            $data['vacated_at'] = $data['requires_approval'] ? now() : null;
        }

        if ($position->isFilled()) {
            unset($data['role'], $data['cti'], $data['display_department_id'], $data['display_division_id']);
        }

        if (array_key_exists('display_department_id', $data) && ! empty($data['display_department_id'])) {
            $data['display_division_id'] = null;
        } elseif (array_key_exists('display_division_id', $data) && ! empty($data['display_division_id'])) {
            $data['display_department_id'] = null;
        }

        $position->update($data);

        ActivityLog::record(
            action: 'plantilla_position.updated',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $position->id,
            subjectLabel: $position->position_slot_name,
            payload: ['before' => $before, 'after' => [
                'salary_grade_id'       => $position->salary_grade_id,
                'step_increment_id'     => $position->step_increment_id,
                'position_title'        => $position->position_title,
                'role'                  => $position->role,
                'cti'                   => $position->cti,
                'display_department_id' => $position->display_department_id,
                'display_division_id'   => $position->display_division_id,
            ]],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $this->formatter->format($this->positions->loadBasicRelations($position));
    }

    public function destroy(Request $request, PlantillaPosition $position): array
    {
        if ($position->isFilled()) {
            return [
                'status' => 422,
                'body' => ['message' => "Cannot delete [{$position->position_slot_name}] — it is currently FILLED."],
            ];
        }
        if ($position->isUnfilled()) {
            return [
                'status' => 422,
                'body' => ['message' => "Cannot delete [{$position->position_slot_name}] — it is UNFILLED and awaiting approval."],
            ];
        }

        DB::transaction(function () use ($position, $request) {
            $position->delete();

            ActivityLog::record(
                action: 'plantilla_position.deleted',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectId: $position->id,
                subjectLabel: $position->position_slot_name,
                payload: [
                    'base_item_number' => $position->base_item_number,
                    'slot_number'      => $position->slot_number,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            $this->itemNumbers->syncSlotNames($position->base_item_number);
        });

        return ['status' => 200, 'body' => ['message' => 'Position slot deleted.']];
    }

    public function bulkDelete(array $ids, Request $request): array
    {
        $positions  = $this->positions->findByIds($ids);
        $unfillable = $positions->filter(fn ($p) => $p->isFilled() || $p->isUnfilled());

        if ($unfillable->isNotEmpty()) {
            $names = $unfillable->pluck('position_slot_name')->implode(', ');

            return [
                'status' => 422,
                'body' => [
                    'message'          => "Cannot delete these positions (they are FILLED or UNFILLED): {$names}",
                    'unfillable_count' => $unfillable->count(),
                ],
            ];
        }

        DB::transaction(function () use ($positions, $request) {
            $positions->each(fn ($pos) => $pos->delete());
            $positions->pluck('base_item_number')->unique()->each(fn ($base) => $this->itemNumbers->syncSlotNames($base));

            ActivityLog::record(
                action: 'plantilla_position.bulk_deleted',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                payload: ['count' => $positions->count(), 'positions' => $positions->pluck('position_slot_name')],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return [
            'status' => 200,
            'body' => [
                'message' => "Deleted {$positions->count()} position slot(s).",
                'count'   => $positions->count(),
            ],
        ];
    }

    public function restore(int $id, Request $request): array
    {
        $position = $this->positions->findTrashed($id);

        if (! $position) {
            return ['status' => 404, 'body' => ['message' => 'Position not found (or not deleted).']];
        }

        DB::transaction(function () use ($position, $request) {
            $position->restore();
            $this->itemNumbers->syncSlotNames($position->base_item_number);

            ActivityLog::record(
                action: 'plantilla_position.restored',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectId: $position->id,
                subjectLabel: $position->position_slot_name,
                payload: ['base_item_number' => $position->base_item_number, 'slot_number' => $position->slot_number],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return [
            'status' => 200,
            'body' => [
                'message'  => 'Position restored.',
                'position' => $this->formatter->format($this->positions->loadBasicRelations($position)),
            ],
        ];
    }

    public function bulkRestore(array $ids, Request $request): array
    {
        $positions = $this->positions->findTrashedByIds($ids);

        if ($positions->isEmpty()) {
            return ['status' => 404, 'body' => ['message' => 'No deleted positions found with those IDs.']];
        }

        DB::transaction(function () use ($positions, $request) {
            $positions->each(fn ($pos) => $pos->restore());
            $positions->pluck('base_item_number')->unique()->each(fn ($base) => $this->itemNumbers->syncSlotNames($base));

            ActivityLog::record(
                action: 'plantilla_position.bulk_restored',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                payload: ['count' => $positions->count(), 'positions' => $positions->pluck('position_slot_name')],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return [
            'status' => 200,
            'body' => [
                'message' => "Restored {$positions->count()} position slot(s).",
                'count'   => $positions->count(),
            ],
        ];
    }

    public function approve(PlantillaPosition $position, Request $request): array
    {
        if (! $position->requires_approval) {
            return ['status' => 422, 'body' => ['message' => 'This position does not require approval.']];
        }

        $position->approveForRefilling();

        ActivityLog::record(
            action: 'plantilla_position.approved',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $position->id,
            subjectLabel: $position->position_slot_name,
            payload: ['approved_by' => $request->user()?->id],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return [
            'status' => 200,
            'body' => [
                'message'  => 'Position approved and reopened for assignment.',
                'position' => $this->formatter->format($this->positions->loadBasicRelations($position)),
            ],
        ];
    }

    public function summary(): array
    {
        $counts = $this->positions->counts();

        return [
            ...$counts,
            'by_department' => $this->positions->countsByDepartment()->map(fn ($item) => [
                'department_id' => $item->display_department_id,
                'department'    => $item->displayDepartment?->name,
                'count'         => $item->count,
            ]),
        ];
    }

    public function salaryConfig(PlantillaPosition $position): array
    {
        $position->load(['salaryGrade.stepIncrements', 'stepIncrement']);

        return $position->formatSalaryInfo();
    }

    public function availableItems(): array
    {
        $items         = $this->positions->distinctVacantBaseItems();
        $vacantCounts  = $this->positions->vacantCountsByBaseItemNumber();

        return $items
            ->map(fn ($pos) => [
                'base_item_number' => $pos->base_item_number,
                'title'            => $pos->title,
                'role'             => $pos->role,
                'department'       => $pos->displayDepartment
                    ? $pos->displayDepartment->only(['id', 'name', 'code'])
                    : null,
                'division'         => $pos->displayDivision
                    ? $pos->displayDivision->only(['id', 'name', 'code'])
                    : null,
                'available_count'  => $vacantCounts[$pos->base_item_number] ?? 0,
            ])
            ->values()
            ->all();
    }
}