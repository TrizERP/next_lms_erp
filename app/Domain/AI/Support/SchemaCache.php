<?php

namespace App\Domain\AI\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * `Schema::hasTable()` and `Schema::hasColumn()`, asked once per request instead of
 * once per call site.
 *
 * WHY THIS EXISTS
 *
 * The AI layer is written to degrade rather than crash on an estate that has not run
 * every migration, so almost every read begins by asking whether the table it wants is
 * there. That is the right defence and it is kept. What was wrong is how often the
 * question was asked: each `Schema::hasTable()` is a real round trip to
 * `information_schema`, and this estate's database is remote, so each one costs 200-400ms.
 *
 * Measured on the Admission AI Stack before this class existed:
 *
 *   GET /api/ai/configuration                      31.1s  126 queries
 *                                                  — 70 of them schema probes, 16.8s
 *   GET /api/ai/templates/catalog                   6.1s   22 queries
 *                                                  — 9 identical `ai_modules` reads
 *   GET /api/ai/modules/admissions/usage            7.5s   29 queries
 *   GET /api/ai/capabilities/knowledge-rag          4.0s   17 queries
 *                                                  — 10 `information_schema.columns` probes
 *
 * The answers were identical every time. They have to be: the schema cannot change
 * during a request, so a probe repeated inside one is a round trip bought for nothing.
 *
 * SCOPED, NOT SINGLETON
 *
 * Registered with `scoped()` in `AiServiceProvider`, so the memo lives exactly as long as
 * one request or one queued job and is rebuilt for the next. A process-wide singleton
 * would be faster still and would be wrong: a worker that stayed up across a deployment
 * would keep answering "that column does not exist" after the migration added it, and the
 * failure would be a silently degraded AI layer rather than an error anybody could see.
 *
 * FAILURE IS NOT CACHED AS FALSE
 *
 * A probe that throws — the connection dropped, the user lacks `information_schema` —
 * returns false for that call and is NOT memoised, so a transient database blip cannot
 * pin a table to "missing" for the rest of the request. That matters because "missing"
 * is how this layer decides to skip a whole section of a screen.
 */
final class SchemaCache
{
    /** @var array<string, bool> */
    private array $tables = [];

    /** @var array<string, bool> */
    private array $columns = [];

    public function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tables)) {
            return $this->tables[$table];
        }

        try {
            return $this->tables[$table] = Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether a column exists, without probing for the column when the table is absent.
     *
     * The short-circuit is not just a saving: `Schema::hasColumn()` on a missing table
     * throws on some drivers, and every caller here treats "no table" and "no column" as
     * the same answer anyway.
     */
    public function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, $this->columns)) {
            return $this->columns[$key];
        }

        if (! $this->hasTable($table)) {
            return $this->columns[$key] = false;
        }

        try {
            return $this->columns[$key] = Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Every named table exists. Reads better than three chained `hasTable` calls at the
     * top of a method that needs all of them.
     */
    public function hasTables(string ...$tables): bool
    {
        foreach ($tables as $table) {
            if (! $this->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /** Forget everything. For a test that migrates between assertions. */
    public function flush(): void
    {
        $this->tables = [];
        $this->columns = [];
    }
}
