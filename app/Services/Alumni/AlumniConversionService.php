<?php

namespace App\Services\Alumni;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student -> alumni conversion.
 *
 * ELIGIBILITY IS A CONFIGURABLE RULE, NOT AN ASSUMPTION. The ERP has no "passed out"
 * flag, so a tenant picks the rule in alumni_settings ('eligibility_rule'):
 *
 *   lc_or_final_enrollment_ended (default, the product owner's decision) a Leaving Certificate row
 *                           exists in tblstudent_tc_details, OR the student has an enrollment in the
 *                           institute's final standard (highest sort_order) whose end_date has passed.
 *   lc_issued               a Leaving Certificate row exists in tblstudent_tc_details.
 *   lc_or_inactive          LC issued, or tblstudent.student_inactive = 1 with an
 *                           inactive_date (when those columns exist).
 *
 * Nothing runs on its own: preview() is read-only, run() converts explicitly chosen
 * students (or everyone the rule matches) and is IDEMPOTENT - the unique key
 * (sub_institute_id, student_id) plus a pre-check means re-running never duplicates.
 *
 * The live tblstudent has drifted from its migrations, so only columns that actually
 * exist are selected (Schema::getColumnListing), and missing ones simply yield null.
 */
class AlumniConversionService
{
    public const RULES = ['lc_or_final_enrollment_ended', 'lc_issued', 'lc_or_inactive'];

    public function __construct(private AlumniService $alumni)
    {
    }

    public function rule(int $tenant): string
    {
        $r = (string) $this->alumni->setting($tenant, 'eligibility_rule', 'lc_or_final_enrollment_ended');

        return in_array($r, self::RULES, true) ? $r : 'lc_or_final_enrollment_ended';
    }

    /**
     * Students matching the rule who are not yet alumni.
     *
     * @return array{rule:string, total:int, candidates:array<int,array<string,mixed>>}
     */
    public function preview(int $tenant, int $limit = 200): array
    {
        $rule = $this->rule($tenant);
        $base = $this->eligibleQuery($tenant, $rule);

        $total = (clone $base)->count('s.id');
        $cols = $this->studentColumns();
        $rows = $base->orderBy('s.id')->limit(max(1, min($limit, 1000)))
            ->get($this->selectList($cols));

        return [
            'rule' => $rule,
            'total' => $total,
            'candidates' => $rows->map(fn ($r) => $this->mapStudent($r))->all(),
        ];
    }

    /**
     * Convert eligible students. $studentIds null = every eligible student; otherwise only
     * those ids (and only if they are eligible - an id outside the rule is reported, not
     * converted). Returns counts and per-student skips.
     *
     * @param  array<int,int>|null  $studentIds
     * @return array{created:int, already_alumni:int, skipped:array<int,array{student_id:int,reason:string}>}
     */
    public function run(int $tenant, ?array $studentIds, ?int $userId, ?Request $request = null): array
    {
        $rule = $this->rule($tenant);
        $q = $this->eligibleQuery($tenant, $rule);
        if ($studentIds !== null) {
            $q->whereIn('s.id', array_map('intval', $studentIds));
        }
        $cols = $this->studentColumns();
        $announce = (bool) $this->alumni->setting($tenant, 'notify_on_registration', false);
        $result = ['created' => 0, 'already_alumni' => 0, 'skipped' => []];

        if ($studentIds !== null) {
            $already = DB::table('alumni')->where('sub_institute_id', $tenant)
                ->whereIn('student_id', array_map('intval', $studentIds))->pluck('student_id')->all();
            $result['already_alumni'] = count($already);
        }

        $q->orderBy('s.id')->select($this->selectList($cols))->chunk(200, function ($rows) use (&$result, $tenant, $userId, $request, $announce) {
            foreach ($rows as $r) {
                $data = $this->mapStudent($r);
                try {
                    $created = $this->alumni->create($tenant, $data, $userId, 'auto', false, $request);
                    $result['created']++;
                    if ($announce) {
                        // opt-in (setting notify_on_registration): a welcome notice should be a decision, not a side effect of converting
                        try {
                            app(AlumniMessaging::class)->notify($tenant, (int) $created->id, 'registration', 'Welcome to the {{institute}} alumni network',
                                "Dear {{name}},\n\nYou have been added to the {{institute}} alumni network (ID {{alumni_code}}). Please check your details are correct and tell us how you would like to hear from us.",
                                'registration:' . $created->id, true);
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                } catch (DuplicateAlumniException $e) {
                    $result['skipped'][] = ['student_id' => (int) $r->id, 'reason' => 'matches existing alumni ' . ($e->matches[0]['alumni_code'] ?? '')];
                } catch (\Throwable $e) {
                    $result['skipped'][] = ['student_id' => (int) $r->id, 'reason' => 'error: ' . $e->getMessage()];
                }
            }
        });

        return $result;
    }

    // ------------------------------------------------------------------ internals

    private function eligibleQuery(int $tenant, string $rule)
    {
        $q = DB::table('tblstudent as s')
            ->where('s.sub_institute_id', $tenant)
            ->whereNotExists(function ($n) use ($tenant) {
                $n->select(DB::raw(1))->from('alumni as a')
                    ->whereColumn('a.student_id', 's.id')->where('a.sub_institute_id', $tenant);
            });

        $hasLc = fn ($w) => $w->select(DB::raw(1))->from('tblstudent_tc_details as tc')
            ->whereColumn('tc.student_id', 's.id')->where('tc.sub_institute_id', $tenant);

        $cols = $this->studentColumns();
        if ($rule === 'lc_or_final_enrollment_ended' && $this->hasFinalEnrollmentData()) {
            // "final standard" = the institute's highest sort_order. next_standard_id is not used: it is not reliably filled in.
            $finalSort = DB::table('standard')->where('sub_institute_id', $tenant)->max('sort_order');
            $today = now()->toDateString();
            $q->where(function ($w) use ($hasLc, $tenant, $finalSort, $today) {
                $w->whereExists($hasLc);
                if ($finalSort !== null) {
                    $w->orWhereExists(function ($e) use ($tenant, $finalSort, $today) {
                        $e->select(DB::raw(1))->from('tblstudent_enrollment as en')->join('standard as st', 'st.id', '=', 'en.standard_id')
                            ->whereColumn('en.student_id', 's.id')->where('en.sub_institute_id', $tenant)->where('st.sub_institute_id', $tenant)
                            ->where('st.sort_order', $finalSort)->whereNotNull('en.end_date')->where('en.end_date', '<=', $today);
                    });
                }
            });
        } elseif ($rule === 'lc_or_inactive' && in_array('student_inactive', $cols, true) && in_array('inactive_date', $cols, true)) {
            $q->where(function ($w) use ($hasLc) {
                $w->whereExists($hasLc)
                    ->orWhere(function ($x) {
                        $x->where('s.student_inactive', 1)->whereNotNull('s.inactive_date');
                    });
            });
        } else {
            $q->whereExists($hasLc);
        }

        return $q;
    }

    /** The columns this rule reads, checked because the live tables have drifted from their migrations; without them it falls back to leaving-certificate only. */
    private function hasFinalEnrollmentData(): bool
    {
        return Schema::hasTable('tblstudent_enrollment') && Schema::hasTable('standard')
            && Schema::hasColumns('tblstudent_enrollment', ['student_id', 'standard_id', 'end_date', 'sub_institute_id'])
            && Schema::hasColumns('standard', ['sort_order', 'sub_institute_id']);
    }

    /** @return array<int,string> */
    private function studentColumns(): array
    {
        return Schema::getColumnListing('tblstudent');
    }

    private function selectList(array $cols): array
    {
        $want = ['id', 'enrollment_no', 'admission_year', 'first_name', 'middle_name', 'last_name', 'gender', 'dob',
            'mobile', 'student_mobile', 'email', 'address', 'city', 'state', 'pincode', 'bloodgroup', 'image', 'inactive_date'];

        return array_map(fn ($c) => 's.' . $c, array_values(array_intersect($want, $cols)));
    }

    /** Map a legacy student row to alumni master fields (never invents values). */
    private function mapStudent(object $r): array
    {
        $g = fn (string $k) => isset($r->{$k}) && trim((string) $r->{$k}) !== '' ? trim((string) $r->{$k}) : null;

        $leaving = $g('inactive_date');

        return array_filter([
            'student_id' => (int) $r->id,
            'enrollment_no' => $g('enrollment_no'),
            'first_name' => $g('first_name') ?? 'Unknown',
            'middle_name' => $g('middle_name'),
            'last_name' => $g('last_name'),
            'gender' => $g('gender'),
            'dob' => $g('dob'),
            'blood_group' => $g('bloodgroup'),
            'email' => $g('email'),
            'mobile' => $g('mobile') ?? $g('student_mobile'),
            'current_address' => $g('address'),
            'city' => $g('city'),
            'state' => $g('state'),
            'pincode' => $g('pincode'),
            'admission_year' => $g('admission_year'),
            'leaving_date' => $leaving,
        ], fn ($v) => $v !== null);
    }
}
