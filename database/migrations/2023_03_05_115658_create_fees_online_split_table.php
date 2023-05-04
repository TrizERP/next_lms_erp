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
        Schema::create('fees_online_split', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('fees_title_id')->default(0);
            $table->string('bank_split_name', 50)->default('0');
            $table->integer('sub_institute_id', 10);
            $table->dateTime('updated_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_online_split');
    }
};
