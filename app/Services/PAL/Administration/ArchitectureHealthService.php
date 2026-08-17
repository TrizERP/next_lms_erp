<?php

namespace App\Services\PAL\Administration;

use App\Services\PAL\Framework\FrameworkCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * New PAL → Administration — live subsystem health.
 *
 * Answers one question per subsystem: is this actually running, or is it
 * configured and inert? Every figure is measured — a class that resolves, a
 * table that exists, a row count, a connection that opens — never asserted.
 *
 * The design rule here is that a subsystem is reported as `inactive` when its
 * evidence is missing, not `operational` with a zero. An administrator tuning
 * BKT parameters needs to know whether any learner data reaches the model at
 * all, and a dashboard that renders 0% while implying "working" is worse than
 * one that says "no evidence yet".
 *
 * Row counts are cheap here (the PAL tables are indexed on learner_id and the
 * estate is small), but the whole report is memoised per request because the
 * overview asks for all nine at once.
 */
class ArchitectureHealthService
{
    /** @var array<string, array>|null */
    private ?array $memo = null;

    public function __construct(
        private readonly FrameworkCatalogService $frameworks
    ) {}

    /** Health for every subsystem, keyed by subsystem slug. */
    public function all(?int $tenant): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        return $this->memo = [
            'intelligence-layers' => $this->intelligenceLayers(),
            'adaptive-loop' => $this->adaptiveLoop(),
            'mastery-model' => $this->masteryModel(),
            'hpc-stages' => $this->hpcStages($tenant),
            'progression-rubric' => $this->progressionRubric(),
            'knowledge-graph' => $this->knowledgeGraph(),
            'ai-agents' => $this->aiAgents(),
            'student-model' => $this->studentModel(),
            'career-pathway' => $this->careerPathway(),
        ];
    }

    public function for(string $subsystem, ?int $tenant): array
    {
        return $this->all($tenant)[$subsystem] ?? $this->report('unknown', 'No health probe is defined for this subsystem.', []);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Probes
    // ══════════════════════════════════════════════════════════════════════

    /**
     * A layer is "wired" when its owning service class resolves AND at least
     * one of the tables it reads holds a row. Class-only means the code shipped
     * but nothing feeds it.
     */
    private function intelligenceLayers(): array
    {
        $layers = (array) config('pal_architecture.subsystems.intelligence-layers.settings.layers', []);

        $resolvable = 0;
        $withEvidence = 0;
        $rowStatus = [];

        foreach ($layers as $layer) {
            $classOk = class_exists((string) ($layer['owner_service'] ?? ''));
            $hasRows = false;

            foreach ((array) ($layer['reads_tables'] ?? []) as $table) {
                if ($this->rowCount($table) > 0) {
                    $hasRows = true;
                    break;
                }
            }

            if ($classOk) {
                $resolvable++;
            }
            if ($classOk && $hasRows) {
                $withEvidence++;
            }

            $rowStatus[(string) ($layer['key'] ?? '')] = match (true) {
                $classOk && $hasRows => ['tone' => 'good', 'label' => 'Live'],
                $classOk => ['tone' => 'warn', 'label' => 'No evidence'],
                default => ['tone' => 'critical', 'label' => 'Not deployed'],
            };
        }

        $total = count($layers);

        return $this->report(
            $withEvidence === $total ? 'operational' : ($resolvable > 0 ? 'degraded' : 'inactive'),
            $withEvidence === 0
                ? 'Every layer service is deployed but none has learner evidence to work on yet.'
                : "{$withEvidence} of {$total} layers are processing real learner evidence.",
            [
                $this->metric('Layers defined', $total, 'neutral'),
                $this->metric('Services resolvable', "{$resolvable}/{$total}", $resolvable === $total ? 'good' : 'critical'),
                $this->metric('Layers with evidence', "{$withEvidence}/{$total}", $withEvidence === $total ? 'good' : ($withEvidence > 0 ? 'warn' : 'critical')),
            ],
            $rowStatus
        );
    }

    /**
     * The loop is only closed if answers are being recorded. `pal_assessment_results`
     * is the join between a learner acting and the engine reacting, so its row
     * count is the honest headline for this subsystem.
     */
    private function adaptiveLoop(): array
    {
        $answers = $this->rowCount('pal_assessment_results');
        $sessions = $this->rowCount('pal_learning_sessions');
        $events = $this->rowCount('pal_session_events');
        $telemetry = $this->rowCount('pal_telemetry_events');

        $closed = $answers > 0 && $sessions > 0;

        return $this->report(
            $closed ? 'operational' : 'inactive',
            $closed
                ? 'Sessions and responses are being recorded, so the loop can execute end to end.'
                : 'No session or response is being recorded, so steps 3 onwards have nothing to run against. The quiz write path has to record into the PAL tables before the loop closes.',
            [
                $this->metric('Sessions recorded', $sessions, $sessions > 0 ? 'good' : 'critical'),
                $this->metric('Responses recorded', $answers, $answers > 0 ? 'good' : 'critical'),
                $this->metric('Session events', $events, $events > 0 ? 'good' : 'warn'),
                $this->metric('xAPI statements', $telemetry, $telemetry > 0 ? 'good' : 'warn'),
            ]
        );
    }

    private function masteryModel(): array
    {
        $rows = $this->rowCount('pal_competencies');
        $learners = $this->distinctCount('pal_competencies', 'learner_id');
        $average = $this->average('pal_competencies', 'mastery_score');
        $responses = $this->rowCount('pal_assessment_results');

        return $this->report(
            $rows > 0 ? 'operational' : 'inactive',
            $rows > 0
                ? "Mastery is tracked for {$learners} learner(s)."
                : 'No mastery record exists yet, so BKT parameters are configured but never applied. They take effect as soon as responses are recorded.',
            [
                $this->metric('Mastery records', $rows, $rows > 0 ? 'good' : 'critical'),
                $this->metric('Learners tracked', $learners, $learners > 0 ? 'good' : 'critical'),
                $this->metric('Mean mastery', $average === null ? '—' : number_format($average, 2), $average === null ? 'neutral' : 'good'),
                $this->metric('Responses available', $responses, $responses > 0 ? 'good' : 'warn'),
            ]
        );
    }

    /**
     * Stage readiness is a CONTENT question: can each stage actually be served?
     * Measured from the extracted chapter estate, which is the content source
     * the New PAL module is built on.
     */
    private function hpcStages(?int $tenant): array
    {
        $stages = (array) config('pal_architecture.subsystems.hpc-stages.settings.stages', []);
        $covered = 0;
        $rowStatus = [];
        $metrics = [];

        $hasSource = Schema::hasTable('semantic_intelligence');

        foreach ($stages as $stage) {
            $key = (string) ($stage['key'] ?? '');
            $count = $hasSource
                ? $this->chaptersInGradeRange((int) ($stage['grade_from'] ?? 0), (int) ($stage['grade_to'] ?? 0), $tenant)
                : 0;

            if ($count > 0) {
                $covered++;
            }

            $rowStatus[$key] = $count > 0
                ? ['tone' => 'good', 'label' => $count . ' chapter' . ($count === 1 ? '' : 's')]
                : ['tone' => 'warn', 'label' => 'No content'];

            $metrics[] = $this->metric(
                ucfirst($key),
                $count,
                $count > 0 ? 'good' : 'warn',
                'Grades ' . ($stage['grade_from'] ?? '?') . '–' . ($stage['grade_to'] ?? '?')
            );
        }

        $total = count($stages);

        return $this->report(
            $covered === $total ? 'operational' : ($covered > 0 ? 'degraded' : 'inactive'),
            $hasSource
                ? "{$covered} of {$total} stages have extracted chapter content behind them."
                : 'The chapter extraction table is not present on this server, so stage coverage cannot be measured.',
            $metrics,
            $rowStatus
        );
    }

    /**
     * The rubric is in force when assessment results carry an HPC level. The
     * base `pal_assessment_results` schema has no level column, so its absence
     * is itself the finding worth reporting.
     */
    private function progressionRubric(): array
    {
        $triggers = (array) config('pal_architecture.subsystems.progression-rubric.settings.triggers', []);
        $hasLevelColumn = $this->hasColumn('pal_assessment_results', 'hpc_level');
        $evidence = $this->rowCount('pal_learning_evidence');

        return $this->report(
            $hasLevelColumn ? 'operational' : 'inactive',
            $hasLevelColumn
                ? 'Assessment results carry an HPC level, so rubric ratings are being stored.'
                : 'Nothing stores an HPC level against a response yet, so rubric descriptors are authoritative for the agent prompt but no rating is persisted.',
            [
                $this->metric('Trigger rules', count($triggers), count($triggers) > 0 ? 'good' : 'warn'),
                $this->metric('Rating storage', $hasLevelColumn ? 'Present' : 'Missing', $hasLevelColumn ? 'good' : 'critical'),
                $this->metric('Learning evidence rows', $evidence, $evidence > 0 ? 'good' : 'warn'),
            ]
        );
    }

    private function knowledgeGraph(): array
    {
        $configured = (string) config('neo4j.uri', '') !== '';
        $driverInstalled = class_exists(\Laudis\Neo4j\ClientBuilder::class);
        $serviceExists = class_exists(\App\Services\Neo4jService::class);

        $connected = false;
        $detail = 'Not attempted.';

        if ($configured && $driverInstalled && $serviceExists) {
            try {
                // testConnection() swallows its own exceptions and returns a
                // message, so compare rather than relying on a throw.
                $result = app(\App\Services\Neo4jService::class)->testConnection();
                $connected = is_string($result) && str_contains(strtolower($result), 'success');
                $detail = is_string($result) ? $result : 'Unknown response.';
            } catch (Throwable $e) {
                $detail = $e->getMessage();
            }
        } elseif (! $configured) {
            $detail = 'No NEO4J_URI is configured for this environment.';
        } elseif (! $driverInstalled) {
            $detail = 'The laudis/neo4j-php-client package is not installed.';
        }

        return $this->report(
            $connected ? 'operational' : 'inactive',
            $connected
                ? 'The graph database is reachable. Concept and prerequisite projection can be enabled.'
                : 'The graph is not reachable, so prerequisite traversal falls back to the relational concept tables. ' . $detail,
            [
                $this->metric('Driver installed', $driverInstalled ? 'Yes' : 'No', $driverInstalled ? 'good' : 'critical'),
                $this->metric('URI configured', $configured ? 'Yes' : 'No', $configured ? 'good' : 'critical'),
                $this->metric('Connection', $connected ? 'Open' : 'Closed', $connected ? 'good' : 'critical'),
            ]
        );
    }

    private function aiAgents(): array
    {
        $agents = (array) config('pal_architecture.subsystems.ai-agents.settings.agents', []);
        $keyPresent = trim((string) config('openrouter.api_key', getenv('OPENROUTER_API_KEY') ?: '')) !== '';
        $baseUrl = (string) config('openrouter.base_url', '');
        $orchestratorExists = class_exists(\App\Services\PAL\AI\AIOrchestrationService::class);

        $enabled = 0;
        foreach ($agents as $agent) {
            if (! empty($agent['enabled'])) {
                $enabled++;
            }
        }

        $ready = $keyPresent && $baseUrl !== '' && $orchestratorExists;

        return $this->report(
            $ready ? 'operational' : 'inactive',
            $ready
                ? "The provider is configured; {$enabled} of " . count($agents) . ' agent personas are enabled.'
                : 'No AI provider credential is configured on this server, so every agent falls back to its non-generative behaviour.',
            [
                $this->metric('Orchestrator', $orchestratorExists ? 'Deployed' : 'Missing', $orchestratorExists ? 'good' : 'critical'),
                $this->metric('Provider key', $keyPresent ? 'Configured' : 'Missing', $keyPresent ? 'good' : 'critical'),
                $this->metric('Agents enabled', $enabled . '/' . count($agents), $enabled > 0 ? 'good' : 'warn'),
            ]
        );
    }

    /**
     * Coverage per dimension, measured against the table each one declares as
     * its evidence source. This is the sharpest diagnostic in the module: it
     * names exactly which of the nine dimensions are inert and why.
     */
    private function studentModel(): array
    {
        $dimensions = (array) config('pal_architecture.subsystems.student-model.settings.dimensions', []);

        $withEvidence = 0;
        $rowStatus = [];

        foreach ($dimensions as $dimension) {
            $table = (string) ($dimension['evidence_table'] ?? '');
            $exists = $table !== '' && Schema::hasTable($table);
            $rows = $exists ? $this->rowCount($table) : 0;

            if ($rows > 0) {
                $withEvidence++;
            }

            $rowStatus[(string) ($dimension['key'] ?? '')] = match (true) {
                $rows > 0 => ['tone' => 'good', 'label' => number_format($rows) . ' rows'],
                $exists => ['tone' => 'warn', 'label' => 'Table empty'],
                default => ['tone' => 'critical', 'label' => 'No table'],
            };
        }

        $total = count($dimensions);

        return $this->report(
            $withEvidence === $total ? 'operational' : ($withEvidence > 0 ? 'degraded' : 'inactive'),
            $withEvidence === 0
                ? 'No dimension has evidence yet. The model is fully specified and returns defaults until learner events are recorded.'
                : "{$withEvidence} of {$total} dimensions have evidence to infer from.",
            [
                $this->metric('Dimensions defined', $total, 'neutral'),
                $this->metric('With evidence', "{$withEvidence}/{$total}", $withEvidence === $total ? 'good' : ($withEvidence > 0 ? 'warn' : 'critical')),
                $this->metric('Learner state rows', $this->rowCount('pal_learner_states'), $this->rowCount('pal_learner_states') > 0 ? 'good' : 'warn'),
            ],
            $rowStatus
        );
    }

    private function careerPathway(): array
    {
        $policy = (array) config('pal_architecture.subsystems.career-pathway.settings.policy', []);
        $minEvents = (int) ($policy['min_events_for_report'] ?? 1500);

        $events = $this->rowCount('pal_learning_events');
        $evidence = $this->rowCount('pal_learning_evidence');
        $tagged = $this->taggedEventCount();

        $catalog = $this->frameworks->catalog();
        $vocabularyReady = count((array) ($catalog['riasec'] ?? [])) > 0
            && count((array) ($catalog['ncdg'] ?? [])) > 0;

        return $this->report(
            $tagged >= $minEvents ? 'operational' : ($tagged > 0 ? 'degraded' : 'inactive'),
            $tagged === 0
                ? 'No learning event carries a career signal yet. The vocabularies are in force on content, but nothing accumulates them onto a learner, so no report can be generated.'
                : "{$tagged} career-tagged event(s) accumulated; {$minEvents} are required before a report is considered valid.",
            [
                $this->metric('Learning events', $events, $events > 0 ? 'good' : 'critical'),
                $this->metric('Career-tagged events', $tagged, $tagged > 0 ? 'good' : 'critical'),
                $this->metric('Evidence rows', $evidence, $evidence > 0 ? 'good' : 'warn'),
                $this->metric('Vocabularies loaded', $vocabularyReady ? 'Yes' : 'No', $vocabularyReady ? 'good' : 'critical'),
            ]
        );
    }

    /** The career vocabularies actually in force, read through the catalog service. */
    public function careerVocabulary(): array
    {
        $catalog = $this->frameworks->catalog();
        $out = [];

        foreach (['riasec', 'gardner', 'ncdg'] as $group) {
            $values = (array) ($catalog[$group] ?? []);
            if ($values !== []) {
                $out[] = ['group' => $group, 'values' => array_values($values)];
            }
        }

        $out[] = [
            'group' => 'aptitude',
            'values' => array_values((array) config('pal_architecture.subsystems.career-pathway.settings.aptitude_domains', [])),
        ];

        return $out;
    }

    /** Which blueprint node labels actually exist in the connected graph. */
    public function graphSchemaPresence(): array
    {
        $schema = (array) config('pal_architecture.subsystems.knowledge-graph.settings.schema', []);
        $present = $this->presentGraphLabels();

        $nodes = array_map(static function (array $node) use ($present) {
            $label = (string) ($node['label'] ?? '');

            return $node + [
                'present' => $present === null ? null : in_array($label, $present, true),
            ];
        }, (array) ($schema['nodes'] ?? []));

        return [
            'nodes' => $nodes,
            'relationships' => array_values((array) ($schema['relationships'] ?? [])),
            'probed' => $present !== null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Measurement helpers — every one is guarded, none may throw
    // ══════════════════════════════════════════════════════════════════════

    private function rowCount(string $table): int
    {
        if ($table === '' || ! Schema::hasTable($table)) {
            return 0;
        }

        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function distinctCount(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! $this->hasColumn($table, $column)) {
            return 0;
        }

        try {
            return (int) DB::table($table)->distinct()->count($column);
        } catch (Throwable) {
            return 0;
        }
    }

    private function average(string $table, string $column): ?float
    {
        if (! Schema::hasTable($table) || ! $this->hasColumn($table, $column)) {
            return null;
        }

        try {
            $value = DB::table($table)->avg($column);

            return $value === null ? null : (float) $value;
        } catch (Throwable) {
            return null;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /** Chapters whose standard falls inside a stage's grade range. */
    private function chaptersInGradeRange(int $from, int $to, ?int $tenant): int
    {
        if (! $this->hasColumn('semantic_intelligence', 'standard')) {
            return 0;
        }

        try {
            $query = DB::table('semantic_intelligence')
                ->whereBetween('standard', [$from, $to]);

            if ($tenant !== null && $this->hasColumn('semantic_intelligence', 'sub_institute_id')) {
                $query->where('sub_institute_id', $tenant);
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Learning events carrying any career signal column. */
    private function taggedEventCount(): int
    {
        if (! Schema::hasTable('pal_learning_events')) {
            return 0;
        }

        $columns = array_values(array_filter(
            ['riasec_signal', 'ncdg_goal', 'gardner_intelligence'],
            fn (string $column) => $this->hasColumn('pal_learning_events', $column)
        ));

        if ($columns === []) {
            return 0;
        }

        try {
            $query = DB::table('pal_learning_events');
            $query->where(function ($inner) use ($columns) {
                foreach ($columns as $column) {
                    $inner->orWhereNotNull($column);
                }
            });

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return string[]|null null when the graph could not be probed at all */
    private function presentGraphLabels(): ?array
    {
        if ((string) config('neo4j.uri', '') === '' || ! class_exists(\App\Services\Neo4jService::class)) {
            return null;
        }

        try {
            $client = app(\App\Services\Neo4jService::class)->getClient();
            $result = $client->run('CALL db.labels() YIELD label RETURN label');

            $labels = [];
            foreach ($result as $record) {
                $labels[] = (string) $record->get('label');
            }

            return $labels;
        } catch (Throwable) {
            return null;
        }
    }

    private function metric(string $label, mixed $value, string $tone, ?string $note = null): array
    {
        return [
            'label' => $label,
            'value' => is_int($value) ? number_format($value) : (string) $value,
            'tone' => $tone,
            'note' => $note,
        ];
    }

    private function report(string $status, string $summary, array $metrics, array $rowStatus = []): array
    {
        return [
            'status' => $status,
            'summary' => $summary,
            'metrics' => array_values($metrics),
            'row_status' => $rowStatus,
        ];
    }
}
