<?php

namespace App\Http\Controllers\api\Documents;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Document module's read side. There is no write side.
 *
 * ============================ WHAT THIS IS ============================
 * A single place to SEE every document the ERP already holds. It aggregates —
 * it does not own. Every row comes from a table another module writes, and the
 * "Open in module" link on every row sends the user back to that module's own
 * screen to actually do anything with it.
 *
 * WHAT IT DELIBERATELY CANNOT DO
 * There is no store(), no update(), no destroy(), and no upload path. Not as an
 * oversight — as the contract. Sixteen modules currently own their own upload
 * and delete flows, they work, and tenants depend on them. A second writer
 * against the same tables would be a second source of truth for the same row.
 * Adding a write method here is not an extension of this class; it is a
 * different decision that needs its own review.
 *
 * TENANCY
 * The tenant comes from session('sub_institute_id'), which `api.session`
 * populates from the VERIFIED JWT and nothing else (see
 * Concerns/HydratesLegacyApiSession). It is never read from request input.
 * That matters more here than almost anywhere: this endpoint's whole job is to
 * return documents in bulk, so a caller-controlled tenant would turn one
 * request into a cross-tenant export.
 *
 * WHY EVERY IDENTIFIER IS LOOKED UP, NEVER INTERPOLATED
 * A caller names a source by KEY ('student_documents'), and the table and column
 * names come from config/documents.php. No table name, column name or sort
 * direction reaches a query from the request. A source key that is not in the
 * registry is a 404, not an empty result — "no such source" and "no documents"
 * are different answers and the UI shows them differently.
 *
 * MISSING TABLES ARE SKIPPED, NOT FATAL
 * Schema::hasTable() guards every source. Not every tenant's database is at the
 * same migration state, and one absent table must degrade to one absent card
 * rather than a 500 that blacks out the whole dashboard.
 */
