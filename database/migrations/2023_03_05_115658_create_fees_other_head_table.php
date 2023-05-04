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
        Schema::create('fees_other_head', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 10)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('amount', 150)->nullable();
            $table->string('include_imprest', 5)->nullable();
            $table->integer('status')->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('created_ip', 200)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->useCurrent();
            $table->dateTime('updated_at')->nullable();
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
        Schema::dropIfExists('fees_other_head');
    }
};
