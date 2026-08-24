<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\onboarding\OnboardingModuleModel;
use App\Models\onboarding\OnboardingProgressModel;
use App\Models\onboarding\OnboardingStepModel;
use App\Services\Onboarding\OnboardingProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Module-wise onboarding API.
 *
 * Auth: the `api.session` middleware (real JWT validation + session hydration).
 * Deliberately NOT the `session` + `type=API` convention used by the legacy
 * screens — that pair short-circuits both SessionMiddleware and checkPermission
 * on caller-supplied input, and the onboarding surface must not inherit it.
 *
 * Tenant scope comes from the validated token payload via the hydrated session,
 * never from request input, so a caller cannot read another school's journey.
 */
class OnboardingApiController extends Controller
{
    private OnboardingProgressService $progress;

    public function __construct(OnboardingProgressService $progress)
    {
        $this->progress = $progress;
    }

    /**
     * GET api/onboarding-modules/overview
     * Every module the tenant can onboard, each with a derived progress roll-up.
     */
    public function overview(Request $request)
    {
        [$subInstituteId, $syear] = $this->context();

        $overview = $this->progress->tenantOverview($subInstituteId, $syear);

        return $this->success('Onboarding overview loaded.', [
            'modules' => $overview['modules'],
            'summary' => $overview['summary'],
            'context' => [
                'sub_institute_id' => $subInstituteId,
                'syear' => $syear,
                'school_name' => session()->get('school_name'),
                'current_user_name' => trim((string) session()->get('name')) ?: session()->get('user_name'),
            ],
        ]);
    }

    /**
     * GET api/onboarding-modules/modules/{moduleKey}
     * One module's full journey: ordered steps, derived status, help resources.
     */
    public function show(Request $request, string $moduleKey)
    {
        [$subInstituteId, $syear] = $this->context();

        $module = $this->resolveModule($moduleKey, $subInstituteId);
        if (! $module) {
            return $this->failure('Onboarding journey not found for this module.', 404);
        }

        $journey = $this->progress->moduleJourney($module, $subInstituteId, $syear);

        return $this->success('Module journey loaded.', [
            'module' => $journey['module'],
            'steps' => $journey['steps'],
            'summary' => $journey['summary'],
            'resources' => $this->moduleResources($module, $subInstituteId),
            'context' => [
                'sub_institute_id' => $subInstituteId,
                'syear' => $syear,
                'current_user_name' => trim((string) session()->get('name')) ?: session()->get('user_name'),
            ],
        ]);
    }

