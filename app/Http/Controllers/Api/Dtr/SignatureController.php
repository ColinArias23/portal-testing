<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Http\Controllers\Controller;
use App\Services\Dtr\DtrSignatureService;
use App\Services\Dtr\EmployeeResolver;

class SignatureController extends Controller
{
    public function __construct(
        private readonly EmployeeResolver $employees,
        private readonly DtrSignatureService $signatures,
    ) {
    }

    public function employee(string $employeeNumber)
    {
        return $this->signatures->stream($this->employees->byNumber($employeeNumber));
    }

    public function verifier()
    {
        return $this->signatures->stream($this->signatures->verifier());
    }
}
