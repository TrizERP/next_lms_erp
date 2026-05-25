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
        Schema::table('lms_misconceptions', function (Blueprint $table) {

            $table->unsignedBigInteger('concept_id')->nullable()->after('id');

            $table->unsignedBigInteger('chapter_id')->nullable()->after('concept_id');

            $table->unsignedBigInteger('subject_id')->nullable()->after('chapter_id');

            $table->unsignedBigInteger('standard_id')->nullable()->after('subject_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_misconceptions', function (Blueprint $table) {

            $table->dropColumn([
                'concept_id',
                'chapter_id',
                'subject_id',
                'standard_id'
            ]);

        });
    }
};