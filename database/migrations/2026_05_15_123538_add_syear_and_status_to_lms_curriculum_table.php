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
        Schema::table('lms_curriculum', function (Blueprint $table) {

            // Academic year
            if (!Schema::hasColumn('lms_curriculum', 'syear')) {
                $table->bigInteger('syear')->nullable();
            }

            // Status
            if (!Schema::hasColumn('lms_curriculum', 'status')) {
                $table->string('status')->nullable()->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_curriculum', function (Blueprint $table) {

            if (Schema::hasColumn('lms_curriculum', 'syear')) {
                $table->dropColumn('syear');
            }

            if (Schema::hasColumn('lms_curriculum', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};