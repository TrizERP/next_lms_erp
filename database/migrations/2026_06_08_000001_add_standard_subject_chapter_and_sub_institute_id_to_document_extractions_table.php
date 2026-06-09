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
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->unsignedBigInteger('standard_id')->nullable()->after('id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('standard_id');
            $table->unsignedBigInteger('chapter_id')->nullable()->after('subject_id');
            $table->unsignedBigInteger('sub_institute_id')->nullable()->after('chapter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->dropColumn([
                'standard_id',
                'subject_id',
                'chapter_id',
                'sub_institute_id',
            ]);
        });
    }
};
