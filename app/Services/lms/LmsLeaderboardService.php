<?php

namespace App\Services\lms;

use Illuminate\Support\Facades\DB;

/**
 * LMS Engagement -> Leader Board business logic.
 *
 * Re-implementation (NOT a wrapper) of the legacy web module
 * `lms\lmsLeaderboardController` for the K12 REST API. The legacy controller
 * stays untouched; the rules below are the ones it encodes, restated against
 * the same tables:
 *
 *   lb_master  - admin config: how many points a module is worth, per
 *                (grade, standard) and institute, plus its display icon.
 *   lb_points  - the earned-points ledger: one row per (user, module, date).
 *
 * Rules preserved from the legacy module
 *   - a learner's points are scoped by institute + user + user_profile + syear;
 *   - the module breakdown is keyed by `module_name`, carrying `lb_master.icon`
 *     and the per-day series, and the learner's total is the sum of the ledger;
 *   - the class ranking is SUM(points) grouped by user across every learner
 *     enrolled in the SAME standard for that year, ordered DESC;
 *   - "class toppers" is that ranking capped at 5 rows by default.
 *
 * Deliberate corrections (each an outright defect in the legacy version, and
 * each documented in LMS_LEADERBOARD_SOCIAL_COLLABORATIVE_MIGRATION.md):
 *   1. rank was `array_search($user, $top5) + 1`, so every learner outside the
 *      top five was reported as rank #1. Rank is now computed over the whole
 *      class, with ties sharing a rank.
 *   2. the class query filtered the ENROLLMENT year but not the POINTS year,
 *      so it summed every year's points while "my points" summed one - the two
 *      figures could never agree. Both are now pinned to the same syear.
 *   3. the enrollment join carried no year predicate, so a learner enrolled in
 *      several years multiplied their own ledger rows (inflated totals).
 *   4. `lb_master` was INNER JOINed, silently dropping - from the breakdown AND
 *      from the total - any points whose module has no master row, even though
 *      the class ranking counted those same points. It is now a LEFT JOIN: a
 *      missing master row is a configuration gap, not a reason to void points
 *      the learner has already earned.
 */
class LmsLeaderboardService
{
    /** Legacy UI showed a fixed medal; no tier logic exists anywhere in the ERP. */
    public const DEFAULT_MEDAL = 'Bronze';

    /** Modules the leader board master supports (mirrors the legacy add form). */
    public const MODULES = ['login', 'exampass', 'examfail', 'homework'];

    /**
     * A learner's leader-board summary: totals, module breakdown, class rank
     * and the class toppers table.
     *
     * @param  array  $ctx      ['sub_institute_id','user_id','user_profile_id','syear']
     * @param  int    $topLimit how many toppers to return (legacy: 5)
     */
    public function summary(array $ctx, int $topLimit = 5): array
    {
        $subInstituteId = (int) $ctx['sub_institute_id'];
        $userId         = (int) $ctx['user_id'];
        $syear          = (int) $ctx['syear'];
        $userProfileId  = $ctx['user_profile_id'] ?? null;

        $enrollment = $this->enrollment($subInstituteId, $userId, $syear);

        $ledger = $this->ledger($subInstituteId, $userId, $syear, $userProfileId, $enrollment['standard_id'] ?? null);

        $modules     = [];
        $totalPoints = 0;
        foreach ($ledger as $row) {
            $points      = (int) $row->points;
            $totalPoints += $points;

            $module = (string) $row->module_name;
            if (! isset($modules[$module])) {
                $modules[$module] = [
                    'module_name' => $module,
                    'label'       => $this->moduleLabel($module),
                    'icon'        => $row->icon,
                    'description' => $row->description,
                    'points'      => 0,
                    'entries'     => [],
                ];
            }
            $modules[$module]['points'] += $points;
            // Legacy keyed the series by date, so same-day rows collapse.
            $date = (string) $row->inserted_date;
            $modules[$module]['entries'][$date] = ($modules[$module]['entries'][$date] ?? 0) + $points;
        }

        $modules = array_values(array_map(static function (array $module) {
            $module['entries'] = array_map(
                static fn ($date, $points) => ['date' => $date, 'points' => $points],
                array_keys($module['entries']),
                $module['entries']
            );

            return $module;
        }, $modules));

        usort($modules, static fn ($a, $b) => $b['points'] <=> $a['points']);

        $ranking = $this->ranking($subInstituteId, $syear, $enrollment['standard_id'] ?? null);

        return [
            'has_data'     => $ledger !== [] || $ranking !== [],
            'syear'        => $syear,
            'learner'      => [
                'user_id'       => $userId,
                'standard_id'   => $enrollment['standard_id'] ?? null,
                'standard_name' => $enrollment['standard_name'] ?? null,
                'section_id'    => $enrollment['section_id'] ?? null,
                'section_name'  => $enrollment['section_name'] ?? null,
                'total_points'  => $totalPoints,
                'rank'          => $this->rankOf($ranking, $userId),
                'class_size'    => count($ranking),
                'medal'         => self::DEFAULT_MEDAL,
            ],
            'modules'      => $modules,
            'toppers'      => $this->decorate(array_slice($ranking, 0, max(1, $topLimit)), $userId),
        ];
    }

