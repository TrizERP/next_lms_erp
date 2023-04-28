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
        Schema::create('std_div_map', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->unsignedInteger('standard_id')->index('std_div_map_standard_id_foreign');
            $table->unsignedInteger('division_id')->index('std_div_map_division_id_foreign');
            $table->integer('sub_institute_id');
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
        Schema::dropIfExists('std_div_map');
    }
};
