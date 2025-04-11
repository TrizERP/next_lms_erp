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
        Schema::create('donation_collection', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('donar_id');
            $table->date('paid_date');
            $table->integer('donation_amount');
            $table->string('payment_mode');
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->mediumText('remarks')->nullable();
            $table->string('reciept_no')->nullable();
            $table->longText('reciept_html')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->bigInteger('sub_institute_id');
            $table->biginteger('created_by')->nullable();
            $table->biginteger('updated_by')->nullable();
            $table->biginteger('deleted_by')->nullable();
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
        Schema::dropIfExists('donation_collection');
    }
};
