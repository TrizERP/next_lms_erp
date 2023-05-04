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
        Schema::create('fees_config_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->decimal('late_fees_amount', 10, 0)->nullable();
            $table->integer('send_sms')->nullable();
            $table->integer('send_email')->nullable();
            $table->mediumText('fees_receipt_template')->nullable();
            $table->mediumText('fees_bank_challan_template')->nullable();
            $table->mediumText('fees_receipt_note')->nullable();
            $table->string('institute_name', 50)->nullable();
            $table->string('pan_no', 50)->nullable();
            $table->string('account_to_be_credited', 50)->nullable();
            $table->string('cms_client_code', 150)->nullable();
            $table->string('auto_head_counting', 5)->nullable();
            $table->string('nach_account_type', 50)->nullable();
            $table->decimal('nach_registration_charge', 10, 0)->nullable();
            $table->decimal('nach_transaction_charge', 10, 0)->nullable();
            $table->decimal('nach_failed_charge', 10, 0)->nullable();
            $table->string('bank_logo', 50)->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('fees_config_master');
    }
};
