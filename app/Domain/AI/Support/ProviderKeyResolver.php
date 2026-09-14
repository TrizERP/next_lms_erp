<?php

namespace App\Domain\AI\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One tenant-aware lookup for provider credentials, for every AI call site.
 *
 * THE PROBLEM THIS FIXES
 *
 * `ai_api_keys` carries a `sub_institute_id` column and, until this class, nothing
 * read it. Three separate resolutions existed and all three were tenant-blind:
 *
 *   - `getAIKey()` in Helper.php — `where(api_type)->where(status)->first()`, with no
 *     ordering either, so with two active rows of one type it is a coin flip.
 *   - `GeminiClient::resolveApiKey()` — added ordering to dodge that coin flip (one of
 *     the two live `gemini` rows answers 400 API_KEY_INVALID), but still no tenant.
 *   - `QuestionGenerationService::resolveApiKey()` — its own copy again, via getAIKey.
 *
 * So whichever school was signed in, question generation and every other AI call used
 * whichever key the database happened to return. That is the hard-coded-tenant problem
 * in its real form: not a literal `1` in the code, but a lookup that never asks who is
 * asking.
 *
 * THE RULE
 *
 * A school's own key wins; the platform key (`sub_institute_id IS NULL`) is the
 * fallback; within either, the newest active row wins, which is what a rotated key
 * expects. A caller that names no institute gets platform keys only.
 *
 * BEHAVIOUR TODAY IS UNCHANGED, DELIBERATELY
 *
 * All four rows in the pool are currently `sub_institute_id NULL` — platform keys — so
 * this returns exactly what `GeminiClient` returned before: the newest active row of
 * that type. The tenant branch only starts mattering the moment someone adds a
 * school-specific key, which is the point: the capability exists before it is needed,
 * rather than being retrofitted after a school asks why its key is ignored.
 */
class ProviderKeyResolver
{
    /**
     * The credential for one provider type, for one school.
     *
     * @param  string  $apiType  The `ai_api_keys.api_type` value, e.g. `gemini`.
     * @param  int|string|null  $subInstituteId  The signed-in school, or null for platform-only.
     * @param  string|null  $envFallback  Used when the pool has nothing, so a key-table
     *                                    outage degrades rather than takes AI down.
     * @return array{api_key:string, api_limit:int|null, id:int|string|null, scope:string}|null
     */
    public function resolve(string $apiType, int|string|null $subInstituteId = null, ?string $envFallback = null): ?array
    {
        $row = $this->fromPool($apiType, $subInstituteId);

        if ($row !== null) {
            return $row;
        }

        $envKey = trim((string) $envFallback, " \t\n\r\0\x0B'\"");

        if ($envKey === '') {
            return null;
        }

        return ['api_key' => $envKey, 'api_limit' => null, 'id' => null, 'scope' => 'env'];
    }

    /** @return array{api_key:string, api_limit:int|null, id:int|string|null, scope:string}|null */
    private function fromPool(string $apiType, int|string|null $subInstituteId): ?array
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return null;
        }

        try {
            $query = DB::table('ai_api_keys')
                ->where('api_type', $apiType)
                ->where('status', 1);

            $hasTenantColumn = Schema::hasColumn('ai_api_keys', 'sub_institute_id');

            if ($hasTenantColumn) {
                $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);

                if ($institute === null || $institute === '') {
                    $query->whereNull('sub_institute_id');
                } else {
                    $query->where(function ($inner) use ($institute) {
                        $inner->where('sub_institute_id', $institute)
                            ->orWhereNull('sub_institute_id');
                    });
                    // A school's own key beats the platform key. Ordering rather than
                    // two queries keeps it one round trip and one place to read.
                    $query->orderByRaw('sub_institute_id IS NULL ASC');
                }
            }

            // Newest active row wins — the convention a rotated key expects, and what
            // stops an older dead key being picked ahead of the one that works.
            $row = $query->orderByDesc('id')->first();

            if ($row === null || empty($row->api_key) || trim((string) $row->api_key) === '-') {
                return null;
            }

            $rowInstitute = $hasTenantColumn ? ($row->sub_institute_id ?? null) : null;

            return [
                'api_key' => trim((string) $row->api_key),
                'api_limit' => isset($row->api_limit) ? (int) $row->api_limit : null,
                'id' => $row->id ?? null,
                'scope' => $rowInstitute === null || $rowInstitute === '' ? 'platform' : 'institute',
            ];
        } catch (Throwable) {
            // A key-table outage falls through to the caller's env fallback rather
            // than failing the generation outright.
            return null;
        }
    }
}
