<?php

namespace App\Http\Controllers\AI;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The AI & Intelligence console — one endpoint behind all twelve capabilities.
 *
 * WHY ONE CONTROLLER AND NOT TWELVE
 *
 * Every capability answers the same three questions for the school the user is
 * signed in to: is it configured, how much of it is there, and what has it done
 * lately. Twelve controllers would be twelve copies of the same tenant filter, the
 * same missing-table guard and the same envelope — and the first one written
 * differently is the one that leaks another school's rows. One controller with a
 * provider per capability keeps that in a single place.
 *
 * TENANT SCOPE IS NEVER A PARAMETER
 *
 * `$this->scope($request)->selectedInstituteId` comes from McpContextHydrator, which
 * derives it from the caller's JWT. It is never read from request input, so a caller
 * cannot name another school by editing a query string, and nothing here carries a
 * hard-coded institute, user or module id.
 *
 * HONEST ABOUT WHAT IS NOT THERE
 *
 * A capability whose table has not been migrated onto this estate returns
 * `state: unavailable` and says which table is missing. One whose table exists but
 * holds nothing for this school returns `state: empty`. Those are different facts and
 * the console shows them differently — "not installed" and "nothing recorded yet"
 * lead to different next steps, and collapsing them into one empty screen has sent
 * people looking for data that was never going to be there.
 */
class CapabilityController extends AiController
{
    /**
     * Capability key => the tables it reads.
     *
     * The keys match the slugs in `packages/ai-intelligence-core` and the menu links
     * seeded by `2026_09_10_000001_add_ai_intelligence_menu`, so the sidebar entry,
     * the console route and this payload all address the same capability.
     */
    private const TABLES = [
        'providers' => ['ai_api_keys', 'ai_platforms'],
        // Model resolution comes from config, not a table, so nothing gates it.
        'models' => [],
        'prompts' => ['ai_templates'],
        'policies' => ['ai_signal_definitions', 'ai_decisions'],
        'agents' => ['ai_agents', 'ai_agent_runs'],
        'conversational-ai' => ['ai_conversations', 'ai_conversation_turns'],
        'knowledge-rag' => ['ai_sops', 'ai_evidence'],
        'recommendations' => ['ai_recommendations'],
        'knowledge-graph' => ['ai_ontology_views', 'ai_evidence'],
        'evaluation' => ['ai_outcomes', 'ai_hypotheses'],
        'usage-cost' => ['ai_interaction_logs', 'ai_daily_used_api'],
        'audit' => ['ai_audit_logs'],
    ];

