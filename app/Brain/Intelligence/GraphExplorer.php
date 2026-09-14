<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\LmsOrganization;
use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The organization as a graph, built from vivek_erp's own foreign keys.
 *
 * EVERY EDGE IS A REAL JOIN. Department→Staff is tbluser.department_id;
 * Class→Student is attendance_student.standard_id; Class→Teacher is
 * class_teacher.teacher_id. Nothing is inferred by name similarity and nothing
 * is added to make the picture look connected — a school whose data has no
 * teacher attribution genuinely has no Class→Teacher edges, and showing some
 * anyway would misrepresent the one thing this screen exists to reveal.
 *
 * THE GRAPH IS EXPANDED, NOT DUMPED. Returning 3,438 student nodes produces a
 * hairball nobody can read and a payload nobody should download. So the API
 * returns one node's immediate neighbourhood: the entity, what it connects to,
 * and the counts on each edge. Exploration happens by walking, which is also how
 * a person actually asks questions of an organization chart.
 *
 * NODES CARRY THEIR INTELLIGENCE. Selecting a department shows its headcount and
 * its open findings, not just its id — the graph is a way into the intelligence,
 * not a decoration beside it.
 */
final class GraphExplorer
{
    /**
     * The graph counts the SAME population the Foundation screens count.
     *
     * Before this, the root node said "Staff 556" from a bare tbluser count
     * while Foundation said 120 from the profile-master join, and "Students"
     * included withdrawn records the student roll excludes.
     */
    use LmsQueryScope;

    /**
     * $organizationLabel is the name the Organization node carries. The caller
     * passes it because only the controller knows the signed-in user, and the
     * Brain shows that user's `tbluser.user_name` as the organization's name.
     * Omitted, it falls back to the institute's own name.
     */
    public function __construct(
        private readonly string $tenantId,
        ?string $syear = null,
        private readonly ?string $organizationLabel = null,
    ) {
        $this->syear = $syear;
    }

    /**
     * The entry points: what kinds of thing this school's data actually has.
     *
     * @return array<int, array<string, mixed>>
     */
    public function roots(): array
    {
        $types = [
            ['type' => 'organization', 'label' => 'Organization', 'count' => 1],
            // Named for what it actually counts: expanding this root walks the
            // Department -> Staff edge, so departments with nobody in them have
            // no edge to walk and are not offered here.
            ['type' => 'department', 'label' => 'Departments with staff', 'count' => $this->occupiedDepartments()],
            ['type' => 'class', 'label' => 'Classes', 'count' => $this->activeClasses()],
            ['type' => 'person', 'label' => 'Staff', 'count' => $this->lmsCount('tbluser')],
            ['type' => 'student', 'label' => 'Students', 'count' => $this->lmsCount('tblstudent')],
            ['type' => 'subject', 'label' => 'Subjects', 'count' => $this->count('subject')],
        ];

        return array_values(array_filter($types, fn ($t) => $t['count'] > 0));
    }

    /**
     * Top-level nodes of one type, most connected first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nodes(string $type, string $search = '', int $limit = 50): array
    {
        return match ($type) {
            'department' => $this->departmentNodes($search, $limit),
            'class' => $this->classNodes($search, $limit),
            'person' => $this->personNodes($search, $limit),
            'student' => $this->studentNodes($search, $limit),
            'subject' => $this->subjectNodes($search, $limit),
            'organization' => [$this->organizationNode()],
            default => [],
        };
    }

    /**
     * One node and everything it connects to.
     *
     * @return array<string, mixed>
     */
    public function expand(string $type, string $id): array
    {
        return match ($type) {
            'department' => $this->expandDepartment($id),
            'class' => $this->expandClass($id),
            'person' => $this->expandPerson($id),
            'student' => $this->expandStudent($id),
            'subject' => $this->expandSubject($id),
            'organization' => $this->expandOrganization(),
            default => ['available' => false, 'reason' => 'That kind of node is not in this graph.'],
        };
    }

