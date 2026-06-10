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
        Schema::create('lms_master_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->integer('type_id');
            $table->integer('mapping_type_id');
            $table->integer('mapping_value_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_master_mapping');
    }
};
