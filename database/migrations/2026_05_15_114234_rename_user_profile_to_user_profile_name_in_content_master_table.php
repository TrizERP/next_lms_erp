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
        Schema::table('content_master', function (Blueprint $table) {

            if (
                Schema::hasColumn('content_master', 'user_profile') &&
                !Schema::hasColumn('content_master', 'user_profile_name')
            ) {
                $table->renameColumn('user_profile', 'user_profile_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_master', function (Blueprint $table) {

            if (
                Schema::hasColumn('content_master', 'user_profile_name') &&
                !Schema::hasColumn('content_master', 'user_profile')
            ) {
                $table->renameColumn('user_profile_name', 'user_profile');
            }
        });
    }
};