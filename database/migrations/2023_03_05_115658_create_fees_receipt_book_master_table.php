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
        Schema::create('fees_receipt_book_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->nullable();
            $table->integer('receipt_id')->default(0)->index('receipt_id');
            $table->string('receipt_line_1')->nullable();
            $table->string('receipt_line_2')->nullable();
            $table->string('receipt_line_3')->nullable();
            $table->string('receipt_line_4')->nullable();
            $table->string('receipt_prefix', 50)->nullable();
            $table->string('receipt_postfix', 50)->nullable();
            $table->string('receipt_logo')->nullable();
            $table->string('account_number', 50)->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('last_receipt_number', 50)->nullable();
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('fees_head_id')->nullable();
            $table->integer('status')->nullable();
            $table->string('pan', 50)->nullable();
            $table->string('bank_logo')->nullable();
            $table->string('branch')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_on')->useCurrent();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_receipt_book_master');
    }
};
