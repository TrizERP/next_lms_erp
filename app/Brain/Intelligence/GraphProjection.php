<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The organization projected as a graph, live from vivek_erp and the Brain's
 * own loop tables.
 *
 * Ported in shape from hp-enterprise-brain/app/Domain/Graph/GraphProjection.php:
 * the same node/edge contract, the same expand-with-offset walk, the same
 * vocabulary gate. What differs is the source — the reference reads imported
 * datasets, this reads the LMS's own tables — and that is the only difference
 * that should exist.
 *
 * PROJECTED, NEVER MATERIALISED. There is no hpbrain_graph_nodes table and there
 * should not be one: a copy of the organization goes stale the moment somebody
 * renames a department, and a graph that disagrees with the ERP is worse than no
 * graph. Every node and edge on this screen is a query against the system of
 * record, run when you ask for it.
 *
 * EVERY EDGE CARRIES ITS PROVENANCE. GraphVocabulary names the column behind
 * each relationship and the clause travels with the edge to the client. An edge
 * type absent from that vocabulary cannot be emitted at all.
 *
 * THE LOOP IS IN THE GRAPH. Signal → Evidence → Case → Recommendation → Decision
 * are nodes like any other, hanging off the department, class or student they
 * concern. That is what makes this an intelligence graph rather than an org
 * chart: you can walk from a class to the finding about it to the action
 * somebody took.
 */
final class GraphProjection
{
    use LmsQueryScope;

    /** Neighbours returned per edge per page. */
    private const PAGE = 25;

    public function __construct(private readonly string $tenantId)
    {
    }

    /* ---------------------------------------------------------------- summary */

    /**
     * How many of each label this institute actually has.
     *
     * A label with zero rows is still returned, with its count, because "this
     * school records no subjects" is information the explorer should show rather
     * than hide by omitting the chip.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(): array
    {
        $counts = [
            'Organization' => 1,
            'Department' => $this->occupiedDepartments(),
            'Person' => $this->lmsCount('tbluser'),
            'Student' => $this->lmsCount('tblstudent'),
            'Class' => $this->activeClasses(),
            'Subject' => $this->lmsCount('subject'),
            'Signal' => $this->brainCount('hpbrain_signals'),
            'Evidence' => $this->brainCount('hpbrain_evidence'),
            'Case' => $this->brainCount('hpbrain_cases'),
            'Recommendation' => $this->brainCount('hpbrain_recommendations'),
            'Decision' => $this->brainCount('hpbrain_decisions'),
            'Capability' => $this->brainCount('hpbrain_capabilities'),
        ];

        $out = [];
        foreach ($counts as $label => $count) {
            $out[] = [
                'label' => $label,
                'plural' => GraphVocabulary::plural($label),
                'family' => GraphVocabulary::family($label),
                'count' => $count,
            ];
        }

        return $out;
    }

    /* ----------------------------------------------------------------- search */

    /**
     * Find a node by name across every label, or a chosen subset.
     *
     * @param  array<int, string>  $labels
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, array $labels = [], int $limit = 40): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $wanted = $labels === []
            ? array_keys(GraphVocabulary::LABEL_FAMILY)
            : array_values(array_filter($labels, fn ($l) => GraphVocabulary::isKnownLabel($l)));

        $results = [];
        $perLabel = max(3, (int) floor($limit / max(1, count($wanted))));

        foreach ($wanted as $label) {
            foreach ($this->nodes($label, $term, $perLabel) as $node) {
                $results[] = $node;
            }
        }

        // Most-connected first: a department with 31 staff is a more useful
        // answer to an ambiguous search than a subject nobody has used.
        usort($results, fn ($a, $b) => $b['degree'] <=> $a['degree']);

        return array_slice($results, 0, $limit);
    }

    /* ------------------------------------------------------------ node lists */

    /** @return array<int, array<string, mixed>> */
    public function nodes(string $label, string $search = '', int $limit = 50, int $offset = 0): array
    {
        if (! GraphVocabulary::isKnownLabel($label)) {
            return [];
        }

        return match ($label) {
            'Organization' => [$this->organizationNode()],
            'Department' => $this->departmentNodes($search, $limit, $offset),
            'Person' => $this->personNodes($search, $limit, $offset),
            'Student' => $this->studentNodes($search, $limit, $offset),
            'Class' => $this->classNodes($search, $limit, $offset),
            'Subject' => $this->subjectNodes($search, $limit, $offset),
            'Signal' => $this->signalNodes($search, $limit, $offset),
            'Case' => $this->caseNodes($search, $limit, $offset),
            'Recommendation' => $this->recommendationNodes($search, $limit, $offset),
            'Decision' => $this->decisionNodes($search, $limit, $offset),
            'Evidence' => $this->evidenceNodes($search, $limit, $offset),
            'Capability' => $this->capabilityNodes($search, $limit, $offset),
            default => [],
        };
    }

