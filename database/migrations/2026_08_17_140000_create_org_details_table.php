<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('org_details', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary key

            $table->string('legal_name')->nullable();
            $table->string('cin')->nullable()->index()->comment('Corporate Identification Number');
            $table->string('gstin')->nullable()->comment('Goods and Services Tax Identification Number');
            $table->string('pan')->nullable()->index()->comment('Permanent Account Number');
            $table->text('registered_address')->nullable();
            $table->string('industry')->nullable()->index()->comment('Industry Type');
            $table->string('employee_count')->nullable()->comment('Total Number of Employees');
            $table->string('work_week')->nullable()->comment('Work Week Pattern');
            $table->string('logo')->nullable()->comment('organization logo');

            // Column types matched to the referenced PKs exactly (MySQL
            // requires matching types for FK constraints): school_setup.Id
            // is a signed int(11), tbluser.id is an unsigned int(10).
            $table->integer('sub_institute_id')->nullable()->index();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->unsignedInteger('updated_by')->nullable()->index();

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('sub_institute_id')
                ->references('Id')
                ->on('school_setup')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            $table->foreign('created_by')
                ->references('id')
                ->on('tbluser')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');

            $table->foreign('updated_by')
                ->references('id')
                ->on('tbluser')
                ->onUpdate('NO ACTION')
                ->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_details');
    }
};
