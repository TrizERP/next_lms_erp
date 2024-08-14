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
        Schema::create('onet_employer', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID (int 11 by default in Laravel)
            $table->string('category', 50);
            $table->string('profile', 50);
            $table->string('type', 25);
            $table->string('company_name', 100);
            $table->text('description')->nullable();
            $table->string('company_logo', 100)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('address', 100)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->string('stipend', 100)->nullable();
            $table->string('duration', 100)->nullable();
            $table->string('placement', 100)->nullable();
            $table->string('status', 100)->nullable();
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('onet_employer');
    }
};
