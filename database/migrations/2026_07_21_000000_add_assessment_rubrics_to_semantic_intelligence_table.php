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
        Schema::table('semantic_intelligence', function (Blueprint $table) {
            if (! Schema::hasColumn('semantic_intelligence', 'assessment_rubrics')) {
                $table->longText('assessment_rubrics')->nullable()->after('assessment_blueprint');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semantic_intelligence', function (Blueprint $table) {
            if (Schema::hasColumn('semantic_intelligence', 'assessment_rubrics')) {
                $table->dropColumn('assessment_rubrics');
            }
        });
    }
};
