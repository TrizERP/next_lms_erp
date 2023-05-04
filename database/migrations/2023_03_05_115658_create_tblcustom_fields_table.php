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
        Schema::create('tblcustom_fields', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('table_name', 50)->nullable();
            $table->string('field_name', 50)->nullable();
            $table->string('field_label', 50)->nullable();
            $table->integer('status')->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('field_type', 50)->nullable();
            $table->string('field_message', 50)->nullable();
            $table->string('file_size_max', 50)->nullable();
            $table->integer('required')->nullable()->default(0);
            $table->integer('common_to_all')->nullable()->default(0);
            $table->integer('sub_institute_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblcustom_fields');
    }
};