class DocumentAggregationController extends Controller
{
    /**
     * GET /api/documents/sources
     *
     * The dashboard payload: every registered source that actually exists in
     * this database, its document count for this tenant, grouped by domain.
     */
    public function sources(Request $request)
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return $this->fail('A school context is required.', 401);
        }

        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $syear = $this->syearFilter($request);

        $domains = [];
        foreach ((array) config('documents.domains', []) as $key => $domain) {
            $domains[$key] = [
                'key' => $key,
                'label' => $domain['label'] ?? $key,
                'icon' => $domain['icon'] ?? 'file-text',
                'description' => $domain['description'] ?? '',
                'total' => 0,
                /** Raised below if any source in this domain carries a syear. */
                'year_scoped' => false,
                'sources' => [],
            ];
        }

        $unavailable = [];

        foreach ((array) config('documents.sources', []) as $key => $source) {
            if (! Schema::hasTable($source['table'])) {
                $unavailable[] = $key;
                continue;
            }

            $count = (int) $this->baseQuery($source, $tenant, $syear)->count();
            $domainKey = $source['domain'] ?? 'other';

            if (! isset($domains[$domainKey])) {
                $domains[$domainKey] = [
                    'key' => $domainKey,
                    'label' => ucfirst($domainKey),
                    'icon' => 'file-text',
                    'description' => '',
                    'total' => 0,
                    'year_scoped' => false,
                    'sources' => [],
                ];
            }

            /*
             * Whether this source can answer "in which academic year?" at all.
             *
             * Five of the thirteen tables — including tblstudent_document and
             * staff_document, which together hold the overwhelming majority of
             * rows — have no syear column, so a year filter cannot apply to them
             * and their counts are identical for every year. That is a property
             * of those tables, not a bug in the filter, but on screen the two are
             * indistinguishable: the user changes the year and the number does
             * not move. Reporting the flag lets the UI say which cards the year
             * actually applies to instead of leaving it looking broken.
             */
            $yearScoped = ! empty($source['syear_column']);

            $domains[$domainKey]['sources'][] = [
                'key' => $key,
                'label' => $source['label'] ?? $key,
                'count' => $count,
                'route' => $source['route'] ?? null,
                'with_file' => true,
                'year_scoped' => $yearScoped,
            ];
            $domains[$domainKey]['total'] += $count;
            if ($yearScoped) {
                $domains[$domainKey]['year_scoped'] = true;
            }
        }

        return $this->ok([
            'domains' => array_values($domains),
            'total' => array_sum(array_column($domains, 'total')),
            /*
            | Named, not hidden. A source missing from this database is a fact
            | the operator should be able to see, otherwise a card silently
            | absent looks identical to a card with nothing in it.
            */
            'unavailable_sources' => $unavailable,
            'syear' => $syear,
        ]);
    }

    /**
     * GET /api/documents?source=<key>&search=&page=&per_page=&syear=
     *
     * One source's rows, tenant-scoped and paginated.
     */
    public function index(Request $request)
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return $this->fail('A school context is required.', 401);
        }

        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $key = (string) $request->query('source', '');
        $source = config('documents.sources.' . $key);

        if (! is_array($source)) {
            return $this->fail('Unknown document source.', 404);
        }

        if (! Schema::hasTable($source['table'])) {
            return $this->fail('That document source is not available in this database.', 404);
        }

        $syear = $this->syearFilter($request);
        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->query('page', 1));

        $query = $this->baseQuery($source, $tenant, $syear);

        // Search is applied to the title column only, and the term is bound, not
        // interpolated. The column name comes from the registry.
        $search = trim((string) $request->query('search', ''));
        if ($search !== '' && ! empty($source['columns']['title'])) {
            $query->where($source['columns']['title'], 'like', '%' . $search . '%');
        }

        $total = (int) (clone $query)->count();

        /*
        | The id is a TIEBREAKER, not decoration. created_at is not unique in any
        | of these tables — 374 student documents share one timestamp, 150 teacher
        | resources share another — and ordering by it alone leaves MySQL free to
        | return tied rows in any order it likes. Across two requests that means a
        | row can appear on page 2 having already appeared on page 1, while
        | another is never shown at all. Ordering by (created_at, id) makes the
        | sequence total, so OFFSET paging is reproducible.
        */
        $idColumn = $source['columns']['id'] ?? 'id';
        $createdColumn = $source['columns']['created_at'] ?? null;
        if ($createdColumn) {
            $query->orderByDesc($createdColumn);
        }
        $query->orderByDesc($idColumn);

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return $this->ok([
            'source' => [
                'key' => $key,
                'label' => $source['label'] ?? $key,
                'domain' => $source['domain'] ?? null,
                'route' => $source['route'] ?? null,
            ],
            'rows' => $rows->map(fn ($row) => $this->present($row, $source))->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    /**
     * GET /api/documents/recent?limit=
     *
     * The most recent documents across every source, merged.
     *
     * Merged in PHP rather than by a UNION. The sources have different column
     * names, different date column names and — in two cases — dates stored as
     * strings, so a UNION would need per-source casting to line up and would
     * still scan every table. Taking the newest `limit` from each source and
     * sorting the (at most a few hundred) rows that come back is both simpler
     * and bounded.
     */
    public function recent(Request $request)
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return $this->fail('A school context is required.', 401);
        }

        if ($denied = $this->denyUnlessAdministrator()) {
            return $denied;
        }

        $limit = min(50, max(1, (int) $request->query('limit', 10)));
        $syear = $this->syearFilter($request);

        $collected = [];

        foreach ((array) config('documents.sources', []) as $key => $source) {
            if (! Schema::hasTable($source['table'])) {
                continue;
            }

            $createdColumn = $source['columns']['created_at'] ?? null;
            $idColumn = $source['columns']['id'] ?? 'id';
            $query = $this->baseQuery($source, $tenant, $syear);
            // Same total ordering as index(), for the same reason.
            if ($createdColumn) {
                $query->orderByDesc($createdColumn);
            }
            $query->orderByDesc($idColumn);

            foreach ($query->limit($limit)->get() as $row) {
                $item = $this->present($row, $source);
                $item['source_key'] = $key;
                $item['source_label'] = $source['label'] ?? $key;
                $collected[] = $item;
            }
        }

        usort($collected, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $this->ok(['rows' => array_slice($collected, 0, $limit)]);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * A SELECT against one source, scoped to the tenant and (where the table has
     * one) the academic year.
     *
     * Only the columns the registry names are selected. Selecting `*` would put
     * whatever else those tables carry — remarks, internal ids, evaluation JSON —
     * into a response that has no use for it.
     */
    private function baseQuery(array $source, string $tenant, ?string $syear)
    {
        $columns = $source['columns'];

        $select = [];
        foreach (['id', 'title', 'file', 'file_name', 'status', 'created_at', 'owner'] as $role) {
            if (! empty($columns[$role])) {
                $select[] = $columns[$role] . ' as doc_' . $role;
            }
        }

        $query = DB::table($source['table'])
            ->select($select)
            ->where($source['tenant_column'], $tenant);

        if (! empty($source['soft_delete'])) {
            $query->whereNull('deleted_at');
        }

        if ($syear !== null && ! empty($source['syear_column'])) {
            $query->where($source['syear_column'], $syear);
        }

        // A row with no file is not a document. Several of these tables carry
        // records whose attachment is optional (a circular with no PDF, an
        // announcement that is text only), and listing them here would make the
        // counts disagree with what the user can actually open.
        $fileColumn = $columns['file'] ?? null;
        if ($fileColumn) {
            $query->whereNotNull($fileColumn)->where($fileColumn, '!=', '');
        }

        return $query;
    }

    /** One row, in the single shape every source is presented in. */
    private function present($row, array $source): array
    {
        $file = isset($row->doc_file) ? (string) $row->doc_file : '';

        return [
            'id' => $row->doc_id ?? null,
            'title' => $this->cleanTitle($row->doc_title ?? null, $file),
            'file_name' => isset($row->doc_file_name) && $row->doc_file_name
                ? (string) $row->doc_file_name
                : basename($file),
            'extension' => strtolower((string) pathinfo($file, PATHINFO_EXTENSION)),
            'status' => $row->doc_status ?? null,
            'owner_id' => $row->doc_owner ?? null,
            'created_at' => $row->doc_created_at ?? null,
            'url' => $this->resolveUrl($file, $source['storage'] ?? []),
            'module_route' => $source['route'] ?? null,
        ];
    }

    /**
     * Builds the SAME url the owning module builds. It does not normalise,
     * re-host or sign anything — this layer is not allowed to change how an
     * existing file is addressed, and a URL invented here would simply 404.
     */
    private function resolveUrl(string $file, array $storage): ?string
    {
        if ($file === '') {
            return null;
        }

        // Already a URL (either because the column stores one, or because a
        // legacy row was written with a full path). Trust it as-is.
        if (preg_match('#^https?://#i', $file)) {
            return $file;
        }

        $prefix = $storage['prefix'] ?? '';

        /*
         * Encode the stored value the way sqaaFileUrl(), attachmentUrl() and
         * galleryFileUrl() already encode theirs — those three call
         * encodeURIComponent on the filename, and rawurlencode is its PHP
         * equivalent (space becomes %20, not the "+" urlencode would give).
         * Without this, 2,675 of the 87,799 stored names — every one containing
         * a space or an "&" — produced a malformed URL and an Open link that
         * silently did nothing.
         *
         * SEGMENT BY SEGMENT, not the whole string: one stored value is
         * "hwgen/hw1788951163fix38.pdf" and onboarding stores a full
         * disk-relative path, so encoding the "/" away would break those.
         * Splitting on "/" leaves the separators intact and encodes each part
         * exactly as encodeURIComponent would.
         *
         * Double-encoding is not a risk here: no stored name contains a "%"
         * (verified across all thirteen sources), so nothing is already encoded.
         *
         * The prefix is not encoded — it is this file's own config, plain ASCII
         * and slashes.
         */
        $encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($file, '/'))));

        switch ($storage['kind'] ?? 'spaces') {
            case 'absolute':
                return $file;

            case 'local':
                return rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($prefix . $encoded, '/');

            case 'spaces':
            default:
                return rtrim((string) config('documents.spaces_base'), '/') . '/' . ltrim($prefix . $encoded, '/');
        }
    }

    /**
     * Several sources have no title column worth showing (upload_result titles
     * itself with its own filename), and a few carry empty strings. Falling back
     * to the filename keeps every row identifiable instead of showing a blank
     * cell the user cannot act on.
     */
    private function cleanTitle($title, string $file): string
    {
        $title = trim((string) $title);

        return $title !== '' ? $title : (basename($file) ?: 'Untitled document');
    }

    /**
     * The module's access gate: administrators only.
     *
     * WHY THIS EXISTS AT ALL. Every endpoint here lists documents for the whole
     * institute. Authenticating the caller establishes WHICH school's documents
     * they get; it says nothing about whether they should get all of them. Before
     * this gate a student's own valid token returned every student's identity and
     * medical records and every payslip in the tenant — tenant-correct, and
     * completely wrong.
     *
     * FAIL CLOSED, AND IN THIS ORDER. A student session is refused outright
     * before any name matching, because `is_student` is a fact from the token
     * while a profile name is a per-tenant string an administrator can edit; if
     * someone names a student profile "Admin", the flag still says no. Then
     * is_admin 1/2 passes. Then the normalised profile name must appear in
     * config('documents.access.profiles'). Anything else — including an empty or
     * unrecognised profile — is denied.
     *
     * IDENTITY COMES FROM THE SESSION THE MIDDLEWARE HYDRATED, which it built
     * from the verified JWT and nothing else. No part of this decision can be
     * influenced by request input.
     *
     * This is a stopgap for the absence of a tblmenumaster row, not a second
     * permission system. When that row exists, this method is replaced by
     * `perm:document.<domain>,view` on the route — not supplemented by it.
     */
    private function denyUnlessAdministrator()
    {
        if (session()->get('is_student')) {
            return $this->forbidden();
        }

        $access = (array) config('documents.access', []);

        $isAdminFlag = (int) session()->get('is_admin', 0);
        if (! empty($access['allow_super_admin']) && ($isAdminFlag === 1 || $isAdminFlag === 2)) {
            return null;
        }

        $profile = $this->normaliseProfile((string) session()->get('user_profile_name', ''));
        if ($profile === '') {
            return $this->forbidden();
        }

        $allowed = array_map(
            fn ($name) => $this->normaliseProfile((string) $name),
            (array) ($access['profiles'] ?? [])
        );

        return in_array($profile, $allowed, true) ? null : $this->forbidden();
    }

    /**
     * Live profile names are not clean: the same role appears as "ADMIN",
     * "Admin", "PRINCIPAL " with a trailing space and "collage_admin". Matching
     * raw strings would grant access to one tenant's principal and refuse
     * another's, so both sides of the comparison are normalised the same way.
     */
    private function normaliseProfile(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(str_replace('_', ' ', $value))) ?? '');
    }

    private function forbidden()
    {
        return $this->fail(
            'The Document module is available to administrative roles only.',
            403
        );
    }

    /** Tenant from the verified token's session. Never from request input. */
    private function tenant(): ?string
    {
        $tenant = session()->get('sub_institute_id');

        return ($tenant === null || $tenant === '') ? null : (string) $tenant;
    }

    /**
     * The year filter is opt-in: `syear=all` (or an absent session year) means
     * every year. Sources whose table has no syear column ignore it either way,
     * which is why the dashboard's counts can exceed a single year's rows.
     */
    private function syearFilter(Request $request): ?string
    {
        $syear = $request->query('syear', session()->get('syear'));

        if ($syear === null || $syear === '' || $syear === 'all') {
            return null;
        }

        return (string) $syear;
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', (int) config('documents.page_size', 25));
        $max = (int) config('documents.max_page_size', 100);

        return max(1, min($requested ?: 25, $max));
    }

    private function ok(array $data)
    {
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => $data]);
    }

    private function fail(string $message, int $httpStatus = 422)
    {
        return response()->json(['status_code' => 0, 'message' => $message, 'data' => null], $httpStatus);
    }
}
