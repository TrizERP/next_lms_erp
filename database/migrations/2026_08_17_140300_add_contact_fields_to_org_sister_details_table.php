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
        Schema::table('org_sister_details', function (Blueprint $table) {
            $table->string('mobile_no', 20)->nullable();
            $table->string('country_code', 5)->nullable()->default('+91');
            $table->string('email', 255)->nullable();
            $table->string('website', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_sister_details', function (Blueprint $table) {
            $table->dropColumn(['mobile_no', 'country_code', 'email', 'website']);
        });
    }
};
