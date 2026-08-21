<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approval workflow configuration for the Leave Management module.
     * One row per sub_institute_id.
     */
    public function up(): void
    {
        Schema::create('hrms_leave_workflow_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->boolean('reporting_manager_enabled')->default(true);
            $table->boolean('department_head_enabled')->default(true);
            $table->boolean('hr_enabled')->default(false);
            $table->boolean('multi_level_enabled')->default(false);
            $table->unsignedTinyInteger('multi_level_count')->default(2);

            $table->boolean('escalation_enabled')->default(true);
            $table->unsignedInteger('escalation_time')->default(24);
            $table->enum('escalation_unit', ['hours', 'days'])->default('hours');
            $table->enum('escalate_to', ['department-head', 'hr', 'admin'])->default('hr');

            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('sub_institute_id', 'hrms_leave_workflow_settings_institute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_leave_workflow_settings');
    }
};
