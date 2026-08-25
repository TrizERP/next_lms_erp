<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\OrganizationManagement\Compliance\ComplianceLibraryController;
use App\Http\Controllers\api\OrganizationManagement\Disciplinary\DisciplinaryLibraryController;
use App\Http\Controllers\api\OrganizationManagement\EmployeeDirectory\EmployeeDirectoryController;
use App\Http\Controllers\api\OrganizationManagement\EmployeeDirectory\EmployeeDirectoryAnalyticsController;
use App\Http\Controllers\api\OrganizationManagement\RolePermissions\RolePermissionsController;

/*
|--------------------------------------------------------------------------
| Organization Management
|--------------------------------------------------------------------------
|
| Registered stateless (mapApiRoutes-style: prefix('api')->middleware('api'))
| in RouteServiceProvider::mapOrganizationManagementRoutes(). Every group below
| additionally requires `api.session` (real JWT validation + legacy-session
| hydration), matching the established convention for this generation of
| Next.js-facing modules (see routes/talent_management.php).
|
| This file is shared by multiple ported G2G features under the single new
| "Organization Management" module - each feature owns its own prefix group
| below. Add new feature route groups here rather than creating new route
| files per feature.
*/

Route::middleware(['api.session'])->group(function () {

    // Compliance Library - ported from G2G's instituteDetailController
    // `formName == 'complaince_library'` branch.
    Route::prefix('organization-management/compliance-library')->group(function () {
        Route::get('/', [ComplianceLibraryController::class, 'index']);
        Route::post('/', [ComplianceLibraryController::class, 'store']);
        Route::match(['put', 'post'], '/{id}', [ComplianceLibraryController::class, 'update']);
        Route::delete('/{id}', [ComplianceLibraryController::class, 'destroy']);
    });

    // Disciplinary Library - ported from G2G's discliplinaryManagementController.
    Route::prefix('organization-management/disciplinary-library')->group(function () {
        Route::get('/', [DisciplinaryLibraryController::class, 'index']);
        Route::post('/', [DisciplinaryLibraryController::class, 'store']);
        Route::match(['put', 'post'], '/{id}', [DisciplinaryLibraryController::class, 'update']);
        Route::delete('/{id}', [DisciplinaryLibraryController::class, 'destroy']);
        Route::get('/departments/{department}/employees', [DisciplinaryLibraryController::class, 'departmentEmployees']);
    });

    // Employee Directory - ported from G2G's tbluserController +
    // Reports\EmployeeDirectoryAnalytics\EmployeeDirectoryAnalyticsController.
    Route::prefix('organization-management/employee-directory')->group(function () {
        Route::get('/', [EmployeeDirectoryController::class, 'index']);
        Route::post('/', [EmployeeDirectoryController::class, 'store']);
        Route::get('/teachers', [EmployeeDirectoryController::class, 'teacherList']);
        Route::get('/{id}', [EmployeeDirectoryController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [EmployeeDirectoryController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [EmployeeDirectoryController::class, 'destroy'])->whereNumber('id');
        Route::post('/{id}/documents', [EmployeeDirectoryController::class, 'uploadDocument'])->whereNumber('id');
        Route::get('/{id}/competency-profile', [EmployeeDirectoryController::class, 'competencyProfile'])->whereNumber('id');
        Route::put('/{id}/skills/{matrixId}', [EmployeeDirectoryController::class, 'updateSkillRating'])->whereNumber('id')->whereNumber('matrixId');

        Route::prefix('/analytics')->group(function () {
            Route::get('/kpis', [EmployeeDirectoryAnalyticsController::class, 'getKPIs']);
            Route::get('/growth', [EmployeeDirectoryAnalyticsController::class, 'getGrowthData']);
            Route::get('/growth-stacked', [EmployeeDirectoryAnalyticsController::class, 'getStackedGrowth']);
            Route::get('/departments-distribution', [EmployeeDirectoryAnalyticsController::class, 'getDepartmentDistribution']);
            Route::get('/job-roles-distribution', [EmployeeDirectoryAnalyticsController::class, 'getJobRoleDistribution']);
            Route::get('/lifecycle', [EmployeeDirectoryAnalyticsController::class, 'getLifecycle']);
            Route::get('/attrition', [EmployeeDirectoryAnalyticsController::class, 'getAttritionBreakdown']);
            Route::get('/skills-matrix', [EmployeeDirectoryAnalyticsController::class, 'getSkillMatrix']);
        });
    });

    // Role & Permissions - ported from G2G's tblmenumasterG2gController, wired
    // onto LMS-K12's EXISTING tbluserprofilemaster / tblmenumaster /
    // tblgroupwise_rights tables (no tblmenumaster_g2g-style tables created).
    Route::prefix('organization-management/role-permissions')->group(function () {
        Route::get('/roles', [RolePermissionsController::class, 'index']);
        Route::post('/roles', [RolePermissionsController::class, 'storeRole']);
        Route::get('/roles/{id}/rights', [RolePermissionsController::class, 'rights'])->whereNumber('id');
        Route::post('/roles/{id}/rights', [RolePermissionsController::class, 'store'])->whereNumber('id');
    });

});
