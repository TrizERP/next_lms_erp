<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs the original workspace seed: Students could run the Academic Risk agent
 * but its workflow capability was disabled, so a recorded approval could not advance
 * to the already-bound academic intervention workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        DB::table('ai_modules')
            ->where('module_key', 'students')
            ->orderBy('id')
            ->get()
            ->each(function (object $row): void {
                $capabilities = json_decode((string) ($row->capabilities ?? ''), true);

                if (! is_array($capabilities)) {
                    return;
                }

                if (($capabilities['workflow'] ?? false) === true) {
                    return;
                }

                $capabilities['workflow'] = true;

                DB::table('ai_modules')->where('id', $row->id)->update([
                    'capabilities' => json_encode($capabilities),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Do not disable a workflow that an administrator may have since enabled.
    }
};
