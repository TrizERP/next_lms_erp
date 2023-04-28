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
        Schema::create('transport_kilometer_rate', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->text('distance_from_school')->nullable();
            $table->text('from_distance')->nullable();
            $table->text('to_distance')->nullable();
            $table->text('rick_old')->nullable();
            $table->text('rick_new')->nullable();
            $table->text('van_old')->nullable();
            $table->text('van_new')->nullable();
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
        Schema::dropIfExists('transport_kilometer_rate');
    }
};
