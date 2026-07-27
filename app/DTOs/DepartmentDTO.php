<?php

namespace App\DTOs;

/**
 * Deliberately thin: the original controller passed the raw validated
 * array straight into create()/update(), relying on Laravel's
 * `sometimes` validation rule to omit keys the client didn't send (so a
 * partial update only touches the fields provided). A strongly-typed
 * DTO with nullable properties for every column would lose that
 * "was this key even sent" distinction and risk silently nulling out
 * fields on partial updates. So this DTO only separates out
 * `employee_ids` (which is synced separately against the Employee
 * table, not written to the departments row) and leaves the rest as-is.
 */
final class DepartmentDTO
{
    /** @param array<int> $employeeIds */
    public function __construct(
        public readonly array $attributes,
        public readonly ?array $employeeIds,
        public readonly bool $employeeIdsProvided,
    ) {
    }

    public static function fromValidated(array $validated, bool $employeeIdsProvided): self
    {
        return new self(
            attributes: collect($validated)->except('employee_ids')->toArray(),
            employeeIds: $validated['employee_ids'] ?? null,
            employeeIdsProvided: $employeeIdsProvided,
        );
    }
}
