<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core Ontology — the shared vocabulary the whole intelligence layer resolves against.
 *
 * An entity row is a *mapping*, not a copy: `source_table` / `primary_key_column` /
 * `label_column` point at the table that already holds the real records, so the ontology
 * never duplicates business data. Entities that have no table of their own (Signal, Case,
 * Hypothesis, Recommendation, Decision, Outcome) are marked `is_virtual`.
 *
 * A relationship row carries the join it takes to walk the edge in SQL
 * (`join_table` / `from_column` / `to_column`) and, where the Neo4j programme has landed
 * the same edge, the graph relationship type. That is what lets the Knowledge Graph layer
 * answer the same question from MySQL today and from Neo4j once phases 7/8 complete.
 *
 * Rows with a NULL `sub_institute_id` are platform-global definitions; a tenant may
 * override or extend them with its own rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ontology_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_key', 100)->index();
            $table->string('label', 150);
            $table->string('domain', 40)->default('shared')->index();   // k12 | g2g | shared
            $table->string('category', 40)->default('core')->index();   // core|people|academic|operations|intelligence
            $table->text('description')->nullable();

            // Where the real records live. NULL for virtual/AI-native entities.
            $table->string('source_table', 100)->nullable();
            $table->string('primary_key_column', 64)->nullable()->default('id');
            $table->string('label_column', 64)->nullable();
            $table->string('tenant_column', 64)->nullable();            // e.g. sub_institute_id
            // Org-level tables (tblclient, school_setup) scope by client rather than
            // by school. Without this they would have to be marked un-scoped, which
            // would expose every organisation's name to any authenticated user.
            $table->string('client_column', 64)->nullable();            // e.g. client_id
            $table->string('academic_year_column', 64)->nullable();     // e.g. syear

            $table->json('attributes')->nullable();                     // [{key,column,type,label}]
            $table->boolean('is_virtual')->default(false);
            $table->boolean('is_tenant_scoped')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['entity_key', 'sub_institute_id'], 'ontology_entities_key_tenant_unique');
        });

        Schema::create('ontology_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('relationship_key', 150)->index();
            $table->string('from_entity_key', 100)->index();
            $table->string('relation', 80)->index();                    // enrolled_in, studies, produces...
            $table->string('to_entity_key', 100)->index();
            $table->string('cardinality', 24)->default('one_to_many');
            $table->text('description')->nullable();

            // SQL traversal plan. `join_table` is set when the edge needs a pivot.
            $table->string('from_column', 64)->nullable();
            $table->string('join_table', 100)->nullable();
            $table->string('join_from_column', 64)->nullable();
            $table->string('join_to_column', 64)->nullable();
            $table->string('to_column', 64)->nullable();

            // Neo4j traversal plan, when the edge exists in the graph.
            $table->string('graph_relationship_type', 80)->nullable();
            $table->boolean('in_graph')->default(false);

            $table->boolean('traversable')->default(true);
            $table->unsignedSmallInteger('traversal_cost')->default(1);
            $table->json('attributes')->nullable();
            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['relationship_key', 'sub_institute_id'], 'ontology_rels_key_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ontology_relationships');
        Schema::dropIfExists('ontology_entities');
    }
};
