<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per remap run. Records the thresholds actually used, so a
 * decision made months ago can still be explained without guessing
 * what config looked like at the time.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('lms_remap_run')) {
            return;
        }

        Schema::create('lms_remap_run', function (Blueprint $table) {
            $table->char('run_id', 36)->primary();
            $table->string('command', 64);
            $table->text('args_json')->nullable();
            $table->string('git_sha', 40)->nullable();
            $table->string('status', 16)->default('running'); // running|completed|failed|aborted
            $table->longText('thresholds_json')->nullable();
            $table->longText('counts_json')->nullable();
            $table->unsignedInteger('llm_calls')->default(0);
            $table->unsignedBigInteger('llm_tokens')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lms_remap_run');
    }
};
