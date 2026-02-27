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

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

// Authentication
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Public utilities (if needed)
Route::get('/employees/all', [EmployeeController::class, 'all']);
Route::get('/pending-users/count', [UserController::class, 'pendingCount']);


/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);


    /*
    |--------------------------------------------------------------------------
    | CORE RESOURCES (CRUD)
    |--------------------------------------------------------------------------
    */

    Route::apiResources([
        'departments'      => DepartmentController::class,
        'divisions'        => DivisionController::class,
        'employees'        => EmployeeController::class,
        'salary-grades'    => SalaryGradeController::class,
        'step-increments'  => StepIncrementController::class,
        'plantilla-items'  => PlantillaItemController::class,
    ]);


    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE ASSIGNMENTS (Transfers / Promotions)
    |--------------------------------------------------------------------------
    */

    Route::prefix('assignments')->group(function () {

        Route::get('/employee/{employee}', [EmployeeAssignmentController::class, 'index']);
        Route::post('/', [EmployeeAssignmentController::class, 'store']);
        Route::put('/end/{assignment}', [EmployeeAssignmentController::class, 'end']);
        Route::delete('/{assignment}', [EmployeeAssignmentController::class, 'destroy']);
    });


    /*
    |--------------------------------------------------------------------------
    | MANPOWER ANALYTICS
    |--------------------------------------------------------------------------
    */

    Route::prefix('manpower')->group(function () {

        Route::get('/tree', [ManpowerMappingController::class, 'tree']);
        Route::get('/report', [ManpowerMappingController::class, 'report']);

        Route::get('/department/{id}', [ManpowerMappingController::class, 'departmentHierarchy']);

        Route::get('/plantilla/count', [ManpowerController::class, 'plantillaCount']);
        Route::get('/cos/count', [ManpowerController::class, 'cosCount']);
        Route::get('/consultant/count', [ManpowerController::class, 'consultantCount']);
        Route::get('/vacant/count', [ManpowerController::class, 'vacantCount']);

        Route::get('/summary', [ManpowerController::class, 'summary']);
        Route::get('/overstaffed', [ManpowerController::class, 'overstaffed']);
        Route::get('/division-analytics', [ManpowerController::class, 'divisionAnalytics']);
    });

});