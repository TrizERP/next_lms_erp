<?php

namespace App\Services\lms\Prayogshala;

use App\Services\QuestionGeneration\H5p\ClaudeQuestionClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generates the ONE Prayogshala activity of a topic from that topic's real curriculum data.
 *
 *   topic_master (+ chapter_master -> standard, subject)
 *     + lms_concept rows of the topic          } the topic's concepts, all covered by the one activity
 *     + the chapter's own extracted text       } document_extractions.md_content, only the passages
 *                                                that mention the topic or its concepts
 *   -> sufficiency check (honest: too little source = `needs_content`, nothing invented)
 *   -> the configured Claude provider (the same ClaudeQuestionClient / ProviderKeyResolver the
 *      question and content generators use; the key never leaves the server)
 *   -> SimulationConfigValidator (one automatic repair round, then `failed`)
 *   -> lms_prayogshala_activity, status `review` (a teacher publishes; learners never see it before)
 *
 * IDEMPOTENT. The row is claimed first, under a lock and behind the (institute, topic) unique index,
 * so a refresh, a double click or two workers cannot make a second activity or a second AI call:
 * a ready topic is returned as is; a topic another worker is generating answers `busy`. Only an
 * explicit `$regenerate` replaces content, and if that attempt fails the existing content is kept.
 *
 * Nothing here knows a standard, subject or chapter: every id comes from the topic row.
 */
class PrayogshalaGenerator
{
    /** A `generating` claim older than this is a dead worker, not a live one. */
    private const CLAIM_TTL_MINUTES = 15;
    private const EXCERPT_CHARS = 6000;
    private const MIN_DESCRIPTION_CHARS = 40;
    private const MIN_EXCERPT_CHARS = 300;
    public const GENERATION_VERSION = 1;

    public function __construct(
        private PrayogshalaService $service,
        private SimulationConfigValidator $validator,
        private ClaudeQuestionClient $client,
    ) {
    }

    // ---------------------------------------------------------------- source data

    /**
     * Everything known about a topic, resolved through the real hierarchy. Null when the topic
     * does not exist or its chapter is not visible to this institute.
     *
     * @return array<string,mixed>|null
     */
    public function context(int $topicId, int $tenant): ?array
    {
        $topic = DB::table('topic_master')->where('id', $topicId)->first();
        if (! $topic) {
            return null;
        }
        $chapter = $this->service->visibleChapter((int) $topic->chapter_id, $tenant);
        if (! $chapter) {
            return null;
        }

        $concepts = DB::table('lms_concept')
            ->where('topic_id', $topic->id)
            ->where('chapter_id', $chapter->id)
            ->orderBy('id')
            ->limit(12)
            ->get(['id', 'name', 'definition', 'description'])
            ->map(fn ($c) => [
                'id'         => (int) $c->id,
                'name'       => trim((string) $c->name),
                'definition' => trim((string) ($c->definition ?: $c->description ?: '')),
            ])
            ->all();

        $extraction = DB::table('document_extractions')
            ->where('chapter_id', $chapter->id)
            ->where('document_type', 'Chapter')
            ->whereNotNull('md_content')
            ->orderByDesc('id')
            ->first(['id', 'md_content']);
        $excerpt = $extraction ? $this->excerpt((string) $extraction->md_content, $topic->name, $concepts) : '';

        $description = trim((string) ($topic->description ?? ''));
        $sufficient = mb_strlen($description) >= self::MIN_DESCRIPTION_CHARS
            && (count($concepts) >= 1 || mb_strlen($excerpt) >= self::MIN_EXCERPT_CHARS);

        $hash = hash('sha256', json_encode([$topic->name, $description, $concepts, $excerpt], JSON_UNESCAPED_UNICODE));

        return [
            'topic'      => $topic,
            'chapter'    => $chapter,
            'hierarchy'  => $this->service->chapterContext($chapter),
            'concepts'   => $concepts,
            'excerpt'    => $excerpt,
            'description' => $description,
            'sufficient' => $sufficient,
            'insufficient_reason' => $sufficient ? null : $this->insufficientReason($description, $concepts, $excerpt),
            'source_hash' => $hash,
            'source_refs' => [
                'topic_id'       => (int) $topic->id,
                'chapter_id'     => (int) $chapter->id,
                'concept_ids'    => array_column($concepts, 'id'),
                'extraction_ids' => $extraction ? [(int) $extraction->id] : [],
                'excerpt_chars'  => mb_strlen($excerpt),
            ],
        ];
    }

