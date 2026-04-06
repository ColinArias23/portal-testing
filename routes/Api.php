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
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\ApplicantDocumentController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\HiringEventController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\OnboardingController;

Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::prefix('profile')->group(function () {
        Route::get('/status',    [ProfileController::class, 'status']);
        Route::post('/complete', [ProfileController::class, 'complete']);
    });

    Route::get('/pending-users',                     [UserController::class, 'pendingUsers']);
    Route::get('/pending-users/count',               [UserController::class, 'pendingCount']);
    Route::patch('/users/{user}/activate',           [UserController::class, 'activate']);
    Route::post('/users/bulk-activate',              [UserController::class, 'bulkActivate']);
    Route::get('/approved-users',                    [UserController::class, 'approvedUsers']);
    Route::put('/users/{user}/permissions',          [UserController::class, 'updatePermissions']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Must be before apiResources to avoid route conflicts
    Route::get('/employees/non-root',     [EmployeeController::class, 'nonRoot']);
    Route::get('/employees/gender-count', [EmployeeController::class, 'genderCount']);

    Route::apiResources([
        'departments'     => DepartmentController::class,
        'divisions'       => DivisionController::class,
        'employees'       => EmployeeController::class,
        'salary-grades'   => SalaryGradeController::class,
        'step-increments' => StepIncrementController::class,
        'plantilla-items' => PlantillaItemController::class,
    ]);

    Route::prefix('notifications')->group(function () {
        Route::get('/',                          [NotificationController::class, 'index']);
        Route::get('/unread-count',              [NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read',            [NotificationController::class, 'markAllRead']);
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markRead']);
    });

    Route::get('/plantilla-items/{id}/steps', [PlantillaItemController::class, 'steps']);

    Route::prefix('assignments')->group(function () {
        Route::get('/employee/{employee}', [EmployeeAssignmentController::class, 'index']);
        Route::post('/',                   [EmployeeAssignmentController::class, 'store']);
        Route::put('/end/{assignment}',    [EmployeeAssignmentController::class, 'end']);
        Route::delete('/{assignment}',     [EmployeeAssignmentController::class, 'destroy']);
    });

    Route::prefix('manpower')->group(function () {
        Route::get('/tree',               [ManpowerMappingController::class, 'tree']);
        Route::get('/report',             [ManpowerMappingController::class, 'report']);
        Route::get('/department/{id}',    [ManpowerMappingController::class, 'departmentHierarchy']);
        Route::get('/summary',            [ManpowerController::class, 'summary']);
        Route::get('/overstaffed',        [ManpowerController::class, 'overstaffed']);
        Route::get('/division-analytics', [ManpowerController::class, 'divisionAnalytics']);
    });

    Route::prefix('trainings')->group(function () {
        Route::get('/summary', [TrainingController::class, 'summary']);

        Route::get('/',    [TrainingController::class, 'index']);
        Route::post('/',   [TrainingController::class, 'store']);

        Route::post('/{training}/join',                      [TrainingController::class, 'join']);
        Route::post('/{training}/participants',              [TrainingController::class, 'assignParticipants']);
        Route::patch('/{training}/participants/{employee}',  [TrainingController::class, 'updateParticipant']);
        Route::delete('/{training}/participants/{employee}', [TrainingController::class, 'removeParticipant']);

        Route::get('/{training}',    [TrainingController::class, 'show']);
        Route::put('/{training}',    [TrainingController::class, 'update']);
        Route::delete('/{training}', [TrainingController::class, 'destroy']);
    });

    Route::prefix('hiring')->group(function () {

    // Applicants
    Route::get('/applicants/summary',          [ApplicantController::class, 'summary']);
    Route::get('/applicants',                  [ApplicantController::class, 'index']);
    Route::post('/applicants',                 [ApplicantController::class, 'store']);
    Route::get('/applicants/{applicant}',      [ApplicantController::class, 'show']);
    Route::put('/applicants/{applicant}',      [ApplicantController::class, 'update']);
    Route::delete('/applicants/{applicant}',   [ApplicantController::class, 'destroy']);

    // Documents
    Route::get('/applicants/{applicant}/documents',                         [ApplicantDocumentController::class, 'index']);
    Route::post('/applicants/{applicant}/documents',                        [ApplicantDocumentController::class, 'store']);
    Route::put('/applicants/{applicant}/documents/{document}',              [ApplicantDocumentController::class, 'update']);
    Route::delete('/applicants/{applicant}/documents/{document}',           [ApplicantDocumentController::class, 'destroy']);
    Route::post('/applicants/{applicant}/move-to-onboarding', [ApplicantController::class, 'moveToOnboarding']);

    // Interviews
    Route::get('/interviews',                          [InterviewController::class, 'index']);
    Route::get('/applicants/{applicant}/interview',    [InterviewController::class, 'show']);
    Route::put('/applicants/{applicant}/interview',    [InterviewController::class, 'update']);

    // Events / Calendar
    Route::get('/events',                    [HiringEventController::class, 'index']);
    Route::post('/events',                   [HiringEventController::class, 'store']);
    Route::put('/events/{hiringEvent}',      [HiringEventController::class, 'update']);
    Route::delete('/events/{hiringEvent}',   [HiringEventController::class, 'destroy']);

    // Contracts
    Route::get('/contracts',                 [ContractController::class, 'index']);
    Route::post('/contracts',                [ContractController::class, 'store']);
    Route::put('/contracts/{contract}',      [ContractController::class, 'update']);
    Route::delete('/contracts/{contract}',   [ContractController::class, 'destroy']);
    });

    Route::prefix('onboarding')->group(function () {
        
    Route::get('/summary',                                          [OnboardingController::class, 'summary']);
    Route::get('/',                                                 [OnboardingController::class, 'index']);
    Route::post('/',                                                [OnboardingController::class, 'store']);
    Route::get('/{onboarding}',                                     [OnboardingController::class, 'show']);
    Route::put('/{onboarding}',                                     [OnboardingController::class, 'update']);
    Route::delete('/{onboarding}',                                  [OnboardingController::class, 'destroy']);

   // Tasks
    Route::post('/{onboarding}/tasks',                    [OnboardingController::class, 'addTask']);
    Route::patch('/{onboarding}/tasks/{task}/toggle',     [OnboardingController::class, 'toggleTask']);
    Route::put('/{onboarding}/tasks/{task}',              [OnboardingController::class, 'updateTask']);
    Route::delete('/{onboarding}/tasks/{task}',           [OnboardingController::class, 'deleteTask']);
    });
});