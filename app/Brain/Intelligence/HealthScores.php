<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Organization health across the dimensions a school actually runs on.
 *
 * EVERY SCORE SHOWS ITS WORKING. Each dimension returns the components it was
 * built from, the formula in words, and a plain sentence explaining why it
 * landed where it did. A number nobody can reconstruct is a number nobody should
 * act on — and a health dial with a hidden formula is the single easiest way for
 * a dashboard to look intelligent while meaning nothing.
 *
 * A DIMENSION WITH TOO LITTLE DATA SCORES NOTHING, NOT ZERO. This school has
 * marks for 9 of 3,438 students. An "academic health: 37/100" computed off nine
 * rows would be a fabricated judgement about 3,429 children nobody has assessed.
 * So each dimension declares a coverage floor, and below it returns
 * available:false with the reason — which is itself the finding, and is exactly
 * what the coverage rules raise a signal about.
 *
 * SCORES ARE 0-100 WHERE 100 IS "NOTHING TO DO HERE". They are deliberately not
 * benchmarked against other schools: the Brain has one institute's data and no
 * standing to say how it compares to anyone else.
 */
final class HealthScores
{
    use LmsQueryScope;

    /** Below this share of the roll, a dimension cannot speak for the school. */
    private const COVERAGE_FLOOR = 0.20;

    public function __construct(private readonly string $tenantId)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $dimensions = [
            $this->attendanceHealth(),
            $this->engagementHealth(),
            $this->academicHealth(),
            $this->departmentHealth(),
            $this->staffHealth(),
            $this->studentRecordHealth(),
            $this->financialHealth(),
            $this->dataQualityHealth(),
        ];

        $scored = array_values(array_filter($dimensions, fn ($d) => $d['available']));

        // The overall figure is a plain mean of the dimensions that COULD be
        // scored. Weighting them would be asserting that attendance matters
        // more than fees to this particular school, which is the principal's
        // call and not the Brain's.
        $overall = $scored === []
            ? null
            : (int) round(array_sum(array_column($scored, 'score')) / count($scored));

