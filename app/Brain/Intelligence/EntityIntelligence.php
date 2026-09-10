<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Intelligence about one student, class, department or teacher.
 *
 * THE SAME SHAPE EVERY TIME, whatever the subject: a headline metric, how it
 * compares to the relevant baseline, the evidence behind it, a risk band, and
 * what to do. A principal should not have to learn a new layout to read about a
 * teacher after reading about a class.
 *
 * COMPARISON IS ALWAYS AGAINST THIS SCHOOL. A student at 76% attendance means
 * nothing until you know their class is at 91%. Every profile therefore carries
 * the peer figure it is measured against, computed from the same institute's
 * rows — never a national statistic the Brain has no access to.
 *
 * WHERE THE DATA IS THIN, THE PROFILE SAYS SO. This school has attendance for 33
 * students and marks for 9; most people in it have no measurable record at all.
 * A profile for one of them returns available:false with the reason rather than
 * a confident-looking 0%, because "no data" and "zero" are different facts and
 * conflating them is how a system starts lying about children.
 */
final class EntityIntelligence
{
    /** The same active-population definition the Foundation screens use. */
    use LmsQueryScope;

    /** Below this many observations, an individual figure is not worth quoting. */
    private const MIN_OBSERVATIONS = 3;

    public function __construct(private readonly string $tenantId, ?string $syear = null)
    {
        $this->syear = $syear;
    }

    /* --------------------------------------------------------------- student */

