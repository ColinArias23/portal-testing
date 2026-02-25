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
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| PUBLIC AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/employees/all', [EmployeeController::class, 'all']);
Route::get('/pending-users/count', [UserController::class, 'pendingCount']);

/*
|--------------------------------------------------------------------------
| PUBLIC MANPOWER ROUTES (NO AUTH)
|--------------------------------------------------------------------------
*/

Route::prefix('manpower')->group(function () {
    Route::get('/tree', [ManpowerMappingController::class, 'tree']);
    Route::get('/report', [ManpowerMappingController::class, 'report']);
    Route::get('/plantilla/count', [ManpowerController::class, 'plantillaCount']);
    Route::get('/cos/count', [ManpowerController::class, 'cosCount']);
    Route::get('/consultant/count', [ManpowerController::class, 'consultantCount']);
    Route::get('/vacant/count', [ManpowerController::class, 'vacantCount']);
    Route::get('/summary', [ManpowerController::class, 'summary']);
    Route::get('/overstaffed', [ManpowerController::class, 'overstaffed']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Core Resources (CRUD secured)
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('divisions', DivisionController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('salary-grades', SalaryGradeController::class);
    Route::apiResource('step-increments', StepIncrementController::class);
    Route::apiResource('plantilla-items', PlantillaItemController::class);
});