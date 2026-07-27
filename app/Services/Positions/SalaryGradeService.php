<?php

namespace App\Services\Positions;

use App\Models\SalaryGrade;
use App\Repositories\Positions\SalaryGradeRepository;
use Illuminate\Support\Collection;

class SalaryGradeService
{
    public function __construct(
        private readonly SalaryGradeRepository $grades,
    ) {
    }

    public function index(): Collection
    {
        return $this->grades->allForListing();
    }

    public function options(): Collection
    {
        return $this->grades->options();
    }

    public function store(array $data): SalaryGrade
    {
        return $this->grades->create($data);
    }

    public function show(SalaryGrade $grade): SalaryGrade
    {
        return $this->grades->loadStepIncrements($grade);
    }

    public function update(SalaryGrade $grade, array $data): SalaryGrade
    {
        return $this->grades->update($grade, $data);
    }

    public function destroy(SalaryGrade $grade): array
    {
        if ($this->grades->isInUse($grade)) {
            return [
                'status' => 422,
                'body' => ['message' => 'Cannot delete a salary grade that is in use by plantilla items.'],
            ];
        }

        $this->grades->delete($grade);

        return ['status' => 200, 'body' => ['message' => 'Salary grade deleted.']];
    }
}
