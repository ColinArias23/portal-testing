<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Services\Recruitment\Hiring\OnboardingService;
use Illuminate\Http\Request;

class PlantillaOnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardings,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.plantilla.onboarding.view')->only(['index', 'summary']);
        $this->middleware('permission:hiring.plantilla.onboarding.manage')->only(['update', 'destroy', 'toggleTask', 'addTask', 'updateTask', 'deleteTask']);
    }

    public function summary()
    {
        return response()->json($this->onboardings->summary(plantilla: true));
    }

    public function index(Request $request)
    {
        $paginated = $this->onboardings->index($request, plantilla: true);

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

    public function update(Request $request, Onboarding $onboarding)
    {
        $this->ensurePlantilla($onboarding);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:150'],
            'position'   => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
        ]);

        return response()->json($this->onboardings->format($this->onboardings->update($request, $onboarding, $data)));
    }

    public function destroy(Request $request, Onboarding $onboarding)
    {
        $this->ensurePlantilla($onboarding);

        $this->onboardings->destroy($request, $onboarding);

        return response()->json(['message' => 'Onboarding record deleted.']);
    }

    public function toggleTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        $this->ensurePlantilla($onboarding);

        if (! $this->onboardings->assertTaskBelongsToOnboarding($task, $onboarding)) {
            return response()->json(['message' => 'Task does not belong to this onboarding record.'], 404);
        }

        return response()->json($this->onboardings->format($this->onboardings->toggleTask($request, $onboarding, $task)));
    }

    public function addTask(Request $request, Onboarding $onboarding)
    {
        $this->ensurePlantilla($onboarding);

        $data = $request->validate(['title' => ['required', 'string', 'max:150']]);

        return response()->json($this->onboardings->format($this->onboardings->addTask($request, $onboarding, $data['title'])));
    }

    public function updateTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        $this->ensurePlantilla($onboarding);

        if (! $this->onboardings->assertTaskBelongsToOnboarding($task, $onboarding)) {
            return response()->json(['message' => 'Task does not belong to this onboarding record.'], 404);
        }

        $data = $request->validate(['title' => ['required', 'string', 'max:150']]);

        return response()->json($this->onboardings->format($this->onboardings->updateTask($request, $onboarding, $task, $data['title'])));
    }

    public function deleteTask(Request $request, Onboarding $onboarding, OnboardingTask $task)
    {
        $this->ensurePlantilla($onboarding);

        if (! $this->onboardings->assertTaskBelongsToOnboarding($task, $onboarding)) {
            return response()->json(['message' => 'Task does not belong to this onboarding record.'], 404);
        }

        return response()->json($this->onboardings->format($this->onboardings->deleteTask($request, $onboarding, $task)));
    }

    private function ensurePlantilla(Onboarding $onboarding): void
    {
        abort_unless($onboarding->isPlantilla(), 404);
    }
}
