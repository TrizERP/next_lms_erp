<?php

namespace App\Console\Commands;

use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Configuration\ProviderModelDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Move AI provider keys out of `.env` files and into AI Providers (`ai_api_keys`).
 *
 *   php artisan ai:import-env-keys                       # report only — writes nothing
 *   php artisan ai:import-env-keys --from=D:\lms_k12\.env.local
 *   php artisan ai:import-env-keys --apply               # insert the keys that pass
 *
 * SAFE BY DEFAULT
 *
 * Without `--apply` this only reads. It lists every AI-looking variable in each file, which
 * provider it belongs to, the last four characters of the key, whether that key is already stored,
 * and whether the provider accepts it (a model-list call, which spends no generation quota).
 *
 * With `--apply` it INSERTS rows into the shared pool (all modules, platform scope) for keys that
 * are new and accepted. It never updates or deletes a row, never overwrites a stored key, and never
 * edits any `.env` file — removing a key from a file is a separate step to take once it is confirmed
 * working in AI Providers. The ids it inserts are written to `storage/app/ai-import-<time>.json` so
 * the insert can be undone by deactivating exactly those rows.
 *
 * Keys are never printed in full, logged, or written to the report.
 */
class AiImportEnvKeysCommand extends Command
{
    protected $signature = 'ai:import-env-keys
        {--from=* : Extra .env files to read (the backend .env is always read)}
        {--apply : Insert the new, accepted keys. Without this nothing is written}
        {--include-unverified : Also insert keys the provider could not be reached to check (never invalid ones)}';

    protected $description = 'Report (and with --apply, insert) AI provider keys found in .env files into AI Providers';

    /** Variable-name fragments that identify a provider key, in the order they are tried. */
    private const NAME_RULES = [
        'OPENROUTER' => 'openrouter',
        'DEEPSEEK' => 'deepseek',
        'GEMINI' => 'gemini',
        'GOOGLE_GENERATIVE_AI' => 'gemini',
        'GROQ' => 'groq',
        'MISTRAL' => 'mistral',
        'OPENAI' => 'openai',
        'ANTHROPIC' => 'anthropic',
    ];

    public function handle(ProviderCatalog $providers, ProviderModelDiscovery $discovery): int
    {
        if (! Schema::hasTable('ai_api_keys')) {
            $this->error('ai_api_keys does not exist on this installation.');

            return self::FAILURE;
        }

        $files = array_merge([base_path('.env')], (array) $this->option('from'));
        $found = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                $this->warn("Not found: {$file}");
                continue;
            }