    /**
     * POST api/onboarding-modules/steps/{stepId}
     * Record the non-derivable part of a step: sign-off, skip, assignee, notes.
     *
     * A manual `completed` is rejected on a proof-backed step — that is the
     * whole point of derived completion, and silently accepting it would
     * recreate the `erptour` self-reported-flag problem.
     */
    public function updateStep(Request $request, $stepId)
    {
        [$subInstituteId, $syear] = $this->context();

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:' . implode(',', OnboardingProgressModel::STATUSES),
            'notes' => 'nullable|string|max:5000',
            'assigned_to_id' => 'nullable|integer',
            'assigned_to_name' => 'nullable|string|max:160',
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $step = OnboardingStepModel::where('id', $stepId)->where('status', 1)->first();
        if (! $step) {
            return $this->failure('Onboarding step not found.', 404);
        }

        // Writes obey the same visibility rule as reads: a step belonging to a
        // module this school has no menu for cannot be updated either.
        $module = $this->progress->modulesForTenant($subInstituteId)
            ->firstWhere('id', (int) $step->module_id);

        if (! $module) {
            return $this->failure('This onboarding step is not available for your institute.', 403);
        }

        $status = $request->input('status');

        if ($status === 'completed' && $step->proof_type === 'table_rows') {
            return $this->failure(
                "\"{$step->title}\" is completed automatically once the underlying records exist. " .
                'Add the records on the linked screen, or mark the step as skipped if it does not apply.',
                422
            );
        }

        $attributes = array_filter([
            'status' => $status,
            'notes' => $request->input('notes'),
            'assigned_to_id' => $request->input('assigned_to_id'),
            'assigned_to_name' => $request->input('assigned_to_name'),
        ], static fn ($value) => $value !== null);

        $attributes['updated_by_id'] = session()->get('user_id');
        $attributes['updated_by_name'] = trim((string) session()->get('name')) ?: session()->get('user_name');

        DB::transaction(function () use ($step, $subInstituteId, $syear, $attributes) {
            $this->progress->saveStepState($step, $subInstituteId, $syear, $attributes);
        });

        $journey = $this->progress->moduleJourney($module, $subInstituteId, $syear);
        $updated = collect($journey['steps'])->firstWhere('id', (int) $step->id);

        return $this->success('Step updated.', [
            'step' => $updated,
            'summary' => $journey['summary'],
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Tenant + academic year, taken from the JWT-hydrated session only. */
    private function context(): array
    {
        return [
            (int) session()->get('sub_institute_id'),
            (int) session()->get('syear'),
        ];
    }

    /**
     * Same visibility rule as the overview list — a module the school has no
     * menu for is not reachable by URL either, so the two views can never
     * disagree about what this tenant can onboard.
     */
    private function resolveModule(string $moduleKey, int $subInstituteId): ?OnboardingModuleModel
    {
        return $this->progress->findModuleForTenant($moduleKey, $subInstituteId);
    }

    /**
     * Everything the journey needs that already lives in the ERP. Nothing here
     * is new storage — the training links, per-module notes and bulk-import
     * field registry are all pre-existing tables, read tenant-scoped.
     */
    private function moduleResources(OnboardingModuleModel $module, int $subInstituteId): array
    {
        $menuTitle = $module->menu_title;

        $menus = $menuTitle ? $this->moduleSubMenus($menuTitle, $subInstituteId) : [];

        // `sub_institute_id = 0` is the global default; a tenant row overrides it.
        $requirements = DB::table('requirement_gathering')
            ->whereIn('sub_institute_id', [0, $subInstituteId])
            ->when($menuTitle, static fn ($q) => $q->where('menu_name', $menuTitle))
            ->orderBy('sub_institute_id')
            ->get();

        $requirementText = null;
        foreach ($requirements as $row) {
            $requirementText = $row->requirements;
        }

        // `requirement_gathering.requirements` is authored in a rich-text editor
        // and stored as raw HTML. Return plain text: the client renders it as
        // text, so passing markup through would both display tag soup and put
        // unsanitised, user-authored HTML on the wire.
        if ($requirementText !== null) {
            $requirementText = trim(html_entity_decode(
                strip_tags(preg_replace('#<(br|/p|/div|/li)[^>]*>#i', "\n", $requirementText)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            $requirementText = preg_replace("/\n{3,}/", "\n\n", $requirementText);
        }

        // Bulk-import registry backing the "Upload existing data" step.
        $importTables = OnboardingStepModel::where('module_id', $module->id)
            ->where('status', 1)
            ->whereNotNull('proof_table')
            ->pluck('proof_table')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $importFields = $importTables
            ? DB::table('import_table_fields')
                ->whereIn('table_name', $importTables)
                ->orderBy('id')
                ->get()
                ->groupBy('table_name')
                ->map(static fn ($rows) => $rows->map(static fn ($r) => (array) $r)->values())
                ->all()
            : [];

        $responsibilities = DB::table('role_responsibility')
            ->where('module_name', $module->module_name)
            ->orWhere('module_name', $menuTitle)
            ->get()
            ->map(static fn ($row) => ['profile_name' => $row->profile_name, 'text' => $row->text])
            ->all();

        return [
            'menus' => $menus,
            'requirements' => $requirementText,
            'import_fields' => $importFields,
            'responsibilities' => $responsibilities,
            'users' => $this->tenantUsers($subInstituteId),
        ];
    }

    /**
     * The module's sub-menus, in the order and shape the sidebar shows them.
     *
     * `ORDER BY sort_order` is wrong here and was the reason this list came out
     * scrambled: `sort_order` restarts at 1 under every parent, so sorting a
     * group that spans several parents by it interleaves unrelated branches.
     * The rows are ordered by the sidebar walk instead — the same index that
     * orders the modules — so a module's sub-menus read exactly as they do in
     * the sidebar.
     *
     * `level`, `parent_menu_id` and `parent_name` ride along so the client can
     * nest the list under its parents rather than only indent it.
     */
    private function moduleSubMenus(string $menuTitle, int $subInstituteId): array
    {
        $positions = $this->progress->menuPositions($subInstituteId);

        return DB::table('tblmenumaster as m')
            ->leftJoin('tblmenumaster as p', 'p.id', '=', 'm.parent_menu_id')
            ->select(
                'm.id', 'm.name', 'm.link', 'm.menu_type', 'm.icon',
                'm.youtube_link', 'm.pdf_link', 'm.database_table',
                'm.level', 'm.parent_menu_id', 'm.sort_order',
                DB::raw('p.name as parent_name')
            )
            ->where('m.menu_title', $menuTitle)
            ->where('m.status', 1)
            ->whereRaw('find_in_set(?, m.sub_institute_id)', [$subInstituteId])
            ->get()
            ->map(static function ($row) use ($positions) {
                $menu = (array) $row;
                $menu['level'] = (int) $row->level;
                $menu['parent_menu_id'] = (int) $row->parent_menu_id;
                $menu['sort_order'] = (int) $row->sort_order;
                $menu['sidebar_position'] = $positions[(int) $row->id] ?? null;

                return $menu;
            })
            // Anything the walk did not reach cannot be placed, so it trails the
            // rows that can — it never displaces them.
            ->sortBy(static fn ($menu) => $menu['sidebar_position'] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * Staff of this institute, for assigning a step to a real person.
     *
     * Read live from `tbluser` joined to `tbluserprofilemaster` — there is no
     * static or seeded list anywhere in the onboarding feature, so a school sees
     * exactly its own current, active staff and nothing else. Tenant-scoped by
     * hand, like every other query in this codebase.
     */
    private function tenantUsers(int $subInstituteId): array
    {
        return DB::table('tbluser as u')
            ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.sub_institute_id', $subInstituteId)
            ->where('u.status', 1)
            ->selectRaw(
                "u.id,
                 TRIM(CONCAT_WS(' ', NULLIF(u.first_name,''), NULLIF(u.last_name,''))) as name,
                 u.user_name,
                 u.email,
                 COALESCE(p.name, '') as profile_name"
            )
            ->orderByRaw("TRIM(CONCAT_WS(' ', NULLIF(u.first_name,''), NULLIF(u.last_name,'')))")
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                // Fall back through the identifiers the row actually has, so a
                // user with no first/last name still renders as something usable.
                'name' => $row->name ?: ($row->user_name ?: $row->email ?: ('User #' . $row->id)),
                'profile_name' => $row->profile_name,
            ])
            ->values()
            ->all();
    }

    private function success(string $message, array $data)
    {
        return response()->json(['status' => 1, 'status_code' => 1, 'message' => $message, 'data' => $data]);
    }

    private function failure(string $message, int $code = 422, $errors = null)
    {
        return response()->json([
            'status' => 0,
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $code);
    }
}
