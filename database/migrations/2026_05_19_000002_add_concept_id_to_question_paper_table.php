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
        Schema::table('question_paper', function (Blueprint $table) {
            if (!Schema::hasColumn('question_paper', 'concept_id')) {
                $table->unsignedBigInteger('concept_id')->nullable()->after('subject_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_paper', function (Blueprint $table) {
            if (Schema::hasColumn('question_paper', 'concept_id')) {
                $table->dropColumn('concept_id');
            }
        });
    }
};
