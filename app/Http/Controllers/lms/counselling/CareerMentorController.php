<?php

namespace App\Http\Controllers\lms\counselling;

use App\CareerIntelligence\StaffMentorMatchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Career Counseling: staff mentor suggestions and the HR-managed crosswalk
 * between People & Competency skills and O*NET elements.
 *
 * Mounted on the same 'session' group as the other career routes, so tenant
 * and user come from the hydrated session, never from the request.
 */
class CareerMentorController extends Controller
{
    /** Same role set as ResolvesCompetencyContext::COMPETENCY_ELEVATED. */
    private const READ_ROLES = ['administrator', 'hr_manager', 'hr_executive', 'executive', 'auditor'];

    private const WRITE_ROLES = ['administrator', 'hr_manager', 'hr_executive'];

    /**
     * This estate leaves tbluserprofilemaster.role_key NULL on every profile, so a role_key
     * check alone would lock everyone out. Profile names (lower-cased) are accepted as well.
     */
    private const READ_PROFILE_NAMES = ['admin', 'school admin', 'assistant admin', 'principal'];

    private const WRITE_PROFILE_NAMES = ['admin', 'school admin', 'assistant admin'];

    public function __construct(private StaffMentorMatchService $service)
    {
    }

    /** GET careerMentorSuggestions?occupation_code=15-1252.00 */
    public function suggestions(Request $request): JsonResponse
    {
        $tenant = (int) $request->session()->get('sub_institute_id');
        if ($tenant <= 0 || empty($request->session()->get('user_id'))) {
            return response()->json(['status_code' => 0, 'message' => 'Unauthenticated.'], 401);
        }

        $code = trim((string) $request->query('occupation_code'));
        if ($code === '' || strlen($code) > 20) {
            return response()->json(['status_code' => 0, 'message' => 'occupation_code is required.'], 422);
        }

        $data = $this->service->suggestMentors($code, $tenant, 10);
        // Staff names and job titles are shown to the caller (often a minor), so keep a record of who looked.
        Log::channel('daily')->info('career.mentor_suggestions viewed', [
            'viewer_id' => (int) $request->session()->get('user_id'),
            'school_id' => $tenant,
            'student_view' => $this->callerIsStudent($request),
            'occupation_code' => $code,
            'mentors_returned' => count($data['mentors'] ?? []),
        ]);
        if ($this->callerIsStudent($request)) {
            $data = $this->studentSafeMentors($data);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ]);
    }

    private function callerIsStudent(Request $request): bool
    {
        $profile = strtolower((string) $request->session()->get('user_profile_name'));

        return (bool) $request->session()->get('is_student') || in_array($profile, ['student', 'parent'], true);
    }

    /**
     * Students see who could mentor them, not staff records: no staff id and no
     * self-rated skill levels, only name, job title, match score and skill names.
     */
    private function studentSafeMentors(array $data): array
    {
        $data['mentors'] = array_map(static function (array $m): array {
            return [
                'name' => $m['name'] ?? null,
                'job_title' => $m['job_title'] ?? null,
                'score' => $m['score'] ?? null,
                'matched_skills' => array_map(
                    static fn ($s) => ['skill_title' => $s['skill_title'] ?? null],
                    $m['matched_skills'] ?? []
                ),
            ];
        }, $data['mentors'] ?? []);

        return $data;
    }

    /** GET careerMentorCrosswalk?search= : unmapped staff skills with PROPOSED elements, plus confirmed mappings. */
    public function crosswalk(Request $request): JsonResponse
    {
        $denied = $this->authorise($request, self::READ_ROLES, self::READ_PROFILE_NAMES);
        if ($denied) {
            return $denied;
        }

        $data = $this->service->managementView(
            (int) $request->session()->get('sub_institute_id'),
            $request->query('search') !== null ? (string) $request->query('search') : null,
            (int) ($request->query('limit') ?: 50)
        );
        $data['can_write'] = $this->allowed($request, self::WRITE_ROLES, self::WRITE_PROFILE_NAMES);
        $data['note'] = 'Suggested elements are text-similarity proposals only. Nothing is saved until you confirm it.';

        return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $data]);
    }

    /** POST careerMentorCrosswalk {skill_id, element_id} */
    public function save(Request $request): JsonResponse
    {
        $denied = $this->authorise($request, self::WRITE_ROLES, self::WRITE_PROFILE_NAMES);
        if ($denied) {
            return $denied;
        }

        $skillId = (int) $request->input('skill_id');
        $elementId = trim((string) $request->input('element_id'));
        if ($skillId <= 0 || $elementId === '' || strlen($elementId) > 20) {
            return response()->json(['status_code' => 0, 'message' => 'skill_id and element_id are required.'], 422);
        }

        $result = $this->service->saveMapping(
            (int) $request->session()->get('sub_institute_id'),
            $skillId,
            $elementId,
            (int) $request->session()->get('user_id')
        );

        if (! $result['ok']) {
            $messages = [
                'crosswalk_not_ready' => ['Mentor matching is not set up yet.', 409],
                'skill_not_found' => ['Skill not found.', 404],
                'element_not_found' => ['O*NET element not found.', 404],
            ];
            [$message, $status] = $messages[$result['error']] ?? ['Could not save the mapping.', 422];

            return response()->json(['status_code' => 0, 'message' => $message], $status);
        }

        // Who mapped which skill to which element, for the record: this steers which staff are shown
        // to students as mentors.
        Log::channel('daily')->info('career.mentor_crosswalk saved', [
            'actor_id' => (int) $request->session()->get('user_id'),
            'school_id' => (int) $request->session()->get('sub_institute_id'),
            'skill_id' => $skillId,
            'element_id' => $elementId,
            'ip' => $request->ip(),
        ]);

        return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $result]);
    }

    /** DELETE careerMentorCrosswalk/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $denied = $this->authorise($request, self::WRITE_ROLES, self::WRITE_PROFILE_NAMES);
        if ($denied) {
            return $denied;
        }

        $deleted = $this->service->deleteMapping((int) $request->session()->get('sub_institute_id'), $id);
        Log::channel('daily')->info('career.mentor_crosswalk deleted', [
            'actor_id' => (int) $request->session()->get('user_id'),
            'school_id' => (int) $request->session()->get('sub_institute_id'),
            'mapping_id' => $id,
            'found' => (bool) $deleted,
            'ip' => $request->ip(),
        ]);

        return $deleted
            ? response()->json(['status_code' => 1, 'message' => 'SUCCESS'])
            : response()->json(['status_code' => 0, 'message' => 'Mapping not found.'], 404);
    }

    private function allowed(Request $request, array $roleKeys, array $profileNames): bool
    {
        $userId = (int) $request->session()->get('user_id');
        if ($userId <= 0) {
            return false;
        }

        $profile = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.id', $userId)
            ->where('u.sub_institute_id', (int) $request->session()->get('sub_institute_id'))
            ->first(['p.role_key', 'p.name']);

        if ($profile === null) {
            return false;
        }

        // Profile names are editable by anyone who can save profiles, so a name alone is not
        // proof of an admin: the account must also carry the admin flag.
        $nameMatches = in_array(strtolower(trim((string) $profile->name)), $profileNames, true)
            && (int) $request->session()->get('is_admin') >= 1;

        return in_array((string) $profile->role_key, $roleKeys, true) || $nameMatches;
    }

    private function authorise(Request $request, array $roleKeys, array $profileNames): ?JsonResponse
    {
        // These routes are CSRF-exempt because the SPA calls them with a bearer token. A
        // browser cannot attach that header cross-site, so requiring it on writes closes
        // the forgery gap for requests that only carry a session cookie.
        if (! $request->isMethodSafe() && ! $request->bearerToken()) {
            return response()->json(['status_code' => 0, 'message' => 'Bearer token required.'], 401);
        }
        if ((int) $request->session()->get('sub_institute_id') <= 0 || empty($request->session()->get('user_id'))) {
            return response()->json(['status_code' => 0, 'message' => 'Unauthenticated.'], 401);
        }
        if (! $this->allowed($request, $roleKeys, $profileNames)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You do not have permission to manage career mentor mappings.',
            ], 403);
        }

        return null;
    }
}
