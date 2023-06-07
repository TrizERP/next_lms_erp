<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnDetailsToTbluserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tbluser', function (Blueprint $table) {
            $table->unsignedBigInteger('jobtitle_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->dateTime('joined_date')->nullable();
            $table->dateTime('probation_period_from')->nullable();
            $table->dateTime('probation_period_to')->nullable();
            $table->dateTime('terminated_date')->nullable();
            $table->text('termination_reason')->nullable();
            $table->dateTime('notice_fromdate')->nullable();
            $table->dateTime('notice_todate')->nullable();
            $table->text('noticereason')->nullable();
            $table->integer('openingleave')->nullable();
            $table->dateTime('relieving_date')->nullable();
            $table->text('relieving_reason')->nullable();
            $table->integer('CL_opening_leave')->nullable();
            $table->string('supervisor_opt')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('reporting_method')->nullable();
            $table->string('branch_name')->nullable()->after('bank_name');
            $table->integer('amount')->nullable()->after('branch_name');
            $table->string('transfer_type')->nullable()->after('amount');
            $table->boolean('monday')->default(0);
            $table->boolean('tuesday')->default(0);
            $table->boolean('wednesday')->default(0);
            $table->boolean('thursday')->default(0);
            $table->boolean('friday')->default(0);
            $table->boolean('saturday')->default(0);
            $table->boolean('sunday')->default(0);
            $table->time('monday_in_date')->nullable();
            $table->time('monday_out_date')->nullable();
            $table->time('tuesday_in_date')->nullable();
            $table->time('tuesday_out_date')->nullable();
            $table->time('wednesday_in_date')->nullable();
            $table->time('wednesday_out_date')->nullable();
            $table->time('thursday_in_date')->nullable();
            $table->time('thursday_out_date')->nullable();
            $table->time('friday_in_date')->nullable();
            $table->time('friday_out_date')->nullable();
            $table->time('saturday_in_date')->nullable();
            $table->time('saturday_out_date')->nullable();
            $table->time('sunday_in_date')->nullable();
            $table->time('sunday_out_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tbluser', function (Blueprint $table) {
            $table->dropColumn('jobtitle_id');
            $table->dropColumn('department_id');
            $table->dropColumn('joined_date');
            $table->dropColumn('probation_period_from');
            $table->dropColumn('probation_period_to');
            $table->dropColumn('terminated_date');
            $table->dropColumn('termination_reason');
            $table->dropColumn('notice_fromdate');
            $table->dropColumn('notice_todate');
            $table->dropColumn('noticereason');
            $table->dropColumn('openingleave');
            $table->dropColumn('relieving_date');
            $table->dropColumn('relieving_reason');
            $table->dropColumn('CL_opening_leave');
            $table->dropColumn('supervisor_opt');
            $table->dropColumn('employee_id');
            $table->dropColumn('reporting_method');
            $table->dropColumn('branch_name');
            $table->dropColumn('amount');
            $table->dropColumn('transfer_type');
            $table->dropColumn('monday');
            $table->dropColumn('tuesday');
            $table->dropColumn('wednesday');
            $table->dropColumn('thursday');
            $table->dropColumn('friday');
            $table->dropColumn('saturday');
            $table->dropColumn('sunday');
            $table->dropColumn('monday_in_date');
            $table->dropColumn('monday_out_date');
            $table->dropColumn('tuesday_in_date');
            $table->dropColumn('tuesday_out_date');
            $table->dropColumn('wednesday_in_date');
            $table->dropColumn('wednesday_out_date');
            $table->dropColumn('thursday_in_date');
            $table->dropColumn('thursday_out_date');
            $table->dropColumn('friday_in_date');
            $table->dropColumn('friday_out_date');
            $table->dropColumn('saturday_in_date');
            $table->dropColumn('saturday_out_date');
            $table->dropColumn('sunday_in_date');
            $table->dropColumn('sunday_out_date');
        });
    }
}
