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
        Schema::create('tblemp_skills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->default(0)->nullable();
            $table->string('skills')->nullable();
            $table->timestamps();
        });

        Schema::table('task', function (Blueprint $table) {
            //
            $table->string('required_skill')->nullable()->after('TASK_ALLOCATED_TO');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblemp_skills');
        Schema::table('task', function (Blueprint $table) {
            $table->dropColumn('required_skill');
        });
    }
};
