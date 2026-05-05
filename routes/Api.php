<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
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
use App\Http\Controllers\Api\PlantillaItemController;
use App\Http\Controllers\Api\PlantillaPositionController;
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

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |----------------------------------------------------------------------
    | Auth
    |----------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    /*
    |----------------------------------------------------------------------
    | Profile
    |----------------------------------------------------------------------
    */

    Route::prefix('profile')->group(function () {
        Route::get('/status',    [ProfileController::class, 'status']);
        Route::post('/complete', [ProfileController::class, 'complete']);
    });

    /*
    |----------------------------------------------------------------------
    | Users
    |----------------------------------------------------------------------
    */

    Route::get('/pending-users',            [UserController::class, 'pendingUsers']);
    Route::get('/pending-users/count',      [UserController::class, 'pendingCount']);
    Route::patch('/users/{user}/activate',  [UserController::class, 'activate']);
    Route::post('/users/bulk-activate',     [UserController::class, 'bulkActivate']);
    Route::get('/approved-users',           [UserController::class, 'approvedUsers']);
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions']);
    Route::put('/users/{user}/role',        [UserController::class, 'updateRole']);

    /*
    |----------------------------------------------------------------------
    | Audit Logs
    |----------------------------------------------------------------------
    */

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Employees
    | Named routes must come BEFORE apiResources to avoid conflicts.
    |----------------------------------------------------------------------
    */

    Route::get('/employees/non-root',          [EmployeeController::class, 'nonRoot']);
    Route::get('/employees/gender-count',      [EmployeeController::class, 'genderCount']);
    Route::get('/employees/{employee}/avatar', [EmployeeController::class, 'avatar']);

    /*
    |----------------------------------------------------------------------
    | Skills
    |----------------------------------------------------------------------
    */
 
    Route::get('/skills/all', [SkillController::class, 'all']);
    Route::apiResources(['skills' => SkillController::class,
    ]);
 
    /*
    |----------------------------------------------------------------------
    | Skill Assessments
    |----------------------------------------------------------------------
    */
 
    Route::prefix('skill-assessments')->group(function () {
        Route::get('/', [SkillAssessmentController::class, 'index']);
        Route::post('/', [SkillAssessmentController::class, 'store']);
        Route::get('/employee/{employee}', [SkillAssessmentController::class, 'byEmployee']);
        Route::get('/{skillAssessment}', [SkillAssessmentController::class, 'show']);
        Route::put('/{skillAssessment}', [SkillAssessmentController::class, 'update']);
        Route::delete('/{skillAssessment}', [SkillAssessmentController::class, 'destroy']);
        Route::post('/{skillAssessment}/submit', [SkillAssessmentController::class, 'submit']);
        Route::post('/{skillAssessment}/review', [SkillAssessmentController::class, 'review']);
    });
 
    /*
    |----------------------------------------------------------------------
    | Skill Recommendations
    |----------------------------------------------------------------------
    */
 
    Route::prefix('skill-recommendations')->group(function () {
        Route::get('/', [SkillRecommendationController::class, 'index']);
        Route::post('/', [SkillRecommendationController::class, 'store']);
        Route::get('/skill/{skillId}', [SkillRecommendationController::class, 'bySkill']);
        Route::get('/{skillRecommendation}', [SkillRecommendationController::class, 'show']);
        Route::put('/{skillRecommendation}', [SkillRecommendationController::class, 'update']);
        Route::delete('/{skillRecommendation}', [SkillRecommendationController::class, 'destroy']);
    });
 
    /*
    |----------------------------------------------------------------------
    | Development Plans
    |----------------------------------------------------------------------
    */
 
    Route::prefix('development-plans')->group(function () {
        Route::get('/', [DevelopmentPlanController::class, 'index']);
        Route::post('/', [DevelopmentPlanController::class, 'store']);
        Route::get('/employee/{employee}', [DevelopmentPlanController::class, 'byEmployee']);
        Route::get('/{developmentPlan}', [DevelopmentPlanController::class, 'show']);
        Route::put('/{developmentPlan}', [DevelopmentPlanController::class, 'update']);
        Route::delete('/{developmentPlan}', [DevelopmentPlanController::class, 'destroy']);
        Route::patch('/{developmentPlan}/progress', [DevelopmentPlanController::class, 'updateProgress']);
        Route::patch('/{developmentPlan}/complete', [DevelopmentPlanController::class, 'complete']);
    });
 
    /*
    |----------------------------------------------------------------------
    | Skill Gap Analysis
    |----------------------------------------------------------------------
    */
 
    Route::prefix('skill-gap-analysis')->group(function () {
        Route::get('/{skillAssessment}', [SkillGapAnalysisController::class, 'generate']);
        Route::post('/{skillAssessment}/create-plans', [SkillGapAnalysisController::class, 'createPlans']);
        Route::get('/department/{departmentId}/stats', [SkillGapAnalysisController::class, 'departmentStats']);
        Route::get('/employee/{employee}/summary', [SkillGapAnalysisController::class, 'employeeSummary']);
        Route::get('/export/{skillAssessment}', [SkillGapAnalysisController::class, 'export']);
    });

    /*
    |----------------------------------------------------------------------
    | Plantilla Items — Named routes BEFORE apiResources
    |
    | /plantilla-items/available  must be registered before the
    | apiResource binding or Laravel will treat "available" as an {id}.
    |----------------------------------------------------------------------
    */

    Route::get('plantilla-items/available', [PlantillaItemController::class, 'available']);
    Route::get('plantilla-items/summary',   [PlantillaItemController::class, 'summary']);

    /*
    |----------------------------------------------------------------------
    | Core API Resources
    |----------------------------------------------------------------------
    */

    Route::apiResources([
        'departments'         => DepartmentController::class,
        'divisions'           => DivisionController::class,
        'employees'           => EmployeeController::class,
        'salary-grades'       => SalaryGradeController::class,
        'step-increments'     => StepIncrementController::class,
        'plantilla-items'     => PlantillaItemController::class,
        'plantilla-positions' => PlantillaPositionController::class,
    ]);

    /*
    |----------------------------------------------------------------------
    | COS Positions
    |----------------------------------------------------------------------
    |
    | GET    /api/cos-positions               → index   (list / search / paginate)
    | POST   /api/cos-positions               → store
    | GET    /api/cos-positions/{cosPosition} → show
    | PUT    /api/cos-positions/{cosPosition} → update
    | DELETE /api/cos-positions/{cosPosition} → destroy
    |
    */

    Route::prefix('cos-positions')->group(function () {
        Route::get('/',            [CosPositionController::class, 'index']);
        Route::post('/',           [CosPositionController::class, 'store']);
        Route::get('/{cosPosition}', [CosPositionController::class, 'show']);
        Route::put('/{cosPosition}', [CosPositionController::class, 'update']);
        Route::delete('/{cosPosition}', [CosPositionController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Consultant Positions
    |----------------------------------------------------------------------
    |
    | GET    /api/consultant-positions                 → index
    | POST   /api/consultant-positions                 → store
    | GET    /api/consultant-positions/{consultantPosition} → show
    | PUT    /api/consultant-positions/{consultantPosition} → update
    | DELETE /api/consultant-positions/{consultantPosition} → destroy
    |
    */

    Route::prefix('consultant-positions')->group(function () {
        Route::get('/',                     [ConsultantPositionController::class, 'index']);
        Route::post('/',                    [ConsultantPositionController::class, 'store']);
        Route::get('/{consultantPosition}', [ConsultantPositionController::class, 'show']);
        Route::put('/{consultantPosition}', [ConsultantPositionController::class, 'update']);
        Route::delete('/{consultantPosition}', [ConsultantPositionController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Notifications
    |----------------------------------------------------------------------
    */

    Route::prefix('notifications')->group(function () {
        Route::get('/',                          [NotificationController::class, 'index']);
        Route::get('/unread-count',              [NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read',            [NotificationController::class, 'markAllRead']);
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markRead']);
    });

    /*
    |----------------------------------------------------------------------
    | Plantilla Items — Extended Actions
    |
    | Status lifecycle:
    |   VACANT
    |     → POST /{id}/assign       → FILLED
    |   FILLED
    |     → POST /{id}/vacate       → UNFILLED  (city approval required)
    |     → POST /{id}/transfer     → source: UNFILLED, destination: FILLED
    |   UNFILLED
    |     → PATCH /{id}/approve     → VACANT  (city gives the green light)
    |
    | GET  /{id}/steps              → step increments for this item's SG
    | GET  /{id}/history            → full assignment history (audit trail)
    |----------------------------------------------------------------------
    */

    Route::prefix('plantilla-items/{plantillaItem}')->group(function () {
        Route::get  ('steps',    [PlantillaItemController::class, 'steps']);
        Route::get  ('history',  [PlantillaItemController::class, 'history']);
        Route::post ('assign',   [PlantillaItemController::class, 'assign']);
        Route::post ('vacate',   [PlantillaItemController::class, 'vacate']);
        Route::post ('transfer', [PlantillaItemController::class, 'transfer']);
        Route::patch('approve',  [PlantillaItemController::class, 'approve']);
    });

    /*
    |----------------------------------------------------------------------
    | Plantilla Positions — Extended Actions
    |
    | GET  /{id}/steps    → step increments for this slot's salary grade
    | PATCH /{id}/approve → city approval received, unlock for re-filling
    |----------------------------------------------------------------------
    */

    Route::prefix('plantilla-positions/{plantillaPosition}')->group(function () {
        Route::get  ('steps',   [PlantillaPositionController::class, 'steps']);
        Route::patch('approve', [PlantillaPositionController::class, 'approve']);
    });

    /*
    |----------------------------------------------------------------------
    | Employee Assignments
    |----------------------------------------------------------------------
    */

    Route::prefix('assignments')->group(function () {
        Route::get('/employee/{employee}', [EmployeeAssignmentController::class, 'index']);
        Route::post('/',                   [EmployeeAssignmentController::class, 'store']);
        Route::put('/end/{assignment}',    [EmployeeAssignmentController::class, 'end']);
        Route::delete('/{assignment}',     [EmployeeAssignmentController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Manpower
    |----------------------------------------------------------------------
    */

    Route::prefix('manpower')->group(function () {
        Route::get('/tree',               [ManpowerMappingController::class, 'tree']);
        Route::get('/report',             [ManpowerMappingController::class, 'report']);
        Route::get('/department/{id}',    [ManpowerMappingController::class, 'departmentHierarchy']);

        // Counts
        Route::get('/plantilla-count',    [ManpowerMappingController::class, 'plantillaCount']);
        Route::get('/cos-count',          [ManpowerMappingController::class, 'cosCount']);
        Route::get('/consultant-count',   [ManpowerMappingController::class, 'consultantCount']);
        Route::get('/vacant-count',       [ManpowerMappingController::class, 'vacantCount']);

        // Summary & Analytics
        Route::get('/summary',            [ManpowerMappingController::class, 'summary']);
        Route::get('/overstaffed',        [ManpowerMappingController::class, 'overstaffed']);
        Route::get('/division-analytics', [ManpowerMappingController::class, 'divisionAnalytics']);
    });

    /*
    |----------------------------------------------------------------------
    | Trainings
    |----------------------------------------------------------------------
    */

    Route::prefix('trainings')->group(function () {
        Route::get('/summary', [TrainingController::class, 'summary']);

        Route::get('/',  [TrainingController::class, 'index']);
        Route::post('/', [TrainingController::class, 'store']);

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
    |----------------------------------------------------------------------
    | Hiring
    |----------------------------------------------------------------------
    */

    Route::prefix('hiring')->group(function () {

        // Applicants
        Route::get('/applicants/summary',        [ApplicantController::class, 'summary']);
        Route::get('/applicants',                [ApplicantController::class, 'index']);
        Route::post('/applicants',               [ApplicantController::class, 'store']);
        Route::get('/applicants/{applicant}',    [ApplicantController::class, 'show']);
        Route::put('/applicants/{applicant}',    [ApplicantController::class, 'update']);
        Route::delete('/applicants/{applicant}', [ApplicantController::class, 'destroy']);

        Route::post('/applicants/{applicant}/move-to-onboarding', [ApplicantController::class, 'moveToOnboarding']);

        // Documents
        Route::get('/applicants/{applicant}/documents',              [ApplicantDocumentController::class, 'index']);
        Route::post('/applicants/{applicant}/documents',             [ApplicantDocumentController::class, 'store']);
        Route::put('/applicants/{applicant}/documents/{document}',   [ApplicantDocumentController::class, 'update']);
        Route::delete('/applicants/{applicant}/documents/{document}',[ApplicantDocumentController::class, 'destroy']);

        // Interviews
        Route::get('/interviews',                       [InterviewController::class, 'index']);
        Route::get('/applicants/{applicant}/interview', [InterviewController::class, 'show']);
        Route::put('/applicants/{applicant}/interview', [InterviewController::class, 'update']);

        // Events / Calendar
        Route::get('/events',                  [HiringEventController::class, 'index']);
        Route::post('/events',                 [HiringEventController::class, 'store']);
        Route::put('/events/{hiringEvent}',    [HiringEventController::class, 'update']);
        Route::delete('/events/{hiringEvent}', [HiringEventController::class, 'destroy']);

        // Contracts
        Route::get('/contracts',               [ContractController::class, 'index']);
        Route::post('/contracts',              [ContractController::class, 'store']);
        Route::put('/contracts/{contract}',    [ContractController::class, 'update']);
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------------
    | Onboarding
    |----------------------------------------------------------------------
    */

    Route::prefix('onboarding')->group(function () {
        Route::get('/summary', [OnboardingController::class, 'summary']);

        Route::get('/',                [OnboardingController::class, 'index']);
        Route::post('/',               [OnboardingController::class, 'store']);
        Route::get('/{onboarding}',    [OnboardingController::class, 'show']);
        Route::put('/{onboarding}',    [OnboardingController::class, 'update']);
        Route::delete('/{onboarding}', [OnboardingController::class, 'destroy']);

        // Tasks
        Route::post('/{onboarding}/tasks',                 [OnboardingController::class, 'addTask']);
        Route::patch('/{onboarding}/tasks/{task}/toggle',  [OnboardingController::class, 'toggleTask']);
        Route::put('/{onboarding}/tasks/{task}',           [OnboardingController::class, 'updateTask']);
        Route::delete('/{onboarding}/tasks/{task}',        [OnboardingController::class, 'deleteTask']);
    });
});