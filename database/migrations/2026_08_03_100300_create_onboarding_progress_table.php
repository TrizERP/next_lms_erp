<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journey state — holds only what CANNOT be derived from module tables:
 * manual sign-off, an explicit skip/block, assignee, and notes.
 *
 * Deliberately NOT a completion cache. For `proof_type = table_rows` steps the
 * API always re-derives completion at read time and this row only carries the
 * annotation; that is what stops the store degrading into the `erptour`
 * self-reported-flag anti-pattern.
 *
 * Scoped per (tenant, academic year, step) — school-level, not per-user, so
 * progress does not reset when a different staff member signs in. `syear = 0`
 * is used for steps whose proof is not year-scoped.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sub_institute_id');
            $table->integer('syear')->default(0)->comment('0 = not year-scoped');
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('step_id');

            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped', 'blocked'])
                ->default('pending');
            $table->text('notes')->nullable();

            $table->bigInteger('assigned_to_id')->nullable();
            $table->string('assigned_to_name', 160)->nullable();
            $table->bigInteger('updated_by_id')->nullable();
            $table->string('updated_by_name', 160)->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['sub_institute_id', 'syear', 'step_id'],
                'onboarding_progress_tenant_year_step_unique'
            );
            $table->index(['sub_institute_id', 'module_id'], 'onboarding_progress_tenant_module_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('onboarding_progress');
    }
};
