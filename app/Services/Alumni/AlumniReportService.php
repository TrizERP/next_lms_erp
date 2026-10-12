<?php

namespace App\Services\Alumni;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The alumni reports (spec section 36-37), one definition each so the screen and the downloaded file can never disagree.
 *
 * Every report is a plan: a tenant-scoped query, its columns, and an optional row mapper. run() pages the plan for the
 * screen; rows() walks every page for an export, up to MAX_ROWS.
 *
 * PRIVACY. Reports are a sharing surface exactly like the directory and the export, so the 'institution' viewer rules apply:
 *   - a report that lists people masks a field the alumnus made private (AlumniPrivacy::mask), and
 *   - a report that counts by a private-able field (location, employer, higher education) leaves out alumni who hid it
 *     (AlumniPrivacy::scopeVisible), so an aggregate cannot be used to read a private value back out.
 * Salary is never reported. The donation report is behind the finance gate and shows an anonymous donor as such.
 *
 * Request filters are whitelisted per report in filterKeys(); anything else is ignored, never passed to a query.
 */
class AlumniReportService
{
    public const MAX_ROWS = 20000;

    /** key => [label, description, gate (manage|finance)] */
    public const REPORTS = [
        'master' => ['Alumni master', 'Every alumnus with contact, batch, location and current work.', 'manage'],
        'batch' => ['Batch-wise', 'Alumni, verification, employment and higher education for each batch.', 'manage'],
        'career' => ['Career and employment', 'Employment history: who works where, in which industry and role.', 'manage'],
        'higher_education' => ['Higher education', 'Further studies: institution, qualification, country and status.', 'manage'],
        'location' => ['Location', 'Where alumni live: country, state and city counts.', 'manage'],
        'company_industry' => ['Company and industry', 'Where alumni work: counts by industry and company.', 'manage'],
        'engagement' => ['Engagement', 'Engagement score, level and recent activity for each alumnus.', 'manage'],
        'event' => ['Events', 'Registrations, attendance, no-shows and feedback for each event.', 'manage'],
        'donation' => ['Donations', 'Gifts, refunds and receipts. Anonymous donors are not named.', 'finance'],
    ];

    private const EMPLOYED = ['employed', 'government_employee'];

    public function __construct(
        private AlumniSearch $search,
        private AlumniPrivacy $privacy,
        private AlumniEngagementService $engagement,
        private AlumniDonationService $donations,
    ) {
    }

    public static function exists(string $key): bool
    {
        return isset(self::REPORTS[$key]);
    }

    /** The filter keys a report accepts. */
    public function filterKeys(string $key): array
    {
        return match ($key) {
            'event' => ['status', 'event_type', 'from', 'to'],
            'donation' => ['status', 'campaign_id', 'from', 'to'],
            'career' => array_merge(array_diff(AlumniSearch::FILTERS, ['industry', 'company']), ['company', 'industry', 'current_only']),
            'higher_education' => array_merge(AlumniSearch::FILTERS, ['institution', 'qualification']),
            default => AlumniSearch::FILTERS,
        };
    }

    /**
     * One page of a report.
     *
     * @param  array<string, mixed>  $filters
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>, total: int, summary: array<int, array{label: string, value: mixed}>}
     */
    public function run(int $t, string $key, array $filters, int $page = 1, int $per = 25): array
    {
        $filters = $this->clean($key, $filters);
        $plan = $this->plan($t, $key, $filters);
        $total = (int) DB::query()->fromSub(clone $plan['query'], 'r')->count();
        $rows = $plan['query']->forPage(max(1, $page), $per)->get();

        return [
            'columns' => $plan['columns'], 'rows' => $this->mapRows($plan, $rows), 'total' => $total,
            'summary' => $plan['summary'] ? ($plan['summary'])($total) : [['label' => 'Rows', 'value' => $total]],
        ];
    }

    /**
     * Every row of a report, for export. Null when it is larger than MAX_ROWS (the caller asks for narrower filters).
     *
     * @param  array<string, mixed>  $filters
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>}|null
     */
    public function all(int $t, string $key, array $filters): ?array
    {
        $filters = $this->clean($key, $filters);
        $plan = $this->plan($t, $key, $filters);
        if ((int) DB::query()->fromSub(clone $plan['query'], 'r')->count() > self::MAX_ROWS) {
            return null;
        }
        $out = [];
        for ($page = 1; ; $page++) {
            $chunk = (clone $plan['query'])->forPage($page, 500)->get();
            foreach ($this->mapRows($plan, $chunk) as $row) {
                $out[] = $row;
            }
            if ($chunk->count() < 500) {
                break;
            }
        }

        return ['columns' => $plan['columns'], 'rows' => $out];
    }