    /** Every capability, with just enough per row for the console index. */
    public function index(Request $request)
    {
        try {
            $instituteId = $this->scope($request)->selectedInstituteId;

            $capabilities = [];

            foreach (array_keys(self::TABLES) as $key) {
                $summary = $this->summarise($key, $instituteId);
                $capabilities[] = ['key' => $key] + $summary;
            }

            return $this->success('AI capabilities resolved.', [
                'sub_institute_id' => $instituteId,
                'capabilities' => $capabilities,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One capability, with its metrics and its most recent rows. */
    public function show(Request $request, string $capability)
    {
        try {
            $capability = strtolower(trim($capability));

            if (! array_key_exists($capability, self::TABLES)) {
                return $this->failure("\"{$capability}\" is not an AI capability.", 404);
            }

            $instituteId = $this->scope($request)->selectedInstituteId;
            $missing = $this->missingTables($capability);

            if ($missing !== []) {
                return $this->success('This capability is not installed on this estate.', [
                    'key' => $capability,
                    'state' => 'unavailable',
                    'missing_tables' => $missing,
                    'metrics' => [],
                    'table' => null,
                ]);
            }

            $limit = $this->limit($request, 25, 100);
            $detail = match ($capability) {
                'providers' => $this->providers($instituteId, $limit),
                'models' => $this->models($instituteId, $limit),
                'prompts' => $this->prompts($instituteId, $limit),
                'policies' => $this->policies($instituteId, $limit),
                'agents' => $this->agents($instituteId, $limit),
                'conversational-ai' => $this->conversational($instituteId, $limit),
                'knowledge-rag' => $this->knowledge($instituteId, $limit),
                'recommendations' => $this->recommendations($instituteId, $limit),
                'knowledge-graph' => $this->knowledgeGraph($instituteId, $limit),
                'evaluation' => $this->evaluation($instituteId, $limit),
                'usage-cost' => $this->usage($instituteId, $limit),
                'audit' => $this->audit($instituteId, $limit),
            };

            return $this->success('Capability resolved.', [
                'key' => $capability,
                'sub_institute_id' => $instituteId,
                'state' => ($detail['table']['rows'] ?? []) === [] ? 'empty' : 'live',
            ] + $detail);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ---------------------------------------------------------------------
    // Per-capability providers. Each returns metrics + one recent-rows table.
    // ---------------------------------------------------------------------

    private function providers(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('keys', 'Credentials', $this->count('ai_api_keys', $institute)),
                $this->metric('active', 'Active', $this->count('ai_api_keys', $institute, ['status' => 1])),
                $this->metric('platforms', 'Platforms listed', $this->count('ai_platforms', $institute)),
            ],
            'table' => $this->rows(
                'ai_api_keys',
                $institute,
                ['api_type' => 'Provider', 'account_email' => 'Account', 'api_limit' => 'Limit', 'status' => 'Status', 'updated_at' => 'Updated'],
                $limit,
                // The key itself is never selected. A console that can display a
                // credential is a console that can leak one.
            ),
        ];
    }

    /**
     * Which model each configured provider resolves to.
     *
     * THERE IS NO MODEL CATALOGUE TABLE, AND THIS DOES NOT PRETEND OTHERWISE
     *
     * An earlier version read `ai_platforms`, which was wrong: that table is the
     * directory of third-party AI tools shown on `/ai-platforms` — Canva, Grammarly —
     * not a list of models the platform calls. Reporting 280 "models" from it made
     * Model Management look configured when nothing behind it was.
     *
     * What actually decides the model is `config/ai.php`: a driver per provider, each
     * naming its own model and limits, with `ai.provider.driver` selecting the active
     * one. That is what is shown, and the credential column says whether this school
     * has a key for that provider in `ai_api_keys` — so an admin can see the pairing
     * that a generation will actually use.
     */
    private function models(int|string $institute, int $limit): array
    {
        $drivers = ['gemini', 'openrouter', 'deepseek'];
        $active = (string) config('ai.provider.driver');

        $rows = [];

        foreach ($drivers as $driver) {
            $settings = (array) config("ai.provider.{$driver}", []);

            if ($settings === []) {
                continue;
            }

            $apiType = (string) ($settings['api_type'] ?? $driver);

            $rows[] = [
                'provider' => $driver,
                'model' => (string) ($settings['model'] ?? '—'),
                'max_output_tokens' => (string) ($settings['max_output_tokens'] ?? '—'),
                'credential' => $this->count('ai_api_keys', $institute, ['api_type' => $apiType]) > 0
                    ? 'Configured'
                    : 'None for this institute',
                'state' => $driver === $active ? 'Active' : 'Standby',
            ];
        }

        return [
            'metrics' => [
                $this->metric('drivers', 'Providers configured', count($rows)),
                $this->metric('keys', 'Credentials held', $this->count('ai_api_keys', $institute)),
                $this->metric('templates_pinned', 'Templates pinning a provider', $this->distinct('ai_templates', $institute, 'provider')),
            ],
            'table' => [
                'columns' => [
                    ['key' => 'provider', 'label' => 'Provider'],
                    ['key' => 'model', 'label' => 'Model'],
                    ['key' => 'max_output_tokens', 'label' => 'Max output tokens'],
                    ['key' => 'credential', 'label' => 'Credential'],
                    ['key' => 'state', 'label' => 'State'],
                ],
                'rows' => array_slice($rows, 0, $limit),
            ],
        ];
    }

    private function prompts(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('templates', 'Templates', $this->count('ai_templates', $institute)),
                // `ai_templates.status` is a word, not a flag — 'published' or
                // 'archived'. Counting `status = 1` reported 0 published against 9
                // rows, which read as "prompt management is empty" on a screen whose
                // whole job is to say whether it is configured.
                $this->metric('active', 'Published', $this->count('ai_templates', $institute, ['status' => 'published'])),
                $this->metric('domains', 'Domains', $this->distinct('ai_templates', $institute, 'domain')),
            ],
            'table' => $this->rows(
                'ai_templates',
                $institute,
                ['template_key' => 'Key', 'name' => 'Name', 'domain' => 'Domain', 'version' => 'Version', 'provider' => 'Provider', 'status' => 'Status'],
                $limit
            ),
        ];
    }

    private function policies(int|string $institute, int $limit): array
    {
        $rows = DB::table('ai_policies')
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->name,
                    'policy_type' => $row->policy_type,
                    'status' => $row->status ? 'Active' : 'Retired',
                    'require_disclosure' => (int) $row->require_disclosure === 1 ? 'Required' : 'Not required',
                    'updated_at' => $row->updated_at,
                ];
            })
            ->all();

