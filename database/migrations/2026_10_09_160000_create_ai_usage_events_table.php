<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D6 (shared cross-product AI gateway), minimal first step: K-12 had zero
 * AI usage tracking before this table existed (confirmed — the closest
 * thing, `ai_models.input_cost_per_1k`/`output_cost_per_1k`, is seeded
 * NULL everywhere). Columns deliberately named to match G2G's
 * `ai_usage_events` (same schema, hp_erp) and EB's `hpbrain_ai_usage_events`
 * (ported from the same design) so a cross-product view can UNION all
 * three without a translation layer. `input_tokens`/`output_tokens`/
 * `estimated_cost_usd` stay nullable and unpopulated for now — see
 * UsageTrackingModelClient's docblock for why K-12's interface layer
 * cannot surface them without touching many more call sites than this
 * round is scoped for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->string('product', 16)->default('k12');
            $table->string('ai_module', 64)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            $table->string('outcome', 16)->nullable();
            $table->string('finish_reason', 32)->nullable();
            $table->text('error')->nullable();
            $table->string('related_type', 64)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'created_at']);
            $table->index(['product', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
