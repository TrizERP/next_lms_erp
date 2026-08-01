<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_confirmation_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('tool_name')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->longText('arguments_json');
            $table->longText('preview_json')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_confirmation_requests');
    }
};
