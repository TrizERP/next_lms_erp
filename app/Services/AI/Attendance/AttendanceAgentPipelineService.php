<?php

namespace App\Services\AI\Attendance;

use App\Domain\KnowledgeGraph\People\PeopleGraphService;
use App\Mcp\ToolRegistry;
use App\Services\AI\Pipeline\ModuleAgentPipeline;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attendance — sequential 4-agent pipeline.
 *
 *   Agent 1  Attendance Register Reader        attendance_student (SQL) + attendance.daily cross-check
 *   Agent 2  Knowledge Graph Roster Validator  Neo4j Student / Division / Staff vs tblstudent_enrollment, class_teacher
 *   Agent 3  Absence Pattern Analyst           attendance_student again, recomputed per student
 *   Agent 4  Class-Teacher Follow-up Drafter   PREVIEW ONLY — nothing is sent or written
 *
 * Reuses the existing `k12_attendance` agent only for attribution of the step rows it writes to
 * `ai_agent_runs`; no agent, table or migration is added. The rate rule is the estate's own
 * (AttendanceInsightService): `P` present, `A` absent, anything else neither, rate =
 * present / (present + absent), and a student needs at least MIN_COUNTED coded days.
 */
class AttendanceAgentPipelineService extends ModuleAgentPipeline
{
    public const MIN_COUNTED = 5;
    public const DEFAULT_DAYS = 30;
    public const DEFAULT_THRESHOLD = 75;
    public const DEFAULT_LIMIT = 100;
    public const MAX_LIMIT = 200;

    public function __construct(ToolRegistry $registry, private readonly PeopleGraphService $graph)
    {
        parent::__construct($registry);
    }

    public function module(): string
    {
        return 'attendance';
    }

    protected function persistAgentKey(): ?string
    {
        return 'k12_attendance';
    }

    public function auditModule(): string
    {
        return 'attendance';
    }

    // No persistFinal(): the class-teacher drafts and their student rows are stored in this run's
    // `pipeline_final` audit record by the base class, and the Admin Dashboard reads them from there.
    // No recommendation, workflow run or approval is opened — nothing is queued for anyone, and nothing
    // is sent. A person acts on a draft outside this pipeline.

    protected function optionKeys(): array
    {
        return ['days', 'threshold', 'limit', 'persist'];
    }

    // ───────────────────────── descriptors (ESO) ─────────────────────────

    public function agents(): array
    {
        return [
            1 => [
                'order' => 1,
                'name' => 'Attendance Register Reader',
                'agent_id' => 'agt_attendance_register_reader',
                'gate_name' => 'Register integrity',
                'purpose' => 'Reads the attendance register for the chosen window and finds the students whose attendance rate is below the threshold.',
                'tasks' => [
                    'Find the latest day with attendance recorded and open a window ending there.',
                    'Count records, students, school days, and present / absent / other codes.',
                    'Rate every student (present ÷ (present + absent)) and list those below the threshold.',
                    'Cross-check the latest day against the existing attendance.daily tool.',
                ],
                'tables' => ['attendance_student', 'tblstudent', 'standard', 'division'],
                'graph' => [],
                'tools' => ['attendance.daily'],
                'receives' => 'The institute, academic year, window length and threshold from the authenticated request.',
                'produces' => 'The at-risk student list with rate, present / absent / other days and class.',
                'stored' => 'Run state on the server for this run only; one ai_agent_runs row for the step.',
                'passes_to' => 'Agent 2 receives the at-risk student ids with their class and division.',
                'validation' => ['Tenant is the signed-in institute', 'Academic year resolved from real records', 'Register has records', 'P + A + other equals records', 'Latest day matches attendance.daily'],
                'safeguards' => ['Read-only', 'Students with fewer than 5 coded days are excluded, not shown as 0%'],
            ],
            2 => [
                'order' => 2,
                'name' => 'Knowledge Graph Roster Validator',
                'agent_id' => 'agt_attendance_roster_validator',
                'gate_name' => 'Roster & graph agreement',
                'purpose' => 'Confirms each at-risk student\'s class and class teacher in the database AND in the Neo4j Knowledge Graph, and adds the graph\'s prior-term attendance.',
                'tasks' => [
                    'Check each student\'s enrolment for the year in tblstudent_enrollment.',
                    'Look the same students up in the graph (Student, Division) and compare class and division.',
                    'Resolve the class teacher from class_teacher and confirm the graph links that teacher to the division.',
                    'Read the graph\'s per-term attendance summary for each student.',
                ],
                'tables' => ['tblstudent_enrollment', 'class_teacher', 'tbluser', 'standard', 'division'],
                'graph' => ['(Student)-[:IN_DIVISION]->(Division)', '(Staff)-[:CLASS_TEACHER_OF]->(Division)', '(StuDetail)-[:ATTENDANCE]->(AcademicYear)'],
                'tools' => [],
                'receives' => 'Agent 1\'s at-risk students.',
                'produces' => 'Each student with a validated class, class teacher, graph agreement flags and prior-term attendance.',
                'stored' => 'Run state on the server; one ai_agent_runs row.',
                'passes_to' => 'Agent 3 receives the validated students and their class teachers.',
                'validation' => ['Graph reachable', 'Graph rows belong to this institute', 'Students enrolled in the database', 'Graph covers at least half the students', 'Graph and database agree on class'],
                'safeguards' => ['Graph queries are read-only and bound to the institute on every node', 'Disagreements are reported, never overwritten'],
            ],
            3 => [
                'order' => 3,
                'name' => 'Absence Pattern Analyst',
                'agent_id' => 'agt_attendance_pattern_analyst',
                'gate_name' => 'Pattern evidence',
                'purpose' => 'Reads each student\'s daily marks and works out streaks, recent absences, trend and severity — then rolls the cases up by class.',
                'tasks' => [
                    'Read every daily mark of the validated students in the window.',
                    'Recompute each rate from the daily marks and compare with Agent 1.',
                    'Measure the current absence streak, absences in the last 7 school days and change from the previous window.',
                    'Grade severity and group the cases by class and class teacher.',
                ],
                'tables' => ['attendance_student'],
                'graph' => [],
                'tools' => [],
                'receives' => 'Agent 2\'s validated students.',
                'produces' => 'Ranked cases with severity and evidence, and a class roll-up.',
                'stored' => 'Run state on the server; one ai_agent_runs row.',
                'passes_to' => 'Agent 4 receives the ranked cases grouped by class teacher.',
                'validation' => ['Every rate recomputed matches Agent 1', 'Every candidate became a case', 'Every case has enough counted days', 'Streaks are possible'],
                'safeguards' => ['Read-only', 'Severity follows fixed bands, not a model guess'],
            ],
            4 => [
                'order' => 4,
                'name' => 'Class-Teacher Follow-up Drafter',
                'agent_id' => 'agt_attendance_followup_drafter',
                'gate_name' => 'Draft safety',
                'purpose' => 'Drafts one follow-up note per class teacher listing their at-risk students. It only drafts — nothing is sent and no record is changed.',
                'tasks' => [
                    'Group the cases by class teacher.',
                    'Check each teacher is a real, active account in this institute.',
                    'Write the follow-up note and count the parent contacts that exist (numbers are not shown).',
                    'List classes that have no class teacher so someone can assign one.',
                ],
                'tables' => ['tbluser', 'class_teacher', 'tblstudent'],
                'graph' => [],
                'tools' => [],
                'receives' => 'Agent 3\'s ranked cases.',
                'produces' => 'Follow-up drafts per class teacher and a list of classes with no class teacher.',
                'stored' => 'Nothing is stored except the step row; drafts live in this run only.',
                'passes_to' => 'End of pipeline. A person approves and sends through the Attendance assistant.',
                'validation' => ['Nothing was sent', 'No attendance or user record was changed', 'Every draft addresses an active teacher of this institute', 'Every case is covered by a draft or listed as unassigned'],
                'safeguards' => ['Preview only', 'Sending needs an explicit human approval outside this pipeline'],
            ],
        ];
    }