    /* -------------------------------------------------------------- expansion */

    /**
     * One node and its neighbourhood.
     *
     * @return array<string, mixed>
     */
    public function expand(string $label, string $id, int $offset = 0): array
    {
        if (! GraphVocabulary::isKnownLabel($label)) {
            return ['available' => false, 'reason' => 'That kind of node is not part of this graph.'];
        }

        return match ($label) {
            'Organization' => $this->expandOrganization(),
            'Department' => $this->expandDepartment($id, $offset),
            'Person' => $this->expandPerson($id, $offset),
            'Student' => $this->expandStudent($id, $offset),
            'Class' => $this->expandClass($id, $offset),
            'Subject' => $this->expandSubject($id, $offset),
            'Signal' => $this->expandSignal($id),
            'Case' => $this->expandCase($id),
            'Recommendation' => $this->expandRecommendation($id),
            'Decision' => $this->expandDecision($id),
            default => ['available' => false, 'reason' => 'That node cannot be expanded.'],
        };
    }

    /* ------------------------------------------------------- node constructors */

    private function node(string $label, string $id, string $title, int $degree, array $metrics = [], ?string $subtitle = null): array
    {
        return [
            'label' => $label,
            'family' => GraphVocabulary::family($label),
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'degree' => $degree,
            'metrics' => $metrics,
        ];
    }

    private function organizationNode(): array
    {
        $name = SchemaCache::hasTable('hpbrain_organizations')
            ? DB::table('hpbrain_organizations')->where('tenant_id', $this->tenantId)->value('name')
            : null;

        return $this->node('Organization', $this->tenantId, (string) ($name ?: 'This organization'),
            $this->occupiedDepartments() + $this->activeClasses(), [
                ['label' => 'Departments with staff', 'value' => number_format($this->occupiedDepartments())],
                ['label' => 'Staff', 'value' => number_format($this->lmsCount('tbluser'))],
                ['label' => 'Students', 'value' => number_format($this->lmsCount('tblstudent'))],
                ['label' => 'Open findings', 'value' => number_format($this->brainCount('hpbrain_signals'))],
            ]);
    }

