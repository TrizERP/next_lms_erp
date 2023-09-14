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
        Schema::create('result_template_master', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->bigInteger('sub_institute_id')->index();
            $table->string('module_name',255)->nullable();                
            $table->string('title',255)->nullable();  
            $table->longtext('html_content')->nullable();   
            $table->integer('sort_order')->nullable();                                              
            $table->integer('status')->nullable();                                                                                                     
            $table->bigInteger('created_by')->index()->nullable();                        
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
        Schema::dropIfExists('result_template_master');
    }
};
