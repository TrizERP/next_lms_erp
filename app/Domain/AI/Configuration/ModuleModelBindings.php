<?php

namespace App\Domain\AI\Configuration;

use App\Domain\AI\Support\SchemaCache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A product module's own model choice, read and written.
 *
 * WHAT THIS IS FOR
 *
 * The AI Stack inside a module is decentralised: what happens on the Fees module's Models
 * tab stays in the Fees module. This class is the storage behind that — one row per
 * product module per capability per scope, in `ai_module_model_bindings`.
 *
 * It sits AHEAD of the capability configuration the central console writes, not instead of
 * it. A module with no binding resolves exactly as it always did, so switching this on
 * changed nothing for any module until somebody chose something.
 *
 * TWO VOCABULARIES, AND THEY ARE BOTH NEEDED
 *
 *   `product_module`  what the page is about — `fees`, `hostel`, `document-templates`.
 *                     An `ai_modules` key.
 *   `capability`      what the call IS — `conversational_ai`, `generative_ai`,
 *                     `agent_reasoning`. An `AiModuleRegistry` key.
 *
 * A binding is the intersection: "when Fees makes a conversational call, use this model".
 * Neither key alone identifies one, which is why the previous single-column approach on
 * `ai_api_keys` could not express this at all.
 *
 * PRECEDENCE
 *
 * This school's own row, then the platform's. The same two steps `ai_api_keys` uses, so
 * there is one rule in this area rather than two.
 *
 * READ ONCE PER REQUEST
 *
 * `overview()` resolves several capabilities for one module and the lifecycle resolves one
 * per stage, so the rows are fetched once per institute and chosen in PHP — the same
 * reason `AiConfigurationResolver::moduleRows()` caches.
 */
class ModuleModelBindings
{
    public const TABLE = 'ai_module_model_bindings';

    /**
     * Rows per institute scope, keyed by the institute (or '' for platform-only reads).
     *
     * @var array<string, array<int, object>|null>
     */
    private array $cache = [];

    public function __construct(private readonly SchemaCache $schema)
    {
    }

    /** Whether this estate has the table at all. An older deployment simply has no bindings. */
    public function available(): bool
    {
        return $this->schema->hasTable(self::TABLE);
    }

