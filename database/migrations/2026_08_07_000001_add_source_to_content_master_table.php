<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (!Schema::hasColumn('content_master', 'source')) {
                $table->string('source', 50)->nullable()->after('content_category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (Schema::hasColumn('content_master', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