    /** A cell safe to open in a spreadsheet: text that starts like a formula is neutralised. */
    public static function safeCell($value)
    {
        if (! is_string($value)) {
            return $value;
        }
        if (preg_match('/^\+[0-9][0-9 ()\-]*$/', $value)) {
            return $value;   // an international phone number: digits and separators only, it cannot call a function
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    // ------------------------------------------------------------------- plans

    /** @param array<string, mixed> $f */
    private function plan(int $t, string $key, array $f): array
    {
        $plan = match ($key) {
            'master' => $this->master($t, $f),
            'batch' => $this->batch($t, $f),
            'career' => $this->career($t, $f),
            'higher_education' => $this->higherEducation($t, $f),
            'location' => $this->location($t, $f),
            'company_industry' => $this->companyIndustry($t, $f),
            'engagement' => $this->engagementPlan($t, $f),
            'event' => $this->events($t, $f),
            'donation' => $this->donationPlan($t, $f),
        };

        return $plan + ['map' => null, 'summary' => null];
    }

    private function cols(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $k => $label) {
            $out[] = ['key' => $k, 'label' => $label];
        }

        return $out;
    }

    private function master(int $t, array $f): array
    {
        $q = $this->search->filtered($t, $f)->orderBy('id');
        $columns = $this->cols(['alumni_code' => 'Alumni ID', 'full_name' => 'Name', 'batch' => 'Batch', 'passing_year' => 'Passing year', 'course' => 'Course', 'mobile' => 'Mobile', 'email' => 'Email',
            'city' => 'City', 'country' => 'Country', 'employment_status' => 'Employment status', 'current_company' => 'Company', 'current_job_title' => 'Job role', 'status' => 'Status', 'profile_completion' => 'Profile completion %']);

        return ['query' => $q, 'columns' => $columns, 'map' => fn (array $rows) => $this->maskedRows($t, $rows, array_column($columns, 'key'))];
    }

    private function batch(int $t, array $f): array
    {
        $q = $this->search->filtered($t, $f)->whereNotNull('batch')->where('batch', '!=', '')->groupBy('batch')
            ->selectRaw("batch, MIN(passing_year) as passing_year, COUNT(*) as total, SUM(CASE WHEN status IN ('verified','active') THEN 1 ELSE 0 END) as verified_active, ROUND(AVG(profile_completion)) as avg_completion")
            ->orderByRaw('MIN(passing_year) DESC')->orderBy('batch');
        $columns = $this->cols(['batch' => 'Batch', 'passing_year' => 'Passing year', 'total' => 'Alumni', 'verified_active' => 'Verified or active', 'employed' => 'Employed', 'entrepreneurs' => 'Entrepreneurs', 'higher_education' => 'Higher education', 'avg_completion' => 'Avg profile completion %']);

        return ['query' => $q, 'columns' => $columns, 'map' => function (array $rows) use ($t, $f) {
            $batches = array_column($rows, 'batch');
            // employment and higher-education counts leave out alumni who made those fields private
            $emp = $this->search->filtered($t, $f)->whereIn('batch', $batches);
            $this->privacy->scopeVisible($emp, $t, 'employment', 'institution');
            $by = fn (array $statuses) => (clone $emp)->whereIn('employment_status', $statuses)->groupBy('batch')->selectRaw('batch, COUNT(*) as n')->pluck('n', 'batch');
            $employed = $by(self::EMPLOYED);
            $entre = $by(['entrepreneur', 'self_employed']);
            $he = $this->search->filtered($t, $f)->whereIn('batch', $batches)
                ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('alumni_education as ed')->whereColumn('ed.alumni_id', 'alumni.id')->where('ed.sub_institute_id', $t));
            $this->privacy->scopeVisible($he, $t, 'higher_education', 'institution');
            $higher = $he->groupBy('batch')->selectRaw('batch, COUNT(*) as n')->pluck('n', 'batch');
            foreach ($rows as &$r) {
                $r['employed'] = (int) ($employed[$r['batch']] ?? 0);
                $r['entrepreneurs'] = (int) ($entre[$r['batch']] ?? 0);
                $r['higher_education'] = (int) ($higher[$r['batch']] ?? 0);
            }

            return $rows;
        }];
    }

