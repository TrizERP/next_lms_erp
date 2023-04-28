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
        Schema::create('tblstudent_payment_method_mapping', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->nullable();
            $table->integer('student_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('month_id')->nullable();
            $table->string('payment_method', 250)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('remarks', 250)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_payment_method_mapping');
    }
};
