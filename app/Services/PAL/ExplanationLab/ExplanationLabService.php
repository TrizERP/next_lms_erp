<?php

namespace App\Services\PAL\ExplanationLab;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Services\PAL\ApplicationLab\ApplicationLabService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Application Lab: one interactive demonstration per student and chapter, then
 * the student's own explanation, then marking.
 *
 * ---------------------------------------------------------------------------
 * THE ANSWER NEVER LEAVES THE SERVER
 * ---------------------------------------------------------------------------
 * The model returns a challenge AND a `key` (what a good demonstration looks
 * like). Only the challenge is ever sent to the browser; the key is stored with
 * the record and used when marking. A student cannot read the expected model
 * out of the page.
 *
 * ---------------------------------------------------------------------------
 * WHAT THE STUDENT DOES
 * ---------------------------------------------------------------------------
 * The model chooses the workspace that suits the chapter:
 *  - `board`: build a model, diagram, process or cycle by placing parts on a
 *    board (optionally into named areas), connecting them, labelling them and
 *    drawing on it;
 *  - `simulation`: vary controls and watch a cause-and-effect readout.
 * Both are generic engines in the front end; the content is generated.
 *
 * ---------------------------------------------------------------------------
 * STORAGE
 * ---------------------------------------------------------------------------
 * pal_explanation_lab_session when it exists; otherwise the cache for 30 days,
 * so the lab works before the migration is applied. Nothing else is written.
 */
class ExplanationLabService
{
    private const TABLE = 'pal_explanation_lab_session';

    private const FORMATS = ['board', 'simulation'];

    private const CACHE_DAYS = 30;

    private const RUBRIC = [
        ['criterion' => 'Conceptual understanding', 'max_points' => 20],
        ['criterion' => 'Correctness of the demonstration', 'max_points' => 20],
        ['criterion' => 'Reasoning behind your actions', 'max_points' => 20],
        ['criterion' => 'Observation and interpretation', 'max_points' => 15],
        ['criterion' => 'Connection to chapter concepts', 'max_points' => 15],
        ['criterion' => 'Explaining what you learned', 'max_points' => 10],
    ];

    public function __construct(private ApplicationLabService $applicationLab)
    {
    }

    // ------------------------------------------------------------------
    // Storage (table, or cache until the migration is applied)
    // ------------------------------------------------------------------

    private function useTable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheKey($studentId, int $chapterId, string $scopeKey): string
    {
        return 'pal_explanation_lab:'.(int) $studentId.':'.$chapterId.':'.$scopeKey;
    }

    /** @return array<string,mixed>|null the full record, key included */
    private function load($studentId, int $chapterId, string $scopeKey): ?array
    {
        if ($this->useTable()) {
            // The scope lives inside the stored challenge, so there is no column to query; the student's
            // most recent rows for the chapter are read and the first one for this scope wins.
            $rows = DB::table(self::TABLE)->where('student_id', (int) $studentId)
                ->where('chapter_id', $chapterId)->orderByDesc('id')->limit(40)->get();
            foreach ($rows as $row) {
                $challenge = json_decode($row->challenge, true);
                if (($challenge['scope']['key'] ?? null) !== $scopeKey) {
                    continue;
                }

                return [
                    'id' => (int) $row->id,
                    'status' => $row->status,
                    'challenge' => $challenge,
                    'workspace' => $row->workspace ? json_decode($row->workspace, true) : null,
                    'evaluation' => $row->evaluation ? json_decode($row->evaluation, true) : null,
                    'submitted_at' => $row->submitted_at,
                ];
            }

            return null;
        }

        $rec = Cache::get($this->cacheKey($studentId, $chapterId, $scopeKey));

        return is_array($rec) ? $rec : null;
    }

    /** @param array<string,mixed> $rec */
    private function store($studentId, int $chapterId, $subInstituteId, array $rec): array
    {
        if ($this->useTable()) {
            $fields = [
                'status' => $rec['status'],
                'challenge' => json_encode($rec['challenge'], JSON_UNESCAPED_UNICODE),
                'workspace' => isset($rec['workspace']) ? json_encode($rec['workspace'], JSON_UNESCAPED_UNICODE) : null,
                'evaluation' => isset($rec['evaluation']) ? json_encode($rec['evaluation'], JSON_UNESCAPED_UNICODE) : null,
                'submitted_at' => $rec['submitted_at'] ?? null,
                'evaluated_at' => ($rec['evaluation'] ?? null) ? now() : null,
                'updated_at' => now(),
            ];
            if (empty($rec['id'])) {
                $rec['id'] = DB::table(self::TABLE)->insertGetId($fields + [
                    'student_id' => (int) $studentId, 'sub_institute_id' => (int) $subInstituteId,
                    'chapter_id' => $chapterId, 'created_at' => now(),
                ]);
            } else {
                DB::table(self::TABLE)->where('id', $rec['id'])->where('student_id', (int) $studentId)->update($fields);
            }

            return $rec;
        }

        $rec['id'] = $rec['id'] ?? time();
        Cache::put($this->cacheKey($studentId, $chapterId, (string) ($rec['challenge']['scope']['key'] ?? 'none')), $rec, now()->addDays(self::CACHE_DAYS));

        return $rec;
    }

