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
        Schema::create('school_setup', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('Id');
            $table->string('SchoolName', 255);
            $table->string('ShortCode', 255);
            $table->string('ContactPerson', 255);
            $table->string('Mobile', 255);
            $table->string('Email', 255);
            $table->string('ReceiptHeader', 255);
            $table->string('ReceiptAddress', 255);
            $table->string('FeeEmail', 255);
            $table->string('ReceiptContact', 255);
            $table->string('SortOrder', 255);
            $table->string('Logo', 255);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->string('created_by', 150)->nullable();
            $table->string('created_ip', 250)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->integer('client_id')->nullable()->index('FK_school_setup_tblclient');
            $table->string('is_lms', 50)->nullable()->default('N');
            $table->string('cheque_return_charges', 50)->nullable()->default('0');
            $table->string('syear', 10)->nullable();
            $table->date('expire_date')->nullable();
            $table->string('given_space_mb', 250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('school_setup');
    }
};
