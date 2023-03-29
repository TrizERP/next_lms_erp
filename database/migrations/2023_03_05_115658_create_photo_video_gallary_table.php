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
        Schema::create('photo_video_gallary', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('standard_id')->default(0);
            $table->integer('division_id')->default(0);
            $table->string('album_title', 150)->nullable();
            $table->string('title', 250);
            $table->text('type');
            $table->string('ai', 50)->default('Active');
            $table->text('file_name');
            $table->text('file_size');
            $table->text('file_type');
            $table->date('date_');
            $table->bigInteger('sub_institute_id');
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
        Schema::dropIfExists('photo_video_gallary');
    }
};