    /** @return array<string, mixed> */
    public function student(string $studentId): array
    {
        if (! SchemaCache::hasTable('tblstudent')) {
            return ['available' => false, 'reason' => 'This LMS records no students.'];
        }

        $student = $this->lmsStudents()->where('tblstudent.id', $studentId)->first();

        if (! $student) {
            return ['available' => false, 'reason' => 'No such student in this institute.'];
        }

        $name = trim($student->first_name.' '.($student->middle_name ?? '').' '.$student->last_name);
        $profile = [
            'available' => true,
            'id' => (string) $student->id,
            'name' => $name !== '' ? $name : ('Student '.$student->id),
            'enrollmentNo' => (string) ($student->enrollment_no ?? ''),
            'admissionYear' => $student->admission_year ?? null,
            'gender' => (string) ($student->gender ?? ''),
            'metrics' => [],
            'evidence' => [],
            'strengths' => [],
            'weaknesses' => [],
            'risks' => [],
            'recommendations' => [],
            'dataGaps' => [],
        ];

        $attendance = $this->studentAttendance($studentId);
        $marks = $this->studentMarks($studentId);
        $homework = $this->studentHomework($studentId);
        $fees = $this->studentFees($studentId);

        /* ---- attendance ---- */
        if ($attendance['available']) {
            $profile['metrics'][] = [
                'key' => 'attendance',
                'label' => 'Attendance',
                'value' => self::pct($attendance['rate']),
                'change' => $attendance['gapVsClass'],
                'changeLabel' => $attendance['className']
                    ? sprintf('vs %s%% in %s', self::num($attendance['classRate']), $attendance['className'])
                    : null,
                'band' => self::attendanceBand($attendance['rate']),
            ];
            $profile['evidence'][] = ['label' => 'Attendance', 'value' => self::pct($attendance['rate']),
                'note' => sprintf('%d of %d days present', $attendance['present'], $attendance['marks'])];
            if ($attendance['className']) {
                $profile['evidence'][] = ['label' => 'Class average', 'value' => self::pct($attendance['classRate']),
                    'note' => $attendance['className']];
            }

            if ($attendance['rate'] < 75) {
                $profile['risks'][] = [
                    'label' => 'Persistent absence',
                    'severity' => $attendance['rate'] < 60 ? 'high' : 'medium',
                    'detail' => sprintf('Attendance is %s%% across %d recorded days, with %d absences.',
                        self::num($attendance['rate']), $attendance['marks'], $attendance['absent']),
                ];
                $profile['recommendations'][] = 'Contact the guardian about attendance before the next assessment cycle.';
            } elseif ($attendance['gapVsClass'] !== null && $attendance['gapVsClass'] <= -10) {
                $profile['risks'][] = [
                    'label' => 'Below class attendance',
                    'severity' => 'medium',
                    'detail' => sprintf('Attendance is %s points below the %s average.',
                        self::num(abs($attendance['gapVsClass'])), $attendance['className']),
                ];
            }
        } else {
            $profile['dataGaps'][] = 'No attendance has been recorded for this student.';
        }

        /* ---- marks ---- */
        if ($marks['available']) {
            $profile['metrics'][] = [
                'key' => 'performance',
                'label' => 'Average score',
                'value' => self::pct($marks['mean']),
                'change' => $marks['gapVsPeers'],
                'changeLabel' => $marks['peerMean'] !== null
                    ? sprintf('vs %s%% school average', self::num($marks['peerMean'])) : null,
                'band' => self::scoreBand($marks['mean']),
            ];
            $profile['evidence'][] = ['label' => 'Average score', 'value' => self::pct($marks['mean']),
                'note' => sprintf('%d mark records', $marks['records'])];

            $profile['strengths'] = array_map(
                fn ($s) => ['label' => $s['subject'], 'value' => self::pct($s['mean'])],
                $marks['strongest']
            );
            $profile['weaknesses'] = array_map(
                fn ($s) => ['label' => $s['subject'], 'value' => self::pct($s['mean'])],
                $marks['weakest']
            );

            $pass = (float) config('brain.thresholds.pass_percentage', 40.0);
            if ($marks['mean'] < $pass) {
                $profile['risks'][] = [
                    'label' => 'Below the pass band',
                    'severity' => 'high',
                    'detail' => sprintf('Average score is %s%% against a pass mark of %s%%.',
                        self::num($marks['mean']), self::num($pass)),
                ];
                if ($marks['weakest']) {
                    $profile['recommendations'][] = sprintf('Arrange remedial support in %s.', $marks['weakest'][0]['subject']);
                }
            }
        } else {
            $profile['dataGaps'][] = 'No marks have been recorded for this student.';
        }

        /* ---- homework ---- */
        if ($homework['available']) {
            $profile['metrics'][] = [
                'key' => 'homework',
                'label' => 'Homework submitted',
                'value' => self::pct($homework['rate']),
                'change' => null,
                'changeLabel' => sprintf('%d assignments', $homework['total']),
                'band' => self::attendanceBand($homework['rate']),
            ];
            if ($homework['rate'] < 70) {
                $profile['risks'][] = [
                    'label' => 'Homework not being submitted',
                    'severity' => 'medium',
                    'detail' => sprintf('%d of %d assignments are outstanding.',
                        $homework['total'] - $homework['submitted'], $homework['total']),
                ];
            }
        }

        /* ---- fees ---- */
        if ($fees['receipts'] > 0) {
            $profile['evidence'][] = ['label' => 'Fees paid', 'value' => Narrative::money($fees['collected']),
                'note' => sprintf('%d receipt%s', $fees['receipts'], $fees['receipts'] === 1 ? '' : 's')];
        }

        /* ---- record completeness ---- */
        $missing = [];
        if (empty($student->enrollment_no)) {
            $missing[] = 'enrolment number';
        }
        if (empty($student->dob)) {
            $missing[] = 'date of birth';
        }
        if (empty($student->mobile) && empty($student->student_mobile) && empty($student->mother_mobile)) {
            $missing[] = 'contact number';
        }
        if ($missing) {
            $profile['risks'][] = [
                'label' => 'Incomplete record',
                'severity' => in_array('contact number', $missing, true) ? 'medium' : 'low',
                'detail' => 'Missing: '.implode(', ', $missing).'.',
            ];
            $profile['recommendations'][] = 'Complete the student record: '.implode(', ', $missing).'.';
        }

        $profile['risk'] = self::overallRisk($profile['risks']);
        $profile['summary'] = $this->studentSummary($profile, $attendance, $marks);

        if ($profile['metrics'] === []) {
            $profile['summary'] = 'Nothing has been recorded against this student yet — no attendance, marks or homework — so the Brain has nothing to reason over.';
        }

        return $profile;
    }

