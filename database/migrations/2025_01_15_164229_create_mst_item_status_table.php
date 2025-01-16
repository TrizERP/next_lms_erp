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
        Schema::create('mst_item_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('item_status_name',255)->nullable();
            $table->string('rules',255)->nullable();
            $table->char('no_loan')->nullable();
            $table->biginteger('sub_institute_id');
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
        Schema::dropIfExists('mst_item_status');
    }
};
