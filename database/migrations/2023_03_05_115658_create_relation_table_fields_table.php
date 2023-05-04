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
        Schema::create('relation_table_fields', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('table_name', 50)->nullable();
            $table->string('table_field', 50)->nullable();
            $table->string('relation_table_name', 50)->nullable();
            $table->string('relation_table_field', 50)->nullable();
            $table->string('relation_table_id', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('relation_table_fields');
    }
};
