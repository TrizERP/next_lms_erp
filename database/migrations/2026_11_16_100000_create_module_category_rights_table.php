<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-wise visibility of a module's navigation tabs (Operations, Reports, Onboarding, Workflow,
 * Audit Trail ...). Those tabs are rows of `fees_menu_categories`, not of `tblmenumaster`, so the
 * menu rights never covered them.
 *
 * A row here HIDES one tab of one module from one role at one school. No row = visible, so
 * nothing changes for any role until somebody unticks a tab in Roles & Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_category_rights')) {
            return;
        }

        Schema::create('module_category_rights', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sub_institute_id');
            $table->unsignedInteger('profile_id');
            $table->string('module_name', 100);
            $table->string('category_key', 100);
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamps();

            $table->unique(['sub_institute_id', 'profile_id', 'module_name', 'category_key'], 'mcr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_category_rights');
    }
};
