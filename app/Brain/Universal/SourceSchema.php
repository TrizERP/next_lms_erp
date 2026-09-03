<?php

namespace App\Brain\Universal;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Which of a resolved source's mapped columns the physical table actually has.
 *
 * Ported from hp-enterprise-brain/app/Domain/Universal/SourceSchema.php.
 *
 * A MAPPING IS A DESCRIPTION, NOT A GUARANTEE. The mapping seeder deliberately
 * writes a candidate's whole field list — including optional fields a particular
 * deployment's copy of the table happens not to carry — because the description
 * must not depend on which connection ran the seeder. That is the right call at
 * describe time and it leaves exactly one question open at read time: is the
 * column there?
 *
 * Asking it here rather than at each call site is what keeps an optional profile
 * column (an ERP that records `website` and one that does not) from turning a
 * working Organization screen into a SQL error. It is only ever used to NARROW a
 * mapped set — never to guess a column that was not mapped — so it cannot become
 * the fallback EntityResolver exists to forbid.
 *
 * Scoped per request for the same reason as EntityResolver: schemas change under
 * a long-lived worker, and one request asks the same table the same question
 * several times.
 */
final class SourceSchema
{
    /** @var array<string, array<int, string>> table => lower-cased column names */
    private $columns = [];

    /**
     * The mapped universal fields whose source column exists on the table.
     *
     * @param  array<int, string>  $universalFields
     * @return array<string, string> universal field => source column
     */
    public function usable(ResolvedSource $source, array $universalFields): array
    {
        $mapped = $source->columns($universalFields);
        $present = $this->columnsOf($source->table);

        // An unreadable table (another schema, a suite with no ERP fixture)
        // yields no column list. Narrowing to nothing there would blank a screen
        // that works; the mapping is trusted and the read fails loudly if it
        // really is wrong.
        if ($present === []) {
            return $mapped;
        }

        return array_filter($mapped, function ($column) use ($present) {
            return in_array(strtolower($column), $present, true);
        });
    }

    /** Whether one mapped universal field is both mapped and physically present. */
    public function has(ResolvedSource $source, string $universalField): bool
    {
        return $this->usable($source, [$universalField]) !== [];
    }

    /** @return array<int, string> */
    private function columnsOf(string $table): array
    {
        if (! isset($this->columns[$table])) {
            try {
                $this->columns[$table] = array_map('strtolower', Schema::getColumnListing($table));
            } catch (QueryException $e) {
                $this->columns[$table] = [];
            }
        }

        return $this->columns[$table];
    }
}
