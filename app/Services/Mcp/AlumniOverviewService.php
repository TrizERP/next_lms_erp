<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni, as the Alumni Management module records them.
 *
 * WHAT IS DELIBERATELY NOT RETURNED
 *
 * Alumni carry consent flags per channel (`consent_email`, `consent_sms`, …) and the
 * module has a privacy table. So this returns who the person is professionally — name,
 * batch, passing year, status, employer and role — and NEVER an email address, mobile
 * number, home address, date of birth or photograph. Donations are reported in totals and
 * never against a named alumnus, because an alumnus may have given anonymously
 * (`alumni_donations.anonymous`).
 *
 * STATUS WORDS ARE REPORTED AS STORED
 *
 * The status columns on events, mentorship requests, opportunities, surveys and donations
 * are grouped and counted as they are written; this class does not decide which of them
 * mean "open" or "closed".
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every table read.
 */
class AlumniOverviewService
{
    /**
     * Alumni, most recent passing year first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function directory(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('alumni')) {
            return ['count' => 0, 'alumni' => [], 'note' => 'Alumni are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = DB::table('alumni as a')->where('a.sub_institute_id', $context->selectedInstituteId);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $employed = (clone $query)->whereNotNull('a.current_company')->where('a.current_company', '<>', '')->count();

        $rows = $query
            ->orderByDesc('a.passing_year')
            ->orderBy('a.full_name')
            ->limit($limit)
            ->get(['a.id', 'a.alumni_code', 'a.full_name', 'a.batch', 'a.passing_year', 'a.status',
                'a.employment_status', 'a.current_company', 'a.current_job_title', 'a.current_industry', 'a.city']);

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'with_employer_recorded' => $employed,
            'figures_cover' => 'every alumnus matching these filters, not only the rows listed',
            'alumni' => $rows->map(static fn ($row) => [
                'alumni_id' => (int) $row->id,
                'alumni_code' => $row->alumni_code,
                'name' => $row->full_name,
                'batch' => $row->batch,
                'passing_year' => $row->passing_year,
                'status' => $row->status,
                'employment_status' => $row->employment_status,
                'employer' => $row->current_company,
                'job_title' => $row->current_job_title,
                'industry' => $row->current_industry,
                'city' => $row->city,
            ])->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Alumni events with how many registrations each has.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function events(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('alumni_events')) {
            return ['count' => 0, 'events' => [], 'note' => 'Alumni events are not recorded in this estate.'];
        }

        $institute = $context->selectedInstituteId;
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('alumni_events as e')->where('e.sub_institute_id', $institute);

        if (! empty($filters['status'])) {
            $query->where('e.status', (string) $filters['status']);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('e.event_date', $operator, $date);
            }
        }

        $total = (clone $query)->count();

        $rows = $query
            ->leftJoin('alumni_event_registrations as r', function ($join) use ($institute) {
                $join->on('r.event_id', '=', 'e.id')->where('r.sub_institute_id', '=', $institute);
            })
            ->groupBy('e.id', 'e.name', 'e.event_type', 'e.event_date', 'e.venue', 'e.mode', 'e.capacity', 'e.status')
            ->selectRaw('e.id, e.name, e.event_type, e.event_date, e.venue, e.mode, e.capacity, e.status,
                COUNT(r.id) AS registrations')
            ->orderByDesc('e.event_date')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'events' => $rows->map(static fn ($row) => [
                'event_id' => (int) $row->id,
                'name' => $row->name,
                'event_type' => $row->event_type,
                'event_date' => $row->event_date,
                'venue' => $row->venue,
                'mode' => $row->mode,
                'capacity' => $row->capacity,
                'status' => $row->status,
                'registrations' => (int) $row->registrations,
            ])->all(),
            'rule' => 'One row is one alumni event. Registrations counts every registration row for the event, '
                .'whatever its own status, and attendance is not recorded here, so never say how many people '
                .'attended.',
        ];
    }

    /**
     * Alumni counted by status, passing year and employment status, with the engagement
     * activity around them.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('alumni')) {
            return ['count' => 0, 'note' => 'Alumni are not recorded in this estate.'];
        }

        $institute = $context->selectedInstituteId;
        $base = fn () => DB::table('alumni as a')->where('a.sub_institute_id', $institute);

        $group = static fn (Builder $q, string $column, string $alias, int $limit = 20) => $q
            ->selectRaw("COALESCE(NULLIF(TRIM($column), ''), '(not recorded)') AS $alias, COUNT(*) AS alumni")
            ->groupByRaw("COALESCE(NULLIF(TRIM($column), ''), '(not recorded)')")
            ->orderByDesc('alumni')
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [$alias => $row->$alias, 'alumni' => (int) $row->alumni])
            ->all();

        return [
            'count' => $base()->count(),
            'by_status' => $group($base(), 'a.status', 'status'),
            'by_passing_year' => $group($base(), 'a.passing_year', 'passing_year'),
            'by_employment_status' => $group($base(), 'a.employment_status', 'employment_status'),
            'engagement' => [
                'events' => $this->countByStatus('alumni_events', $institute),
                'mentorship_requests' => $this->countByStatus('alumni_mentorship_requests', $institute),
                'opportunities' => $this->countByStatus('alumni_opportunities', $institute),
                'surveys' => $this->countByStatus('alumni_surveys', $institute),
                'donations' => $this->donations($institute),
            ],
            'rule' => $this->rule(),
        ];
    }

    private function rule(): string
    {
        return 'One row is one alumnus. Email, mobile, address, date of birth and photograph are NOT provided '
            .'and must not be asked for or guessed: alumni give consent per channel and that consent is not '
            .'part of this view. Statuses are shown as stored. Donations are totals only and are never '
            .'attributed to a named alumnus, because a donor may have given anonymously.';
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['status' => 'a.status', 'employment_status' => 'a.employment_status', 'batch' => 'a.batch'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (string) $filters[$filter]);
            }
        }

        if (! empty($filters['passing_year'])) {
            $query->where('a.passing_year', (int) $filters['passing_year']);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('a.full_name', 'like', $needle)
                    ->orWhere('a.current_company', 'like', $needle)
                    ->orWhere('a.current_job_title', 'like', $needle);
            });
        }
    }

    /** @return array<string, mixed> */
    private function countByStatus(string $table, int $institute): array
    {
        if (! Schema::hasTable($table)) {
            return ['total' => 0, 'by_status' => []];
        }

        $rows = DB::table($table)->where('sub_institute_id', $institute)
            ->selectRaw("COALESCE(NULLIF(TRIM(status), ''), '(not recorded)') AS status, COUNT(*) AS n")
            ->groupByRaw("COALESCE(NULLIF(TRIM(status), ''), '(not recorded)')")
            ->orderByDesc('n')
            ->get();

        return [
            'total' => (int) $rows->sum('n'),
            'by_status' => $rows->map(static fn ($row) => ['status' => $row->status, 'count' => (int) $row->n])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function donations(int $institute): array
    {
        if (! Schema::hasTable('alumni_donations')) {
            return ['total' => 0, 'by_status' => []];
        }

        $rows = DB::table('alumni_donations')->where('sub_institute_id', $institute)
            ->selectRaw("COALESCE(NULLIF(TRIM(status), ''), '(not recorded)') AS status, COUNT(*) AS n, SUM(amount) AS amount")
            ->groupByRaw("COALESCE(NULLIF(TRIM(status), ''), '(not recorded)')")
            ->orderByDesc('n')
            ->get();

        return [
            'total' => (int) $rows->sum('n'),
            'by_status' => $rows->map(static fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->n,
                'amount' => (float) $row->amount,
            ])->all(),
        ];
    }
}