    private function departmentNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hrms_departments') || ! SchemaCache::hasTable('tbluser')) {
            return [];
        }

        $q = DB::table('tbluser as u')
            ->join('hrms_departments as d', 'd.id', '=', 'u.department_id')
            ->where('u.sub_institute_id', $this->tenantId)
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($qq) => $qq->where('d.status', 1));
        if (SchemaCache::hasTable('tbluserprofilemaster')) {
            $q->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id');
        }
        return $q->when($search !== '', fn ($qq) => $qq->where('d.department', 'like', '%'.$search.'%'))
            ->selectRaw('d.id, d.department, d.head_user_id, COUNT(u.id) as headcount')
            ->groupBy('d.id', 'd.department', 'd.head_user_id')
            ->orderByDesc('headcount')->offset($offset)->limit($limit)->get()
            ->map(fn ($r) => $this->node('Department', (string) $r->id, (string) $r->department, (int) $r->headcount, [
                ['label' => 'Staff', 'value' => number_format((int) $r->headcount)],
                ['label' => 'Head', 'value' => $r->head_user_id ? 'Assigned' : 'None'],
            ]))->all();
    }

    private function personNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return [];
        }

        $q = DB::table('tbluser as u')
            ->leftJoin('hrms_departments as d', 'd.id', '=', 'u.department_id')
            ->where('u.sub_institute_id', $this->tenantId);
        if (SchemaCache::hasTable('tbluserprofilemaster')) {
            $q->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id');
        }
        $q->when($search !== '', fn ($qq) => $qq->where(fn ($i) => $i
                ->where('u.first_name', 'like', '%'.$search.'%')
                ->orWhere('u.last_name', 'like', '%'.$search.'%')
                ->orWhere('u.email', 'like', '%'.$search.'%')))
            ->orderBy('u.first_name')->offset($offset)->limit($limit)
            ->get(['u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.status', 'u.last_login', 'd.department'])
            ->map(fn ($r) => $this->node('Person', (string) $r->id,
                trim($r->first_name.' '.$r->last_name) ?: ('Staff '.$r->id),
                $r->department ? 1 : 0,
                [
                    ['label' => 'Department', 'value' => (string) ($r->department ?: 'None')],
                    ['label' => 'Signed in', 'value' => empty($r->last_login) ? 'Never' : 'Yes'],
                ],
                (string) ($r->email ?: '')))->all();
    }

    private function studentNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('tblstudent')) {
            return [];
        }

        $q = DB::table('tblstudent')->where('sub_institute_id', $this->tenantId);
        if (SchemaCache::hasColumn('tblstudent', 'status')) {
            $q->where('tblstudent.status', 1);
        }
        $q->when($search !== '', fn ($qq) => $qq->where(fn ($i) => $i
                ->where('tblstudent.first_name', 'like', '%'.$search.'%')
                ->orWhere('tblstudent.last_name', 'like', '%'.$search.'%')
                ->orWhere('tblstudent.enrollment_no', 'like', '%'.$search.'%')))
            ->orderBy('tblstudent.first_name')->offset($offset)->limit($limit)
            ->get(['tblstudent.id', 'tblstudent.first_name', 'tblstudent.last_name', 'tblstudent.enrollment_no', 'tblstudent.admission_year'])
            ->map(fn ($r) => $this->node('Student', (string) $r->id,
                trim($r->first_name.' '.$r->last_name) ?: ('Student '.$r->id), 0,
                array_values(array_filter([
                    $r->admission_year ? ['label' => 'Admitted', 'value' => (string) $r->admission_year] : null,
                ])),
                (string) ($r->enrollment_no ?: '')))->all();
    }

    private function classNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('attendance_student') || ! SchemaCache::hasTable('standard')) {
            return [];
        }

        return DB::table('attendance_student as a')
            ->join('standard as s', 's.id', '=', 'a.standard_id')
            ->where('a.sub_institute_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('s.name', 'like', '%'.$search.'%'))
            ->selectRaw('a.standard_id, s.name, COUNT(DISTINCT a.student_id) as students, COUNT(*) as marks, SUM(a.attendance_code = "P") as present')
            ->groupBy('a.standard_id', 's.name')
            ->orderByDesc('students')->offset($offset)->limit($limit)->get()
            ->map(function ($r) {
                $marks = (int) $r->marks;
                $rate = $marks > 0 ? round(((int) $r->present) / $marks * 100, 1) : 0;

                return $this->node('Class', (string) $r->standard_id, $this->className($r->name), (int) $r->students, [
                    ['label' => 'Students', 'value' => number_format((int) $r->students)],
                    ['label' => 'Attendance', 'value' => self::num($rate).'%'],
                ]);
            })->all();
    }

    private function subjectNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('subject')) {
            return [];
        }

        $homework = SchemaCache::hasTable('homework')
            ? DB::table('homework')->where('sub_institute_id', $this->tenantId)
                ->selectRaw('subject_id, COUNT(*) as total')->groupBy('subject_id')->pluck('total', 'subject_id')
            : collect();

        return DB::table('subject')
            ->where('sub_institute_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('subject_name', 'like', '%'.$search.'%'))
            ->orderBy('subject_name')->offset($offset)->limit($limit)
            ->get(['id', 'subject_name', 'subject_code'])
            ->map(fn ($r) => $this->node('Subject', (string) $r->id, (string) $r->subject_name,
                (int) ($homework[$r->id] ?? 0),
                [['label' => 'Homework set', 'value' => number_format((int) ($homework[$r->id] ?? 0))]],
                (string) ($r->subject_code ?: '')))->all();
    }

    private function signalNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        return DB::table('hpbrain_signals')->where('tenant_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('metadata', 'like', '%'.$search.'%'))
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->offset($offset)->limit($limit)->get()
            ->map(function ($r) {
                $card = Narrative::forSignal(array_merge((array) $r, [
                    'metadata' => json_decode((string) $r->metadata, true) ?: [],
                ]));

                return $this->node('Signal', (string) $r->id, $card['title'], 0, [
                    ['label' => 'Severity', 'value' => $card['severityLabel']],
                    ['label' => 'Owner', 'value' => $card['owner']],
                ], $card['headline']['value'] ?? null);
            })->all();
    }

    private function caseNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hpbrain_cases')) {
            return [];
        }

        return DB::table('hpbrain_cases')->where('tenant_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('created_date')->offset($offset)->limit($limit)->get()
            ->map(fn ($r) => $this->node('Case', (string) $r->id, (string) $r->title, 0, [
                ['label' => 'Status', 'value' => ucfirst((string) $r->status)],
            ]))->all();
    }

    private function recommendationNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hpbrain_recommendations')) {
            return [];
        }

        return DB::table('hpbrain_recommendations')->where('tenant_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->offset($offset)->limit($limit)->get()
            ->map(fn ($r) => $this->node('Recommendation', (string) $r->id, (string) $r->title, 0, [
                ['label' => 'Priority', 'value' => ucfirst((string) $r->priority)],
                ['label' => 'Status', 'value' => ucfirst((string) $r->status)],
                ['label' => 'Confidence', 'value' => Narrative::confidenceBand((float) $r->confidence)],
            ]))->all();
    }

    private function decisionNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hpbrain_decisions')) {
            return [];
        }

        $rows = DB::table('hpbrain_decisions')->where('tenant_id', $this->tenantId)
            ->orderByDesc('created_date')->offset($offset)->limit($limit)->get();

        $names = SchemaCache::hasTable('tbluser')
            ? DB::table('tbluser')->whereIn('id', $rows->pluck('decided_by')->filter()->all())
                ->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn ($u) => [(string) $u->id => trim($u->first_name.' '.$u->last_name)])
            : collect();

        return $rows->map(fn ($r) => $this->node('Decision', (string) $r->id,
            (string) ($r->explanation ?: 'Decision'), 0, [
                ['label' => 'Status', 'value' => ucfirst((string) $r->status)],
                ['label' => 'Decided by', 'value' => (string) ($names[(string) $r->decided_by] ?? $r->decided_by)],
            ]))->all();
    }

    private function evidenceNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hpbrain_evidence')) {
            return [];
        }

        return DB::table('hpbrain_evidence')->where('tenant_id', $this->tenantId)
            ->orderBy('ledger_sequence')->offset($offset)->limit($limit)->get()
            ->map(function ($r) {
                $content = json_decode((string) $r->content, true) ?: [];

                return $this->node('Evidence', (string) $r->id,
                    (string) ($content['issue'] ?? 'Observation'), 0, [
                        ['label' => 'Source', 'value' => (string) $r->source],
                    ]);
            })->all();
    }

    private function capabilityNodes(string $search, int $limit, int $offset): array
    {
        if (! SchemaCache::hasTable('hpbrain_capabilities')) {
            return [];
        }

        return DB::table('hpbrain_capabilities')->where('tenant_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->offset($offset)->limit($limit)->get(['id', 'name', 'category'])
            ->map(fn ($r) => $this->node('Capability', (string) $r->id, (string) $r->name, 0, [
                ['label' => 'Category', 'value' => (string) ($r->category ?: 'Unspecified')],
            ]))->all();
    }

    /* --------------------------------------------------------- expansions */

    private function expandOrganization(): array
    {
        return $this->expansion($this->organizationNode(), array_values(array_filter([
            $this->edge('has_department', 'Department', $this->departmentNodes('', self::PAGE, 0), $this->occupiedDepartments()),
            $this->edge('contains', 'Class', $this->classNodes('', self::PAGE, 0), $this->activeClasses()),
            $this->edge('employs', 'Person', $this->personNodes('', self::PAGE, 0), $this->lmsCount('tbluser')),
            $this->edge('has_finding', 'Signal', $this->signalNodes('', self::PAGE, 0), $this->brainCount('hpbrain_signals')),
        ])));
    }

    private function expandDepartment(string $id, int $offset): array
    {
        if (! SchemaCache::hasTable('hrms_departments')) {
            return ['available' => false, 'reason' => 'This LMS records no departments.'];
        }

        $row = $this->lmsDepartments()->where('hrms_departments.id', $id)->first();
        if (! $row) {
            return ['available' => false, 'reason' => 'No such department in this institute.'];
        }

        $staffTotal = SchemaCache::hasTable('tbluser') && SchemaCache::hasTable('tbluserprofilemaster')
            ? (int) $this->lmsPeople()->where('tbluser.department_id', $id)->count() : 0;

        $staff = SchemaCache::hasTable('tbluser') && SchemaCache::hasTable('tbluserprofilemaster')
            ? $this->lmsPeople()->where('tbluser.department_id', $id)
                ->orderBy('tbluser.first_name')->offset($offset)->limit(self::PAGE)
                ->get(['tbluser.id', 'tbluser.first_name', 'tbluser.last_name', 'tbluser.email', 'tbluser.status', 'tbluser.last_login'])
                ->map(fn ($r) => $this->node('Person', (string) $r->id,
                    trim($r->first_name.' '.$r->last_name) ?: ('Staff '.$r->id), 0, [
                        ['label' => 'Status', 'value' => ((int) $r->status) === 1 ? 'Active' : 'Inactive'],
                        ['label' => 'Signed in', 'value' => empty($r->last_login) ? 'Never' : 'Yes'],
                    ], (string) ($r->email ?: '')))->all()
            : [];

        $head = ! empty($row->head_user_id) && SchemaCache::hasTable('tbluser')
            ? DB::table('tbluser')->where('sub_institute_id', $this->tenantId)
                ->where('id', $row->head_user_id)->first(['id', 'first_name', 'last_name'])
            : null;

        $node = $this->node('Department', (string) $row->id, (string) $row->department, $staffTotal, [
            ['label' => 'Staff', 'value' => number_format($staffTotal)],
            ['label' => 'Head', 'value' => $head ? trim($head->first_name.' '.$head->last_name) : 'None assigned'],
            ['label' => 'Written remit', 'value' => empty($row->description) ? 'No' : 'Yes'],
        ]);

        return $this->expansion($node, array_values(array_filter([
            $head ? $this->edge('headed_by', 'Person', [
                $this->node('Person', (string) $head->id, trim($head->first_name.' '.$head->last_name), 0),
            ], 1) : null,
            $this->edge('employs', 'Person', $staff, $staffTotal, $offset),
            $this->edge('has_finding', 'Signal', $this->signalsAbout('OrganizationUnit', $id), null),
        ])));
    }

    private function expandPerson(string $id, int $offset): array
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return ['available' => false, 'reason' => 'This LMS records no staff.'];
        }

        $row = $this->lmsPeople()->where('tbluser.id', $id)->first([
            'tbluser.id', 'tbluser.first_name', 'tbluser.last_name', 'tbluser.email',
            'tbluser.mobile', 'tbluser.status', 'tbluser.last_login', 'tbluser.department_id',
            'tbluser.employee_no', 'tbluser.jobtitle_id', 'tbluser.occupation',
        ]);
        if (! $row) {
            return ['available' => false, 'reason' => 'No such staff member in this institute.'];
        }

        $department = ! empty($row->department_id) && SchemaCache::hasTable('hrms_departments')
            ? $this->lmsDepartments()->where('hrms_departments.id', $row->department_id)
                ->first(['hrms_departments.id', 'hrms_departments.department']) : null;

        $classes = SchemaCache::hasTable('class_teacher') && SchemaCache::hasTable('standard')
            ? DB::table('class_teacher as ct')->join('standard as s', 's.id', '=', 'ct.standard_id')
                ->where('ct.sub_institute_id', $this->tenantId)->where('ct.teacher_id', $id)
                ->distinct()->limit(self::PAGE)->get(['s.id', 's.name'])
                ->map(fn ($r) => $this->node('Class', (string) $r->id, $this->className($r->name), 0))->all()
            : [];

        $node = $this->node('Person', (string) $row->id,
            trim($row->first_name.' '.$row->last_name) ?: ('Staff '.$row->id),
            count($classes) + ($department ? 1 : 0), [
                ['label' => 'Department', 'value' => (string) ($department->department ?? 'None assigned')],
                ['label' => 'Status', 'value' => ((int) $row->status) === 1 ? 'Active' : 'Inactive'],
                ['label' => 'Signed in', 'value' => empty($row->last_login) ? 'Never' : 'Yes'],
            ], (string) ($row->email ?: ''));

        return $this->expansion($node, array_values(array_filter([
            $department ? $this->edge('works_in', 'Department', [
                $this->node('Department', (string) $department->id, (string) $department->department, 0),
            ], 1) : null,
            $classes ? $this->edge('teaches', 'Class', $classes, count($classes)) : null,
            $this->edge('has_finding', 'Signal', $this->signalsAbout('Person', $id), null),
        ])));
    }

    private function expandClass(string $id, int $offset): array
    {
        if (! SchemaCache::hasTable('attendance_student')) {
            return ['available' => false, 'reason' => 'This LMS records no attendance, so classes have no members.'];
        }

        $name = SchemaCache::hasTable('standard') ? DB::table('standard')->where('id', $id)->value('name') : null;

        $totals = DB::table('attendance_student')->where('sub_institute_id', $this->tenantId)->where('standard_id', $id)
            ->selectRaw('COUNT(*) as marks, SUM(attendance_code = "P") as present, COUNT(DISTINCT student_id) as students')->first();

        $marks = (int) ($totals->marks ?? 0);
        $rate = $marks > 0 ? round(((int) $totals->present) / $marks * 100, 1) : 0;

        $students = DB::table('attendance_student as a')
            ->leftJoin('tblstudent as st', 'st.id', '=', 'a.student_id')
            ->where('a.sub_institute_id', $this->tenantId)->where('a.standard_id', $id)
            ->when(SchemaCache::hasColumn('tblstudent', 'status'), fn ($q) => $q->where('st.status', 1))
            ->selectRaw('a.student_id, st.first_name, st.last_name, st.enrollment_no')
            ->selectRaw('COUNT(*) as marks, SUM(a.attendance_code = "P") as present, SUM(a.attendance_code = "A") as absent')
            ->groupBy('a.student_id', 'st.first_name', 'st.last_name', 'st.enrollment_no')
            ->orderByDesc('absent')->offset($offset)->limit(self::PAGE)->get()
            ->map(function ($r) {
                $m = (int) $r->marks;
                $studentRate = $m > 0 ? round(((int) $r->present) / $m * 100, 1) : 0;

                return $this->node('Student', (string) $r->student_id,
                    trim(($r->first_name ?? '').' '.($r->last_name ?? '')) ?: ('Student '.$r->student_id), $m, [
                        ['label' => 'Attendance', 'value' => self::num($studentRate).'%'],
                        ['label' => 'Absences', 'value' => (string) (int) $r->absent],
                    ], (string) ($r->enrollment_no ?? ''));
            })->all();

        $teachers = SchemaCache::hasTable('class_teacher') && SchemaCache::hasTable('tbluser')
            ? DB::table('class_teacher as ct')->join('tbluser as u', 'u.id', '=', 'ct.teacher_id')
                ->where('ct.sub_institute_id', $this->tenantId)->where('ct.standard_id', $id)
                ->when(SchemaCache::hasColumn('tbluser', 'status'), fn ($q) => $q->where('u.status', 1))
                ->distinct()->limit(20)->get(['u.id', 'u.first_name', 'u.last_name'])
                ->map(fn ($r) => $this->node('Person', (string) $r->id, trim($r->first_name.' '.$r->last_name), 0))->all()
            : [];

        $node = $this->node('Class', $id, $this->className($name ?? $id), (int) ($totals->students ?? 0), [
            ['label' => 'Students', 'value' => number_format((int) ($totals->students ?? 0))],
            ['label' => 'Attendance', 'value' => self::num($rate).'%'],
            ['label' => 'Marks recorded', 'value' => number_format($marks)],
        ]);

        return $this->expansion($node, array_values(array_filter([
            $teachers ? $this->edge('taught_by', 'Person', $teachers, count($teachers)) : null,
            $this->edge('enrolls', 'Student', $students, (int) ($totals->students ?? 0), $offset),
        ])));
    }

    private function expandStudent(string $id, int $offset): array
    {
        $intelligence = (new EntityIntelligence($this->tenantId))->student($id);
        if (! ($intelligence['available'] ?? false)) {
            return ['available' => false, 'reason' => $intelligence['reason'] ?? 'No such student.'];
        }

        $classes = SchemaCache::hasTable('attendance_student') && SchemaCache::hasTable('standard')
            ? DB::table('attendance_student as a')->join('standard as s', 's.id', '=', 'a.standard_id')
                ->where('a.sub_institute_id', $this->tenantId)->where('a.student_id', $id)
                ->selectRaw('s.id, s.name, COUNT(*) as marks')->groupBy('s.id', 's.name')
                ->orderByDesc('marks')->limit(self::PAGE)->get()
                ->map(fn ($r) => $this->node('Class', (string) $r->id, $this->className($r->name), (int) $r->marks, [
                    ['label' => 'Marks', 'value' => number_format((int) $r->marks)],
                ]))->all()
            : [];

        $node = $this->node('Student', $id, $intelligence['name'], count($classes),
            array_map(fn ($m) => ['label' => $m['label'], 'value' => $m['value']], $intelligence['metrics']),
            $intelligence['enrollmentNo'] ?: null);

        $expansion = $this->expansion($node, array_values(array_filter([
            $classes ? $this->edge('enrolled_in', 'Class', $classes, count($classes)) : null,
        ])));
        $expansion['intelligence'] = $intelligence;

        return $expansion;
    }

    private function expandSubject(string $id, int $offset): array
    {
        if (! SchemaCache::hasTable('subject')) {
            return ['available' => false, 'reason' => 'This LMS records no subjects.'];
        }

        $row = DB::table('subject')->where('sub_institute_id', $this->tenantId)->where('id', $id)->first();
        if (! $row) {
            return ['available' => false, 'reason' => 'No such subject in this institute.'];
        }

        $homework = SchemaCache::hasTable('homework')
            ? DB::table('homework')->where('sub_institute_id', $this->tenantId)->where('subject_id', $id)
                ->selectRaw('COUNT(*) as total, SUM(completion_status = "Y") as submitted')->first()
            : null;

        $total = (int) ($homework->total ?? 0);
        $rate = $total > 0 ? round(((int) $homework->submitted) / $total * 100, 1) : null;

        $node = $this->node('Subject', $id, (string) $row->subject_name, $total, array_values(array_filter([
            ['label' => 'Homework set', 'value' => number_format($total)],
            $rate !== null ? ['label' => 'Submission rate', 'value' => self::num($rate).'%'] : null,
        ])), (string) ($row->subject_code ?: ''));

        return $this->expansion($node, []);
    }

    private function expandSignal(string $id): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return ['available' => false, 'reason' => 'The Brain signal store is not provisioned.'];
        }

        $row = DB::table('hpbrain_signals')->where('tenant_id', $this->tenantId)->where('id', $id)->first();
        if (! $row) {
            return ['available' => false, 'reason' => 'No such finding.'];
        }

        $card = Narrative::forSignal(array_merge((array) $row, [
            'metadata' => json_decode((string) $row->metadata, true) ?: [],
        ]));

        $evidence = SchemaCache::hasTable('hpbrain_evidence')
            ? DB::table('hpbrain_evidence')->where('tenant_id', $this->tenantId)->where('signal_id', $id)
                ->orderBy('ledger_sequence')->limit(self::PAGE)->get()
                ->map(function ($r) {
                    $content = json_decode((string) $r->content, true) ?: [];

                    return $this->node('Evidence', (string) $r->id, (string) ($content['issue'] ?? 'Observation'), 0, [
                        ['label' => 'Source', 'value' => (string) $r->source],
                    ]);
                })->all()
            : [];

        $cases = SchemaCache::hasTable('hpbrain_cases')
            ? DB::table('hpbrain_cases')->where('tenant_id', $this->tenantId)->where('signal_id', $id)->limit(10)->get()
                ->map(fn ($r) => $this->node('Case', (string) $r->id, (string) $r->title, 0, [
                    ['label' => 'Status', 'value' => ucfirst((string) $r->status)],
                ]))->all()
            : [];

        $node = $this->node('Signal', $id, $card['title'], count($evidence) + count($cases), [
            ['label' => 'Severity', 'value' => $card['severityLabel']],
            ['label' => 'Confidence', 'value' => $card['confidence']['band']],
            ['label' => 'Owner', 'value' => $card['owner']],
        ], $card['headline']['value'] ?? null);

        $expansion = $this->expansion($node, array_values(array_filter([
            $evidence ? $this->edge('supported_by', 'Evidence', $evidence, count($evidence)) : null,
            $cases ? $this->edge('opened_case', 'Case', $cases, count($cases)) : null,
        ])));
        $expansion['finding'] = $card;

        return $expansion;
    }

    private function expandCase(string $id): array
    {
        if (! SchemaCache::hasTable('hpbrain_cases')) {
            return ['available' => false, 'reason' => 'The Brain case store is not provisioned.'];
        }

        $row = DB::table('hpbrain_cases')->where('tenant_id', $this->tenantId)->where('id', $id)->first();
        if (! $row) {
            return ['available' => false, 'reason' => 'No such case.'];
        }

        $steps = SchemaCache::hasTable('hpbrain_reasoning_steps')
            ? DB::table('hpbrain_reasoning_steps')->where('tenant_id', $this->tenantId)->where('case_id', $id)
                ->orderBy('step_order')->get() : collect();

        $recommendations = ($steps->isNotEmpty() && SchemaCache::hasTable('hpbrain_recommendations'))
            ? DB::table('hpbrain_recommendations')->where('tenant_id', $this->tenantId)
                ->whereIn('reasoning_step_id', $steps->pluck('id'))->limit(10)->get()
                ->map(fn ($r) => $this->node('Recommendation', (string) $r->id, (string) $r->title, 0, [
                    ['label' => 'Priority', 'value' => ucfirst((string) $r->priority)],
                    ['label' => 'Status', 'value' => ucfirst((string) $r->status)],
                ]))->all()
            : [];

        $node = $this->node('Case', $id, (string) $row->title, count($recommendations), [
            ['label' => 'Status', 'value' => ucfirst((string) $row->status)],
            ['label' => 'Reasoning steps', 'value' => (string) $steps->count()],
        ]);

        $expansion = $this->expansion($node, array_values(array_filter([
            $recommendations ? $this->edge('led_to', 'Recommendation', $recommendations, count($recommendations)) : null,
        ])));
        $expansion['reasoning'] = $steps->map(fn ($s) => [
            'order' => (int) $s->step_order,
            'description' => (string) $s->description,
        ])->all();

        return $expansion;
    }

    private function expandRecommendation(string $id): array
    {
        if (! SchemaCache::hasTable('hpbrain_recommendations')) {
            return ['available' => false, 'reason' => 'The Brain recommendation store is not provisioned.'];
        }

        $row = DB::table('hpbrain_recommendations')->where('tenant_id', $this->tenantId)->where('id', $id)->first();
        if (! $row) {
            return ['available' => false, 'reason' => 'No such recommendation.'];
        }

        $decisions = SchemaCache::hasTable('hpbrain_decisions')
            ? DB::table('hpbrain_decisions')->where('tenant_id', $this->tenantId)->where('recommendation_id', $id)
                ->limit(10)->get()
                ->map(fn ($r) => $this->node('Decision', (string) $r->id, (string) ($r->explanation ?: 'Decision'), 0, [
                    ['label' => 'Status', 'value' => ucfirst((string) $r->status)],
                ]))->all()
            : [];

        $node = $this->node('Recommendation', $id, (string) $row->title, count($decisions), [
            ['label' => 'Priority', 'value' => ucfirst((string) $row->priority)],
            ['label' => 'Status', 'value' => ucfirst((string) $row->status)],
            ['label' => 'Confidence', 'value' => Narrative::confidenceBand((float) $row->confidence)],
        ]);

        $expansion = $this->expansion($node, array_values(array_filter([
            $decisions ? $this->edge('decided_by', 'Decision', $decisions, count($decisions)) : null,
        ])));
        $expansion['detail'] = ['description' => (string) $row->description, 'impact' => (string) $row->impact];

        return $expansion;
    }

    private function expandDecision(string $id): array
    {
        if (! SchemaCache::hasTable('hpbrain_decisions')) {
            return ['available' => false, 'reason' => 'The Brain decision store is not provisioned.'];
        }

        $row = DB::table('hpbrain_decisions')->where('tenant_id', $this->tenantId)->where('id', $id)->first();
        if (! $row) {
            return ['available' => false, 'reason' => 'No such decision.'];
        }

        $who = SchemaCache::hasTable('tbluser')
            ? DB::table('tbluser')->where('id', $row->decided_by)->first(['id', 'first_name', 'last_name']) : null;

        $node = $this->node('Decision', $id, (string) ($row->explanation ?: 'Decision'), $who ? 1 : 0, [
            ['label' => 'Status', 'value' => ucfirst((string) $row->status)],
            ['label' => 'Decided by', 'value' => $who ? trim($who->first_name.' '.$who->last_name) : (string) $row->decided_by],
            ['label' => 'Executor', 'value' => ucfirst((string) $row->executor_type)],
        ]);

        $expansion = $this->expansion($node, array_values(array_filter([
            $who ? $this->edge('decided_by', 'Person', [
                $this->node('Person', (string) $who->id, trim($who->first_name.' '.$who->last_name), 0),
            ], 1) : null,
        ])));
        $expansion['detail'] = ['rationale' => (string) $row->rationale];

        return $expansion;
    }

    /* ------------------------------------------------------------- helpers */

    private function expansion(array $node, array $edges): array
    {
        return [
            'available' => true,
            'node' => $node,
            'edges' => $edges,
            'families' => GraphVocabulary::RELATIONSHIP_FAMILIES,
        ];
    }

    /**
     * One edge, carrying the clause that produced it.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function edge(string $type, string $targetLabel, array $nodes, ?int $total, int $offset = 0): ?array
    {
        if ($nodes === []) {
            return null;
        }

        [$label, $family, $provenance] = GraphVocabulary::relationship($type);

        return [
            'type' => $type,
            'label' => $label,
            'family' => $family,
            'provenance' => $provenance,
            'targetLabel' => $targetLabel,
            'total' => $total ?? count($nodes),
            'shown' => count($nodes),
            'offset' => $offset,
            'hasMore' => $total !== null && ($offset + count($nodes)) < $total,
            'nodes' => $nodes,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function signalsAbout(string $entityType, string $entityId): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        return DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->where('related_entity_type', $entityType)
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->limit(6)->get()
            ->map(function ($r) {
                $card = Narrative::forSignal(array_merge((array) $r, [
                    'metadata' => json_decode((string) $r->metadata, true) ?: [],
                ]));

                return $this->node('Signal', (string) $r->id, $card['title'], 0, [
                    ['label' => 'Severity', 'value' => $card['severityLabel']],
                ], $card['headline']['value'] ?? null);
            })->all();
    }

    private function className($name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'Unnamed class';
        }

        return is_numeric($name) ? 'Class '.$name : $name;
    }

    private function occupiedDepartments(): int
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return 0;
        }

        if (! SchemaCache::hasTable('hrms_departments')) {
            return 0;
        }

        // See LmsQueryScope: department_id is not tenant-scoped in this schema,
        // so the count goes through the department row it names.
        return (int) $this->lmsPeople()
            ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
            ->where('d.sub_institute_id', $this->tenantId)
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('d.status', 1))
            ->distinct()->count('d.id');
    }

    private function activeClasses(): int
    {
        if (! SchemaCache::hasTable('attendance_student')) {
            return 0;
        }

        return (int) DB::table('attendance_student')->where('sub_institute_id', $this->tenantId)
            ->whereNotNull('standard_id')->distinct()->count('standard_id');
    }

    private function brainCount(string $table): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $this->tenantId)->count();
    }

    private static function num($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    }
}
