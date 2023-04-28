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
        Schema::create('follow_up', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->integer('enquiry_id')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 50)->nullable();
            $table->mediumText('remarks')->nullable();
            $table->string('module_type', 50)->nullable();
            $table->timestamp('created_on')->useCurrent();
            $table->string('created_ip', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('follow_up');
    }
};
