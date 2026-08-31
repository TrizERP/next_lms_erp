<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Role based permission matrix for the Leave Management module.
     * One row per (sub_institute_id, role_name).
     */
    public function up(): void
    {
        Schema::create('hrms_leave_role_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('role_name', 100);
            $table->enum('scope', ['Self', 'Team', 'Department', 'Organization'])->default('Self');

            $table->boolean('approve_leave')->default(false);
            $table->boolean('view_reports')->default(false);
            $table->boolean('configure_settings')->default(false);
            $table->boolean('bulk_operations')->default(false);
            $table->boolean('escalation_rights')->default(false);
            $table->boolean('user_management')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sub_institute_id', 'role_name'], 'hrms_leave_role_permissions_institute_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_leave_role_permissions');
    }
};
