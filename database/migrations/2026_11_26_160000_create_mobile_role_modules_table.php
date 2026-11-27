<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which native app screens (config/mobile_app_modules.php) each role may open, per school.
 * Edited in Roles & Access -> Mobile app; read by /api/mobile/bootstrap. One row per grant, so
 * revoking a module deletes its row and the app drops it on the next menu load.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_role_modules')) {
            return;
        }

        Schema::create('mobile_role_modules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('sub_institute_id');
            $table->unsignedInteger('profile_id');
            $table->string('module_key', 60);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['sub_institute_id', 'profile_id', 'module_key'], 'mobile_role_modules_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_role_modules');
    }
};
