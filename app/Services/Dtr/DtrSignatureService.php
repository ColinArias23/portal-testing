<?php

namespace App\Services\Dtr;

use App\Enums\SignatureType;
use App\Models\Employee;
use App\Models\User;
use App\Services\Account\AccountService;

class DtrSignatureService
{
    private const VERIFIER_ROLE = 'Medical Center Chief';

    public function __construct(private readonly AccountService $account)
    {
    }

    public function verifierUser(): ?User
    {
        $holders = User::role(self::VERIFIER_ROLE)->approved()->get();

        return $holders->count() === 1 ? $holders->first() : null;
    }

    public function verifier(): ?Employee
    {
        return $this->verifierUser()?->employee;
    }

    public function hasSignature(?Employee $employee): bool
    {
        return $employee !== null
            && $employee->signatures()->where('type', SignatureType::Primary->value)->exists();
    }

    public function stream(?Employee $employee)
    {
        abort_if($employee === null, 404, 'No signature uploaded.');

        $response = $this->account->streamEmployeeSignature($employee, SignatureType::Primary);

        abort_if($response === null, 404, 'No signature uploaded.');

        return $response;
    }
}
