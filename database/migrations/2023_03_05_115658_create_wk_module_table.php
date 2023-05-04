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
        Schema::create('wk_module', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('modulename', 250)->nullable();
            $table->string('fieldname', 250)->nullable();
            $table->string('displayname', 250)->nullable();
            $table->string('tablename', 250)->nullable();
            $table->string('tablealias', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wk_module');
    }
};
