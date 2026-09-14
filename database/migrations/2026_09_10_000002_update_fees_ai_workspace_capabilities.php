<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_modules')) {
            $row = DB::table('ai_modules')
                ->where('module_key', 'fees')
                ->whereNull('sub_institute_id')
                ->first();

            if ($row) {
                $capabilities = json_decode($row->capabilities ?? '[]', true);
                if (! is_array($capabilities)) {
                    $capabilities = [];
                }

                $capabilities['workflow'] = true;

                DB::table('ai_modules')
                    ->where('id', $row->id)
                    ->update([
                        'capabilities' => json_encode($capabilities),
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            $exists = DB::table('ai_suggestions')
                ->where('module_key', 'fees')
                ->where('capability', 'workflow')
                ->where('label', 'Review pending fees')
                ->whereNull('sub_institute_id')
                ->exists();

            if (! $exists) {
                DB::table('ai_suggestions')->insert([
                    'module_key' => 'fees',
                    'capability' => 'workflow',
                    'label' => 'Review pending fees',
                    'description' => null,
                    'icon' => null,
                    'action_type' => 'start_workflow',
                    'action_ref' => 'fees_collection',
                    'prompt' => null,
                    'payload' => null,
                    'requires_entity' => false,
                    'allowed_roles' => null,
                    'required_permissions' => null,
                    'sort_order' => 10,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_modules')) {
            $row = DB::table('ai_modules')
                ->where('module_key', 'fees')
                ->whereNull('sub_institute_id')
                ->first();

            if ($row) {
                $capabilities = json_decode($row->capabilities ?? '[]', true);
                if (is_array($capabilities)) {
                    $capabilities['workflow'] = false;

                    DB::table('ai_modules')
                        ->where('id', $row->id)
                        ->update([
                            'capabilities' => json_encode($capabilities),
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', 'fees')
                ->where('capability', 'workflow')
                ->where('label', 'Review pending fees')
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }
};
