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
        Schema::create('master_skills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name',255);
            $table->string('category',255);
            $table->string('sub_category',255)->nullable();
            $table->string('title',255);
            $table->text('description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->biginteger('sub_institute_id');
            $table->biginteger('created_by');
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
        Schema::dropIfExists('master_skills');
    }
};
