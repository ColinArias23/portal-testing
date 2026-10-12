<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\RegisterFaceRequest;
use App\Models\DtrFace;
use App\Models\Employee;
use App\Models\ActivityLog;
use App\Services\Dtr\EmployeeResolver;
use App\Services\Dtr\FaceEngineClient;
use App\Services\Dtr\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceRegistrationController extends Controller
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

    public function index(): JsonResponse
    {
        $faces = DtrFace::query()->with('employee:id,employee_number,first_name,middle_name,last_name')
            ->orderByDesc('updated_at')->paginate(50);

        return response()->json($faces->through(fn (DtrFace $f) => [
            'id'              => $f->id,
            'employee_number' => $f->employee?->employee_number,
            'name'            => $f->employee?->full_name,
            'registered_at'   => $f->updated_at?->toIso8601String(),
        ]));
    }

    public function store(RegisterFaceRequest $request): JsonResponse
    {
        $employee = $this->employees->byNumber($request->validated('employee_number'))
            ?? throw new DtrException('No employee found for that employee number.', 422);

        $frames = $request->validated('images');
        $data   = $this->engine->register($frames, 'employee:'.$employee->id);

        $existing = DtrFace::where('employee_id', $employee->id)->first();
        $oldPath  = $existing?->image_path;

        $face = DtrFace::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'encodings'     => $data['encodings'],
                'image_path'    => $this->images->putDataUrl($frames[0], 'faces'),
                'registered_by' => $request->user()->id,
                'legacy_id'     => null,
            ],
        );
        $this->images->delete($oldPath);

        ActivityLog::record(
            action: $existing ? 'dtr.face.re_registered' : 'dtr.face.registered',
            userId: $request->user()->id,
            subjectType: $employee::class,
            subjectId: $employee->id,
            subjectLabel: $employee->employee_number,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message'          => 'Face saved successfully',
            'name'             => $employee->full_name,
            'frames_used'      => $data['frames_used'],
            'frames_submitted' => $data['frames_submitted'],
        ], $existing ? 200 : 201);
    }

    public function destroy(Request $request, DtrFace $face): JsonResponse
    {
        $employee = $face->employee;
        $path     = $face->image_path;
        $face->delete();
        $this->images->delete($path);

        ActivityLog::record(
            action: 'dtr.face.deleted',
            userId: $request->user()->id,
            subjectType: Employee::class,
            subjectId: $employee?->id,
            subjectLabel: $employee?->employee_number,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['message' => 'Face registration removed.']);
    }
}