    /**
     * The full class ranking, paginated - the "everyone" view the legacy Blade
     * only ever showed five rows of.
     *
     * @param  array  $filters ['standard_id','section_id','module_name','from','to']
     */
    public function rankings(array $ctx, array $filters, int $page, int $perPage): array
    {
        $subInstituteId = (int) $ctx['sub_institute_id'];
        $syear          = (int) $ctx['syear'];
        $userId         = (int) $ctx['user_id'];

        $standardId = $filters['standard_id'] ?? null;
        if (! $standardId && ($ctx['is_student'] ?? false)) {
            // A student always sees their own class, exactly like the legacy page.
            $standardId = $this->enrollment($subInstituteId, $userId, $syear)['standard_id'] ?? null;
        }

        // Staff who have not picked a class get the institute-wide board rather
        // than an empty screen; a student is always pinned to their own class.
        $rows  = $this->ranking($subInstituteId, $syear, $standardId, $filters, false);
        $total = count($rows);

        $page    = max(1, $page);
        $perPage = $perPage > 0 ? $perPage : max(1, $total);
        $slice   = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return [
            'items' => $this->decorate($slice, $userId, ($page - 1) * $perPage),
            'meta'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
                'standard_id'  => $standardId !== null ? (int) $standardId : null,
                'syear'        => $syear,
            ],
        ];
    }

    /**
     * Filter options that are actually backed by data: the years the institute
     * has ledger rows for, the standards it has configured, and the modules.
     */
    public function filterOptions(array $ctx): array
    {
        $subInstituteId = (int) $ctx['sub_institute_id'];

        $syears = DB::table('lb_points')
            ->where('sub_institute_id', $subInstituteId)
            ->distinct()
            ->orderByDesc('syear')
            ->pluck('syear')
            ->map(static fn ($year) => (int) $year)
            ->all();

        $standards = DB::table('lb_master as m')
            ->join('standard as s', 's.id', '=', 'm.standard_id')
            ->where('m.sub_institute_id', $subInstituteId)
            ->distinct()
            ->orderBy('s.name')
            ->get(['s.id', 's.name'])
            ->map(static fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();

        $modules = DB::table('lb_master')
            ->where('sub_institute_id', $subInstituteId)
            ->distinct()
            ->orderBy('module_name')
            ->pluck('module_name')
            ->filter()
            ->values()
            ->map(fn ($module) => ['value' => (string) $module, 'label' => $this->moduleLabel((string) $module)])
            ->all();

        return [
            'syears'    => $syears,
            'standards' => $standards,
            'modules'   => $modules ?: array_map(
                fn ($module) => ['value' => $module, 'label' => $this->moduleLabel($module)],
                self::MODULES
            ),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Leader Board Master (lb_master) - the admin points configuration     */
    /* ------------------------------------------------------------------ */

    /**
     * Configuration rows for the institute, joined to their grade and class
     * names, with search / filter / pagination the legacy screen never had.
     *
     * The legacy list (lbMasterController@getData) INNER JOINed
     * `academic_section` and `standard`, so a row whose grade or class had
     * since been removed vanished from the admin screen while still counting
     * in the leader board. Both joins are LEFT here, so configuration is never
     * silently invisible.
     *
     * @param  array  $filters ['search','module_name','standard_id','status']
     */
    public function masterList(array $ctx, array $filters, int $page, int $perPage): array
    {
        $query = DB::table('lb_master as m')
            ->leftJoin('academic_section as a', 'a.id', '=', 'm.grade_id')
            ->leftJoin('standard as s', 's.id', '=', 'm.standard_id')
            ->where('m.sub_institute_id', (int) $ctx['sub_institute_id']);

        if (! empty($filters['module_name'])) {
            $query->where('m.module_name', $filters['module_name']);
        }
        if (! empty($filters['standard_id'])) {
            $query->where('m.standard_id', $filters['standard_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('m.status', (int) $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('m.description', 'like', $search)
                    ->orWhere('m.module_name', 'like', $search)
                    ->orWhere('s.name', 'like', $search)
                    ->orWhere('a.title', 'like', $search);
            });
        }

        $total   = (clone $query)->count('m.id');
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $rows = $query
            ->orderBy('s.sort_order')
            ->orderBy('m.module_name')
            ->forPage($page, $perPage)
            ->get([
                'm.id',
                'm.grade_id',
                'm.standard_id',
                'm.module_name',
                'm.per_value',
                'm.points',
                'm.icon',
                'm.description',
                'm.status',
                'm.created_on',
                'a.title as grade_name',
                's.name as standard_name',
            ]);

        return [
            'items' => $rows->map(fn ($row) => $this->presentMaster($row))->all(),
            'meta'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
            ],
        ];
    }

    /** One configuration row, or null when it is not the caller's. */
    public function masterFind(array $ctx, int $id): ?array
    {
        $row = DB::table('lb_master as m')
            ->leftJoin('academic_section as a', 'a.id', '=', 'm.grade_id')
            ->leftJoin('standard as s', 's.id', '=', 'm.standard_id')
            ->where('m.sub_institute_id', (int) $ctx['sub_institute_id'])
            ->where('m.id', $id)
            ->first([
                'm.id',
                'm.grade_id',
                'm.standard_id',
                'm.module_name',
                'm.per_value',
                'm.points',
                'm.icon',
                'm.description',
                'm.status',
                'm.created_on',
                'a.title as grade_name',
                's.name as standard_name',
            ]);

        return $row ? $this->presentMaster($row) : null;
    }

    /**
     * Whether another row already configures this (grade, class, module) for
     * the institute - the legacy uniqueness rule, with self excluded on update.
     */
    public function masterExists(array $ctx, array $input, ?int $ignoreId = null): bool
    {
        return DB::table('lb_master')
            ->where('sub_institute_id', (int) $ctx['sub_institute_id'])
            ->where('grade_id', $input['grade_id'])
            ->where('standard_id', $input['standard_id'])
            ->where('module_name', $input['module_name'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    /** Create a configuration row. Institute comes from the caller's token. */
    public function masterCreate(array $ctx, array $input): array
    {
        $id = DB::table('lb_master')->insertGetId($this->masterPayload($input) + [
            'sub_institute_id' => (int) $ctx['sub_institute_id'],
            'created_on'       => now(),
        ]);

        return $this->masterFind($ctx, (int) $id) ?? ['id' => (int) $id];
    }

    /** Update a configuration row that belongs to the caller's institute. */
    public function masterUpdate(array $ctx, int $id, array $input): ?array
    {
        $affected = DB::table('lb_master')
            ->where('sub_institute_id', (int) $ctx['sub_institute_id'])
            ->where('id', $id)
            ->update($this->masterPayload($input));

        return $affected >= 0 ? $this->masterFind($ctx, $id) : null;
    }

    /** Delete a configuration row that belongs to the caller's institute. */
    public function masterDelete(array $ctx, int $id): bool
    {
        return DB::table('lb_master')
            ->where('sub_institute_id', (int) $ctx['sub_institute_id'])
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Columns written by both create and update.
     *
     * `per_value` is the pass/fail percentage threshold and is only meaningful
     * for the exam modules; anything else is stored as 0, the same normalisation
     * the legacy form performed.
     */
    private function masterPayload(array $input): array
    {
        $module = (string) $input['module_name'];

        return [
            'grade_id'    => (int) $input['grade_id'],
            'standard_id' => (int) $input['standard_id'],
            'module_name' => $module,
            'per_value'   => in_array($module, ['exampass', 'examfail'], true)
                ? (float) ($input['per_value'] ?? 0)
                : 0,
            'points'      => (int) $input['points'],
            'icon'        => (string) ($input['icon'] ?? ''),
            'description' => (string) ($input['description'] ?? ''),
            'status'      => ! empty($input['status']) ? 1 : 0,
        ];
    }

    private function presentMaster(object $row): array
    {
        return [
            'id'            => (int) $row->id,
            'grade_id'      => $row->grade_id !== null ? (int) $row->grade_id : null,
            'grade_name'    => (string) ($row->grade_name ?? ''),
            'standard_id'   => $row->standard_id !== null ? (int) $row->standard_id : null,
            'standard_name' => (string) ($row->standard_name ?? ''),
            'module_name'   => (string) $row->module_name,
            'module_label'  => $this->moduleLabel((string) $row->module_name),
            'per_value'     => (float) ($row->per_value ?? 0),
            'points'        => (int) $row->points,
            'icon'          => (string) ($row->icon ?? ''),
            'description'   => (string) ($row->description ?? ''),
            'status'        => (int) ($row->status ?? 0),
            'created_on'    => (string) ($row->created_on ?? ''),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** The learner's enrollment (standard/section) for the given year. */
    public function enrollment(int $subInstituteId, int $userId, int $syear): array
    {
        $row = DB::table('tblstudent_enrollment as se')
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->where('se.student_id', $userId)
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->orderByDesc('se.id')
            ->first([
                'se.standard_id',
                'se.section_id',
                'st.name as standard_name',
                'd.name as section_name',
            ]);

        return $row ? (array) $row : [];
    }

    /**
     * The learner's own ledger rows, decorated with their master icon.
     *
     * LEFT JOIN on lb_master (see class docblock, correction 4) and matched on
     * institute as well as module + standard, which the legacy join omitted.
     */
    private function ledger(int $subInstituteId, int $userId, int $syear, $userProfileId, $standardId): array
    {
        $query = DB::table('lb_points as l')
            ->leftJoin('lb_master as m', function ($join) use ($standardId) {
                $join->on('m.module_name', '=', 'l.module_name')
                    ->on('m.sub_institute_id', '=', 'l.sub_institute_id');
                if ($standardId) {
                    $join->where('m.standard_id', '=', $standardId);
                }
            })
            ->where('l.sub_institute_id', $subInstituteId)
            ->where('l.user_id', $userId)
            ->where('l.syear', $syear);

        if ($userProfileId !== null && $userProfileId !== '') {
            $query->where('l.user_profile_id', $userProfileId);
        }

        return $query
            ->orderBy('l.inserted_date')
            ->get([
                'l.id',
                'l.module_name',
                'l.points',
                'l.inserted_date',
                'm.icon',
                'm.description',
            ])
            ->all();
    }

    /**
     * SUM(points) per learner across a class for one year, ordered DESC.
     *
     * The enrollment join is pinned to the same syear as the points (legacy bug
     * 2 + 3) and enrollment is de-duplicated, so a learner counts exactly once.
     *
     * When $requireStandard is true (the learner summary) a missing standard
     * means "no class to rank against" and yields an empty board. When it is
     * false (the staff-facing ranking table) a missing standard widens the
     * board to every learner enrolled in the institute for that year.
     *
     * @return array<int,object> {user_id, student_name, total_points}
     */
    private function ranking(
        int $subInstituteId,
        int $syear,
        $standardId,
        array $filters = [],
        bool $requireStandard = true
    ): array {
        if (! $standardId && $requireStandard) {
            return [];
        }

        $enrolled = DB::table('tblstudent_enrollment')
            ->select('student_id')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->when($standardId, fn ($q) => $q->where('standard_id', $standardId))
            ->when(! empty($filters['section_id']), fn ($q) => $q->where('section_id', $filters['section_id']))
            ->distinct();

        $query = DB::table('lb_points as l')
            ->join('tblstudent as s', function ($join) {
                $join->on('s.id', '=', 'l.user_id')
                    ->on('s.sub_institute_id', '=', 'l.sub_institute_id');
            })
            ->joinSub($enrolled, 'e', fn ($join) => $join->on('e.student_id', '=', 'l.user_id'))
            ->where('l.sub_institute_id', $subInstituteId)
            ->where('l.syear', $syear)
            ->when(! empty($filters['module_name']), fn ($q) => $q->where('l.module_name', $filters['module_name']))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('l.inserted_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('l.inserted_date', '<=', $filters['to']))
            ->groupBy('l.user_id', 's.first_name', 's.middle_name', 's.last_name', 's.image')
            ->orderByDesc('total_points')
            ->orderBy('student_name');

        return $query->get([
            DB::raw('SUM(l.points) as total_points'),
            DB::raw('l.user_id as user_id'),
            DB::raw("TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)) as student_name"),
            DB::raw('s.image as image'),
        ])->all();
    }

    /** Competition ranking (ties share a rank) for one learner. */
    private function rankOf(array $ranking, int $userId): ?int
    {
        $mine = null;
        foreach ($ranking as $row) {
            if ((int) $row->user_id === $userId) {
                $mine = (int) $row->total_points;
                break;
            }
        }
        if ($mine === null) {
            return null;
        }

        $ahead = 0;
        foreach ($ranking as $row) {
            if ((int) $row->total_points > $mine) {
                $ahead++;
            }
        }

        return $ahead + 1;
    }

    /** Shape ranking rows for the API, adding rank numbers and avatars. */
    private function decorate(array $rows, int $currentUserId, int $offset = 0): array
    {
        return array_values(array_map(static function ($row, $index) use ($currentUserId, $offset) {
            $image = trim((string) ($row->image ?? ''));

            return [
                'rank'            => $offset + $index + 1,
                'user_id'         => (int) $row->user_id,
                'student_name'    => (string) $row->student_name,
                'total_points'    => (int) $row->total_points,
                'avatar_url'      => url('/storage/student/' . ($image !== '' ? $image : 'no-image.jpg')),
                'is_current_user' => (int) $row->user_id === $currentUserId,
            ];
        }, $rows, array_keys($rows)));
    }

    private function moduleLabel(string $module): string
    {
        return [
            'login'    => 'Login',
            'exampass' => 'Exam passed',
            'examfail' => 'Exam failed',
            'homework' => 'Homework',
        ][$module] ?? ucfirst($module);
    }
}
