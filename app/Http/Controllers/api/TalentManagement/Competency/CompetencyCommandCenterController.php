<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use App\Services\Competency\CommandCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * `filters()` ported from G2G's `App\Services\Competency\CommandCenterService::filterOptions()`.
 *
 * The Competency Command Center screen itself was originally out of scope for
 * this port (see this class's earlier doc, preserved below), but the
 * Capability Intelligence Dashboard screen needs the full Command Center
 * payload, so `index()` (ported from G2G's
 * `App\Http\Controllers\Api\Competency\CommandCenterController::index()` +
 * `App\Services\Competency\CommandCenterService::dashboard()`, the latter
 * ported into `App\Services\Competency\CommandCenterService` in this project)
 * was added alongside it.
 *
 * Original doc, still true of `filters()`: the Development & Career Paths
 * workspace's `useWorkspaceLookups()` hook calls
 * `GET /competency/command-center/filters` purely to populate its
 * "Department" dropdown — ported without pulling in the rest of the Command
 * Center dashboard. Logic/field names/response shape are unchanged from the
 * source.
 */
class CompetencyCommandCenterController extends Controller
{
    use ResolvesCompetencyContext;

    public function __construct(private CommandCenterService $service)
    {
    }

    /** GET /competency/command-center */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $filters = $this->competencyFilters($request);

        $data = $this->service->dashboard(
            $context['sub_institute_id'],
            $context['user_id'],
            $filters
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Competency command center fetched successfully',
            'data'    => $data,
        ]);
    }

    public function filters(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = (int) $context['sub_institute_id'];

        $roleBase = fn () => DB::table('s_user_jobrole')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $departments = $roleBase()
            ->whereNotNull('department_id')
            ->where('department_id', '!=', 0)
            ->select('department_id', 'department')
            ->distinct()
            ->orderBy('department')
            ->get()
            ->map(fn ($r) => ['value' => (string) $r->department_id, 'label' => $r->department ?: ('Department ' . $r->department_id)])
            ->unique('value')
            ->values()
            ->all();

        $simpleOptions = function (string $column) use ($roleBase) {
            return $roleBase()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->select($column)
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v])
                ->all();
        };

        return response()->json([
            'status'  => 1,
            'message' => 'Filter options fetched successfully',
            'data'    => [
                'departments'    => $departments,
                'jobroles'       => $simpleOptions('jobrole'),
                'locations'      => $simpleOptions('location'),
                'business_units' => $simpleOptions('industries'),
                'job_families'   => $simpleOptions('jobrole_category'),
            ],
        ]);
    }
}
