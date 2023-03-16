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
        Schema::create('form_builder', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('form_name', 255)->nullable();
            $table->longText('form_xml')->nullable();
            $table->longText('form_json')->nullable();
            $table->integer('form_active')->default(0);
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
        Schema::dropIfExists('form_builder');
    }
};
