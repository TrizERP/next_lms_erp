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
        Schema::create('fees_axis', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear');
            $table->string('encryption_key', 150);
            $table->string('checksum_key', 150);
            $table->integer('cid');
            $table->string('merchant_id', 150)->nullable();
            $table->integer('sub_institute_id');
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
        Schema::dropIfExists('fees_axis');
    }
};
