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
        Schema::create('s2_log', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('LOT_NO');
            $table->string('MESSAGE_ID', 50);
            $table->date('MESSAGE_CREATION');
            $table->string('INITIATING_PARTY_ID', 50)->nullable();
            $table->string('INSTRUCTING_AGENT_MEMBER_ID', 50);
            $table->string('INSTRUCTED_AGENT_MEMBER_ID', 50);
            $table->string('INSTRUCTED_AGENT_NAME', 50)->nullable();
            $table->string('MANDATE_REQUEST_ID', 50);
            $table->string('MANDATE_CATEGORY', 50);
            $table->string('MANDATE_CATEGORY_NAME', 50);
            $table->string('TXN_TYPE', 50);
            $table->string('RECURRING', 50);
            $table->string('FREQUENCY', 50);
            $table->string('FIRST_COLLECTION_DATE', 50);
            $table->string('FINAL_COLLECTION_DATE', 50)->nullable();
            $table->string('COLLECTION_AMOUNT', 50);
            $table->string('MAXIMUM_AMOUNT', 50);
            $table->string('NAME_OF_UTILITY', 50);
            $table->string('UTILITY_CODE', 50);
            $table->string('SPONSOR_BANK_CODE', 50);
            $table->string('NAME_OF_ACCOUNT_HOLDER', 50);
            $table->string('CONSUMER_REFERENCE_NO', 50);
            $table->string('SCHEME_PLAN_REFERENCE_NO', 50);
            $table->string('DEBTOR_TELEPHONE_NO', 50)->nullable();
            $table->string('DEBTOR_MOBILE_NO', 50);
            $table->string('DEBTOR_EMAIL_ADD', 50)->nullable();
            $table->string('DEBTOR_OTHER_DETAILS', 50)->nullable();
            $table->string('DESTINATION_BANK_ACCOUNT_NUMBER', 50);
            $table->string('DESTINATION_BANK_ACCOUNT_TYPE', 50);
            $table->string('DESTINATION_BANK_IFSC', 50);
            $table->string('DESTINATION_BANK_NAME', 50);
            $table->string('UMRN_NO', 50)->nullable();
            $table->string('STATUS_', 50);
            $table->string('RTN_CODE', 50)->nullable();
            $table->string('REASON', 50);
            $table->date('CLOSURE_DATE');
            $table->string('TRUST_ID', 50);
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('s2_log');
    }
};
