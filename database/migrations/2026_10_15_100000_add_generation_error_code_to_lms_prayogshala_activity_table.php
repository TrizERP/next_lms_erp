<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A safe, structured reason for a failed generation, next to the human message.
 *
 * `generation_error` is text for a person. `generation_error_code` is what a program (the retry
 * policy, a batch deciding whether to stop, the UI choosing a hint) can rely on:
 * rate_limited | provider_unavailable | timeout | quota_exhausted | invalid_key | no_provider |
 * model_not_found | bad_request | invalid_output | unknown. See AiFailure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_prayogshala_activity') || Schema::hasColumn('lms_prayogshala_activity', 'generation_error_code')) {
            return;
        }

        Schema::table('lms_prayogshala_activity', function (Blueprint $table) {
            $table->string('generation_error_code', 32)->nullable()->after('generation_error');
            // Seconds the provider asked us to wait when it said so; lets the UI say "after HH:MM".
            $table->unsignedInteger('generation_retry_after')->nullable()->after('generation_error_code');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lms_prayogshala_activity') && Schema::hasColumn('lms_prayogshala_activity', 'generation_error_code')) {
            Schema::table('lms_prayogshala_activity', function (Blueprint $table) {
                $table->dropColumn(['generation_error_code', 'generation_retry_after']);
            });
        }
    }
};