    private function career(int $t, array $f): array
    {
        $company = $f['company'] ?? null;
        $industry = $f['industry'] ?? null;
        $currentOnly = ! empty($f['current_only']);
        unset($f['company'], $f['industry'], $f['current_only']);   // here these mean the job row, not the alumnus's current-company column

        $visible = DB::table('alumni')->where('sub_institute_id', $t)->select('id');
        $this->privacy->scopeVisible($visible, $t, 'employment', 'institution');
        $q = DB::table('alumni_career as c')->join('alumni as a', fn ($j) => $j->on('a.id', '=', 'c.alumni_id')->on('a.sub_institute_id', '=', 'c.sub_institute_id'))
            ->where('c.sub_institute_id', $t)->whereIn('c.alumni_id', $this->search->filtered($t, $f)->select('id'))->whereIn('c.alumni_id', $visible)
            ->when($company, fn ($x) => $x->where('c.company_name', 'like', $this->like($company)))
            ->when($industry, fn ($x) => $x->where('c.industry', $industry))
            ->when($currentOnly, fn ($x) => $x->where('c.is_current', 1))
            ->orderByDesc('c.is_current')->orderBy('a.full_name')->orderByDesc('c.start_date')
            ->select('a.alumni_code', 'a.full_name', 'a.batch', 'c.company_name', 'c.industry', 'c.job_title', 'c.employment_type', 'c.start_date', 'c.end_date', 'c.is_current', 'c.location', 'c.country');   // no salary_range: never reported

        return ['query' => $q, 'columns' => $this->cols(['alumni_code' => 'Alumni ID', 'full_name' => 'Name', 'batch' => 'Batch', 'company_name' => 'Company', 'industry' => 'Industry', 'job_title' => 'Job role', 'employment_type' => 'Type',
            'start_date' => 'From', 'end_date' => 'To', 'current' => 'Current', 'location' => 'Location', 'country' => 'Country']),
            'map' => fn (array $rows) => array_map(function ($r) {
                $r['current'] = ! empty($r['is_current']) ? 'Yes' : 'No';
                unset($r['is_current']);

                return $r;
            }, $rows)];
    }

    private function higherEducation(int $t, array $f): array
    {
        $institution = $f['institution'] ?? null;
        $qualification = $f['qualification'] ?? null;
        unset($f['institution'], $f['qualification']);
        $visible = DB::table('alumni')->where('sub_institute_id', $t)->select('id');
        $this->privacy->scopeVisible($visible, $t, 'higher_education', 'institution');
        $q = DB::table('alumni_education as e')->join('alumni as a', fn ($j) => $j->on('a.id', '=', 'e.alumni_id')->on('a.sub_institute_id', '=', 'e.sub_institute_id'))
            ->where('e.sub_institute_id', $t)->whereIn('e.alumni_id', $this->search->filtered($t, $f)->select('id'))->whereIn('e.alumni_id', $visible)
            ->when($institution, fn ($x) => $x->where('e.institution', 'like', $this->like($institution)))
            ->when($qualification, fn ($x) => $x->where('e.qualification', 'like', $this->like($qualification)))
            ->orderBy('a.full_name')->orderByDesc('e.passing_year')
            ->select('a.alumni_code', 'a.full_name', 'a.batch', 'e.qualification', 'e.course', 'e.specialization', 'e.institution', 'e.country', 'e.admission_year', 'e.passing_year', 'e.current_status');

        return ['query' => $q, 'columns' => $this->cols(['alumni_code' => 'Alumni ID', 'full_name' => 'Name', 'batch' => 'Batch', 'qualification' => 'Qualification', 'course' => 'Course', 'specialization' => 'Specialization',
            'institution' => 'Institution', 'country' => 'Country', 'admission_year' => 'From', 'passing_year' => 'To', 'current_status' => 'Status'])];
    }

    private function location(int $t, array $f): array
    {
        $q = $this->search->filtered($t, $f);
        $this->privacy->scopeVisible($q, $t, 'location', 'institution');
        $q->whereNotNull('country')->where('country', '!=', '')->groupBy('country', 'state', 'city')->selectRaw('country, state, city, COUNT(*) as total')->orderByDesc('total')->orderBy('country')->orderBy('state')->orderBy('city');

        return ['query' => $q, 'columns' => $this->cols(['country' => 'Country', 'state' => 'State', 'city' => 'City', 'total' => 'Alumni'])];
    }

