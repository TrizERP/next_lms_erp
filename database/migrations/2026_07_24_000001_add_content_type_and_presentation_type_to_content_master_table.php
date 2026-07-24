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
            if (!Schema::hasColumn('content_master', 'content_type')) {
                $table->string('content_type', 100)->nullable()->after('content_category');
            }

            if (!Schema::hasColumn('content_master', 'presentation_type')) {
                $table->string('presentation_type', 150)->nullable()->after('content_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (Schema::hasColumn('content_master', 'presentation_type')) {
                $table->dropColumn('presentation_type');
            }

            if (Schema::hasColumn('content_master', 'content_type')) {
                $table->dropColumn('content_type');
            }
        });
    }
};
