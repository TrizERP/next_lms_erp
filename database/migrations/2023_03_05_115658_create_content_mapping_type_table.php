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
        Schema::create('content_mapping_type', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('content_id')->nullable();
            $table->integer('mapping_type_id')->nullable();
            $table->integer('mapping_value_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('content_mapping_type');
    }
};
