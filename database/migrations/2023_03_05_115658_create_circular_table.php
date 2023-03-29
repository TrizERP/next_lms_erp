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
            $table->string('syear', 150);
            $table->string('standard_id', 150);
            $table->string('division_id', 150)->nullable();
            $table->string('title', 250);
            $table->text('message');
            $table->text('file_name')->nullable();
            $table->date('date_');
            $table->bigInteger('sub_institute_id');
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
