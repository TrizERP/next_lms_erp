<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `gemini-flash-latest` the default Gemini model, ahead of `gemini-3.6-flash`.
 *
 * WHY
 *
 * `gemini-3.6-flash` carries a free-tier allowance of **20 requests per day, per project,
 * per model** — `GenerateRequestsPerDayPerProjectPerModel-FreeTier`, quotaValue 20. Both
 * pooled `gemini` keys spent theirs, so every generation answered HTTP 429 and the Create
 * tab refused with "the AI provider's rate limit has been reached". Nothing was wrong
 * with the request, the grounding or the credential: the day's allowance was simply gone.
 *
 * The allowance is per *model*, which is the part worth knowing. `gemini-flash-latest`
 * answered 200 on the same key at the same moment, because it draws on its own quota.
 * Changing which model is default therefore restores service without a billing change.
 *
 * WHY HERE AND NOT IN .env
 *
 * `GEMINI_MODEL` looks like the switch and is not: `AiConfigurationResolver::modelFor()`
 * asks `ModelCatalog::defaultFor()`, which returns the lowest `sort_order` active row in
 * `ai_models` for the provider. The env value is only the fallback when that table has
 * nothing to say. Setting it and expecting the model to change is a trap that cost an
 * afternoon; the catalogue is the source of truth, and it is what the Model Management
 * screen writes to.
 *
 * NOTHING IS REMOVED
 *
 * `gemini-3.6-flash` stays in the catalogue, active and selectable — its quota resets
 * daily and it is the better model. This only changes which one is reached for when
 * nobody has named one. A school that wants it back moves the sort order, in the screen.
 */
return new class extends Migration
{
    private const MODEL_ID = 'gemini-flash-latest';

    public function up(): void
    {
        if (! Schema::hasTable('ai_models')) {
            return;
        }

        $row = [
            'provider' => 'gemini',
            'model_id' => self::MODEL_ID,
            'label' => 'Gemini Flash (latest)',
            // Matches the existing gemini-3.6-flash row, and the client's own default.
            'max_output_tokens' => 1466,
            // `forProvider()` orders by sort_order ascending and `defaultFor()` takes the
            // first. A negative value looks like the tidy way to jump the queue and is
            // not: the column is UNSIGNED, so -1 stored as 0, tied with the row it was
            // meant to overtake, and the tie went to the lower id — leaving the default
            // exactly where it started. So this takes 0 and the row it displaces moves
            // down, below.
            'sort_order' => 0,
            'status' => 1,
            'updated_at' => now(),
        ];

        $existing = DB::table('ai_models')
            ->where('provider', 'gemini')
            ->where('model_id', self::MODEL_ID)
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing !== null) {
            DB::table('ai_models')->where('id', $existing->id)->update($row);
        } else {
            DB::table('ai_models')->insert($row + ['created_at' => now()]);
        }

        // Outside the branch on purpose: re-running the migration after the row exists
        // took the update path and returned before reordering, so the default never
        // moved and the change looked like it had not applied.
        $this->reorderDisplacedModel(1);
    }

    /**
     * Move `gemini-3.6-flash` out of the top slot without retiring it.
     *
     * It stays active and selectable — its daily allowance resets and it is the better
     * model. This only decides which one is used when nobody has named one.
     */
    private function reorderDisplacedModel(int $sortOrder): void
    {
        DB::table('ai_models')
            ->where('provider', 'gemini')
            ->where('model_id', 'gemini-3.6-flash')
            ->whereNull('sub_institute_id')
            ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_models')) {
            return;
        }

        // Retired rather than deleted: a generation request row records the model it ran
        // against, and the catalogue is what gives that string a label.
        DB::table('ai_models')
            ->where('provider', 'gemini')
            ->where('model_id', self::MODEL_ID)
            ->whereNull('sub_institute_id')
            ->update(['status' => 0, 'updated_at' => now()]);

        // And give gemini-3.6-flash its original slot back.
        $this->reorderDisplacedModel(0);
    }
};
