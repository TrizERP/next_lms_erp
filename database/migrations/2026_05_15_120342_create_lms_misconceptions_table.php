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
        Schema::create('lms_misconceptions', function (Blueprint $table) {

            $table->bigIncrements('id');


            // Error type
            $table->string('error_type')->nullable();

            // Description
            $table->text('description')->nullable();

            // Severity
            $table->integer('severity_default')->default(1);

            // Common fields
            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('deleted_by')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_misconceptions');
    }
};