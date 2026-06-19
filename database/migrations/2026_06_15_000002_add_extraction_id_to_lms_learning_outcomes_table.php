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
        Schema::table('lms_learning_outcomes', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_learning_outcomes', 'extraction_id')) {
                $table->unsignedBigInteger('extraction_id')->nullable()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_learning_outcomes', function (Blueprint $table) {
            if (Schema::hasColumn('lms_learning_outcomes', 'extraction_id')) {
                $table->dropColumn('extraction_id');
            }
        });
    }
};
