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
        Schema::create('student_anacdotal', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('syear')->index();    
            $table->bigInteger('sub_institute_id')->index();
            $table->integer('student_id')->nullable()->index();
            $table->string('place', 50)->nullable()->index();  
            $table->date('date')->nullable()->index();
            $table->time('time')->nullable()->index();                                        
            $table->string('observation', 255)->nullable()->index();            
            $table->string('observer_name', 50)->nullable()->index();
            $table->string('life_skills', 5)->nullable()->index();
            $table->string('life_values', 5)->nullable()->index();
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
        Schema::dropIfExists('anacdotal');
    }
};
