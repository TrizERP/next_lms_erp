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
        Schema::create('tblfields_data', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('field_id')->nullable();
            $table->string('display_text', 50)->nullable();
            $table->string('display_value', 50)->nullable();
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
        Schema::dropIfExists('tblfields_data');
    }
};
