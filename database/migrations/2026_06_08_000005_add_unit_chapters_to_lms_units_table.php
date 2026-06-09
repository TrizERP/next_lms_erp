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
        Schema::table('lms_units', function (Blueprint $table) {
            $table->json('unit_chapters')->nullable()->after('extraction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_units', function (Blueprint $table) {
            $table->dropColumn('unit_chapters');
        });
    }
};
