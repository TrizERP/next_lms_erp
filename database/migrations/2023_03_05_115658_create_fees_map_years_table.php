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
        Schema::create('fees_map_years', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('from_month')->default(0);
            $table->integer('to_month')->default(0);
            $table->integer('syear')->default(0);
            $table->integer('sub_institute_id')->default(0);
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
        Schema::dropIfExists('fees_map_years');
    }
};
