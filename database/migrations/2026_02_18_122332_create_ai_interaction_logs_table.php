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
        Schema::create('ai_interaction_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('menu_type')->index()->nullable();
            $table->string('student_level','50')->index()->nullable();
            $table->bigInteger('student_id')->index()->nullable();
            $table->longText('prompt_by_user')->nullable();
            $table->longText('response_ai')->nullable();
            $table->bigInteger('sub_institute_id')->index()->nullable();
            $table->string('syear',10)->index()->nullable();
            $table->bigInteger('created_by')->index()->nullable();
            $table->bigInteger('updated_by')->index()->nullable();
            $table->bigInteger('deleted_by')->index()->nullable();
            $table->timestamps(); // created_at & updated_at
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ai_interaction_logs');
    }
};
