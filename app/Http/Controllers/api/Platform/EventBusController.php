<?php

namespace App\Http\Controllers\api\Platform;

use App\Services\Platform\EventBusReader;
use App\Services\Platform\PlatformRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Event Bus read API — six GETs over tables this application already writes.
 *
 * WHAT IT IS NOT. There is no event bus in this product and this does not add
 * one. No broker, no queue, no event store, no new table, no publisher, no
 * retry, no replay. Decision #14 is explicit that a second bus must not be built
 * beside the `sync_log` outbox that already works; this makes that outbox
 * visible instead, which is the part nobody could see before.
 *
 * EVERY VERB HERE IS GET. Deliberately, and not only because Phase 1 is
 * read-only: with `QUEUE_CONNECTION=sync` a replay would run the replayed work
 * on the operator's own request thread. There is nowhere for it to go until
 * real queue workers exist.
 *
 * THE QUERIES LIVE IN EventBusReader, not here. These methods parse the query
 * string, resolve the tenant and hand off — the same division the three sibling
 * platform controllers keep, and it is what lets the reader be read in one sitting
 * without a controller's worth of request handling folded through it.
 *
 * ---------------------------------------------------------------------------
 * THREE GATES, EACH ANSWERING A DIFFERENT QUESTION
 * ---------------------------------------------------------------------------
 *   `lms.auth`                      who are you?
 *   `lms.staff`                     are you staff at all? (students and parents
 *                                   are refused 403 — see RequireLmsStaff)
 *   `perm:platform.eventbus,view`   are you staff who may open THIS screen?
 *   `isSuperAdmin()`, in code       may you see other institutes?
 *
 * The first three are declared on the route, where a reader can see them next to
 * the verb. The fourth cannot be: see below.
 *
 * Unlike Communication, Scheduler and Workflow — whose reads need only a session,
 * because seeing which notifications the product can raise is useful to most
 * staff and harmful to none — these reads are permission-gated. That is a
 * deliberate departure. Those three expose configuration; this one exposes
 * operational data, some of it from tables with no tenant column.
 *
 * ---------------------------------------------------------------------------
 * TWO TIERS, BECAUSE TWO KINDS OF TABLE
 * ---------------------------------------------------------------------------
 * Nine of the ten tables carry `sub_institute_id` and are scoped to the caller's
 * institute. `sync_log`, `neo4j_sync_queue` and `failed_jobs` do not — an outbox
 * row does not record a tenant (GraphDrain.php:464 says so outright), and
 * Laravel's failed-job table has no such column. Filtering them on the JSON
 * payload was rejected in EventBusReader: it would silently drop untagged rows
 * and report a number that is quietly wrong.
 *
 * So access splits along the line the schema already draws:
 *
 *   TIER 1, any permitted staff member — Deliveries, Audit logs, Integrations,
 *     the delivery-rate KPI and the audit-activity KPI. Every source is filtered
 *     by the caller's own institute.
 *
 *   TIER 2, `is_admin === 2` only — the event stream, the failures list, the
 *     four outbox KPIs, the volume series and the recent-failures panel. These
 *     read estate-wide tables, and an institute administrator has no right to
 *     another school's record ids, activity timing or exception text.
 *
 * THE RESTRICTED BRANCH RETURNS BEFORE ANY QUERY RUNS. Nothing estate-wide is
 * fetched and then filtered out — a filter is somewhere a bug can live, and a
 * leak there would be invisible. EventBusReader is told the tier and never
 * computes what it may not return.
 *
 * WHY THE TIER IS NOT AN RBAC KEY. `perm:platform.eventbus,view` decides WHICH
 * STAFF may open this screen. It cannot decide whether they may see other
 * tenants, because PermissionService resolves every grant
 * `where sub_institute_id = ?` (PermissionService.php:183-199) — a right held in
 * one institute is silent about another. Cross-tenant visibility is an identity
 * question, so `isSuperAdmin()` answers it in code.
 */
class EventBusController extends PlatformController
{
    public function __construct(PlatformRegistry $registry, private readonly EventBusReader $reader)
    {
        parent::__construct($registry);
    }

