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
        Schema::create('onet_expert_advice', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('image', 150);
            $table->string('name', 50);
            $table->string('description', 255);
            $table->string('education', 150);
            $table->string('city', 25);
            $table->string('state', 25);
            $table->string('contact_no', 25);
            $table->string('teacher', 150);
            $table->text('benefits');
            $table->string('university_shortlist', 255);
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
        Schema::dropIfExists('onet_expert_advice');
    }
};
