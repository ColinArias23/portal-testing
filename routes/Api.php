<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrientationController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SkillAssessmentController;
use App\Http\Controllers\Api\SkillRecommendationController;
use App\Http\Controllers\Api\DevelopmentPlanController;
use App\Http\Controllers\Api\SkillGapAnalysisController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DivisionController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\StepIncrementController;
use App\Http\Controllers\Api\PlantillaPositionController;
use App\Http\Controllers\Api\EmployeeDepartmentAssignmentController;
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
use App\Http\Controllers\Api\CosPositionController;
use App\Http\Controllers\Api\ConsultantPositionController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReleaseController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login',          [AuthController::class, 'login']);
Route::post('/register',       [AuthController::class, 'register']);
Route::post('/check-username', [AuthController::class, 'checkUsername']);

Route::get('/releases',           [ReleaseController::class, 'index']);
Route::get('/releases/{release}', [ReleaseController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Broadcasting Auth
|--------------------------------------------------------------------------
*/

Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    return Broadcast::auth($request);
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::post('/orientation/complete', [OrientationController::class, 'complete']);

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::prefix('profile')->group(function () {
        Route::get('/status',    [ProfileController::class, 'status']);
        Route::post('/complete', [ProfileController::class, 'complete']);
    });

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get('/pending-users',            [UserController::class, 'pendingUsers']);
    Route::get('/pending-users/count',      [UserController::class, 'pendingCount']);
    Route::patch('/users/{user}/activate',  [UserController::class, 'activate']);
    Route::post('/users/bulk-activate',     [UserController::class, 'bulkActivate']);
    Route::get('/approved-users',           [UserController::class, 'approvedUsers']);
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions']);
    Route::put('/users/{user}/role',        [UserController::class, 'updateRole']);

    /*
    |--------------------------------------------------------------------------
    | Audit Logs
    |--------------------------------------------------------------------------
    */

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Employees
    |--------------------------------------------------------------------------
    */

    Route::get('/employees/non-root',          [EmployeeController::class, 'nonRoot']);
    Route::get('/employees/gender-count',      [EmployeeController::class, 'genderCount']);
    Route::get('/employees/{employee}/avatar', [EmployeeController::class, 'avatar']);

    /*
    |--------------------------------------------------------------------------
    | Employee Department Assignments
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'employees.department-assignments',
        EmployeeDepartmentAssignmentController::class
    )->only(['index', 'store', 'update', 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Skills
    |--------------------------------------------------------------------------
    */

    Route::get('/skills/all', [SkillController::class, 'all']);

    Route::apiResources([
        'skills' => SkillController::class,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Skill Assessments
    |--------------------------------------------------------------------------
    */

    Route::prefix('skill-assessments')->group(function () {
        Route::get('/',                          [SkillAssessmentController::class, 'index']);
        Route::post('/',                         [SkillAssessmentController::class, 'store']);
        Route::get('/employee/{employee}',       [SkillAssessmentController::class, 'byEmployee']);
        Route::get('/{skillAssessment}',         [SkillAssessmentController::class, 'show']);
        Route::put('/{skillAssessment}',         [SkillAssessmentController::class, 'update']);
        Route::delete('/{skillAssessment}',      [SkillAssessmentController::class, 'destroy']);
        Route::post('/{skillAssessment}/submit', [SkillAssessmentController::class, 'submit']);
        Route::post('/{skillAssessment}/review', [SkillAssessmentController::class, 'review']);
    });

    /*
    |--------------------------------------------------------------------------
    | Skill Recommendations
    |--------------------------------------------------------------------------
    */

    Route::prefix('skill-recommendations')->group(function () {
        Route::get('/',                         [SkillRecommendationController::class, 'index']);
        Route::post('/',                        [SkillRecommendationController::class, 'store']);
        Route::get('/skill/{skillId}',          [SkillRecommendationController::class, 'bySkill']);
        Route::get('/{skillRecommendation}',    [SkillRecommendationController::class, 'show']);
        Route::put('/{skillRecommendation}',    [SkillRecommendationController::class, 'update']);
        Route::delete('/{skillRecommendation}', [SkillRecommendationController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Development Plans
    |--------------------------------------------------------------------------
    */

    Route::prefix('development-plans')->group(function () {
        Route::get('/',                             [DevelopmentPlanController::class, 'index']);
        Route::post('/',                            [DevelopmentPlanController::class, 'store']);
        Route::get('/employee/{employee}',          [DevelopmentPlanController::class, 'byEmployee']);
        Route::get('/{developmentPlan}',            [DevelopmentPlanController::class, 'show']);
        Route::put('/{developmentPlan}',            [DevelopmentPlanController::class, 'update']);
        Route::delete('/{developmentPlan}',         [DevelopmentPlanController::class, 'destroy']);
        Route::patch('/{developmentPlan}/progress', [DevelopmentPlanController::class, 'updateProgress']);
        Route::patch('/{developmentPlan}/complete', [DevelopmentPlanController::class, 'complete']);
    });

    /*
    |--------------------------------------------------------------------------
    | Skill Gap Analysis
    |--------------------------------------------------------------------------
    */

    Route::prefix('skill-gap-analysis')->group(function () {
        Route::get('/{skillAssessment}',               [SkillGapAnalysisController::class, 'generate']);
        Route::post('/{skillAssessment}/create-plans', [SkillGapAnalysisController::class, 'createPlans']);
        Route::get('/department/{departmentId}/stats', [SkillGapAnalysisController::class, 'departmentStats']);
        Route::get('/employee/{employee}/summary',     [SkillGapAnalysisController::class, 'employeeSummary']);
        Route::get('/export/{skillAssessment}',        [SkillGapAnalysisController::class, 'export']);
    });

    /*
    |--------------------------------------------------------------------------
    | Core API Resources
    |--------------------------------------------------------------------------
    */

    Route::apiResources([
        'departments'     => DepartmentController::class,
        'divisions'       => DivisionController::class,
        'employees'       => EmployeeController::class,
        'salary-grades'   => SalaryGradeController::class,
        'step-increments' => StepIncrementController::class,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Plantilla Positions
    |--------------------------------------------------------------------------
    */

    Route::prefix('plantilla-positions')->group(function () {

        // !! Order matters: static/specific segments must come before wildcards !!

        Route::get('/',  [PlantillaPositionController::class, 'index']);
        Route::post('/', [PlantillaPositionController::class, 'store']);

        // Static POST routes (before /{plantillaPosition} wildcard)
        Route::post('/add-slots',    [PlantillaPositionController::class, 'addSlots']);
        Route::post('/bulk-delete',  [PlantillaPositionController::class, 'bulkDelete']);
        Route::post('/bulk-restore', [PlantillaPositionController::class, 'bulkRestore']);

        // Static GET routes (before /{plantillaPosition} wildcard)
        Route::get('/summary/all',     [PlantillaPositionController::class, 'summary']);
        Route::get('/available/items', [PlantillaPositionController::class, 'availableItems']);

        // Wildcard sub-paths first, then bare wildcard
        Route::get('/{plantillaPosition}/steps',         [PlantillaPositionController::class, 'steps']);
        Route::get('/{plantillaPosition}/salary-config', [PlantillaPositionController::class, 'salaryConfig']);
        Route::post('/{plantillaPosition}/approve',      [PlantillaPositionController::class, 'approve']);
        Route::patch('/{id}/restore',                    [PlantillaPositionController::class, 'restore']);

        Route::get('/{plantillaPosition}',    [PlantillaPositionController::class, 'show']);
        Route::patch('/{plantillaPosition}',  [PlantillaPositionController::class, 'update']);
        Route::delete('/{plantillaPosition}', [PlantillaPositionController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | COS Positions
    |--------------------------------------------------------------------------
    */

    Route::prefix('cos-positions')->group(function () {
        Route::get('/',                 [CosPositionController::class, 'index']);
        Route::post('/',                [CosPositionController::class, 'store']);
        Route::get('/{cosPosition}',    [CosPositionController::class, 'show']);
        Route::put('/{cosPosition}',    [CosPositionController::class, 'update']);
        Route::delete('/{cosPosition}', [CosPositionController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Consultant Positions
    |--------------------------------------------------------------------------
    */

    Route::prefix('consultant-positions')->group(function () {
        Route::get('/',                        [ConsultantPositionController::class, 'index']);
        Route::post('/',                       [ConsultantPositionController::class, 'store']);
        Route::get('/{consultantPosition}',    [ConsultantPositionController::class, 'show']);
        Route::put('/{consultantPosition}',    [ConsultantPositionController::class, 'update']);
        Route::delete('/{consultantPosition}', [ConsultantPositionController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::prefix('notifications')->group(function () {
        Route::get('/',                          [NotificationController::class, 'index']);
        Route::get('/unread-count',              [NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read',            [NotificationController::class, 'markAllRead']);
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Assignments
    |--------------------------------------------------------------------------
    */

    Route::prefix('assignments')->group(function () {
        Route::get('/employee/{employee}', [EmployeeAssignmentController::class, 'index']);
        Route::post('/',                   [EmployeeAssignmentController::class, 'store']);
        Route::put('/end/{assignment}',    [EmployeeAssignmentController::class, 'end']);
        Route::delete('/{assignment}',     [EmployeeAssignmentController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Manpower
    |--------------------------------------------------------------------------
    */

    Route::prefix('manpower')->group(function () {
        Route::get('/tree',                    [ManpowerMappingController::class, 'tree']);
        Route::get('/report',                  [ManpowerMappingController::class, 'report']);
        Route::get('/departments',             [ManpowerMappingController::class, 'departments']);
        Route::get('/department/{department}', [ManpowerMappingController::class, 'departmentDetail']);

        Route::get('/plantilla-count',         [ManpowerMappingController::class, 'plantillaCount']);
        Route::get('/cos-count',               [ManpowerMappingController::class, 'cosCount']);
        Route::get('/consultant-count',        [ManpowerMappingController::class, 'consultantCount']);
        Route::get('/vacant-count',            [ManpowerMappingController::class, 'vacantCount']);

        Route::get('/summary',                 [ManpowerMappingController::class, 'summary']);
        Route::get('/overstaffed',             [ManpowerMappingController::class, 'overstaffed']);
        Route::get('/division-analytics',      [ManpowerMappingController::class, 'divisionAnalytics']);
    });

    /*
    |--------------------------------------------------------------------------
    | Trainings
    |--------------------------------------------------------------------------
    */

    Route::prefix('trainings')->group(function () {
        Route::get('/summary', [TrainingController::class, 'summary']);

        Route::get('/',        [TrainingController::class, 'index']);
        Route::post('/',       [TrainingController::class, 'store']);

        Route::post('/{training}/join',                            [TrainingController::class, 'join']);
        Route::post('/{training}/participants',                    [TrainingController::class, 'assignParticipants']);
        Route::patch('/{training}/participants/{employee}',        [TrainingController::class, 'updateParticipant']);
        Route::delete('/{training}/participants/{employee}',       [TrainingController::class, 'removeParticipant']);
        Route::patch('/{training}/participants/{employee}/attend', [TrainingController::class, 'markAttendance']);

        Route::get('/{training}',    [TrainingController::class, 'show']);
        Route::put('/{training}',    [TrainingController::class, 'update']);
        Route::delete('/{training}', [TrainingController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Hiring
    |--------------------------------------------------------------------------
    */

    Route::prefix('hiring')->group(function () {

        // Applicants
        Route::get('/applicants/summary',                              [ApplicantController::class, 'summary']);
        Route::get('/applicants',                                      [ApplicantController::class, 'index']);
        Route::post('/applicants',                                     [ApplicantController::class, 'store']);
        Route::get('/applicants/{applicant}',                          [ApplicantController::class, 'show']);
        Route::put('/applicants/{applicant}',                          [ApplicantController::class, 'update']);
        Route::delete('/applicants/{applicant}',                       [ApplicantController::class, 'destroy']);
        Route::post('/applicants/{applicant}/move-to-onboarding',      [ApplicantController::class, 'moveToOnboarding']);

        // Documents
        Route::get('/applicants/{applicant}/documents',                    [ApplicantDocumentController::class, 'index']);
        Route::post('/applicants/{applicant}/documents',                   [ApplicantDocumentController::class, 'store']);
        Route::put('/applicants/{applicant}/documents/{document}',         [ApplicantDocumentController::class, 'update']);
        Route::delete('/applicants/{applicant}/documents/{document}',      [ApplicantDocumentController::class, 'destroy']);

        // Interviews
        Route::get('/interviews',                            [InterviewController::class, 'index']);
        Route::get('/applicants/{applicant}/interview',      [InterviewController::class, 'show']);
        Route::put('/applicants/{applicant}/interview',      [InterviewController::class, 'update']);

        // Events / Calendar
        Route::get('/events',                       [HiringEventController::class, 'index']);
        Route::post('/events',                      [HiringEventController::class, 'store']);
        Route::put('/events/{hiringEvent}',         [HiringEventController::class, 'update']);
        Route::delete('/events/{hiringEvent}',      [HiringEventController::class, 'destroy']);

        // Contracts
        Route::get('/contracts',                                    [ContractController::class, 'index']);
        Route::post('/contracts',                                   [ContractController::class, 'store']);
        Route::put('/contracts/{contract}',                         [ContractController::class, 'update']);
        Route::delete('/contracts/{contract}',                      [ContractController::class, 'destroy']);
        Route::patch('/contracts/{contract}/auto-renewal',          [ContractController::class, 'updateAutoRenewal']);
        Route::post('/contracts/{contract}/manual-renew',           [ContractController::class, 'manualRenew']);
    });

    /*
    |--------------------------------------------------------------------------
    | Onboarding
    |--------------------------------------------------------------------------
    */

    Route::prefix('onboarding')->group(function () {
        Route::get('/summary',              [OnboardingController::class, 'summary']);
        Route::get('/',                     [OnboardingController::class, 'index']);
        Route::post('/',                    [OnboardingController::class, 'store']);
        Route::get('/{onboarding}',         [OnboardingController::class, 'show']);
        Route::put('/{onboarding}',         [OnboardingController::class, 'update']);
        Route::delete('/{onboarding}',      [OnboardingController::class, 'destroy']);

        // Tasks
        Route::post('/{onboarding}/tasks',                     [OnboardingController::class, 'addTask']);
        Route::patch('/{onboarding}/tasks/{task}/toggle',      [OnboardingController::class, 'toggleTask']);
        Route::put('/{onboarding}/tasks/{task}',               [OnboardingController::class, 'updateTask']);
        Route::delete('/{onboarding}/tasks/{task}',            [OnboardingController::class, 'deleteTask']);
    });

    /*
    |--------------------------------------------------------------------------
    | Holidays
    |--------------------------------------------------------------------------
    */

    Route::prefix('holidays')->group(function () {
        // !! Static segments before wildcards !!
        Route::get('/current',                          [HolidayController::class, 'current']);
        Route::get('/check',                            [HolidayController::class, 'checkDate']);
        Route::get('/next',                             [HolidayController::class, 'next']);
        Route::get('/search',                           [HolidayController::class, 'search']);
        Route::delete('/cache/{year?}',                 [HolidayController::class, 'clearCache']);
        Route::get('/range/{startYear}/{endYear}',      [HolidayController::class, 'getRange']);
        Route::get('/{year}',                           [HolidayController::class, 'getByYear']);
    });

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::prefix('reports')->group(function () {
        Route::get('/',            [ReportController::class, 'index']);
        Route::post('/',           [ReportController::class, 'store']);
        Route::get('/resolved/unassigned', [ReleaseController::class, 'unassignedResolved']);
        Route::get('/{report}',    [ReportController::class, 'show']);
        Route::put('/{report}',    [ReportController::class, 'update']);
        Route::delete('/{report}', [ReportController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Releases
    |--------------------------------------------------------------------------
    */

    Route::prefix('releases')->group(function () {
        Route::post('/',           [ReleaseController::class, 'store']);
        Route::delete('/{release}', [ReleaseController::class, 'destroy']);
    });

});