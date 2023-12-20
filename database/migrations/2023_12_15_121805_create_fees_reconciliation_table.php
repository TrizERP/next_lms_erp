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
        Schema::create('fees_reconciliation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('icid', 15)->nullable();
            $table->text('reference_no', 30)->nullable();
            $table->string('subMerchant_id', 10)->nullable();
            $table->string('PGAmount', 20)->nullable();
            $table->text('student_name', 15)->nullable();
            $table->text('mobile_no', 15)->nullable();
            $table->text('email_iD', 15)->nullable();
            $table->text('CN_student', 15)->nullable();
            $table->text('sports_type', 15)->nullable();
            $table->text('tenure', 15)->nullable();
            $table->text('batch', 15)->nullable();
            $table->text('UPIVPA', 15)->nullable();
            $table->text('TRANID', 30)->nullable();
            $table->text('merchant_opted_mode', 50)->nullable();
            $table->text('tran_date', 15)->nullable();
            $table->decimal('tran_amount', 10, 2)->nullable();
            $table->decimal('PROCESSINGFEE', 10, 2)->nullable();
            $table->text('SERVICETAX', 15)->nullable();
            $table->text('payer_opted_mode', 15)->nullable();
            $table->text('recon_date', 15)->nullable();
            $table->text('settlement_date', 15)->nullable();
            $table->text('transaction_process', 15)->nullable();
            $table->text('UNIQUE_REF_NUMBER', 15)->nullable();
            $table->text('VAN_NUMBER', 15)->nullable();
            $table->text('student_id', 15)->nullable();
            $table->text('term_id', 15)->nullable();
            $table->text('recept_no', 15)->nullable();
            $table->text('standard_id', 15)->nullable();
            $table->text('paymode', 15)->nullable();
            $table->text('bank_detals', 15)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->integer('sub_institute_id')->nullable();
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
        Schema::dropIfExists('fees_reconciliation');
    }
};
