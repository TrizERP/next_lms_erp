<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journey definition — one row per onboarding-able module.
 *
 * `sub_institute_id = 0` is the global template every tenant inherits; a row
 * carrying a real tenant id overrides the template for that tenant only. This
 * is the same "0 = global default, tenant row wins" convention already used by
 * `requirement_gathering`.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('onboarding_module', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('module_key', 60)->comment('Stable slug used by the API and frontend route');
            $table->string('module_name', 120);
            $table->string('menu_title', 120)->nullable()->comment('Joins to tblmenumaster.menu_title');
            $table->string('description', 500)->nullable();
            $table->string('icon', 60)->nullable()->comment('Lucide icon name');
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->bigInteger('sub_institute_id')->default(0)->comment('0 = global template');
            $table->timestamps();

            $table->unique(['module_key', 'sub_institute_id'], 'onboarding_module_key_tenant_unique');
            $table->index(['sub_institute_id', 'status'], 'onboarding_module_tenant_status_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('onboarding_module');
    }
};