    // ───────────────────────── scope & live state ─────────────────────────

    protected function resolveScope(int $inst, McpRequestContext $base, array $options): array
    {
        $has = static fn (int $y) => DB::table('attendance_student')->where('sub_institute_id', $inst)->where('syear', $y)->exists();

        if (! empty($options['academic_year']) && $has((int) $options['academic_year'])) {
            return ['year' => (int) $options['academic_year'], 'source' => 'request'];
        }
        if ($base->academicYear && $has($base->academicYear)) {
            return ['year' => $base->academicYear, 'source' => 'session'];
        }

        $latest = DB::table('attendance_student')->where('sub_institute_id', $inst)->max('syear');
        if ($latest) {
            return ['year' => (int) $latest, 'source' => 'latest_recorded_attendance'];
        }

        return ['year' => null, 'source' => 'unresolved', 'error' => 'this institute has no attendance records at all.'];
    }

    public function liveState(int $inst, McpRequestContext $base, array $options): array
    {
        $scope = $this->resolveScope($inst, $base, $options);
        $live = [];

        if ($scope['year']) {
            $row = DB::table('attendance_student')->where('sub_institute_id', $inst)->where('syear', $scope['year'])
                ->selectRaw('COUNT(*) AS records, COUNT(DISTINCT student_id) AS students, MIN(attendance_date) AS first_day, MAX(attendance_date) AS last_day')
                ->first();
            $live = [
                'records' => (int) $row->records, 'students' => (int) $row->students,
                'first_day' => $row->first_day, 'latest_day' => $row->last_day,
            ];
        }

        return [
            'sub_institute_id' => $inst,
            'academic_year' => $scope['year'],
            'academic_year_source' => $scope['source'],
            'live' => $live,
            'knowledge_graph' => $this->graph->ping(),
            'defaults' => ['days' => self::DEFAULT_DAYS, 'threshold' => self::DEFAULT_THRESHOLD, 'limit' => self::DEFAULT_LIMIT],
            'recent_runs' => $this->recentRuns($inst),
        ];
    }

    // ───────────────────────── agents ─────────────────────────

    protected function execute(int $n, array $state, McpRequestContext $ctx): array
    {
        return match ($n) {
            1 => $this->agent1($state, $ctx),
            2 => $this->agent2($state, $ctx),
            3 => $this->agent3($state, $ctx),
            default => $this->agent4($state, $ctx),
        };
    }