    /**
     * The binding that applies to one module's call of one kind, or null.
     *
     * Null is the normal answer and means "resolve as you would have": the module has made
     * no choice, so the central configuration decides. It is not a fault.
     */
    public function find(?string $productModule, ?string $capability, int|string|null $subInstituteId): ?object
    {
        if ($productModule === null || $productModule === '' || $capability === null || $capability === '') {
            return null;
        }

        if (! $this->available()) {
            return null;
        }

        $rows = $this->rows($subInstituteId);

        if ($rows === null) {
            // A table outage falls through to the central configuration rather than
            // failing the call — the same posture the credential lookup takes.
            return null;
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        // This school's own row first, then the platform's. Within each, the newest,
        // which is what a re-save expects.
        $attempts = [];

        if ($institute !== null) {
            $attempts[] = ['module_binding', static fn (object $row) => (string) ($row->sub_institute_id ?? '') === $institute];
        }

        $attempts[] = ['module_binding_platform', static fn (object $row) => ($row->sub_institute_id ?? null) === null];

        foreach ($attempts as [$source, $matches]) {
            foreach ($rows as $row) {
                if ((string) $row->product_module !== $productModule
                    || (string) $row->capability !== $capability
                    || ! $matches($row)) {
                    continue;
                }

                // A row with no provider chooses nothing. It can exist — somebody cleared
                // the field — and it must not shadow the central configuration.
                if (trim((string) ($row->provider ?? '')) === '') {
                    continue;
                }

                // Cloned before stamping, because the row is shared with every other
                // capability resolved from this cache.
                $found = clone $row;
                $found->source = $source;

                return $found;
            }
        }

        return null;
    }

    /**
     * Every binding a module has, for the screen that edits them.
     *
     * Returns the school's own row where one exists and the platform's otherwise, so the
     * screen shows what is actually in force rather than both.
     *
     * @return array<string, object>  capability => row
     */
    public function forModule(string $productModule, int|string|null $subInstituteId): array
    {
        if (! $this->available()) {
            return [];
        }

        $rows = $this->rows($subInstituteId) ?? [];
        $institute = $this->normaliseInstitute($subInstituteId);

        $byCapability = [];

        // Platform rows first, then the institute's over the top — so the institute's own
        // choice wins without the caller having to know the precedence.
        foreach ([null, $institute] as $scope) {
            if ($scope === null && $institute !== null) {
                // still want platform rows; handled by the comparison below
            }

            foreach ($rows as $row) {
                $rowScope = ($row->sub_institute_id ?? null) === null ? null : (string) $row->sub_institute_id;

                if ($rowScope !== $scope) {
                    continue;
                }

                if ((string) $row->product_module !== $productModule) {
                    continue;
                }

                $byCapability[(string) $row->capability] = $row;
            }
        }

        return $byCapability;
    }

    /**
     * Save one module's choice for one capability, as an upsert on the unique index.
     *
     * Returns the row as stored, so the caller reports what is now in force rather than
     * what it asked for.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(
        string $productModule,
        string $capability,
        int|string|null $subInstituteId,
        array $values,
        int|string|null $actorId = null
    ): ?object {
        if (! $this->available()) {
            return null;
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        $payload = [
            'provider' => $this->trimOrNull($values['provider'] ?? null),
            'model' => $this->trimOrNull($values['model'] ?? null),
            'api_key_id' => isset($values['api_key_id']) && $values['api_key_id'] !== null && $values['api_key_id'] !== ''
                ? (int) $values['api_key_id']
                : null,
            'max_output_tokens' => isset($values['max_output_tokens']) && $values['max_output_tokens'] !== null
                && $values['max_output_tokens'] !== '' && (int) $values['max_output_tokens'] > 0
                ? (int) $values['max_output_tokens']
                : null,
            'status' => array_key_exists('status', $values) ? (int) (bool) $values['status'] : 1,
            'updated_by' => $actorId === null ? null : (int) $actorId,
            'updated_at' => now(),
        ];

        $existing = DB::table(self::TABLE)
            ->where('product_module', $productModule)
            ->where('capability', $capability)
            ->where(fn ($query) => $institute === null
                ? $query->whereNull('sub_institute_id')
                : $query->where('sub_institute_id', $institute))
            ->first();

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update($payload);
        } else {
            DB::table(self::TABLE)->insert($payload + [
                'product_module' => $productModule,
                'capability' => $capability,
                'sub_institute_id' => $institute === null ? null : (int) $institute,
                'created_by' => $actorId === null ? null : (int) $actorId,
                'created_at' => now(),
            ]);
        }

        $this->forget();

        return DB::table(self::TABLE)
            ->where('product_module', $productModule)
            ->where('capability', $capability)
            ->where(fn ($query) => $institute === null
                ? $query->whereNull('sub_institute_id')
                : $query->where('sub_institute_id', $institute))
            ->first();
    }

    /**
     * Remove one module's choice, returning it to the central configuration.
     *
     * Deletes rather than deactivates: "use the estate default" has one meaning, and a
     * disabled row that still exists would leave two ways to express it.
     *
     * A platform row is never deleted by an institute-scoped call — clearing a choice must
     * not clear it for every other school.
     */
    public function clear(string $productModule, string $capability, int|string|null $subInstituteId): bool
    {
        if (! $this->available()) {
            return false;
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        $deleted = DB::table(self::TABLE)
            ->where('product_module', $productModule)
            ->where('capability', $capability)
            ->where(fn ($query) => $institute === null
                ? $query->whereNull('sub_institute_id')
                : $query->where('sub_institute_id', $institute))
            ->delete();

        $this->forget();

        return $deleted > 0;
    }

    /** Drop the per-request cache after a write, so the next read sees the new row. */
    public function forget(): void
    {
        $this->cache = [];
    }

    /**
     * Every active binding this institute can see — its own plus the platform's.
     *
     * Null, distinct from an empty list, when the table could not be read.
     *
     * @return array<int, object>|null
     */
    private function rows(int|string|null $subInstituteId): ?array
    {
        $institute = $this->normaliseInstitute($subInstituteId);
        $cacheKey = $institute ?? '';

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('status', 1)
                ->where(function ($inner) use ($institute) {
                    $inner->whereNull('sub_institute_id');

                    if ($institute !== null) {
                        $inner->orWhere('sub_institute_id', $institute);
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->all();
        } catch (Throwable) {
            return $this->cache[$cacheKey] = null;
        }

        return $this->cache[$cacheKey] = $rows;
    }

    private function normaliseInstitute(int|string|null $subInstituteId): ?string
    {
        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);

        return $institute === '' ? null : $institute;
    }

    private function trimOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
