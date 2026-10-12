<?php

namespace App\Services\Alumni;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one definition of "which alumni match these filters" for the staff list, bulk actions and exports,
 * so a list and the export of that list can never disagree. Always scoped to the tenant.
 */
class AlumniSearch
{
    public const FILTERS = ['q', 'status', 'batch', 'passing_year', 'city', 'state', 'country', 'campus', 'course', 'department',
        'employment_status', 'industry', 'company', 'job_role', 'skill', 'higher_education', 'source', 'admission_year', 'passing_year_from', 'passing_year_to'];

    /** @param array<string, mixed> $in */
    public function filtered(int $t, array $in): Builder
    {
        $filled = fn (string $k) => isset($in[$k]) && trim((string) $in[$k]) !== '';
        $like = fn (string $v) => '%' . str_replace(['%', '_'], ['\%', '\_'], $v) . '%';
        $q = DB::table('alumni')->where('sub_institute_id', $t);

        if ($filled('q')) {
            $l = $like(trim((string) $in['q']));
            $q->where(function ($w) use ($l) {
                foreach (['full_name', 'alumni_code', 'email', 'mobile', 'gr_no', 'enrollment_no'] as $c) {
                    $w->orWhere($c, 'like', $l);
                }
                $w->orWhereRaw('CAST(student_id AS CHAR) like ?', [$l]);
            });
        }
        foreach (['status', 'batch', 'passing_year', 'city', 'state', 'country', 'campus', 'course', 'department', 'employment_status', 'source', 'admission_year'] as $c) {
            if ($filled($c)) {
                $q->where($c, $in[$c]);
            }
        }
        if ($filled('passing_year_from')) {
            $q->where('passing_year', '>=', (int) $in['passing_year_from']);
        }
        if ($filled('passing_year_to')) {
            $q->where('passing_year', '<=', (int) $in['passing_year_to']);
        }
        if ($filled('industry')) {
            $q->where('current_industry', $in['industry']);
        }
        if ($filled('company')) {
            $q->where('current_company', 'like', $like((string) $in['company']));
        }
        if ($filled('job_role')) {
            $q->where('current_job_title', 'like', $like((string) $in['job_role']));
        }
        if ($filled('skill')) {
            $skill = $like(mb_strtolower((string) $in['skill']));
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('alumni_skills as sk')
                ->whereColumn('sk.alumni_id', 'alumni.id')->where('sk.sub_institute_id', $t)
                ->whereRaw('LOWER(sk.skill) like ?', [$skill]));
        }
        if ($filled('higher_education')) {
            $he = $like((string) $in['higher_education']);
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('alumni_education as ed')
                ->whereColumn('ed.alumni_id', 'alumni.id')->where('ed.sub_institute_id', $t)
                ->where(fn ($x) => $x->where('ed.qualification', 'like', $he)->orWhere('ed.course', 'like', $he)->orWhere('ed.institution', 'like', $he)));
        }

        return $q;
    }
}
