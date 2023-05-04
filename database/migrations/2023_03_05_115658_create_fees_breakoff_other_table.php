<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fees_breakoff_other', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->default(0);
            $table->integer('syear')->default(0)->index('syear');
            $table->integer('fee_type_id')->index('fee_type_id');
            $table->integer('month_id')->index('marking_period_id');
            $table->decimal('amount', 10, 0);
            $table->integer('sub_institute_id')->index('sub_institute_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_breakoff_other');
    }
};
