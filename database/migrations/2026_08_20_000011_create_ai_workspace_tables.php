<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Workspace configuration — what the assistant offers, per module.
 *
 * The brief is explicit that module capability config must not be hardcoded in the
 * frontend. These three tables are that configuration, so a capability can be turned
 * on for a module, a suggestion reworded, or a relationship view added, without a
 * deploy and without touching the panel.
 *
 * Route patterns live here too. This estate has no `/students/:id` route — the real
 * student-entity routes are `/fees/collect/:studentId` and
 * `/lms/student-analysis/:studentId` — so mapping route to module has to be data, not
 * an assumption baked into a resolver.
 *
 * Nothing here duplicates what already exists: agents live in `ai_agents`, workflows
 * in `workflow_definitions`, prompts in `ai_templates`, relationships in
 * `ontology_relationships`. A suggestion row only *points* at one of those by key.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | Modules: the route → module → entity mapping the workspace resolves against.
        */
        Schema::create('ai_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 80)->index();          // student | fees | dashboard | course ...
            $table->string('label', 150);
            $table->string('domain', 40)->default('k12')->index();
            $table->text('description')->nullable();

            // Ordered patterns, most specific first, e.g. ["/fees/collect/:studentId"].
            // A ":param" segment captures; the rest match literally.
            $table->json('route_patterns');

            // Which ontology entity a matched route is about, and which captured
            // param carries its id. Null entity_key means a module-level page with
            // no single subject (a dashboard, a list screen).
            $table->string('entity_key', 100)->nullable();
            $table->string('entity_param', 64)->nullable();

            // Which of the five workspace tabs this module offers.
            // {"conversational":true,"generative":true,"agent":true,"workflow":false,"ontology":true}
            $table->json('capabilities');

            $table->json('allowed_roles')->nullable();          // null = every role
            $table->string('icon', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('match_priority')->default(100);
            $table->boolean('status')->default(true)->index();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['module_key', 'sub_institute_id'], 'ai_modules_key_tenant_unique');
        });

        /*
        | Suggestions: the actions a user actually sees in each tab.
        |
        | This is what keeps the UX free of AI jargon — a teacher sees "Why is this
        | student at risk?", not "invoke academic-risk agent". `action_ref` is the
        | technical binding behind the plain-language label.
        */
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 80)->index();
            $table->string('capability', 24)->index();
            // conversational | generative | agent | workflow | ontology

            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();

            $table->string('action_type', 40);
            // prompt | generate | run_agent | start_workflow | ontology_view

            // The key the action binds to, resolved in its own registry:
            // ai_templates.template_key | ai_agents.agent_key |
            // workflow_definitions.workflow_key | ai_ontology_views.view_key
            $table->string('action_ref', 150)->nullable();

            // For conversational suggestions: the message sent on click. Supports
            // {{entity_label}} so a prompt reads naturally for the current record.
            $table->text('prompt')->nullable();
            $table->json('payload')->nullable();

            // Hidden unless the current route resolved an entity, so student-specific
            // actions cannot appear on a list page.
            $table->boolean('requires_entity')->default(false);
            $table->json('allowed_roles')->nullable();
            $table->json('required_permissions')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true)->index();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->index(['module_key', 'capability', 'sort_order'], 'ai_suggestions_module_cap_idx');
        });

        /*
        | Ontology views: named relationship chains, expressed in the ontology's own
        | relation names so they traverse real data through GraphQueryService.
        |
        | This is what turns "Ontology/KG" from a jargon tab into
        | "View student relationships": a labelled walk from a record the user is
        | already looking at, through the edges that actually exist.
        */
        Schema::create('ai_ontology_views', function (Blueprint $table) {
            $table->id();
            $table->string('view_key', 100)->index();
            $table->string('label', 150);
            $table->string('module_key', 80)->nullable()->index();
            $table->text('description')->nullable();

            $table->string('root_entity_key', 100);

            // Ordered hops: [{"relation":"attempts","entity":"assessment","label":"Assessments"}]
            $table->json('path');

            $table->unsignedSmallInteger('max_per_hop')->default(10);
            $table->json('allowed_roles')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true)->index();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['view_key', 'sub_institute_id'], 'ai_ontology_views_key_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_ontology_views');
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('ai_modules');
    }
};
