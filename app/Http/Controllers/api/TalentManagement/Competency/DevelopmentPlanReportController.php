<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\DevelopmentPlanReportController`.
 *
 * The development plan report - read-only over `s_competency_development_plans`
 * joined to `competency` (the customer-authored competency catalogue this
 * target uses, created by `2026_08_20_101000_create_competency_task_map_tables.php`).
 * Logic and response shape are unchanged from the source; only tenant/actor
 * context resolution was adapted to the hydrated session, per
 * `ResolvesCompetencyContext`.
 */
class DevelopmentPlanReportController extends Controller
{
    use ResolvesCompetencyContext;

    /** Statuses actually present in the data, measured rather than assumed. */
    private const STATUSES = ['active', 'completed', 'on_hold', 'overdue'];

    /**
     * GET /api/competency/reports/development-plans
     *
     * Every figure is scoped to the caller's own tenant, resolved from the
     * session rather than a request field.
     */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid = (int) $context['sub_institute_id'];

        $base = fn () => DB::table('s_competency_development_plans')
            // QUALIFIED. The plans list LEFT JOINs `competency`, which also has
            // a sub_institute_id, and an unqualified filter is ambiguous the
            // moment the join is added.
            ->where('s_competency_development_plans.sub_institute_id', $sid)
            ->whereNull('s_competency_development_plans.deleted_at');

        $total = $base()->count();

        $counts = $base()->select('s_competency_development_plans.status as status')
            ->selectRaw('COUNT(*) as n')->groupBy('s_competency_development_plans.status')->pluck('n', 'status')->all();
        $byStatus = [];
        foreach (self::STATUSES as $s) {
            $byStatus[$s] = (int) ($counts[$s] ?? 0);
        }
        $other = array_sum($counts) - array_sum($byStatus);
        if ($other > 0) {
            $byStatus['other'] = $other;   // never silently dropped
        }

        $overdue = $base()
            ->whereNotNull('s_competency_development_plans.due_date')
            ->whereDate('s_competency_development_plans.due_date', '<', now())
            ->whereNotIn('s_competency_development_plans.status', ['completed'])->count();

        $rows = $base()
            ->leftJoin('competency as c', 'c.id', '=', 's_competency_development_plans.competency_id')
            ->orderByRaw("FIELD(s_competency_development_plans.status,'overdue','on_hold','active','completed')")
            ->orderBy('s_competency_development_plans.due_date')
            ->limit(500)
            ->get([
                's_competency_development_plans.id',
                's_competency_development_plans.title',
                's_competency_development_plans.user_id',
                's_competency_development_plans.jobrole',
                's_competency_development_plans.status',
                's_competency_development_plans.progress',
                's_competency_development_plans.start_date',
                's_competency_development_plans.due_date',
                's_competency_development_plans.completed_at',
                'c.name as competency_name',
            ]);

        return response()->json([
            'status' => 1,
            'data'   => [
                'total'      => $total,
                'by_status'  => $byStatus,
                'overdue'    => $overdue,
                'plans'      => $rows,
            ],
            // A tenant with no plans is a normal tenant, not a failed query.
            'empty_is_expected' => $total === 0,
            // Stated so nobody reads 500 as "all of them".
            'truncated' => $total > 500,
        ]);
    }
}