    private function companyIndustry(int $t, array $f): array
    {
        $q = $this->search->filtered($t, $f);
        $this->privacy->scopeVisible($q, $t, 'employment', 'institution');
        $q->whereNotNull('current_company')->where('current_company', '!=', '')->groupBy('current_industry', 'current_company')
            ->selectRaw('current_industry as industry, current_company as company, COUNT(*) as total')->orderByDesc('total')->orderBy('current_company');

        return ['query' => $q, 'columns' => $this->cols(['industry' => 'Industry', 'company' => 'Company', 'total' => 'Alumni']),
            'map' => fn (array $rows) => array_map(function ($r) {
                $r['industry'] = $r['industry'] ?: 'Not specified';

                return $r;
            }, $rows)];
    }

    private function engagementPlan(int $t, array $f): array
    {
        $since = now()->subMonths($this->engagement->windowMonths($t));
        $pts = DB::table('alumni_activity')->where('sub_institute_id', $t)->where('points', '>', 0)->where('occurred_at', '>=', $since)
            ->groupBy('alumni_id')->selectRaw('alumni_id, SUM(points) as pts, COUNT(*) as acts, MAX(occurred_at) as last_at');
        $q = DB::table('alumni as a')->leftJoinSub($pts, 'p', 'p.alumni_id', '=', 'a.id')->where('a.sub_institute_id', $t)
            ->whereIn('a.id', $this->search->filtered($t, $f)->select('id'))
            ->orderByRaw('COALESCE(p.pts, 0) DESC')->orderBy('a.id')
            ->selectRaw('a.alumni_code, a.full_name, a.batch, COALESCE(p.pts, 0) as pts, COALESCE(p.acts, 0) as activities, p.last_at');

        return ['query' => $q, 'columns' => $this->cols(['alumni_code' => 'Alumni ID', 'full_name' => 'Name', 'batch' => 'Batch', 'score' => 'Score (0-100)', 'level' => 'Level', 'activities' => 'Activities in window', 'last_at' => 'Last activity']),
            'map' => function (array $rows) use ($t) {
                foreach ($rows as &$r) {
                    $r['score'] = (int) min(100, (int) $r['pts']);
                    $r['level'] = ucfirst(str_replace('_', ' ', $this->engagement->level($t, $r['score'])));
                    unset($r['pts']);
                }

                return $rows;
            }];
    }

    private function events(int $t, array $f): array
    {
        $count = fn (array $statuses) => DB::table('alumni_event_registrations as r')->whereColumn('r.event_id', 'e.id')->where('r.sub_institute_id', $t)->whereIn('r.status', $statuses)->selectRaw('COUNT(*)');
        $q = DB::table('alumni_events as e')->where('e.sub_institute_id', $t)
            ->when($f['status'] ?? null, fn ($x, $v) => $x->where('e.status', $v))->when($f['event_type'] ?? null, fn ($x, $v) => $x->where('e.event_type', $v))
            ->when($f['from'] ?? null, fn ($x, $v) => $x->where('e.event_date', '>=', $v))->when($f['to'] ?? null, fn ($x, $v) => $x->where('e.event_date', '<=', $v))
            ->orderByDesc('e.event_date')->orderByDesc('e.id')
            ->select('e.name', 'e.event_type', 'e.event_date', 'e.status', 'e.capacity')
            ->selectSub($count(['registered', 'confirmed', 'attended', 'no_show']), 'registered')->selectSub($count(['confirmed']), 'confirmed')->selectSub($count(['waitlisted']), 'waitlisted')
            ->selectSub($count(['attended']), 'attended')->selectSub($count(['no_show']), 'no_show')
            ->selectSub(DB::table('alumni_event_feedback as fb')->whereColumn('fb.event_id', 'e.id')->where('fb.sub_institute_id', $t)->selectRaw('ROUND(AVG(fb.rating), 1)'), 'rating');

        return ['query' => $q, 'columns' => $this->cols(['name' => 'Event', 'event_type' => 'Type', 'event_date' => 'Date', 'status' => 'Status', 'capacity' => 'Capacity', 'registered' => 'Registered', 'confirmed' => 'Confirmed',
            'waitlisted' => 'Waiting list', 'attended' => 'Attended', 'no_show' => 'No-shows', 'attendance_rate' => 'Attendance %', 'rating' => 'Avg rating']),
            'map' => function (array $rows) {
                foreach ($rows as &$r) {
                    $r['event_type'] = ucfirst(str_replace('_', ' ', (string) $r['event_type']));
                    $r['attendance_rate'] = (int) $r['registered'] > 0 ? (int) round((int) $r['attended'] / (int) $r['registered'] * 100) : null;
                }

                return $rows;
            }];
    }

