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
        Schema::create('onet_institute_data', function (Blueprint $table) {
            $table->integer('id')->unsigned()->autoIncrement();
            $table->string('college_name', 255); // VARCHAR(255)
            $table->text('description')->nullable(); // TEXT
            $table->string('aicte_id', 50)->unique(); // VARCHAR(50)
            $table->string('type', 25); // VARCHAR(25)
            $table->string('level', 25); // VARCHAR(25)
            $table->string('address', 255); // VARCHAR(255)
            $table->string('district', 25); // VARCHAR(25)
            $table->string('state', 25); // VARCHAR(25)
            $table->string('image', 255); // VARCHAR(255)
            $table->char('women', 1); // CHAR(1)
            $table->char('minority', 1); // CHAR(1)
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('onet_institute_data');
    }
};
