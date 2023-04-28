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
        Schema::create('tblmenumaster_old', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('description', 255);
            $table->integer('parent_menu_id');
            $table->integer('level');
            $table->integer('status');
            $table->integer('sort_order');
            $table->string('link', 255);
            $table->string('icon', 255);
            $table->string('sub_institute_id', 50);
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
        Schema::dropIfExists('tblmenumaster_old');
    }
};
