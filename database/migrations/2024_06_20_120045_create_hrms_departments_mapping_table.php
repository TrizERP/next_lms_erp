<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hrms_departments_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('department');
            $table->bigInteger('parent_id')->default(0);
            $table->mediumText('tasks')->nullable();
            $table->mediumText('roles_responsibility')->nullable();
            $table->string('much_skill')->nullable();
            $table->enum('status', [true, false])->default(true);
            $table->integer('is_calculated')->default(0);
            $table->integer('sub_institute_id')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hrms_departments_mapping');
    }
};