    /* ------------------------------------------------------------ node lists */

    private function organizationNode(): array
    {
        return [
            'type' => 'organization',
            'id' => $this->tenantId,
            'label' => $this->organizationLabel ?: LmsOrganization::instituteNameFor($this->tenantId),
            'degree' => $this->occupiedDepartments() + $this->activeClasses(),
            'metrics' => [
                ['label' => 'Departments with staff', 'value' => number_format($this->occupiedDepartments())],
                ['label' => 'Staff', 'value' => number_format($this->lmsCount('tbluser'))],
                ['label' => 'Students', 'value' => number_format($this->lmsCount('tblstudent'))],
            ],
        ];
    }

    private function departmentNodes(string $search, int $limit): array
    {
        if (! SchemaCache::hasTable('hrms_departments') || ! SchemaCache::hasTable('tbluser')) {
            return [];
        }

        return $this->lmsPeople()
            ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
            ->where('d.sub_institute_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('d.department', 'like', '%'.$search.'%'))
            ->selectRaw('d.id, d.department, d.head_user_id, COUNT(tbluser.id) as headcount')
            ->groupBy('d.id', 'd.department', 'd.head_user_id')
            ->orderByDesc('headcount')->limit($limit)->get()
            ->map(fn ($r) => [
                'type' => 'department',
                'id' => (string) $r->id,
                'label' => (string) $r->department,
                'degree' => (int) $r->headcount,
                'metrics' => [
                    ['label' => 'Staff', 'value' => number_format((int) $r->headcount)],
                    ['label' => 'Head', 'value' => $r->head_user_id ? 'Assigned' : 'None'],
                ],
            ])->all();
    }

    private function classNodes(string $search, int $limit): array
    {
        if (! SchemaCache::hasTable('attendance_student') || ! SchemaCache::hasTable('standard')) {
            return [];
        }

        return $this->lmsAttendance('a')
            ->join('standard as s', 's.id', '=', 'a.standard_id')
            ->when($search !== '', fn ($q) => $q->where('s.name', 'like', '%'.$search.'%'))
            ->selectRaw('a.standard_id, s.name, s.short_name, COUNT(DISTINCT a.student_id) as students, COUNT(*) as marks, SUM(a.attendance_code = "P") as present')
            ->groupBy('a.standard_id', 's.name', 's.short_name')
            ->orderByDesc('students')->limit($limit)->get()
            ->map(function ($r) {
                $marks = (int) $r->marks;
                $rate = $marks > 0 ? round(((int) $r->present) / $marks * 100, 1) : 0;

                return [
                    'type' => 'class',
                    'id' => (string) $r->standard_id,
                    'label' => is_numeric($r->name) ? 'Class '.$r->name : (string) $r->name,
                    'degree' => (int) $r->students,
                    'metrics' => [
                        ['label' => 'Students', 'value' => number_format((int) $r->students)],
                        ['label' => 'Attendance', 'value' => self::num($rate).'%'],
                    ],
                ];
            })->all();
    }

    private function personNodes(string $search, int $limit): array
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return [];
        }

