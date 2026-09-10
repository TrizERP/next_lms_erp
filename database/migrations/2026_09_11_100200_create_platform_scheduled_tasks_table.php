<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-institute overrides for the scheduled tasks each component owns — the rows
 * behind the Scheduler screen.
 *
 * THE FIVE CRON FIELDS ARE FIVE COLUMNS, DELIBERATELY
 * Minute, hour, day of month, month and day of week are stored separately rather
 * than as one `0 9 * * 1-5` string because that is exactly how the screen edits
 * them — five inputs, the shape administrators here already read on the reference
 * scheduled-task screen. Storing the joined string would mean splitting it on
 * every render and rejoining it on every save, and the first malformed value
 * would be discovered at run time by the dispatcher instead of at save time by
 * the person who typed it. Each field is validated on write (star, `5`, `1,15`,
 * `1-5`, `star-slash-10`), so a row in this table always parses.
 *
 * ONLY OVERRIDES ARE STORED. A task a school has never touched has no row and
 * runs on the schedule in config/platform_services.php. Reset-to-default on the
 * screen DELETES the row rather than writing today's default into it, which is
 * what keeps "default" meaning "whatever the product currently ships" instead of
 * "whatever the product shipped the day someone pressed reset".
 *
 * WHY `next_run_at` IS ABSENT
 * It is computed from the schedule on read, never stored. A stored next-run goes
 * stale the moment anyone edits the schedule, and a stale timestamp on an
 * administration screen is worse than none — it looks like an answer.
 *
 * `last_run_at` / `last_run_status` ARE WRITTEN BY THE DISPATCHER, not by this
 * screen. They are here so an administrator can see whether a task is actually
 * running, which is the question they came to the screen with; the screen itself
 * never sets them.
 *
 * `fail_delay` is minutes to wait after a failure before retrying, doubling each
 * time — the same back-off the reference screen shows. 0 means no retry.
 *
 * Forward-only; see the DROP TABLE guard note in the channels migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_scheduled_tasks')) {
            return;
        }

        Schema::create('platform_scheduled_tasks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id');

            $table->string('task_key', 191)->comment('module.component.task, from config platform_services.tasks');

            // Derived from task_key at write time, for module- and
            // component-wise filtering without scanning strings.
            $table->string('module', 64);
            $table->string('component', 128);

            // The five cron fields. Short strings because a field is at most
            // something like "1,3,5,7,9,11" — validated on write, never free text.
            $table->string('minute', 64)->default('0');
            $table->string('hour', 64)->default('0');
            $table->string('day', 64)->default('*');
            $table->string('month', 64)->default('*');
            $table->string('day_of_week', 64)->default('*');

            // A disabled task keeps its schedule and is simply never dispatched,
            // so switching it back on does not lose what it was set to.
            $table->boolean('disabled')->default(false);

            $table->unsignedInteger('fail_delay')->default(0)->comment('minutes to wait after a failure; doubles each retry; 0 = no retry');

            // Written by the dispatcher, not by the configuration screen.
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status', 16)->nullable()->comment('ok | failed');

            $table->string('updated_by', 191)->nullable();

            $table->timestamps();

            $table->unique(['sub_institute_id', 'task_key'], 'pst_tenant_task_uq');
            $table->index(['sub_institute_id', 'module'], 'pst_tenant_module_idx');
            // The dispatcher's own question: which of this institute's tasks are live?
            $table->index(['sub_institute_id', 'disabled'], 'pst_tenant_disabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_scheduled_tasks');
    }
};
