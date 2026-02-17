<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\PlantillaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManpowerMappingService
{
  public function build(array $filters): array
  {
    $divisionId     = $filters['division_id'] ?? null;
    $fundingSource  = $filters['funding_source'] ?? null;
    $employmentType = $filters['employment_type'] ?? null;
    $includeSlots   = (bool)($filters['include_slots'] ?? true);

    $unitsQuery = OrgUnit::query()
      ->select('id','division_id','parent_id','name','code','type')
      ->orderBy('id');

    if ($divisionId) $unitsQuery->where('division_id', $divisionId);

    $units = $unitsQuery->get();
    $byParent = $units->groupBy('parent_id');

    $leadersByUnit = DB::table('employee_org_unit_assignments as a')
      ->join('employees as e', 'e.id', '=', 'a.employee_id')
      ->whereIn('a.org_unit_id', $units->pluck('id')->all())
      ->whereIn('a.assignment_role', ['Head','Officer'])
      ->select([
        'a.org_unit_id',
        'a.assignment_role',
        'e.employee_number',
        'e.first_name',
        'e.last_name',
      ])
      ->get()
      ->groupBy('org_unit_id');

    $itemsQuery = PlantillaItem::query()
      ->with([
        'position:id,title,salary_grade,employment_type',
        'employee:id,employee_number,first_name,last_name',
      ])
      ->whereIn('org_unit_id', $units->pluck('id')->all());

    if ($fundingSource) $itemsQuery->where('funding_source', $fundingSource);

    if ($employmentType) {
      $itemsQuery->whereHas('position', fn($q) => $q->where('employment_type', $employmentType));
    }

    $itemsByUnit = $itemsQuery->get()->groupBy('org_unit_id');

    $buildNode = function (OrgUnit $unit) use (&$buildNode, $byParent, $leadersByUnit, $itemsByUnit, $includeSlots) {

      $leader = $this->pickLeader($leadersByUnit[$unit->id] ?? collect());

      $unitItems = $itemsByUnit[$unit->id] ?? collect();
      $stats = $this->computeStats($unitItems);

      $node = [
        'org_unit_id' => $unit->id,
        'name' => $unit->name,
        'type' => $unit->type,
        'head' => $leader,
        'stats' => $stats,
      ];

      if ($includeSlots) {
        $node['slots'] = $this->formatSlots($unitItems);
      }

      $children = $byParent[$unit->id] ?? collect();
      $node['children'] = $children->map(fn($c) => $buildNode($c))->values()->all();

      return $node;
    };

    $roots = $byParent[null] ?? collect();
    return $roots->map(fn($r) => $buildNode($r))->values()->all();
  }

  private function pickLeader(Collection $leaders): array
  {
    $head = $leaders->firstWhere('assignment_role', 'Head');
    if ($head) {
      return [
        'employee_number' => $head->employee_number,
        'name' => trim($head->first_name.' '.$head->last_name),
        'role' => 'Head',
      ];
    }

    $officer = $leaders->firstWhere('assignment_role', 'Officer');
    if ($officer) {
      return [
        'employee_number' => $officer->employee_number,
        'name' => trim($officer->first_name.' '.$officer->last_name),
        'role' => 'Officer-in-Charge',
      ];
    }

    return [
      'employee_number' => '',
      'name' => 'Vacant',
      'role' => 'Vacant',
    ];
  }

  private function computeStats(Collection $items): array
  {
    $totalSlots = $items->count();

    $filled = $items->where('item_status', 'FILLED')->count();
    $forPsb = $items->where('item_status', 'FOR PSB')->count();
    $impending = $items->where('item_status', 'IMPENDING')->count();
    $pendingAppointment = $items->where('item_status', 'PENDING APPOINTMENT')->count();
    $unfilled = $items->where('item_status', 'UNFILLED')->count();

    $pipeline = $forPsb + $impending + $pendingAppointment;
    $vacantTotal = $pipeline + $unfilled;

    return [
      'total_slots' => $totalSlots,
      'filled' => $filled,
      'vacant_total' => $vacantTotal,
      'pipeline' => $pipeline,
      'unfilled' => $unfilled,
      'for_psb' => $forPsb,
      'impending' => $impending,
      'pending_appointment' => $pendingAppointment,
    ];
  }

  private function formatSlots(Collection $items): array
  {
    return $items->map(function ($item) {
      $employee = null;

      if ($item->employee) {
        $employee = [
          'employee_number' => $item->employee->employee_number,
          'name' => trim($item->employee->first_name.' '.$item->employee->last_name),
        ];
      }

      return [
        'item_number' => $item->item_number,
        'position' => $item->position?->title,
        'salary_grade' => $item->position?->salary_grade,
        'employment_type' => $item->position?->employment_type,
        'funding_source' => $item->funding_source,
        'item_status' => $item->item_status,
        'employee' => $employee,
        'filled_at' => optional($item->filled_at)->toDateString(),
        'actual_monthly_salary' => $item->actual_monthly_salary,
        'salary_override_reason' => $item->salary_override_reason,
      ];
    })->values()->all();
  }
}
