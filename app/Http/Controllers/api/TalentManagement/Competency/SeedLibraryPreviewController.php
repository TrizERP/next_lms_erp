<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\SeedLibraryPreviewController`.
 *
 * WHAT AN IMPORT WOULD GIVE YOU, BEFORE YOU RUN IT. Reports the tenant's
 * current job-role/skill counts against the global seed library, plus how
 * much of the tenant's existing KASBA vocabulary the (skill-only) seed
 * library can actually resolve. It reports; it imports nothing.
 *
 * Source used `ResolvesApiIdentity` (Sanctum token identity); this port uses
 * this namespace's own `ResolvesCompetencyContext::competencyContext()`
 * instead, per this session's established convention (see
 * CompetencyLibraryDependantsController's docblock) - identical
 * `sub_institute_id` shape, no behaviour change. All five tables this
 * queries (`s_jobrole`, `master_skills`, `s_user_jobrole`, `s_users_skills`,
 * `competency_kasba_item`) already exist in this target with the columns
 * read here.
 */
class SeedLibraryPreviewController extends Controller
{
    use ResolvesCompetencyContext;

    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        $tenant = $context['sub_institute_id'];

        // ── what the GLOBAL library offers ──────────────────────────────────
        $globalRoles  = DB::table('s_jobrole')->count();
        $globalSkills = DB::table('master_skills')->count();

        // ── what this tenant already holds ──────────────────────────────────
        $ownRoles  = DB::table('s_user_jobrole')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->count();
        $ownSkills = DB::table('s_users_skills')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->count();

        // ── OVERLAP, by name, because that is how an import would match ─────
        // A name match is a CANDIDATE for reconciliation, never a confirmed
        // duplicate.
        $roleOverlap = DB::table('s_user_jobrole as t')
            ->join('s_jobrole as g', 'g.jobrole', '=', 't.jobrole')
            ->where('t.sub_institute_id', $tenant)->whereNull('t.deleted_at')
            ->distinct()->count('t.jobrole');

        $skillOverlap = DB::table('s_users_skills as t')
            ->join('master_skills as g', 'g.title', '=', 't.title')
            ->where('t.sub_institute_id', $tenant)->whereNull('t.deleted_at')
            ->distinct()->count('t.title');

        // ── THE VOCABULARY-DISTANCE NUMBER ───────────────────────────────────
        // How much of this tenant's KASBA vocabulary the skill library can
        // actually satisfy.
        $items = DB::table('competency_kasba_item')->where('sub_institute_id', $tenant)->count();
        $target = DB::table('competency_kasba_item')->where('sub_institute_id', $tenant)
            ->whereNotNull('item_id')->count();

        $byDimension = DB::table('competency_kasba_item')
            ->where('sub_institute_id', $tenant)
            ->select('kasba_type', DB::raw('count(*) total'), DB::raw('sum(item_id is not null) resolved'))
            ->groupBy('kasba_type')->get()
            ->map(fn ($r) => [
                'dimension' => $r->kasba_type,
                'items'     => (int) $r->total,
                'resolved'  => (int) $r->resolved,
            ])->all();

        return response()->json(['status' => 1, 'data' => [
            'global'  => ['job_roles' => $globalRoles, 'skills' => $globalSkills],
            'tenant'  => ['job_roles' => $ownRoles, 'skills' => $ownSkills],
            'name_match_candidates' => ['job_roles' => $roleOverlap, 'skills' => $skillOverlap],
            'vocabulary' => [
                'kasba_items'   => $items,
                'resolved'      => $target,
                'held_as_label' => $items - $target,
                'percent_resolved' => $items > 0 ? round(100 * $target / $items, 1) : null,
                'by_dimension'  => $byDimension,
            ],
            'expectation' => $items === 0
                ? 'No competencies defined yet, so there is nothing to measure the library against.'
                : 'Four of the five KASBA dimensions are not skills. A skill library cannot '
                  . 'resolve knowledge, attitude, behaviour or ability items, so most items will '
                  . 'arrive as labels. That is the normal first state, not an import failure.',
        ]]);
    }
}
