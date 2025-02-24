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
        Schema::create('master_fields_table', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('main_menu',255)->nullable();
            $table->string('module',255)->nullable();
            $table->string('table_name',255)->nullable();
            $table->text('duplicate_fields')->nullable();
            $table->boolean('duplicate_status')->default(false);
            $table->bigInteger('sub_institute_id');
            $table->biginteger('created_by');
            $table->softDeletes();
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
        Schema::dropIfExists('master_fields_table');
    }
};