        return $this->lmsPeople()
            ->leftJoin('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($i) => $i
                ->where('tbluser.first_name', 'like', '%'.$search.'%')
                ->orWhere('tbluser.last_name', 'like', '%'.$search.'%')
                ->orWhere('tbluser.email', 'like', '%'.$search.'%')))
            ->orderBy('tbluser.first_name')->limit($limit)
            ->get(['tbluser.id', 'tbluser.first_name', 'tbluser.last_name', 'tbluser.email', 'd.department'])
            ->map(fn ($r) => [
                'type' => 'person',
                'id' => (string) $r->id,
                'label' => trim($r->first_name.' '.$r->last_name) ?: ('Staff '.$r->id),
                'degree' => $r->department ? 1 : 0,
                'metrics' => array_values(array_filter([
                    $r->department ? ['label' => 'Department', 'value' => (string) $r->department] : null,
                    $r->email ? ['label' => 'Email', 'value' => (string) $r->email] : null,
                ])),
            ])->all();
    }

    private function studentNodes(string $search, int $limit): array
    {
        if (! SchemaCache::hasTable('tblstudent')) {
            return [];
        }

        return $this->lmsStudents()
            ->when($search !== '', fn ($q) => $q->where(fn ($i) => $i
                ->where('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere('enrollment_no', 'like', '%'.$search.'%')))
            ->orderBy('first_name')->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'enrollment_no', 'admission_year'])
            ->map(fn ($r) => [
                'type' => 'student',
                'id' => (string) $r->id,
                'label' => trim($r->first_name.' '.$r->last_name) ?: ('Student '.$r->id),
                'degree' => 0,
                'metrics' => array_values(array_filter([
                    $r->enrollment_no ? ['label' => 'Enrolment', 'value' => (string) $r->enrollment_no] : null,
                    $r->admission_year ? ['label' => 'Admitted', 'value' => (string) $r->admission_year] : null,
                ])),
            ])->all();
    }

    private function subjectNodes(string $search, int $limit): array
    {
        if (! SchemaCache::hasTable('subject')) {
            return [];
        }

        $homework = SchemaCache::hasTable('homework')
            ? $this->lmsHomework()
                ->selectRaw('subject_id, COUNT(*) as total')->groupBy('subject_id')->pluck('total', 'subject_id')
            : collect();

        return DB::table('subject')
            ->where('sub_institute_id', $this->tenantId)
            ->when($search !== '', fn ($q) => $q->where('subject_name', 'like', '%'.$search.'%'))
            ->orderBy('subject_name')->limit($limit)
            ->get(['id', 'subject_name', 'subject_code'])
            ->map(fn ($r) => [
                'type' => 'subject',
                'id' => (string) $r->id,
                // Some rows carry only a code. A node labelled with the empty
                // string is unclickable and unreadable, so fall through.
                'label' => self::subjectLabel($r),
                'degree' => (int) ($homework[$r->id] ?? 0),
                'metrics' => [
                    ['label' => 'Homework set', 'value' => number_format((int) ($homework[$r->id] ?? 0))],
                ],
            ])->all();
    }

    /** subject_name, else subject_code, else something a person can still click. */
    private static function subjectLabel(object $row): string
    {
        $name = trim((string) ($row->subject_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($row->subject_code ?? ''));

        return $code !== '' ? $code : 'Subject '.$row->id;
    }

    /* -------------------------------------------------------------- expanders */

    private function expandOrganization(): array
    {
        return [
            'available' => true,
            'node' => $this->organizationNode(),
            'edges' => array_values(array_filter([
                $this->edge('Departments with staff', 'department', $this->departmentNodes('', 12), $this->occupiedDepartments()),
                $this->edge('Classes with attendance', 'class', $this->classNodes('', 12), $this->activeClasses()),
                $this->edge('Staff', 'person', $this->personNodes('', 12), $this->lmsCount('tbluser')),
            ])),
            'signals' => $this->signalsFor(null, null),
        ];
    }

    private function expandDepartment(string $id): array
    {
        if (! SchemaCache::hasTable('hrms_departments')) {
            return ['available' => false, 'reason' => 'This LMS records no departments.'];
        }

        $department = DB::table('hrms_departments')
            ->where('sub_institute_id', $this->tenantId)->where('id', $id)->first();

        if (! $department) {
            return ['available' => false, 'reason' => 'No such department in this institute.'];
        }

        // The true headcount, counted before the page is capped: an edge that
        // reports count($staff) tells a 200-person department it has 60 people
        // because that is how many nodes fit on the page.
        $staffTotal = SchemaCache::hasTable('tbluser')
            ? (int) $this->lmsPeople()->where('tbluser.department_id', $id)->count()
            : 0;

        $staff = SchemaCache::hasTable('tbluser')
            ? $this->lmsPeople()->where('tbluser.department_id', $id)
                ->orderBy('tbluser.first_name')->limit(60)
                ->get(['tbluser.id', 'tbluser.first_name', 'tbluser.last_name', 'tbluser.email', 'tbluser.status', 'tbluser.last_login'])
                ->map(fn ($r) => [
                    'type' => 'person',
                    'id' => (string) $r->id,
                    'label' => trim($r->first_name.' '.$r->last_name) ?: ('Staff '.$r->id),
                    'degree' => 0,
                    'metrics' => array_values(array_filter([
                        ['label' => 'Status', 'value' => ((int) $r->status) === 1 ? 'Active' : 'Inactive'],
                        ['label' => 'Signed in', 'value' => empty($r->last_login) ? 'Never' : 'Yes'],
                    ])),
                ])->all()
            : [];

        $head = ! empty($department->head_user_id) && SchemaCache::hasTable('tbluser')
            ? DB::table('tbluser')->where('sub_institute_id', $this->tenantId)
                ->where('id', $department->head_user_id)->first(['id', 'first_name', 'last_name'])
            : null;

        return [
            'available' => true,
            'node' => [
                'type' => 'department',
                'id' => (string) $department->id,
                'label' => (string) $department->department,
                'degree' => $staffTotal,
                'metrics' => array_values(array_filter([
                    ['label' => 'Staff', 'value' => number_format($staffTotal)],
                    ['label' => 'Head', 'value' => $head ? trim($head->first_name.' '.$head->last_name) : 'None assigned'],
                    ['label' => 'Remit', 'value' => empty($department->description) ? 'Not written' : 'Written'],
                ])),
            ],
            'edges' => array_values(array_filter([
                $head ? $this->edge('Head of department', 'person', [[
                    'type' => 'person', 'id' => (string) $head->id,
                    'label' => trim($head->first_name.' '.$head->last_name), 'degree' => 0, 'metrics' => [],
                ]], 1) : null,
                $this->edge('Staff in this department', 'person', $staff, $staffTotal),
            ])),
            'signals' => $this->signalsFor('OrganizationUnit', (string) $department->id),
        ];
    }

    private function expandClass(string $id): array
    {
        if (! SchemaCache::hasTable('attendance_student')) {
            return ['available' => false, 'reason' => 'This LMS records no attendance, so classes have no members to show.'];
        }

        $name = SchemaCache::hasTable('standard') ? DB::table('standard')->where('id', $id)->value('name') : null;
        $label = $name === null ? ('Class '.$id) : (is_numeric($name) ? 'Class '.$name : (string) $name);

        $students = $this->lmsAttendance('a')
            ->leftJoin('tblstudent as st', 'st.id', '=', 'a.student_id')->where('a.standard_id', $id)
            ->selectRaw('a.student_id, st.first_name, st.last_name, st.enrollment_no')
            ->selectRaw('COUNT(*) as marks, SUM(a.attendance_code = "P") as present, SUM(a.attendance_code = "A") as absent')
            ->groupBy('a.student_id', 'st.first_name', 'st.last_name', 'st.enrollment_no')
            ->orderByDesc('absent')->limit(60)->get()
            ->map(function ($r) {
                $marks = (int) $r->marks;
                $rate = $marks > 0 ? round(((int) $r->present) / $marks * 100, 1) : 0;

                return [
                    'type' => 'student',
                    'id' => (string) $r->student_id,
                    'label' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')) ?: ('Student '.$r->student_id),
                    'degree' => $marks,
                    'metrics' => [
                        ['label' => 'Attendance', 'value' => self::num($rate).'%'],
                        ['label' => 'Absences', 'value' => (string) (int) $r->absent],
                    ],
                ];
            })->all();

        $teachers = SchemaCache::hasTable('class_teacher') && SchemaCache::hasTable('tbluser')
            ? DB::table('class_teacher as ct')->join('tbluser as u', 'u.id', '=', 'ct.teacher_id')
                ->where('ct.sub_institute_id', $this->tenantId)->where('ct.standard_id', $id)
                ->distinct()->limit(20)->get(['u.id', 'u.first_name', 'u.last_name'])
                ->map(fn ($r) => [
                    'type' => 'person', 'id' => (string) $r->id,
                    'label' => trim($r->first_name.' '.$r->last_name), 'degree' => 0, 'metrics' => [],
                ])->all()
            : [];

        $totals = $this->lmsAttendance()->where('standard_id', $id)
            ->selectRaw('COUNT(*) as marks, SUM(attendance_code = "P") as present')->first();
        $rate = ((int) $totals->marks) > 0 ? round(((int) $totals->present) / ((int) $totals->marks) * 100, 1) : 0;

        return [
            'available' => true,
            'node' => [
                'type' => 'class',
                'id' => $id,
                'label' => $label,
                'degree' => count($students),
                'metrics' => [
                    ['label' => 'Students', 'value' => number_format(count($students))],
                    ['label' => 'Attendance', 'value' => self::num($rate).'%'],
                    ['label' => 'Marks recorded', 'value' => number_format((int) $totals->marks)],
                ],
            ],
            'edges' => array_values(array_filter([
                $teachers ? $this->edge('Class teachers', 'person', $teachers, count($teachers)) : null,
                $this->edge('Students (most absent first)', 'student', $students, count($students)),
            ])),
            'signals' => $this->signalsFor('Student', $id),
        ];
    }

    /**
     * One subject: the homework set for it and the marks recorded against it.
     *
     * result_marks stores the subject by NAME rather than by id in this schema,
     * so the marks edge matches on the subject's own name; when the subject has
     * no name there is nothing to match and the edge is simply absent, which is
     * the truthful answer rather than a zero.
     */
    private function expandSubject(string $id): array
    {
        if (! SchemaCache::hasTable('subject')) {
            return ['available' => false, 'reason' => 'This LMS records no subjects.'];
        }

        $subject = DB::table('subject')
            ->where('sub_institute_id', $this->tenantId)->where('id', $id)
            ->first(['id', 'subject_name', 'subject_code', 'subject_type']);

        if (! $subject) {
            return ['available' => false, 'reason' => 'No such subject in this institute.'];
        }

        $label = self::subjectLabel($subject);

        $homeworkTotal = SchemaCache::hasTable('homework')
            ? (int) $this->lmsHomework()->where('subject_id', $id)->count()
            : 0;

        $classes = SchemaCache::hasTable('homework') && SchemaCache::hasTable('standard')
            ? $this->lmsHomework('h')->join('standard as s', 's.id', '=', 'h.standard_id')->where('h.subject_id', $id)
                ->selectRaw('s.id, s.name, COUNT(*) as assignments')
                ->groupBy('s.id', 's.name')->orderByDesc('assignments')->limit(20)->get()
                ->map(fn ($r) => [
                    'type' => 'class',
                    'id' => (string) $r->id,
                    'label' => is_numeric($r->name) ? 'Class '.$r->name : (string) $r->name,
                    'degree' => (int) $r->assignments,
                    'metrics' => [['label' => 'Homework set', 'value' => number_format((int) $r->assignments)]],
                ])->all()
            : [];

        $marks = [];
        $marksTotal = 0;
        $name = trim((string) ($subject->subject_name ?? ''));
        if ($name !== '' && SchemaCache::hasTable('result_marks')) {
            $row = $this->lmsMarks()->where('subject_name', $name)
                ->selectRaw('COUNT(*) as marks, COUNT(DISTINCT student_id) as students, AVG(per) as mean')
                ->first();

            $marksTotal = (int) ($row->marks ?? 0);
            if ($marksTotal > 0) {
                $marks[] = [
                    'type' => 'metric',
                    'id' => 'marks-'.$id,
                    'label' => number_format((int) $row->students).' students assessed',
                    'degree' => $marksTotal,
                    'metrics' => [
                        ['label' => 'Marks recorded', 'value' => number_format($marksTotal)],
                        ['label' => 'Mean score', 'value' => self::num((float) ($row->mean ?? 0)).'%'],
                    ],
                ];
            }
        }

        $node = [
            'type' => 'subject',
            'id' => (string) $subject->id,
            'label' => $label,
            'degree' => $homeworkTotal,
            'metrics' => array_values(array_filter([
                ['label' => 'Homework set', 'value' => number_format($homeworkTotal)],
                ['label' => 'Marks recorded', 'value' => number_format($marksTotal)],
                $subject->subject_code ? ['label' => 'Code', 'value' => (string) $subject->subject_code] : null,
            ])),
        ];

        return [
            'available' => true,
            'node' => $node,
            'edges' => array_values(array_filter([
                $classes ? $this->edge('Taught in', 'class', $classes, count($classes)) : null,
                $marks ? $this->edge('Assessment', 'metric', $marks, 1) : null,
            ])),
            'signals' => $this->signalsFor(null, null),
        ];
    }

    private function expandPerson(string $id): array
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return ['available' => false, 'reason' => 'This LMS records no staff.'];
        }

        $person = $this->lmsPeople()->where('tbluser.id', $id)->first(self::PERSON_COLUMNS);
        if (! $person) {
            return ['available' => false, 'reason' => 'No such staff member in this institute.'];
        }

        $department = ! empty($person->department_id) && SchemaCache::hasTable('hrms_departments')
            ? $this->lmsDepartments()->where('hrms_departments.id', $person->department_id)
                ->first(['hrms_departments.id', 'hrms_departments.department'])
            : null;

        $classes = SchemaCache::hasTable('class_teacher') && SchemaCache::hasTable('standard')
            ? DB::table('class_teacher as ct')->join('standard as s', 's.id', '=', 'ct.standard_id')
                ->where('ct.sub_institute_id', $this->tenantId)->where('ct.teacher_id', $id)
                ->distinct()->limit(30)->get(['s.id', 's.name'])
                ->map(fn ($r) => [
                    'type' => 'class', 'id' => (string) $r->id,
                    'label' => is_numeric($r->name) ? 'Class '.$r->name : (string) $r->name,
                    'degree' => 0, 'metrics' => [],
                ])->all()
            : [];

        return [
            'available' => true,
            'node' => [
                'type' => 'person',
                'id' => (string) $person->id,
                'label' => trim($person->first_name.' '.$person->last_name) ?: ('Staff '.$person->id),
                'degree' => count($classes) + ($department ? 1 : 0),
                'metrics' => array_values(array_filter([
                    ['label' => 'Department', 'value' => $department->department ?? 'None assigned'],
                    ['label' => 'Status', 'value' => ((int) $person->status) === 1 ? 'Active' : 'Inactive'],
                    ['label' => 'Signed in', 'value' => empty($person->last_login) ? 'Never' : 'Yes'],
                ])),
            ],
            'edges' => array_values(array_filter([
                $department ? $this->edge('Department', 'department', [[
                    'type' => 'department', 'id' => (string) $department->id,
                    'label' => (string) $department->department, 'degree' => 0, 'metrics' => [],
                ]], 1) : null,
                $classes ? $this->edge('Classes taught', 'class', $classes, count($classes)) : null,
            ])),
            'signals' => $this->signalsFor('Person', (string) $person->id),
        ];
    }

    private function expandStudent(string $id): array
    {
        $intelligence = (new EntityIntelligence($this->tenantId, $this->syear))->student($id);

        if (! ($intelligence['available'] ?? false)) {
            return ['available' => false, 'reason' => $intelligence['reason'] ?? 'No such student.'];
        }

        $classes = SchemaCache::hasTable('attendance_student') && SchemaCache::hasTable('standard')
            ? $this->lmsAttendance('a')->join('standard as s', 's.id', '=', 'a.standard_id')->where('a.student_id', $id)
                ->selectRaw('s.id, s.name, COUNT(*) as marks')->groupBy('s.id', 's.name')
                ->orderByDesc('marks')->limit(10)->get()
                ->map(fn ($r) => [
                    'type' => 'class', 'id' => (string) $r->id,
                    'label' => is_numeric($r->name) ? 'Class '.$r->name : (string) $r->name,
                    'degree' => (int) $r->marks,
                    'metrics' => [['label' => 'Marks', 'value' => number_format((int) $r->marks)]],
                ])->all()
            : [];

        return [
            'available' => true,
            'node' => [
                'type' => 'student',
                'id' => $id,
                'label' => $intelligence['name'],
                'degree' => count($classes),
                'metrics' => array_map(
                    fn ($m) => ['label' => $m['label'], 'value' => $m['value']],
                    $intelligence['metrics']
                ),
            ],
            'edges' => array_values(array_filter([
                $classes ? $this->edge('Classes', 'class', $classes, count($classes)) : null,
            ])),
            'intelligence' => $intelligence,
            'signals' => [],
        ];
    }

    /* --------------------------------------------------------------- helpers */

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function edge(string $label, string $targetType, array $nodes, int $total): ?array
    {
        if ($nodes === []) {
            return null;
        }

        return [
            'label' => $label,
            'targetType' => $targetType,
            'total' => $total,
            'shown' => count($nodes),
            'nodes' => $nodes,
        ];
    }

    /**
     * Open findings about this node, so the graph leads into the intelligence.
     *
     * @return array<int, array<string, mixed>>
     */
    private function signalsFor(?string $entityType, ?string $entityId): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        $query = DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->whereNotIn('status', ['resolved', 'dismissed']);

        if ($entityType !== null) {
            $query->where('related_entity_type', $entityType);
            if ($entityId !== null) {
                $query->where(fn ($q) => $q->where('related_entity_id', $entityId)->orWhereNull('related_entity_id'));
            }
        }

        return $query->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->limit(5)->get()
            ->map(function ($row) {
                $signal = (array) $row;
                $signal['metadata'] = json_decode((string) $row->metadata, true) ?: [];

                return Narrative::forSignal($signal);
            })->all();
    }

    /**
     * Departments of THIS institute that hold staff.
     *
     * The join back to hrms_departments is not decoration. tbluser.department_id
     * is not a scoped foreign key in this database: institute 47's staff carry
     * department ids belonging to institutes 1, 2, 3 and 5. Counting distinct
     * ids off tbluser alone therefore reported 31 departments for an institute
     * that owns none, and the listing beneath it — which does scope — came back
     * empty. Counting through the department row makes the two agree and keeps
     * another institute's department names off this screen.
     */
    private function occupiedDepartments(): int
    {
        if (! SchemaCache::hasTable('tbluser') || ! SchemaCache::hasTable('hrms_departments')) {
            return 0;
        }

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

        return (int) $this->lmsAttendance()
            ->whereNotNull('standard_id')->distinct()->count('standard_id');
    }

    /**
     * The staff columns a person node needs. Qualified because lmsPeople()
     * joins tbluserprofilemaster, which carries its own id / name / status.
     */
    private const PERSON_COLUMNS = [
        'tbluser.id', 'tbluser.first_name', 'tbluser.last_name', 'tbluser.email',
        'tbluser.mobile', 'tbluser.status', 'tbluser.last_login', 'tbluser.department_id',
        'tbluser.employee_no', 'tbluser.jobtitle_id',
    ];

    private function count(string $table): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'sub_institute_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('sub_institute_id', $this->tenantId)->count();
    }

    private static function num($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    }
}
