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
        Schema::create('fees_breackoff', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->index('syear');
            $table->integer('admission_year')->index('admission_year');
            $table->bigInteger('fee_type_id');
            $table->bigInteger('quota')->index('quota');
            $table->bigInteger('grade_id')->index('grade_id');
            $table->bigInteger('standard_id')->index('standard_id');
            $table->bigInteger('section_id')->index('section_id');
            $table->bigInteger('month_id');
            $table->integer('amount');
            $table->integer('sub_institute_id')->index('sub_institute_id');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->unique([
                'syear', 'admission_year', 'fee_type_id', 'quota', 'grade_id', 'standard_id', 'month_id',
                'sub_institute_id',
            ], 'key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_breackoff');
    }
};
