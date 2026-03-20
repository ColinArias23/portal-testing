<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DivisionController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\StepIncrementController;
use App\Http\Controllers\Api\PlantillaItemController;
use App\Http\Controllers\Api\ManpowerController;
use App\Http\Controllers\Api\ManpowerMappingController;
use App\Http\Controllers\Api\EmployeeAssignmentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |----------------------------------------------------------------------
    | AUTH
    |----------------------------------------------------------------------
    */
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    /*
    |----------------------------------------------------------------------
    | PROFILE
    |----------------------------------------------------------------------
    */
    Route::prefix('profile')->group(function () {
        Route::get('/status',    [ProfileController::class, 'status']);
        Route::post('/complete', [ProfileController::class, 'complete']);
    });

    /*
    |----------------------------------------------------------------------
    | USER MANAGEMENT
    |----------------------------------------------------------------------
    */
    Route::get('/pending-users',           [UserController::class, 'pendingUsers']);
    Route::get('/pending-users/count',     [UserController::class, 'pendingCount']);
    Route::patch('/users/{user}/activate', [UserController::class, 'activate']);
    Route::post('/users/bulk-activate',      [UserController::class, 'bulkActivate']);

    /*
    |----------------------------------------------------------------------
    | CORE RESOURCES (CRUD)
    |----------------------------------------------------------------------
    */
    Route::get('/employees/non-root', [EmployeeController::class, 'nonRoot']);

    Route::apiResources([
        'departments'     => DepartmentController::class,
        'divisions'       => DivisionController::class,
        'employees'       => EmployeeController::class,
        'salary-grades'   => SalaryGradeController::class,
        'step-increments' => StepIncrementController::class,
        'plantilla-items' => PlantillaItemController::class,
    ]);

    /*
    |----------------------------------------------------------------------
    | PLANTILLA EXTRA
    |----------------------------------------------------------------------
    */
    Route::get('/plantilla-items/{id}/steps', [PlantillaItemController::class, 'steps']);

    /*
    |----------------------------------------------------------------------
    | EMPLOYEE ASSIGNMENTS
    |----------------------------------------------------------------------
    */
    Route::prefix('assignments')->group(function () {
        Route::get('/employee/{employee}',  [EmployeeAssignmentController::class, 'index']);
        Route::post('/',                    [EmployeeAssignmentController::class, 'store']);
        Route::put('/end/{assignment}',     [EmployeeAssignmentController::class, 'end']);
        Route::delete('/{assignment}',      [EmployeeAssignmentController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | MANPOWER ANALYTICS
    |----------------------------------------------------------------------
    */
    Route::prefix('manpower')->group(function () {
        Route::get('/tree',               [ManpowerMappingController::class, 'tree']);
        Route::get('/report',             [ManpowerMappingController::class, 'report']);
        Route::get('/department/{id}',    [ManpowerMappingController::class, 'departmentHierarchy']);
        Route::get('/summary',            [ManpowerController::class, 'summary']);
        Route::get('/overstaffed',        [ManpowerController::class, 'overstaffed']);
        Route::get('/division-analytics', [ManpowerController::class, 'divisionAnalytics']);
    });

});