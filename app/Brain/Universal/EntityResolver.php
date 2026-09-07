<?php

namespace App\Brain\Universal;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Resolves universal entity names to the tables a given tenant keeps them in.
 *
 * Ported from hp-enterprise-brain/app/Domain/Universal/EntityResolver.php.
 *
 * THIS CLASS IS WHY THE INTEGRATION DOES NOT DUPLICATE THE LMS. The Brain's own
 * vocabulary is fixed — Organization, OrganizationUnit, Person, Position — while
 * the tables underneath vary per installation. In this one they are the K-12
 * LMS's own tables: institute_detail, hrms_departments, tbluser, hrms_job_titles,
 * tblstudent, all keyed on sub_institute_id. Nothing is copied into a second
 * organization/department/people store; the Brain reads the rows the LMS already
 * owns.
 *
 * IT FAILS CLOSED, AND THAT IS THE MOST IMPORTANT THING ABOUT IT.
 *
 * There is no default, no fallback, no "if unmapped, assume some table". A
 * tenant with no mapping for an entity gets an exception. The tempting
 * alternative — defaulting to a known table so nothing breaks during rollout —
 * would mean one institute silently reading another's employee rows and
 * presenting them as its own. That is a cross-tenant leak, and it would arrive
 * disguised as a display bug rather than as a security incident. A thrown
 * exception is the only failure mode that cannot be mistaken for data.
 *
 * CACHING IS PER REQUEST, NEVER PER PROCESS. Mappings are configuration and
 * change while the application is running; a process-lifetime cache would serve
 * a stale table name until the worker recycled. The container binding in
 * BrainServiceProvider is scoped(), so the instance and its cache are discarded
 * at the end of each request. Within a request the cache is keyed by tenant, so
 * resolving four entities for two tenants is two queries, not eight.
 */
final class EntityResolver
{
    /** The one table this class reads. Every mapping in the installation lives here. */
    const TABLE = 'hpbrain_entity_mappings';

    /**
     * Universal fields that bind the source rather than describe it. Both are
     * mandatory on every mapped entity.
     */
    const FIELD_ID = 'id';

    const FIELD_TENANT_KEY = 'tenantKey';

    /**
     * The vocabulary the Brain reasons in.
     *
     * The first four are the entities intelligence is produced about. The next
     * two are satellite records the ERP keeps alongside them — an organization's
     * legal details, a person's role profile.
     *
     * `Student` is last because it is the one entity many source systems do not
     * have. Listing it here does NOT assume it exists — an installation whose
     * ERP has no student table simply never maps it, has() answers false, and
     * every student surface stays empty.
     */
    const ENTITIES = [
        'Organization',
        'OrganizationUnit',
        'Person',
        'Position',
        'OrganizationProfile',
        'PersonProfile',
        'Student',
    ];

    /** @var array<string, array<string, ResolvedSource>> tenantId => entity => source */
    private $cache = [];

    /** @var array<string, true> tenants whose mappings have been loaded */
    private $loaded = [];

    public function resolve(string $tenantId, string $entity): ResolvedSource
    {
        $this->load($tenantId);

        if (! isset($this->cache[$tenantId][$entity])) {
            throw UnsupportedEntityException::forEntity(
                $tenantId,
                $entity,
                array_keys(isset($this->cache[$tenantId]) ? $this->cache[$tenantId] : [])
            );
        }

        return $this->cache[$tenantId][$entity];
    }

    /**
     * Whether an entity is mapped, without throwing.
     *
     * For callers deciding whether to offer a feature at all. It is NOT for
     * swapping in a default when the answer is false — that is the fallback this
     * class exists to prevent.
     */
    public function has(string $tenantId, string $entity): bool
    {
        $this->load($tenantId);

        return isset($this->cache[$tenantId][$entity]);
    }

    /** @return array<int, string> */
    public function mappedEntities(string $tenantId): array
    {
        $this->load($tenantId);

        $names = array_keys(isset($this->cache[$tenantId]) ? $this->cache[$tenantId] : []);
        sort($names);

        return $names;
    }

    /**
     * Every tenant that maps a given entity, resolved.
     *
     * For the one operation that has no tenant to scope by: identifying a person
     * from an email address alone.
     *
     * @return array<string, ResolvedSource> tenantId => source
     */
    public function everyTenantWith(string $entity): array
    {
        $tenantIds = $this->readMappings(function () use ($entity) {
            return DB::table(self::TABLE)
                ->where('universal_entity', $entity)
                ->where('is_active', 1)
                ->distinct()
                ->orderBy('tenant_id')
                ->pluck('tenant_id')
                ->all();
        });

        $out = [];

        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;

            // A tenant listed here always resolves, unless its mapping is
            // incomplete — in which case resolve() throws, which is correct: a
            // half-configured tenant must not silently drop out of a search and
            // leave its people unreachable with no explanation.
            $out[$tenantId] = $this->resolve($tenantId, $entity);
        }

