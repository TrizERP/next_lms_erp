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
        Schema::create('petty_cash', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('title_id')->nullable()->default(0);
            $table->string('description', 255)->nullable();
            $table->integer('amount')->nullable()->default(0);
            $table->timestamp('created_on')->useCurrent();
            $table->integer('user_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('bill_image', 255)->nullable();
            $table->text('file_size')->nullable();
            $table->text('file_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('petty_cash');
    }
};
