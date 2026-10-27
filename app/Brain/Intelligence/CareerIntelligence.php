<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Career Intelligence (Brain module) — one institute, one academic year.
 *
 * READ-ONLY over the Career Intelligence ledger. It never writes to, and never
 * calls into, `App\CareerIntelligence\*`; it only counts rows the career
 * adapters have already written.
 *
 * Primary tables:
 * - `evidence_events`     append-only student evidence ledger (assessment / PAL adapters)
 * - `student_aspirations` the student's stated ambition, a snapshot series (`is_current`)
 * - `tblstudent_enrollment` + `standard` — the cohort and the tenant scope
 *
 * ── TENANT SCOPE IS DERIVED, NOT STORED ─────────────────────────────────────
 *
 * Neither `evidence_events` nor `student_aspirations` has a `sub_institute_id`.
 * Both key on `student_id` alone, so an institute's career data is exactly the
 * rows whose student is enrolled at it. Every figure below is therefore taken
 * over the COHORT — students enrolled this year in the Career wedge grades —
 * and ledger rows are reached only through `student_id IN (cohort)`. A row for
 * a student who is not in this institute's cohort is invisible to it.
 *
 * ── WHY GRADES 9 AND 10 ─────────────────────────────────────────────────────
 *
 * `student_aspirations.grade` is documented as "9 or 10 for the wedge", and the
 * aspiration form is a Grade-9 form. Measuring evidence coverage over every
 * enrolled student would call a whole primary school "uncovered" by a module
 * that was never meant to reach it. The grade is read from the class name the
 * same way `ErpSubjectEnrolmentAdapter::resolveGrade()` does (first run of
 * digits), because `standard` carries no numeric grade of its own.
 *
 * ── WHAT THIS DOES NOT CLAIM ────────────────────────────────────────────────
 *
 * `evidence_events.academic_year` is free text ("2021", "2022", "2026-2027" are
 * all possible), so a year is matched on its first four digits. Evidence is a
 * longitudinal ledger: "has any evidence" looks across every year, and the
 * in-year figure is reported separately rather than substituted for it.
 */
final class CareerIntelligence
{
    private const EVIDENCE_TABLE = 'evidence_events';
    private const ASPIRATION_TABLE = 'student_aspirations';
    private const ENROLMENT_TABLE = 'tblstudent_enrollment';
    private const STANDARD_TABLE = 'standard';

    /** Grades the career wedge (aspiration form, CAI) is built for. */
    public const WEDGE_GRADES = [9, 10];

    /** Evidence older than this, with nothing newer, no longer describes the student. */
    public const STALE_AFTER_DAYS = 365;

