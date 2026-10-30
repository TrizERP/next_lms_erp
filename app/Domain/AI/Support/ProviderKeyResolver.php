<?php

namespace App\Domain\AI\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One tenant-aware lookup for provider credentials, for every AI call site.
 *
 * THE PROBLEM THIS FIXES
 *
 * `ai_api_keys` carries a `sub_institute_id` column and, until this class, nothing
 * read it. Three separate resolutions existed and all three were tenant-blind, so
 * whichever school was signed in, AI calls used whichever key the database happened to
 * return. That is the hard-coded-tenant problem in its real form: not a literal `1` in
 * the code, but a lookup that never asks who is asking.
 *
 * THE RULE
 *
 * A school's own key wins; the platform key (`sub_institute_id IS NULL`) is the
 * fallback; within either, a ranked row (`priority`, when that column exists) beats an
 * unranked one and the newest active row wins among equals, which is what a rotated key
 * expects. A caller that names no institute gets platform keys only.
 *
 * THE ENVIRONMENT IS NOT A FALLBACK
 *
 * `$envFallback` is honoured only when `ai.allow_env_credentials` is true, which it is not
 * by default. An empty pool used to be quietly papered over by whatever key the `.env`
 * file held, which let a module run on a provider and account its administrator never
 * chose and could not see. A missing credential is now reported as missing. The switch
 * exists for a deliberate bootstrap — a fresh install before anyone has opened the admin
 * screen — and is documented in `config/ai.php`.
 */
class ProviderKeyResolver
{
    /**
     * The schema probes this class makes, asked once per request.
     *
     * Injected rather than resolved inline so a test can hand in a fresh one. See
     * SchemaCache: these two probes used to run on every credential lookup, and the
     * configuration overview makes fourteen of those in a row.
     */
    public function __construct(private readonly SchemaCache $schema)
    {
    }

    /**
     * The credential for one provider type, for one school.
     *
     * @param  string  $apiType  The `ai_api_keys.api_type` value, e.g. `gemini`.
     * @param  int|string|null  $subInstituteId  The signed-in school, or null for platform-only.
     * @param  string|null  $envFallback  Used only when `ai.allow_env_credentials` is on.
     * @return array{api_key:string, api_limit:int|null, id:int|string|null, scope:string, priority:int|null}|null
     */
    public function resolve(string $apiType, int|string|null $subInstituteId = null, ?string $envFallback = null): ?array
    {
        $row = $this->fromPool($apiType, $subInstituteId);

        if ($row !== null) {
            return $row;
        }

        if (! (bool) config('ai.allow_env_credentials', false)) {
            return null;
        }

        $envKey = trim((string) $envFallback, " \t\n\r\0\x0B'\"");

        if ($envKey === '') {
            return null;
        }

        return ['api_key' => $envKey, 'api_limit' => null, 'id' => null, 'scope' => 'env', 'priority' => null];
    }

    /** @return array{api_key:string, api_limit:int|null, id:int|string|null, scope:string, priority:int|null}|null */
    private function fromPool(string $apiType, int|string|null $subInstituteId): ?array
    {
        if (! $this->schema->hasTable('ai_api_keys')) {
            return null;
        }

        try {
            $query = DB::table('ai_api_keys')
                ->where('api_type', $apiType)
                ->where('status', 1);

            $hasTenantColumn = $this->schema->hasColumn('ai_api_keys', 'sub_institute_id');

            // The shared pool is rows no module claimed. A module-bound row is that module's
            // own credential and is resolved by the module step, never handed to another module.
            if ($this->schema->hasColumn('ai_api_keys', 'ai_module')) {
                $query->whereNull('ai_module');
            }

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

            if ($this->schema->hasColumn('ai_api_keys', 'priority')) {
                $query->orderByRaw('priority IS NULL ASC')->orderBy('priority');
            }

            // Newest active row wins — the convention a rotated key expects, and what
            // stops an older dead key being picked ahead of the one that works.
            $row = $query->orderByDesc('id')->first();

            if ($row === null || empty($row->api_key) || trim((string) $row->api_key) === '-') {
                return null;
            }

            $rawKey = trim((string) $row->api_key);
            $apiKey = self::decryptIfEncrypted($rawKey);

            $rowInstitute = $hasTenantColumn ? ($row->sub_institute_id ?? null) : null;

            return [
                'api_key' => $apiKey,
                'api_limit' => isset($row->api_limit) ? (int) $row->api_limit : null,
                'id' => $row->id ?? null,
                'scope' => $rowInstitute === null || $rowInstitute === '' ? 'platform' : 'institute',
                'priority' => isset($row->priority) && (int) $row->priority > 0 ? (int) $row->priority : null,
            ];
        } catch (Throwable) {
            // A key-table outage is reported as "no key" — the caller says so — rather
            // than failing the generation outright.
            return null;
        }
    }

    /**
     * Decrypt encrypted keys stored in the database with standard Laravel Crypt prefix enc: or enc:v1:
     */
    public static function decryptIfEncrypted(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if (str_starts_with($trimmed, 'enc:v1:')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString(substr($trimmed, 7));
            } catch (Throwable) {
                return $trimmed;
            }
        }
        if (str_starts_with($trimmed, 'enc:')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString(substr($trimmed, 4));
            } catch (Throwable) {
                return $trimmed;
            }
        }

        return $trimmed;
    }
}
