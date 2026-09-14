<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives `ai_api_keys` a module and a model, and adds the model catalogue behind them.
 *
 * WHAT THIS ENABLES
 *
 * Until now a credential row said only "here is a key for provider X". Which model it
 * ran, and which part of the product it served, were decided elsewhere — the model by
 * `config/ai.php`, the module not at all, because every AI call drew from the same
 * undifferentiated pool. So an administrator could not say "Conversational AI uses
 * OpenRouter, Generative AI uses Gemini" without an .env edit and a deploy.
 *
 * Two nullable columns make a row a complete binding: module → provider → model → key.
 *
 * EXISTING ROWS ARE UNTOUCHED, AND THAT IS THE POINT
 *
 * Both columns are nullable and neither is backfilled. The four rows on this estate
 * keep `ai_module = NULL`, which `AiConfigurationResolver` reads as "serves any module"
 * — exactly the pool behaviour every current caller has. A module only starts
 * resolving differently once someone saves a row naming it, so this migration on its
 * own changes nothing a user can observe. That is deliberate: the AI paths it sits
 * under are live, and a schema change is not the place to also change behaviour.
 *
 * WHY A CATALOGUE TABLE AND NOT MORE CONFIG
 *
 * `config/ai.php` names one model per driver, which is why Model Management had
 * nothing to manage: a second model for the same provider had nowhere to live, and
 * changing the one it had meant editing a deployed file. `ai_models` is that missing
 * list — many models per provider, addable and editable at runtime, and seeded below
 * with exactly what config already resolves to, so nothing that works today stops.
 *
 * Rows are seeded at platform scope (`sub_institute_id NULL`), so every school sees
 * the same catalogue until one adds a model of its own.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_10_000003_add_ai_module_configuration.php
 */
