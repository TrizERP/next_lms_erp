<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per content-authoring attempt through the consolidated endpoint.
 *
 * Delivers the accountability half of tracker row 3 / Decision #36, and the thing that
 * lets row 3 satisfy Decision #58's Definition of Done ("auditable, user-visible value")
 * rather than merely running.
 *
 * WHY IT EXISTS
 * There is no Generative AI Gateway in this codebase. Five providers are called from 13+
 * sites, several reading keys via env() at request time, and NOTHING records what was
 * generated, by whom, at what token cost, or whether it succeeded. A generation that
 * silently fails today is indistinguishable from one never attempted.
 *
 * IDEMPOTENCY
 * `idempotency_key` is unique. A double-clicked Generate button, or a client retry after
 * a timeout, resolves to the same row instead of a second billed provider call. This
 * matters more than usual here: QUEUE_CONNECTION is `sync`, so generation runs inline in
 * the request and a user watching a slow spinner WILL click again.
 *
 * This table is additive and touches none of the three live content estates.
 * Rollback: down() will throw under the DROP TABLE guard in AppServiceProvider - see the
 * other migrations in this series. Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_content_authoring_audit')) {
            return;
        }

        Schema::create('lms_content_authoring_audit', function (Blueprint $table) {
            $table->id();

            // Dedup key: caller-supplied, or derived from (actor, type, mode, payload hash).
            $table->string('idempotency_key', 64)->unique();

            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable();

            $table->string('module', 32)->default('lms_content');
            $table->string('authoring_type', 32)->comment('key from config lms_content.authoring_types');
            $table->string('mode', 16)->comment('generate | upload');

            // Null for uploads - there is no provider involved.
            $table->string('provider', 32)->nullable()->comment('gamma | gemini | question | null');
            $table->string('model', 64)->nullable();

            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->unsignedBigInteger('concept_id')->nullable();

            $table->string('status', 16)->default('pending')->comment('pending | succeeded | failed');

            // What it produced. `entity_ids` is a list because one question-generation run
            // creates many rows.
            $table->string('entity_type', 24)->nullable();
            $table->json('entity_ids')->nullable();

            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['sub_institute_id', 'authoring_type', 'status'], 'lcaa_tenant_type_status_idx');
            $table->index(['actor_user_id', 'created_at'], 'lcaa_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_content_authoring_audit');
    }
};
