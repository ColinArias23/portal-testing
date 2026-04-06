<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index()
    {
        return response()->json(
            Contract::with('applicant')->orderBy('created_at', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'applicant_id'  => ['nullable', 'exists:applicants,id'],
            'employee_name' => ['required', 'string', 'max:150'],
            'position'      => ['nullable', 'string', 'max:100'],
            'salary'        => ['nullable', 'numeric'],
            'start_date'    => ['nullable', 'date'],
            'status'        => ['nullable', 'string', 'max:30'],
        ]);

        $contract = Contract::create($data);

        ActivityLog::record(
            action:       'contract.created',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $contract->id,
            subjectLabel: $contract->employee_name,
            payload:      [
                'position'   => $contract->position,
                'salary'     => $contract->salary,
                'start_date' => $contract->start_date?->toDateTimeString(),
                'status'     => $contract->status,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($contract->load('applicant'), 201);
    }

    public function update(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'applicant_id'  => ['nullable', 'exists:applicants,id'],
            'employee_name' => ['sometimes', 'string', 'max:150'],
            'position'      => ['nullable', 'string', 'max:100'],
            'salary'        => ['nullable', 'numeric'],
            'start_date'    => ['nullable', 'date'],
            // FIX: only allow status update if explicitly sent and valid
            // Prevents frontend from accidentally overwriting 'Signed' → 'Generated'
            'status'        => ['sometimes', 'nullable', 'string', 'in:Draft,Generated,Signed', 'max:30'],
        ]);

        $before = [
            'applicant_id' => $contract->applicant_id,
            'status'       => $contract->status,
            'salary'       => $contract->salary,
            'position'     => $contract->position,
            'start_date'   => $contract->start_date?->toDateTimeString(),
        ];

        // FIX: if the contract is already Signed, preserve its status
        // unless the request is explicitly trying to change it
        if ($contract->status === 'Signed' && !$request->has('status')) {
            unset($data['status']);
        }

        $contract->update($data);

        ActivityLog::record(
            action:       'contract.updated',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $contract->id,
            subjectLabel: $contract->employee_name,
            payload:      [
                'before' => $before,
                'after'  => [
                    'applicant_id' => $contract->applicant_id,
                    'status'       => $contract->status,
                    'salary'       => $contract->salary,
                    'position'     => $contract->position,
                    'start_date'   => $contract->start_date?->toDateTimeString(),
                ],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($contract->fresh()->load('applicant'));
    }

    public function destroy(Request $request, Contract $contract)
    {
        $id    = $contract->id;
        $label = $contract->employee_name;

        $contract->delete();

        ActivityLog::record(
            action:       'contract.deleted',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $id,
            subjectLabel: $label,
            payload:      [
                'position' => $contract->position,
                'status'   => $contract->status,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['message' => 'Contract deleted successfully.']);
    }
}