        return [
            'metrics' => [
                $this->metric('policies', 'Policies', $this->count('ai_policies', $institute)),
                $this->metric('active', 'Active', $this->count('ai_policies', $institute, ['status' => 1])),
                $this->metric('assignments', 'Assignments', $this->count('ai_policy_assignments', $institute)),
            ],
            'table' => [
                'columns' => [
                    ['key' => 'name', 'label' => 'Policy'],
                    ['key' => 'policy_type', 'label' => 'Type'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'require_disclosure', 'label' => 'Disclosure'],
                    ['key' => 'updated_at', 'label' => 'Updated'],
                ],
                'rows' => $rows,
            ],
        ];
    }

    private function agents(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('agents', 'Registered agents', $this->count('ai_agents', $institute)),
                $this->metric('runs', 'Runs', $this->count('ai_agent_runs', $institute)),
            ],
            'table' => $this->rows(
                'ai_agent_runs',
                $institute,
                ['agent_key' => 'Agent', 'status' => 'Status', 'trigger_type' => 'Trigger', 'confidence' => 'Confidence', 'started_at' => 'Started'],
                $limit
            ),
        ];
    }

    private function conversational(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('conversations', 'Conversations', $this->count('ai_conversations', $institute)),
                $this->metric('turns', 'Turns', $this->count('ai_conversation_turns', $institute)),
            ],
            'table' => $this->rows(
                'ai_conversations',
                $institute,
                ['title' => 'Conversation', 'module_key' => 'Module', 'turn_count' => 'Turns', 'status' => 'Status', 'last_turn_at' => 'Last activity'],
                $limit
            ),
        ];
    }

    private function knowledge(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('sops', 'Documents', $this->count('ai_sops', $institute)),
                $this->metric('evidence', 'Evidence records', $this->count('ai_evidence', $institute)),
            ],
            'table' => $this->rows(
                'ai_sops',
                $institute,
                ['sop_name' => 'Document', 'department_id' => 'Department', 'status' => 'Status', 'updated_at' => 'Updated'],
                $limit
            ),
        ];
    }

    private function recommendations(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('total', 'Recommendations', $this->count('ai_recommendations', $institute)),
                $this->metric('consequential', 'Need approval', $this->count('ai_recommendations', $institute, ['is_consequential' => 1])),
                $this->metric('domains', 'Domains', $this->distinct('ai_recommendations', $institute, 'domain')),
            ],
            'table' => $this->rows(
                'ai_recommendations',
                $institute,
                ['title' => 'Recommendation', 'domain' => 'Domain', 'action_type' => 'Action', 'confidence' => 'Confidence', 'risk_level' => 'Risk'],
                $limit
            ),
        ];
    }

    private function knowledgeGraph(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('views', 'Ontology views', $this->count('ai_ontology_views', $institute)),
                $this->metric('evidence', 'Evidence records', $this->count('ai_evidence', $institute)),
                $this->metric('entities', 'Entity types', $this->distinct('ai_evidence', $institute, 'subject_entity_key')),
            ],
            'table' => $this->rows(
                'ai_ontology_views',
                $institute,
                ['view_key' => 'View', 'label' => 'Label', 'module_key' => 'Module', 'root_entity_key' => 'Root entity', 'status' => 'Status'],
                $limit,
                'sort_order'
            ),
        ];
    }

    private function evaluation(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('outcomes', 'Outcomes measured', $this->count('ai_outcomes', $institute)),
                $this->metric('hypotheses', 'Hypotheses', $this->count('ai_hypotheses', $institute)),
                $this->metric('metrics', 'Distinct metrics', $this->distinct('ai_outcomes', $institute, 'metric_key')),
            ],
            // An outcome row is the only honest evaluation this product has: what the
            // measure was before an action, and what it was afterwards.
            'table' => $this->rows(
                'ai_outcomes',
                $institute,
                ['metric_label' => 'Metric', 'baseline_value' => 'Baseline', 'target_value' => 'Target', 'observed_value' => 'Observed', 'delta' => 'Change', 'observed_at' => 'Measured'],
                $limit
            ),
        ];
    }

    private function usage(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('interactions', 'Interactions logged', $this->count('ai_interaction_logs', $institute)),
                $this->metric('daily', 'Daily usage rows', $this->count('ai_daily_used_api', $institute)),
                $this->metric('menus', 'Surfaces used', $this->distinct('ai_interaction_logs', $institute, 'menu_type')),
            ],
            'table' => $this->rows(
                'ai_interaction_logs',
                $institute,
                ['menu_type' => 'Surface', 'student_level' => 'Level', 'syear' => 'Year', 'created_by' => 'By', 'created_at' => 'When'],
                $limit
            ),
        ];
    }

    private function audit(int|string $institute, int $limit): array
    {
        return [
            'metrics' => [
                $this->metric('events', 'Events recorded', $this->count('ai_audit_logs', $institute)),
                $this->metric('types', 'Event types', $this->distinct('ai_audit_logs', $institute, 'event_type')),
                $this->metric('actors', 'Actors', $this->distinct('ai_audit_logs', $institute, 'actor_id')),
            ],
            'table' => $this->rows(
                'ai_audit_logs',
                $institute,
                ['event_type' => 'Event', 'actor_label' => 'Actor', 'actor_type' => 'Actor type', 'outcome' => 'Outcome', 'message' => 'Message', 'created_at' => 'When'],
                $limit
            ),
        ];
    }

    // ---------------------------------------------------------------------
    // Shared helpers. Every query below is filtered by the caller's institute.
    // ---------------------------------------------------------------------

    /** Counts and one sample row per capability, cheap enough for the index. */
    private function summarise(string $capability, int|string $institute): array
    {
        $missing = $this->missingTables($capability);

        if ($missing !== []) {
            return ['state' => 'unavailable', 'count' => 0, 'missing_tables' => $missing];
        }

        $primary = self::TABLES[$capability][0];
        $count = $this->count($primary, $institute);

        return [
            'state' => $count > 0 ? 'live' : 'empty',
            'count' => $count,
            'primary_table' => $primary,
        ];
    }

    /** @return array<int, string> tables this estate has not migrated. */
    private function missingTables(string $capability): array
    {
        return array_values(array_filter(
            self::TABLES[$capability],
            fn (string $table) => ! Schema::hasTable($table)
        ));
    }

    private function count(string $table, int|string $institute, array $where = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = $this->scoped($table, $institute);

        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return (int) $query->count();
    }

    private function distinct(string $table, int|string $institute, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) $this->scoped($table, $institute)->distinct()->count($column);
    }

    /**
     * A table's most recent rows, restricted to columns that actually exist.
     *
     * Column sets drift between estates — this database was built from a snapshot
     * rather than by running migrations, so a column named here may be absent on
     * another install. Selecting only what `Schema::hasColumn` confirms means a
     * missing column costs one blank column rather than a 500.
     */
    private function rows(string $table, int|string $institute, array $columns, int $limit, ?string $orderBy = null): array
    {
        $present = array_filter(
            $columns,
            fn (string $label, string $column) => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH
        );

        if ($present === []) {
            return ['columns' => [], 'rows' => []];
        }

        $query = $this->scoped($table, $institute)->select(array_keys($present));

        if ($orderBy !== null && Schema::hasColumn($table, $orderBy)) {
            $query->orderBy($orderBy);
        } else {
            $query->orderByDesc('id');
        }

        $rows = $query->limit($limit)->get()->map(
            fn ($row) => array_map(
                fn ($value) => $value === null ? null : (string) $value,
                (array) $row
            )
        )->all();

        return [
            'columns' => array_map(
                fn (string $column, string $label) => ['key' => $column, 'label' => $label],
                array_keys($present),
                array_values($present)
            ),
            'rows' => $rows,
        ];
    }

    /**
     * The one place a tenant filter is written.
     *
     * Baseline rows — those with a null `sub_institute_id` — are shared
     * configuration seeded for every school, so they are included alongside the
     * school's own. A table without the column is platform-wide by construction and
     * is returned unfiltered rather than silently emptied.
     */
    private function scoped(string $table, int|string $institute)
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'sub_institute_id')) {
            $query->where(function ($inner) use ($institute) {
                $inner->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            });
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function metric(string $key, string $label, int $value): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value];
    }
}
