<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Administration\ArchitectureHealthService;
use App\Services\PAL\Administration\ArchitectureRegistry;
use App\Services\PAL\Runtime\SubsystemRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * New PAL → Administration API.
 *
 * The control plane for the nine architecture subsystems the Master Blueprint
 * specifies. Every response pairs the CONFIGURATION (what this estate has
 * decided) with the HEALTH (what is actually running), because tuning a
 * parameter is meaningless without knowing whether the subsystem it belongs to
 * receives any data.
 *
 * Access, in three tiers:
 *   students          403 on everything — this is not a learner surface
 *   staff / teachers  read only
 *   admins            read and write, per ArchitectureRegistry::mayWrite
 *
 * Tenancy follows the same rule as NewPalContentModelController: architecture
 * settings have no learner for `pal.auth` to scope through, so the institute is
 * resolved here from the caller's own token. A super-admin (is_admin === 2) may
 * target another institute explicitly, or omit it to edit the estate default.
 *
 * Envelope: {success: true, data: …} / {success: false, message: …}.
 */
class PalArchitectureController extends Controller
{
    public function __construct(
        protected ArchitectureRegistry $registry,
        protected ArchitectureHealthService $health,
        protected SubsystemRuntime $runtime
    ) {}

    /**
     * GET /api/pal/new/administration
     *
     * The overview: every subsystem with its live status. One call, because the
     * landing page shows all nine at once and nine round trips would be worse.
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        $health = $this->health->all($tenant);

        $subsystems = array_map(function (array $summary) use ($health, $tenant) {
            $probe = $health[$summary['key']] ?? null;
            $live = $this->runtime->for($summary['key'], $tenant);

            // The card reports what the subsystem PRODUCES, not merely whether
            // its declared dependency is up. Knowledge Graph is the case that
            // forces this: the Neo4j probe is red (no server), yet the runtime
            // projects a real 1,000-node DAG out of the extracted chapters. A
            // card reading "Not running" next to a detail page showing a live
            // graph is a contradiction, and the detail page is the truthful one.
            //
            // operational  computes, and its native pipeline is healthy too
            // degraded     computes, but from fallback evidence
            // inactive     cannot compute at all
            $status = match (true) {
                ! $live['available'] => 'inactive',
                ($probe['status'] ?? '') === 'operational' => 'operational',
                default => 'degraded',
            };

            return $summary + [
                'status' => $status,
                'summary' => $live['summary'] ?? ($probe['summary'] ?? ''),
                // Prefer the computed figures — "Concept nodes 1,138" tells an
                // administrator more than "Connection Closed".
                'metrics' => $live['available'] && $live['headline'] !== []
                    ? $live['headline']
                    : ($probe['metrics'] ?? []),
                'health_summary' => $probe['summary'] ?? '',
            ];
        }, $this->registry->catalogue($tenant));

        return $this->ok([
            'subsystems' => $subsystems,
            'totals' => $this->rollUp($subsystems),
            'can_write' => $this->registry->mayWrite($this->auth($request)),
            'scope' => [
                'sub_institute_id' => $tenant,
                'estate_wide' => $tenant === null,
            ],
        ]);
    }

    /**
     * GET /api/pal/new/administration/{subsystem}
     *
     * The full descriptor: panels, resolved settings, health and — where the
     * panel needs it — the read-only catalog data that cannot live in config
     * because it is probed at request time.
     */
    public function show(Request $request, string $subsystem): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        if (! $this->registry->has($subsystem)) {
            return $this->fail("Unknown architecture subsystem '{$subsystem}'.", 404);
        }

        $tenant = $this->tenantFor($request);

