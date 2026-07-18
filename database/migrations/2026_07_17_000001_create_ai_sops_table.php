<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_sops', function (Blueprint $table) {
            $table->id();
            $table->string('sop_name');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->longText('sop_content')->nullable();
            $table->string('pdf_file_path')->nullable();
            $table->string('pdf_url')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('status', 30)->default('Active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'sub_institute_id']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_sops');
    }
};