return new class extends Migration
{
    /**
     * The catalogue as it stands when this runs.
     *
     * `gemini` and `openrouter` are the two drivers with a working client today;
     * `deepseek` is configured in `config/ai.php` but has never had one. The rest are
     * the OpenAI-compatible vendors the new `OpenAiCompatibleClient` can drive.
     *
     * Costs are per 1,000 tokens in USD, and are null wherever the vendor's public
     * pricing is not something this migration should assert. A null cost means usage
     * reporting shows tokens and leaves the money column blank, which is the honest
     * outcome — a guessed rate is worse than no rate on a screen an administrator
     * uses to explain a bill.
     */
    private const CATALOGUE = [
        // provider,     model_id,                         label,                     max out, in/1k,   out/1k
        ['gemini',       'gemini-3.6-flash',               'Gemini 3.6 Flash',        8192,    null,    null],
        ['gemini',       'gemini-3.6-pro',                 'Gemini 3.6 Pro',          8192,    null,    null],
        ['openrouter',   'deepseek/deepseek-chat',         'DeepSeek Chat',           4096,    null,    null],
        ['openrouter',   'openai/gpt-4o-mini',             'GPT-4o mini',             4096,    null,    null],
        ['openrouter',   'anthropic/claude-3.5-sonnet',    'Claude 3.5 Sonnet',       4096,    null,    null],
        ['openai',       'gpt-4o-mini',                    'GPT-4o mini',             4096,    null,    null],
        ['openai',       'gpt-4o',                         'GPT-4o',                  4096,    null,    null],
        ['deepseek',     'deepseek-chat',                  'DeepSeek Chat',           4096,    null,    null],
        ['deepseek',     'deepseek-reasoner',              'DeepSeek Reasoner',       4096,    null,    null],
        ['groq',         'llama-3.3-70b-versatile',        'Llama 3.3 70B',           4096,    null,    null],
        ['mistral',      'mistral-large-latest',           'Mistral Large',           4096,    null,    null],
    ];

    public function up(): void
    {
        $this->addBindingColumns();
        $this->createCatalogue();
        $this->seedCatalogue();
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_api_keys')) {
            foreach (['ai_module', 'model'] as $column) {
                if (Schema::hasColumn('ai_api_keys', $column)) {
                    Schema::table('ai_api_keys', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('ai_models');
    }

    /**
     * The two columns that turn a credential into a binding.
     *
     * Guarded per column rather than per table: a partial run must be resumable, and
     * this estate has migrations that landed one half of their work before.
     */
    private function addBindingColumns(): void
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return;
        }

        if (! Schema::hasColumn('ai_api_keys', 'ai_module')) {
            Schema::table('ai_api_keys', function (Blueprint $table) {
                // NULL = serves any module. Indexed because resolution reads by it on
                // every AI call, which is the hottest lookup this table has.
                $table->string('ai_module', 64)->nullable()->after('api_type')->index();
            });
        }

        if (! Schema::hasColumn('ai_api_keys', 'model')) {
            Schema::table('ai_api_keys', function (Blueprint $table) {
                // NULL = whatever the provider's configured default resolves to, which
                // is what every existing row means today.
                $table->string('model', 120)->nullable()->after('ai_module');
            });
        }
    }

    private function createCatalogue(): void
    {
        if (Schema::hasTable('ai_models')) {
            return;
        }

        Schema::create('ai_models', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('provider', 40)->index();
            // The id the provider itself answers to, sent verbatim on the wire.
            $table->string('model_id', 120);
            // What an administrator reads in a dropdown.
            $table->string('label', 120);

            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->decimal('input_cost_per_1k', 12, 6)->nullable();
            $table->decimal('output_cost_per_1k', 12, 6)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->integer('status')->default(1);   // 1 = selectable, 0 = retired

            // NULL = platform catalogue, visible to every school. A row with an
            // institute is that school's own addition and nobody else sees it.
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

            $table->timestamps();

            // One row per model per scope. Stops a double-submit from putting the
            // same model in a dropdown twice.
            $table->unique(['provider', 'model_id', 'sub_institute_id'], 'ai_models_scope_unique');
        });
    }

    /**
     * Seed the catalogue, including whatever `config/ai.php` currently resolves to.
     *
     * The config models are inserted first and separately from the fixed list above,
     * because a deployment that has pinned its own `GEMINI_MODEL` must find that
     * model in the dropdown — otherwise this migration would quietly narrow the
     * choices to the ones written here.
     */
    private function seedCatalogue(): void
    {
        if (! Schema::hasTable('ai_models')) {
            return;
        }

        $rows = [];
        $order = 0;

        foreach (['gemini', 'openrouter', 'deepseek'] as $driver) {
            $model = trim((string) config("ai.provider.{$driver}.model", ''));

            if ($model === '') {
                continue;
            }

            $rows[$driver . '|' . $model] = [
                'provider' => $driver,
                'model_id' => $model,
                'label' => $model,
                'max_output_tokens' => (int) config("ai.provider.{$driver}.max_output_tokens") ?: null,
                'input_cost_per_1k' => null,
                'output_cost_per_1k' => null,
                'sort_order' => $order++,
                'status' => 1,
                'sub_institute_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (self::CATALOGUE as [$provider, $modelId, $label, $maxOut, $inCost, $outCost]) {
            $key = $provider . '|' . $modelId;

            if (isset($rows[$key])) {
                // Config already contributed this one; keep the friendlier label.
                $rows[$key]['label'] = $label;

                continue;
            }

            $rows[$key] = [
                'provider' => $provider,
                'model_id' => $modelId,
                'label' => $label,
                'max_output_tokens' => $maxOut,
                'input_cost_per_1k' => $inCost,
                'output_cost_per_1k' => $outCost,
                'sort_order' => $order++,
                'status' => 1,
                'sub_institute_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_values($rows) as $row) {
            $exists = DB::table('ai_models')
                ->where('provider', $row['provider'])
                ->where('model_id', $row['model_id'])
                ->whereNull('sub_institute_id')
                ->exists();

            if (! $exists) {
                DB::table('ai_models')->insert($row);
            }
        }
    }
};