        return $this->ok([
            'subsystem' => $this->registry->subsystem($subsystem, $tenant),
            'health' => $this->health->for($subsystem, $tenant),
            'catalog' => $this->catalogFor($subsystem),
            'live' => $this->runtime->for($subsystem, $tenant),
            'can_write' => $this->registry->mayWrite($this->auth($request)),
        ]);
    }

    /**
     * POST /api/pal/new/administration/{subsystem}
     *
     * Body: {"group": "bkt", "value": {...}}
     *
     * The registry sanitises before anything is stored — non-editable fields
     * are dropped, unknown records rejected, numbers range-checked — so a
     * malformed or hostile payload can never widen what this endpoint writes.
     */
    public function update(Request $request, string $subsystem): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        if (! $this->registry->has($subsystem)) {
            return $this->fail("Unknown architecture subsystem '{$subsystem}'.", 404);
        }

        $auth = $this->auth($request);
        if (! $this->registry->mayWrite($auth)) {
            return $this->fail('Architecture settings may only be changed by an administrator.', 403);
        }

        $group = trim((string) $request->input('group', ''));
        if ($group === '') {
            return $this->fail('A settings group is required.', 422);
        }

        if (! $request->has('value')) {
            return $this->fail('A value is required.', 422);
        }

        try {
            $result = $this->registry->save(
                $subsystem,
                $group,
                $request->input('value'),
                $this->tenantFor($request),
                $this->userId($auth)
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $tenant = $this->tenantFor($request);

        return $this->ok([
            'subsystem' => $result,
            'health' => $this->health->for($subsystem, $tenant),
            'catalog' => $this->catalogFor($subsystem),
            // Recomputed with the values just saved: changing P(S) must visibly
            // move the mastery numbers, otherwise the settings are decorative.
            'live' => $this->runtime->for($subsystem, $tenant),
            'can_write' => true,
        ]);
    }

    /**
     * POST /api/pal/new/administration/{subsystem}/reset
     *
     * Body: {"group": "bkt"} — or no group, to reset the whole subsystem.
     * Deletes the override so the group tracks the shipped blueprint default
     * again; it does not write the default back, so a later revision to the
     * blueprint still reaches this estate.
     */
    public function reset(Request $request, string $subsystem): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        if (! $this->registry->has($subsystem)) {
            return $this->fail("Unknown architecture subsystem '{$subsystem}'.", 404);
        }

        if (! $this->registry->mayWrite($this->auth($request))) {
            return $this->fail('Architecture settings may only be changed by an administrator.', 403);
        }

        $group = trim((string) $request->input('group', ''));
        $tenant = $this->tenantFor($request);

        try {
            $result = $this->registry->reset($subsystem, $group === '' ? null : $group, $tenant);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'subsystem' => $result,
            'health' => $this->health->for($subsystem, $tenant),
            'catalog' => $this->catalogFor($subsystem),
            'live' => $this->runtime->for($subsystem, $tenant),
            'can_write' => true,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Internals
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Read-only reference data for the `catalog` panels. Probed rather than
     * configured — which graph labels exist, which vocabularies are loaded —
     * so it cannot be stated in config/pal_architecture.php.
     */
    private function catalogFor(string $subsystem): array
    {
        return match ($subsystem) {
            'knowledge-graph' => ['schema' => $this->health->graphSchemaPresence()],
            'career-pathway' => ['vocabulary' => $this->health->careerVocabulary()],
            default => [],
        };
    }

    /** Status counts for the overview header. */
    private function rollUp(array $subsystems): array
    {
        $totals = ['operational' => 0, 'degraded' => 0, 'inactive' => 0, 'unknown' => 0];

        foreach ($subsystems as $subsystem) {
            $status = (string) ($subsystem['status'] ?? 'unknown');
            $totals[$status] = ($totals[$status] ?? 0) + 1;
        }

        $totals['total'] = count($subsystems);

        return $totals;
    }

    private function auth(Request $request): array
    {
        return (array) $request->attributes->get('pal_auth', []);
    }

    private function userId(array $auth): ?int
    {
        $id = $auth['id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /**
     * The institute whose settings this request reads or writes.
     *
     * Mirrors NewPalContentModelController: a CSV membership resolves to its
     * first entry, and a super-admin may target another institute or fall
     * through to the estate-wide scope.
     */
    private function tenantFor(Request $request): ?int
    {
        $auth = $this->auth($request);

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return $request->filled('sub_institute_id') ? (int) $request->get('sub_institute_id') : null;
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    private function denyStudents(Request $request): ?JsonResponse
    {
        return ! empty($this->auth($request)['is_student'])
            ? $this->fail('The Administration workspace is not available to students.', 403)
            : null;
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
