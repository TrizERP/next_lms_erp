<?php

namespace App\Http\Controllers\api\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Shared admin gate for Talent/Task/Competency Management write endpoints
 * that act on someone OTHER than the caller (compensation, appraisals,
 * offers, promotions, org-wide config, etc.). These modules authenticate
 * via `api.session` and block students/parents via `staff.only`, but had no
 * role check beyond that - any staff account, any role, could approve a
 * raise or terminate someone's probation. Mirrors the convention already
 * used by RolePermissionsController::assertIsAdmin() in Organization
 * Management: `is_admin` 1 or 2 (Super Admin), sourced from the
 * JWT-verified session, never from client input.
 */
trait RequiresTalentAdmin
{
    protected function assertIsAdmin(): ?JsonResponse
    {
        $isAdmin = (int) session()->get('is_admin');

        if ($isAdmin === 1 || $isAdmin === 2) {
            return null;
        }

        return response()->json([
            'status_code' => 0,
            'message' => 'You do not have permission to perform this action.',
        ], 403);
    }
}
