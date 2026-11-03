<?php

namespace App\Services\CoursePresentation;

use App\Services\StudyDeck\TeachingMethodProfile;

/**
 * The staged pedagogical workflow of the Course Presentation card, switched on for ONE chapter
 * (config pedagogical_workflow.scope). Reads config only; nothing here touches the database.
 *
 * Every other chapter is "not applicable": the caller keeps the existing card and pipeline.
 */
class PedagogicalWorkflow
{
    private array $config;
    private MethodContextRepository $contexts;

    public function __construct(?array $config = null, ?MethodContextRepository $contexts = null)
    {
        $this->config = $config ?? (array) config('pedagogical_workflow', []);
        $this->contexts = $contexts ?? new MethodContextRepository();
    }

    /** Chapter, standard, subject and school must ALL match the one pilot entry. */
    public function appliesTo(object|array $chapter): bool
    {
        $s = (array) ($this->config['scope'] ?? []);
        $row = (array) $chapter;

        return $s !== []
            && (int) ($row['id'] ?? 0) === (int) $s['chapter_id']
            && (int) ($row['standard_id'] ?? 0) === (int) $s['standard_id']
            && (int) ($row['subject_id'] ?? 0) === (int) $s['subject_id']
            && (int) ($row['sub_institute_id'] ?? 0) === (int) $s['sub_institute_id'];
    }

    /** For the background run, which already has the verified ids only. */
    public function appliesToIds(int $chapterId, int $tenant): bool
    {
        $s = (array) ($this->config['scope'] ?? []);

        return $s !== [] && $chapterId === (int) $s['chapter_id'] && $tenant === (int) $s['sub_institute_id'];
    }

    /**
     * Active methods. A method's definition comes from the stored context (pedagogical_method_contexts) when that row exists
     * and is complete; a method switched off there is left out; a method with no stored row uses config/pedagogical_workflow.php
     * (so nothing breaks before the table is created and seeded). The config still decides WHICH methods exist.
     *
     * @return array<string,array<string,mixed>>
     */
    public function methods(): array
    {
        $out = [];
        foreach ((array) ($this->config['methods'] ?? []) as $key => $m) {
            if (empty($m['active']) || $this->contexts->isDisabled((string) $key)) {
                continue;
            }
            $row = $this->contexts->active((string) $key);
            $out[$key] = $row === null ? $m : $this->fromStored($m, $row);
        }

        return $out;
    }

    /** The stored context laid over the config entry; fields the stored row does not carry keep the config value. @return array<string,mixed> */
    private function fromStored(array $config, \App\Models\PedagogicalMethodContext $row): array
    {
        $activities = (array) $row->classroom_activities;
        $assessment = (array) $row->assessment_strategy;

        return [
            'name' => $row->method_name, 'description' => $row->method_definition,
            'objectives' => $assessment['learning_objectives'] ?? $config['objectives'],
            'teacher_role' => $row->teacher_role, 'student_role' => $row->student_role,
            'activity_types' => $activities['types'] ?? $config['activity_types'],
            'interaction_types' => $activities['interaction_types'] ?? $config['interaction_types'],
            'assessment' => $assessment['summary'] ?? $config['assessment'],
            'ai_instructions' => $row->ai_generation_instructions,
            'philosophy' => $row->teaching_philosophy, 'principles' => (array) $row->core_principles,
            'strategies' => (array) $row->teaching_strategies, 'questioning' => (array) $row->questioning_strategies,
            'active' => true, 'stages' => (array) $row->workflow_stages, 'source' => 'database',
        ];
    }

    /** True when a generator is implemented for the format AND the pipeline takes the teaching method. */
    public function formatAvailable(string $format): bool
    {
        $f = $this->config['formats'][$format] ?? null;

        return $f !== null
            && $f['generator_key'] !== null
            && in_array($format, (array) config('course_presentation_pilot.generation.formats', []), true)
            && !empty(config("course_presentation_pilot.format_capability.$format.pedagogy_applied"));
    }

    /** @return array<int,array<string,mixed>> formats compatible with the method, each flagged available or not */
    public function formatsFor(string $method): array
    {
        $out = [];
        foreach ((array) ($this->config['compatibility'][$method] ?? []) as $key) {
            $f = $this->config['formats'][$key] ?? null;
            if ($f === null) {
                continue;
            }
            $out[] = [
                'key' => $key, 'name' => $f['name'], 'description' => $f['description'],
                'available' => $this->formatAvailable($key),
                'unavailable_reason' => $this->formatAvailable($key) ? null : 'No generator is implemented for this format yet.',
            ];
        }

        return $out;
    }

