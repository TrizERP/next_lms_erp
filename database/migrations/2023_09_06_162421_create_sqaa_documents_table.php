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
        Schema::create('sqaa_documents', function (Blueprint $table) {
                $table->bigIncrements('id')->unsigned();
                $table->bigInteger('menu_id')->index();
                $table->bigInteger('document_id')->index()->nullable();     
                $table->longtext('title')->nullable();  
                $table->longtext('reasons')->nullable();              
                $table->string('availability',20)->index();                     
                $table->string('file',255)->index()->nullable();
                $table->bigInteger('sub_institute_id')->index();
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
        Schema::dropIfExists('sqaa_documents');
    }
};
