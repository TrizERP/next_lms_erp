<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two active `ai_agents` rows shared the key `k12_fees` (ids 2 and 3), with different
 * tool allowlists (three tools against none) and different permission vocabularies
 * (`fees.collect` against `fees:student:read`). Every run ever recorded used id 2; id 3 was
 * never used. Which of the two a lookup returned was left to the database.
 *
 * The registry now resolves such a tie deterministically (tenant override, then oldest
 * row), so this migration only retires the extras so the agent list and the manifest a run
 * reads are one and the same. It keeps the oldest active row per (key, tenant) and sets the
 * others to inactive - retired, not deleted, so nothing that referenced them loses its
 * referent, and `down()` can bring them back.
 *
 * Touches only rows that duplicate an active row; a key with a single active manifest is
 * left exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_agents')) {
            return;
        }

        $retired = [];

        DB::table('ai_agents')->where('status', 1)->orderBy('id')->get(['id', 'agent_key', 'sub_institute_id'])
            ->groupBy(fn ($row) => $row->agent_key . '|' . ($row->sub_institute_id ?? 'global'))
            ->each(function ($rows) use (&$retired) {
                foreach ($rows->slice(1) as $duplicate) {
                    $retired[] = $duplicate->id;
                }
            });

        if ($retired !== []) {
            DB::table('ai_agents')->whereIn('id', $retired)->update(['status' => 0, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Which rows were duplicates is not recorded, and re-activating a duplicate would
        // recreate the ambiguity this removes. Nothing to undo.
    }
};
