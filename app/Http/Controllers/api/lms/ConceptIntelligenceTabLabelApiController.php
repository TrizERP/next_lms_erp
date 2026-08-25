<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Course Master -> Concept Intelligence — tenant-wise tab names.
 *
 * The tab strip above the concept intelligence panel is named per institute.
 * This controller serves the merged catalogue (shipped default + this
 * institute's overrides) and accepts renames from the inline editor.
 *
 * Resolution order for one tab, first hit wins:
 *   1. the institute's own row   (sub_institute_id = <tenant>)
 *   2. the estate-wide row       (sub_institute_id = 0)
 *   3. the shipped default       (config/lms_concept_intelligence_tabs.php)
 *
 * so institute 1 renaming "Real World" is invisible to institute 3, which keeps
 * whatever the estate or the blueprint says.
 */
class ConceptIntelligenceTabLabelApiController extends Controller
{
    private const TABLE = 'lms_concept_intelligence_tab_labels';

    /**
     * The estate-wide scope, read as the fallback under every tenant.
     *
     * Read-only through this API: update() and reset() both require a real
     * institute, so no client can rewrite the whole estate by posting 0. A row
     * at this scope is seeded by a DBA or a future super-admin surface.
     */
    private const ESTATE_SCOPE = 0;

    /** GET|POST /api/lms/concept-intelligence/tab-labels */
    public function index(Request $request): JsonResponse
    {
        try {
            return $this->catalogueResponse($this->resolveTenant($request));
        } catch (Throwable $e) {
            return $this->failure('Fetch failed', $e, ['request' => $request->all()]);
        }
    }

    /**
     * POST /api/lms/concept-intelligence/tab-labels/update
     *
     * Body: { sub_institute_id, labels: { tab_key: "New name", ... } }
     * or the single-tab form the inline editor sends:
     *       { sub_institute_id, tab_key, label }
     *
     * Only the keys present are touched. A blank value clears that tab's
     * override, as does a value identical to the shipped default — so a tab
     * renamed back to "Real World" resumes following the blueprint instead of
     * pinning a redundant copy.
     */
    public function update(Request $request): JsonResponse
    {
        $subInstituteId = $this->resolveTenant($request);

        if ($subInstituteId <= 0) {
            return $this->rejected('sub_institute_id is required');
        }

        $labels = $request->input('labels');

        if (!is_array($labels) && $request->filled('tab_key')) {
            $labels = [$request->input('tab_key') => $request->input('label')];
        }

        if (!is_array($labels) || $labels === []) {
            return $this->rejected('labels must be a non-empty object of tab_key => label');
        }

        $defaults = $this->defaults();
        $maxLength = $this->maxLabelLength();

        $unknown = array_diff(array_keys($labels), array_keys($defaults));
        if ($unknown !== []) {
            return $this->rejected('Unknown tab key(s): ' . implode(', ', $unknown));
        }

        foreach ($labels as $key => $value) {
            if ($value !== null && !is_scalar($value)) {
                return $this->rejected("Label for '{$key}' must be text");
            }

            if (mb_strlen(trim((string) $value)) > $maxLength) {
                return $this->rejected("Label for '{$key}' exceeds {$maxLength} characters");
            }
        }

        try {
            $userId = $this->resolveUserId($request);
            $now = now();

            DB::transaction(function () use ($labels, $defaults, $subInstituteId, $userId, $now) {
                foreach ($labels as $key => $value) {
                    $label = trim((string) $value);

                    if ($label === '' || $label === $defaults[$key]) {
                        DB::table(self::TABLE)
                            ->where('sub_institute_id', $subInstituteId)
                            ->where('tab_key', $key)
                            ->delete();
                        continue;
                    }

                    DB::table(self::TABLE)->updateOrInsert(
                        ['sub_institute_id' => $subInstituteId, 'tab_key' => $key],
                        [
                            'custom_label' => $label,
                            'updated_by'   => $userId,
                            'updated_at'   => $now,
                            'created_at'   => $now,
                        ]
                    );
                }
            });

            return $this->catalogueResponse($subInstituteId, 'Tab names updated');
        } catch (Throwable $e) {
            return $this->failure('Save failed', $e, [
                'sub_institute_id' => $subInstituteId,
                'tab_keys'         => array_keys($labels),
            ]);
        }
    }

    /**
     * POST /api/lms/concept-intelligence/tab-labels/reset
     *
     * Drops one override when `tab_key` is given, otherwise every override this
     * institute holds. The estate-wide row (scope 0) is never touched here.
     */
    public function reset(Request $request): JsonResponse
    {
        $subInstituteId = $this->resolveTenant($request);

        if ($subInstituteId <= 0) {
            return $this->rejected('sub_institute_id is required');
        }

        $tabKey = $request->input('tab_key');

        if ($tabKey !== null && !array_key_exists($tabKey, $this->defaults())) {
            return $this->rejected("Unknown tab key: {$tabKey}");
        }

        try {
            $query = DB::table(self::TABLE)->where('sub_institute_id', $subInstituteId);

            if ($tabKey !== null) {
                $query->where('tab_key', $tabKey);
            }

            $query->delete();

            return $this->catalogueResponse($subInstituteId, 'Default tab names restored');
        } catch (Throwable $e) {
            return $this->failure('Reset failed', $e, ['sub_institute_id' => $subInstituteId]);
        }
    }

    // -- internals ----------------------------------------------------------

    /** @return array<string, string> tab_key => shipped label, in display order */
    private function defaults(): array
    {
        $tabs = config('lms_concept_intelligence_tabs.tabs', []);

        return is_array($tabs) ? $tabs : [];
    }

    private function maxLabelLength(): int
    {
        return (int) config('lms_concept_intelligence_tabs.max_label_length', 120);
    }

    /**
     * The institute this request speaks for.
     *
     * The JWT-hydrated session wins when the route ran through `api.session`,
     * so a client cannot rename another institute's tabs by posting a different
     * id. The request parameter is the fallback for the unauthenticated
     * course-master calls the rest of this module already makes.
     */
    private function resolveTenant(Request $request): int
    {
        $fromSession = $request->hasSession()
            ? $request->session()->get('sub_institute_id')
            : null;

        return (int) ($fromSession ?: $request->input('sub_institute_id', 0));
    }

    private function resolveUserId(Request $request): ?int
    {
        $fromSession = $request->hasSession()
            ? $request->session()->get('user_id')
            : null;

        $userId = (int) ($fromSession ?: $request->input('user_id', 0));

        return $userId > 0 ? $userId : null;
    }

    /**
     * Overrides that apply to this tenant, its own rows shadowing the
     * estate-wide ones.
     *
     * @return array<string, array{label: string, scope: int}>
     */
    private function overrides(int $subInstituteId): array
    {
        $scopes = array_values(array_unique([self::ESTATE_SCOPE, $subInstituteId]));

        $rows = DB::table(self::TABLE)
            ->select(['tab_key', 'custom_label', 'sub_institute_id'])
            ->whereIn('sub_institute_id', $scopes)
            ->get();

        $resolved = [];

        foreach ($rows as $row) {
            $scope = (int) $row->sub_institute_id;

            // The tenant's own row always wins over the estate-wide one,
            // whatever order the driver returned them in.
            if (isset($resolved[$row->tab_key]) && $scope === self::ESTATE_SCOPE) {
                continue;
            }

            $resolved[$row->tab_key] = [
                'label' => (string) $row->custom_label,
                'scope' => $scope,
            ];
        }

        return $resolved;
    }

    private function catalogueResponse(int $subInstituteId, string $message = 'SUCCESS'): JsonResponse
    {
        $defaults = $this->defaults();
        $overrides = $this->overrides($subInstituteId);

        $tabs = [];

        foreach ($defaults as $key => $default) {
            $override = $overrides[$key] ?? null;

            $tabs[] = [
                'tab_key'       => $key,
                'default_label' => $default,
                'label'         => $override['label'] ?? $default,
                'is_custom'     => $override !== null,
                // Which scope supplied the label: this tenant, the estate, or
                // the shipped blueprint.
                'source'        => $override === null
                    ? 'default'
                    : ($override['scope'] === self::ESTATE_SCOPE ? 'estate' : 'institute'),
            ];
        }

        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => [
                'sub_institute_id' => $subInstituteId,
                'tabs'             => $tabs,
            ],
        ], 200);
    }

    private function rejected(string $message): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
        ], 422);
    }

    private function failure(string $message, Throwable $e, array $context = []): JsonResponse
    {
        Log::error(
            'Concept intelligence tab labels — ' . $message . ': ' . $e->getMessage(),
            $context + ['trace' => $e->getTraceAsString()]
        );

        return response()->json([
            'status'  => false,
            'message' => $message . ': ' . $e->getMessage(),
        ], 500);
    }
}