            foreach ($this->read($file) as $name => $value) {
                $found[] = ['file' => $file, 'name' => $name, 'value' => $value];
            }
        }

        if ($found === []) {
            $this->info('No AI provider keys found in the given files.');

            return self::SUCCESS;
        }

        $stored = DB::table('ai_api_keys')->pluck('api_key')->map(fn ($k) => trim((string) \App\Support\KeyVault::reveal((string) $k)))->all();
        $rows = [];
        $toInsert = [];
        $seen = [];

        foreach ($found as $item) {
            $provider = $this->providerFor($item['name'], $item['value']);
            $fingerprint = '…' . substr($item['value'], -4);
            $key = $provider . '|' . $item['value'];

            if ($provider === null) {
                $rows[] = [basename($item['file']), $item['name'], '?', $fingerprint, '—', 'Skipped: provider cannot be told from the name — add it in AI Providers.'];
                continue;
            }

            if (! $providers->isDriveable($provider)) {
                $rows[] = [basename($item['file']), $item['name'], $provider, $fingerprint, '—', 'Skipped: this platform has no client for ' . $provider . '.'];
                continue;
            }

            if (isset($seen[$key])) {
                $rows[] = [basename($item['file']), $item['name'], $provider, $fingerprint, '—', 'Duplicate of a key already listed above.'];
                continue;
            }
            $seen[$key] = true;

            if (in_array($item['value'], $stored, true)) {
                $rows[] = [basename($item['file']), $item['name'], $provider, $fingerprint, 'stored', 'Already in AI Providers — nothing to do.'];
                continue;
            }

            $verdict = $discovery->verify($provider, $item['value'], fresh: true);
            $status = $verdict['status'];

            $action = match ($status) {
                'valid' => 'New and accepted — will be inserted with --apply.',
                'invalid' => 'REJECTED by the provider (revoked or wrong) — not inserted.',
                default => $this->option('include-unverified')
                    ? 'Could not be checked — will be inserted (--include-unverified).'
                    : 'Could not be checked right now — not inserted.',
            };

            $rows[] = [basename($item['file']), $item['name'], $provider, $fingerprint, $status, $action];

            if ($status === 'valid' || ($status !== 'invalid' && $this->option('include-unverified'))) {
                $toInsert[] = ['provider' => $provider, 'value' => $item['value'], 'name' => $item['name']];
            }
        }

        $this->table(['File', 'Variable', 'Provider', 'Key', 'Provider says', 'Result'], $rows);

        if (! $this->option('apply')) {
            $this->line('');
            $this->info(count($toInsert) . ' key(s) would be inserted. Re-run with --apply to insert them. Nothing was written.');

            return self::SUCCESS;
        }

        if ($toInsert === []) {
            $this->info('Nothing to insert.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Insert ' . count($toInsert) . ' row(s) into ai_api_keys (insert only; no row is changed or removed)?')) {
            $this->warn('Cancelled. Nothing was written.');

            return self::SUCCESS;
        }

        $ids = [];

        DB::transaction(function () use ($toInsert, $providers, &$ids) {
            foreach ($toInsert as $row) {
                $columns = [
                    'api_type' => $providers->apiType($row['provider']),
                    'api_key' => \App\Support\KeyVault::seal($row['value']),
                    'account_email' => null,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $ids[] = DB::table('ai_api_keys')->insertGetId($columns);
            }
        });

        $manifest = storage_path('app/ai-import-' . now()->format('Ymd-His') . '.json');
        File::ensureDirectoryExists(dirname($manifest));
        // Ids only — no key material — so the insert can be reversed by deactivating exactly these rows.
        File::put($manifest, json_encode(['inserted_ai_api_keys_ids' => $ids], JSON_PRETTY_PRINT));

        $this->info(count($ids) . ' row(s) inserted. Ids recorded in ' . $manifest);
        $this->line('Confirm each works in AI & Intelligence → AI Providers, then remove the keys from the .env file(s) yourself.');

        return self::SUCCESS;
    }

    /** @return array<string, string> AI key variables in one env file: name => value. */
    private function read(string $file): array
    {
        $out = [];

        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if (! preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }

            [$name, $value] = [$m[1], trim($m[2], " \t\"'")];

            if ($value === '' || ! preg_match('/(API_KEY|_KEY|TOKEN)$/', $name) || $this->nameFragment($name) === null && ! str_starts_with($name, 'LLM_')) {
                continue;
            }

            if (preg_match('/^(GAMMA|HP_|NEXT_PUBLIC|SUPABASE|NEO4J|LARAVEL)/', $name)) {
                continue;
            }

            $out[$name] = $value;
        }

        return $out;
    }

    private function nameFragment(string $name): ?string
    {
        foreach (self::NAME_RULES as $fragment => $provider) {
            if (str_contains($name, $fragment)) {
                return $provider;
            }
        }

        return null;
    }

    private function providerFor(string $name, string $value): ?string
    {
        // The estate's OPENAI_API_KEY holds an OpenRouter key (sk-or-…); the value, not the name, decides.
        if (str_starts_with($value, 'sk-or-')) {
            return 'openrouter';
        }

        if (str_starts_with($value, 'AIza') || str_starts_with($value, 'AQ.')) {
            return 'gemini';
        }

        return $this->nameFragment($name);
    }
}