    /** @return array<string,array<int,string>> method => compatible format keys, for active methods only */
    public function compatibility(): array
    {
        return array_intersect_key((array) ($this->config['compatibility'] ?? []), $this->methods());
    }

    /** null when the combination may be used, otherwise the reason it may not. */
    public function refusal(string $method, string $format): ?string
    {
        if (!isset($this->methods()[$method])) {
            return 'This teaching method is not available for this chapter.';
        }
        if (!in_array($format, (array) ($this->config['compatibility'][$method] ?? []), true)) {
            return 'This content format does not suit the chosen teaching method.';
        }
        if (!$this->formatAvailable($format)) {
            return 'No generator is implemented for this content format yet.';
        }

        return null;
    }

    /** What the teacher reviews before generating: the method, its roles and every stage. @return array<string,mixed>|null */
    public function workflow(string $method): ?array
    {
        $m = $this->methods()[$method] ?? null;
        if ($m === null) {
            return null;
        }

        return [
            'key' => $method, 'name' => $m['name'], 'description' => $m['description'], 'objectives' => $m['objectives'],
            'teacher_role' => $m['teacher_role'], 'student_role' => $m['student_role'], 'activity_types' => $m['activity_types'],
            'interaction_types' => $m['interaction_types'], 'assessment' => $m['assessment'],
            'total_minutes' => array_sum(array_column($m['stages'], 'minutes')),
            'stages' => array_map(fn ($st, $i) => ['number' => $i + 1] + $st, $m['stages'], array_keys($m['stages'])),
        ];
    }

    /**
     * The context the study-deck pipeline receives for this method and format. Facts (concepts, outcomes, text) are
     * NOT put here: the pipeline loads them from the chapter itself, so nothing outside chapter 8592 can enter.
     *
     * @return array<string,mixed>|null
     */
    public function generationContext(string $method, string $format): ?array
    {
        $wf = $this->workflow($method);
        $f = $this->config['formats'][$format] ?? null;
        if ($wf === null || $f === null || $this->refusal($method, $format) !== null) {
            return null;
        }
        $m = $this->methods()[$method];

        return [
            'chapter_id' => (int) $this->config['scope']['chapter_id'],
            // Every stored field of the method context (the eleven of pedagogical_method_contexts); rule() decides which reach which prompt.
            'method' => [
                'key' => $method, 'name' => $wf['name'], 'definition' => $wf['description'], 'ai_instructions' => $m['ai_instructions'],
                'teacher_role' => $wf['teacher_role'], 'student_role' => $wf['student_role'], 'assessment' => $wf['assessment'],
                'philosophy' => $m['philosophy'] ?? null, 'principles' => $m['principles'] ?? [], 'strategies' => $m['strategies'] ?? [],
                'questioning' => $m['questioning'] ?? [], 'activity_types' => $wf['activity_types'], 'interaction_types' => $wf['interaction_types'],
                'source' => $m['source'] ?? 'config',
            ],
            'format' => ['key' => $format, 'name' => $f['name'], 'structure' => (string) ($f['structure'] ?? '')],
            'stages' => $wf['stages'],
            'outcomes' => [],
        ];
    }

    /**
     * Adds the chapter's stored learning outcomes and competencies to a generation context. The reference of each is its own stored
     * code (for example "C5.2-LO-3"), so nothing is numbered or invented here. A row with no description is left out. With no rows the
     * list stays empty and `outcomes_note` says why, so a validator reports "not checked" instead of passing.
     *
     * @param array<string,mixed> $context a generationContext() value
     * @param array<int,array{type:string,code:string,description:string}> $rows SourceRegistry::acceptedOutcomes()
     * @return array<string,mixed>
     */
    public function withOutcomes(array $context, array $rows, ?string $note = null): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $code = trim((string) ($r['code'] ?? ''));
            $text = trim((string) ($r['description'] ?? ''));
            if ($code === '' || $text === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = ['ref' => $code, 'type' => ($r['type'] ?? '') === 'competency' ? 'competency' : 'learning_outcome', 'code' => $code, 'description' => $text];
        }
        $context['outcomes'] = $out;
        $context['outcomes_note'] = $out === [] ? ($note ?? 'No learning outcomes or competencies are stored for this chapter; outcome coverage is not checked.') : null;

