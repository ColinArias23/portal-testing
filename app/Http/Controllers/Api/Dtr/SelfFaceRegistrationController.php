<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\RegisterFaceRequest;
use App\Models\Dtr\DtrFace;
use App\Models\Employee;
use App\Models\ActivityLog;
use App\Services\Dtr\EmployeeResolver;
use App\Services\Dtr\FaceEngineClient;
use App\Services\Dtr\ImageStore;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SelfFaceRegistrationController extends Controller
{
    public function __construct(
        private readonly EmployeeResolver $employees,
        private readonly FaceEngineClient $engine,
        private readonly ImageStore $images,
    ) {
    }

    public function lookup(Request $request): JsonResponse
    {
        $employee = $this->employees->byNumber((string) $request->query('employee_number', ''));

        return response()->json([
            'employee' => $employee ? [
                'name'            => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'has_face'        => DtrFace::where('employee_id', $employee->id)->exists(),
            ] : null,
        ]);
    }

    public function store(RegisterFaceRequest $request): JsonResponse
    {
        $employee = $this->employees->byNumber($request->validated('employee_number'))
            ?? throw new DtrException('No employee found for that employee number.', 422);

        $this->ensureNotRegistered($employee);

        $frames = $request->validated('images');
        $data   = $this->engine->register($frames, 'employee:'.$employee->id);
        $path   = $this->images->putDataUrl($frames[0], 'faces');

        try {
            DB::transaction(function () use ($employee, $data, $path, $request) {
                DtrFace::create([
                    'employee_id'   => $employee->id,
                    'encodings'     => $data['encodings'],
                    'image_path'    => $path,
                    'registered_by' => null,
                    'legacy_id'     => null,
                ]);

                ActivityLog::record(
                    action: 'dtr.face.self_registered',
                    subjectType: $employee::class,
                    subjectId: $employee->id,
                    subjectLabel: $employee->employee_number,
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            });
        } catch (UniqueConstraintViolationException) {
            $this->images->delete($path);
            $this->throwAlreadyRegistered();
        } catch (Throwable $e) {
            $this->images->delete($path);
            throw $e;
        }

        return response()->json([
            'message'          => 'Face saved successfully',
            'name'             => $employee->full_name,
            'frames_used'      => $data['frames_used'],
            'frames_submitted' => $data['frames_submitted'],
        ], 201);
    }

    private function ensureNotRegistered(Employee $employee): void
    {
        if (DtrFace::where('employee_id', $employee->id)->exists()) {
            $this->throwAlreadyRegistered();
        }
    }

    private function throwAlreadyRegistered(): never
    {
        throw new DtrException('A face is already registered for this employee. Please contact HR to reset it.', 409);
    }
}