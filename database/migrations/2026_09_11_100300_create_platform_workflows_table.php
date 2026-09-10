<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval chains, one row per chain, owned by one component — the rows behind
 * the Workflow screen.
 *
 * A POINT IS NOT A CHAIN
 * config/platform_services.php declares WORKFLOW POINTS: the places in each
 * component where an action can pause for a sign-off (`fees.concession.flow`).
 * This table holds the CHAINS a school defines against those points. One point
 * may carry several: "Concession above ₹10,000" with three steps and "Concession
 * up to ₹10,000" with one are two rows on the same `flow_key`, told apart by
 * `condition`. That is why this is a table and not more configuration — the point
 * is the product's, the chain is the school's.
 *
 * `condition` IS STORED AS THE OPERATOR WROTE IT AND IS NOT EVALUATED HERE.
 * It is free text (`amount > 10000`) that the workflow engine interprets when a
 * chain runs. This layer neither parses nor trusts it: a configuration screen
 * that quietly evaluated user-supplied expressions would be a code-execution
 * surface, and a chain whose condition is nonsense should fail visibly in the
 * engine, not be silently rewritten at save time.
 *
 * WHY `steps` IS JSON AND NOT A CHILD TABLE
 * A chain is always read and written whole — the screen edits the ladder as one
 * thing, and reordering steps is a normal edit — so a child table would buy a
 * join, a delete-and-reinsert on every save, and an ordering column to keep in
 * step with the array. The shape is fixed and validated in the controller:
 * [{"id","order","name","approver_type","approver","sla_hours","on_breach",
 * "allow_delegate","require_comment"}].
 *
 * THE OTHER HALF, WHICH IS NOT HERE
 * Approvals IN FLIGHT — which record is at which step, who approved when, what
 * they said — are runtime, not configuration, and belong in their own table when
 * the engine is built. Putting them here would mean a row that is both a policy
 * and an instance of that policy, and editing the policy would rewrite history.
 * This table is safe to edit precisely because nothing in it is a record of
 * something that happened.
 *
 * Forward-only; see the DROP TABLE guard note in the channels migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_workflows')) {
            return;
        }

        Schema::create('platform_workflows', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id');

            $table->string('flow_key', 191)->comment('module.component.flow, from config platform_services.workflows');

            // Derived from flow_key at write time, for module- and
            // component-wise filtering without scanning strings.
            $table->string('module', 64);
            $table->string('component', 128);

            $table->string('name', 191);
            $table->text('description')->nullable();

            // draft = being built, never runs. active = runs. disabled = kept
            // but not running, so a school can switch a chain off for a term
            // without losing how it was set up.
            $table->string('status', 16)->default('draft');

            // Free text, evaluated by the engine, empty means "always applies".
            $table->string('condition', 255)->default('');

            $table->json('steps');

            $table->string('on_reject', 32)->default('return_to_requester')->comment('return_to_requester | close');

            // Tell the requester at each step change, through the Communication
            // service rather than through a second notification mechanism.
            $table->boolean('notify_requester')->default(true);

            $table->string('created_by', 191)->nullable();
            $table->string('updated_by', 191)->nullable();

            $table->timestamps();

            // Not unique on flow_key: several chains per point is the design.
            $table->index(['sub_institute_id', 'flow_key'], 'pw_tenant_flow_idx');
            $table->index(['sub_institute_id', 'module'], 'pw_tenant_module_idx');
            $table->index(['sub_institute_id', 'status'], 'pw_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_workflows');
    }
};
