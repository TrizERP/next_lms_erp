<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_experience_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // FK to tbluser
            $table->unsignedBigInteger('sub_institute_id'); // FK to institute
            $table->string('institute_name')->nullable();
            $table->string('designation')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->string('experience')->nullable(); // could be "2 years 5 months" etc.
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_experience_details');
    }
};
