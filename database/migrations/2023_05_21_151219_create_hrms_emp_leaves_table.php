<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hrms_emp_leaves', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('department_id')->nullable();
            $table->bigInteger('user_id');
            $table->bigInteger('leave_type_id')->unsigned();
            $table->foreign('leave_type_id')->references('id')->on('hrms_leave_types')->cascadeOnDelete();
            $table->string('day_type')->nullable();
            $table->string('slot')->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('comment')->nullable();
            $table->text('hod_comment')->nullable();
            $table->date('hod_comment_date')->nullable();
            $table->text('hr_remarks')->nullable();
            $table->date('hr_remark_date')->nullable();
            $table->string('approved_by')->nullable();
            $table->enum('status', ['approved', 'cancelled', 'rejected', 'pending', 'approved_lwp'])->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hrms_emp_leaves');
    }
};