    private function insufficientReason(string $description, array $concepts, string $excerpt): string
    {
        $missing = [];
        if (mb_strlen($description) < self::MIN_DESCRIPTION_CHARS) {
            $missing[] = 'the topic has no usable description';
        }
        if ($concepts === [] && mb_strlen($excerpt) < self::MIN_EXCERPT_CHARS) {
            $missing[] = 'it has no concepts and no matching passages in the chapter text';
        }

        return 'Not enough source material: ' . implode('; ', $missing) . '. Add topic details, concepts or the chapter text, then generate again.';
    }

    /**
     * The chapter paragraphs that mention the topic or its concepts, in reading order, within a
     * budget. Nothing is returned when nothing matches: the head of the chapter is not a
     * substitute for the topic.
     *
     * @param  list<array{name:string}>  $concepts
     */
    private function excerpt(string $markdown, string $topicName, array $concepts): string
    {
        $words = [];
        foreach (array_merge([$topicName], array_column($concepts, 'name')) as $phrase) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($phrase)) ?: [] as $w) {
                if (mb_strlen($w) >= 5) {
                    $words[$w] = true;
                }
            }
        }
        if ($words === []) {
            return '';
        }

        $scored = [];
        foreach (preg_split('/\n\s*\n/', $markdown) ?: [] as $i => $para) {
            $para = trim($para);
            if (mb_strlen($para) < 60) {
                continue;
            }
            $lower = mb_strtolower($para);
            $hits = 0;
            foreach (array_keys($words) as $w) {
                $hits += substr_count($lower, $w) > 0 ? 1 : 0;
            }
            if ($hits >= 2 || ($hits === 1 && count($words) <= 2)) {
                $scored[] = ['i' => $i, 'hits' => $hits, 'text' => $para];
            }
        }
        usort($scored, fn ($a, $b) => $b['hits'] <=> $a['hits'] ?: $a['i'] <=> $b['i']);

        $chosen = [];
        $size = 0;
        foreach ($scored as $p) {
            if ($size + mb_strlen($p['text']) > self::EXCERPT_CHARS) {
                continue;
            }
            $chosen[$p['i']] = $p['text'];
            $size += mb_strlen($p['text']);
        }
        ksort($chosen);

        return implode("\n\n", $chosen);
    }

    // --------------------------------------------------------------- generation

    /**
     * @return array{outcome:string,activity_id:?int,message:string}
     *   outcome: created | regenerated | exists | busy | needs_content | failed | forbidden | not_found
     */
    public function generate(int $topicId, int $tenant, ?int $userId, bool $regenerate = false): array
    {
        $ctx = $this->context($topicId, $tenant);
        if (! $ctx) {
            return $this->result('not_found', null, 'Topic not found.');
        }

        [$state, $row] = $this->claim($ctx, $tenant, $userId, $regenerate);
        if ($state === 'forbidden') {
            return $this->result('forbidden', (int) $row->id, 'This activity belongs to the platform and can only be regenerated by its owner.');
        }
        if ($state === 'exists') {
            return $this->result('exists', (int) $row->id, 'An activity already exists for this topic.');
        }
        if ($state === 'busy') {
            return $this->result('busy', (int) $row->id, 'This topic is already being generated.');
        }

        $hadContent = $row->lab_config !== null;

        try {
            if (! $ctx['sufficient']) {
                return $this->finishNeedsContent($row, $ctx['insufficient_reason'], $hadContent);
            }

            $outcome = $this->askAndValidate($ctx, $tenant);
            if ($outcome['kind'] === 'insufficient') {
                return $this->finishNeedsContent($row, $outcome['reason'], $hadContent);
            }
            if ($outcome['kind'] === 'failed') {
                return $this->finishFailed($row, $outcome['error'], $hadContent);
            }

            return $this->finishReady($row, $ctx, $outcome, $regenerate && $hadContent);
        } catch (\Throwable $e) {
            Log::error('Prayogshala generation crashed', ['topic_id' => $topicId, 'error' => $e->getMessage()]);

            return $this->finishFailed($row, 'Generation stopped unexpectedly: ' . $e->getMessage(), $hadContent);
        }
    }

    /**
     * Take the topic's row, or learn why it cannot be taken.
     *
     * @param  array<string,mixed>  $ctx
     * @return array{0:string,1:object}  state: claimed | exists | busy | forbidden
     */
    private function claim(array $ctx, int $tenant, ?int $userId, bool $regenerate): array
    {
        $topic = $ctx['topic'];
        $chapter = $ctx['chapter'];

        try {
            return DB::transaction(function () use ($topic, $chapter, $tenant, $userId, $regenerate) {
                // Own row first, then a platform row this institute can read.
                $existing = DB::table('lms_prayogshala_activity')
                    ->where('topic_id', $topic->id)
                    ->whereNull('deleted_at')
                    ->whereIn('sub_institute_id', $this->service->visibleTenants($tenant))
                    ->orderByRaw('sub_institute_id = ? desc', [$tenant])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ((int) $existing->sub_institute_id !== $tenant) {
                        return [$regenerate ? 'forbidden' : 'exists', $existing];
                    }
                    $live = $existing->generation_status === 'generating'
                        && strtotime((string) $existing->updated_at) > time() - self::CLAIM_TTL_MINUTES * 60;
                    if ($live) {
                        return ['busy', $existing];
                    }
                    $done = $existing->lab_config !== null && $existing->generation_status !== 'failed' && $existing->generation_status !== 'needs_content';
                    if ($done && ! $regenerate) {
                        return ['exists', $existing];
                    }

                    DB::table('lms_prayogshala_activity')->where('id', $existing->id)->update([
                        'generation_status'   => 'generating',
                        'generation_attempts' => (int) $existing->generation_attempts + 1,
                        'updated_by'          => $userId,
                        'updated_at'          => now(),
                    ]);

                    return ['claimed', DB::table('lms_prayogshala_activity')->find($existing->id)];
                }

                $id = DB::table('lms_prayogshala_activity')->insertGetId([
                    'sub_institute_id'    => $tenant,
                    'syear'               => $chapter->syear ?? null,
                    'grade_id'            => $chapter->grade_id,
                    'standard_id'         => $chapter->standard_id,
                    'subject_id'          => $chapter->subject_id,
                    'chapter_id'          => $chapter->id,
                    'topic_id'            => $topic->id,
                    'topic_slot'          => $topic->id,
                    'title'               => mb_substr((string) $topic->name, 0, 250),
                    'slug'                => 'topic-' . $topic->id,
                    'activity_type'       => 'experiment',
                    // Hidden from learners until a teacher publishes real content.
                    'status'              => 'draft',
                    'show_hide'           => 1,
                    'sort_order'          => (int) ($topic->topic_sort_order ?? 0),
                    'generation_status'   => 'generating',
                    'generation_attempts' => 1,
                    'created_by'          => $userId,
                    'updated_by'          => $userId,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                return ['claimed', DB::table('lms_prayogshala_activity')->find($id)];
            });
        } catch (UniqueConstraintViolationException $e) {
            // Lost the race to another worker for the same topic.
            $row = DB::table('lms_prayogshala_activity')->where('sub_institute_id', $tenant)->where('topic_slot', $topic->id)->first();

            return ['busy', $row];
        }
    }

    /**
     * One model call, one repair round if the config is refused.
     *
     * @param  array<string,mixed>  $ctx
     * @return array{kind:string,activity?:array,model?:string,reason?:string,error?:string}
     */
    private function askAndValidate(array $ctx, int $tenant): array
    {
        $system = $this->systemPrompt();
        $user = $this->userPrompt($ctx);
        $problems = [];

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $prompt = $attempt === 1 ? $user : $user . "\n\nYour previous answer was rejected by the validator:\n- "
                . implode("\n- ", array_slice($problems, 0, 8)) . "\nReturn the corrected full JSON object only.";

            $response = $this->client->complete($system, $prompt, $tenant);
            if (! ($response['ok'] ?? false)) {
                return ['kind' => 'failed', 'error' => (string) ($response['error'] ?? 'The AI provider did not answer.')];
            }

            $data = $this->decodeJson((string) $response['content']);
            if ($data === null) {
                $problems = ['The reply was not a single JSON object.'];
                continue;
            }
            if (! empty($data['insufficient'])) {
                return ['kind' => 'insufficient', 'reason' => 'The model could not build a faithful activity from the available material: '
                    . mb_substr((string) ($data['reason'] ?? 'no reason given'), 0, 400)];
            }

            $problems = $this->validateActivity($data);
            if ($problems === []) {
                return ['kind' => 'ok', 'activity' => $data, 'model' => (string) ($response['model'] ?? '')];
            }
        }

        return ['kind' => 'failed', 'error' => 'The generated activity did not pass validation: ' . implode(' ', array_slice($problems, 0, 4))];
    }

    /** @return list<string> */
    private function validateActivity(array $d): array
    {
        $problems = [];
        foreach (['title' => 250, 'objective' => 1200, 'description' => 2000, 'observation' => 1500, 'result' => 1500] as $key => $max) {
            if (! is_string($d[$key] ?? null) || trim($d[$key]) === '' || mb_strlen($d[$key]) > $max) {
                $problems[] = "$key must be non-empty text of at most $max characters.";
            }
        }
        foreach (['materials_required', 'procedure_steps'] as $key) {
            if (! is_array($d[$key] ?? null) || ! array_is_list($d[$key]) || $d[$key] === [] || count($d[$key]) > 20) {
                $problems[] = "$key must be a list of 1 to 20 strings.";
            }
        }

        return array_merge($problems, $this->validator->validate($d['lab_config'] ?? null));
    }

    // ----------------------------------------------------------------- outcomes

    /** @param array<string,mixed> $outcome */
    private function finishReady(object $row, array $ctx, array $outcome, bool $isRegeneration): array
    {
        $a = $outcome['activity'];
        $clean = fn ($v) => is_string($v) ? trim($v) : null;
        $list = fn ($v) => json_encode(array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $v), fn ($s) => $s !== '')), JSON_UNESCAPED_UNICODE);
        $type = array_key_exists($a['activity_type'] ?? '', PrayogshalaService::ACTIVITY_TYPES) ? $a['activity_type'] : 'experiment';
        $conceptIds = $ctx['source_refs']['concept_ids'];

        DB::table('lms_prayogshala_activity')->where('id', $row->id)->update([
            'title'                => mb_substr($clean($a['title']), 0, 250),
            'activity_type'        => $type,
            'description'          => $clean($a['description']),
            'objective'            => $clean($a['objective']),
            'materials_required'   => $list($a['materials_required']),
            'procedure_steps'      => $list($a['procedure_steps']),
            'observation'          => $clean($a['observation']),
            'result'               => $clean($a['result']),
            'safety_instructions'  => $clean($a['safety_instructions'] ?? null),
            'teacher_instructions' => $clean($a['teacher_instructions'] ?? null),
            'student_instructions' => $clean($a['student_instructions'] ?? null),
            'estimated_minutes'    => isset($a['estimated_minutes']) ? max(1, min(240, (int) $a['estimated_minutes'])) : null,
            'lab_config'           => json_encode($a['lab_config'], JSON_UNESCAPED_UNICODE),
            // A generated activity always waits for a teacher, including a regenerated one.
            'status'               => 'review',
            'generation_status'    => 'ready',
            'generation_version'   => (int) $row->generation_version + 1,
            'generation_error'     => null,
            'generation_model'     => mb_substr($outcome['model'] ?: 'unknown', 0, 80),
            'generated_at'         => now(),
            'source_hash'          => $ctx['source_hash'],
            'source_refs'          => json_encode($ctx['source_refs']),
            'concept_ids'          => json_encode($conceptIds),
            'concept_id'           => count($conceptIds) === 1 ? $conceptIds[0] : null,
            'updated_at'           => now(),
        ]);

        return $this->result($isRegeneration ? 'regenerated' : 'created', (int) $row->id, 'Activity generated and waiting for teacher review.');
    }

    private function finishNeedsContent(object $row, string $reason, bool $hadContent): array
    {
        DB::table('lms_prayogshala_activity')->where('id', $row->id)->update($hadContent
            ? ['generation_status' => 'ready', 'generation_error' => $reason, 'updated_at' => now()]
            : ['generation_status' => 'needs_content', 'generation_error' => $reason, 'updated_at' => now()]);

        return $this->result('needs_content', (int) $row->id, $reason);
    }

    private function finishFailed(object $row, string $error, bool $hadContent): array
    {
        $error = mb_substr($error, 0, 1000);
        DB::table('lms_prayogshala_activity')->where('id', $row->id)->update([
            'generation_status' => $hadContent ? 'ready' : 'failed',
            'generation_error'  => $error,
            'updated_at'        => now(),
        ]);

        return $this->result('failed', (int) $row->id, $error);
    }

    /** @return array{outcome:string,activity_id:?int,message:string} */
    private function result(string $outcome, ?int $id, string $message): array
    {
        return ['outcome' => $outcome, 'activity_id' => $id, 'message' => $message];
    }

    // ------------------------------------------------------------------ prompts

    /** @return array<string,mixed>|null */
    private function decodeJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    private function systemPrompt(): string
    {
        $visuals = implode(', ', SimulationConfigValidator::VISUALS);

        return <<<PROMPT
You design ONE interactive Prayogshala (practical learning) activity for a school topic. Reply with a single JSON object and nothing else.

GROUNDING RULES
- Use only the topic, concepts and chapter passages you are given. Do not invent textbook quotations, page numbers, facts or measured results. Numbers a simulation needs that the source does not give are labelled model settings in the text, never stated as textbook facts.
- One activity must cover the topic's concepts together; do not make one activity per concept.
- If the material cannot support a faithful, interactive activity, reply {"insufficient": true, "reason": "..."} instead of guessing.
- This is a virtual simulation. Never claim a physical experiment happened. Say honestly where the model is simplified.
- Plain text only: no HTML, no markdown, no code. Write for the student's standard.

CHOOSE THE FORMAT FROM THE SUBJECT AND THE LEARNING GOAL (not every topic is a lab experiment)
- A quantity that responds to a control (temperature, angle, current, speed, length): simulation.type "variable_model".
- A calculation or estimate from stated values: "calculator".
- Putting stages, events or steps in the right order (a process, a timeline): "sequence".
- Sorting examples into groups (types, properties, causes): "classify".
- Deciding which details a model should keep: "relevance".

JSON SHAPE
{
 "title", "activity_type" (one of: experiment, activity, demonstration, investigation, exploration, map_activity, project),
 "description", "objective", "materials_required": [..], "procedure_steps": [..], "observation", "result",
 "safety_instructions", "teacher_instructions", "student_instructions", "estimated_minutes": number,
 "lab_config": {
   "version": 1,
   "simulation": {"type": ..., "params": {...}},
   "steps": {
     "mission": {"scenario", "task", "tags": [..]},
     "predict": {"question", "scenario": {optional control/state overrides}, "options": [{"id","label","when": <expression true when this option is right>}]},
     "do": {"instructions": [..], "materials": [..], "safety"},
     "observe": {"prompt"},
     "explain": {"text", "cases": [{"when": <expression>, "text"}]},
     "concept": {"text", "points": [..]},
     "apply": {"question", "options": [{"id","label","correct": true|false,"feedback"}]},   // exactly one correct
     "reflect": {"prompts": [..]}
   },
   "outcomes": [..], "teacher_script": [..]
 }
}

EXPRESSIONS (formulas and "when" conditions) use only numbers, identifiers, + - * /, parentheses, < > <= >= == != && || !. No functions. Identifiers must be facts the engine provides (below). At least one Predict option must be true for the predict scenario.

ENGINES AND THEIR params (ids: letters, digits, underscore, start with a letter)
1. variable_model: {"controls":[{"id","label","unit","kind":"slider"|"toggle","min","max","step","default"}] (1-6; toggle = min 0, max 1, step 1),
   "derived":[{"id","label","unit","formula","decimals"}] (0-10, may use controls and earlier derived),
   "visual":{"kind": one of [$visuals], "bind": ...},
   "observations":[{"when","text"}] (1-10, first true one is shown; text may use {controlId} and {derivedId} placeholders),
   "warnings":[{"when","text"}]}
   visual binds (each value is an expression): heating {"temperature","heat" (0..1),"boiling_point"}; particles {"energy" (0..1),"spacing" (0..1)};
   ray {"incidence" (degrees),"reflection" (degrees)}; circuit {"closed" (0|1),"brightness" (0..1)};
   rectangle {"width","height"}; bars {"bars":[{"label","value","max"}]}.
   Facts: every control id and derived id.
2. calculator: {"variables":[{"id","label","unit","kind":"measured"|"assumed","min","max","step","default"}], "outputs":[{"id","label","unit","formula","decimals","primary"}], "warnings":[{"when","text"}], "observation_template": "text with {outputId}"}. Facts: variable and output ids.
3. sequence: {"items":[{"id","label"}]} listed in the CORRECT order (2-10); the student reorders them. Facts: correct, total, in_order (1 when fully correct).
4. classify: {"categories":[{"id","label"}] (2-5), "items":[{"id","label","category": <category id>}] (2-12)}. Facts: correct, wrong, unassigned, total, complete.
5. relevance: {"subject","details":[{"id","label"}],"questions":[{"id","text","relevant":[detail ids]}],"default_question","observation_template"}. Facts: kept, missed, extra, complete, rel_<detailId>, kept_<detailId>.

The Observe text and the Explain cases must follow from the simulation's own facts so they can never contradict what the student sees.
PROMPT;
    }

    /** @param array<string,mixed> $ctx */
    private function userPrompt(array $ctx): string
    {
        $h = $ctx['hierarchy'];
        $lines = [
            'Standard: ' . ($h['standard_name'] ?? '?'),
            'Subject: ' . ($h['subject_name'] ?? '?'),
            'Chapter: ' . $h['chapter_name'],
            'Topic: ' . $ctx['topic']->name,
            'Topic description: ' . $ctx['description'],
            '',
            'Concepts of this topic (cover them all in the one activity):',
        ];
        foreach ($ctx['concepts'] as $c) {
            $lines[] = '- ' . $c['name'] . ($c['definition'] !== '' ? ': ' . mb_substr($c['definition'], 0, 400) : '');
        }
        if ($ctx['concepts'] === []) {
            $lines[] = '(none recorded)';
        }
        $lines[] = '';
        $lines[] = $ctx['excerpt'] !== ''
            ? "Passages from the chapter text that mention this topic:\n\"\"\"\n" . $ctx['excerpt'] . "\n\"\"\""
            : 'No matching passages were found in the chapter text.';

        return implode("\n", $lines);
    }
}
