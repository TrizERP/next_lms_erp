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
        Schema::create('tblindividual_rights', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->default(0);
            $table->integer('menu_id')->default(0);
            $table->integer('profile_id')->default(0);
            $table->integer('can_view')->nullable()->default(0)->comment('1 meanys Y and 0 means N');
            $table->integer('can_add')->nullable()->default(0)->comment('1 meanys Y and 0 means N');
            $table->integer('can_edit')->nullable()->default(0)->comment('1 meanys Y and 0 means N');
            $table->integer('can_delete')->nullable()->default(0)->comment('1 meanys Y and 0 means N');
            $table->timestamp('created_at')->useCurrent();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('client_id')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblindividual_rights');
    }
};
