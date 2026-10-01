<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two fields the AI Stack Activity screens want to show and could not: how long a
 * logged event took, and whether it drew on the knowledge graph.
 *
 * Both nullable, both promoted rather than guessed. `duration_ms` was already being
 * computed by several callers (AgentRunner, McpToolCaller, WorkflowEngine) and buried
 * inside the freeform `payload` JSON column — AiAuditLogger::record() now promotes it
 * to a first-class column when a caller supplies it, so existing writers light this up
 * for free without being touched. `knowledge_graph_used` is wired at exactly one call
 * site for now (ExplanationBuilder, at agent case-build time) — see its docblock. A row
 * with null in either column means "not measured for this event", never "false" or
 * "zero"; nothing here fabricates a figure nobody supplied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table) {
            $table->unsignedInteger('duration_ms')->nullable()->after('payload');
            $table->boolean('knowledge_graph_used')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['duration_ms', 'knowledge_graph_used']);
        });
    }
};
