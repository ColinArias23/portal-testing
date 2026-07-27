<?php

namespace App\Repositories\Organization;

use App\Models\EmployeeAssignment;
use Illuminate\Database\Eloquent\Collection;

class EmployeeAssignmentRepository
{
    private const RELATIONS = ['plantillaPosition.salaryGrade', 'stepIncrement'];

    public function forEmployee(int $employeeId): Collection
    {
        return EmployeeAssignment::with(self::RELATIONS)
            ->where('employee_id', $employeeId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function loadRelations(EmployeeAssignment $assignment): EmployeeAssignment
    {
        return $assignment->load(self::RELATIONS);
    }

    public function format(EmployeeAssignment $a): array
    {
        $position = $a->plantillaPosition;
        $step = $a->effectiveStepIncrement();

        return [
            'id'         => $a->id,
            'is_primary' => $a->is_primary,
            'is_active'  => $a->isActive(),
            'start_date' => $a->start_date,
            'end_date'   => $a->end_date,

            'plantilla_position' => $position ? [
                'id'                 => $position->id,
                'base_item_number'   => $position->base_item_number,
                'position_slot_name' => $position->position_slot_name,
                'position_title'     => $position->position_title,
                'status'             => $position->computed_status,
                'salary_grade' => $position->salaryGrade ? [
                    'id'             => $position->salaryGrade->id,
                    'salary_grade'   => $position->salaryGrade->salary_grade,
                    'monthly_salary' => $position->salaryGrade->monthly_salary,
                    'annual_salary'  => $position->salaryGrade->annual_salary,
                ] : null,
            ] : null,

            'step_increment' => $step ? [
                'id'             => $step->id,
                'step'           => $step->step,
                'monthly_salary' => $step->monthly_salary,
                'annual_salary'  => $step->annual_salary,
            ] : null,

            'created_at' => $a->created_at,
            'updated_at' => $a->updated_at,
        ];
    }
}
