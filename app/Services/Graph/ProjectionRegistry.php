<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * MariaDB table -> the projection that knows its graph shape.
 *
 * The database triggers write thin "row X of table T changed" events into
 * `sync_log`. This is how GraphDrain turns that back into a projection call.
 *
 * Two lookups, both needed:
 *
 *   for('tblstudent')        -> StudentGraphProjection    (expand a change event)
 *   tableForLabel('Standard') -> 'standard'               (repair a missing edge
 *                                                          endpoint on the fly)
 *
 * Bespoke projections are listed in $classes; everything whose graph shape is a
 * plain column-to-property map is declared as data in `config/neo4j.php`
 * and served by TableGraphProjection. Adding a straightforward entity is then a
 * config entry, not a class — which is what keeps the K12 entity set from
 * drifting back out of sync one table at a time.
 */
class ProjectionRegistry
{
    /**
     * Projections whose shape cannot be expressed as a column map: a student
     * spans two tables at two different grains (person / enrolment), and a
     * result has to fan out across every enrolment the person holds.
     *
     * @var class-string<GraphProjection>[]
     */
    private const BESPOKE = [
        StudentGraphProjection::class,
        ResultGraphProjection::class,
        // `tbluser` maps to TWO labels — :Teacher for the 118 the reference
        // ingest claimed, :Staff for the other 4,653 — and which one a row
        // belongs to is a fact about the graph, not about the row.
        StaffGraphProjection::class,
    ];

    /** @var array<string, GraphProjection>|null  table => projection */
    private ?array $byTable = null;

    /** @var array<string, string>|null  label => table */
    private ?array $byLabel = null;

    public function __construct(private readonly Container $app)
    {
    }

    public function has(string $table): bool
    {
        return isset($this->byTable()[$table]);
    }

    /**
     * @throws RuntimeException when no projection owns the table
     */
    public function for(string $table): GraphProjection
    {
        $projection = $this->byTable()[$table] ?? null;

        if ($projection === null) {
            throw new RuntimeException(
                "No graph projection registered for table '{$table}' — "
                . 'add one to config/neo4j.php (projections key) or remove its database trigger'
            );
        }

        return $projection;
    }

    /**
     * The table whose projection authors this label, or null when nothing owns
     * it (labels from the CSV-loaded ERP graph, e.g. :Content, :MappingType).
     */
    public function tableForLabel(string $label): ?string
    {
        return $this->byLabel()[$label] ?? null;
    }

    /** @return string[] every table carrying a sync trigger */
    public function tables(): array
    {
        return array_keys($this->byTable());
    }

    // -----------------------------------------------------------------------

    /** @return array<string, GraphProjection> */
    private function byTable(): array
    {
        if ($this->byTable !== null) {
            return $this->byTable;
        }

        $map = [];

        foreach (self::BESPOKE as $class) {
            /** @var GraphProjection $projection */
            $projection = $this->app->make($class);

            foreach ($projection->tables() as $table) {
                $map[$table] = $projection;
            }
        }

        // Declarative entities. A bespoke projection always wins, so a table can
        // be promoted out of config without having to remember to delete its
        // spec first.
        foreach ((array) config('neo4j.projections.entities', []) as $table => $spec) {
            if (! isset($map[$table])) {
                $map[$table] = new TableGraphProjection(
                    $table,
                    $spec,
                    $this->app->make(GraphOutbox::class)
                );
            }
        }

        return $this->byTable = $map;
    }

    /** @return array<string, string> */
    private function byLabel(): array
    {
        if ($this->byLabel !== null) {
            return $this->byLabel;
        }

        $map = [];

        foreach ($this->byTable() as $table => $projection) {
            foreach ($projection->labels() as $label) {
                // First table to claim a label owns it. StudentGraphProjection
                // registers `tblstudent` before `tblstudent_enrollment`, so
                // :StuDetail and :Student both resolve to a table whose
                // projection rebuilds the pair either way.
                $map[$label] ??= $table;
            }
        }

        return $this->byLabel = $map;
    }
}
