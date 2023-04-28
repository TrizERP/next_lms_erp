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
        Schema::create('nach_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('instructing_agent_member_id', 250)->nullable();
            $table->string('mandate_category', 250)->nullable();
            $table->string('mandate_category_name', 250)->nullable();
            $table->string('name_of_utility', 250)->nullable();
            $table->string('utility_code', 250)->nullable();
            $table->string('sponsor_bank_code', 250)->nullable();
            $table->string('fees_head_mapping', 250)->nullable()->comment('receipt_trust_master\'s ->id');
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nach_master');
    }
};
