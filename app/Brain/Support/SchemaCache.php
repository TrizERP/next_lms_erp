<?php

namespace App\Brain\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Per-request memo over Schema::hasTable()/hasColumn().
 *
 * Every Brain read is written defensively — a screen asks whether the table and
 * column it wants exist before touching them — and the database is remote. Left
 * uncached, a single ingestion run issued one INFORMATION_SCHEMA round trip per
 * column per row, which is what made 617 departments take over a minute.
 */
class SchemaCache
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, array<string, bool>> */
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (! isset(self::$columns[$table])) {
            self::$columns[$table] = self::hasTable($table)
                ? array_fill_keys(array_map('strval', Schema::getColumnListing($table)), true)
                : [];
        }

        return isset(self::$columns[$table][$column]);
    }

    /** Only the keys of $row that are real columns of $table. */
    public static function only(string $table, array $row): array
    {
        if (! self::hasTable($table)) {
            return [];
        }

        return array_filter($row, fn ($value, $column) => self::hasColumn($table, $column), ARRAY_FILTER_USE_BOTH);
    }
}
