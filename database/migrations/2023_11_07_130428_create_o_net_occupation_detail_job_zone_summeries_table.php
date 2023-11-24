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
        Schema::create('o_net_occupation_detail_job_zone_summeries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('o_net_occupation_detail_list_id');
            $table->text('title');
            $table->text('education');
            $table->text('related_experience');
            $table->text('job_training');
            $table->text('job_zone_examples');
            $table->text('svp_range');
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
        Schema::dropIfExists('o_net_occupation_detail_job_zone_summeries');
    }
};
