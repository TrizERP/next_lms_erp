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
        Schema::table('suggested_content', function (Blueprint $table) {
            if (!Schema::hasColumn('suggested_content', 'concept_id')) {
                $table->unsignedBigInteger('concept_id')->nullable()->after('chapter_id');
            }
        });

        if (!Schema::hasTable('lms_concept')) {
            return;
        }

        DB::table('suggested_content')
            ->whereNull('concept_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if (empty($row->standard_id) || empty($row->subject_id) || empty($row->chapter_id)) {
                        continue;
                    }

                    $conceptQuery = DB::table('lms_concept')
                        ->where('standard_id', $row->standard_id)
                        ->where('subject_id', $row->subject_id)
                        ->where('chapter_id', $row->chapter_id);

                    if (!empty($row->sub_institute_id) && Schema::hasColumn('lms_concept', 'sub_institute_id')) {
                        $conceptQuery->where('sub_institute_id', $row->sub_institute_id);
                    }

                    if (!empty($row->syear) && Schema::hasColumn('lms_concept', 'syear')) {
                        $conceptQuery->where('syear', $row->syear);
                    }

                    $conceptId = $conceptQuery->orderBy('id')->value('id');

                    if (!empty($conceptId)) {
                        DB::table('suggested_content')
                            ->where('id', $row->id)
                            ->update(['concept_id' => $conceptId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggested_content', function (Blueprint $table) {
            if (Schema::hasColumn('suggested_content', 'concept_id')) {
                $table->dropColumn('concept_id');
            }
        });
    }
};
