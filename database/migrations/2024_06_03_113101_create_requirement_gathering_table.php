<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('requirement_gathering', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->text('requirements');
            $table->bigInteger('menu_id');
            $table->string('menu_name', 250);
            $table->bigInteger('created_by_id');
            $table->string('created_by_name', 250);
            $table->bigInteger('sub_institute_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('requirement_gathering');
    }
};