    /** @return array<string, mixed> */
    private function studentAttendance(string $studentId): array
    {
        if (! SchemaCache::hasTable('attendance_student')) {
            return ['available' => false];
        }

        $own = $this->lmsAttendance()->where('student_id', $studentId)
            ->selectRaw('COUNT(*) as marks, SUM(attendance_code = "P") as present, SUM(attendance_code = "A") as absent, MAX(standard_id) as standard_id')
            ->first();

        $marks = (int) ($own->marks ?? 0);
        if ($marks < self::MIN_OBSERVATIONS) {
            return ['available' => false];
        }

        $rate = round(((int) $own->present) / $marks * 100, 1);

        // The peer figure: the same class, excluding this student, so a small
        // class is not compared against a number they themselves dominate.
        $classRate = null;
        $className = null;
        if ($own->standard_id) {
            $peers = $this->lmsAttendance()
                ->where('standard_id', $own->standard_id)
                ->where('student_id', '!=', $studentId)
                ->selectRaw('COUNT(*) as marks, SUM(attendance_code = "P") as present')->first();

            if ((int) ($peers->marks ?? 0) >= 20) {
                $classRate = round(((int) $peers->present) / ((int) $peers->marks) * 100, 1);
            }

            $className = SchemaCache::hasTable('standard')
                ? DB::table('standard')->where('id', $own->standard_id)->value('name')
                : null;
            $className = $className !== null && is_numeric($className) ? 'Class '.$className : $className;
        }

        return [
            'available' => true,
            'marks' => $marks,
            'present' => (int) $own->present,
            'absent' => (int) $own->absent,
            'rate' => $rate,
            'classRate' => $classRate,
            'className' => $className,
            'gapVsClass' => $classRate !== null ? round($rate - $classRate, 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function studentMarks(string $studentId): array
    {
        if (! SchemaCache::hasTable('result_marks')) {
            return ['available' => false];
        }

        $own = $this->lmsMarks()->where('student_id', $studentId)
            ->selectRaw('COUNT(*) as records, AVG(per) as mean_pct')->first();

        if ((int) ($own->records ?? 0) === 0) {
            return ['available' => false];
        }

        $mean = round((float) $own->mean_pct, 1);

        $peerMean = $this->lmsMarks()
            ->where('student_id', '!=', $studentId)->avg('per');
        $peerMean = $peerMean !== null ? round((float) $peerMean, 1) : null;

        $bySubject = $this->lmsMarks()->where('student_id', $studentId)
            ->whereNotNull('subject_name')->where('subject_name', '!=', '')
            ->selectRaw('subject_name, AVG(per) as mean_pct, COUNT(*) as records')
            ->groupBy('subject_name')->get()
            ->map(fn ($r) => ['subject' => (string) $r->subject_name, 'mean' => round((float) $r->mean_pct, 1)])
            ->sortBy('mean')->values();

        return [
            'available' => true,
            'records' => (int) $own->records,
            'mean' => $mean,
            'peerMean' => $peerMean,
            'gapVsPeers' => $peerMean !== null ? round($mean - $peerMean, 1) : null,
            'weakest' => $bySubject->take(3)->all(),
            'strongest' => $bySubject->reverse()->take(3)->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function studentHomework(string $studentId): array
    {
        if (! SchemaCache::hasTable('homework')) {
            return ['available' => false];
        }

        $row = $this->lmsHomework()->where('student_id', $studentId)
            ->selectRaw('COUNT(*) as total, SUM(completion_status = "Y") as submitted')->first();

        $total = (int) ($row->total ?? 0);
        if ($total < self::MIN_OBSERVATIONS) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'total' => $total,
            'submitted' => (int) $row->submitted,
            'rate' => round(((int) $row->submitted) / $total * 100, 1),
        ];
    }

    /** @return array{receipts: int, collected: float} */
    private function studentFees(string $studentId): array
    {
        if (! SchemaCache::hasTable('fees_collect')) {
            return ['receipts' => 0, 'collected' => 0.0];
        }

        $row = $this->lmsFees()->where('student_id', $studentId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y'))
            ->selectRaw('COUNT(*) as receipts, COALESCE(SUM(amount),0) as collected')->first();

        return ['receipts' => (int) ($row->receipts ?? 0), 'collected' => (float) ($row->collected ?? 0)];
    }

    private function studentSummary(array $profile, array $attendance, array $marks): string
    {
        $parts = [];

        if ($attendance['available']) {
            $parts[] = $attendance['gapVsClass'] !== null && abs($attendance['gapVsClass']) >= 5
                ? sprintf('Attendance is %s%%, %s points %s the %s average',
                    self::num($attendance['rate']), self::num(abs($attendance['gapVsClass'])),
                    $attendance['gapVsClass'] < 0 ? 'below' : 'above', $attendance['className'])
                : sprintf('Attendance is %s%%', self::num($attendance['rate']));
        }

        if ($marks['available']) {
            $parts[] = $marks['weakest']
                ? sprintf('the average score is %s%%, weakest in %s at %s%%',
                    self::num($marks['mean']), $marks['weakest'][0]['subject'], self::num($marks['weakest'][0]['mean']))
                : sprintf('the average score is %s%%', self::num($marks['mean']));
        }

        if ($parts === []) {
            return 'No attendance or marks recorded for this student yet.';
        }

        return ucfirst(implode(', and ', $parts)).'.';
    }

    /* ----------------------------------------------------------------- class */

    /** @return array<int, array<string, mixed>> */
    public function classes(): array
    {
        $byClass = (new TrendAnalyzer($this->tenantId, $this->syear))->attendanceByClass();

        if ($byClass['classes'] === []) {
            return [];
        }

        return array_map(function ($class) use ($byClass) {
            $risks = [];
            if ($class['gapPoints'] <= -4) {
                $risks[] = sprintf('%s points below the school baseline', self::num(abs($class['gapPoints'])));
            }
            if ($class['rate'] < 80) {
                $risks[] = 'attendance under 80%';
            }

            return [
                'id' => $class['classId'],
                'name' => $class['className'],
                'attendanceRate' => $class['rate'],
                'baseline' => $byClass['baseline'],
                'gapPoints' => $class['gapPoints'],
                'students' => $class['students'],
                'absences' => $class['absent'],
                'marks' => $class['marks'],
                'band' => self::attendanceBand($class['rate']),
                'risks' => $risks,
                'summary' => sprintf('%s is at %s%% attendance against a school baseline of %s%%, across %s students.',
                    $class['className'], self::num($class['rate']), self::num($byClass['baseline']), $class['students']),
                'action' => $class['gapPoints'] <= -4
                    ? 'Review with the class teacher and contact the guardians of the most absent students.'
                    : null,
            ];
        }, $byClass['classes']);
    }

    /* ------------------------------------------------------------ department */

    /** @return array<int, array<string, mixed>> */
    public function departments(int $limit = 40): array
    {
        if (! SchemaCache::hasTable('hrms_departments') || ! SchemaCache::hasTable('tbluser')) {
            return [];
        }

        // Only departments that actually hold people. An HRMS department tree is
        // mostly taxonomy — most institutes here have an order of magnitude more
        // nodes than occupied ones — and listing every empty leaf as a
        // "department needing attention" would bury the ones that matter. The
        // Departments Foundation screen still shows the full tree; this is the
        // subset intelligence can say anything true about.
        $rows = $this->lmsPeople()
            ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
            ->where('d.sub_institute_id', $this->tenantId)
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('d.status', 1))
            ->selectRaw('d.id, d.department, d.head_user_id, d.description, d.status')
            ->selectRaw('COUNT(tbluser.id) as headcount')
            ->selectRaw('SUM(tbluser.status = 0) as inactive')
            ->selectRaw('SUM(tbluser.email IS NULL OR tbluser.email = "" OR tbluser.mobile IS NULL OR tbluser.mobile = "") as uncontactable')
            ->selectRaw('SUM(tbluser.last_login IS NULL OR tbluser.last_login = "") as never_signed_in')
            ->groupBy('d.id', 'd.department', 'd.head_user_id', 'd.description', 'd.status')
            ->orderByDesc('headcount')
            ->limit($limit)
            ->get();

        $heads = DB::table('tbluser')
            ->where('sub_institute_id', $this->tenantId)
            ->whereIn('id', $rows->pluck('head_user_id')->filter()->all())
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [(string) $u->id => trim($u->first_name.' '.$u->last_name)]);

        return $rows->map(function ($row) use ($heads) {
            $headcount = (int) $row->headcount;
            $hasHead = ! empty($row->head_user_id);
            $neverSignedIn = (int) $row->never_signed_in;

            $risks = [];
            if (! $hasHead) {
                $risks[] = ['label' => 'No head assigned', 'severity' => 'high'];
            }
            if ((int) $row->inactive > 0) {
                $risks[] = ['label' => sprintf('%d inactive staff still posted here', (int) $row->inactive), 'severity' => 'medium'];
            }
            if ($headcount > 0 && $neverSignedIn === $headcount) {
                $risks[] = ['label' => 'No one in this department has signed in', 'severity' => 'medium'];
            }
            if ((int) $row->uncontactable > 0) {
                $risks[] = ['label' => sprintf('%d staff not contactable', (int) $row->uncontactable), 'severity' => 'low'];
            }

            // Ownership dominates: a department with no head is the finding,
            // whatever else is true of it.
            $score = (int) round(
                ($hasHead ? 55 : 0)
                + (empty($row->description) ? 0 : 15)
                + ($headcount > 0 ? (1 - min(1, $neverSignedIn / $headcount)) * 20 : 0)
                + ((int) $row->inactive === 0 ? 10 : 0)
            );

            return [
                'id' => (string) $row->id,
                'name' => (string) $row->department,
                'headcount' => $headcount,
                'head' => $hasHead ? ($heads[(string) $row->head_user_id] ?? 'Assigned') : null,
                'hasRemit' => ! empty($row->description),
                'inactive' => (int) $row->inactive,
                'neverSignedIn' => $neverSignedIn,
                'score' => $score,
                'band' => HealthScores::band($score),
                'risks' => $risks,
                'summary' => sprintf('%s holds %d staff%s.%s',
                    $row->department,
                    $headcount,
                    $hasHead ? ' under '.($heads[(string) $row->head_user_id] ?? 'an assigned head') : ' with no head assigned',
                    $neverSignedIn === $headcount && $headcount > 0 ? ' None of them has signed in.' : ''),
                'action' => ! $hasHead
                    ? 'Assign a head to this department — it carries staff but has no accountable owner.'
                    : null,
                'formula' => 'Fifty-five points for having a head, twenty for staff who have signed in, fifteen for a written remit, ten for no inactive staff still posted.',
            ];
        })->all();
    }

    /* --------------------------------------------------------------- teacher */

    /**
     * Teaching-staff activity.
     *
     * THIS SCHOOL'S TEACHER DATA IS THIN AND THE PROFILE SAYS SO. Of 1,542
     * attendance marks only 52 carry a teacher id, and most homework has no
     * author. Rather than rank teachers on that, each profile reports exactly
     * what is attributable to them and flags the rest as unattributed — because
     * a performance judgement built on 3% of the record would be indefensible,
     * and teachers are the people most harmed by a system that guesses.
     *
     * @return array<string, mixed>
     */
    public function teachers(int $limit = 50): array
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return ['available' => false, 'reason' => 'This LMS records no staff.', 'teachers' => []];
        }

        $attendanceByTeacher = SchemaCache::hasTable('attendance_student')
            ? $this->lmsAttendance()
                ->whereNotNull('teacher_id')->where('teacher_id', '!=', '')
                ->selectRaw('teacher_id, COUNT(*) as marks, SUM(attendance_code = "A") as absent, COUNT(DISTINCT student_id) as students')
                ->groupBy('teacher_id')->get()->keyBy('teacher_id')
            : collect();

        $homeworkByTeacher = SchemaCache::hasTable('homework')
            ? $this->lmsHomework()
                ->whereNotNull('created_by')->where('created_by', '>', 0)
                ->selectRaw('created_by, COUNT(*) as total, SUM(completion_status = "Y") as submitted')
                ->groupBy('created_by')->get()->keyBy('created_by')
            : collect();

        $classesByTeacher = SchemaCache::hasTable('class_teacher')
            ? DB::table('class_teacher')->where('sub_institute_id', $this->tenantId)
                ->selectRaw('teacher_id, COUNT(DISTINCT standard_id) as classes')
                ->groupBy('teacher_id')->get()->keyBy('teacher_id')
            : collect();

        $ids = collect()
            ->merge($attendanceByTeacher->keys())
            ->merge($homeworkByTeacher->keys())
            ->merge($classesByTeacher->keys())
            ->map(fn ($id) => (string) $id)->unique()->filter()->values();

        // Attribution coverage — the honest headline for this whole screen.
        $totalMarks = SchemaCache::hasTable('attendance_student')
            ? (int) $this->lmsAttendance()->count() : 0;
        $attributedMarks = (int) $attendanceByTeacher->sum('marks');

        if ($ids->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'No attendance, homework or class allocation in this institute is attributed to a named teacher, so no teaching activity can be measured.',
                'teachers' => [],
                'coverage' => ['attributedMarks' => 0, 'totalMarks' => $totalMarks],
            ];
        }

        $people = DB::table('tbluser')
            ->where('sub_institute_id', $this->tenantId)->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name', 'email', 'department_id'])->keyBy('id');

        $teachers = $ids->map(function ($id) use ($people, $attendanceByTeacher, $homeworkByTeacher, $classesByTeacher) {
            $person = $people[$id] ?? null;
            $attendance = $attendanceByTeacher[$id] ?? null;
            $homework = $homeworkByTeacher[$id] ?? null;
            $classes = $classesByTeacher[$id] ?? null;

            $metrics = [];
            $evidence = [];
            $notes = [];

            if ($attendance) {
                $marks = (int) $attendance->marks;
                $rate = $marks > 0 ? round((($marks - (int) $attendance->absent) / $marks) * 100, 1) : null;
                $metrics[] = ['key' => 'attendance_marked', 'label' => 'Attendance marked', 'value' => number_format($marks)];
                if ($rate !== null) {
                    $metrics[] = ['key' => 'class_attendance', 'label' => 'Attendance in their classes', 'value' => self::pct($rate), 'band' => self::attendanceBand($rate)];
                }
                $evidence[] = ['label' => 'Marks recorded', 'value' => number_format($marks)];
                $evidence[] = ['label' => 'Students covered', 'value' => number_format((int) $attendance->students)];
            }

            if ($homework) {
                $total = (int) $homework->total;
                $rate = $total > 0 ? round(((int) $homework->submitted) / $total * 100, 1) : null;
                $metrics[] = ['key' => 'homework_set', 'label' => 'Homework set', 'value' => number_format($total)];
                if ($rate !== null) {
                    $metrics[] = ['key' => 'homework_rate', 'label' => 'Submission rate', 'value' => self::pct($rate), 'band' => self::attendanceBand($rate)];
                    if ($rate < 50) {
                        $notes[] = sprintf('Only %s%% of the homework they set is being submitted.', self::num($rate));
                    }
                }
            }

            if ($classes) {
                $evidence[] = ['label' => 'Classes allocated', 'value' => (string) (int) $classes->classes];
            }

            $observations = ((int) ($attendance->marks ?? 0)) + ((int) ($homework->total ?? 0));

            return [
                'id' => (string) $id,
                'name' => $person ? trim($person->first_name.' '.$person->last_name) : ('Staff '.$id),
                'email' => (string) ($person->email ?? ''),
                'metrics' => $metrics,
                'evidence' => $evidence,
                'observations' => $observations,
                // Below a floor the figures describe a handful of records, not a
                // person's teaching. Saying so is the finding.
                'sufficientEvidence' => $observations >= 20,
                'summary' => $observations >= 20
                    ? implode(' ', $notes) ?: 'Activity is recorded and within normal range for this school.'
                    : sprintf('Only %d records in this institute are attributed to this member of staff — too few to describe their teaching.', $observations),
            ];
        })->sortByDesc('observations')->take($limit)->values()->all();

        return [
            'available' => true,
            'teachers' => $teachers,
            'coverage' => [
                'attributedMarks' => $attributedMarks,
                'totalMarks' => $totalMarks,
                'sharePercent' => $totalMarks > 0 ? round($attributedMarks / $totalMarks * 100, 1) : 0.0,
                'note' => $totalMarks > 0 && ($attributedMarks / $totalMarks) < 0.5
                    ? sprintf('Only %s%% of attendance marks in this institute name the teacher who took them, so most teaching activity cannot be attributed to anyone.',
                        self::num($totalMarks > 0 ? $attributedMarks / $totalMarks * 100 : 0))
                    : null,
            ],
        ];
    }

    /* --------------------------------------------------------------- helpers */

    /** @param array<int, array{severity: string}> $risks */
    private static function overallRisk(array $risks): string
    {
        foreach (['high', 'medium'] as $level) {
            foreach ($risks as $risk) {
                if (($risk['severity'] ?? '') === $level) {
                    return $level === 'high' ? 'High' : 'Medium';
                }
            }
        }

        return $risks === [] ? 'Low' : 'Low';
    }

    private static function attendanceBand(float $rate): string
    {
        return match (true) {
            $rate >= 90 => 'Good',
            $rate >= 80 => 'Watch',
            $rate >= 65 => 'At risk',
            default => 'Critical',
        };
    }

    private static function scoreBand(float $mean): string
    {
        $pass = (float) config('brain.thresholds.pass_percentage', 40.0);

        return match (true) {
            $mean >= 75 => 'Good',
            $mean >= 60 => 'Watch',
            $mean >= $pass => 'At risk',
            default => 'Critical',
        };
    }

    private static function pct($value): string
    {
        return self::num($value).'%';
    }

    private static function num($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    }
}
