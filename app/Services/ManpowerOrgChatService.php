<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\PlantillaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManpowerOrgChartService
{
  public function build(?int $divisionId = null): array
  {
    $unitsQuery = OrgUnit::query()
      ->select('id','division_id','parent_id','code','name','type')
      ->orderBy('id');

    if ($divisionId) $unitsQuery->where('division_id', $divisionId);

    $units = $unitsQuery->get();
    $byParent = $units->groupBy('parent_id');

    // leaders per unit (Head/Officer)
    $leadersByUnit = DB::table('employee_org_unit_assignments as a')
      ->join('employees as e', 'e.id', '=', 'a.employee_id')
      ->whereIn('a.org_unit_id', $units->pluck('id')->all())
      ->whereIn('a.assignment_role', ['Head','Officer'])
      ->select([
        'a.org_unit_id',
        'a.assignment_role',
        'e.employee_number',
        'e.first_name',
        'e.middle_name',
        'e.last_name',
        'e.suffix',
        'e.sex',
      ])
      ->get()
      ->groupBy('org_unit_id');

    // plantilla per unit (for staff list)
    $plantillaByUnit = PlantillaItem::query()
      ->with([
        'position:id,title,employment_type',
        'employee:id,employee_number,first_name,middle_name,last_name,suffix,sex',
      ])
      ->whereIn('org_unit_id', $units->pluck('id')->all())
      ->get()
      ->groupBy('org_unit_id');

    $buildNode = function (OrgUnit $unit, ?OrgUnit $parent = null) use (&$buildNode, $byParent, $leadersByUnit, $plantillaByUnit) {
      $deployment = $parent?->name ?? '';

      $leaders = $leadersByUnit[$unit->id] ?? collect();
      $leader  = $this->pickLeader($leaders);

      $data = $this->formatNodeData($unit->name, $deployment, $leader);

      $staff = $this->formatStaffList(
        $plantillaByUnit[$unit->id] ?? collect(),
        $leader['employee_number'] ?? null
      );

      if (!empty($staff)) $data['staff'] = $staff;

      $childrenUnits = $byParent[$unit->id] ?? collect();

      return [
        'type' => 'person',
        'expanded' => true,
        'data' => $data,
        'children' => $childrenUnits->map(fn($c) => $buildNode($c, $unit))->values()->all(),
      ];
    };

    $roots = $byParent[null] ?? collect();
    return $roots->map(fn($r) => $buildNode($r, null))->values()->all();
  }

  private function pickLeader(Collection $leaders): array
  {
    $head = $leaders->firstWhere('assignment_role', 'Head');
    if ($head) return (array) $head;

    $officer = $leaders->firstWhere('assignment_role', 'Officer');
    if ($officer) return (array) $officer;

    return [
      'assignment_role' => 'Vacant',
      'employee_number' => '',
      'first_name' => 'Vacant',
      'middle_name' => '',
      'last_name' => '',
      'suffix' => '',
      'sex' => '',
    ];
  }

  private function formatNodeData(string $department, string $deployment, array $leader): array
  {
    $firstName  = $leader['first_name'] ?? 'Vacant';
    $middleName = $leader['middle_name'] ?? '';
    $lastName   = $leader['last_name'] ?? '';
    $suffix     = $leader['suffix'] ?? '';
    $middleInitial = $this->middleInitial($middleName);

    $assignment = $leader['assignment_role'] ?? 'Vacant';
    $role = match ($assignment) {
      'Head' => 'Head',
      'Officer' => 'Officer-in-Charge',
      default => 'Vacant',
    };

    return [
      'department'     => $department,
      'deployment'     => $deployment,
      'plantillaItem'  => '',

      'prefix'         => '',
      'firstName'      => $firstName,
      'middleName'     => $middleName,
      'middleInitial'  => $middleInitial,
      'lastName'       => $lastName,
      'suffix'         => $suffix,
      'postTitle'      => '',

      'role'           => $role,
      'gender'         => $leader['sex'] ?? '',

      'borderColor'    => $this->borderColor($department, $role),
      'employmentType' => 'Plantilla',
      'aligned'        => true,

      'employeeId'     => $leader['employee_number'] ?? '',
      'image'          => $this->avatar($leader),
    ];
  }

  private function formatStaffList(Collection $plantillaItems, ?string $leaderEmployeeNumber): array
  {
    if ($plantillaItems->isEmpty()) return [];

    $staff = [];

    foreach ($plantillaItems as $item) {
      $positionTitle  = $item->position?->title ?? 'Staff';
      $employmentType = $item->position?->employment_type ?? 'Plantilla';

      if ($item->employee) {
        if ($leaderEmployeeNumber && $item->employee->employee_number === $leaderEmployeeNumber) continue;

        $staff[] = [
          'firstName'      => $item->employee->first_name,
          'middleName'     => $item->employee->middle_name ?? '',
          'middleInitial'  => $this->middleInitial($item->employee->middle_name ?? ''),
          'lastName'       => $item->employee->last_name,
          'suffix'         => $item->employee->suffix ?? '',
          'postTitle'      => '',
          'role'           => $positionTitle,
          'employmentType' => $employmentType,
          'employeeId'     => $item->employee->employee_number,
          'image'          => $this->avatar([
            'first_name' => $item->employee->first_name,
            'last_name'  => $item->employee->last_name,
          ]),
        ];
      } else {
        $staff[] = [
          'firstName'      => 'Vacant',
          'middleName'     => '',
          'middleInitial'  => '',
          'lastName'       => '',
          'suffix'         => '',
          'postTitle'      => '',
          'role'           => $positionTitle ?: 'Vacant',
          'employmentType' => 'Vacant',
          'employeeId'     => '',
          'image'          => 'https://cdn-icons-png.flaticon.com/512/147/147147.png',
        ];
      }
    }

    return $staff;
  }

  private function middleInitial(string $middleName): string
  {
    $middleName = trim($middleName);
    return $middleName ? mb_substr($middleName, 0, 1) : '';
  }

  private function borderColor(string $department, string $role): string
  {
    $d = mb_strtolower($department);
    if (str_contains($d, 'chief of hospital')) return 'yellow';
    if (str_contains($d, 'director')) return 'blue';
    if (str_contains($d, 'assistant')) return 'pink';
    if ($role === 'Vacant') return 'gray';
    return 'brown';
  }

  private function avatar(array $person): string
  {
    $first = mb_strtolower($person['first_name'] ?? '');
    $last  = mb_strtolower($person['last_name'] ?? '');
    if ($first === 'vacant' || $last === 'vacant') {
      return 'https://cdn-icons-png.flaticon.com/512/147/147147.png';
    }
    return 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png';
  }
}
