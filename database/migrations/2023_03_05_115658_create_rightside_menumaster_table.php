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
        Schema::create('rightside_menumaster', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('name', 50);
            $table->string('description', 250);
            $table->integer('main_menu_id');
            $table->integer('parent_menu_id');
            $table->integer('tblmenu_master_id')->nullable();
            $table->string('icon', 50);
            $table->integer('status');
            $table->integer('sort_order');
            $table->text('sub_institute_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rightside_menumaster');
    }
};
