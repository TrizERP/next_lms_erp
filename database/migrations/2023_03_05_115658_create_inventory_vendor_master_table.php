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
        Schema::create('inventory_vendor_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->decimal('syear', 4, 0)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('vendor_name', 100)->nullable();
            $table->string('contact_number', 10)->nullable();
            $table->string('short_name', 50)->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('address', 100)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('file_number', 50)->nullable();
            $table->string('file_location', 50)->nullable();
            $table->string('company_name', 50)->nullable();
            $table->string('business_type', 50)->nullable();
            $table->string('office_address', 100)->nullable();
            $table->string('office_contact_person', 50)->nullable();
            $table->string('office_number', 50)->nullable();
            $table->string('office_email', 50)->nullable();
            $table->string('tin_no', 50)->nullable();
            $table->date('tin_date')->nullable();
            $table->string('registration_no', 50)->nullable();
            $table->date('registration_date')->nullable();
            $table->string('serivce_tax_no', 50)->nullable();
            $table->date('serivce_tax_date')->nullable();
            $table->string('pan_no', 50)->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_branch', 50)->nullable();
            $table->string('bank_ifsc_code', 50)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip_address', 15)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_vendor_master');
    }
};
