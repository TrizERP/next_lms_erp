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
            if (!Schema::hasColumn('suggested_content', 'misconception_id')) {
                $column = $table->unsignedBigInteger('misconception_id')->nullable();

                if (Schema::hasColumn('suggested_content', 'concept_id')) {
                    $column->after('concept_id');
                } elseif (Schema::hasColumn('suggested_content', 'chapter_id')) {
                    $column->after('chapter_id');
                }
            }
        });

        if (!Schema::hasTable('lms_misconceptions')) {
            return;
        }

        DB::table('suggested_content')
            ->whereNull('misconception_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if (empty($row->standard_id) || empty($row->subject_id) || empty($row->chapter_id)) {
                        continue;
                    }

                    $query = DB::table('lms_misconceptions')
                        ->where('standard_id', $row->standard_id)
                        ->where('subject_id', $row->subject_id)
                        ->where('chapter_id', $row->chapter_id);

                    if (isset($row->concept_id) && !empty($row->concept_id) && Schema::hasColumn('lms_misconceptions', 'concept_id')) {
                        $query->where('concept_id', $row->concept_id);
                    }

                    if (!empty($row->sub_institute_id) && Schema::hasColumn('lms_misconceptions', 'sub_institute_id')) {
                        $query->where('sub_institute_id', $row->sub_institute_id);
                    }

                    $misconceptionId = $query->orderBy('id')->value('id');

                    if (!empty($misconceptionId)) {
                        DB::table('suggested_content')
                            ->where('id', $row->id)
                            ->update(['misconception_id' => $misconceptionId]);
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
            if (Schema::hasColumn('suggested_content', 'misconception_id')) {
                $table->dropColumn('misconception_id');
            }
        });
    }
};
