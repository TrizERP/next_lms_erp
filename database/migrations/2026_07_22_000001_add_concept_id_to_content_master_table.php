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
            if (!Schema::hasColumn('content_master', 'concept_id')) {
                $table->unsignedBigInteger('concept_id')->nullable()->after('chapter_id');
            }
        });

        Schema::table('content_master', function (Blueprint $table) {
            if (
                Schema::hasColumn('content_master', 'concept_id')
                && Schema::hasTable('lms_concept')
            ) {
                $table->foreign('concept_id')
                    ->references('id')
                    ->on('lms_concept')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (Schema::hasColumn('content_master', 'concept_id')) {
                $table->dropForeign(['concept_id']);
                $table->dropColumn('concept_id');
            }
        });
    }
};
