<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Learning Dashboard — G2G LMS migration (Package 1).
 *
 * Ported from hp_erp's `App\Http\Controllers\lms_course_enroll\LmsCourseEnrollController`
 * (enrolledCourses/availableCourses/enroll) and
 * `App\Http\Controllers\Api\SkillDevelopmentController` (the widgets: skill
 * progress, streak, weekly goal, achievements, peer comparison, calendar,
 * recent activity).
 *
 * ── SCHEMA ADAPTATIONS FROM hp_erp (see Package 1 report for the full list) ──
 *   - `content_master`, `chapter_master`, `calendar_events` have no
 *     `deleted_at` column in this schema, so filters against it are dropped
 *     for those tables only (every table this controller filters `deleted_at`
 *     on genuinely has the column: `lms_course_enroll`, `lms_content_progress`,
 *     `hrms_attendances`, `s_skill_matrix`, `s_users_skills`, `sub_std_map`).
 *   - `lms_achievements` does not exist and is out of this package's approved
 *     migration list; `achievements()` degrades gracefully instead of
 *     querying a missing table (Schema::hasTable() guard).
 */
class LearningDashboardController extends Controller
{
    use ResolvesLmsIdentity;

    /* ------------------------------------------------------------------ *
     * Enrolled / available courses, enrol — ported from
     * LmsCourseEnrollController::index / available / store.
     * ------------------------------------------------------------------ */

    /**
     * GET /g2g-lms/learning-dashboard/enrolled-courses
     *
     * `user_id` is read from the request (not forced to the caller): an
     * administrator may be looking at somebody else's enrolments. Bounded by
     * the caller's own tenant regardless of whose id is asked for.
     */
    public function enrolledCourses(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);
        $userId = $request->input('user_id') ?? $request->header('user_id');

        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        $latestEnrollments = DB::table('lms_course_enroll')
            ->select('course_id', DB::raw('MAX(created_at) as latest_enrolled_at'))
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->groupBy('course_id');

        $courses = DB::table('lms_course_enroll as e')
            ->join('sub_std_map as s', 'e.course_id', '=', 's.id')
            ->leftJoin('hrms_departments as d', 'd.id', '=', 's.standard_id')
            ->joinSub($latestEnrollments, 'latest', function ($join) {
                $join->on('e.course_id', '=', 'latest.course_id')
                     ->on('e.created_at', '=', 'latest.latest_enrolled_at');
            })
            ->where('e.user_id', $userId)
            ->where('s.sub_institute_id', $subInstituteId)
            ->whereNull('e.deleted_at')
            ->whereNull('s.deleted_at')
            ->select(
                's.*',
                'e.id as enrollment_id',
                'd.department as standard_name',
                'e.status as enrollment_status',
                'e.start_date',
                'e.end_date',
                'e.created_at as enrolled_at'
            )
            ->get();

