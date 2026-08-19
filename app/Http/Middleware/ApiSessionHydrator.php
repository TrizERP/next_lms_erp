<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hydrates the (in-memory) session from the JWT payload so that the existing
 * Result module controllers/models/helpers - which read session values set at
 * web login (loginController) - work unchanged when called through the REST API.
 *
 * Authentication mechanism is the SAME JWT already used by the ERP mobile/API
 * login (App\Http\Controllers\api\ApiLoginController -> user_token).
 *
 * NOTE: the session store is never started/saved here, so nothing is persisted;
 * values only live for the duration of the API request (stateless).
 */
class ApiSessionHydrator
{
    use GetsJwtToken;

    public function handle(Request $request, Closure $next)
    {
        try {
            if (! $this->jwtToken($request)->validate()) {
                return $this->unauthorized('Token Auth Failed');
            }
        } catch (\Exception $e) {
            return $this->unauthorized($e->getMessage());
        }

        $payload = $this->jwtPayload(null, $request);

        $userId         = $payload['id'] ?? null;
        $subInstituteId = $payload['sub_institute_id'] ?? $request->input('sub_institute_id');
        $userProfileId  = $payload['user_profile_id'] ?? null;
        $clientId       = $payload['client_id'] ?? null;
        $isAdmin        = $payload['is_admin'] ?? 0;
        $isStudent      = (bool) ($payload['is_student'] ?? false);

        if (empty($userId) || empty($subInstituteId)) {
            return $this->unauthorized('Invalid token payload');
        }

        // Same current-term resolution as loginController. The frontend may
        // switch academic year/term explicitly (like the web year switcher).
        $syear  = $request->input('syear');
        $termId = $request->input('term_id');

        if (empty($syear) || empty($termId)) {
            $currentTerm = DB::table('academic_year')
                ->where('sub_institute_id', $subInstituteId)
                ->whereRaw('"' . date('Y-m-d') . '" between start_date and end_date')
                ->first();

            if (empty($currentTerm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Term Date Expired',
                    'data'    => null,
                    'errors'  => null,
                ], 422);
            }

            $syear  = $syear ?: $currentTerm->syear;
            $termId = $termId ?: $currentTerm->term_id;
        }

        $profile = DB::table('tbluserprofilemaster')->where('id', $userProfileId)->first();

        $userProfileName = ((int) $isAdmin === 1 || (int) $isAdmin === 2)
            ? 'Super Admin'
            : ($profile->name ?? '');

        $academicTerms = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->orderBy('sort_order')
            ->get()->toArray();

        $academicYears = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->groupBy('syear')
            ->get()->toArray();

        $school = DB::table('school_setup')->where('Id', $subInstituteId)->first();

        // Attach the (unstarted) session store to the request and hydrate the
        // exact keys web login sets, so all legacy session() reads keep working.
        $store = app('session.store');
        $request->setLaravelSession($store);

        $store->put('user_id', $userId);
        $store->put('sub_institute_id', $subInstituteId);
        $store->put('syear', $syear);
        $store->put('term_id', $termId);
        $store->put('user_profile_id', $userProfileId);
        $store->put('user_profile_name', $userProfileName);
        $store->put('profile_parent_id', $profile->parent_id ?? null);
        $store->put('client_id', $clientId);
        $store->put('is_admin', $isAdmin);
        $store->put('is_student', $isStudent);
        $store->put('academicTerms', $academicTerms);
        $store->put('academicYears', $academicYears);
        $store->put('institute_type', $school->institute_type ?? '');
        $store->put('erpcode', $school->ShortCode ?? '');
        $store->put('school_name', $school->SchoolName ?? '');

        if (! $isStudent) {
            $user = DB::table('tbluser')->where('id', $userId)->first();
            $store->put('user_name', $user->user_name ?? '');
            $store->put('name', trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
            $store->put('email', $user->email ?? '');
        }

        // Class-teacher scoping - same as loginController.
        if ($userProfileName === 'Teacher') {
            $classTeacher = DB::table('class_teacher')
                ->where('teacher_id', $userId)
                ->where('sub_institute_id', $subInstituteId)
                ->where('syear', $syear)
                ->get()->toArray();

            $classTeacherGrdArr = $classTeacherStdArr = $classTeacherDivArr = [];
            foreach ($classTeacher as $v) {
                $classTeacherGrdArr[] = $v->grade_id;
                $classTeacherStdArr[] = $v->standard_id;
                $classTeacherDivArr[] = $v->division_id;
            }
            $store->put('classTeacherGrdArr', $classTeacherGrdArr);
            $store->put('classTeacherStdArr', $classTeacherStdArr);
            $store->put('classTeacherDivArr', $classTeacherDivArr);
            $store->put('classTeacher', $classTeacher);
        }

        if ($request->filled('menu_id')) {
            $store->put('right_menu_id', $request->input('menu_id'));
        }

        // Existing controllers switch to their JSON branches on type=API
        // (is_mobile helper / SessionMiddleware / checkPermission behave the
        // same way they already do for the mobile apps).
        $request->merge(['type' => 'API']);

        return $next($request);
    }

    private function unauthorized(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => null,
        ], 401);
    }
}