        return $context;
    }

    /**
     * Prompt paragraph for the pipeline. Appended after the existing method rule, so the method's own checks still apply.
     *
     * @param array<string,mixed>|null $workflow a generationContext() value; null leaves every prompt unchanged
     */
    public static function rule(?array $workflow, string $kind): string
    {
        if (empty($workflow['stages'])) {
            return '';
        }
        $stages = [];
        foreach ($workflow['stages'] as $st) {
            $stages[] = "{$st['number']}. {$st['name']} (~{$st['minutes']} min): goal \"{$st['objective']}\"; activity: {$st['activity']}; teacher: {$st['teacher']} student: {$st['student']}; ask: " . implode(' / ', $st['questions']) . "; interaction: {$st['interactions']}; visual: {$st['visual']}; outcome: {$st['outcome']}";
        }
        $m = $workflow['method'];
        $count = count($workflow['stages']);
        $outcomes = (array) ($workflow['outcomes'] ?? []);
        $join = fn ($v) => implode('; ', array_map('strval', (array) $v));

        // Which stored method fields each prompt needs: the planner shapes the lesson, the text writer speaks to students,
        // the interaction planner chooses what students do, the story gets all of it.
        $lines = [
            'definition' => ['Method definition', $m['definition'] ?? ''],
            'philosophy' => ['Method philosophy', $m['philosophy'] ?? ''],
            'principles' => ['Core principles', $join($m['principles'] ?? [])],
            'strategies' => ['Teaching strategies', $join($m['strategies'] ?? [])],
            'teacher_role' => ['Teacher role', $m['teacher_role'] ?? ''],
            'student_role' => ['Student role', $m['student_role'] ?? ''],
            'activities' => ['Classroom activities', $join($m['activity_types'] ?? [])],
            'interactions' => ['Interaction types', $join($m['interaction_types'] ?? [])],
            'questioning' => ['Questioning style', $join($m['questioning'] ?? [])],
            'assessment' => ['Assessment strategy', $m['assessment'] ?? ''],
        ];
        $wanted = match ($kind) {
            'planner' => ['definition', 'philosophy', 'principles', 'strategies', 'activities', 'assessment'],
            'content' => ['philosophy', 'principles', 'teacher_role', 'student_role', 'activities', 'questioning', 'assessment'],
            'story' => array_keys($lines),
            default => ['student_role', 'activities', 'interactions', 'questioning'],
        };
        $context = '';
        foreach ($wanted as $key) {
            if (!empty($lines[$key][1])) {
                $context .= "\n{$lines[$key][0]}: {$lines[$key][1]}" . (str_ends_with((string) $lines[$key][1], '.') ? '' : '.');
            }
        }

        $head = "\n- PEDAGOGICAL WORKFLOW ({$m['name']} as {$workflow['format']['name']}): follow these stages IN ORDER and cover every concept inside them; do not write generic slides under renamed titles. "
            . $m['ai_instructions'] . ' ' . $workflow['format']['structure'] . $context;

        $outcomeBlock = '';
        if ($outcomes !== []) {
            $outcomeBlock = "\nLEARNING OUTCOMES AND COMPETENCIES for this chapter (the school's stored curriculum; use ONLY these, never invent others):\n"
                . implode("\n", array_map(fn ($o) => "- {$o['ref']} ({$o['type']}): {$o['description']}", $outcomes));
        }

        $tail = match ($kind) {
            'planner' => ' Give EVERY slide except the cover a "workflow_stage": a whole number from 1 to ' . $count . ' naming the stage it belongs to. The deck may run the stage sequence again inside each topic, but within one topic the stage numbers never go down, and every stage from 1 to ' . $count . ' must appear on at least one slide.'
                . ($outcomes === [] ? '' : ' Give each slide an "outcome_refs" list holding the refs (from the list above) of the outcomes and competencies it genuinely helps achieve ([] if none). EVERY listed outcome and competency must appear in the outcome_refs of at least one slide. Use only the listed refs.'),
            'content' => ' Each slide in the input names its workflow_stage (and stage name) and its outcome_refs: write the slide as that stage, using the stage activity and questions for the text and the check, addressed to the students described above.'
                . ($outcomes === [] ? '' : ' Make sure every outcome listed in a slide\'s outcome_refs is actually addressed in its explanation or check.'),
            'story' => ' Scenes follow the stages in order; the stage questions are the student decisions.'
                . ($outcomes === [] ? '' : ' Across the story, address every outcome and competency listed above (this is not checked automatically for stories).'),
            default => ' Make each interaction the stage interaction type where it fits.',
        };

        return $head . $outcomeBlock . $tail . "\nStages:\n" . implode("\n", $stages);
    }

    /** The method profile key must exist in the shared pipeline, otherwise the method cannot be applied. */
    public function pipelineKnows(string $method): bool
    {
        return in_array($method, TeachingMethodProfile::keys(), true);
    }
}
