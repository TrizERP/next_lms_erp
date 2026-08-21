<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from hp_erp's `App\Http\Controllers\user\UserSkillController::getUserSkills`
 * (`GET /api/user-skills/{user_id}`, called by the Create Task modal's
 * "assign to" flow to populate the skills multi-select for the selected
 * employee).
 *
 * NOT a literal port: hp_erp resolves the employee's jobrole -> skills via
 * its tenant-scoped `s_library_map`/`s_users_skills.id` pair, neither of
 * which exist in this target (that table was never migrated here - see
 * `database/migrations/2026_08_20_090100_create_s_user_skill_jobrole_table.php`'s
 * docblock). This target already solved the identical "jobrole -> skills"
 * lookup for the Employee Directory profile's Jobrole Skills tab
 * (`EmployeeDirectoryController::buildJobroleSkillsAndTasks()`), via the
 * global `s_jobrole_skills` catalog joined to the tenant's `s_users_skills`
 * by an exact (case/whitespace-insensitive) title match - that is reused
 * here verbatim instead of re-introducing the source's broken table.
 */
class UserSkillController extends Controller
{
    use ResolvesTaskManagementContext;

    public function index(Request $request, int $userId)
    {
        $context = $this->taskManagementContext($request);

        $assignedJobroleId = DB::table('tbluser')
            ->where('id', $userId)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->value('allocated_standards');

        if (!$assignedJobroleId || !Schema::hasTable('s_user_jobrole')) {
            return $this->taskManagementResponse([], 'Jobrole not allocated to user.');
        }

        $assignedJobroleName = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('id', (int) $assignedJobroleId)
            ->whereNull('deleted_at')
            ->value('jobrole');

        if (!$assignedJobroleName) {
            return $this->taskManagementResponse([], 'Jobrole not found for user.');
        }

        $skills = [];
        if (Schema::hasTable('s_jobrole_skills') && Schema::hasTable('s_users_skills')) {
            $skills = DB::table('s_jobrole_skills as js')
                ->join('s_users_skills as us', function ($join) {
                    $join->on(DB::raw('LOWER(TRIM(us.title))'), '=', DB::raw('LOWER(TRIM(js.skill))'));
                })
                ->where('js.jobrole', $assignedJobroleName)
                ->where('us.sub_institute_id', $context['sub_institute_id'])
                ->whereNull('js.deleted_at')
                ->whereNull('us.deleted_at')
                ->select('us.id', 'us.title as name')
                ->get()
                ->unique('id')
                ->values();
        }

        return $this->taskManagementResponse($skills, 'Skills fetched successfully.');
    }
}
