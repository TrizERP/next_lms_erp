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
        Schema::create('circular', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear');
            $table->bigInteger('standard_id');
            $table->bigInteger('division_id')->nullable();
            $table->string('title', 250);
            $table->text('message');
            $table->string('file_name')->nullable();
            $table->date('date_');
            $table->integer('sub_institute_id');
            $table->integer('type');
            $table->timestamp('created_at')->nullable()->useCurrent();
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
        Schema::dropIfExists('circular');
    }
};
