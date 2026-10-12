<?php

namespace App\Services\Positions;

use App\Models\PlantillaPosition;
use App\Models\PlantillaPositionHistory;

class PlantillaPositionFormatterService
{
    public function buildSlotName(string $baseItemNumber, int $slotNumber, int $totalSlots): string
    {
        return $totalSlots === 1 ? $baseItemNumber : "{$baseItemNumber}-{$slotNumber}";
    }

    public function format(PlantillaPosition $position): array
    {
        $activeAssignments = $position->relationLoaded('activeAssignments')
            ? $position->activeAssignments
            : $position->activeAssignments()->get();

        $sg   = $position->relationLoaded('salaryGrade')      ? $position->salaryGrade      : $position->salaryGrade()->first();
        $step = $position->relationLoaded('stepIncrement')    ? $position->stepIncrement    : $position->stepIncrement()->first();
        $dept = $position->relationLoaded('displayDepartment') ? $position->displayDepartment : $position->displayDepartment()->first();
        $div  = $position->relationLoaded('displayDivision')  ? $position->displayDivision  : $position->displayDivision()->first();

        return [
            'id'                    => $position->id,
            'base_item_number'      => $position->base_item_number,
            'title'                 => $position->title,
            'description'           => $position->description,
            'slot_number'           => $position->slot_number,
            'position_slot_name'    => $position->position_slot_name,
            'position_title'        => $position->position_title,
            'role'                  => $position->role,
            'cti'                   => $position->cti,
            'is_cti'                => $position->is_cti,
            'display_department_id' => $position->display_department_id,
            'display_department'    => $dept ? $dept->only(['id', 'name', 'code']) : null,
            'display_division_id'   => $position->display_division_id,
            'display_division'      => $div ? $div->only(['id', 'name', 'code']) : null,
            'status'                => $position->status,
            'computed_status'       => $position->computed_status,
            'is_assignable'         => $position->is_assignable,
            'is_filled'             => $position->isFilled(),
            'is_unfilled'           => $position->isUnfilled(),
            'vacated_at'            => $position->vacated_at?->toIso8601String(),
            'vacancy_notes'         => $position->vacancy_notes,
            'date_of_assumption'    => $position->date_of_assumption?->toDateString(),
            'salary_grade_id'       => $position->salary_grade_id,
            'step_increment_id'     => $position->step_increment_id,
            'salary_grade'          => $sg ? [
                'id'             => $sg->id,
                'salary_grade'   => $sg->salary_grade,
                'monthly_salary' => $sg->monthly_salary,
                'annual_salary'  => $sg->annual_salary,
            ] : null,
            'step_increment'        => $step ? [
                'id'             => $step->id,
                'step'           => $step->step,
                'monthly_salary' => $step->monthly_salary,
                'annual_salary'  => $step->annual_salary,
            ] : null,
            'has_salary_config'     => $position->has_salary_config,
            'salary_info'           => $position->formatSalaryInfo(),
            'active_assignments'    => $activeAssignments->map(fn ($a) => [
                'id'            => $a->id,
                'employee_id'   => $a->employee_id,
                'employee_name' => $a->employee?->full_name,
                'employee'      => $a->employee ? [
                    'id'          => $a->employee->id,
                    'prefix'      => $a->employee->prefix,
                    'first_name'  => $a->employee->first_name,
                    'middle_name' => $a->employee->middle_name,
                    'last_name'   => $a->employee->last_name,
                    'suffix'      => $a->employee->suffix,
                    'title'       => $a->employee->title,
                ] : null,
                'start_date'    => $a->start_date,
                'end_date'      => $a->end_date,
            ])->toArray(),
            'is_deleted'            => $position->trashed(),
            'deleted_at'            => $position->deleted_at?->toIso8601String(),
            'created_at'            => $position->created_at?->toIso8601String(),
            'updated_at'            => $position->updated_at?->toIso8601String(),
        ];
    }

    public function formatHistory(PlantillaPositionHistory $event): array
    {
        $related     = $event->relatedPosition;
        $application = $event->application;
        $posting     = $application?->posting;

        return [
            'id'             => $event->id,
            'event_type'     => $event->event_type,
            'effective_date' => $event->effective_date?->toDateString(),
            'employee_id'    => $event->employee_id,
            'employee_name'  => $event->employee?->full_name ?? $event->employee_name,
            'remarks'        => $event->remarks,
            'related_position' => ($related || $event->related_position_label) ? [
                'id'    => $event->related_position_id,
                'label' => $related?->position_slot_name ?? $event->related_position_label,
            ] : null,
            'application'    => $application ? [
                'id'                 => $application->id,
                'posting_title'      => $posting?->title,
                'base_item_number'   => $posting?->base_item_number,
                'position_slot_name' => $posting?->position_slot_name,
            ] : null,
            'created_at'     => $event->created_at?->toIso8601String(),
        ];
    }
}
