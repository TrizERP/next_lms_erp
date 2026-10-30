<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person notifications with real read state, for the header bell.
 *
 * (sub_institute_id, user_id) is always taken from the verified token when reading. dedupe_key is
 * UNIQUE so a retried job that reaches the same outcome twice cannot raise a second notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_notifications')) {
            return;
        }

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('sub_institute_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 48);
            // info | success | warning | error
            $table->string('severity', 12)->default('info');
            $table->string('title', 191);
            $table->text('body')->nullable();
            // In-app path only, e.g. /documents_new?tab=question-papers&job=<uuid>
            $table->string('link', 255)->nullable();
            $table->string('source_type', 48)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('dedupe_key', 120)->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'user_id', 'read_at', 'id'], 'idx_un_inbox');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