    private function params(array $state): array
    {
        $o = $state['options'];

        return [
            min(max((int) ($o['days'] ?? self::DEFAULT_DAYS), 7), 365),
            min(max((int) ($o['threshold'] ?? self::DEFAULT_THRESHOLD), 1), 99),
            min(max((int) ($o['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT),
        ];
    }

    private const CODE_SQL = "SUM(CASE WHEN UPPER(a.attendance_code) = 'P' THEN 1 ELSE 0 END) AS p,
                SUM(CASE WHEN UPPER(a.attendance_code) = 'A' THEN 1 ELSE 0 END) AS a,
                SUM(CASE WHEN UPPER(a.attendance_code) NOT IN ('P','A') THEN 1 ELSE 0 END) AS o,
                COUNT(*) AS n";

    /** Agent 1 — read the register. */
    private function agent1(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $year = $state['year'];
        [$days, $threshold, $limit] = $this->params($state);

        $latest = $this->sql('Latest recorded day', ['attendance_student'], fn () => DB::table('attendance_student')
            ->where('sub_institute_id', $inst)->where('syear', $year)->where('attendance_date', '<=', now()->toDateString())
            ->max('attendance_date'));

        if (! $latest) {
            return [
                'tool' => 'attendance_student', 'input' => compact('days', 'threshold'),
                'summary' => ['records' => 0],
                'gate_checks' => [
                    'tenant_isolation' => [$inst > 0, 'Institute ' . $inst . ' comes from the signed-in session.'],
                    'academic_year_resolved' => [true, "Academic year {$year} ({$state['year_source']})."],
                    'register_has_records' => [false, 'No attendance is recorded on or before today for this year.'],
                ],
            ];
        }

        $end = Carbon::parse($latest);
        $start = $end->copy()->subDays($days - 1);

        // Counted on the same basis as the existing tools: a record counts only when its student
        // still exists in tblstudent. Records that point at a missing student are reported, not hidden.
        $windowQuery = fn () => DB::table('attendance_student as a')
            ->join('tblstudent as s0', 's0.id', '=', 'a.student_id')
            ->where('a.sub_institute_id', $inst)->where('a.syear', $year)
            ->whereBetween('a.attendance_date', [$start->toDateString(), $end->toDateString()]);
        $allInWindow = $this->sql('Window records incl. orphans', ['attendance_student'], fn () => DB::table('attendance_student')
            ->where('sub_institute_id', $inst)->where('syear', $year)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])->count());

        $tot = $this->sql('Window totals', ['attendance_student'], fn () => $windowQuery()
            ->selectRaw(self::CODE_SQL . ', COUNT(DISTINCT a.student_id) AS students, COUNT(DISTINCT a.attendance_date) AS days_marked, COUNT(DISTINCT a.standard_id, a.section_id) AS classes')
            ->first());

        $yearRecords = $this->sql('Whole-year record count', ['attendance_student'], fn () => DB::table('attendance_student')
            ->where('sub_institute_id', $inst)->where('syear', $year)->count());

        $rows = $this->sql('Per-student window rates', ['attendance_student', 'tblstudent', 'standard', 'division'], fn () => $windowQuery()
            ->join('tblstudent as s', 's.id', '=', 'a.student_id')
            ->leftJoin('standard as st', 'st.id', '=', 'a.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'a.section_id')
            ->selectRaw(self::CODE_SQL . ", a.student_id,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                MAX(a.standard_id) AS standard_id, MAX(a.section_id) AS section_id, MAX(st.name) AS standard_name, MAX(d.name) AS division_name")
            ->groupBy('a.student_id', 's.first_name', 's.middle_name', 's.last_name')
            ->get());

        // The same daily snapshot the existing tool reports, for the latest day.
        $dayRow = $this->sql('Latest-day counts', ['attendance_student'], fn () => DB::table('attendance_student as a')
            ->join('tblstudent as s0', 's0.id', '=', 'a.student_id')
            ->where('a.sub_institute_id', $inst)->where('a.syear', $year)->where('a.attendance_date', $end->toDateString())
            ->selectRaw(self::CODE_SQL)->first());
        $daily = $this->tool('attendance.daily', ['date' => $end->toDateString(), 'limit' => 1], $ctx);
        $dd = $daily['data'] ?? $daily;
        $toolMatches = ($daily['success'] ?? false)
            && (int) ($dd['present_count'] ?? -1) === (int) $dayRow->p
            && (int) ($dd['absent_count'] ?? -1) === (int) $dayRow->a;

        $judged = 0;
        $insufficient = 0;
        $atRisk = [];
        foreach ($rows as $r) {
            $counted = (int) $r->p + (int) $r->a;
            if ($counted < self::MIN_COUNTED) {
                $insufficient++;

                continue;
            }
            $judged++;
            $rate = (int) $r->p / $counted;
            if ($rate * 100 < $threshold) {
                $atRisk[] = [
                    'student_id' => (int) $r->student_id,
                    'student_name' => trim((string) $r->student_name),
                    'standard_id' => $r->standard_id === null ? null : (int) $r->standard_id,
                    'section_id' => $r->section_id === null ? null : (int) $r->section_id,
                    'class' => trim(($r->standard_name ?? '?') . ' - ' . ($r->division_name ?? '?')),
                    'present' => (int) $r->p, 'absent' => (int) $r->a, 'other' => (int) $r->o,
                    'rate' => round($rate, 4),
                ];
            }
        }
        usort($atRisk, static fn ($x, $y) => $x['rate'] <=> $y['rate']);
        $atRiskTotal = count($atRisk);
        $candidates = array_slice($atRisk, 0, $limit);

        $noClass = count(array_filter($candidates, static fn ($c) => ! $c['standard_id'] || ! $c['section_id']));
        $window = ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'days' => $days];

        return [
            'tool' => 'attendance_student + attendance.daily',
            'input' => ['academic_year' => $year, 'window_start' => $window['start'], 'window_end' => $window['end'], 'threshold_percent' => $threshold],
            'summary' => ['records_in_window' => (int) $tot->n, 'students_in_window' => (int) $tot->students, 'at_risk_students' => $atRiskTotal, 'handed_over' => count($candidates)],
            'records' => ['fetched' => (int) $tot->n, 'processed' => $rows->count(), 'produced' => count($candidates)],
            'metrics' => [
                ['label' => 'Records in window', 'value' => (int) $tot->n],
                ['label' => 'Records this year', 'value' => $yearRecords],
                ['label' => 'Students with marks', 'value' => (int) $tot->students],
                ['label' => 'School days marked', 'value' => (int) $tot->days_marked],
                ['label' => 'Classes marked', 'value' => (int) $tot->classes],
                ['label' => 'Present / Absent / Other', 'value' => (int) $tot->p . ' / ' . (int) $tot->a . ' / ' . (int) $tot->o],
                ['label' => 'Students rated', 'value' => $judged],
                ['label' => 'Too few days to rate', 'value' => $insufficient],
                ['label' => "Below {$threshold}%", 'value' => $atRiskTotal],
            ],
            'tables' => [$this->table('at_risk', "Students below {$threshold}% (worst first)", [
                ['student_name', 'Student'], ['class', 'Class'], ['present', 'Present'], ['absent', 'Absent'], ['rate_percent', 'Rate'],
            ], array_map(static fn ($c) => $c + ['rate_percent' => number_format($c['rate'] * 100, 1) . '%'], array_slice($candidates, 0, 50)), $atRiskTotal)],
            'findings' => [
                "Window {$window['start']} → {$window['end']} ({$days} days), ending at the latest day with attendance recorded.",
                $allInWindow > (int) $tot->n ? ($allInWindow - (int) $tot->n) . ' attendance record(s) in the window point at a student who no longer exists in tblstudent; they are left out of every figure, as the attendance tools do.' : null,
                $insufficient > 0 ? "{$insufficient} student(s) had fewer than " . self::MIN_COUNTED . ' coded days and were left out rather than shown as 0%.' : 'Every student with marks had enough coded days to be rated.',
                $noClass > 0 ? "{$noClass} at-risk student(s) have no standard or division on their register rows; their class is taken from enrolment in Agent 2." : null,
                $atRiskTotal > count($candidates) ? "Showing the worst {$limit} of {$atRiskTotal}; the rest are not handed on." : null,
            ],
            'sources' => ['attendance_student', 'tblstudent', 'standard', 'division', 'MCP tool attendance.daily'],
            'gate_checks' => [
                'tenant_isolation' => [$inst > 0, "Every read is filtered to institute {$inst} from the signed-in session."],
                'academic_year_resolved' => [(bool) $year, "Academic year {$year} ({$state['year_source']})."],
                'register_has_records' => [(int) $tot->n > 0, number_format((int) $tot->n) . ' record(s) in the window.'],
                'codes_reconcile' => [(int) $tot->p + (int) $tot->a + (int) $tot->o === (int) $tot->n, "Present + absent + other = {$tot->n} records."],
                'matches_daily_tool' => [$toolMatches, $toolMatches
                    ? "{$end->toDateString()}: {$dayRow->p} present / {$dayRow->a} absent — identical to attendance.daily."
                    : 'attendance.daily disagrees with the direct count for ' . $end->toDateString() . '.'],
                'window_disclosed' => [$start->lte($end), "Window {$window['start']} → {$window['end']}."],
            ],
            'handoff' => [
                'students_to_check' => [$candidates !== [], count($candidates) . ' at-risk student(s) to validate.'],
                'class_resolvable' => [true, (count($candidates) - $noClass) . ' of ' . count($candidates) . ' carry a class on the register; Agent 2 resolves the others from enrolment.'],
            ],
            'handoff_message' => count($candidates) . ' at-risk student(s) with their class handed to Agent 2.',
            'stop' => $candidates === [] ? "No student is below {$threshold}% in this window, so Agents 2–4 had nothing to do." : null,
            'final' => $candidates === [] ? ['headline' => "No student is below {$threshold}% between {$window['start']} and {$window['end']}.", 'next_action' => 'Nothing to follow up.'] : null,
            'data' => ['window' => $window, 'threshold' => $threshold, 'limit' => $limit, 'candidates' => $candidates, 'at_risk_total' => $atRiskTotal, 'tot' => ['n' => (int) $tot->n, 'students' => (int) $tot->students]],
        ];
    }

    /** Agent 2 — validate roster and class teacher in SQL and the Knowledge Graph. */
    private function agent2(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $year = $state['year'];
        $cands = $state['data']['candidates'];
        $ids = array_column($cands, 'student_id');

        $enrol = $this->sql('Enrolment for the year', ['tblstudent_enrollment'], fn () => DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $inst)->where('syear', $year)->whereIn('student_id', $ids)
            ->get(['student_id', 'standard_id', 'section_id'])->keyBy('student_id'));

        $teachers = $this->sql('Class teachers', ['class_teacher', 'tbluser'], fn () => DB::table('class_teacher as ct')
            ->join('tbluser as u', function ($j) use ($inst) {
                $j->on('u.id', '=', 'ct.teacher_id')->where('u.sub_institute_id', '=', $inst);
            })
            ->where('ct.sub_institute_id', $inst)->where('ct.syear', $year)
            ->get(['ct.standard_id', 'ct.division_id', 'ct.teacher_id', 'u.status', DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) AS name")]));
        $teacherByClass = [];
        foreach ($teachers as $t) {
            $teacherByClass[$t->standard_id . ':' . $t->division_id] ??= ['id' => (int) $t->teacher_id, 'name' => $t->name, 'active' => (string) $t->status === '1'];
        }

        $ping = $this->graph->ping();
        $placement = $terms = $gTeachers = [];
        $graphOk = $ping['ok'];
        if ($graphOk) {
            $r1 = $this->graph('Student placement', '(Student)-[:IN_DIVISION]->(Division)', fn () => [$this->graph->studentPlacement($inst, $ids, $year)], fn ($r) => count($r[0] ?? []));
            $graphOk = $r1['ok'];
            $placement = $r1['rows'][0] ?? [];
            $sections = array_values(array_unique(array_filter(array_map(static fn ($p) => $p['section_id'], $placement))));
            $r2 = $this->graph('Class teachers by division', '(Staff)-[:CLASS_TEACHER_OF]->(Division)', fn () => [$this->graph->classTeachersByDivision($inst, $sections)], fn ($r) => array_sum(array_map('count', $r[0] ?? [])));
            $gTeachers = $r2['rows'][0] ?? [];
            $r3 = $this->graph('Prior-term attendance', '(StuDetail)-[:ATTENDANCE]->(AcademicYear)', fn () => [$this->graph->termAttendance($inst, $ids)], fn ($r) => array_sum(array_map('count', $r[0] ?? [])));
            $terms = $r3['rows'][0] ?? [];
        }

        $enrolled = $found = $agree = $teacherSql = $teacherConfirmed = $foreign = 0;
        $validated = [];
        foreach ($cands as $c) {
            $e = $enrol[$c['student_id']] ?? null;
            $sqlStd = $e ? (int) $e->standard_id : null;
            $sqlSec = $e ? (int) $e->section_id : null;
            $p = $placement[$c['student_id']] ?? null;
            $enrolled += $e ? 1 : 0;
            $found += $p ? 1 : 0;
            $foreign += ($p && $p['inst'] !== $inst) ? 1 : 0;
            $placeAgree = $p && $e && $p['standard_id'] === $sqlStd && $p['section_id'] === $sqlSec;
            $agree += $placeAgree ? 1 : 0;

            $t = $teacherByClass[($sqlStd ?? $c['standard_id']) . ':' . ($sqlSec ?? $c['section_id'])] ?? null;
            $teacherSql += $t ? 1 : 0;
            $confirmed = false;
            if ($t && $p && $p['section_id'] !== null) {
                $confirmed = in_array($t['id'], array_column($gTeachers[(string) $p['section_id']] ?? [], 'staff_id'), true);
            }
            $teacherConfirmed += $confirmed ? 1 : 0;

            $prior = $terms[$c['student_id']][0] ?? null;
            $validated[] = $c + [
                'enrolled_in_sql' => (bool) $e,
                'in_graph' => (bool) $p,
                'graph_agrees' => (bool) $placeAgree,
                'teacher_id' => $t['id'] ?? null,
                'teacher_name' => $t['name'] ?? null,
                'teacher_active' => $t['active'] ?? false,
                'teacher_in_graph' => $confirmed,
                'prior_term_percent' => $prior['percentage'] ?? null,
            ];
        }
        $total = count($cands);
        $pct = static fn (int $a, int $b) => $b === 0 ? 0 : (int) round($a / $b * 100);

        $byClass = [];
        foreach ($validated as $v) {
            $k = $v['class'];
            $byClass[$k] ??= ['class' => $k, 'at_risk' => 0, 'teacher' => $v['teacher_name'] ?? 'No class teacher', 'graph_confirms' => $v['teacher_in_graph'] ? 'Yes' : 'No'];
            $byClass[$k]['at_risk']++;
        }

        return [
            'tool' => 'tblstudent_enrollment + class_teacher + Neo4j',
            'input' => ['students' => $total, 'academic_year' => $year],
            'summary' => ['students_checked' => $total, 'enrolled_in_sql' => $enrolled, 'found_in_graph' => $found, 'graph_agrees_on_class' => $agree, 'class_teacher_resolved' => $teacherSql, 'teacher_confirmed_by_graph' => $teacherConfirmed],
            'records' => ['fetched' => $total, 'processed' => $total, 'produced' => count($validated)],
            'metrics' => [
                ['label' => 'Students checked', 'value' => $total],
                ['label' => 'Enrolled (database)', 'value' => "{$enrolled} ({$pct($enrolled, $total)}%)"],
                ['label' => 'Found in Knowledge Graph', 'value' => "{$found} ({$pct($found, $total)}%)"],
                ['label' => 'Graph agrees on class', 'value' => "{$agree} of {$found}"],
                ['label' => 'Class teacher in database', 'value' => "{$teacherSql} ({$pct($teacherSql, $total)}%)"],
                ['label' => 'Teacher confirmed by graph', 'value' => "{$teacherConfirmed} of {$teacherSql}"],
                ['label' => 'Graph latency', 'value' => $ping['ms'] . ' ms'],
            ],
            'tables' => [
                $this->table('class_coverage', 'At-risk students by class and class teacher', [['class', 'Class'], ['at_risk', 'At-risk'], ['teacher', 'Class teacher'], ['graph_confirms', 'Graph confirms']], array_values($byClass)),
                $this->table('roster', 'Roster validation', [['student_name', 'Student'], ['class', 'Class'], ['sql', 'Database'], ['graph', 'Graph'], ['teacher_name', 'Class teacher'], ['prior', 'Prior term (graph)']],
                    array_map(static fn ($v) => [
                        'student_name' => $v['student_name'], 'class' => $v['class'],
                        'sql' => $v['enrolled_in_sql'] ? 'Enrolled' : 'Not enrolled',
                        'graph' => ! $v['in_graph'] ? 'Not in graph' : ($v['graph_agrees'] ? 'Agrees' : 'Differs'),
                        'teacher_name' => $v['teacher_name'] ?? '—',
                        'prior' => $v['prior_term_percent'] === null ? '—' : $v['prior_term_percent'] . '%',
                    ], array_slice($validated, 0, 50)), $total),
            ],
            'findings' => array_values(array_filter([
                $graphOk ? null : 'The Knowledge Graph could not be read: ' . ($ping['error'] ?? 'unknown error'),
                $found < $total ? ($total - $found) . ' student(s) are not in the Knowledge Graph yet, so only the database vouches for them.' : null,
                $found > 0 && $agree < $found ? ($found - $agree) . ' student(s) are placed in a different class by the graph than by the database — the graph is out of date for them.' : null,
                $teacherSql < $total ? ($total - $teacherSql) . ' student(s) are in a class with no class teacher assigned.' : null,
            ])),
            'sources' => ['tblstudent_enrollment', 'class_teacher', 'tbluser', 'Neo4j: Student, Division, Staff, StuDetail'],
            'gate_checks' => [
                'graph_reachable' => [$graphOk, $graphOk ? "Neo4j answered in {$ping['ms']} ms." : 'Neo4j could not be reached: ' . ($ping['error'] ?? '')],
                'graph_rows_in_this_institute' => [$foreign === 0, $foreign === 0 ? "All {$found} graph placements belong to institute {$inst}." : "{$foreign} graph row(s) belong to another institute."],
                'students_enrolled_in_database' => [$pct($enrolled, $total) >= 90, "{$enrolled} of {$total} are enrolled for {$year}."],
                'graph_covers_students' => [$pct($found, $total) >= 50, "{$found} of {$total} are in the graph ({$pct($found, $total)}%, needs 50%)."],
                'graph_agrees_with_database' => [$found === 0 || $pct($agree, $found) >= 80, "{$agree} of {$found} graph placements match the database ({$pct($agree, max($found, 1))}%, needs 80%)."],
            ],
            'handoff' => [
                'every_student_carried_forward' => [count($validated) === $total, count($validated) . ' of ' . $total . ' students carried forward.'],
            ],
            'handoff_message' => $total . ' validated student(s) with class teacher and prior-term attendance handed to Agent 3.',
            'data' => ['validated' => $validated, 'teacher_by_class' => $teacherByClass, 'graph_stats' => compact('found', 'agree', 'teacherConfirmed') + ['ms' => $ping['ms']]],
        ];
    }

    /** Agent 3 — analyse absence patterns from the daily marks. */
    private function agent3(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $year = $state['year'];
        $w = $state['data']['window'];
        $validated = $state['data']['validated'];
        $ids = array_column($validated, 'student_id');

        $marks = $this->sql('Daily marks in window', ['attendance_student'], fn () => DB::table('attendance_student')
            ->where('sub_institute_id', $inst)->where('syear', $year)->whereIn('student_id', $ids)
            ->whereBetween('attendance_date', [$w['start'], $w['end']])
            ->orderBy('attendance_date')->get(['student_id', 'attendance_date', 'attendance_code']));

        $prevEnd = Carbon::parse($w['start'])->subDay();
        $prevStart = $prevEnd->copy()->subDays($w['days'] - 1);
        $prev = $this->sql('Previous window rates', ['attendance_student'], fn () => DB::table('attendance_student as a')
            ->where('a.sub_institute_id', $inst)->where('a.syear', $year)->whereIn('a.student_id', $ids)
            ->whereBetween('a.attendance_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->selectRaw(self::CODE_SQL . ', a.student_id')->groupBy('a.student_id')->get()->keyBy('student_id'));

        $byStudent = [];
        foreach ($marks as $m) {
            $byStudent[$m->student_id][] = [$m->attendance_date, strtoupper((string) $m->attendance_code)];
        }
        // The school days of the window (any class marked), newest last: "last 7 school days".
        $schoolDays = $marks->pluck('attendance_date')->unique()->sort()->values()->all();
        $last7 = array_slice($schoolDays, -7);

        $mismatch = $thin = $impossible = 0;
        $cases = [];
        foreach ($validated as $v) {
            $rows = $byStudent[$v['student_id']] ?? [];
            $p = count(array_filter($rows, static fn ($r) => $r[1] === 'P'));
            $a = count(array_filter($rows, static fn ($r) => $r[1] === 'A'));
            $counted = $p + $a;
            $rate = $counted > 0 ? round($p / $counted, 4) : null;
            $mismatch += ($rate === null || abs($rate - $v['rate']) > 0.0001) ? 1 : 0;
            $thin += $counted < self::MIN_COUNTED ? 1 : 0;

            $streak = 0;
            foreach (array_reverse($rows) as $r) {
                if ($r[1] === 'A') {
                    $streak++;
                } elseif ($r[1] === 'P') {
                    break;
                }
            }
            $impossible += $streak > $a ? 1 : 0;
            $recent = count(array_filter($rows, static fn ($r) => $r[1] === 'A' && in_array($r[0], $last7, true)));

            $pv = $prev[$v['student_id']] ?? null;
            $pc = $pv ? (int) $pv->p + (int) $pv->a : 0;
            $prevRate = $pc >= self::MIN_COUNTED ? round((int) $pv->p / $pc, 4) : null;
            $delta = ($prevRate !== null && $rate !== null) ? round(($rate - $prevRate) * 100, 1) : null;

            $severity = ($rate ?? 1) < 0.5 ? 'critical' : (($rate ?? 1) < 0.65 ? 'high' : 'medium');
            if ($streak >= 5 && $severity !== 'critical') {
                $severity = $severity === 'medium' ? 'high' : 'critical';
            }

            $cases[] = $v + [
                'rate_recomputed' => $rate, 'counted_days' => $counted, 'absent_streak' => $streak,
                'absent_last_7' => $recent, 'previous_rate' => $prevRate, 'change_points' => $delta, 'severity' => $severity,
            ];
        }
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2];
        usort($cases, static fn ($x, $y) => [$order[$x['severity']], $x['rate']] <=> [$order[$y['severity']], $y['rate']]);

        $sev = array_count_values(array_column($cases, 'severity')) + ['critical' => 0, 'high' => 0, 'medium' => 0];
        $roll = [];
        foreach ($cases as $c) {
            $roll[$c['class']] ??= ['class' => $c['class'], 'cases' => 0, 'critical' => 0, 'avg' => [], 'teacher' => $c['teacher_name'] ?? 'No class teacher'];
            $roll[$c['class']]['cases']++;
            $roll[$c['class']]['critical'] += $c['severity'] === 'critical' ? 1 : 0;
            $roll[$c['class']]['avg'][] = $c['rate'];
        }
        $roll = array_map(static fn ($r) => ['class' => $r['class'], 'cases' => $r['cases'], 'critical' => $r['critical'], 'avg_rate' => number_format(array_sum($r['avg']) / count($r['avg']) * 100, 1) . '%', 'teacher' => $r['teacher']], $roll);
        usort($roll, static fn ($x, $y) => $y['cases'] <=> $x['cases']);
        $longest = max(array_column($cases, 'absent_streak') ?: [0]);
        $declining = count(array_filter($cases, static fn ($c) => $c['change_points'] !== null && $c['change_points'] < 0));

        return [
            'tool' => 'attendance_student (daily marks)',
            'input' => ['students' => count($validated), 'window' => $w['start'] . ' → ' . $w['end']],
            'summary' => ['cases' => count($cases), 'critical' => $sev['critical'], 'high' => $sev['high'], 'medium' => $sev['medium'], 'longest_absent_streak' => $longest],
            'records' => ['fetched' => $marks->count(), 'processed' => count($validated), 'produced' => count($cases)],
            'metrics' => [
                ['label' => 'Daily marks read', 'value' => $marks->count()],
                ['label' => 'Students analysed', 'value' => count($validated)],
                ['label' => 'Critical (<50% or long streak)', 'value' => $sev['critical']],
                ['label' => 'High (<65%)', 'value' => $sev['high']],
                ['label' => 'Medium', 'value' => $sev['medium']],
                ['label' => 'Longest absence streak', 'value' => $longest . ' day(s)'],
                ['label' => 'Declining vs previous window', 'value' => $declining],
                ['label' => 'Classes affected', 'value' => count($roll)],
            ],
            'tables' => [
                $this->table('cases', 'Cases, most severe first', [['student_name', 'Student'], ['class', 'Class'], ['severity', 'Severity'], ['rate_percent', 'Rate'], ['absent_streak', 'Absent streak'], ['absent_last_7', 'Absent in last 7 days'], ['change', 'Change vs previous']],
                    array_map(static fn ($c) => [
                        'student_name' => $c['student_name'], 'class' => $c['class'], 'severity' => $c['severity'],
                        'rate_percent' => number_format($c['rate'] * 100, 1) . '%', 'absent_streak' => $c['absent_streak'], 'absent_last_7' => $c['absent_last_7'],
                        'change' => $c['change_points'] === null ? 'No earlier data' : ($c['change_points'] > 0 ? '+' : '') . $c['change_points'] . ' pts',
                    ], array_slice($cases, 0, 50)), count($cases)),
                $this->table('class_rollup', 'By class', [['class', 'Class'], ['cases', 'Cases'], ['critical', 'Critical'], ['avg_rate', 'Average rate'], ['teacher', 'Class teacher']], $roll),
            ],
            'findings' => array_values(array_filter([
                $declining > 0 ? "{$declining} student(s) are doing worse than in the previous " . $w['days'] . '-day window.' : null,
                $longest >= 5 ? "Longest current streak is {$longest} school days absent in a row." : null,
                'Severity bands: critical below 50% (or a 5+ day streak lifts one band), high below 65%, medium otherwise.',
            ])),
            'sources' => ['attendance_student'],
            'gate_checks' => [
                'rates_match_agent_1' => [$mismatch === 0, $mismatch === 0 ? 'Every rate recomputed from the daily marks equals the one Agent 1 handed over.' : "{$mismatch} rate(s) differ from Agent 1's."],
                'every_candidate_a_case' => [count($cases) === count($validated), count($cases) . ' case(s) from ' . count($validated) . ' student(s).'],
                'enough_evidence_per_case' => [$thin === 0, $thin === 0 ? 'Every case rests on at least ' . self::MIN_COUNTED . ' coded days.' : "{$thin} case(s) have too few coded days."],
                'streaks_are_possible' => [$impossible === 0, $impossible === 0 ? 'No streak exceeds the student\'s absent days.' : "{$impossible} streak(s) exceed the absent days."],
            ],
            'handoff' => ['cases_to_draft' => [$cases !== [], count($cases) . ' case(s) to draft follow-ups for.']],
            'handoff_message' => count($cases) . ' ranked case(s) across ' . count($roll) . ' class(es) handed to Agent 4.',
            'data' => ['cases' => $cases, 'rollup' => $roll, 'severity' => $sev],
        ];
    }

    /** Agent 4 — draft follow-ups (preview only). */
    private function agent4(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $cases = $state['data']['cases'];
        $w = $state['data']['window'];
        $thr = $state['data']['threshold'];

        $byTeacher = [];
        $unassigned = [];
        foreach ($cases as $c) {
            if ($c['teacher_id']) {
                $byTeacher[$c['teacher_id']]['teacher'] = $c['teacher_name'];
                $byTeacher[$c['teacher_id']]['cases'][] = $c;
            } else {
                $unassigned[$c['class']][] = $c;
            }
        }

        $teacherRows = $this->sql('Teacher accounts', ['tbluser'], fn () => DB::table('tbluser')
            ->where('sub_institute_id', $inst)->whereIn('id', array_keys($byTeacher) ?: [0])->get(['id', 'status'])->keyBy('id'));

        $studentIds = array_column($cases, 'student_id');
        $contacts = $this->sql('Parent contact availability', ['tblstudent'], fn () => DB::table('tblstudent')
            ->where('sub_institute_id', $inst)->whereIn('id', $studentIds ?: [0])
            ->selectRaw("COUNT(*) AS n, SUM(CASE WHEN COALESCE(NULLIF(TRIM(mobile),''), NULLIF(TRIM(mother_mobile),'')) IS NOT NULL THEN 1 ELSE 0 END) AS reachable")->first());

        $drafts = [];
        $activeOk = true;
        foreach ($byTeacher as $tid => $g) {
            $activeOk = $activeOk && isset($teacherRows[$tid]) && (string) $teacherRows[$tid]->status === '1';
            $lines = array_map(static fn ($c) => sprintf('• %s (%s) — %s%% attendance, %d absent%s', $c['student_name'], $c['class'], number_format($c['rate'] * 100, 1), $c['absent'], $c['absent_streak'] >= 3 ? ", absent {$c['absent_streak']} days running" : ''), array_slice($g['cases'], 0, 15));
            $classes = array_values(array_unique(array_column($g['cases'], 'class')));
            $first = count($classes) <= 3 ? implode(', ', $classes) : implode(', ', array_slice($classes, 0, 3)) . ' +' . (count($classes) - 3) . ' more';
            $mix = array_count_values(array_column($g['cases'], 'severity'));
            $drafts[] = [
                'teacher_id' => $tid, 'teacher' => $g['teacher'], 'class' => $first, 'students' => count($g['cases']),
                'student_rows' => array_map(static fn ($c) => [
                    'student_id' => $c['student_id'], 'name' => $c['student_name'], 'class' => $c['class'],
                    'rate_percent' => number_format($c['rate'] * 100, 1) . '%', 'present' => $c['present'], 'absent' => $c['absent'],
                    'absent_streak' => $c['absent_streak'], 'absent_last_7' => $c['absent_last_7'], 'severity' => $c['severity'],
                    'prior_term_percent' => $c['prior_term_percent'], 'change_points' => $c['change_points'],
                ], $g['cases']),
                'severity' => ($mix['critical'] ?? 0) . ' critical, ' . ($mix['high'] ?? 0) . ' high, ' . ($mix['medium'] ?? 0) . ' medium',
                'message' => "Dear {$g['teacher']},\nBetween {$w['start']} and {$w['end']}, these students in your class(es) are below {$thr}% attendance:\n" . implode("\n", $lines) . "\nPlease speak with the students and their parents and record the reason for the absences.",
            ];
        }
        usort($drafts, static fn ($a, $b) => $b['students'] <=> $a['students']);
        $unRows = [];
        foreach ($unassigned as $class => $list) {
            $unRows[] = ['class' => $class, 'students' => count($list), 'action' => 'Assign a class teacher in Academics, then re-run.'];
        }

        $covered = array_sum(array_map(static fn ($d) => $d['students'], $drafts)) + array_sum(array_column($unRows, 'students'));
        $reachable = (int) ($contacts->reachable ?? 0);
        $headline = sprintf(
            '%d student(s) below %d%% in %d class(es); %d follow-up draft(s) for class teachers%s. Nothing was sent or changed.',
            count($cases), $thr, count($state['data']['rollup']), count($drafts),
            $unRows === [] ? '' : sprintf(', %d class(es) have no class teacher', count($unRows))
        );

        return [
            'tool' => 'drafts (preview only)',
            'input' => ['cases' => count($cases)],
            'summary' => ['drafts' => count($drafts), 'teachers' => count($byTeacher), 'unassigned_classes' => count($unRows), 'sent' => 0, 'records_changed' => 0],
            'records' => ['fetched' => count($cases), 'processed' => count($cases), 'produced' => count($drafts)],
            'metrics' => [
                ['label' => 'Cases', 'value' => count($cases)],
                ['label' => 'Class teachers to notify', 'value' => count($byTeacher)],
                ['label' => 'Drafts written', 'value' => count($drafts)],
                ['label' => 'Classes with no teacher', 'value' => count($unRows)],
                ['label' => 'Parents reachable', 'value' => $reachable . ' of ' . (int) $contacts->n],
                ['label' => 'Messages sent', 'value' => 0],
                ['label' => 'Records changed', 'value' => 0],
            ],
            'tables' => [
                $this->table('drafts', 'Follow-up drafts (not sent)', [['teacher', 'Class teacher'], ['class', 'Class'], ['students', 'Students'], ['severity', 'Severity mix']], $drafts),
                $this->table('unassigned', 'Classes with no class teacher', [['class', 'Class'], ['students', 'At-risk students'], ['action', 'Needed']], $unRows),
            ],
            'findings' => array_values(array_filter([
                $drafts !== [] ? 'Sample draft — ' . $drafts[0]['teacher'] . ":\n" . $drafts[0]['message'] : null,
                'These are drafts only. To send, a person approves them through the Attendance assistant (attendance_followup workflow).',
            ])),
            'sources' => ['tbluser', 'tblstudent', 'Agent 3 cases'],
            'gate_checks' => [
                'nothing_sent' => [true, 'No message, SMS or email was sent by this pipeline.'],
                'no_records_changed' => [true, 'No attendance, user or student record was written.'],
                'teachers_are_active_accounts' => [$activeOk, $activeOk ? 'Every drafted teacher is an active account of this institute.' : 'A drafted teacher is missing or inactive.'],
                'every_case_covered' => [$covered === count($cases), "{$covered} of " . count($cases) . ' case(s) are in a draft or listed as unassigned.'],
            ],
            'final' => [
                'headline' => $headline,
                'next_action' => 'A person reviews the drafts and sends them through the Attendance assistant. Unassigned classes need a class teacher first.',
                'severity' => $state['data']['severity'],
                'window' => $w,
                'threshold_percent' => $thr,
                'at_risk_students' => count($cases),
                'unassigned_classes' => $unRows,
                'drafts' => $drafts,
                'sent' => 0,
                'status' => 'draft',
            ],
            'data' => ['drafts' => $drafts],
        ];
    }
}
