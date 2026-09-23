<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a PRODUCT module's own model choice lives.
 *
 * WHY A NEW TABLE AND NOT `ai_api_keys.ai_module`
 *
 * That column already exists and already works, but it holds an AI CAPABILITY key —
 * `conversational_ai`, `generative_ai`, `agent_reasoning` — which is what
 * `AiModuleRegistry` enumerates and what the central console configures. A product module
 * like `fees` or `hostel` is a different kind of thing and never appears in that list.
 *
 * Overloading the column would mean one field holding two vocabularies, and the first
 * question anybody asked of a row would be "which kind of module is this?". A row here is
 * unambiguously a product module choosing a model for one capability.
 *
 * WHAT A ROW MEANS
 *
 * "When the Fees module makes a conversational call, use this provider and model."
 * Precedence is added AHEAD of the existing capability resolution, so a module with no row
 * behaves exactly as it does today — this is additive, and an estate that never writes a
 * row cannot tell the table exists.
 *
 * THE CREDENTIAL IS OPTIONAL, AND USUALLY ABSENT
 *
 * `api_key_id` is nullable on purpose. The common case is a school wanting one module on a
 * different MODEL — cheaper, faster, longer context — while still using the estate's own
 * credential and quota. Naming a key as well is for the rarer case of a module that needs
 * its own quota or its own account. When it is null the credential resolves exactly as it
 * would have, so choosing a model never silently requires handing out another key.
 *
 * SCOPE
 *
 * `sub_institute_id` null is a platform default that every school inherits; a row with an
 * institute is that school's own and wins over it. The same two-step precedence
 * `ai_api_keys` already uses, so there is one rule to learn rather than two.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_27_100000_create_ai_module_model_bindings_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_module_model_bindings')) {
            return;
        }

        Schema::create('ai_module_model_bindings', function (Blueprint $table) {
            $table->id();

            // The `ai_modules` key — `fees`, `hostel`, `document-templates`. Not validated
            // by a foreign key: `ai_modules` rows are seeded per estate and a binding for a
            // module a school has not enabled yet should be storable rather than rejected.
            $table->string('product_module', 100);

            // The `AiModuleRegistry` key — `conversational_ai`, `generative_ai`,
            // `agent_reasoning`. Which KIND of call this binding applies to.
            $table->string('capability', 100);

            $table->string('provider', 60)->nullable();
            $table->string('model', 190)->nullable();

            // Optional. Null means "use whatever credential this provider would have used
            // anyway", which is the common and usually correct case.
            $table->unsignedBigInteger('api_key_id')->nullable();

            $table->unsignedInteger('max_output_tokens')->nullable();

            // A row can be switched off without losing what it said, so a school can try a
            // model, revert, and put it back without retyping.
            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // One binding per module per capability per scope. The unique index is what
            // makes a save an upsert rather than an ever-growing pile of overrides.
            $table->unique(
                ['product_module', 'capability', 'sub_institute_id'],
                'ai_module_model_binding_unique'
            );

            // The read path: every resolution asks for one institute's rows plus the
            // platform's.
            $table->index(['sub_institute_id', 'status'], 'ai_module_model_binding_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_module_model_bindings');
    }
};
