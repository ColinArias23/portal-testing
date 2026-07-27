<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Services\Recruitment\Hiring\OnboardingService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardings,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.nonplantilla.onboarding.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:hiring.nonplantilla.onboarding.manage')->only(['store', 'update', 'destroy', 'toggleTask', 'addTask', 'updateTask', 'deleteTask']);
    }

    // ─── Summary ─────────────────────────────────────────────────────
    public function summary()
    {
        return response()->json($this->onboardings->summary());
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $paginated = $this->onboardings->index($request);

        return response()->json([
            'data'       => $paginated->getCollection()->map(fn ($o) => $this->onboardings->format($o)),
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'position'     => ['nullable', 'string', 'max:100'],
            'department'   => ['nullable', 'string', 'max:100'],
            'start_date'   => ['nullable', 'date'],
            'employee_id'  => ['nullable', 'exists:employees,id'],
            'applicant_id' => ['nullable', 'exists:applicants,id'],
            'tasks'        => ['nullable', 'array'],
            'tasks.*'      => ['string'],
        ]);

        return response()->json($this->onboardings->format($this->onboardings->store($request, $data)), 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Onboarding $onboarding)
    {
        return response()->json($this->onboardings->format($this->onboardings->show($onboarding)));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:150'],
            'position'   => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable'],
            'status'     => ['nullable', 'in:Pending,In Progress,Completed'],
        ]);

        return response()->json($this->onboardings->format($this->onboardings->update($request, $onboarding, $data)));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Onboarding $onboarding)
    {
        $this->onboardings->destroy($request, $onboarding);

        return response()->json(['message' => 'Onboarding record deleted.']);
    }

    // ─── ToggleTask ─────────────────────────────────────────────────────
    public function toggleTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        if (! $this->onboardings->assertTaskBelongsToOnboarding($task, $onboarding)) {
            return response()->json(['message' => 'Task does not belong to this onboarding record.'], 404);
        }

        return response()->json($this->onboardings->format($this->onboardings->toggleTask($request, $onboarding, $task)));
    }

    // ─── AddTask ─────────────────────────────────────────────────────
    public function addTask(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150']]);

        return response()->json($this->onboardings->format($this->onboardings->addTask($request, $onboarding, $data['title'])));
    }

    // ─── UpdateTask ─────────────────────────────────────────────────────
    public function updateTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        if (! $this->onboardings->assertTaskBelongsToOnboarding($task, $onboarding)) {
            return response()->json(['message' => 'Task does not belong to this onboarding record.'], 404);
        }

        $data = $request->validate(['title' => ['required', 'string', 'max:150']]);

        return response()->json($this->onboardings->format($this->onboardings->updateTask($request, $onboarding, $task, $data['title'])));
    }

    // ─── DeleteTask ─────────────────────────────────────────────────────
    public function deleteTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        if (! $this->onboardings->assertTaskBelongsToOnboarding($task, $onboarding)) {
            return response()->json(['message' => 'Task does not belong to this onboarding record.'], 404);
        }

        return response()->json($this->onboardings->format($this->onboardings->deleteTask($request, $onboarding, $task)));
    }
}
