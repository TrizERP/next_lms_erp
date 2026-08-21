<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\TalentManagement\Competency\EmployeeCompetencyProfileController;
use App\Http\Controllers\api\TalentManagement\Competency\CertificationController;
use App\Http\Controllers\api\TalentManagement\Competency\CertificationRequirementController;
use App\Http\Controllers\api\TalentManagement\Competency\DevelopmentPlanController;
use App\Http\Controllers\api\TalentManagement\Competency\DevelopmentPlanReportController;
use App\Http\Controllers\api\TalentManagement\Competency\CareerPathController;
use App\Http\Controllers\api\TalentManagement\Competency\KasbaRatingController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyCommandCenterController;

/*
|--------------------------------------------------------------------------
| Competency Management (Talent Management > Employee Profiles,
| Certifications, Development & Career Paths)
|--------------------------------------------------------------------------
|
| Registered stateless (mapApiRoutes-style: prefix('api')->middleware('api'))
| in RouteServiceProvider::mapCompetencyManagementRoutes(). Every group below
| additionally requires `api.session` (real JWT validation + legacy-session
| hydration), matching routes/talent_management.php's convention for this
| generation of Next.js-facing modules.
|
| An as-is migration of G2G's (hp_erp) Competency Management backend
| (EmployeeCompetencyProfileController, CertificationController,
| CertificationRequirementController, DevelopmentPlanController,
| DevelopmentPlanReportController, CareerPathController -
| CompetencyLearningAssignmentController and the Command Center /
| Audit Center controllers are explicitly out of scope for this port).
| URLs, HTTP methods and path params are kept identical to the source so the
| ported frontend service files need no URL changes beyond the shared `/api`
| base.
|
| Source route middleware note: `profile:admin,hr` / `profile:admin,hr,manager`
| gated some Employee Profiles write routes in G2G; Certifications /
| Certification Requirements / Development Plans / Career Paths carried no
| role middleware at all (Sanctum token auth + tenant scoping only). This
| target has no direct equivalent of the Sanctum `profile:` gate on these
| session-authenticated routes, so every route below is registered the same
| way the source's non-role-gated majority already was: `api.session`
| (token + tenant scoping) only. The ownership/elevated-role check the source
| enforced in-controller for Employee Profiles (self-service unless the
| caller's role_key is administrator/hr_manager/hr_executive/executive/
| auditor) is preserved unchanged in
| `ResolvesCompetencyContext::competencySubject()`, which every Employee
| Profiles action still calls - so the effective authorization boundary is
| identical even though the extra route-level role gate was not replicated.
*/
Route::middleware(['api.session'])->group(function () {

    // ---------------------------------------------------------------
    // Employee Profiles
    // ---------------------------------------------------------------
    Route::get('competency/employee-profiles', [EmployeeCompetencyProfileController::class, 'index']);
    Route::get('competency/employee-profiles/{id}', [EmployeeCompetencyProfileController::class, 'show'])->whereNumber('id');
    Route::get('competency/employee-profiles/{id}/available-skills', [EmployeeCompetencyProfileController::class, 'availableSkills'])->whereNumber('id');
    Route::post('competency/employee-profiles/{id}/skills', [EmployeeCompetencyProfileController::class, 'addSkill'])->whereNumber('id');
    Route::put('competency/employee-profiles/{id}/skills/{matrixId}', [EmployeeCompetencyProfileController::class, 'updateSkill'])->whereNumber('id')->whereNumber('matrixId');
    Route::get('competency/employee-profiles/{id}/skills/{skillId}/history', [EmployeeCompetencyProfileController::class, 'skillHistory'])->whereNumber('id')->whereNumber('skillId');
    Route::get('competency/employee-profiles/{id}/notes', [EmployeeCompetencyProfileController::class, 'notes'])->whereNumber('id');
    Route::put('competency/employee-profiles/{id}/notes', [EmployeeCompetencyProfileController::class, 'saveNotes'])->whereNumber('id');
    Route::get('competency/employee-profiles/{id}/certifications', [EmployeeCompetencyProfileController::class, 'certifications'])->whereNumber('id');
    Route::get('competency/employee-profiles/{id}/development-plans', [EmployeeCompetencyProfileController::class, 'developmentPlans'])->whereNumber('id');
    Route::get('competency/employee-profiles/{id}/evidence', [EmployeeCompetencyProfileController::class, 'evidence'])->whereNumber('id');
    Route::post('competency/employee-profiles/{id}/evidence', [EmployeeCompetencyProfileController::class, 'storeEvidence'])->whereNumber('id');
    Route::delete('competency/employee-profiles/{id}/evidence/{evidenceId}', [EmployeeCompetencyProfileController::class, 'deleteEvidence'])->whereNumber('id')->whereNumber('evidenceId');
    Route::get('competency/employee-profiles/{id}/career-path', [EmployeeCompetencyProfileController::class, 'careerPath'])->whereNumber('id');

    // ---------------------------------------------------------------
    // Certification & Compliance Center
    // ---------------------------------------------------------------
    // Static segments before the /{id} routes so the numeric show/update
    // route cannot swallow metrics / filters / export / bulk.
    Route::get('competency/certifications/metrics', [CertificationController::class, 'metrics']);
    Route::get('competency/certifications/filters', [CertificationController::class, 'filters']);
    Route::get('competency/certifications/export', [CertificationController::class, 'export']);
    Route::post('competency/certifications/bulk', [CertificationController::class, 'bulk']);

    Route::get('competency/certifications', [CertificationController::class, 'index']);
    Route::post('competency/certifications', [CertificationController::class, 'store']);
    Route::get('competency/certifications/{id}', [CertificationController::class, 'show'])->whereNumber('id');
    Route::put('competency/certifications/{id}', [CertificationController::class, 'update'])->whereNumber('id');
    Route::delete('competency/certifications/{id}', [CertificationController::class, 'destroy'])->whereNumber('id');
    Route::post('competency/certifications/{id}/notes', [CertificationController::class, 'addNote'])->whereNumber('id');
    Route::get('competency/certifications/{id}/compliance', [CertificationController::class, 'compliance'])->whereNumber('id');
    Route::get('competency/certifications/{id}/requirements', [CertificationController::class, 'requirements'])->whereNumber('id');
    Route::get('competency/certifications/{id}/history', [CertificationController::class, 'history'])->whereNumber('id');
    Route::get('competency/certifications/{id}/documents', [CertificationController::class, 'documents'])->whereNumber('id');
    Route::post('competency/certifications/{id}/documents', [CertificationController::class, 'storeDocument'])->whereNumber('id');
    Route::delete('competency/certifications/{id}/documents/{documentId}', [CertificationController::class, 'destroyDocument'])->whereNumber('id')->whereNumber('documentId');

    // Certification requirements - the "which role must hold what" policy master.
    Route::get('competency/certification-requirements/department-options', [CertificationRequirementController::class, 'departmentOptions']);
    Route::get('competency/certification-requirements', [CertificationRequirementController::class, 'index']);
    Route::post('competency/certification-requirements', [CertificationRequirementController::class, 'store']);
    Route::put('competency/certification-requirements/{id}', [CertificationRequirementController::class, 'update'])->whereNumber('id');
    Route::delete('competency/certification-requirements/{id}', [CertificationRequirementController::class, 'destroy'])->whereNumber('id');

    // ---------------------------------------------------------------
    // Development & Career Path Workspace
    // ---------------------------------------------------------------
    // Static segments first so they cannot be swallowed by /{id}.
    Route::get('competency/development-plans/metrics', [DevelopmentPlanController::class, 'metrics']);
    Route::get('competency/development-plans/owners', [DevelopmentPlanController::class, 'owners']);
    Route::get('competency/employee-options', [DevelopmentPlanController::class, 'employees']);
    Route::get('competency/reports/development-plans', [DevelopmentPlanReportController::class, 'index']);

    Route::get('competency/development-plans', [DevelopmentPlanController::class, 'index']);
    Route::post('competency/development-plans', [DevelopmentPlanController::class, 'store']);
    Route::get('competency/development-plans/{id}', [DevelopmentPlanController::class, 'show'])->whereNumber('id');
    Route::put('competency/development-plans/{id}', [DevelopmentPlanController::class, 'update'])->whereNumber('id');
    Route::delete('competency/development-plans/{id}', [DevelopmentPlanController::class, 'destroy'])->whereNumber('id');
    Route::get('competency/development-plans/{id}/gaps', [DevelopmentPlanController::class, 'gaps'])->whereNumber('id');
    Route::get('competency/development-plans/{id}/history', [DevelopmentPlanController::class, 'history'])->whereNumber('id');
    Route::get('competency/development-plans/{id}/actions', [DevelopmentPlanController::class, 'actions'])->whereNumber('id');
    Route::post('competency/development-plans/{id}/actions', [DevelopmentPlanController::class, 'storeAction'])->whereNumber('id');
    Route::put('competency/development-plans/{id}/actions/{actionId}', [DevelopmentPlanController::class, 'updateAction'])->whereNumber('id')->whereNumber('actionId');
    Route::delete('competency/development-plans/{id}/actions/{actionId}', [DevelopmentPlanController::class, 'destroyAction'])->whereNumber('id')->whereNumber('actionId');

    // Named career paths + the Career Path Explorer.
    Route::get('competency/career-paths/explorer', [CareerPathController::class, 'explorer']);
    Route::get('competency/career-paths/role-options', [CareerPathController::class, 'roleOptions']);
    Route::get('competency/career-paths', [CareerPathController::class, 'index']);
    Route::post('competency/career-paths', [CareerPathController::class, 'store']);
    Route::get('competency/career-paths/{id}', [CareerPathController::class, 'show'])->whereNumber('id');
    Route::put('competency/career-paths/{id}', [CareerPathController::class, 'update'])->whereNumber('id');
    Route::delete('competency/career-paths/{id}', [CareerPathController::class, 'destroy'])->whereNumber('id');

    // ---------------------------------------------------------------
    // KASBA item rating (Employee Profiles panel)
    // ---------------------------------------------------------------
    Route::get('competency/kasba-rating', [KasbaRatingController::class, 'index']);
    Route::post('competency/kasba-rating', [KasbaRatingController::class, 'store']);
    Route::delete('competency/kasba-rating', [KasbaRatingController::class, 'destroy']);

    // ---------------------------------------------------------------
    // Command Center filters (Department dropdown lookup only — see
    // CompetencyCommandCenterController's class doc for scope note)
    // ---------------------------------------------------------------
    Route::get('competency/command-center/filters', [CompetencyCommandCenterController::class, 'filters']);
});