        return response()->json([
            'status' => true,
            'data' => $courses,
        ]);
    }

    /**
     * GET /g2g-lms/learning-dashboard/available-courses
     */
    public function availableCourses(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);
        $userId = $request->input('user_id') ?? $request->header('user_id');

        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }
        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        $enrolledCourseIds = DB::table('lms_course_enroll')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('course_id')
            ->all();

        $query = DB::table('sub_std_map as s')
            ->leftJoin('hrms_departments as d', 'd.id', '=', 's.standard_id')
            ->where('s.sub_institute_id', $subInstituteId)
            ->whereNull('s.deleted_at')
            ->where('s.status', 1);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('s.display_name', 'like', "%{$search}%")
                  ->orWhere('s.subject_category', 'like', "%{$search}%")
                  ->orWhere('s.jobrole', 'like', "%{$search}%");
            });
        }

        $excludeEnrolled = $request->input('exclude_enrolled', '1') !== '0';
        if ($excludeEnrolled && !empty($enrolledCourseIds)) {
            $query->whereNotIn('s.id', $enrolledCourseIds);
        }

        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $courses = $query
            ->orderBy('s.display_name')
            ->limit($limit)
            ->get([
                's.id',
                's.display_name',
                's.display_image',
                's.subject_type',
                's.subject_category',
                's.jobrole',
                's.proficiency',
                's.standard_id',
                'd.department as standard_name',
            ])
            ->map(function ($course) use ($enrolledCourseIds) {
                $course->is_enrolled = in_array($course->id, $enrolledCourseIds);
                return $course;
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $courses,
        ]);
    }

    /**
     * Decide whether a learner may enrol, and how — ported from
     * LmsCourseEnrollController::checkEnrolmentEligibility. Depends on
     * `lms_course_settings` / `lms_course_prerequisites` (Package 3's
     * Course Builder tables); a course with no settings row behaves exactly
     * as open enrolment, so this never regresses a course Package 3 has not
     * touched yet.
     */
    private function checkEnrolmentEligibility($courseId, $userId, $subInstituteId): array
    {
        $open = ['allowed' => true, 'reason' => null, 'status' => 'enrolled'];

        if (! Schema::hasTable('lms_course_settings') || ! Schema::hasTable('lms_course_prerequisites')) {
            return $open;
        }

        $settings = DB::table('lms_course_settings')
            ->where('course_id', $courseId)
            ->whereNull('deleted_at')
            ->first();

        $prerequisites = DB::table('lms_course_prerequisites as p')
            ->join('sub_std_map as s', 's.id', '=', 'p.prerequisite_course_id')
            ->where('p.course_id', $courseId)
            ->whereNull('p.deleted_at')
            ->whereNull('s.deleted_at')
            ->pluck('s.display_name', 'p.prerequisite_course_id');

        if ($prerequisites->isNotEmpty()) {
            $completed = DB::table('lms_course_enroll')
                ->where('user_id', $userId)
                ->whereIn('course_id', $prerequisites->keys())
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->pluck('course_id')
                ->all();

            $missing = $prerequisites->except($completed);

            if ($missing->isNotEmpty()) {
                return [
                    'allowed' => false,
                    'reason' => 'Complete ' . $missing->implode(', ') . ' first.',
                    'status' => null,
                ];
            }
        }

        if (!$settings) {
            return $open;
        }

        $today = now()->startOfDay();

        if ($settings->available_from && $today->lt(\Carbon\Carbon::parse($settings->available_from))) {
            return [
                'allowed' => false,
                'reason' => 'This course opens on '
                    . \Carbon\Carbon::parse($settings->available_from)->format('d M Y') . '.',
                'status' => null,
            ];
        }

        if ($settings->available_until && $today->gt(\Carbon\Carbon::parse($settings->available_until))) {
            return [
                'allowed' => false,
                'reason' => 'Enrolment for this course closed on '
                    . \Carbon\Carbon::parse($settings->available_until)->format('d M Y') . '.',
                'status' => null,
            ];
        }

        if ($settings->visibility === 'restricted') {
            $learner = DB::table('tbluser')
                ->where('id', $userId)
                ->first(['department_id', 'user_profile_id']);

            $departments = $settings->restrict_departments
                ? json_decode($settings->restrict_departments, true)
                : null;

            if (is_array($departments) && $departments !== []) {
                if (!$learner || !in_array((int) $learner->department_id, array_map('intval', $departments), true)) {
                    return [
                        'allowed' => false,
                        'reason' => 'This course is restricted to specific departments.',
                        'status' => null,
                    ];
                }
            }

            $roles = $settings->restrict_roles ? json_decode($settings->restrict_roles, true) : null;

            if (is_array($roles) && $roles !== []) {
                $profileName = $learner
                    ? DB::table('tbluserprofilemaster')->where('id', $learner->user_profile_id)->value('name')
                    : null;

                $matches = collect($roles)->contains(
                    fn ($role) => $profileName && strcasecmp(trim((string) $role), trim($profileName)) === 0
                );

                if (!$matches) {
                    return [
                        'allowed' => false,
                        'reason' => 'This course is restricted to specific roles.',
                        'status' => null,
                    ];
                }
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
            'status' => $settings->enrollment_rule === 'approval' ? 'pending' : 'enrolled',
        ];
    }

    /**
     * POST /g2g-lms/learning-dashboard/enroll
     */
    public function enroll(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'course_id' => 'required|integer|exists:sub_std_map,id',
            'status' => 'required|in:completed,in-progress,enrolled,pending',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'sub_institute_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $status = $request->status;

        if ($status === 'enrolled') {
            $eligibility = $this->checkEnrolmentEligibility(
                $request->course_id,
                $request->user_id,
                $subInstituteId
            );

            if (!$eligibility['allowed']) {
                return response()->json([
                    'status' => 0,
                    'message' => $eligibility['reason'],
                    'eligibility' => $eligibility,
                ], 422);
            }

            $status = $eligibility['status'];
        }

        try {
            $existing = DB::table('lms_course_enroll')
                ->where('user_id', $request->user_id)
                ->where('course_id', $request->course_id)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => $existing->status === 'pending'
                        ? 'Enrolment already requested. An administrator will review it.'
                        : 'You are already enrolled in this course.',
                    'data' => $existing,
                    'requires_approval' => $existing->status === 'pending',
                ], 200);
            }

            $id = DB::table('lms_course_enroll')->insertGetId([
                'user_id' => $request->user_id,
                'course_id' => $request->course_id,
                'status' => $status,
                'start_date' => $request->start_date ?: now()->toDateString(),
                'end_date' => $request->end_date,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $this->contextUserId($request),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $enrollment = DB::table('lms_course_enroll')->find($id);

            return response()->json([
                'message' => $enrollment->status === 'pending'
                    ? 'Enrolment requested. An administrator will review it.'
                    : 'Course Enroll added successfully!',
                'data' => $enrollment,
                'requires_approval' => $enrollment->status === 'pending',
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------------ *
     * Dashboard widgets — ported from SkillDevelopmentController.
     * ------------------------------------------------------------------ */

    public function skillProgress(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $userId = $this->contextUserId($request);
            $subInstituteId = $this->lmsTenantId($request);

            if (!$userId) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            $userSkills = DB::table('s_skill_matrix as sm')
                ->join('s_users_skills as sus', 'sus.id', '=', 'sm.skill_id')
                ->leftJoin('sub_std_map as ssm', function ($join) {
                    $join->on('ssm.subject_id', '=', 'sus.id')
                         ->whereNotIn(DB::raw('LOWER(TRIM(ssm.subject_category))'), ['task', 'jobrole', 'course', 'sub'])
                         ->whereNull('ssm.deleted_at');
                })
                ->leftJoin('lms_course_enroll as lce', function ($join) use ($userId) {
                    $join->on('lce.course_id', '=', 'ssm.id')
                         ->where('lce.user_id', '=', DB::raw((int) $userId))
                         ->whereNull('lce.deleted_at');
                })
                ->where('sm.user_id', $userId)
                ->whereNull('sm.deleted_at')
                ->whereNull('sus.deleted_at')
                ->whereNotNull('sus.category')
                ->where('sus.category', '!=', '')
                ->where(function ($query) use ($subInstituteId) {
                    if ($subInstituteId) {
                        $query->where('sus.sub_institute_id', $subInstituteId);
                    }
                })
                ->select([
                    'sus.category as skill_category',
                    'sus.sub_category as sub_category',
                    DB::raw('COUNT(DISTINCT sus.id) as total_skills_in_category'),
                    DB::raw('AVG(sm.skill_level) as avg_skill_level'),
                    DB::raw('COUNT(CASE WHEN lce.status = "completed" THEN 1 END) as courses_completed'),
                    DB::raw('COUNT(lce.id) as total_enrolled_courses'),
                    DB::raw('MAX(sm.created_at) as created_at'),
                    DB::raw('MAX(sm.updated_at) as updated_at'),
                    DB::raw('MAX(sm.deleted_at) as deleted_at'),
                ])
                ->groupBy(['sus.category', 'sus.sub_category'])
                ->get();

            $skillProgress = [];
            $totalProgress = 0;
            $skillsInProgress = 0;

            foreach ($userSkills as $skill) {
                $progressPercentage = ($skill->avg_skill_level / 5) * 100;
                $proficiencyLevel = $this->getProficiencyLevel($skill->avg_skill_level);

                $coursesCompleted = $skill->courses_completed ?? 0;
                $totalCourses = max($skill->total_enrolled_courses ?? 1, 1);

                if ($totalCourses == 0) {
                    $totalCourses = $skill->total_skills_in_category;
                }

                $skillName = $this->mapCategoryName($skill->skill_category);

                $skillProgress[] = [
                    'skill_name' => $skillName,
                    'sub_category' => $skill->sub_category,
                    'progress_percentage' => round($progressPercentage),
                    'proficiency_level' => $proficiencyLevel,
                    'courses_completed' => $coursesCompleted,
                    'total_courses' => $totalCourses,
                    'status' => $progressPercentage < 100 ? 'in-progress' : 'completed',
                ];

                $totalProgress += $progressPercentage;
                if ($progressPercentage < 100) {
                    $skillsInProgress++;
                }
            }

            if (empty($skillProgress)) {
                return response()->json([
                    'status' => true,
                    'message' => 'No skill progress data found for this user',
                    'data' => [
                        'skill_progress' => [],
                        'overall' => [
                            'overall_progress_percentage' => 0,
                            'total_skills' => 0,
                            'skills_in_progress' => 0,
                            'average_progress' => 0,
                        ],
                    ],
                ], 200);
            }

            $totalSkills = count($skillProgress);
            $averageProgress = $totalProgress / $totalSkills;

            return response()->json([
                'status' => true,
                'message' => 'Skill development progress retrieved successfully',
                'data' => [
                    'skill_progress' => $skillProgress,
                    'overall' => [
                        'overall_progress_percentage' => round($averageProgress),
                        'total_skills' => $totalSkills,
                        'skills_in_progress' => $skillsInProgress,
                        'average_progress' => round($averageProgress),
                    ],
                    'timestamps' => [
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                        'deleted_at' => null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve skill progress',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getProficiencyLevel($skillLevel)
    {
        if ($skillLevel >= 4) {
            return 'Advanced';
        } elseif ($skillLevel >= 3) {
            return 'Intermediate';
        }
        return 'Beginner';
    }

    public function streak(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $userId = $this->contextUserId($request);
            $subInstituteId = $this->lmsTenantId($request);

            if (!$userId) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            $attendanceDays = DB::table('hrms_attendances')
                ->where('user_id', $userId)
                ->whereNotNull('punchin_time')
                ->whereNull('deleted_at')
                ->when($subInstituteId, function ($query) use ($subInstituteId) {
                    return $query->where('sub_institute_id', $subInstituteId);
                })
                ->orderBy('day', 'desc')
                ->pluck('day')
                ->toArray();

            $currentStreak = $this->calculateCurrentStreak($attendanceDays);
            $bestStreak = $this->calculateBestStreak($attendanceDays);

            $goal = 30;
            $progressPercentage = $goal > 0 ? round(($currentStreak / $goal) * 100) : 0;
            $daysToGo = max(0, $goal - $currentStreak);

            return response()->json([
                'status' => true,
                'message' => 'Learning streak data retrieved successfully',
                'data' => [
                    'current_streak' => $currentStreak,
                    'goal' => $goal,
                    'progress_percentage' => $progressPercentage,
                    'best_streak' => $bestStreak,
                    'days_to_go' => $daysToGo,
                    'timestamps' => [
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                        'deleted_at' => null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve learning streak data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function calculateCurrentStreak($attendanceDays)
    {
        if (empty($attendanceDays)) {
            return 0;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $hasToday = in_array($today, $attendanceDays);
        $hasYesterday = in_array($yesterday, $attendanceDays);

        if (!$hasToday && !$hasYesterday) {
            return 0;
        }

        $streak = 0;
        $currentDate = $hasToday ? $today : $yesterday;

        while (in_array($currentDate, $attendanceDays)) {
            $streak++;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
        }

        return $streak;
    }

    private function calculateBestStreak($attendanceDays)
    {
        if (empty($attendanceDays)) {
            return 0;
        }

        sort($attendanceDays);

        $maxStreak = 0;
        $currentStreak = 1;

        for ($i = 1; $i < count($attendanceDays); $i++) {
            $prevDate = date('Y-m-d', strtotime($attendanceDays[$i - 1] . ' +1 day'));
            if ($attendanceDays[$i] === $prevDate) {
                $currentStreak++;
            } else {
                $maxStreak = max($maxStreak, $currentStreak);
                $currentStreak = 1;
            }
        }

        return max($maxStreak, $currentStreak);
    }

    public function weeklyGoal(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $userId = $this->contextUserId($request);
            $subInstituteId = $this->lmsTenantId($request);

            if (!$userId) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $weeklyAttendance = DB::table('hrms_attendances')
                ->where('user_id', $userId)
                ->whereNotNull('punchin_time')
                ->whereNotNull('punchout_time')
                ->whereNull('deleted_at')
                ->whereBetween('day', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                ->when($subInstituteId, function ($query) use ($subInstituteId) {
                    return $query->where('sub_institute_id', $subInstituteId);
                })
                ->get();

            $totalHours = 0;
            foreach ($weeklyAttendance as $attendance) {
                $punchIn = \Carbon\Carbon::parse($attendance->punchin_time);
                $punchOut = \Carbon\Carbon::parse($attendance->punchout_time);
                $totalHours += $punchOut->diffInHours($punchIn);
            }

            $weeklyGoal = 12;
            $currentHours = min($totalHours, $weeklyGoal);
            $remainingHours = max(0, $weeklyGoal - $currentHours);
            $progressPercentage = $weeklyGoal > 0 ? round(($currentHours / $weeklyGoal) * 100) : 0;

            return response()->json([
                'status' => true,
                'message' => 'Weekly learning goal data retrieved successfully',
                'data' => [
                    'current_hours' => $currentHours,
                    'goal_hours' => $weeklyGoal,
                    'remaining_hours' => $remainingHours,
                    'progress_percentage' => $progressPercentage,
                    'week_start' => $startOfWeek->format('Y-m-d'),
                    'week_end' => $endOfWeek->format('Y-m-d'),
                    'timestamps' => [
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                        'deleted_at' => null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve weekly learning goal data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /g2g-lms/learning-dashboard/achievements
     *
     * DEVIATION: `lms_achievements` does not exist in this schema and is
     * explicitly out of this package's approved migration list. Rather than
     * query a missing table, this degrades to the same
     * `empty_is_expected` shape SkillDevelopmentController::getPeerComparison
     * already uses for "nothing to show yet" — a 200 with an explicit,
     * legible empty payload instead of a 500.
     */
    public function achievements(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $userId = $this->contextUserId($request);

            if (!$userId) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            if (! Schema::hasTable('lms_achievements')) {
                return response()->json([
                    'status' => true,
                    'message' => 'Achievements are not yet configured for this organisation.',
                    'data' => [
                        'achievements' => [],
                        'overall_progress' => 0,
                        'timestamps' => [
                            'created_at' => now()->toDateTimeString(),
                            'updated_at' => now()->toDateTimeString(),
                            'deleted_at' => null,
                        ],
                    ],
                    'empty_is_expected' => true,
                    'empty_reason' => 'The achievements catalogue has not been set up yet.',
                ], 200);
            }

            $subInstituteId = $this->lmsTenantId($request);
            $achievementDefinitions = DB::table('lms_achievements')->get();
            $achievements = [];

            foreach ($achievementDefinitions as $achievement) {
                $earned = false;
                $earnedDate = null;
                $progress = 'In progress';

                switch ($achievement->criteria_type) {
                    case 'courses_completed_month':
                        $currentMonth = now()->format('Y-m');
                        $coursesCompletedThisMonth = DB::table('lms_course_enroll')
                            ->where('user_id', $userId)
                            ->where('status', 'completed')
                            ->whereNull('deleted_at')
                            ->whereRaw("DATE_FORMAT(end_date, '%Y-%m') = ?", [$currentMonth])
                            ->when($subInstituteId, function ($query) use ($subInstituteId) {
                                return $query->where('sub_institute_id', $subInstituteId);
                            })
                            ->count();

                        $earned = $coursesCompletedThisMonth >= $achievement->criteria_value;
                        $progress = min($coursesCompletedThisMonth, $achievement->criteria_value) . '/' . $achievement->criteria_value . ' courses';
                        break;

                    case 'skill_level_advanced':
                        $advancedSkills = DB::table('s_skill_matrix')
                            ->where('user_id', $userId)
                            ->where('skill_level', '>=', 4)
                            ->whereNull('deleted_at')
                            ->count();

                        $earned = $advancedSkills >= $achievement->criteria_value;
                        $progress = $earned ? 'Achieved' : 'In progress';
                        break;

                    case 'streak':
                        $currentStreak = $this->calculateCurrentStreak(DB::table('hrms_attendances')
                            ->where('user_id', $userId)
                            ->whereNotNull('punchin_time')
                            ->whereNull('deleted_at')
                            ->orderBy('day', 'desc')
                            ->pluck('day')
                            ->toArray());

                        $earned = $currentStreak >= $achievement->criteria_value;
                        $progress = $currentStreak . '/' . $achievement->criteria_value . ' days';
                        break;
                }

                if ($earned) {
                    $earnedDate = now()->format('d/m/Y');
                }

                $achievements[] = [
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'earned' => $earned,
                    'earned_date' => $earnedDate,
                    'progress' => $progress,
                    'timestamps' => [
                        'created_at' => $achievement->created_at,
                        'updated_at' => $achievement->updated_at,
                        'deleted_at' => $achievement->deleted_at ?? null,
                    ],
                ];
            }

            $totalSkills = DB::table('s_skill_matrix')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->count();

            $avgSkillLevel = DB::table('s_skill_matrix')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->avg('skill_level') ?? 0;

            $overallProgress = $totalSkills > 0 ? round(($avgSkillLevel / 5) * 100) : 0;

            return response()->json([
                'status' => true,
                'message' => 'User achievements and progress retrieved successfully',
                'data' => [
                    'achievements' => $achievements,
                    'overall_progress' => $overallProgress,
                    'timestamps' => [
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                        'deleted_at' => null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user achievements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function peerComparison(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $userId = $this->contextUserId($request);
            $subInstituteId = $this->lmsTenantId($request);

            if (!$userId) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            $userProgressData = $this->calculateUserProgress($userId, $subInstituteId);
            $userProgress = $userProgressData['progress'];

            $peerProgresses = DB::table('tbluser')
                ->where('sub_institute_id', $subInstituteId)
                ->pluck('id')
                ->map(function ($peerUserId) use ($subInstituteId) {
                    $peerData = $this->calculateUserProgress($peerUserId, $subInstituteId);
                    return [
                        'user_id' => $peerUserId,
                        'progress' => $peerData['progress'],
                    ];
                })
                ->filter(fn ($peer) => $peer['progress'] > 0)
                ->sortByDesc('progress')
                ->values();

            if ($peerProgresses->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'peer_count' => 0,
                        'rank' => null,
                        'percentile' => null,
                        'user_progress' => $userProgressData['progress'] ?? null,
                    ],
                    'empty_is_expected' => true,
                    'empty_reason' => 'Nobody in your organisation has recorded skill progress yet, '
                        . 'so there is nothing to compare against. This fills once skill ratings exist.',
                    'message' => 'No peer data available',
                ], 200);
            }

            $rank = null;
            foreach ($peerProgresses as $index => $peer) {
                if ($peer['user_id'] == $userId) {
                    $rank = $index + 1;
                    break;
                }
            }

            if ($rank === null) {
                $peerProgresses->push(['user_id' => $userId, 'progress' => $userProgressData['progress']]);
                $peerProgresses = $peerProgresses->sortByDesc('progress')->values();
                $rank = $peerProgresses->search(fn ($peer) => $peer['user_id'] == $userId) + 1;
            }

            $totalPeers = $peerProgresses->count();
            $peerAverage = $peerProgresses->avg('progress');

            $betterThan = $peerProgresses->filter(fn ($peer) => $peer['progress'] < $userProgress)->count();
            $percentile = $totalPeers > 0 ? round(($betterThan / $totalPeers) * 100) : 0;
            $percentileAbove = $totalPeers > 0 ? round((($rank - 1) / $totalPeers) * 100) : 0;

            return response()->json([
                'status' => true,
                'message' => 'Peer comparison data retrieved successfully',
                'data' => [
                    'rank' => $rank,
                    'total_peers' => $totalPeers,
                    'your_progress' => round($userProgress),
                    'peer_average' => round($peerAverage),
                    'percentile' => $percentile,
                    'message' => "You're in the top " . $percentileAbove . "% of learners!",
                    'timestamps' => $userProgressData['timestamps'],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve peer comparison data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function calculateUserProgress($userId, $subInstituteId = null)
    {
        $userSkills = DB::table('s_skill_matrix as sm')
            ->join('s_users_skills as sus', 'sus.id', '=', 'sm.skill_id')
            ->where('sm.user_id', $userId)
            ->whereNull('sm.deleted_at')
            ->whereNull('sus.deleted_at')
            ->whereNotNull('sus.category')
            ->where('sus.category', '!=', '')
            ->when($subInstituteId, function ($query) use ($subInstituteId) {
                $query->where('sus.sub_institute_id', $subInstituteId);
            })
            ->select([
                DB::raw('AVG(sm.skill_level) as avg_skill_level'),
                DB::raw('COUNT(DISTINCT sus.id) as total_skills'),
                DB::raw('MAX(sm.created_at) as created_at'),
                DB::raw('MAX(sm.updated_at) as updated_at'),
                DB::raw('MAX(sm.deleted_at) as deleted_at'),
            ])
            ->first();

        if (!$userSkills || $userSkills->total_skills == 0) {
            return [
                'progress' => 0,
                'timestamps' => ['created_at' => null, 'updated_at' => null, 'deleted_at' => null],
            ];
        }

        return [
            'progress' => ($userSkills->avg_skill_level / 5) * 100,
            'timestamps' => [
                'created_at' => $userSkills->created_at,
                'updated_at' => $userSkills->updated_at,
                'deleted_at' => $userSkills->deleted_at,
            ],
        ];
    }

    /**
     * GET /g2g-lms/learning-dashboard/calendar
     *
     * `calendar_events` has no `deleted_at` in this schema, so the source's
     * `whereNull('deleted_at')` filter is dropped here (adaptation, not a
     * behaviour change: nothing in this table is ever soft-deleted).
     */
    public function calendar(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $subInstituteId = $this->lmsTenantId($request);
            $month = $request->input('month', now()->format('m'));
            $year = $request->input('year', now()->format('Y'));

            if (!$this->contextUserId($request)) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            $events = DB::table('calendar_events')
                ->whereYear('school_date', $year)
                ->whereMonth('school_date', $month)
                ->when($subInstituteId, function ($query) use ($subInstituteId) {
                    return $query->where('sub_institute_id', $subInstituteId);
                })
                ->orderBy('school_date', 'asc')
                ->get();

            $formattedEvents = [];
            foreach ($events as $event) {
                $formattedEvents[] = [
                    'title' => $event->title,
                    'description' => $event->description,
                    'current_datetime' => now()->format('M d \a\t g:i A'),
                    'school_date' => $event->school_date,
                    'priority' => $event->event_type ?? 'medium',
                    'event_type' => $event->event_type,
                    'standard' => $event->standard,
                    'timestamps' => [
                        'created_at' => $event->created_at,
                        'updated_at' => $event->updated_at,
                        'deleted_at' => null,
                    ],
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Learning calendar events retrieved successfully',
                'data' => [
                    'month' => $month,
                    'year' => $year,
                    'events' => $formattedEvents,
                    'timestamps' => [
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                        'deleted_at' => null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve learning calendar events',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /g2g-lms/learning-dashboard/recent-activity
     */
    public function recentActivity(Request $request)
    {
        try {
            if ($tokenError = $this->guardLmsToken($request)) {
                return $tokenError;
            }

            $userId = $this->contextUserId($request);
            $subInstituteId = $this->lmsTenantId($request);
            $limit = (int) $request->input('limit', 8);

            if (!$userId) {
                return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
            }

            $now = now();
            $activities = [];

            $enrollments = DB::table('lms_course_enroll as e')
                ->join('sub_std_map as s', 'e.course_id', '=', 's.id')
                ->leftJoin('subject as subj', 's.subject_id', '=', 'subj.id')
                ->where('e.user_id', $userId)
                ->whereNull('e.deleted_at')
                ->when($subInstituteId, function ($query) use ($subInstituteId) {
                    return $query->where('e.sub_institute_id', $subInstituteId);
                })
                ->select([
                    'e.id',
                    'e.status',
                    'e.end_date',
                    'e.created_at',
                    'e.updated_at',
                    DB::raw('COALESCE(subj.subject_name, s.display_name) as course_title'),
                ])
                ->orderByDesc('e.created_at')
                ->limit(50)
                ->get();

            foreach ($enrollments as $enrollment) {
                $title = $enrollment->course_title ?: 'a course';

                if ($enrollment->status === 'completed') {
                    $completedAt = $enrollment->updated_at ?? $enrollment->created_at;
                    $activities[] = [
                        'id' => 'enroll-completed-' . $enrollment->id,
                        'text' => 'You completed "' . $title . '"',
                        'type' => 'course_completed',
                        'tone' => 'success',
                        'timestamp' => $completedAt,
                        'sort' => $completedAt ? strtotime($completedAt) : 0,
                    ];
                } else {
                    $activities[] = [
                        'id' => 'enroll-started-' . $enrollment->id,
                        'text' => 'You enrolled in "' . $title . '"',
                        'type' => 'course_enrolled',
                        'tone' => 'primary',
                        'timestamp' => $enrollment->created_at,
                        'sort' => $enrollment->created_at ? strtotime($enrollment->created_at) : 0,
                    ];

                    if ($enrollment->end_date) {
                        $dueDate = \Carbon\Carbon::parse($enrollment->end_date);
                        $overdue = $dueDate->isPast();

                        if ($overdue || $dueDate->lte($now->copy()->addDays(14))) {
                            $activities[] = [
                                'id' => 'enroll-due-' . $enrollment->id,
                                'text' => $overdue
                                    ? '"' . $title . '" is overdue'
                                    : '"' . $title . '" is due ' . $dueDate->diffForHumans(),
                                'type' => 'deadline_due',
                                'tone' => 'warning',
                                'timestamp' => $dueDate->toDateTimeString(),
                                'sort' => $dueDate->getTimestamp(),
                            ];
                        }
                    }
                }
            }

            // calendar_events has no deleted_at column in this schema.
            $events = DB::table('calendar_events')
                ->whereBetween('school_date', [
                    $now->copy()->subDays(30)->format('Y-m-d'),
                    $now->copy()->addDays(30)->format('Y-m-d'),
                ])
                ->when($subInstituteId, function ($query) use ($subInstituteId) {
                    return $query->where('sub_institute_id', $subInstituteId);
                })
                ->orderByDesc('school_date')
                ->limit(20)
                ->get();

            foreach ($events as $event) {
                $eventDate = \Carbon\Carbon::parse($event->school_date);
                $activities[] = [
                    'id' => 'event-' . $event->id,
                    'text' => $eventDate->isPast()
                        ? '"' . $event->title . '" took place'
                        : '"' . $event->title . '" is scheduled ' . $eventDate->diffForHumans(),
                    'type' => 'session_upcoming',
                    'tone' => 'neutral',
                    'timestamp' => $eventDate->toDateTimeString(),
                    'sort' => $eventDate->getTimestamp(),
                ];
            }

            usort($activities, fn ($a, $b) => $b['sort'] <=> $a['sort']);

            $deadlineCount = 0;
            $activities = array_values(array_filter($activities, function ($activity) use (&$deadlineCount) {
                if ($activity['type'] !== 'deadline_due') {
                    return true;
                }
                return ++$deadlineCount <= 3;
            }));

            $activities = array_map(function ($activity) {
                return [
                    'id' => $activity['id'],
                    'text' => $activity['text'],
                    'time' => $activity['timestamp']
                        ? \Carbon\Carbon::parse($activity['timestamp'])->diffForHumans()
                        : '',
                    'type' => $activity['type'],
                    'tone' => $activity['tone'],
                    'timestamp' => $activity['timestamp'],
                ];
            }, array_slice($activities, 0, max($limit, 1)));

            return response()->json(['status' => true, 'data' => $activities], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve recent activity',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function mapCategoryName($category)
    {
        $categoryMap = [
            'Frontend' => 'React Development',
            'React' => 'React Development',
            'JavaScript' => 'React Development',
            'Analytics' => 'Data Analysis',
            'Data' => 'Data Analysis',
            'Analysis' => 'Data Analysis',
            'Leadership' => 'Project Management',
            'Management' => 'Project Management',
            'Project' => 'Project Management',
            'AI/ML' => 'Machine Learning',
            'AI' => 'Machine Learning',
            'Machine Learning' => 'Machine Learning',
            'ML' => 'Machine Learning',
        ];

        return $categoryMap[$category] ?? $category;
    }
}
