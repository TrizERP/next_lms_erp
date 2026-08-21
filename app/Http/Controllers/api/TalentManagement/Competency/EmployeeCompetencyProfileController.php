<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\EmployeeCompetencyProfileController`.
 *
 * Talent Management > Employee Profiles. Logic, field names and response
 * shapes are unchanged from the source; only tenant/actor context resolution
 * was adapted to the hydrated session, per `ResolvesCompetencyContext`.
 *
 * One adaptation beyond identity: `show()` and `careerPath()` read the
 * source's `s_competency_assessments` / `s_competency_assessment_cycles`
 * tables for the "latest assessment" metric and the jobrole fallback. Those
 * tables belong to the Competency Assessment Cycle feature, which is out of
 * scope for this port (only Employee Profiles / Certifications / Development
 * & Career Paths were migrated) and does not exist in this schema. Both reads
 * are guarded with `Schema::hasTable()` so they degrade to "N/A" / the next
 * fallback in the chain instead of a fatal query, rather than silently
 * fabricating the assessment-cycle feature.
 */
class EmployeeCompetencyProfileController extends Controller
{
    use ResolvesCompetencyContext;

    /**
     * GET /api/competency/employee-profiles
     *
     * Tenant from the session. An HR user cannot list another organization's
     * people because $sid is never taken from the request.
     */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid = (int) $context['sub_institute_id'];
        $q   = trim((string) $request->input('q', ''));

        $rows = DB::table('tbluser as u')
            ->leftJoin('s_user_jobrole as j', 'j.id', '=', 'u.jobtitle_id')
            ->where('u.sub_institute_id', $sid)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('u.first_name', 'like', "%{$q}%")
                      ->orWhere('u.last_name', 'like', "%{$q}%")
                      ->orWhere('u.email', 'like', "%{$q}%");
                });
            })
            ->orderBy('u.first_name')->orderBy('u.last_name')
            ->limit(500)
            ->get([
                'u.id',
                'u.first_name',
                'u.last_name',
                'u.email',
                'u.jobtitle_id',
                'j.jobrole',
            ]);

        $total = DB::table('tbluser')->where('sub_institute_id', $sid)->count();

        return response()->json([
            'status' => 1,
            'data'   => [
                'employees' => $rows,
                'shown'     => $rows->count(),
                'total'     => $total,
            ],
            // Stated so nobody reads 500 as "all of them".
            'truncated' => $total > 500 && $q === '',
            'empty_is_expected' => $rows->isEmpty(),
            'empty_reason' => $rows->isEmpty()
                ? ($q !== '' ? 'No employee matches that search.' : 'No employees have been added to this organization yet.')
                : null,
        ]);
    }

    public function show(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        // Resolve the route id to a subject the caller is actually allowed to
        // act on, or refuse.
        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $sid = $context['sub_institute_id'];

        // 1. Fetch User Data using the actual tbluser column names
        $user = DB::table('tbluser as u')
            ->where('u.id', $id)
            ->where('u.sub_institute_id', $sid)
            ->select(
                'u.id',
                'u.first_name',
                'u.last_name',
                'u.employee_no',
                'u.jobtitle_id',
                'u.department_id',
                'u.joined_date',
                'u.supervisor_opt',
                'u.total_experience',
                'u.city'
            )
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'User not found'
            ], 404);
        }

        // Resolve job role name from s_user_jobrole using jobtitle_id
        $jobRole = null;
        if ($user->jobtitle_id) {
            $jobRole = DB::table('s_user_jobrole')
                ->where('id', $user->jobtitle_id)
                ->whereNull('deleted_at')
                ->first();
        }
        $jobRoleName = $jobRole ? $jobRole->jobrole : 'N/A';

        // Resolve department name from hrms_departments_mapping
        $deptName = 'N/A';
        if ($user->department_id) {
            $dept = DB::table('hrms_departments_mapping')
                ->where('id', $user->department_id)
                ->first();
            $deptName = $dept ? $dept->department : 'N/A';
        }

        // Resolve manager name from supervisor_opt
        $reportsTo = 'N/A';
        if ($user->supervisor_opt) {
            $manager = DB::table('tbluser')
                ->where('id', $user->supervisor_opt)
                ->select('first_name', 'last_name')
                ->first();
            if ($manager) {
                $reportsTo = trim($manager->first_name . ' ' . $manager->last_name);
            }
        }

        // 2. Fetch required skills for this job role
        $requiredSkills = collect();
        if ($jobRoleName !== 'N/A') {
            $requiredSkills = DB::table('s_user_skill_jobrole')
                ->where('jobrole', $jobRoleName)
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('skill');
        }

        // 3. Fetch employee's current skills from s_skill_matrix
        $currentSkills = DB::table('s_skill_matrix as m')
            ->join('s_users_skills as s', 'm.skill_id', '=', 's.id')
            ->leftJoin('tbluser as asr', 'm.updated_by', '=', 'asr.id')
            ->where('m.user_id', $id)
            ->whereNull('m.deleted_at')
            ->whereNull('s.deleted_at')
            ->select(
                's.id as skill_id',
                's.title',
                's.category',
                's.description as skill_description',
                'm.id as matrix_id',
                'm.skill_level',
                'm.interest_level',
                'm.knowledge',
                'm.ability',
                'm.updated_at',
                'asr.first_name as assessor_fname',
                'asr.last_name as assessor_lname'
            )
            ->get();

        // 4. Build competencies list and calculate gap
        $competencies = [];
        $totalScore = 0;
        $totalSkills = 0;

        foreach ($currentSkills as $skill) {
            $requiredLevel = isset($requiredSkills[$skill->title]) ? (int) $requiredSkills[$skill->title]->proficiency_level : 0;
            $currentLevel = (int) $skill->skill_level;
            $gap = $currentLevel - $requiredLevel;

            $cat = $skill->category ?: 'General Competencies';

            if (!isset($competencies[$cat])) {
                $competencies[$cat] = [
                    'category' => $cat,
                    'items' => []
                ];
            }

            $assessorName = trim(($skill->assessor_fname ?: '') . ' ' . ($skill->assessor_lname ?: ''));

            $competencies[$cat]['items'][] = [
                'skill_id' => (int) $skill->skill_id,
                'matrix_id' => (int) $skill->matrix_id,
                'name' => $skill->title,
                'description' => $skill->skill_description ?: '',
                'required' => $requiredLevel,
                'current' => $currentLevel,
                'interest_level' => (int) ($skill->interest_level ?: 0),
                'knowledge' => (int) ($skill->knowledge ?: 0),
                'ability' => (int) ($skill->ability ?: 0),
                'gap' => $gap,
                'date' => $skill->updated_at ? date('d M Y', strtotime($skill->updated_at)) : 'N/A',
                'assessor' => $assessorName ?: 'System'
            ];

            $totalScore += $currentLevel;
            $totalSkills++;
        }

        $overallScore = $totalSkills > 0 ? round($totalScore / $totalSkills, 1) : 0;

        // 5. Fetch latest assessment date. s_competency_assessments/_cycles
        // belong to the (out-of-scope) Assessment Cycle feature - guarded so a
        // schema without it degrades to "N/A" instead of a fatal query.
        $latestAssessment = null;
        if (Schema::hasTable('s_competency_assessments')) {
            $latestAssessment = DB::table('s_competency_assessments as a')
                ->leftJoin('s_competency_assessment_cycles as c', 'a.cycle_id', '=', 'c.id')
                ->where('a.user_id', $id)
                ->where('a.sub_institute_id', $sid)
                ->orderByDesc('a.completed_at')
                ->select('a.completed_at', 'c.name as cycle_name')
                ->first();
        }

        // Format data for frontend
        $fname = $user->first_name ?: 'Unknown';
        $lname = $user->last_name ?: '';
        $initials = strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));

        $data = [
            'employee' => [
                'id' => (string)$user->id,
                'name' => trim($fname . ' ' . $lname),
                'initials' => $initials,
                'emp_id' => $user->employee_no ?: 'N/A',
                'role' => $jobRoleName,
                'department' => $deptName,
                'location' => $user->city ?: 'N/A',
                'joined' => $user->joined_date ? date('d M Y', strtotime($user->joined_date)) : 'N/A',
                'reports_to' => $reportsTo,
                'experience' => $user->total_experience ? $user->total_experience . ' Years' : 'N/A',
                'type' => 'Full Time'
            ],
            'metrics' => [
                'latest_assessment' => $latestAssessment && $latestAssessment->completed_at ? date('d M Y', strtotime($latestAssessment->completed_at)) : 'N/A',
                'latest_cycle' => $latestAssessment ? $latestAssessment->cycle_name : 'N/A',
                'overall_score' => $overallScore
            ],
            'competencies' => array_values($competencies)
        ];

        return response()->json([
            'status' => 1,
            'message' => 'Employee competency profile fetched successfully',
            'data' => $data
        ]);
    }

    /**
     * GET /api/competency/employee-profiles/{id}/available-skills
     * Returns skills from the catalog NOT already assigned to this employee.
     */
    public function availableSkills(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $sid = $context['sub_institute_id'];

        $existingSkillIds = DB::table('s_skill_matrix')
            ->where('user_id', $id)
            ->whereNull('deleted_at')
            ->pluck('skill_id')
            ->toArray();

        $skills = DB::table('s_users_skills')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('approve_status', 'Approved')
            ->whereNotIn('id', $existingSkillIds)
            ->orderBy('category')
            ->orderBy('title')
            ->get(['id', 'title', 'category', 'description']);

        return response()->json([
            'status' => 1,
            'message' => 'Available skills fetched',
            'data' => $skills
        ]);
    }

    /**
     * POST /api/competency/employee-profiles/{id}/skills
     * Add a competency rating for an employee (insert into s_skill_matrix).
     */
    public function addSkill(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $validator = Validator::make($request->all(), [
            'skill_id' => 'required|integer',
            'skill_level' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent duplicates
        $exists = DB::table('s_skill_matrix')
            ->where('user_id', $id)
            ->where('skill_id', $request->input('skill_id'))
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 0,
                'message' => 'This competency is already assigned to this employee.'
            ], 422);
        }

        $matrixId = DB::table('s_skill_matrix')->insertGetId([
            'user_id' => $id,
            'skill_id' => $request->input('skill_id'),
            'skill_level' => $request->input('skill_level'),
            'interest_level' => $request->input('interest_level', 0),
            // s_skill_matrix.type is an ENUM('skill','knowledge','ability',
            // 'attitude','behaviour') - it names which KASBA dimension the row
            // records. A competency rating IS a skill row, matching the source.
            'type' => 'skill',
            'created_by' => $context['user_id'],
            'updated_by' => $context['user_id'],
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $skillName = DB::table('s_users_skills')->where('id', $request->input('skill_id'))->value('title');

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'added_employee_competency',
            'Rated "' . ($skillName ?: 'competency') . '" at level ' . $request->input('skill_level')
                . ' for ' . $this->employeeName($id),
            'employee_skill',
            (int) $id,
            $skillName ?: ('Competency #' . $request->input('skill_id'))
        );

        return response()->json([
            'status' => 1,
            'message' => 'Competency added successfully',
            'data' => ['id' => $matrixId]
        ], 201);
    }

    /**
     * PUT /api/competency/employee-profiles/{id}/skills/{matrixId}
     * Update an existing competency rating.
     */
    public function updateSkill(Request $request, $id, $matrixId)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $validator = Validator::make($request->all(), [
            'skill_level' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $row = DB::table('s_skill_matrix')
            ->where('id', $matrixId)
            ->where('user_id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Skill rating not found'], 404);
        }

        $update = [
            'skill_level' => $request->input('skill_level'),
            'interest_level' => $request->input('interest_level', $row->interest_level),
        ];

        DB::table('s_skill_matrix')->where('id', $matrixId)->update($update + [
            'updated_by' => $context['user_id'],
            'updated_at' => now()
        ]);

        $skillName = DB::table('s_users_skills')->where('id', $row->skill_id)->value('title');

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'updated_employee_competency',
            'Re-rated "' . ($skillName ?: 'competency') . '" for ' . $this->employeeName($id),
            'employee_skill',
            (int) $id,
            $skillName ?: ('Competency #' . $row->skill_id),
            $this->diffChanges($row, $update, [
                'skill_level'    => 'Proficiency Level',
                'interest_level' => 'Interest Level',
            ])
        );

        return response()->json([
            'status' => 1,
            'message' => 'Competency rating updated successfully'
        ]);
    }

    /**
     * GET /api/competency/employee-profiles/{id}/skills/{skillId}/history
     * Returns the update history for a specific skill from s_skill_matrix.
     */
    public function skillHistory(Request $request, $id, $skillId)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        // Since we don't have a dedicated history table, return the current entry
        // along with activity log entries for this skill.
        $current = DB::table('s_skill_matrix as m')
            ->join('s_users_skills as s', 'm.skill_id', '=', 's.id')
            ->leftJoin('tbluser as asr', 'm.updated_by', '=', 'asr.id')
            ->where('m.user_id', $id)
            ->where('m.skill_id', $skillId)
            ->select(
                's.title',
                's.category',
                's.description',
                'm.skill_level',
                'm.interest_level',
                'm.knowledge',
                'm.ability',
                'm.created_at',
                'm.updated_at',
                'asr.first_name as assessor_fname',
                'asr.last_name as assessor_lname'
            )
            ->first();

        if (!$current) {
            return response()->json(['status' => 0, 'message' => 'Skill not found for this user'], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Skill history fetched',
            'data' => [
                'skill' => [
                    'name' => $current->title,
                    'category' => $current->category,
                    'description' => $current->description,
                    'current_level' => (int) $current->skill_level,
                    'interest_level' => (int) ($current->interest_level ?: 0),
                    'knowledge' => (int) ($current->knowledge ?: 0),
                    'ability' => (int) ($current->ability ?: 0),
                    'assessor' => trim(($current->assessor_fname ?: '') . ' ' . ($current->assessor_lname ?: '')),
                    'created_at' => $current->created_at,
                    'updated_at' => $current->updated_at
                ],
                'timeline' => [
                    [
                        'action' => 'Current Rating',
                        'level' => (int) $current->skill_level,
                        'date' => $current->updated_at ? date('d M Y', strtotime($current->updated_at)) : 'N/A',
                        'by' => trim(($current->assessor_fname ?: '') . ' ' . ($current->assessor_lname ?: '')) ?: 'System'
                    ],
                    [
                        'action' => 'Initial Assessment',
                        'level' => (int) $current->skill_level,
                        'date' => $current->created_at ? date('d M Y', strtotime($current->created_at)) : 'N/A',
                        'by' => 'System'
                    ]
                ]
            ]
        ]);
    }

    /** Private competency-profile note for this employee. */
    public function notes(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $row = DB::table('s_competency_employee_notes')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('user_id', $id)
            ->first();

        return response()->json([
            'status'  => 1,
            'message' => 'Notes fetched successfully',
            'data'    => [
                'note'       => $row->note ?? '',
                'updated_at' => $row->updated_at ?? null,
            ],
        ]);
    }

    /** Create/update this employee's private note (one row per tenant+employee). */
    public function saveNotes(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'note' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $existing = DB::table('s_competency_employee_notes')
            ->where('sub_institute_id', $sid)
            ->where('user_id', $id)
            ->first();

        if ($existing) {
            DB::table('s_competency_employee_notes')->where('id', $existing->id)->update([
                'note'       => $request->input('note'),
                'updated_by' => $context['user_id'],
                'updated_at' => now(),
            ]);
        } else {
            DB::table('s_competency_employee_notes')->insert([
                'sub_institute_id' => $sid,
                'user_id'          => (int) $id,
                'note'             => $request->input('note'),
                'created_by'       => $context['user_id'],
                'updated_by'       => $context['user_id'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'commented_employee_profile',
            ($existing ? 'Updated notes on ' : 'Added notes on ') . $this->employeeName($id) . "'s profile",
            'employee_note',
            (int) $id,
            $this->employeeName($id)
        );

        return response()->json(['status' => 1, 'message' => 'Notes saved successfully']);
    }

    /** This employee's certifications (s_competency_certifications). */
    public function certifications(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $rows = DB::table('s_competency_certifications')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('user_id', $id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        $data = $rows->map(fn ($r) => [
            'id'            => (int) $r->id,
            'name'          => $r->name,
            'issuing_body'  => $r->issuing_body,
            'credential_id' => $r->credential_id,
            'status'        => $r->status,
            'issued_date'   => $r->issued_date ? date('d M Y', strtotime($r->issued_date)) : null,
            'expiry_date'   => $r->expiry_date ? date('d M Y', strtotime($r->expiry_date)) : null,
        ])->all();

        return response()->json(['status' => 1, 'message' => 'Certifications fetched successfully', 'data' => $data]);
    }

    /** This employee's development plans (s_competency_development_plans). */
    public function developmentPlans(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $rows = DB::table('s_competency_development_plans')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('user_id', $id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        $data = $rows->map(fn ($r) => [
            'id'         => (int) $r->id,
            'title'      => $r->title,
            'status'     => $r->status,
            'progress'   => (int) $r->progress,
            'start_date' => $r->start_date ? date('d M Y', strtotime($r->start_date)) : null,
            'due_date'   => $r->due_date ? date('d M Y', strtotime($r->due_date)) : null,
        ])->all();

        return response()->json(['status' => 1, 'message' => 'Development plans fetched successfully', 'data' => $data]);
    }

    /** This employee's evidence items (s_competency_evidence). */
    public function evidence(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $rows = DB::table('s_competency_evidence as e')
            ->leftJoin('s_users_skills as s', 's.id', '=', 'e.competency_id')
            ->where('e.sub_institute_id', $context['sub_institute_id'])
            ->where('e.user_id', $id)
            ->whereNull('e.deleted_at')
            ->orderByDesc('e.id')
            ->get(['e.id', 'e.title', 'e.evidence_type', 'e.description', 'e.link', 'e.status', 'e.competency_id', 's.title as competency_name', 'e.created_at']);

        $data = $rows->map(fn ($r) => [
            'id'              => (int) $r->id,
            'title'           => $r->title,
            'evidence_type'   => $r->evidence_type,
            'description'     => $r->description,
            'link'            => $r->link,
            'status'          => $r->status,
            'competency_id'   => $r->competency_id ? (int) $r->competency_id : null,
            'competency_name' => $r->competency_name,
            'date'            => $r->created_at ? date('d M Y', strtotime($r->created_at)) : null,
        ])->all();

        return response()->json(['status' => 1, 'message' => 'Evidence fetched successfully', 'data' => $data]);
    }

    public function storeEvidence(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:191',
            'evidence_type' => 'nullable|string|max:50',
            'description'   => 'nullable|string',
            'link'          => 'nullable|string|max:500',
            'competency_id' => 'nullable|integer',
            'status'        => 'nullable|in:verified,pending',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $newId = DB::table('s_competency_evidence')->insertGetId([
            'sub_institute_id' => $sid,
            'user_id'          => (int) $id,
            'competency_id'    => $request->input('competency_id'),
            'title'            => $request->input('title'),
            'evidence_type'    => $request->input('evidence_type', 'document'),
            'description'      => $request->input('description'),
            'link'             => $request->input('link'),
            'status'           => $request->input('status', 'pending'),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->logCompetencyActivity($sid, $context['user_id'], 'added_evidence', 'Added evidence "' . $request->input('title') . '"', 'evidence', $newId, $request->input('title'));

        return response()->json(['status' => 1, 'message' => 'Evidence added successfully', 'data' => ['id' => $newId]], 201);
    }

    public function deleteEvidence(Request $request, $id, $evidenceId)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $evidence = DB::table('s_competency_evidence')
            ->where('id', $evidenceId)->where('user_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])->whereNull('deleted_at')->first();
        if (!$evidence) {
            return response()->json(['status' => 0, 'message' => 'Evidence not found'], 404);
        }

        DB::table('s_competency_evidence')->where('id', $evidenceId)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        $this->logCompetencyActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'deleted_evidence',
            'Removed evidence "' . $evidence->title . '"',
            'evidence',
            (int) $evidenceId,
            $evidence->title
        );

        return response()->json(['status' => 1, 'message' => 'Evidence removed successfully']);
    }

    /**
     * Career path: the employee's current role and the roles it can progress to
     * (from s_user_jobrole.related_jobrole - a comma-separated role list).
     */
    public function careerPath(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $this->competencySubject($context, $id);
        if (!is_int($id)) {
            return $id;
        }
        $sid = $context['sub_institute_id'];

        $user = DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $sid)->first();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not found'], 404);
        }

        $currentRole = null;
        if ($user->jobtitle_id) {
            $currentRole = DB::table('s_user_jobrole')
                ->where('id', $user->jobtitle_id)->whereNull('deleted_at')->first();
        }

        // Fallback: this tenant doesn't populate tbluser.jobtitle_id, so derive
        // the current role from the employee's most recent assessment's jobrole
        // (s_competency_assessments belongs to the out-of-scope Assessment Cycle
        // feature - guarded the same way as EmployeeCompetencyProfileController@show).
        $currentRoleName = $currentRole->jobrole ?? null;
        if (!$currentRole && Schema::hasTable('s_competency_assessments')) {
            $recent = DB::table('s_competency_assessments')
                ->where('sub_institute_id', $sid)->where('user_id', $id)
                ->whereNull('deleted_at')->whereNotNull('jobrole')->where('jobrole', '!=', '')
                ->orderByDesc('id')->first();
            if ($recent) {
                $currentRoleName = $recent->jobrole;
                $currentRole = DB::table('s_user_jobrole')
                    ->where('sub_institute_id', $sid)->where('jobrole', $currentRoleName)
                    ->whereNull('deleted_at')->first();
            }
        }

        $current = $currentRoleName ? [
            'jobrole'     => $currentRoleName,
            'department'  => $currentRole->department ?? null,
            'description' => $currentRole->description ?? null,
            'job_level'   => ($currentRole->job_level ?? null) ?: null,
        ] : null;

        // Preferred: explicit related roles. Fallback: other roles in the same
        // department (lateral moves) so the path is meaningful even when the
        // role has no related_jobrole configured.
        $names = [];
        $pathSource = 'related';
        if ($currentRole && !empty($currentRole->related_jobrole)) {
            $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $currentRole->related_jobrole)))));
        }
        if (empty($names) && $currentRole && !empty($currentRole->department)) {
            $names = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)->whereNull('deleted_at')
                ->where('department', $currentRole->department)
                ->where('jobrole', '!=', $currentRoleName)
                ->whereNotNull('jobrole')->where('jobrole', '!=', '')
                ->distinct()->orderBy('jobrole')->limit(8)->pluck('jobrole')->all();
            $pathSource = 'department';
        }

        $nextRoles = [];
        foreach (array_slice($names, 0, 12) as $name) {
            $role = DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)->where('jobrole', $name)->whereNull('deleted_at')->first();
            $requiredSkills = DB::table('s_user_skill_jobrole')
                ->where('sub_institute_id', $sid)->where('jobrole', $name)->whereNull('deleted_at')
                ->distinct()->count('skill');

            $nextRoles[] = [
                'jobrole'         => $name,
                'department'      => $role->department ?? null,
                'description'     => $role ? \Illuminate\Support\Str::limit((string) $role->description, 160) : null,
                'required_skills' => $requiredSkills,
            ];
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Career path fetched successfully',
            'data'    => ['current' => $current, 'next_roles' => $nextRoles, 'path_source' => $pathSource],
        ]);
    }

    /**
     * The employee's display name, for activity descriptions. The subject of a
     * profile write is always the route param, never the calling actor.
     */
    private function employeeName($userId): string
    {
        $user = DB::table('tbluser')->where('id', $userId)->first();

        if (!$user) {
            return 'employee #' . $userId;
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->user_name ?? ('employee #' . $userId));
    }
}
