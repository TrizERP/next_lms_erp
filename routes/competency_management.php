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
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyFrameworkController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyStudioController;
use App\Http\Controllers\api\TalentManagement\Competency\RoleMappingController;
use App\Http\Controllers\api\TalentManagement\Competency\MappingReviewController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyLibraryCrudController;
use App\Http\Controllers\api\TalentManagement\Competency\CapabilityLibraryController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyRoleMapController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyDefinitionController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyApprovalController;
use App\Http\Controllers\api\TalentManagement\Competency\CompetencyLibraryDependantsController;

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
Route::middleware(['api.session', 'staff.only'])->group(function () {

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
    // Command Center (Capability Intelligence Dashboard)
    // ---------------------------------------------------------------
    // Static segment before the bare prefix route.
    Route::get('competency/command-center/filters', [CompetencyCommandCenterController::class, 'filters']);
    Route::get('competency/command-center', [CompetencyCommandCenterController::class, 'index']);

    // ---------------------------------------------------------------
    // Competency Framework Studio - frameworks (Competency Framework screen)
    // ---------------------------------------------------------------
    // Static segments before the /{id} wildcards.
    Route::get('competency/frameworks', [CompetencyFrameworkController::class, 'index']);
    Route::post('competency/frameworks', [CompetencyFrameworkController::class, 'store']);
    Route::get('competency/frameworks/{id}', [CompetencyFrameworkController::class, 'show'])->whereNumber('id');
    Route::put('competency/frameworks/{id}', [CompetencyFrameworkController::class, 'update'])->whereNumber('id');
    Route::delete('competency/frameworks/{id}', [CompetencyFrameworkController::class, 'destroy'])->whereNumber('id');
    Route::post('competency/frameworks/{id}/clone', [CompetencyFrameworkController::class, 'clone'])->whereNumber('id');
    Route::get('competency/frameworks/{id}/items', [CompetencyFrameworkController::class, 'items'])->whereNumber('id');
    Route::post('competency/frameworks/{id}/items', [CompetencyFrameworkController::class, 'storeItem'])->whereNumber('id');
    Route::delete('competency/frameworks/{id}/items/{itemId}', [CompetencyFrameworkController::class, 'destroyItem'])->whereNumber('id')->whereNumber('itemId');
    Route::get('competency/frameworks/{id}/weights', [CompetencyFrameworkController::class, 'weights'])->whereNumber('id');
    Route::put('competency/frameworks/{id}/weights', [CompetencyFrameworkController::class, 'saveWeights'])->whereNumber('id');

    // ---------------------------------------------------------------
    // Competency Framework Studio - weighting config / summary / structure /
    // proficiency scale / tenant-default weights
    // ---------------------------------------------------------------
    Route::get('competency/studio/weighting-config', [CompetencyStudioController::class, 'weightingConfig']);
    Route::put('competency/studio/weighting-config', [CompetencyStudioController::class, 'saveWeightingConfig']);
    Route::get('competency/studio/summary', [CompetencyStudioController::class, 'summary']);
    Route::get('competency/studio/framework-structure', [CompetencyStudioController::class, 'frameworkStructure']);
    Route::get('competency/studio/proficiency-scale', [CompetencyStudioController::class, 'proficiencyScale']);
    Route::post('competency/studio/proficiency-scale', [CompetencyStudioController::class, 'storeLevel']);
    Route::put('competency/studio/proficiency-scale/{id}', [CompetencyStudioController::class, 'updateLevel'])->whereNumber('id');
    Route::delete('competency/studio/proficiency-scale/{id}', [CompetencyStudioController::class, 'deleteLevel'])->whereNumber('id');
    Route::get('competency/studio/weights', [CompetencyStudioController::class, 'weights']);
    Route::put('competency/studio/weights', [CompetencyStudioController::class, 'saveWeights']);

    // ---------------------------------------------------------------
    // Role Mapping Matrix (Competency Framework screen's Role Mapping tab)
    // ---------------------------------------------------------------
    Route::get('competency/role-mapping/roles', [RoleMappingController::class, 'roles']);
    Route::get('competency/role-mapping/matrix', [RoleMappingController::class, 'matrix']);
    Route::put('competency/role-mapping/cell', [RoleMappingController::class, 'upsertCell']);
    Route::delete('competency/role-mapping/cell', [RoleMappingController::class, 'deleteCell']);

    // Role-mapping change approvals (Workflow & Review tab).
    Route::get('competency/mapping-reviews', [MappingReviewController::class, 'index']);
    Route::post('competency/mapping-reviews', [MappingReviewController::class, 'store']);
    Route::put('competency/mapping-reviews/{id}', [MappingReviewController::class, 'update'])->whereNumber('id');
    Route::post('competency/mapping-reviews/bulk-approve', [MappingReviewController::class, 'bulkApprove']);

    // ---------------------------------------------------------------
    // Competency Library (real competencies, /competency-library/* - distinct
    // from the Capability Library below, which serves skills/jobroles/KASA)
    // ---------------------------------------------------------------
    Route::prefix('competency-library')->group(function () {
        // Static segments before the /{id} wildcards below.
        Route::get('competency-export', [CompetencyLibraryCrudController::class, 'exportRows']);
        Route::post('competency-import', [CompetencyLibraryCrudController::class, 'importRows']);

        Route::get('competency-list', [CompetencyLibraryCrudController::class, 'index']);
        Route::get('competency/{id}', [CompetencyLibraryCrudController::class, 'show'])->whereNumber('id');
        Route::post('competency', [CompetencyLibraryCrudController::class, 'store']);
        Route::put('competency/{id}', [CompetencyLibraryCrudController::class, 'update'])->whereNumber('id');
        Route::delete('competency/{id}', [CompetencyLibraryCrudController::class, 'destroy'])->whereNumber('id');

        // Static sub-segments after {id} - registered after the bare {id}
        // routes above since they share the same prefix but a longer, more
        // specific path; Laravel matches these before falling through to a
        // wildcard-only route of a different method, so order here is for
        // readability rather than to prevent shadowing.
        Route::get('competency/{id}/detail', [CompetencyLibraryCrudController::class, 'detail'])->whereNumber('id');
        Route::post('competency/{id}/clone', [CompetencyLibraryCrudController::class, 'clone'])->whereNumber('id');
        Route::put('competency/{id}/archive', [CompetencyLibraryCrudController::class, 'archive'])->whereNumber('id');
    });

    // ---------------------------------------------------------------
    // Capability Library (Skill / Jobrole / Jobrole Task / Knowledge /
    // Ability / Attitude / Behaviour / Invisible tabs + taxonomy editors)
    // ---------------------------------------------------------------
    Route::get('competency/library/meta', [CapabilityLibraryController::class, 'meta']);
    Route::get('competency/library/skill-taxonomy-tree', [CapabilityLibraryController::class, 'skillTaxonomyTree']);
    Route::get('competency/library/levels-of-responsibility', [CapabilityLibraryController::class, 'levelsOfResponsibility']);
    Route::get('competency/library/work-functions', [CapabilityLibraryController::class, 'workFunctions']);
    // Delete-impact check (Capability Library's delete dialog).
    Route::get('competency/library/dependants', [CompetencyLibraryDependantsController::class, 'index']);

    Route::get('competency/library/taxonomy/{type}', [CapabilityLibraryController::class, 'taxonomy']);
    Route::post('competency/library/taxonomy/{type}', [CapabilityLibraryController::class, 'storeTaxonomy']);
    Route::put('competency/library/taxonomy/{type}', [CapabilityLibraryController::class, 'updateTaxonomy']);
    Route::delete('competency/library/taxonomy/{type}', [CapabilityLibraryController::class, 'destroyTaxonomy']);

    Route::get('competency/library/skills', [CapabilityLibraryController::class, 'skills']);
    Route::post('competency/library/skills', [CapabilityLibraryController::class, 'storeSkill']);
    Route::get('competency/library/skills/{id}', [CapabilityLibraryController::class, 'showSkill'])->whereNumber('id');
    Route::put('competency/library/skills/{id}', [CapabilityLibraryController::class, 'updateSkill'])->whereNumber('id');
    Route::delete('competency/library/skills/{id}', [CapabilityLibraryController::class, 'destroySkill'])->whereNumber('id');

    Route::get('competency/library/jobroles', [CapabilityLibraryController::class, 'jobroles']);
    Route::post('competency/library/jobroles', [CapabilityLibraryController::class, 'storeJobrole']);
    Route::get('competency/library/jobroles/{id}', [CapabilityLibraryController::class, 'showJobrole'])->whereNumber('id');
    Route::put('competency/library/jobroles/{id}', [CapabilityLibraryController::class, 'updateJobrole'])->whereNumber('id');
    Route::delete('competency/library/jobroles/{id}', [CapabilityLibraryController::class, 'destroyJobrole'])->whereNumber('id');

    // NOTE: `POST competency/library/jobrole-tasks` is DELIBERATELY NOT
    // registered here. That exact path + method already belongs to
    // `routes/task_management.php`'s `JobRoleTaskLibraryController::store`
    // (the Create Task modal's "Also save to the Job Role Task library"
    // checkbox), added when this Capability Library controller did not yet
    // exist in this target. Registering it again here would either shadow
    // that working feature or be shadowed by it depending on route-file load
    // order - a silent conflict neither side should have. Flagged for the
    // orchestrating session to decide whether to consolidate the two.
    Route::get('competency/library/jobrole-tasks', [CapabilityLibraryController::class, 'jobroleTasks']);
    Route::get('competency/library/jobrole-tasks/{id}', [CapabilityLibraryController::class, 'showJobroleTask'])->whereNumber('id');
    Route::put('competency/library/jobrole-tasks/{id}', [CapabilityLibraryController::class, 'updateJobroleTask'])->whereNumber('id');
    Route::delete('competency/library/jobrole-tasks/{id}', [CapabilityLibraryController::class, 'destroyJobroleTask'])->whereNumber('id');

    // Static /usage segment before the /{id} wildcard.
    Route::get('competency/library/kasa/{type}/{id}/usage', [CapabilityLibraryController::class, 'usageKasa'])->whereNumber('id');
    Route::get('competency/library/kasa/{type}', [CapabilityLibraryController::class, 'kasa']);
    Route::post('competency/library/kasa/{type}', [CapabilityLibraryController::class, 'storeKasa']);
    Route::get('competency/library/kasa/{type}/{id}', [CapabilityLibraryController::class, 'showKasa'])->whereNumber('id');
    Route::put('competency/library/kasa/{type}/{id}', [CapabilityLibraryController::class, 'updateKasa'])->whereNumber('id');
    Route::delete('competency/library/kasa/{type}/{id}', [CapabilityLibraryController::class, 'destroyKasa'])->whereNumber('id');

    Route::post('competency/library/invisible/{id}/clone', [CapabilityLibraryController::class, 'cloneInvisible'])->whereNumber('id');
    Route::get('competency/library/invisible', [CapabilityLibraryController::class, 'invisible']);
    Route::post('competency/library/invisible', [CapabilityLibraryController::class, 'storeInvisible']);
    Route::get('competency/library/invisible/{id}', [CapabilityLibraryController::class, 'showInvisible'])->whereNumber('id');
    Route::put('competency/library/invisible/{id}', [CapabilityLibraryController::class, 'updateInvisible'])->whereNumber('id');
    Route::delete('competency/library/invisible/{id}', [CapabilityLibraryController::class, 'destroyInvisible'])->whereNumber('id');

    // ---------------------------------------------------------------
    // Role-requirement sync (Competency Framework screen sub-feature) -
    // what a job role REQUIRES, keyed on jobrole_id + competency_id. Distinct
    // from the Role Mapping Matrix above (s_user_skill_jobrole cell-matrix).
    // ---------------------------------------------------------------
    Route::get('competency/role-map', [CompetencyRoleMapController::class, 'index']);
    Route::post('competency/role-map', [CompetencyRoleMapController::class, 'store']);
    Route::delete('competency/role-map/{id}', [CompetencyRoleMapController::class, 'destroy'])->whereNumber('id');

    // ---------------------------------------------------------------
    // Competency picker (Capability Library sub-feature) - the real
    // `competency` + `competency_kasba_item` tables, list + create.
    // ---------------------------------------------------------------
    Route::get('competency/definitions', [CompetencyDefinitionController::class, 'index']);
    Route::post('competency/definitions', [CompetencyDefinitionController::class, 'store']);

    // ---------------------------------------------------------------
    // Submit-for-approval workflow (Capability Library / Competency
    // Framework screens' "Submit for Approval" action).
    // ---------------------------------------------------------------
    // Static segments before the /{id} wildcard.
    Route::post('competency/approvals/bulk-approve', [CompetencyApprovalController::class, 'bulkApprove']);
    Route::get('competency/approvals/for/{type}/{id}', [CompetencyApprovalController::class, 'forSubject'])->whereNumber('id');
    Route::get('competency/approvals', [CompetencyApprovalController::class, 'index']);
    Route::post('competency/approvals', [CompetencyApprovalController::class, 'store']);
    Route::put('competency/approvals/{id}', [CompetencyApprovalController::class, 'update'])->whereNumber('id');
});
