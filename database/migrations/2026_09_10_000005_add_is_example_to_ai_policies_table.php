<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_policies', 'is_example')) {
                $table->tinyInteger('is_example')->default(0)->after('policy_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_policies', function (Blueprint $table) {
            if (Schema::hasColumn('ai_policies', 'is_example')) {
                $table->dropColumn('is_example');
            }
        });
    }
};
