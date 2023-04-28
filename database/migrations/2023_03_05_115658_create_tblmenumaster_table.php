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
        Schema::create('tblmenumaster', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('menu_title', 255)->nullable();
            $table->string('menu_sortorder', 255)->nullable();
            $table->string('description', 255);
            $table->integer('parent_menu_id');
            $table->integer('level');
            $table->integer('status');
            $table->integer('sort_order');
            $table->string('link', 255);
            $table->string('icon', 255);
            $table->text('sub_institute_id');
            $table->text('client_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->string('menu_type', 50)->nullable();
            $table->string('site_map_name', 50)->nullable();
            $table->string('youtube_link', 50)->nullable();
            $table->string('pdf_link', 50)->nullable();
            $table->string('menu_path', 50)->nullable();
            $table->string('quick_menu', 255)->nullable();
            $table->string('dashboard_menu', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblmenumaster');
    }
};
