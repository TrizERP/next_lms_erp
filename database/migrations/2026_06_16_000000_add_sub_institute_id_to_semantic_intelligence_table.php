<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('semantic_intelligence', function (Blueprint $table) {
            $table->integer('sub_institute_id')->nullable()->after('chapter_id');
            $table->index(['sub_institute_id', 'chapter_id', 'subject_id'], 'si_inst_chap_sub');
        });

        // Backfill existing rows from their related chapter's institute so they
        // remain queryable once the join is scoped by sub_institute_id.
        if (Schema::hasColumn('semantic_intelligence', 'chapter_id')
            && Schema::hasColumn('chapter_master', 'sub_institute_id')) {
            DB::statement(
                'UPDATE semantic_intelligence si
                 JOIN chapter_master cm ON cm.id = si.chapter_id
                 SET si.sub_institute_id = cm.sub_institute_id
                 WHERE si.sub_institute_id IS NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semantic_intelligence', function (Blueprint $table) {
            $table->dropIndex('si_inst_chap_sub');
            $table->dropColumn('sub_institute_id');
        });
    }
};