        return [
            'overall' => [
                'score' => $overall,
                'band' => $overall === null ? null : self::band($overall),
                'scoredDimensions' => count($scored),
                'totalDimensions' => count($dimensions),
                'formula' => 'The mean of every dimension that had enough data to score. Dimensions below their coverage floor are excluded rather than counted as zero.',
                'why' => $overall === null
                    ? 'No dimension has enough recorded data to score yet.'
                    : sprintf(
                        '%d of %d dimensions could be scored. %s',
                        count($scored),
                        count($dimensions),
                        self::overallWhy($scored)
                    ),
            ],
            'dimensions' => $dimensions,
        ];
    }

    /* ------------------------------------------------------------ dimensions */

    private function attendanceHealth(): array
    {
        $students = $this->lmsCount('tblstudent');
        $covered = SchemaCache::hasTable('attendance_student')
            ? (int) DB::table('attendance_student')->where('sub_institute_id', $this->tenantId)->distinct()->count('student_id')
            : 0;

        if ($students === 0 || $covered === 0) {
            return $this->unavailable('attendance', 'Attendance health',
                'No student attendance has been recorded for this institute.');
        }

        $coverage = $covered / $students;
        if ($coverage < self::COVERAGE_FLOOR) {
            return $this->unavailable('attendance', 'Attendance health', sprintf(
                'Attendance is recorded for only %d of %s students (%s%%). That is too few to describe the school, so no score is given.',
                $covered, number_format($students), self::num($coverage * 100)
            ), [
                ['label' => 'Students tracked', 'value' => number_format($covered)],
                ['label' => 'Students enrolled', 'value' => number_format($students)],
                ['label' => 'Coverage', 'value' => self::num($coverage * 100).'%'],
            ]);
        }

        $trends = new TrendAnalyzer($this->tenantId);
        $byClass = $trends->attendanceByClass();
        $trend = $trends->attendanceTrend();

        $rate = $byClass['baseline'];
        // A 95% attendance rate is a healthy school; 75% is a crisis. The scale
        // maps that band onto 0-100 rather than treating the raw percentage as
        // a score, which would make a genuinely bad 75% look like a pass.
        $score = (int) round(max(0, min(100, ($rate - 70) / 25 * 100)));

        $lagging = array_values(array_filter($byClass['classes'], fn ($c) => $c['gapPoints'] <= -4));

        return [
            'key' => 'attendance',
            'label' => 'Attendance health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => self::num($rate).'%',
            'change' => $trend['available'] ? $trend['changePoints'] : null,
            'changeLabel' => $trend['available']
                ? sprintf('vs %s%% in %s', self::num($trend['previous']['rate']), Narrative::monthName($trend['previous']['period']))
                : null,
            'formula' => 'Attendance rate mapped onto a 70%–95% band: 70% or below scores 0, 95% or above scores 100.',
            'why' => $lagging === []
                ? sprintf('Attendance is running at %s%% across %s recorded marks, with no class materially below the school baseline.', self::num($rate), number_format($byClass['marks']))
                : sprintf('Attendance is %s%% overall, but %d class%s sit%s at least 4 points below it — %s is the lowest at %s%%.',
                    self::num($rate),
                    count($lagging),
                    count($lagging) === 1 ? '' : 'es',
                    count($lagging) === 1 ? 's' : '',
                    $lagging[0]['className'],
                    self::num($lagging[0]['rate'])),
            'drivers' => array_values(array_filter([
                ['label' => 'Attendance rate', 'value' => self::num($rate).'%'],
                ['label' => 'Students tracked', 'value' => number_format($covered).' of '.number_format($students)],
                ['label' => 'Marks recorded', 'value' => number_format($byClass['marks'])],
                $lagging ? ['label' => 'Classes below baseline', 'value' => (string) count($lagging)] : null,
            ])),
            'action' => $lagging
                ? sprintf('Review %s with its class teacher and contact the guardians of its most absent students.', $lagging[0]['className'])
                : null,
        ];
    }

    private function engagementHealth(): array
    {
        if (! SchemaCache::hasTable('homework')) {
            return $this->unavailable('engagement', 'Engagement health', 'This LMS records no homework.');
        }

        $totals = DB::table('homework')->where('sub_institute_id', $this->tenantId)
            ->selectRaw('COUNT(*) as total, SUM(completion_status = "Y") as submitted')->first();

        $total = (int) ($totals->total ?? 0);
        if ($total < 30) {
            return $this->unavailable('engagement', 'Engagement health',
                sprintf('Only %d homework assignments are recorded — too few to describe engagement.', $total));
        }

        $rate = round(((int) $totals->submitted) / $total * 100, 1);
        $trends = new TrendAnalyzer($this->tenantId);
        $trend = $trends->homeworkTrend();
        $bySubject = $trends->homeworkBySubject();
        $lagging = array_values(array_filter($bySubject['subjects'], fn ($s) => $s['gapPoints'] <= -15));

        // 60%–100% submission is the meaningful band: below 60% the homework
        // cycle has effectively stopped working.
        $score = (int) round(max(0, min(100, ($rate - 60) / 40 * 100)));

        return [
            'key' => 'engagement',
            'label' => 'Engagement health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => self::num($rate).'%',
            'change' => $trend['available'] ? $trend['changePoints'] : null,
            'changeLabel' => $trend['available']
                ? sprintf('vs %s%% in %s', self::num($trend['previous']['rate']), Narrative::monthName($trend['previous']['period']))
                : null,
            'formula' => 'Homework submission rate mapped onto a 60%–100% band: 60% or below scores 0, 100% scores 100.',
            'why' => $lagging === []
                ? sprintf('%s%% of %s assignments are marked complete, with no subject lagging the school baseline.', self::num($rate), number_format($total))
                : sprintf('%s%% of %s assignments are complete overall, but %s is at %s%% — %s points below the school baseline.',
                    self::num($rate), number_format($total), $lagging[0]['subject'],
                    self::num($lagging[0]['rate']), self::num(abs($lagging[0]['gapPoints']))),
            'drivers' => array_values(array_filter([
                ['label' => 'Submission rate', 'value' => self::num($rate).'%'],
                ['label' => 'Assignments', 'value' => number_format($total)],
                ['label' => 'Outstanding', 'value' => number_format($total - (int) $totals->submitted)],
                $lagging ? ['label' => 'Subjects below baseline', 'value' => (string) count($lagging)] : null,
            ])),
            'action' => $lagging
                ? sprintf('Review the homework being set in %s for volume and clarity.', $lagging[0]['subject'])
                : null,
        ];
    }

    private function academicHealth(): array
    {
        if (! SchemaCache::hasTable('result_marks')) {
            return $this->unavailable('academic', 'Academic health', 'This LMS records no marks.');
        }

        $students = $this->lmsCount('tblstudent');
        $graded = (int) DB::table('result_marks')->where('sub_institute_id', $this->tenantId)->distinct()->count('student_id');

        if ($students > 0 && ($graded / $students) < self::COVERAGE_FLOOR) {
            return $this->unavailable('academic', 'Academic health', sprintf(
                'Marks exist for %d of %s students. Scoring academic health from %s would be a judgement about %s students nobody has assessed.',
                $graded, number_format($students), $graded === 1 ? 'one record' : "$graded records", number_format($students - $graded)
            ), [
                ['label' => 'Students with marks', 'value' => number_format($graded)],
                ['label' => 'Students enrolled', 'value' => number_format($students)],
                ['label' => 'Coverage', 'value' => $students > 0 ? self::num($graded / $students * 100).'%' : '0%'],
            ]);
        }

        $stats = DB::table('result_marks')->where('sub_institute_id', $this->tenantId)
            ->selectRaw('COUNT(*) as rows_count, AVG(per) as mean_pct')->first();

        $mean = (float) ($stats->mean_pct ?? 0);
        $pass = (float) config('brain.thresholds.pass_percentage', 40.0);
        $score = (int) round(max(0, min(100, $mean)));

        return [
            'key' => 'academic',
            'label' => 'Academic health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => self::num($mean).'%',
            'change' => null,
            'changeLabel' => null,
            'formula' => 'The mean recorded score, used directly as the score out of 100.',
            'why' => sprintf('The mean recorded score across %s mark records is %s%%, against a pass mark of %s%%.',
                number_format((int) $stats->rows_count), self::num($mean), self::num($pass)),
            'drivers' => [
                ['label' => 'Mean score', 'value' => self::num($mean).'%'],
                ['label' => 'Pass mark', 'value' => self::num($pass).'%'],
                ['label' => 'Mark records', 'value' => number_format((int) $stats->rows_count)],
                ['label' => 'Students assessed', 'value' => number_format($graded)],
            ],
            'action' => $mean < $pass ? 'Verify marks entry is complete, then review teaching plans for the subjects below the pass band.' : null,
        ];
    }

    private function departmentHealth(): array
    {
        if (! SchemaCache::hasTable('hrms_departments')) {
            return $this->unavailable('departments', 'Department health', 'This LMS records no departments.');
        }

        $base = fn () => $this->lmsDepartments();

        $total = (int) $base()->count();
        if ($total === 0) {
            return $this->unavailable('departments', 'Department health', 'No departments are recorded for this institute.');
        }

        $withHead = (int) $base()->whereNotNull('hrms_departments.head_user_id')->where('hrms_departments.head_user_id', '>', 0)->count();
        $withRemit = SchemaCache::hasColumn('hrms_departments', 'description')
            ? (int) $base()->whereNotNull('hrms_departments.description')->where('hrms_departments.description', '!=', '')->count() : 0;
        // Through the department row, not off tbluser: department_id is not a
        // tenant-scoped foreign key in this schema, so counting distinct ids
        // directly credits this institute with departments it does not own —
        // which would push Department health up on data belonging elsewhere.
        $occupied = SchemaCache::hasTable('tbluser')
            ? (int) $this->lmsPeople()
                ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
                ->where('d.sub_institute_id', $this->tenantId)
                ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('d.status', 1))
                ->distinct()->count('d.id')
            : 0;

        $headShare = $withHead / $total;
        $remitShare = $withRemit / $total;
        $occupiedShare = min(1.0, $occupied / $total);

        // Ownership is weighted heaviest because a department with no head is an
        // operational gap, while one with no written remit is only untidy.
        $score = (int) round((($headShare * 0.5) + ($remitShare * 0.2) + ($occupiedShare * 0.3)) * 100);

        return [
            'key' => 'departments',
            'label' => 'Department health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => number_format($total),
            'change' => null,
            'changeLabel' => 'departments',
            'formula' => 'Half the score is the share of departments with a head, three tenths the share that actually hold staff, two tenths the share with a written remit.',
            'why' => sprintf('%s of %s departments have a head, %s hold any staff, and %s have a written remit.',
                number_format($withHead), number_format($total), number_format($occupied), number_format($withRemit)),
            'drivers' => [
                ['label' => 'With a head', 'value' => number_format($withHead).' of '.number_format($total)],
                ['label' => 'Holding staff', 'value' => number_format($occupied).' of '.number_format($total)],
                ['label' => 'With a remit', 'value' => number_format($withRemit).' of '.number_format($total)],
            ],
            'action' => $headShare < 0.8
                ? 'Assign a head to every department that carries staff, starting with the largest.'
                : null,
        ];
    }

    private function staffHealth(): array
    {
        if (! SchemaCache::hasTable('tbluser')) {
            return $this->unavailable('staff', 'Staff health', 'This LMS records no staff.');
        }

        $total = SchemaCache::hasTable('tbluser') && SchemaCache::hasTable('tbluserprofilemaster')
            ? (int) $this->lmsPeople()->count()
            : $this->lmsCount('tbluser');
        if ($total === 0) {
            return $this->unavailable('staff', 'Staff health', 'No staff are recorded for this institute.');
        }

        $scoped = fn () => $this->lmsPeople();

        $withDept = (int) $scoped()->whereNotNull('tbluser.department_id')->where('tbluser.department_id', '>', 0)->count();
        $contactable = (int) $scoped()->whereNotNull('tbluser.email')->where('tbluser.email', '!=', '')
            ->whereNotNull('tbluser.mobile')->where('tbluser.mobile', '!=', '')->count();
        $signedIn = SchemaCache::hasColumn('tbluser', 'last_login')
            ? (int) $scoped()->whereNotNull('tbluser.last_login')->where('tbluser.last_login', '!=', '')->count() : 0;
        $withTitle = SchemaCache::hasColumn('tbluser', 'jobtitle_id')
            ? (int) $scoped()->whereNotNull('tbluser.jobtitle_id')->where('tbluser.jobtitle_id', '>', 0)->count() : 0;

        $score = (int) round((
            (($withDept / $total) * 0.3)
            + (($contactable / $total) * 0.2)
            + (($signedIn / $total) * 0.3)
            + (($withTitle / $total) * 0.2)
        ) * 100);

        return [
            'key' => 'staff',
            'label' => 'Staff health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => number_format($total),
            'change' => null,
            'changeLabel' => 'staff on roll',
            'formula' => 'Three tenths each for having a department and having signed in, two tenths each for being contactable and having a job title.',
            'why' => sprintf('%s of %s staff have signed in, %s have a department, %s have a job title, and %s are contactable on both email and phone.',
                number_format($signedIn), number_format($total), number_format($withDept), number_format($withTitle), number_format($contactable)),
            'drivers' => [
                ['label' => 'Have signed in', 'value' => number_format($signedIn).' of '.number_format($total)],
                ['label' => 'Have a department', 'value' => number_format($withDept).' of '.number_format($total)],
                ['label' => 'Have a job title', 'value' => number_format($withTitle).' of '.number_format($total)],
                ['label' => 'Contactable', 'value' => number_format($contactable).' of '.number_format($total)],
            ],
            'action' => $signedIn === 0
                ? 'Distribute credentials and run a first-login campaign; nothing in the system reflects staff activity until they sign in.'
                : ($withTitle === 0 ? 'Populate the job-title master so capability and progression can be assessed.' : null),
        ];
    }

    private function studentRecordHealth(): array
    {
        if (! SchemaCache::hasTable('tblstudent')) {
            return $this->unavailable('students', 'Student record health', 'This LMS records no students.');
        }

        $total = $this->lmsCount('tblstudent');
        if ($total === 0) {
            return $this->unavailable('students', 'Student record health', 'No students are enrolled for this institute.');
        }

        $scoped = fn () => $this->lmsStudents();

        $withEnrolment = (int) $scoped()->whereNotNull('tblstudent.enrollment_no')->where('tblstudent.enrollment_no', '!=', '')->count();
        $withDob = (int) $scoped()->whereNotNull('tblstudent.dob')->count();
        $contactable = (int) $scoped()->where(function ($q) {
            $q->where('tblstudent.mobile', '!=', '')->orWhere('tblstudent.student_mobile', '!=', '')->orWhere('tblstudent.mother_mobile', '!=', '');
        })->count();
        $withId = SchemaCache::hasColumn('tblstudent', 'adharnumber')
            ? (int) $scoped()->whereNotNull('tblstudent.adharnumber')->where('tblstudent.adharnumber', '!=', '')->count() : 0;

        $score = (int) round((
            (($withEnrolment / $total) * 0.3)
            + (($withDob / $total) * 0.25)
            + (($contactable / $total) * 0.3)
            + (($withId / $total) * 0.15)
        ) * 100);

        $gaps = [];
        if ($withId / $total < 0.5) {
            $gaps[] = sprintf('%s have no statutory ID', number_format($total - $withId));
        }
        if ($contactable / $total < 0.99) {
            $gaps[] = sprintf('%s have no contact number', number_format($total - $contactable));
        }

        return [
            'key' => 'students',
            'label' => 'Student record health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => number_format($total),
            'change' => null,
            'changeLabel' => 'students enrolled',
            'formula' => 'Three tenths each for a contact number and an enrolment number, a quarter for date of birth, and the rest for a statutory ID.',
            'why' => $gaps === []
                ? sprintf('All %s student records carry the fields the school depends on.', number_format($total))
                : sprintf('Of %s students, %s.', number_format($total), implode(' and ', $gaps)),
            'drivers' => [
                ['label' => 'Contactable', 'value' => number_format($contactable).' of '.number_format($total)],
                ['label' => 'Enrolment number', 'value' => number_format($withEnrolment).' of '.number_format($total)],
                ['label' => 'Date of birth', 'value' => number_format($withDob).' of '.number_format($total)],
                ['label' => 'Statutory ID', 'value' => number_format($withId).' of '.number_format($total)],
            ],
            'action' => $withId / $total < 0.5
                ? 'Digitise the statutory identifiers held on admission files before the next government return.'
                : null,
        ];
    }

    private function financialHealth(): array
    {
        if (! SchemaCache::hasTable('fees_collect')) {
            return $this->unavailable('finance', 'Fee health', 'This LMS records no fee collection.');
        }

        $students = $this->lmsCount('tblstudent');
        $paying = (int) DB::table('fees_collect')->where('sub_institute_id', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y'))
            ->distinct()->count('student_id');

        $totals = DB::table('fees_collect')->where('sub_institute_id', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y'))
            ->selectRaw('COUNT(*) as receipts, COALESCE(SUM(amount),0) as collected')->first();

        if ($students > 0 && ($paying / $students) < self::COVERAGE_FLOOR) {
            return $this->unavailable('finance', 'Fee health', sprintf(
                'Receipts exist for %d of %s students. Collection is happening outside the ERP, so no collection rate can be calculated from it.',
                $paying, number_format($students)
            ), [
                ['label' => 'Students with receipts', 'value' => number_format($paying)],
                ['label' => 'Students enrolled', 'value' => number_format($students)],
                ['label' => 'Receipts recorded', 'value' => number_format((int) $totals->receipts)],
                ['label' => 'Collected in system', 'value' => Narrative::money($totals->collected)],
            ]);
        }

        $coverage = $students > 0 ? $paying / $students : 0;
        $score = (int) round($coverage * 100);
        $trend = (new TrendAnalyzer($this->tenantId))->feeTrend();

        return [
            'key' => 'finance',
            'label' => 'Fee health',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => Narrative::money($totals->collected),
            'change' => $trend['available'] ? $trend['changePercent'] : null,
            'changeLabel' => $trend['available']
                ? sprintf('vs %s in %s', Narrative::money($trend['previous']['collected']), Narrative::monthName($trend['previous']['period']))
                : null,
            'formula' => 'The share of enrolled students who have at least one fee receipt recorded in the ERP.',
            'why' => sprintf('%s of %s students have a receipt, totalling %s across %s receipts.',
                number_format($paying), number_format($students), Narrative::money($totals->collected), number_format((int) $totals->receipts)),
            'drivers' => [
                ['label' => 'Students with receipts', 'value' => number_format($paying).' of '.number_format($students)],
                ['label' => 'Collected', 'value' => Narrative::money($totals->collected)],
                ['label' => 'Receipts', 'value' => number_format((int) $totals->receipts)],
            ],
            'action' => null,
        ];
    }

    private function dataQualityHealth(): array
    {
        // Data quality is measured by what the engine itself found: how many of
        // its data-quality rules are currently firing. That keeps this dimension
        // honest — it cannot report clean data while the rules say otherwise.
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return $this->unavailable('data_quality', 'Data quality', 'The Brain signal store is not provisioned.');
        }

        $qualityFamilies = ['data_quality', 'compliance_exposure', 'lifecycle_inconsistency'];
        $qualityRules = array_keys(array_filter(
            RuleCatalogue::CAUSES,
            fn ($cause) => in_array($cause['family'], $qualityFamilies, true)
        ));

        if ($qualityRules === []) {
            return $this->unavailable('data_quality', 'Data quality', 'No data-quality rules are defined.');
        }

        $firing = DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('rule_key', $qualityRules)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->get(['rule_key', 'severity', 'metadata']);

        $score = (int) round((1 - (count($firing) / count($qualityRules))) * 100);
        $highest = $firing->first(fn ($s) => $s->severity === 'high') ?? $firing->first();
        $highestTitle = $highest
            ? (json_decode((string) $highest->metadata, true)['title'] ?? $highest->rule_key)
            : null;

        return [
            'key' => 'data_quality',
            'label' => 'Data quality',
            'available' => true,
            'score' => $score,
            'band' => self::band($score),
            'headline' => count($firing).' of '.count($qualityRules),
            'change' => null,
            'changeLabel' => 'checks failing',
            'formula' => 'The share of the Brain’s data-quality, compliance and lifecycle checks that are currently passing.',
            'why' => $firing->isEmpty()
                ? sprintf('All %d data-quality checks are passing.', count($qualityRules))
                : sprintf('%d of %d checks are failing. The most serious is: %s.', count($firing), count($qualityRules), $highestTitle),
            'drivers' => [
                ['label' => 'Checks passing', 'value' => (count($qualityRules) - count($firing)).' of '.count($qualityRules)],
                ['label' => 'Checks failing', 'value' => (string) count($firing)],
            ],
            'action' => $firing->isEmpty() ? null : 'Work the open data-quality findings on the Intelligence Loop screen.',
        ];
    }

    /* --------------------------------------------------------------- helpers */

    /** @param array<int, array{label: string, value: string}> $drivers */
    private function unavailable(string $key, string $label, string $reason, array $drivers = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'available' => false,
            'score' => null,
            'band' => null,
            'headline' => null,
            'change' => null,
            'changeLabel' => null,
            'reason' => $reason,
            'formula' => null,
            'why' => $reason,
            'drivers' => $drivers,
            'action' => null,
        ];
    }

    /** @param array<int, array<string, mixed>> $scored */
    private static function overallWhy(array $scored): string
    {
        usort($scored, fn ($a, $b) => $a['score'] <=> $b['score']);
        $worst = $scored[0];
        $best = $scored[count($scored) - 1];

        return sprintf('%s is the weakest at %d, %s the strongest at %d.',
            $worst['label'], $worst['score'], $best['label'], $best['score']);
    }

    public static function band(int $score): string
    {
        return match (true) {
            $score >= 75 => 'Good',
            $score >= 50 => 'Watch',
            $score >= 25 => 'At risk',
            default => 'Critical',
        };
    }

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
