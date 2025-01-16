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
        Schema::create('item_scan_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->biginteger('syear');
            $table->string('item_code',50);
            $table->string('scan_status',10)->comment('yes or no');
            $table->tinytext('remarks')->nullable();
            $table->biginteger('item_status_id')->default(0);
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
        Schema::dropIfExists('item_scan_details');
    }
};