    private readonly string $tenantId;
    private readonly ?string $syear;

    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    private function computeCoverage(): array
    {
        $base = [
            'available' => false,
            'reason' => null,
            'sourceTable' => self::EVIDENCE_TABLE . ' · ' . self::ASPIRATION_TABLE,
            'totalRows' => 0,
            'usableRows' => 0,
        ];

        if ($this->syear === null) {
            return ['reason' => 'No academic year was specified or resolved for this institute.'] + $base;
        }

        foreach ([self::EVIDENCE_TABLE, self::ENROLMENT_TABLE, self::STANDARD_TABLE] as $table) {
            if (! SchemaCache::hasTable($table)) {
                return ['reason' => "Table '{$table}' does not exist."] + $base;
            }
        }

        $cohort = $this->cohortSize();
        if ($cohort === 0) {
            return ['reason' => 'No students in Grade ' . implode(' or ', self::WEDGE_GRADES)
                . " are enrolled for academic year {$this->syear}, so there is no cohort for career evidence to cover."] + $base;
        }

        $events = $this->eventCount();

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::EVIDENCE_TABLE . ' · ' . self::ASPIRATION_TABLE,
            'totalRows' => $events,
            'usableRows' => $events,
        ];
    }

    /* ----------------------------------------------------------- the cohort */

    /** @return array<int,int> standard ids at this institute whose class name reads as grade 9 or 10 */
    private function wedgeStandardIds(): array
    {
        return $this->memo['standards'] ??= (function (): array {
            $ids = [];
            $rows = DB::table(self::STANDARD_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->get(['id', 'name']);

            foreach ($rows as $r) {
                // Same reading as ErpSubjectEnrolmentAdapter::resolveGrade().
                if (preg_match('/(\d+)/', (string) $r->name, $m) && in_array((int) $m[1], self::WEDGE_GRADES, true)) {
                    $ids[] = (int) $r->id;
                }
            }

            return $ids;
        })();
    }

    /** Students on the roll this year in the wedge grades. */
    private function cohortQuery(): Builder
    {
        $ids = $this->wedgeStandardIds();

        return DB::table(self::ENROLMENT_TABLE . ' as en')
            ->where('en.sub_institute_id', $this->tenantId)
            ->where('en.syear', $this->syear)
            ->whereNull('en.end_date')
            ->whereIn('en.standard_id', $ids === [] ? [-1] : $ids);
    }

    private function cohortSize(): int
    {
        return $this->memo['cohort'] ??= (int) $this->cohortQuery()->distinct()->count('en.student_id');
    }

    /** Ledger rows for cohort students only — the tenant scope. */
    private function cohortEvents(): Builder
    {
        return DB::table(self::EVIDENCE_TABLE . ' as e')
            ->whereIn('e.student_id', $this->cohortQuery()->select('en.student_id'));
    }

    private function eventCount(): int
    {
        return $this->memo['events'] ??= (int) $this->cohortEvents()->count();
    }

    /* ------------------------------------------------------------- position */

    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    private function computePosition(): ?array
    {
        if (! $this->coverage()['available']) {
            return null;
        }

        $cohort = $this->cohortSize();

        $ledger = $this->cohortEvents()
            ->selectRaw('
                count(*) as events,
                count(distinct e.student_id) as evidenced,
                count(distinct case when LEFT(e.academic_year, 4) = ? then e.student_id end) as evidenced_this_year,
                sum(case when e.contested = 1 then 1 else 0 end) as contested,
                sum(case when e.superseded_by is not null then 1 else 0 end) as superseded,
                sum(case when e.contested = 0 and e.superseded_by is null then 1 else 0 end) as active
            ', [$this->syear])
            ->first();

        $events = (int) ($ledger->events ?? 0);
        $evidenced = min((int) ($ledger->evidenced ?? 0), $cohort);

        // A student is stale when their NEWEST observation is old: an old row
        // beside a recent one is history, not staleness.
        $stale = 0;
        if ($evidenced > 0) {
            $cut = now()->subDays(self::STALE_AFTER_DAYS)->toDateTimeString();
            $stale = (int) DB::query()
                ->fromSub(
                    $this->cohortEvents()->groupBy('e.student_id')->selectRaw('e.student_id, max(e.observed_at) as latest'),
                    'l'
                )
                ->where('l.latest', '<', $cut)
                ->count();
        }

        $aspirants = $aspirantsNoEvidence = 0;
        if (SchemaCache::hasTable(self::ASPIRATION_TABLE)) {
            $aspirants = (int) $this->currentAspirations()->distinct()->count('a.student_id');
            $aspirantsNoEvidence = (int) $this->currentAspirations()
                ->whereNotIn('a.student_id', $this->cohortEvents()->select('e.student_id'))
                ->distinct()
                ->count('a.student_id');
        }

        return [
            'cohort' => $cohort,
            'evidenced' => $evidenced,
            'unevidenced' => $cohort - $evidenced,
            'coverageRate' => round($evidenced / max(1, $cohort) * 100, 1),
            'evidencedThisYear' => min((int) ($ledger->evidenced_this_year ?? 0), $cohort),
            'events' => $events,
            'activeEvents' => (int) ($ledger->active ?? 0),
            'contestedEvents' => (int) ($ledger->contested ?? 0),
            'supersededEvents' => (int) ($ledger->superseded ?? 0),
            'staleStudents' => $stale,
            'aspirants' => $aspirants,
            'aspirantsWithoutEvidence' => $aspirantsNoEvidence,
        ];
    }

    /** The current aspiration snapshot for each cohort student. */
    private function currentAspirations(): Builder
    {
        return DB::table(self::ASPIRATION_TABLE . ' as a')
            ->where('a.is_current', 1)
            ->whereIn('a.student_id', $this->cohortQuery()->select('en.student_id'));
    }

    /* ----------------------------------------------------------- breakdowns */

    /** Evidence events for cohort students by the adapter that wrote them. */
    public function bySource(): array
    {
        return $this->memo['bySource'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->cohortEvents()
                ->groupBy('e.source_type')
                ->orderByRaw('count(*) desc')
                ->get([
                    'e.source_type',
                    DB::raw('count(*) as events'),
                    DB::raw('count(distinct e.student_id) as students'),
                    DB::raw('sum(case when e.contested = 1 or e.superseded_by is not null then 1 else 0 end) as revised'),
                ]);

            $total = max(1, (int) $rows->sum('events'));

            return $rows->map(fn ($r) => [
                'key' => (string) $r->source_type,
                'label' => ucfirst(str_replace('_', ' ', (string) $r->source_type)),
                'events' => (int) $r->events,
                'students' => (int) $r->students,
                'revised' => (int) $r->revised,
                'share' => round(((int) $r->events) / $total * 100, 1),
            ])->all();
        })();
    }

    /** Cohort size and evidence coverage per wedge grade. */
    public function byGrade(): array
    {
        return $this->memo['byGrade'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $names = DB::table(self::STANDARD_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->pluck('name', 'id');

            $perStandard = $this->cohortQuery()
                ->groupBy('en.standard_id')
                ->get(['en.standard_id', DB::raw('count(distinct en.student_id) as students')]);

            $evidencedBy = $this->cohortQuery()
                ->join(self::EVIDENCE_TABLE . ' as e', 'e.student_id', '=', 'en.student_id')
                ->groupBy('en.standard_id')
                ->get(['en.standard_id', DB::raw('count(distinct en.student_id) as students')])
                ->pluck('students', 'standard_id');

            $grades = [];
            foreach ($perStandard as $r) {
                preg_match('/(\d+)/', (string) ($names[$r->standard_id] ?? ''), $m);
                $grade = isset($m[1]) ? (int) $m[1] : 0;
                $g = &$grades[$grade];
                $g['students'] = ($g['students'] ?? 0) + (int) $r->students;
                $g['evidenced'] = ($g['evidenced'] ?? 0) + (int) ($evidencedBy[$r->standard_id] ?? 0);
                unset($g);
            }
            ksort($grades);

            $out = [];
            foreach ($grades as $grade => $g) {
                $out[] = [
                    'key' => 'grade-' . $grade,
                    'label' => 'Grade ' . $grade,
                    'students' => $g['students'],
                    'evidenced' => $g['evidenced'],
                    'withoutEvidence' => $g['students'] - $g['evidenced'],
                    'coverage' => round($g['evidenced'] / max(1, $g['students']) * 100, 1),
                ];
            }

            return $out;
        })();
    }

    public function dataQuality(): array
    {
        $pos = $this->position();
        if ($pos === null) {
            return [];
        }

        $unmappedStandards = (int) DB::table(self::STANDARD_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->whereRaw('name NOT REGEXP "[0-9]"')
            ->count();

        return [
            [
                'key' => 'tenant_scope',
                'label' => 'Tenant scope of the career ledger',
                'count' => $pos['events'],
                'status' => 'clean',
                'description' => 'evidence_events and student_aspirations carry no institute column. Rows are attributed to this '
                    . 'institute through the student\'s enrolment, so a student with no enrolment this year is not counted anywhere.',
            ],
            [
                'key' => 'unreadable_class_names',
                'label' => 'Classes whose grade cannot be read',
                'count' => $unmappedStandards,
                'status' => $unmappedStandards > 0 ? 'warning' : 'clean',
                'description' => $unmappedStandards > 0
                    ? "{$unmappedStandards} class names at this institute contain no digit, so a Grade 9 or 10 class named in words would fall outside the cohort."
                    : 'Every class name carries a digit the grade can be read from.',
            ],
            [
                'key' => 'revised_rows',
                'label' => 'Superseded or contested rows',
                'count' => max(0, $pos['supersededEvents']),
                'status' => 'clean',
                'description' => "{$pos['supersededEvents']} ledger rows have been superseded by a newer observation and "
                    . "{$pos['contestedEvents']} are marked contested. The ledger is append-only, so a correction is a new row, not an edit.",
            ],
        ];
    }
}
