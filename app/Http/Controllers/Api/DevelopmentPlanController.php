<?php
 
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Models\DevelopmentPlan;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
 
class DevelopmentPlanController extends Controller
{
    /**
     * GET /api/development-plans
     */
    public function index(Request $request): JsonResponse
    {
        $query = DevelopmentPlan::with(['employee', 'skill', 'assessment']);
 
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }
 
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
 
        $perPage = (int) $request->input('per_page', 15);
 
        return response()->json($query->latest()->paginate($perPage));
    }
 
    /**
     * POST /api/development-plans
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'         => ['required', 'exists:employees,id'],
            'skill_assessment_id' => ['required', 'exists:skill_assessments,id'],
            'skill_id'            => ['required', 'exists:skills,id'],
            'goal'                => ['nullable', 'string'],
            'actions'             => ['nullable', 'array'],
            'resources'           => ['nullable', 'array'],
            'start_date'          => ['required', 'date'],
            'target_date'         => ['required', 'date', 'after:start_date'],
            'status'              => ['nullable', 'in:planned,in_progress,completed,paused'],
        ]);
 
        $plan = DevelopmentPlan::create($validated);
 
        return response()->json([
            'message' => 'Development plan created.',
            'data'    => $plan,
        ], 201);
    }
 
    /**
     * GET /api/development-plans/{id}
     */
    public function show(DevelopmentPlan $plan): JsonResponse
    {
        return response()->json([
            'data' => $plan->load(['employee', 'skill', 'assessment']),
        ]);
    }
 
    /**
     * GET /api/development-plans/employee/{employeeId}
     */
    public function byEmployee(Employee $employee): JsonResponse
    {
        $plans = DevelopmentPlan::where('employee_id', $employee->id)
            ->with(['skill', 'assessment'])
            ->latest()
            ->get();
 
        return response()->json([
            'data' => $plans,
        ]);
    }
 
    /**
     * PUT /api/development-plans/{id}
     */
    public function update(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'goal'                => ['nullable', 'string'],
            'actions'             => ['nullable', 'array'],
            'resources'           => ['nullable', 'array'],
            'start_date'          => ['sometimes', 'date'],
            'target_date'         => ['sometimes', 'date'],
            'status'              => ['sometimes', 'in:planned,in_progress,completed,paused'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
 
        $plan->update($validated);
 
        return response()->json([
            'message' => 'Development plan updated.',
            'data'    => $plan->fresh(),
        ]);
    }
 
    /**
     * PATCH /api/development-plans/{id}/progress
     */
    public function updateProgress(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'notes'               => ['nullable', 'string'],
        ]);
 
        $plan->updateProgress($validated['progress_percentage']);
        
        if ($request->filled('notes')) {
            $plan->notes = $validated['notes'];
            $plan->save();
        }
 
        return response()->json([
            'message' => 'Progress updated.',
            'data'    => $plan->fresh(),
        ]);
    }
 
    /**
     * PATCH /api/development-plans/{id}/complete
     */
    public function complete(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        $plan->markAsCompleted();
 
        return response()->json([
            'message' => 'Development plan completed.',
            'data'    => $plan->fresh(),
        ]);
    }
 
    /**
     * DELETE /api/development-plans/{id}
     */
    public function destroy(DevelopmentPlan $plan): JsonResponse
    {
        $plan->delete();
 
        return response()->json([
            'message' => 'Development plan deleted.',
        ]);
    }
}