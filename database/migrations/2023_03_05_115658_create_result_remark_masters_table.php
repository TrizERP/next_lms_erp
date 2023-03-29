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
        Schema::create('result_remark_masters', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 255);
            $table->string('sub_institute_id', 255);
            $table->string('marking_period_id', 255);
            $table->string('title', 255);
            $table->string('remark_status', 255);
            $table->string('sort_order', 255);
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
        Schema::dropIfExists('result_remark_masters');
    }
};