    /** What the browser may see: everything except the key. @return array<string,mixed>|null */
    private function present(?array $rec): ?array
    {
        if ($rec === null) {
            return null;
        }
        $challenge = $rec['challenge'];
        unset($challenge['key']);

        return [
            'id' => (int) $rec['id'],
            'status' => $rec['status'],
            'challenge' => $challenge,
            'workspace' => $rec['workspace'] ?? null,
            'evaluation' => $rec['evaluation'] ?? null,
            'submitted_at' => $rec['submitted_at'] ?? null,
        ];
    }

    /** @return array{persisted:bool,record:?array<string,mixed>} */
    public function current($studentId, int $chapterId, string $scopeKey): array
    {
        return ['persisted' => $this->useTable(), 'record' => $this->present($this->load($studentId, $chapterId, $scopeKey))];
    }

    // ------------------------------------------------------------------
    // Scope: which concepts an activity may be about
    // ------------------------------------------------------------------

    /**
     * What the student has studied in the chapter, and whether they may pick the whole chapter.
     * A concept counts as studied when Learn Concepts has taught it or they have practised it.
     * The whole chapter is open only when every concept in it has been studied.
     *
     * @return array<string,mixed>
     */
    public function scopeOptions($studentId, int $chapterId, $subInstituteId): array
    {
        // Every concept of the chapter, not the first few that fit in a prompt: "the whole chapter is studied" must mean all of it.
        $concepts = $this->context($studentId, $chapterId, $subInstituteId, null)['concepts'];
        $studied = array_values(array_filter($concepts, fn ($c) => $c['taught']));

        return [
            'studied' => array_map(fn ($c) => ['name' => $c['name'], 'description' => $c['description']], $studied),
            'total' => count($concepts),
            'chapter_complete' => count($concepts) > 0 && count($studied) === count($concepts),
        ];
    }

    /**
     * Turn what the browser asked for into a scope the server will stand behind.
     *
     * @param  array<string,mixed>  $input  ['type' => 'concepts'|'chapter', 'concepts' => [names]]
     * @return array<string,mixed> ['ok' => true, 'scope' => [...]] or ['ok' => false, 'reason', 'message']
     */
    public function resolveScope($studentId, int $chapterId, $subInstituteId, array $input): array
    {
        $options = $this->scopeOptions($studentId, $chapterId, $subInstituteId);
        $type = ($input['type'] ?? '') === 'chapter' ? 'chapter' : (($input['type'] ?? '') === 'concepts' ? 'concepts' : null);

        if ($type === null) {
            return ['ok' => false, 'reason' => 'no_scope', 'message' => 'Choose what your activity is about first.'];
        }

        if ($type === 'chapter') {
            if (! $options['chapter_complete']) {
                return ['ok' => false, 'reason' => 'chapter_incomplete', 'message' => 'The whole-chapter activity opens when you have studied every concept in the chapter. You can still choose the concepts you have studied.'];
            }
            $all = $this->context($studentId, $chapterId, $subInstituteId, null)['concepts'];

            return ['ok' => true, 'scope' => ['type' => 'chapter', 'key' => 'chapter', 'label' => 'Whole chapter', 'concepts' => array_column($all, 'name')]];
        }

        $byLower = [];
        foreach ($options['studied'] as $c) {
            $byLower[mb_strtolower($c['name'])] = $c['name'];
        }
        $chosen = [];
        foreach (array_slice((array) ($input['concepts'] ?? []), 0, 20) as $name) {
            $k = mb_strtolower(trim((string) (is_scalar($name) ? $name : '')));
            if (isset($byLower[$k])) {
                $chosen[$byLower[$k]] = true;
            }
        }
        $names = array_keys($chosen);
        if ($names === []) {
            return ['ok' => false, 'reason' => 'no_studied_concept', 'message' => 'Choose at least one concept you have studied.'];
        }
        if (count($names) > 10) {
            $names = array_slice($names, 0, 10);
        }
        $sorted = array_map('mb_strtolower', $names);
        sort($sorted);

        return ['ok' => true, 'scope' => [
            'type' => 'concepts', 'key' => 'c'.substr(md5(implode('|', $sorted)), 0, 12),
            'label' => count($names) === 1 ? $names[0] : count($names).' concepts', 'concepts' => $names,
        ]];
    }

    // ------------------------------------------------------------------
    // Generating the challenge
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    public function generate($studentId, int $chapterId, $subInstituteId, array $scope, bool $fresh = false): array
    {
        $existing = $this->load($studentId, $chapterId, $scope['key']);
        if ($existing !== null && ! $fresh) {
            return ['ready' => true, 'record' => $this->present($existing)];
        }

        $context = $this->scopedContext($studentId, $chapterId, $subInstituteId, $scope);
        if ($context['concepts'] === []) {
            return $this->failure('no_concepts', 'This chapter has no concepts yet, so there is nothing to demonstrate.');
        }

        $previous = $existing !== null ? [$existing['challenge']['title'] ?? ''] : [];
        $messages = $this->messages($context, array_filter($previous));

        try {
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
        } catch (AiConfigurationException $e) {
            Log::warning('Explanation lab: AI not configured', ['error' => $e->getMessage()]);

            return $this->failure('not_configured', 'The Application Lab AI is not configured for your school yet.');
        }

        $problem = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $send = $messages;
            if ($problem !== null) {
                $send[] = ['role' => 'user', 'content' => 'Your previous reply was rejected: '.$problem.' Return the corrected JSON object only.'];
            }
            try {
                $raw = $client->chat($send, null, 5500, 0.8, true, 100);
            } catch (\Throwable $e) {
                Log::warning('Explanation lab: generation failed', ['error' => $e->getMessage(), 'attempt' => $attempt]);
                $problem = 'The call failed.';

                continue;
            }

            [$challenge, $problem] = $this->validateChallenge($raw, $context);
            if ($challenge !== null) {
                $challenge['scope'] = ['type' => $scope['type'], 'key' => $scope['key'], 'label' => $scope['label'], 'concepts' => $scope['concepts']];
                $challenge['generated_at'] = now()->toIso8601String();
                $challenge['model'] = $client->lastUsed()?->model;
                $rec = $this->store($studentId, $chapterId, $subInstituteId, [
                    'id' => null, 'status' => 'in_progress', 'challenge' => $challenge, 'workspace' => null, 'evaluation' => null,
                ]);

                return ['ready' => true, 'record' => $this->present($rec)];
            }
        }

