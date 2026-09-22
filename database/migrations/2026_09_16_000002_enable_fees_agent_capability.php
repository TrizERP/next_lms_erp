<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turn the agent and ontology capabilities on for the Fees module.
 *
 * `capabilities` is one JSON column holding every flag a module declares, and an estate
 * may carry keys this migration has never heard of — a capability added by a later
 * release, or one switched on for a single institute. Writing a freshly built object
 * over it would enable these two flags by deleting those, silently, with no way to tell
 * afterwards what had been there. So each row is read, the two flags are merged into
 * whatever it already holds, and the merged object is written back. `down()` restores
 * only the same two keys for the same reason.
 */
return new class extends Migration
{
    /** The flags this migration owns, and nothing else. */
    private const ENABLE = ['agent' => true, 'ontology' => true];

    private const DISABLE = ['agent' => false, 'ontology' => false];

    public function up(): void
    {
        $this->merge(self::ENABLE);
    }

    public function down(): void
    {
        $this->merge(self::DISABLE);
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function merge(array $flags): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', 'fees')->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->capabilities, true);

            // A row with no capabilities yet, or with something unparseable in the
            // column, is the one case where there is nothing to preserve. The module
            // still needs to be conversational for the chat to reach it at all, so the
            // defaults it would have had are written alongside the flags.
            if (! is_array($existing)) {
                $existing = ['conversational' => true, 'generative' => true, 'workflow' => true];
            }

            DB::table('ai_modules')
                ->where('id', $row->id)
                ->update([
                    'capabilities' => json_encode(array_merge($existing, $flags)),
                    'updated_at' => now(),
                ]);
        }
    }
};
