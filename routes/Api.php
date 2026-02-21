<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DivisionController;
use App\Http\Controllers\Api\MappingController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\StepIncrementController;
use App\Http\Controllers\Api\PlantillaItemController;
use App\Http\Controllers\Api\ManpowerController;
use App\Http\Controllers\Api\OrgChartController;

// ✅ PUBLIC AUTH ROUTES (NO auth:sanctum here)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']); // optional

// ✅ PROTECTED ROUTES
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ✅ Dashboard
    Route::get('/dashboard', fn () => response()->json(['ok' => true]))
        ->middleware('permission:dashboard.view');

    // ✅ Mapping
    Route::get('/mapping', [MappingController::class, 'index'])
        ->middleware('permission:mapping.view');

    Route::get('/mapping/report', [MappingController::class, 'report'])
        ->middleware('permission:mapping.view');

    // ✅ Manpower counts (connected to your frontend)
    Route::get('/manpower/plantilla/count', [ManpowerController::class, 'plantillaCount'])
        ->middleware('permission:manpower.view');

    Route::get('/manpower/cos/count', [ManpowerController::class, 'cosCount'])
        ->middleware('permission:manpower.view');

    Route::get('/manpower/consultant/count', [ManpowerController::class, 'consultantCount'])
        ->middleware('permission:manpower.view');

    Route::get('/manpower/vacant/count', [ManpowerController::class, 'vacantCount'])
        ->middleware('permission:manpower.view');

    // ✅ one-call endpoint (recommended)
    Route::get('/manpower/summary', [ManpowerController::class, 'summary']);
        // ->middleware('permission:manpower.view');

    Route::get('/orgchart/tree', [OrgChartController::class, 'tree']);
        // ->middleware('permission:manpower.view');

    Route::get('/orgchart/departments', [OrgChartController::class, 'departments']);
        // ->middleware('permission:manpower.view');

    Route::get('/orgchart/divisions', [OrgChartController::class, 'divisions']);
        // ->middleware('permission:manpower.view');   

    // ✅ SUPERADMIN ONLY
    Route::middleware(['role:SuperAdmin'])->group(function () {

        Route::apiResource('departments', DepartmentController::class)
            ->middleware('permission:departments.manage');

        Route::apiResource('divisions', DivisionController::class)
            ->middleware('permission:divisions.manage');

        Route::apiResource('employees', EmployeeController::class)
            ->middleware('permission:employees.manage');

        Route::apiResource('salary-grades', SalaryGradeController::class);
        Route::apiResource('step-increments', StepIncrementController::class);
        Route::apiResource('plantilla-items', PlantillaItemController::class);

        Route::get('/rbac', fn () => response()->json(['rbac' => true]));
    });
});
