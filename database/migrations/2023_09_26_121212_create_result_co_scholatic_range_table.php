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
        Schema::create('result_co_scholatic_range', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->string('title',20)->nullable()->index();
            $table->decimal('grade_max', 3, 1)->nullable()->index();
            $table->decimal('grade_min', 3, 1)->nullable()->index();  
            $table->integer('breakoff')->nullable()->index();      
            $table->string('comment',50)->nullable()->index();                                        
            $table->bigInteger('syear')->index();            
            $table->bigInteger('sub_institute_id')->index();
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
        Schema::dropIfExists('result_co_scholatic_range');
    }
};
