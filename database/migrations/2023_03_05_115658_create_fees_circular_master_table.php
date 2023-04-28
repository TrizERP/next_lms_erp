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
        Schema::create('fees_circular_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->bigInteger('grade_id')->nullable();
            $table->bigInteger('standard_id')->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('address_line1', 250)->nullable();
            $table->string('address_line2', 250)->nullable();
            $table->string('account_no', 150)->nullable();
            $table->string('paid_collection', 150)->nullable();
            $table->string('shift', 100)->nullable();
            $table->string('form_no', 100)->nullable();
            $table->string('branch', 150)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->string('created_ip_address', 150)->nullable();
            $table->dateTime('updated_on')->nullable();
            $table->integer('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_circular_master');
    }
};
