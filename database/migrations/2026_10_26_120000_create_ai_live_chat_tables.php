<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_live_chat_sessions')) {
            Schema::create('ai_live_chat_sessions', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('sub_institute_id', 64)->default('1')->nullable()->index();
                $table->string('client_id', 64)->nullable();
                $table->string('user_id', 64)->index();
                $table->string('user_name', 191);
                $table->string('user_role', 64)->default('Student');
                $table->string('user_email', 191)->nullable();
                $table->enum('status', ['waiting', 'active', 'closed'])->default('waiting')->index();
                $table->string('admin_id', 64)->nullable()->index();
                $table->string('admin_name', 191)->nullable();
                $table->string('page_path', 255)->default('/dashboard');
                $table->string('page_title', 255)->nullable();
                $table->text('initial_message')->nullable();
                $table->boolean('initial_greeting_sent')->default(false);
                $table->integer('unread_count_for_admin')->default(0);
                $table->integer('unread_count_for_user')->default(0);
                $table->integer('messages_exchanged_count')->default(0);
                $table->boolean('meet_suggested')->default(false);
                $table->text('last_message_text')->nullable();
                $table->timestamp('last_message_time')->nullable();
                $table->string('active_meet_url', 255)->nullable();
                $table->string('active_booking_id', 64)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_live_chat_messages')) {
            Schema::create('ai_live_chat_messages', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('session_id', 64)->index();
                $table->string('sender_id', 64);
                $table->string('sender_name', 191);
                $table->enum('sender_role', ['user', 'admin', 'system'])->default('user');
                $table->string('type', 64)->default('text');
                $table->text('text');
                $table->string('meet_url', 255)->nullable();
                $table->json('booking_details')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_live_admin_presence')) {
            Schema::create('ai_live_admin_presence', function (Blueprint $table) {
                $table->id();
                $table->string('admin_id', 64)->index();
                $table->string('admin_name', 191);
                $table->string('sub_institute_id', 64)->default('1')->index();
                $table->enum('status', ['available', 'busy', 'offline'])->default('available');
                $table->timestamp('last_seen')->useCurrent();
                $table->timestamps();
                $table->unique(['admin_id', 'sub_institute_id'], 'admin_subinst_unique');
            });
        }

        if (!Schema::hasTable('ai_live_meeting_slots')) {
            Schema::create('ai_live_meeting_slots', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('admin_id', 64);
                $table->string('admin_name', 191);
                $table->string('sub_institute_id', 64)->default('1')->index();
                $table->string('date', 20)->index();
                $table->string('start_time', 10);
                $table->string('end_time', 10);
                $table->integer('duration_minutes')->default(15);
                $table->boolean('is_booked')->default(false);
                $table->string('booking_id', 64)->nullable();
                $table->string('meet_url', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_live_meeting_bookings')) {
            Schema::create('ai_live_meeting_bookings', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('slot_id', 64)->index();
                $table->string('session_id', 64)->index();
                $table->string('student_id', 64);
                $table->string('student_name', 191);
                $table->string('admin_id', 64);
                $table->string('admin_name', 191);
                $table->string('sub_institute_id', 64)->default('1')->index();
                $table->string('date', 20);
                $table->string('start_time', 10);
                $table->string('end_time', 10);
                $table->integer('duration_minutes')->default(15);
                $table->string('status', 32)->default('scheduled')->index();
                $table->string('meet_url', 255);
                $table->boolean('student_joined')->default(false);
                $table->boolean('admin_joined')->default(false);
                $table->timestamp('student_joined_at')->nullable();
                $table->timestamp('admin_joined_at')->nullable();
                $table->integer('grace_period_minutes')->default(2);
                $table->boolean('reminder_sent')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_live_chat_settings')) {
            Schema::create('ai_live_chat_settings', function (Blueprint $table) {
                $table->string('setting_key', 64)->primary();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_live_chat_settings');
        Schema::dropIfExists('ai_live_meeting_bookings');
        Schema::dropIfExists('ai_live_meeting_slots');
        Schema::dropIfExists('ai_live_admin_presence');
        Schema::dropIfExists('ai_live_chat_messages');
        Schema::dropIfExists('ai_live_chat_sessions');
    }
};
