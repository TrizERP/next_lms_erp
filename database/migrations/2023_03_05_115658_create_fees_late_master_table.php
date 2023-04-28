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
        Schema::create('fees_late_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->date('late_date');
            $table->string('standard_id', 50)->default('0');
            $table->integer('syear')->default(0);
            $table->bigInteger('term_id')->default('0');
            $table->integer('sub_institute_id')->default(0);
            $table->integer('created_by')->default(0);
            $table->timestamp('created_on')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_late_master');
    }
};