        return $this->failure('generation_failed', 'We could not build your challenge just now. Please try again.');
    }

    /**
     * The chapter context cut down to the scope: only the chosen concepts are shown to the model,
     * so it cannot build the activity on anything the student did not pick.
     *
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>
     */
    public function scopedContext($studentId, int $chapterId, $subInstituteId, array $scope): array
    {
        $ctx = $this->context($studentId, $chapterId, $subInstituteId);
        $names = array_map('mb_strtolower', $scope['concepts']);
        $ctx['concepts'] = array_values(array_filter($ctx['concepts'], fn ($c) => in_array(mb_strtolower($c['name']), $names, true)));
        if ($scope['type'] === 'concepts') {
            $ctx['key_concepts'] = array_values(array_filter($ctx['key_concepts'], fn ($k) => in_array(mb_strtolower($k['name']), $names, true)));
        }
        $ctx['scope'] = ['type' => $scope['type'], 'label' => $scope['label']];

        return $ctx;
    }

    /** @return array<string,mixed> */
    public function context($studentId, int $chapterId, $subInstituteId, ?int $limit = 30): array
    {
        $base = $this->applicationLab->buildContext($studentId, $chapterId, $subInstituteId);

        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        $keyConcepts = [];
        foreach ((array) json_decode((string) ($chapter->key_concepts ?? ''), true) as $k) {
            if (is_array($k) && ! empty($k['name'])) {
                $keyConcepts[] = ['name' => mb_substr((string) $k['name'], 0, 120), 'description' => mb_substr((string) ($k['description'] ?? ''), 0, 260)];
            }
        }

        $taught = array_column($base['taught_concepts'], 'status', 'name');
        $concepts = DB::table('lms_concept')->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', [(int) $subInstituteId, 0])->orderBy('id')->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get(['name', 'description', 'definition'])->map(fn ($c) => [
                'name' => (string) $c->name,
                'description' => mb_substr(trim((string) ($c->description ?: $c->definition)), 0, 220),
                'taught' => array_key_exists((string) $c->name, $taught),
                'status' => $taught[(string) $c->name] ?? null,
            ])->all();

        return [
            'chapter' => $base['chapter_name'],
            'subject' => $base['subject'],
            'standard' => $base['standard'],
            'summary' => mb_substr(trim((string) ($chapter->chapter_desc ?? '')), 0, 1500),
            'key_concepts' => array_slice($keyConcepts, 0, 12),
            'concepts' => $concepts,
            'difficulty' => $base['difficulty'],
            'weak' => $base['weak_names'],
            'mastered' => $base['mastered_names'],
        ];
    }

    /** @return array<int,array{role:string,content:string}> */
    private function messages(array $ctx, array $previousTitles): array
    {
        $system = <<<'PROMPT'
You design ONE interactive demonstration challenge for the Application Lab of a Personalized Adaptive Learning system.

The Application Lab is NOT a quiz, worksheet or guided tutorial. The student performs a chapter-specific task with their own hands, then explains what they did and why. Teach & Learn already taught the concept and Practice already drilled it; this step is where the student applies the concept: they independently demonstrate understanding in a practical task.

Choose the workspace format that fits THIS chapter's learning objectives (do not default to one):
- "board": the student builds a model, diagram, process or cycle by placing parts on a board, optionally into named areas, joining parts with arrows, adding their own labels and drawing. Use for structures, classifications, processes, cycles, sequences, cause-and-effect chains, choosing what to include or leave out of a model.
- "simulation": the student changes controls and watches a readout respond. Use for relationships between quantities (change one thing, see what happens).

Rules:
1. Build ONLY on the concepts in `concepts`. They are exactly what the student chose and has studied; use nothing else from the chapter. If `scope.type` is "concepts", the activity is about those concepts. If it is "chapter", build ONE integrated activity that meaningfully connects several of the listed concepts (name at least two in `concept_focus`), not a list of separate mini-tasks. Name the concepts in `concept_focus` EXACTLY as listed.
2. The student must DO the task. Never pre-complete it, never state the solution, the correct placements, the expected relationship or the answer anywhere student-facing (title, brief, goal, item labels, zone labels, reflection prompts). Item labels must be neutral names of things, not hints ("Step 3" is a hint; a bare noun is not). Zone labels may name areas but must not say which item belongs there.
3. Everything student-facing must suit the grade and be short and concrete.
4. Put what a good demonstration looks like ONLY in `key`. The student never sees `key`.
5. Do not repeat a title in `previous_titles`.
5a. Ground it in THIS chapter: use `chapter_summary`, `key_concepts` and the concept descriptions. The task must be about what the chapter actually teaches (its ideas, models, processes, relationships), not a generic science experiment. If the chapter is about ideas rather than apparatus (for example how scientists ask questions or build models), the activity is about those ideas, not an invented lab with liquids or chemicals.
5b. It must not be a quiz or a sorting exercise. Do not simply ask the student to drop labelled items into two or three buckets whose names reveal the answer. Prefer a free board (empty `zones`) where the student BUILDS something: a model, a diagram, a process or cycle, a cause-and-effect chain, a design. Use `zones` only when the chapter's concept really is classification, and then the area names must stay neutral. Include a few parts that do not belong where deciding what is relevant is itself the concept.
5c. Aim for 6 to 10 parts for a board, enough for real choices, and ask the student to connect them (set allow_arrows true) and to add their own labels.
6. Reflection prompts: 4 to 6, adapted to this activity (not generic). Together they must cover: what you did, why you chose to do it that way, what you observed, what you learned, which chapter concept it shows, and how your activity helps explain that concept.

Return ONE JSON object, nothing else:
{
 "title": string,
 "format": "board" | "simulation",
 "concept_focus": [string, 1 to 3 names from concepts],
 "brief": string (the situation, 2 to 4 sentences),
 "goal": string (what to demonstrate, phrased as a task, never the answer),
 "board": (only when format is board) {
    "items": [{"id": string, "label": string}] (4 to 12, unique ids like "i1"),
    "zones": [{"id": string, "label": string}] (0 to 6; empty list means a free board),
    "allow_arrows": boolean, "allow_labels": boolean, "allow_drawing": boolean
 },
 "simulation": (only when format is simulation) {
    "variables": [{"id": string, "label": string, "unit": string, "min": number, "max": number, "step": number, "default": number}] (1 to 4),
    "outputs": [{"id": string, "label": string, "unit": string, "min": number, "max": number, "baseline": number, "terms": [{"var": variable id, "weight": number (may be negative), "exponent": number between 0.5 and 3}]}] (1 to 3)
 },
 "reflection_prompts": [{"id": "r1", "prompt": string}],
 "key": {
    "demonstration_points": [string] (what a correct demonstration shows),
    "expected_placements": [{"item": item id, "zone": zone id}] (board with zones only),
    "expected_relations": [{"from": item id, "to": item id, "meaning": string}] (board with arrows only),
    "expected_order": [item ids] (when order or a cycle matters),
    "expected_relationships": [string] (simulation: the cause and effect the student should discover),
    "concept_points": [string] (what the explanation should say about the concepts),
    "misconceptions": [string] (typical wrong ideas to look for)
 }
}
Simulation outputs are computed as baseline + sum(weight * ((value - min) / (max - min)) ^ exponent) over its terms, clamped to the output's min and max, so choose numbers that make the readout meaningful across the whole range.
PROMPT;

        $user = [
            'chapter' => $ctx['chapter'], 'subject' => $ctx['subject'], 'standard' => $ctx['standard'],
            'chapter_summary' => $ctx['summary'], 'key_concepts' => $ctx['key_concepts'],
            'concepts' => $ctx['concepts'], 'student_difficulty' => $ctx['difficulty'],
            'weak_concepts' => $ctx['weak'], 'mastered_concepts' => $ctx['mastered'], 'scope' => $ctx['scope'] ?? null,
            'previous_titles' => array_values($previousTitles),
        ];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
    }

    // ------------------------------------------------------------------
    // Validating what the model returned
    // ------------------------------------------------------------------

    /** @return array{0: ?array<string,mixed>, 1: ?string} */
    public function validateChallenge(?string $raw, array $ctx): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [null, 'The reply was empty.'];
        }
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $d = json_decode($json, true);
        if (! is_array($d)) {
            $s = strpos($json, '{');
            $e = strrpos($json, '}');
            $d = ($s !== false && $e !== false) ? json_decode(substr($json, $s, $e - $s + 1), true) : null;
        }
        if (! is_array($d)) {
            return [null, 'The reply was not valid JSON.'];
        }

        $str = fn ($v, int $max = 600) => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $idOf = fn ($v) => preg_replace('/[^a-z0-9_]/i', '', mb_substr((string) $v, 0, 24));
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;

        $title = $str($d['title'] ?? '', 140);
        $brief = $str($d['brief'] ?? '', 900);
        $goal = $str($d['goal'] ?? '', 500);
        $format = $str($d['format'] ?? '', 20);
        if ($title === '' || $brief === '' || $goal === '') {
            return [null, 'title, brief and goal are required.'];
        }
        if (! in_array($format, self::FORMATS, true)) {
            return [null, 'format must be "board" or "simulation".'];
        }

        $names = [];
        foreach ($ctx['concepts'] as $c) {
            $names[mb_strtolower($c['name'])] = $c['name'];
        }
        $focus = [];
        foreach ((array) ($d['concept_focus'] ?? []) as $n) {
            $k = mb_strtolower($str($n, 200));
            if (isset($names[$k])) {
                $focus[$names[$k]] = true;
            }
        }
        if ($focus === []) {
            return [null, 'concept_focus must name concepts exactly as listed in concepts.'];
        }
        if (($ctx['scope']['type'] ?? '') === 'chapter' && count($focus) < min(2, count($names))) {
            return [null, 'A whole-chapter activity must connect at least two concepts: name them in concept_focus.'];
        }

        $out = ['title' => $title, 'format' => $format, 'concept_focus' => array_keys($focus), 'brief' => $brief, 'goal' => $goal];
        $key = (array) ($d['key'] ?? []);
        $itemIds = [];
        $zoneIds = [];

        if ($format === 'board') {
            $b = (array) ($d['board'] ?? []);
            $items = [];
            foreach ((array) ($b['items'] ?? []) as $it) {
                $id = $idOf($it['id'] ?? '');
                $label = $str($it['label'] ?? '', 60);
                if ($id !== '' && $label !== '' && ! isset($itemIds[$id])) {
                    $itemIds[$id] = true;
                    $items[] = ['id' => $id, 'label' => $label];
                }
            }
            if (count($items) < 3 || count($items) > 14) {
                return [null, 'board.items needs between 3 and 14 items with unique ids and labels.'];
            }
            $zones = [];
            foreach (array_slice((array) ($b['zones'] ?? []), 0, 8) as $z) {
                $id = $idOf($z['id'] ?? '');
                $label = $str($z['label'] ?? '', 60);
                if ($id !== '' && $label !== '' && ! isset($zoneIds[$id]) && ! isset($itemIds[$id])) {
                    $zoneIds[$id] = true;
                    $zones[] = ['id' => $id, 'label' => $label];
                }
            }
            $out['board'] = [
                'items' => $items, 'zones' => $zones,
                'allow_arrows' => ($b['allow_arrows'] ?? true) !== false,
                'allow_labels' => ($b['allow_labels'] ?? true) !== false,
                'allow_drawing' => ($b['allow_drawing'] ?? true) !== false,
            ];
        } else {
            $s = (array) ($d['simulation'] ?? []);
            $vars = [];
            $varIds = [];
            foreach (array_slice((array) ($s['variables'] ?? []), 0, 4) as $v) {
                $id = $idOf($v['id'] ?? '');
                $min = $num($v['min'] ?? null);
                $max = $num($v['max'] ?? null);
                if ($id === '' || isset($varIds[$id]) || $min === null || $max === null || $max <= $min) {
                    continue;
                }
                $step = $num($v['step'] ?? null);
                $step = ($step !== null && $step > 0) ? $step : ($max - $min) / 20;
                $def = $num($v['default'] ?? null);
                $varIds[$id] = true;
                $vars[] = [
                    'id' => $id, 'label' => $str($v['label'] ?? $id, 60), 'unit' => $str($v['unit'] ?? '', 16),
                    'min' => $min, 'max' => $max, 'step' => $step,
                    'default' => ($def !== null && $def >= $min && $def <= $max) ? $def : $min,
                ];
            }
            if ($vars === []) {
                return [null, 'simulation.variables needs at least one valid variable.'];
            }
            $outputs = [];
            foreach (array_slice((array) ($s['outputs'] ?? []), 0, 3) as $o) {
                $id = $idOf($o['id'] ?? '');
                $min = $num($o['min'] ?? null);
                $max = $num($o['max'] ?? null);
                if ($id === '' || $min === null || $max === null || $max <= $min) {
                    continue;
                }
                $terms = [];
                foreach (array_slice((array) ($o['terms'] ?? []), 0, 6) as $t) {
                    $var = $idOf($t['var'] ?? '');
                    $w = $num($t['weight'] ?? null);
                    if (isset($varIds[$var]) && $w !== null) {
                        $terms[] = ['var' => $var, 'weight' => $w, 'exponent' => max(0.5, min(3.0, $num($t['exponent'] ?? 1) ?? 1.0))];
                    }
                }
                if ($terms === []) {
                    continue;
                }
                $outputs[] = [
                    'id' => $id, 'label' => $str($o['label'] ?? $id, 60), 'unit' => $str($o['unit'] ?? '', 16),
                    'min' => $min, 'max' => $max, 'baseline' => $num($o['baseline'] ?? 0) ?? 0.0, 'terms' => $terms,
                ];
            }
            if ($outputs === []) {
                return [null, 'simulation.outputs needs at least one output driven by the variables.'];
            }
            $out['simulation'] = ['variables' => $vars, 'outputs' => $outputs];
        }

        $prompts = [];
        foreach (array_slice((array) ($d['reflection_prompts'] ?? []), 0, 8) as $p) {
            $text = $str(is_array($p) ? ($p['prompt'] ?? '') : $p, 300);
            if ($text !== '') {
                $prompts[] = ['id' => 'r'.(count($prompts) + 1), 'prompt' => $text];
            }
        }
        if (count($prompts) < 4) {
            return [null, 'reflection_prompts needs at least 4 prompts adapted to this activity.'];
        }
        $out['reflection_prompts'] = $prompts;

        // The key: kept only where it refers to real items and zones.
        $placements = [];
        foreach (array_slice((array) ($key['expected_placements'] ?? []), 0, 20) as $p) {
            $i = $idOf($p['item'] ?? '');
            $z = $idOf($p['zone'] ?? '');
            if (isset($itemIds[$i]) && isset($zoneIds[$z])) {
                $placements[] = ['item' => $i, 'zone' => $z];
            }
        }
        $relations = [];
        foreach (array_slice((array) ($key['expected_relations'] ?? []), 0, 20) as $r) {
            $f = $idOf($r['from'] ?? '');
            $t = $idOf($r['to'] ?? '');
            if (isset($itemIds[$f]) && isset($itemIds[$t])) {
                $relations[] = ['from' => $f, 'to' => $t, 'meaning' => $str($r['meaning'] ?? '', 160)];
            }
        }
        $order = array_values(array_filter(array_map($idOf, (array) ($key['expected_order'] ?? [])), fn ($i) => isset($itemIds[$i])));
        $lines = fn ($v) => array_slice(array_values(array_filter(array_map(fn ($x) => $str($x, 300), is_array($v) ? $v : []), fn ($x) => $x !== '')), 0, 8);

        $out['key'] = [
            'demonstration_points' => $lines($key['demonstration_points'] ?? []),
            'expected_placements' => $placements,
            'expected_relations' => $relations,
            'expected_order' => $order,
            'expected_relationships' => $lines($key['expected_relationships'] ?? []),
            'concept_points' => $lines($key['concept_points'] ?? []),
            'misconceptions' => $lines($key['misconceptions'] ?? []),
        ];
        if ($out['key']['demonstration_points'] === [] && $out['key']['expected_relationships'] === []) {
            return [null, 'key must say what a correct demonstration shows.'];
        }

        return [$out, null];
    }

    // ------------------------------------------------------------------
    // Saving and submitting the student's work
    // ------------------------------------------------------------------

    /**
     * Bound and filter what the browser sends. Ids not in the challenge are dropped.
     *
     * @param  array<string,mixed>  $w
     * @param  array<string,mixed>  $challenge
     * @return array<string,mixed>
     */
    public function cleanWorkspace(array $w, array $challenge): array
    {
        $text = fn ($v, int $max = 2000) => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);
        $unit = fn ($v) => is_numeric($v) ? max(0.0, min(1.0, round((float) $v, 3))) : 0.0;

        $itemIds = [];
        foreach ($challenge['board']['items'] ?? [] as $i) {
            $itemIds[$i['id']] = $i['label'];
        }
        $zoneIds = array_column($challenge['board']['zones'] ?? [], 'label', 'id');

        $placements = [];
        foreach (array_slice((array) ($w['placements'] ?? []), 0, 30) as $p) {
            $id = $text($p['id'] ?? '', 24);
            if (isset($itemIds[$id])) {
                $zone = $text($p['zone'] ?? '', 24);
                $placements[$id] = ['id' => $id, 'x' => $unit($p['x'] ?? 0), 'y' => $unit($p['y'] ?? 0), 'zone' => isset($zoneIds[$zone]) ? $zone : null];
            }
        }
        $extras = [];
        foreach (array_slice((array) ($w['extras'] ?? []), 0, 20) as $e) {
            $id = preg_replace('/[^a-z0-9_]/i', '', $text($e['id'] ?? '', 24));
            $label = $text($e['text'] ?? '', 60);
            if ($id !== '' && $label !== '') {
                $extras[] = ['id' => $id, 'text' => $label, 'x' => $unit($e['x'] ?? 0), 'y' => $unit($e['y'] ?? 0)];
            }
        }
        $valid = array_merge(array_keys($itemIds), array_column($extras, 'id'));
        $arrows = [];
        foreach (array_slice((array) ($w['arrows'] ?? []), 0, 30) as $a) {
            $f = $text($a['from'] ?? '', 24);
            $t = $text($a['to'] ?? '', 24);
            if ($f !== $t && in_array($f, $valid, true) && in_array($t, $valid, true)) {
                $arrows[] = ['from' => $f, 'to' => $t, 'label' => $text($a['label'] ?? '', 60)];
            }
        }

        $ink = $text($w['ink'] ?? '', 210000);
        if ($ink === '' || strlen($ink) > 200000 || ! preg_match('#^data:image/(jpeg|png);base64,[A-Za-z0-9+/=]+$#', $ink)) {
            $ink = null;
        }

        $trials = [];
        $vars = array_column($challenge['simulation']['variables'] ?? [], null, 'id');
        foreach (array_slice((array) ($w['trials'] ?? []), 0, 30) as $t) {
            $values = [];
            foreach ($vars as $id => $v) {
                $val = $t['values'][$id] ?? null;
                if (is_numeric($val)) {
                    $values[$id] = max($v['min'], min($v['max'], round((float) $val, 3)));
                }
            }
            if ($values !== []) {
                $trials[] = ['values' => $values, 'note' => $text($t['note'] ?? '', 400)];
            }
        }

        $answers = [];
        foreach ($challenge['reflection_prompts'] ?? [] as $p) {
            $answers[$p['id']] = $text($w['answers'][$p['id']] ?? '');
        }

        $log = [];
        foreach (array_slice((array) ($w['log'] ?? []), -120) as $l) {
            if (is_string($l)) {
                $log[] = $text($l, 140);
            }
        }

        return [
            'placements' => array_values($placements), 'extras' => $extras, 'arrows' => $arrows, 'ink' => $ink,
            'trials' => $trials, 'answers' => $answers, 'log' => $log,
            'actions' => max(0, min(100000, (int) ($w['actions'] ?? 0))),
            'phase' => in_array($w['phase'] ?? '', ['intro', 'work', 'explain', 'review'], true) ? $w['phase'] : 'intro',
        ];
    }

    /** @return array<string,mixed> */
    public function save($studentId, int $chapterId, $subInstituteId, array $workspace, string $scopeKey): array
    {
        $rec = $this->load($studentId, $chapterId, $scopeKey);
        if ($rec === null) {
            return $this->failure('not_found', 'Start the challenge first.');
        }
        if ($rec['status'] !== 'in_progress') {
            return $this->failure('locked', 'This challenge has already been submitted.');
        }
        $rec['workspace'] = $this->cleanWorkspace($workspace, $rec['challenge']);
        $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);

        return ['ready' => true, 'record' => $this->present($rec)];
    }

    /** @return array<string,mixed> */
    public function submit($studentId, int $chapterId, $subInstituteId, array $workspace, string $scopeKey): array
    {
        $rec = $this->load($studentId, $chapterId, $scopeKey);
        if ($rec === null) {
            return $this->failure('not_found', 'Start the challenge first.');
        }

        if ($rec['status'] === 'in_progress') {
            $clean = $this->cleanWorkspace($workspace, $rec['challenge']);
            $acted = $clean['placements'] !== [] || $clean['extras'] !== [] || $clean['ink'] !== null || $clean['trials'] !== [];
            if (! $acted) {
                return $this->failure('no_work', 'Do the activity on the workspace first, so there is something to look at.');
            }
            if (count(array_filter($clean['answers'], fn ($a) => $a !== '')) < 2) {
                return $this->failure('no_reflection', 'Answer at least two of the reflection questions before submitting.');
            }
            $rec['workspace'] = $clean;
            $rec['status'] = 'submitted';
            $rec['submitted_at'] = now()->toDateTimeString();
            $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);
        }

        if (($rec['evaluation'] ?? null) === null) {
            $evaluation = $this->evaluate($rec, $subInstituteId);
            if ($evaluation !== null) {
                $rec['evaluation'] = $evaluation;
                $rec['status'] = 'evaluated';
                $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);
            }
        }

        return ['ready' => true, 'record' => $this->present($rec)];
    }

    // ------------------------------------------------------------------
    // Evidence the server can state for itself, then marking
    // ------------------------------------------------------------------

    /**
     * Plain facts about what the student actually did, computed from the key.
     *
     * @return array<string,mixed>
     */
    public function evidence(array $challenge, array $ws): array
    {
        $key = $challenge['key'] ?? [];
        $facts = ['format' => $challenge['format'], 'actions' => $ws['actions'] ?? 0];

        if ($challenge['format'] === 'board') {
            $items = array_column($challenge['board']['items'], 'label', 'id');
            $zones = array_column($challenge['board']['zones'], 'label', 'id');
            $placed = array_column($ws['placements'], null, 'id');
            $facts['items_total'] = count($items);
            $facts['items_placed'] = count($placed);
            $facts['student_labels'] = array_column($ws['extras'], 'text');
            $facts['student_arrows'] = array_map(fn ($a) => [
                'from' => $items[$a['from']] ?? $this->extraText($ws, $a['from']),
                'to' => $items[$a['to']] ?? $this->extraText($ws, $a['to']),
                'label' => $a['label'],
            ], $ws['arrows']);
            $facts['drew_on_board'] = $ws['ink'] !== null;

            $exp = $key['expected_placements'] ?? [];
            if ($exp !== []) {
                $right = [];
                $wrong = [];
                $missing = [];
                foreach ($exp as $e) {
                    $p = $placed[$e['item']] ?? null;
                    if ($p === null || $p['zone'] === null) {
                        $missing[] = $items[$e['item']];
                    } elseif ($p['zone'] === $e['zone']) {
                        $right[] = $items[$e['item']];
                    } else {
                        $wrong[] = $items[$e['item']].' (student put it in "'.($zones[$p['zone']] ?? '?').'")';
                    }
                }
                $facts['placement_correct'] = count($right);
                $facts['placement_expected'] = count($exp);
                $facts['placed_correctly'] = $right;
                $facts['placed_wrongly'] = $wrong;
                $facts['not_placed_in_an_area'] = $missing;
            }

            $rel = $key['expected_relations'] ?? [];
            if ($rel !== []) {
                $made = 0;
                foreach ($rel as $r) {
                    foreach ($ws['arrows'] as $a) {
                        if (($a['from'] === $r['from'] && $a['to'] === $r['to']) || ($a['from'] === $r['to'] && $a['to'] === $r['from'])) {
                            $made++;
                            break;
                        }
                    }
                }
                $facts['relations_made'] = $made;
                $facts['relations_expected'] = count($rel);
            }

            $order = $key['expected_order'] ?? [];
            if (count($order) >= 3) {
                $pairs = 0;
                $ok = 0;
                for ($i = 0; $i < count($order) - 1; $i++) {
                    $a = $placed[$order[$i]] ?? null;
                    $b = $placed[$order[$i + 1]] ?? null;
                    if ($a && $b) {
                        $pairs++;
                        // Left-to-right, or top-to-bottom when the row is vertical.
                        $dx = $b['x'] - $a['x'];
                        $ok += ($dx > 0.02 || (abs($dx) <= 0.02 && $b['y'] > $a['y'])) ? 1 : 0;
                    }
                }
                $facts['order_pairs_checked'] = $pairs;
                $facts['order_pairs_in_expected_sequence'] = $ok;
            }
        } else {
            $vars = $challenge['simulation']['variables'];
            $facts['trials'] = count($ws['trials']);
            $explored = [];
            foreach ($vars as $v) {
                $vals = array_filter(array_map(fn ($t) => $t['values'][$v['id']] ?? null, $ws['trials']), 'is_numeric');
                $span = $vals === [] ? 0 : (max($vals) - min($vals)) / ($v['max'] - $v['min']);
                $explored[$v['label']] = $span >= 0.4 ? 'explored a wide range' : ($span > 0 ? 'changed only a little' : 'never changed');
            }
            $facts['variables'] = $explored;
            $facts['trial_notes'] = array_values(array_filter(array_column($ws['trials'], 'note')));
        }

        return $facts;
    }

    private function extraText(array $ws, string $id): string
    {
        foreach ($ws['extras'] as $e) {
            if ($e['id'] === $id) {
                return $e['text'];
            }
        }

        return $id;
    }

    /** @return array<string,mixed>|null */
    private function evaluate(array $rec, $subInstituteId): ?array
    {
        $challenge = $rec['challenge'];
        $ws = $rec['workspace'] ?? [];
        $facts = $this->evidence($challenge, $ws);

        $reflection = [];
        foreach ($challenge['reflection_prompts'] as $p) {
            $reflection[] = ['question' => $p['prompt'], 'student_answer' => $ws['answers'][$p['id']] ?? ''];
        }

        $payload = [
            'challenge' => [
                'title' => $challenge['title'], 'format' => $challenge['format'], 'brief' => $challenge['brief'], 'goal' => $challenge['goal'],
                'concepts' => $challenge['concept_focus'],
            ],
            'what_the_student_actually_did' => $facts,
            'student_reflection' => $reflection,
        ];

        $key = $challenge['key'];
        $system = "You mark one student's Application Lab demonstration for a K-12 PAL system.\n"
            ."The student performed an interactive task on a workspace, then explained it. Mark the ACTIONS (summarised in what_the_student_actually_did, computed by the system) AND the student's own reasoning in their reflection. Do not mark only the final result.\n"
            ."Use the rubric exactly (criterion names unchanged).\n"
            ."Key, for YOU only. Never copy it out as a model answer; guide the student towards the idea.\n"
            .json_encode($key, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Rules:\n"
            ."- What the student did is the system's record of their workspace. You cannot see a drawing, so never describe one; you may say they drew on the board.\n"
            ."- Quote concrete evidence from their actual work (an item they placed, an arrow they made, a label they wrote, a trial they ran, a sentence they wrote).\n"
            ."- If the actions were correct but the explanation is thin, say so, and the reverse. A correct arrangement without reasoning, or good words that do not match the actions, should not score highly.\n"
            ."- Name misconceptions specifically and kindly, and give one concrete way to improve the explanation. Be encouraging and age-appropriate. Do not simply state the right answer.\n"
            ."Return ONE JSON object only:\n"
            .'{"criteria":[{"criterion":string,"points_awarded":number,"comment":string}],"went_well":[string],"incorrect_or_unclear":[string],"misconceptions":[string],"improvements":[string],"feedback":string,"next_action":string,"evidence_statement":string}'
            ."\nRubric (points are maximums): ".json_encode(self::RUBRIC);

        try {
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
            $raw = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ], null, 3500, 0.3, true, 90);
        } catch (\Throwable $e) {
            Log::warning('Explanation lab: marking failed', ['error' => $e->getMessage()]);

            return null;
        }

        $evaluation = $this->applicationLab->validateEvaluation($raw, self::RUBRIC);
        if ($evaluation === null) {
            Log::warning('Explanation lab: marking unusable');

            return null;
        }
        $evaluation['evidence'] = $this->evidenceLines($facts);
        $evaluation['model'] = $client->lastUsed()?->model;

        return $evaluation;
    }

    /** Short statements about their own work that the student can read. @return array<int,string> */
    private function evidenceLines(array $f): array
    {
        $lines = [];
        if ($f['format'] === 'board') {
            $lines[] = "You placed {$f['items_placed']} of {$f['items_total']} parts on the board.";
            if (isset($f['placement_expected'])) {
                $lines[] = "{$f['placement_correct']} of {$f['placement_expected']} parts were in the area where they belong.";
            }
            if (isset($f['relations_expected'])) {
                $lines[] = "You made {$f['relations_made']} of the {$f['relations_expected']} important connections.";
            }
            if (! empty($f['student_labels'])) {
                $lines[] = 'You added your own labels: '.implode(', ', array_slice($f['student_labels'], 0, 5)).'.';
            }
            if (! empty($f['drew_on_board'])) {
                $lines[] = 'You drew on the board.';
            }
        } else {
            $lines[] = "You ran {$f['trials']} trial(s).";
            foreach ($f['variables'] as $label => $state) {
                $lines[] = "{$label}: {$state}.";
            }
        }

        return $lines;
    }

    /** @return array<string,mixed> */
    private function failure(string $reason, string $message): array
    {
        return ['ready' => false, 'reason' => $reason, 'message' => $message];
    }
}
