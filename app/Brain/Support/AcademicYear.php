<?php

namespace App\Brain\Support;

use Illuminate\Support\Facades\DB;

/**
 * The academic year a Brain request is answered for.
 *
 * The LMS's own year switcher (the header dropdown) stores its choice as a
 * `syear` — the integer year in `academic_year.syear`, one row per
 * (institute, syear, term). Every year-aware LMS API takes that same value as
 * `syear`, so the Brain takes it under the same name and means the same thing.
 *
 * THE REQUESTED YEAR IS VALIDATED, NOT TRUSTED. A caller can ask for any year
 * this INSTITUTE has rows for; anything else falls back to the institute's
 * current year rather than being passed into a query. The tenant itself is
 * never negotiable — it comes from the signed token — so the worst a forged
 * `syear` can do is show the caller a different year of their OWN school.
 */
final class AcademicYear
{
    /** Memo per (tenant, requested) so one page load costs one lookup. */
    private static array $resolved = [];

    /**
     * The year to answer with: the requested one when this institute has it,
     * otherwise the institute's current year, otherwise null (no year data —
     * every year filter is then skipped rather than matching nothing).
     */
    public static function resolve(string $tenantId, ?string $requested): ?string
    {
        $requested = self::normalize($requested);
        $key = $tenantId.'|'.($requested ?? '');

        return self::$resolved[$key] ??= self::lookup($tenantId, $requested);
    }

    /** Every year this institute has, newest first — what the LMS switcher offers. */
    public static function availableFor(string $tenantId): array
    {
        if (! SchemaCache::hasTable('academic_year')) {
            return [];
        }

        return DB::table('academic_year')
            ->where('sub_institute_id', $tenantId)
            ->distinct()
            ->orderByDesc('syear')
            ->pluck('syear')
            ->map(fn ($year) => (string) $year)
            ->all();
    }

    public static function forget(): void
    {
        self::$resolved = [];
    }

    /**
     * "2022", 2022 and "2022-23" all mean syear 2022 — the same four-digit
     * reading the LMS fees client already applies to the stored selection.
     */
    private static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/\d{4}/', $value, $m) ? $m[0] : null;
    }

    private static function lookup(string $tenantId, ?string $requested): ?string
    {
        if (! SchemaCache::hasTable('academic_year')) {
            return null;
        }

        if ($requested !== null) {
            $exists = DB::table('academic_year')
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $requested)
                ->exists();

            if ($exists) {
                return $requested;
            }
        }

        return self::currentFor($tenantId);
    }

    /**
     * The institute's current year: the one today falls inside, else its most
     * recent. Same rule BrainIntelligenceController::currentAcademicYear() uses
     * to label the screen, so the label and the data can never disagree.
     */
    public static function currentFor(string $tenantId): ?string
    {
        if (! SchemaCache::hasTable('academic_year')) {
            return null;
        }

        $today = now()->toDateString();

        $syear = DB::table('academic_year')
            ->where('sub_institute_id', $tenantId)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderByDesc('sort_order')
            ->value('syear');

        $syear ??= DB::table('academic_year')
            ->where('sub_institute_id', $tenantId)
            ->orderByDesc('syear')
            ->value('syear');

        return $syear === null ? null : (string) $syear;
    }
}
