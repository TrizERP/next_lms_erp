<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        DB::table('ai_modules')
            ->where('module_key', 'fees')
            ->update([
                'capabilities' => json_encode([
                    'conversational' => true,
                    'generative' => true,
                    'agent' => true,
                    'workflow' => true,
                    'ontology' => true,
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        DB::table('ai_modules')
            ->where('module_key', 'fees')
            ->update([
                'capabilities' => json_encode([
                    'conversational' => true,
                    'generative' => true,
                    'agent' => false,
                    'workflow' => true,
                    'ontology' => false,
                ]),
                'updated_at' => now(),
            ]);
    }
};