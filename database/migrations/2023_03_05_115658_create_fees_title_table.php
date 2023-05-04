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
        Schema::create('fees_title', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('fees_title_id')->default(0)->index('FK_fees_title_fees_title_master');
            $table->string('fees_title', 400)->default('0');
            $table->string('display_name', 400)->default('0');
            $table->string('cumulative_name', 400)->nullable();
            $table->string('append_name', 400)->nullable();
            $table->string('mandatory', 10)->default('0');
            $table->integer('syear')->default(0);
            $table->integer('sub_institute_id')->default(0);
            $table->string('other_fee_id', 10)->default('0');
            $table->integer('rollover_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_title');
    }
};
