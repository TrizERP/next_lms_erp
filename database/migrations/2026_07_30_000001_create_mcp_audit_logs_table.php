<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->nullable()->index();
            $table->string('endpoint')->nullable();
            $table->string('tool_name')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('outcome', 20)->nullable()->index();
            $table->longText('input_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
