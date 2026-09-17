<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDocument;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $leaveRequestService)
    {
        $this->middleware('auth:sanctum');

        $this->authorizeResource(LeaveRequest::class, 'leave_request', [
            'except' => ['index', 'store'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $employeeId = $request->user()->employee_id;

        if (! $employeeId) {
            $empty = new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                $request->integer('per_page', 15),
                1
            );

            return LeaveRequestResource::collection($empty)->response();
        }

        $query = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->with([
                'leaveType',
                'employee.primaryDepartmentAssignment.department.division',
                'employee.primaryDepartmentAssignment.department.parentDivision',
                'approvalSteps.approver',
                'rescheduledFrom',
                'rescheduledTo',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', $request->input('leave_type_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('end_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('start_date', '<=', $request->input('date_to')))
            ->latest();

        $paginated = $query->paginate($request->integer('per_page', 15));

        return LeaveRequestResource::collection($paginated)->response();
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', [LeaveRequest::class, (int) $request->input('employee_id')]);

        $data = $request->validated();

        try {
            $leaveRequest = DB::transaction(function () use ($request, $data) {
                $leaveRequest = $this->leaveRequestService->submit($data, $request->user());
                $this->storeDocuments($request, $leaveRequest);

                return $leaveRequest->fresh(['leaveType', 'employee', 'approvalSteps.approver', 'documents']);
            });
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['leave_request' => $e->getMessage()]);
        }

        return response()->json(
            ['data' => new LeaveRequestResource($leaveRequest), 'message' => 'Leave request submitted successfully.'],
            201
        );
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->load(['leaveType', 'employee.primaryDepartmentAssignment.department', 'approvalSteps.approver', 'documents', 'logs.actor', 'rescheduledFrom', 'rescheduledTo']);

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $updated = $this->leaveRequestService->cancel($leaveRequest, $request->user(), $request->input('reason'));

        return response()->json(['data' => new LeaveRequestResource($updated), 'message' => 'Leave request cancelled.']);
    }

    public function retract(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('retract', $leaveRequest);

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $updated = $this->leaveRequestService->retract($leaveRequest, $request->user(), $request->input('reason'));

        return response()->json(['data' => new LeaveRequestResource($updated), 'message' => 'Leave request retracted.']);
    }

    public function resubmit(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('resubmit', $leaveRequest);

        try {
            $updated = $this->leaveRequestService->resubmit($leaveRequest, $request->user());
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['leave_request' => $e->getMessage()]);
        }

        return response()->json([
            'data' => new LeaveRequestResource(
                $updated->load(['leaveType', 'employee', 'approvalSteps.approver', 'documents'])
            ),
            'message' => 'Leave request resubmitted for approval.',
        ]);
    }

    public function reschedule(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $newRequest = $this->leaveRequestService->reschedule(
            $leaveRequest,
            ['start_date' => $validated['start_date'], 'end_date' => $validated['end_date']],
            $validated['reason'],
            $request->user(),
        );

        return response()->json([
            'data' => new LeaveRequestResource($newRequest),
            'message' => 'Leave request rescheduled. Your new dates are now pending approval.',
        ]);
    }

    public function downloadDocument(Request $request, LeaveRequest $leaveRequest, LeaveRequestDocument $document): mixed
    {
        $this->authorize('view', $leaveRequest);

        abort_if($document->leave_request_id !== $leaveRequest->id, 404);

        if (! Storage::disk($document->disk)->exists($document->stored_path)) {
            Log::warning('Leave document missing from disk', ['document_id' => $document->id]);
            abort(404, 'File not found.');
        }

        return Storage::disk($document->disk)->download($document->stored_path, $document->original_filename);
    }

    private function storeDocuments(Request $request, LeaveRequest $leaveRequest): void
    {
        $files = $request->file('documents', []);
        $keys = $request->input('document_keys', []);
        $disk = config('leave.uploads.disk');

        foreach ($files as $index => $file) {
            $requirementKey = $keys[$index] ?? 'supporting_document';

            $extension = strtolower($file->getClientOriginalExtension());
            abort_unless(
                in_array($extension, config('leave.uploads.allowed_mimes'), true),
                422,
                "File type .{$extension} is not allowed."
            );

            $storedPath = $file->store("leave-requests/{$leaveRequest->id}", $disk);

            LeaveRequestDocument::create([
                'leave_request_id' => $leaveRequest->id,
                'requirement_key' => $requirementKey,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'disk' => $disk,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }
}