        return $out;
    }

    /**
     * Drop cached mappings. Called after a mapping is written, so the change is
     * visible within the same request that made it.
     */
    public function flush(?string $tenantId = null): void
    {
        if ($tenantId === null) {
            $this->cache = [];
            $this->loaded = [];

            return;
        }

        unset($this->cache[$tenantId], $this->loaded[$tenantId]);
    }

    /**
     * Run a read against the mappings table, naming the deployment step if the
     * table is not there at all.
     *
     * ONLY "table does not exist" is translated. Every other database error is
     * rethrown untouched: a connection failure, a permissions problem or a
     * malformed query must not be reported as a missing migration, because that
     * would send whoever is reading the log to fix the wrong thing.
     *
     * @param  Closure  $read
     * @return mixed
     */
    private function readMappings(Closure $read)
    {
        try {
            return $read();
        } catch (QueryException $e) {
            $missing = ((string) $e->getCode() === '42S02')
                || strpos($e->getMessage(), 'no such table: '.self::TABLE) !== false;

            if (! $missing) {
                throw $e;
            }

            throw UnsupportedEntityException::notInstalled(
                self::TABLE,
                (string) DB::connection()->getDatabaseName(),
                $e
            );
        }
    }

    /**
     * One query per tenant per request, assembled into ResolvedSource objects.
     */
    private function load(string $tenantId): void
    {
        if (isset($this->loaded[$tenantId])) {
            return;
        }

        $rows = $this->readMappings(function () use ($tenantId) {
            return DB::table(self::TABLE)
                ->where('tenant_id', $tenantId)
                ->where('is_active', 1)
                ->orderBy('universal_entity')
                ->orderBy('universal_field')
                ->get();
        });

        $byEntity = [];

        foreach ($rows as $row) {
            $entity = (string) $row->universal_entity;
            $field = (string) $row->universal_field;

            if (! isset($byEntity[$entity])) {
                $byEntity[$entity] = [
                    'system' => (string) $row->source_system,
                    'tables' => [],
                    'fields' => [],
                ];
            }

            $byEntity[$entity]['tables'][(string) $row->source_entity] = true;

            $lookupTable = isset($row->lookup_table) ? $row->lookup_table : null;

            $byEntity[$entity]['fields'][$field] = [
                'column' => (string) $row->source_field,
                'type' => (string) ($row->mapping_type ?: 'direct'),
                'expression' => $this->decodeExpression(isset($row->transform_expression) ? $row->transform_expression : null),
                'lookupTable' => ($lookupTable !== null && $lookupTable !== '') ? (string) $lookupTable : null,
            ];
        }

        $resolved = [];

        foreach ($byEntity as $entity => $spec) {
            $tables = array_keys($spec['tables']);

            // Two tables claiming one entity is a configuration error with no
            // safe resolution: picking either binds every subsequent query to an
            // arbitrary winner, and the wrong winner reads the wrong rows.
            if (count($tables) > 1) {
                throw UnsupportedEntityException::ambiguous($tenantId, $entity, $tables);
            }

            foreach ([self::FIELD_ID, self::FIELD_TENANT_KEY] as $required) {
                if (! isset($spec['fields'][$required])) {
                    throw UnsupportedEntityException::incomplete($tenantId, $entity, $required);
                }
            }

            $resolved[$entity] = new ResolvedSource(
                $tenantId,
                $entity,
                $tables[0],
                $spec['system'],
                $spec['fields'][self::FIELD_TENANT_KEY]['column'],
                $spec['fields'][self::FIELD_ID]['column'],
                $spec['fields']
            );
        }

        $this->cache[$tenantId] = $resolved;
        $this->loaded[$tenantId] = true;
    }

    /**
     * transform_expression is stored as JSON describing the transformation, not
     * as SQL.
     *
     * The column is TEXT and its contents are supplied by whoever configures the
     * tenant, so treating it as an expression to interpolate into a query would
     * hand that person the query planner. It is decoded to a structure here and
     * left for the caller to apply in PHP. A value that does not parse as JSON is
     * returned verbatim rather than nulled — the stored text is then not what
     * this class expects, and destroying the only copy of it would be worse than
     * surfacing it.
     *
     * @param  mixed  $raw
     * @return mixed
     */
    private function decodeExpression($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}
