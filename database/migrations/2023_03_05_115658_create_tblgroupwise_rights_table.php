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
        Schema::create('tblgroupwise_rights', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('menu_id')->nullable();
            $table->integer('profile_id')->nullable();
            $table->integer('can_view')->nullable()->default(0);
            $table->integer('can_add')->nullable()->default(0);
            $table->integer('can_edit')->nullable()->default(0);
            $table->integer('can_delete')->nullable()->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
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
        Schema::dropIfExists('tblgroupwise_rights');
    }
};