    /**
     * GET /api/platform/events/overview
     *
     * The one mixed endpoint. A permitted staff member gets the two tenant-scoped
     * KPIs; a Super Admin additionally gets the four outbox KPIs, the volume
     * series and the recent failures. The tier is passed down rather than applied
     * afterwards, so the estate queries are not run for a caller who may not see
     * their results.
     */
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$from, $to] = $this->range($request);

        return $this->ok($this->reader->overview(
            $tenantId,
            $from,
            $to,
            $this->isSuperAdmin($request),
        ));
    }

    /**
     * GET /api/platform/events/stream.
     *
     * Super Admins retain the estate-wide stream. Other staff receive only rows
     * whose payload explicitly attributes them to their institute.
     *
     * WHY FILTERING ON THE JSON IS ACCEPTABLE HERE AND WAS NOT BEFORE. The
     * objection to it was never the filter — it was that an unattributed row
     * would vanish with nothing to say it had. `sync_log` has no tenant column,
     * so a row whose payload carries no `sub_institute_id` cannot be placed, and
     * a filtered list alone would read as a complete one. The response therefore
     * carries `visible_events`, `excluded_events` and `partial`, and the screen
     * states both numbers. A partial view that says how partial it is answers a
     * different question from a complete one, but it answers it honestly.
     *
     * An excluded row is never disclosed, counted into any other total, or
     * inferable beyond its existence.
     */
    public function stream(Request $request): JsonResponse
    {
        if ($this->tenantId($request) === null) {
            return $this->unauthenticated($request);
        }

        [$from, $to] = $this->range($request);
        [$page, $perPage] = $this->paging($request);
        $tenantId = $this->tenantId($request);

        return $this->ok($this->reader->stream(
            $this->isSuperAdmin($request) ? null : $tenantId,
            $from,
            $to,
            $this->str($request, 'status'),
            $this->str($request, 'event_type'),
            (string) ($this->str($request, 'q') ?? ''),
            $page,
            $perPage,
        ));
    }

    /**
     * GET /api/platform/events/failures — TIER 2.
     *
     * Two of its three sources are estate-wide, and one of them is the worst
     * disclosure on this screen: `failed_jobs.exception` is unbounded free text
     * that routinely carries names, addresses and SQL fragments from whichever
     * tenant's job failed. Refused before the query.
     *
     * The `workflow_steps` third of this list IS tenant-scoped and could be
     * offered to Tier 1 on its own. It is not, because splitting a failure list
     * by source would show an institute administrator a partial count with no way
     * to tell it was partial — worse than showing none.
     */
    public function failures(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        if (! $this->isSuperAdmin($request)) {
            return $this->ok($this->restricted());
        }

        [$from, $to] = $this->range($request);
        [$page, $perPage] = $this->paging($request);

        return $this->ok($this->reader->failures($tenantId, $from, $to, (string) ($this->str($request, 'q') ?? ''), $page, $perPage));
    }

    /** GET /api/platform/events/deliveries */
    public function deliveries(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$from, $to] = $this->range($request);
        [$page, $perPage] = $this->paging($request);

        return $this->ok($this->reader->deliveries(
            $tenantId,
            $from,
            $to,
            $this->str($request, 'channel'),
            (string) ($this->str($request, 'q') ?? ''),
            $page,
            $perPage,
        ));
    }

    /** GET /api/platform/events/audit */
    public function audit(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$from, $to] = $this->range($request);
        [$page, $perPage] = $this->paging($request);

        return $this->ok($this->reader->audit(
            $tenantId,
            $from,
            $to,
            $this->str($request, 'event_type'),
            (string) ($this->str($request, 'q') ?? ''),
            $page,
            $perPage,
        ));
    }

    /** GET /api/platform/events/integrations */
    public function integrations(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$from, $to] = $this->range($request);

        return $this->ok($this->reader->integrations($tenantId, $from, $to, (string) ($this->str($request, 'q') ?? '')));
    }

    // -----------------------------------------------------------------------

    /** A trimmed query value, or null when it was absent or blank. */
    private function str(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return $value === '' ? null : $value;
    }

    /**
     * The date range, as whole days.
     *
     * `from` opens at midnight and `to` closes at 23:59:59, because an operator
     * picking the same date twice means "that day" — not an empty window, which
     * is what a naive midnight-to-midnight comparison gives them.
     *
     * An unparseable date is ignored rather than fatal: a filter is a
     * convenience, and a malformed one should widen the view, not 500 it.
     *
     * @return array{0:?CarbonImmutable,1:?CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $parse = function (?string $value, bool $endOfDay): ?CarbonImmutable {
            if ($value === null) {
                return null;
            }

            try {
                $date = CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        };

        return [
            $parse($this->str($request, 'from'), false),
            $parse($this->str($request, 'to'), true),
        ];
    }

    /**
     * Page and page size, both clamped.
     *
     * 200 is the ceiling the AI controllers already use for a page of rows, and
     * these tables are larger than theirs — `sync_log` runs to tens of thousands
     * — so an uncapped `per_page` is a way to ask this endpoint to read the whole
     * outbox into memory.
     *
     * @return array{0:int,1:int}
     */
    private function paging(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min($perPage, 200));

        return [$page, $perPage];
    }
}