    private function donationPlan(int $t, array $f): array
    {
        $q = DB::table('alumni_donations as d')->join('alumni as a', fn ($j) => $j->on('a.id', '=', 'd.alumni_id')->on('a.sub_institute_id', '=', 'd.sub_institute_id'))
            ->leftJoin('alumni_donation_campaigns as c', 'c.id', '=', 'd.campaign_id')->where('d.sub_institute_id', $t)
            ->when($f['status'] ?? null, fn ($x, $v) => $x->where('d.status', $v))->when($f['campaign_id'] ?? null, fn ($x, $v) => $x->where('d.campaign_id', (int) $v))
            ->when($f['from'] ?? null, fn ($x, $v) => $x->whereRaw('COALESCE(d.received_on, d.pledged_on) >= ?', [$v]))->when($f['to'] ?? null, fn ($x, $v) => $x->whereRaw('COALESCE(d.received_on, d.pledged_on) <= ?', [$v]))
            ->orderByDesc('d.id')
            ->select('d.id', 'a.alumni_code', 'a.full_name', 'd.anonymous', 'c.name as campaign', 'd.amount', 'd.refunded_amount', 'd.status', 'd.payment_mode', 'd.received_on', 'd.pledged_on', 'd.receipt_no');

        return ['query' => $q, 'columns' => $this->cols(['date' => 'Date', 'receipt_no' => 'Receipt', 'alumni_code' => 'Alumni ID', 'full_name' => 'Donor', 'campaign' => 'Campaign', 'amount' => 'Amount', 'refunded_amount' => 'Refunded', 'net' => 'Net', 'status' => 'Status', 'payment_mode' => 'Mode']),
            'map' => function (array $rows) {
                foreach ($rows as &$r) {
                    if (! empty($r['anonymous'])) {
                        $r['full_name'] = 'Anonymous donor';
                        $r['alumni_code'] = null;
                    }
                    $r['date'] = $r['received_on'] ?: $r['pledged_on'];
                    $r['net'] = in_array($r['status'], ['verified', 'partially_refunded', 'refunded'], true) ? round((float) $r['amount'] - (float) $r['refunded_amount'], 2) : null;
                    $r['status'] = ucfirst(str_replace('_', ' ', (string) $r['status']));
                    unset($r['anonymous'], $r['received_on'], $r['pledged_on'], $r['id']);
                }

                return $rows;
            },
            'summary' => function (int $total) use ($t, $f) {
                $s = $this->donations->summary($t, array_intersect_key($f, array_flip(['campaign_id', 'from', 'to'])));

                return [['label' => 'Rows', 'value' => $total], ['label' => 'Raised (verified, net)', 'value' => $s['raised']], ['label' => 'Awaiting verification', 'value' => $s['pending_amount']], ['label' => 'Pledged', 'value' => $s['pledged_amount']]];
            }];
    }

    // ----------------------------------------------------------------- helpers

    /** @param array<string, mixed> $filters */
    private function clean(string $key, array $filters): array
    {
        $out = [];
        foreach ($this->filterKeys($key) as $k) {
            if (isset($filters[$k]) && is_scalar($filters[$k]) && trim((string) $filters[$k]) !== '') {
                $out[$k] = is_string($filters[$k]) ? trim($filters[$k]) : $filters[$k];
            }
        }

        return $out;
    }

    private function like(string $v): string
    {
        return '%' . str_replace(['%', '_'], ['\%', '\_'], $v) . '%';
    }

    /** Rows as plain arrays, run through the plan's mapper. */
    private function mapRows(array $plan, $rows): array
    {
        $arr = [];
        foreach ($rows as $r) {
            $arr[] = (array) $r;
        }

        return $plan['map'] ? ($plan['map'])($arr) : $arr;
    }

    /**
     * Mask a page of alumni rows for the institution viewer and keep only the report's columns. The query selects
     * everything (the privacy groups need email, city, ...), so the keep-list also stops stray columns reaching a client.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $keep
     */
    private function maskedRows(int $t, array $rows, array $keep): array
    {
        $vis = $this->privacy->visibilityFor($t, array_map(fn ($r) => (int) $r['id'], $rows));
        $out = [];
        foreach ($rows as $r) {
            [$m] = $this->privacy->mask($r, $vis[(int) $r['id']], 'institution');
            $out[] = array_intersect_key($m, array_flip($keep));
        }

        return $out;
    }
}
