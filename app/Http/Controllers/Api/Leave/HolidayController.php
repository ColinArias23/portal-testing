<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreHolidayRequest;
use App\Http\Requests\Leave\UpdateHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:leave.holidays.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query()
            ->when($request->filled('year'), fn ($q) => $q->whereYear('date', $request->integer('year')))
            ->orderBy('date');

        return response()->json(['data' => $query->get()]);
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = Holiday::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return response()->json(['data' => $holiday, 'message' => 'Holiday added.'], 201);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $holiday->update($request->validated());

        return response()->json(['data' => $holiday->fresh(), 'message' => 'Holiday updated.']);
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        abort_unless($request->user()->can('leave.holidays.manage'), 403);

        $holiday->delete();

        return response()->json(['message' => 'Holiday removed.']);
    }
}
