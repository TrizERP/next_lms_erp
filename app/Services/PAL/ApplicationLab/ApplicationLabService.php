<?php

namespace App\Services\PAL\ApplicationLab;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Services\PAL\Adaptive\ConceptPerformanceAnalyzer;
use App\Services\PAL\Plan\MasteryOverviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Application Lab: one personalised practical activity per request.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS IS, AND WHAT IT DELIBERATELY IS NOT
 * ---------------------------------------------------------------------------
 * It READS what the journey already knows about the learner and asks the PAL
 * Intelligence model for one experiment / investigation / simulation / case
 * study. It writes nothing to the journey: no mastery, no taught_at, no
 * practice rows, no step completion. The only thing it remembers is the title
 * list it uses to avoid repeating itself, and that lives in the cache, not in
 * a table, so no migration is needed and nothing in another module can depend
 * on it.
 *
 * ---------------------------------------------------------------------------
 * "ONLY WHAT THE STUDENT HAS BEEN TAUGHT"
 * ---------------------------------------------------------------------------
 * A concept counts as taught when the engine stamped learner_node_state.taught_at
 * for one of its nodes (that is what the Learn screen's "I have read the lesson"
 * does), or when the learner has answered adaptive practice on it. Anything
 * else is withheld from the model; the model is told so, and the reply is
 * checked: if it applies no taught concept at all it is rejected, and a concept
 * outside the taught set that is not declared as an optional extension is
 * dropped from the applied list.
 *
 * Signals that only the browser holds (Explanation Lab completion, the last
 * adaptive-practice analysis) arrive as `client` hints. They are clamped and
 * only ever used as flavour for the prompt - never to widen the taught set.
 */
class ApplicationLabService
{
    private const HISTORY_LIMIT = 10;

    private const HISTORY_TTL_DAYS = 180;

    private const MODES = ['experiment', 'simulation', 'investigation', 'case_study', 'design_challenge', 'project'];

    public function __construct(
        private ConceptPerformanceAnalyzer $analyzer,
        private MasteryOverviewService $mastery,
    ) {
    }

    /**
     * @param  array<string,mixed>  $client  untrusted hints from the browser
     * @return array<string,mixed>
     */
    public function generate($studentId, int $chapterId, $subInstituteId, array $client = []): array
    {
        $context = $this->buildContext($studentId, $chapterId, $subInstituteId, $client);

        if ($context['taught_concepts'] === []) {
            return [
                'ready' => false,
                'reason' => 'no_learned_concepts',
                'message' => 'Application Lab builds an activity from concepts you have already learned. '
                    .'Open a concept in Learn Concepts or try Adaptive Practice first, then come back.',
            ];
        }

        $previous = $this->history($studentId, $chapterId);
        $messages = $this->messages($context, $previous);

        try {
            $client_ = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
        } catch (AiConfigurationException $e) {
            Log::warning('Application Lab: AI not configured', ['error' => $e->getMessage()]);

            return $this->failure('not_configured', 'The Application Lab AI is not configured for your school yet.');
        }

        $problem = null;

        // One retry: a malformed reply or one that ignores the taught concepts
        // is far more often a one-off than a systematic fault.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $send = $messages;
            if ($problem !== null) {
                $send[] = ['role' => 'user', 'content' => 'Your previous reply was rejected: '.$problem.' Return the corrected JSON object only.'];
            }

            try {
                $raw = $client_->chat($send, null, 4500, 0.8, true, 90);
            } catch (\Throwable $e) {
                Log::warning('Application Lab: AI call failed', ['error' => $e->getMessage(), 'attempt' => $attempt]);
                $problem = 'The call failed.';

                continue;
            }

            [$activity, $problem] = $this->validate($raw, $context, $previous);

            if ($activity !== null) {
                $activity['generated_at'] = now()->toIso8601String();
                $activity['model'] = $client_->lastUsed()?->model;
                $activity['basis'] = [
                    'taught_concepts' => array_map(fn ($c) => $c['name'], $context['taught_concepts']),
                    'weak_concepts' => $context['weak_names'],
                    'mastered_concepts' => $context['mastered_names'],
                    'difficulty' => $context['difficulty'],
                ];

                $this->remember($studentId, $chapterId, $activity);
                $record = $this->store($studentId, $chapterId, $subInstituteId, $activity);

                return ['ready' => true, 'activity' => $activity, 'persisted' => $record !== null, 'record' => $record];
            }
        }

        return $this->failure('generation_failed', 'We could not build an activity just now. Please try again.');
    }

    // ------------------------------------------------------------------
    // Evidence
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $client
     * @return array<string,mixed>
     */
    public function buildContext($studentId, int $chapterId, $subInstituteId, array $client = []): array
    {
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        $subject = ! empty($chapter->subject_id)
            ? DB::table('subject')->where('id', $chapter->subject_id)->value('subject_name')
            : null;
        $standard = ! empty($chapter->standard_id)
            ? DB::table('standard')->where('id', $chapter->standard_id)->value('name')
            : null;

        $adaptive = $this->analyzer->forChapter($studentId, $chapterId, $subInstituteId);
        $overview = $this->mastery->forChapter($studentId, $chapterId, $subInstituteId);

        $masteryById = [];
        foreach ($overview['concepts'] ?? [] as $row) {
            $masteryById[(int) $row['concept_id']] = $row;
        }

        $conceptIds = array_map(fn ($c) => (int) $c['concept_id'], $adaptive['concepts']);
        $nodeSignals = $this->nodeSignals($studentId, $conceptIds, $subInstituteId);

        $taught = [];
        $weak = [];
        $mastered = [];

        foreach ($adaptive['concepts'] as $concept) {
            $id = (int) $concept['concept_id'];
            $node = $nodeSignals[$id] ?? null;
            $m = $masteryById[$id] ?? null;
            $attempts = (int) ($concept['practice']['attempts'] ?? 0);

            $isTaught = ($node['taught'] ?? false) || $attempts > 0;
            if (! $isTaught) {
                continue;
            }

            $diagPct = $concept['diagnostic']['percentage'] ?? null;
            $pracPct = $attempts > 0 ? (float) $concept['practice']['percentage'] : null;
            $pMastery = $m['p_mastery'] ?? null;
            $stage = $m['stage'] ?? null;

            $isMastered = in_array($stage, ['mastered', 'retained', 'recall_due', 'awaiting_mastery_check'], true)
                || ($m['bkt_mastered'] ?? false);
            $score = $pracPct ?? ($diagPct !== null ? (float) $diagPct : ($pMastery !== null ? $pMastery * 100 : null));
            $isWeak = ! $isMastered && $score !== null && $score < 60;

            $taught[] = [
                'id' => $id,
                'name' => (string) $concept['name'],
                'diagnostic_pct' => $diagPct !== null ? round((float) $diagPct) : null,
                'practice_pct' => $pracPct !== null ? round($pracPct) : null,
                'practice_attempts' => $attempts,
                'mastery_pct' => $pMastery !== null ? round($pMastery * 100) : null,
                'stage' => $stage,
                'understanding_check_passed' => $node['cfu_passed'] ?? false,
                'understanding_check_attempts' => $node['cfu_attempts'] ?? 0,
                'status' => $isMastered ? 'mastered' : ($isWeak ? 'weak' : 'developing'),
            ];

            if ($isMastered) {
                $mastered[] = (string) $concept['name'];
            } elseif ($isWeak) {
                $weak[] = (string) $concept['name'];
            }
        }

        $notYetTaught = [];
        $takenIds = array_column($taught, 'id');
        foreach ($adaptive['concepts'] as $concept) {
            if (! in_array((int) $concept['concept_id'], $takenIds, true)) {
                $notYetTaught[] = (string) $concept['name'];
            }
        }

        return [
            'chapter_id' => $chapterId,
            'chapter_name' => $chapter->chapter_name ?? ('Chapter '.$chapterId),
            'subject' => $subject,
            'standard' => $standard,
            'diagnostic_level' => $adaptive['level'] ?? null,
            'has_diagnostic' => (bool) ($adaptive['has_diagnostic'] ?? false),
            'taught_concepts' => $taught,
            'not_yet_taught' => array_slice($notYetTaught, 0, 40),
            'weak_names' => $weak,
            'mastered_names' => $mastered,
            'difficulty' => $this->difficulty($taught),
            'client' => $this->clampClient($client),
        ];
    }

    /**
     * Taught flag and Understanding Check state per concept, one grouped read.
     *
     * @param  array<int,int>  $conceptIds
     * @return array<int,array{taught:bool,cfu_passed:bool,cfu_attempts:int}>
     */
    private function nodeSignals($studentId, array $conceptIds, $subInstituteId): array
    {
        if ($conceptIds === []) {
            return [];
        }

        try {
            $rows = DB::table('learner_node_state as s')
                ->join('pal_concept_nodes as n', 'n.id', '=', 's.node_id')
                ->where('s.student_id', (int) $studentId)
                ->whereIn('n.concept_id', $conceptIds)
                ->whereIn('n.sub_institute_id', [(int) $subInstituteId, 0])
                ->get(['n.concept_id', 's.taught_at', 's.cfu_passed_at', 's.cfu_attempts']);
        } catch (\Throwable $e) {
            Log::warning('Application Lab: node state unreadable', ['error' => $e->getMessage()]);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->concept_id;
            $out[$id] ??= ['taught' => false, 'cfu_passed' => false, 'cfu_attempts' => 0];
            $out[$id]['taught'] = $out[$id]['taught'] || $row->taught_at !== null;
            $out[$id]['cfu_passed'] = $out[$id]['cfu_passed'] || $row->cfu_passed_at !== null;
            $out[$id]['cfu_attempts'] += (int) $row->cfu_attempts;
        }

        return $out;
    }

    /** Challenge level from demonstrated mastery, not from the grade. */
    private function difficulty(array $taught): string
    {
        $scores = [];
        foreach ($taught as $c) {
            $s = $c['practice_pct'] ?? $c['diagnostic_pct'] ?? $c['mastery_pct'];
            if ($s !== null) {
                $scores[] = (float) $s;
            }
        }

        if ($scores === []) {
            return 'guided';
        }

        $avg = array_sum($scores) / count($scores);

        return $avg >= 80 ? 'challenging' : ($avg >= 55 ? 'standard' : 'guided');
    }

    /**
     * @param  array<string,mixed>  $client
     * @return array<string,mixed>
     */
    private function clampClient(array $client): array
    {
        $steps = [];
        foreach ((array) ($client['completed_steps'] ?? []) as $step) {
            if (is_string($step) && preg_match('/^[a-z_]{1,32}$/', $step)) {
                $steps[] = $step;
            }
        }

        $history = [];
        foreach (array_slice((array) ($client['practice_analysis'] ?? []), 0, 12) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $history[] = [
                'concept' => mb_substr(trim((string) ($row['conceptName'] ?? '')), 0, 120),
                'percentage' => is_numeric($row['percentage'] ?? null) ? max(0, min(100, (int) $row['percentage'])) : null,
                'gaps' => array_map(
                    fn ($g) => mb_substr(trim((string) $g), 0, 160),
                    array_slice(array_filter((array) ($row['learningGaps'] ?? []), 'is_string'), 0, 4)
                ),
            ];
        }

        return [
            'completed_steps' => array_slice(array_unique($steps), 0, 20),
            'explanation_lab_done' => in_array('explanation_lab', $steps, true),
            'practice_analysis' => $history,
        ];
    }

    // ------------------------------------------------------------------
    // Prompt
    // ------------------------------------------------------------------

    /** @return array<int,array{role:string,content:string}> */
    private function messages(array $ctx, array $previous): array
    {
        $system = <<<'PROMPT'
# Application Lab – Experiment Generator

You are an educational practical application and experiment designer for a Personalized Adaptive Learning (PAL) system.

Generate a personalized practical experiment, investigation, case study, simulation, or project for ONE student based on that student's actual PAL learning journey, given as JSON in the user message.

Requirements:
1. Build the activity only on concepts listed in `taught_concepts`. Prefer weak concepts the student has been taught, use mastered ones as supporting tools.
2. It must demonstrate application, not recall of definitions.
3. Match the `difficulty` field (guided = more scaffolding and a simpler setting; standard; challenging = open-ended, student designs part of it).
4. Connect it to a realistic situation suited to the student's grade and age.
5. The student independently thinks, performs, observes, records and explains.
6. NEVER give the complete answer, the expected result or the expected conclusion anywhere in the output. Ask, do not tell. Do not hint the final result in titles, steps or questions.
7. Give clear instructions, materials, steps and safety guidelines, using only cheap, common, safe household or classroom items.
8. Include observation fields, data recording and reasoning questions.
9. Evaluate understanding, practical application, reasoning, observation and conclusion.
10. If a physical experiment is unsafe, impractical or needs unavailable equipment, produce a safe simulation, investigation, design challenge or case study instead (set `mode` accordingly). Never require fire, mains electricity, sharp tools, chemicals beyond kitchen safe items, heat above warm tap water, or anything a child could not do safely with an adult nearby.
11. Do NOT use concepts that are not in `taught_concepts`. A concept from `not_yet_taught` may appear only inside `optional_extension`, clearly labelled as optional.
12. Do NOT repeat any activity listed in `previous_activities`; choose a different scenario, mode and angle.

Return ONE JSON object and nothing else, with exactly these keys:
{
  "title": string,
  "mode": one of "experiment" | "simulation" | "investigation" | "case_study" | "design_challenge" | "project",
  "real_life_scenario": string,
  "mission": string,
  "learning_objectives": [string, 3 to 5 items],
  "concepts_applied": [string, each EXACTLY a name from taught_concepts],
  "materials": [string] (empty list for a pure simulation or case study),
  "safety_guidelines": [string, at least 2],
  "procedure": [{"step": number, "instruction": string}],
  "data_collection": {
    "instructions": string,
    "columns": [string, 2 to 6 column headings],
    "rows": number (how many blank rows to give, 3 to 8)
  },
  "investigation_questions": [{"id": string, "question": string, "skill": "observation" | "reasoning" | "application"}],
  "explanation_task": string,
  "conclusion_task": string (what to conclude ABOUT, never the conclusion itself),
  "rubric": [{"criterion": "Understanding" | "Practical Application" | "Reasoning" | "Observation" | "Conclusion", "max_points": number, "levels": [{"label": string, "points": number, "descriptor": string}] (3 levels)}],
  "evidence_requirements": [string],
  "estimated_minutes": number,
  "optional_extension": string or null
}
PROMPT;

        $user = [
            'student_context' => [
                'subject' => $ctx['subject'],
                'standard' => $ctx['standard'],
                'chapter' => $ctx['chapter_name'],
                'chapter_diagnostic_level' => $ctx['diagnostic_level'],
                'difficulty' => $ctx['difficulty'],
                'taught_concepts' => array_map(fn ($c) => [
                    'name' => $c['name'],
                    'status' => $c['status'],
                    'concept_diagnostic_pct' => $c['diagnostic_pct'],
                    'adaptive_practice_pct' => $c['practice_pct'],
                    'adaptive_practice_attempts' => $c['practice_attempts'],
                    'understanding_check_passed' => $c['understanding_check_passed'],
                ], $ctx['taught_concepts']),
                'weak_concepts' => $ctx['weak_names'],
                'mastered_concepts' => $ctx['mastered_names'],
                'not_yet_taught' => $ctx['not_yet_taught'],
                'explanation_lab_completed' => $ctx['client']['explanation_lab_done'],
                'recent_practice_gaps' => $ctx['client']['practice_analysis'],
            ],
            'previous_activities' => array_map(fn ($p) => ['title' => $p['title'], 'mode' => $p['mode']], $previous),
        ];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * @return array{0: ?array<string,mixed>, 1: ?string} activity or the reason it was refused
     */
    private function validate(?string $raw, array $ctx, array $previous): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [null, 'The reply was empty.'];
        }

        $json = trim($raw);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            $start = strpos($json, '{');
            $end = strrpos($json, '}');
            $data = ($start !== false && $end !== false) ? json_decode(substr($json, $start, $end - $start + 1), true) : null;
        }

        if (! is_array($data)) {
            return [null, 'The reply was not valid JSON.'];
        }

        $str = fn ($v) => is_string($v) ? trim($v) : '';
        $list = fn ($v) => array_values(array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : '', is_array($v) ? $v : []), fn ($x) => $x !== ''));

        $title = $str($data['title'] ?? '');
        $mode = $str($data['mode'] ?? '');
        $mode = in_array($mode, self::MODES, true) ? $mode : 'investigation';

        foreach (['real_life_scenario', 'mission', 'explanation_task', 'conclusion_task'] as $key) {
            if ($str($data[$key] ?? '') === '') {
                return [null, "Missing \"{$key}\"."];
            }
        }

        if ($title === '') {
            return [null, 'Missing "title".'];
        }

        // Taught-concepts rule: keep only declared taught concepts.
        $taughtByLower = [];
        foreach ($ctx['taught_concepts'] as $c) {
            $taughtByLower[mb_strtolower($c['name'])] = $c['name'];
        }

        $applied = [];
        foreach ($list($data['concepts_applied'] ?? []) as $name) {
            $key = mb_strtolower($name);
            if (isset($taughtByLower[$key])) {
                $applied[$taughtByLower[$key]] = true;
            }
        }

        if ($applied === []) {
            return [null, 'concepts_applied must contain names taken exactly from taught_concepts.'];
        }

        foreach ($previous as $p) {
            if (mb_strtolower($p['title']) === mb_strtolower($title)) {
                return [null, 'The title repeats a previous activity; choose a different activity.'];
            }
        }

        $procedure = [];
        foreach ((array) ($data['procedure'] ?? []) as $i => $step) {
            $text = is_array($step) ? $str($step['instruction'] ?? '') : $str($step);
            if ($text !== '') {
                $procedure[] = ['step' => count($procedure) + 1, 'instruction' => $text];
            }
        }
        if (count($procedure) < 3) {
            return [null, 'The procedure needs at least 3 steps.'];
        }

        $safety = $list($data['safety_guidelines'] ?? []);
        if (count($safety) < 1) {
            return [null, 'Safety guidelines are required.'];
        }

        $questions = [];
        foreach ((array) ($data['investigation_questions'] ?? []) as $i => $q) {
            $text = is_array($q) ? $str($q['question'] ?? '') : $str($q);
            if ($text === '') {
                continue;
            }
            $skill = is_array($q) ? $str($q['skill'] ?? '') : '';
            $questions[] = [
                'id' => 'q'.(count($questions) + 1),
                'question' => $text,
                'skill' => in_array($skill, ['observation', 'reasoning', 'application'], true) ? $skill : 'reasoning',
            ];
        }
        if (count($questions) < 2) {
            return [null, 'At least 2 investigation questions are required.'];
        }

        $rubric = [];
        foreach ((array) ($data['rubric'] ?? []) as $row) {
            if (! is_array($row) || $str($row['criterion'] ?? '') === '') {
                continue;
            }
            $levels = [];
            foreach ((array) ($row['levels'] ?? []) as $level) {
                if (is_array($level) && $str($level['descriptor'] ?? '') !== '') {
                    $levels[] = [
                        'label' => $str($level['label'] ?? '') ?: 'Level',
                        'points' => (int) ($level['points'] ?? 0),
                        'descriptor' => $str($level['descriptor']),
                    ];
                }
            }
            if ($levels !== []) {
                $rubric[] = [
                    'criterion' => $str($row['criterion']),
                    'max_points' => max(1, (int) ($row['max_points'] ?? max(array_column($levels, 'points')))),
                    'levels' => $levels,
                ];
            }
        }
        if (count($rubric) < 3) {
            return [null, 'The rubric must cover understanding, practical application, reasoning, observation and conclusion.'];
        }

        $dc = is_array($data['data_collection'] ?? null) ? $data['data_collection'] : [];
        $columns = array_slice($list($dc['columns'] ?? []), 0, 6);
        if (count($columns) < 2) {
            return [null, 'data_collection.columns needs at least 2 headings.'];
        }

        $extension = $str($data['optional_extension'] ?? '');

        return [[
            'title' => $title,
            'mode' => $mode,
            'real_life_scenario' => $str($data['real_life_scenario']),
            'mission' => $str($data['mission']),
            'learning_objectives' => array_slice($list($data['learning_objectives'] ?? []), 0, 6),
            'concepts_applied' => array_keys($applied),
            'materials' => $list($data['materials'] ?? []),
            'safety_guidelines' => $safety,
            'procedure' => $procedure,
            'data_collection' => [
                'instructions' => $str($dc['instructions'] ?? ''),
                'columns' => $columns,
                'rows' => max(3, min(8, (int) ($dc['rows'] ?? 4))),
            ],
            'investigation_questions' => $questions,
            'explanation_task' => $str($data['explanation_task']),
            'conclusion_task' => $str($data['conclusion_task']),
            'rubric' => $rubric,
            'evidence_requirements' => $list($data['evidence_requirements'] ?? []),
            'estimated_minutes' => max(5, min(180, (int) ($data['estimated_minutes'] ?? 30))),
            'optional_extension' => $extension !== '' ? $extension : null,
        ], null];
    }

    // ------------------------------------------------------------------
    // No-repeat memory (cache only)
    // ------------------------------------------------------------------

    private function historyKey($studentId, int $chapterId): string
    {
        return 'pal_application_lab:'.(int) $studentId.':'.$chapterId;
    }

    /** @return array<int,array{title:string,mode:string,at:string}> */
    private function history($studentId, int $chapterId): array
    {
        $rows = Cache::get($this->historyKey($studentId, $chapterId), []);
        $rows = is_array($rows) ? $rows : [];

        // The table, when it exists, is the durable memory of what was already given.
        if ($this->persistent()) {
            $saved = DB::table(self::TABLE)
                ->where('student_id', (int) $studentId)->where('chapter_id', $chapterId)
                ->orderByDesc('id')->limit(self::HISTORY_LIMIT)->get(['title', 'mode', 'created_at']);
            foreach ($saved as $row) {
                $rows[] = ['title' => (string) $row->title, 'mode' => (string) $row->mode, 'at' => (string) $row->created_at];
            }
        }

        return $rows;
    }

    private function remember($studentId, int $chapterId, array $activity): void
    {
        $rows = $this->history($studentId, $chapterId);
        array_unshift($rows, [
            'title' => (string) $activity['title'],
            'mode' => (string) $activity['mode'],
            'at' => now()->toIso8601String(),
        ]);

        Cache::put(
            $this->historyKey($studentId, $chapterId),
            array_slice($rows, 0, self::HISTORY_LIMIT),
            now()->addDays(self::HISTORY_TTL_DAYS)
        );
    }

    // ------------------------------------------------------------------
    // Persistence: activity, working copy, submission, evaluation
    // ------------------------------------------------------------------

    private const TABLE = 'pal_application_lab_activity';

    /** False until the Application Lab migration has been applied. */
    public function persistent(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    private function present(?object $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'status' => $row->status,
            'activity' => json_decode($row->activity, true),
            'response' => $row->response ? json_decode($row->response, true) : null,
            'evaluation' => $row->evaluation ? json_decode($row->evaluation, true) : null,
            'submitted_at' => $row->submitted_at,
            'evaluated_at' => $row->evaluated_at,
        ];
    }

    private function store($studentId, int $chapterId, $subInstituteId, array $activity): ?array
    {
        if (! $this->persistent()) {
            return null;
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'student_id' => (int) $studentId,
            'sub_institute_id' => (int) $subInstituteId,
            'chapter_id' => $chapterId,
            'status' => 'draft',
            'mode' => $activity['mode'],
            'title' => mb_substr($activity['title'], 0, 255),
            'activity' => json_encode($activity, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->present(DB::table(self::TABLE)->find($id));
    }

    /** The learner's latest activity for the chapter, or null. */
    public function current($studentId, int $chapterId): array
    {
        if (! $this->persistent()) {
            return ['persisted' => false, 'record' => null];
        }

        $row = DB::table(self::TABLE)->where('student_id', (int) $studentId)
            ->where('chapter_id', $chapterId)->orderByDesc('id')->first();

        return ['persisted' => true, 'record' => $this->present($row)];
    }

    /** The learner's own row, or null. Ownership is checked here, never trusted from the client. */
    private function own($studentId, int $chapterId, int $id): ?object
    {
        return DB::table(self::TABLE)->where('id', $id)
            ->where('student_id', (int) $studentId)->where('chapter_id', $chapterId)->first();
    }

    /**
     * Clamp what the browser sends: bounded strings and table, nothing else.
     *
     * @return array<string,mixed>
     */
    private function cleanResponse(array $r): array
    {
        $text = fn ($v, int $max = 4000) => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);

        $table = [];
        foreach (array_slice((array) ($r['table'] ?? []), 0, 12) as $row) {
            $table[] = array_map(fn ($c) => $text($c, 300), array_slice((array) $row, 0, 8));
        }

        $answers = [];
        foreach (array_slice((array) ($r['answers'] ?? []), 0, 20, true) as $k => $v) {
            if (preg_match('/^[a-z0-9_]{1,16}$/i', (string) $k)) {
                $answers[(string) $k] = $text($v);
            }
        }

        $steps = [];
        foreach (array_slice((array) ($r['steps_done'] ?? []), 0, 40) as $n) {
            $steps[] = (int) $n;
        }

        return [
            'steps_done' => array_values(array_unique($steps)),
            'table' => $table,
            'answers' => $answers,
            'explanation' => $text($r['explanation'] ?? ''),
            'conclusion' => $text($r['conclusion'] ?? ''),
            'evidence_note' => $text($r['evidence_note'] ?? '', 1500),
        ];
    }

    /** @return array<string,mixed> */
    public function saveProgress($studentId, int $chapterId, int $id, array $response): array
    {
        if (! $this->persistent()) {
            return $this->failure('not_persistent', 'Saving to the server is not available yet.');
        }

        $row = $this->own($studentId, $chapterId, $id);
        if ($row === null) {
            return $this->failure('not_found', 'That activity was not found.');
        }
        if (in_array($row->status, ['submitted', 'evaluated'], true)) {
            return $this->failure('locked', 'This activity has already been submitted.');
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'in_progress',
            'response' => json_encode($this->cleanResponse($response), JSON_UNESCAPED_UNICODE),
            'started_at' => $row->started_at ?? now(),
            'updated_at' => now(),
        ]);

        return ['ready' => true, 'record' => $this->present(DB::table(self::TABLE)->find($id))];
    }

    /**
     * Submit once. A second submit never overwrites the first; if the first one
     * was saved but the evaluation failed, it only retries the evaluation.
     *
     * @return array<string,mixed>
     */
    public function submit($studentId, int $chapterId, int $id, array $response, $subInstituteId): array
    {
        if (! $this->persistent()) {
            return $this->failure('not_persistent', 'Submitting is not available yet.');
        }

        $locked = DB::transaction(function () use ($studentId, $chapterId, $id, $response) {
            $row = DB::table(self::TABLE)->where('id', $id)->where('student_id', (int) $studentId)
                ->where('chapter_id', $chapterId)->lockForUpdate()->first();
            if ($row === null) {
                return [null, 'not_found'];
            }
            if (in_array($row->status, ['submitted', 'evaluated'], true)) {
                return [$row, null];
            }

            $clean = $this->cleanResponse($response);
            $hasWork = $clean['explanation'] !== '' || $clean['conclusion'] !== ''
                || array_filter($clean['answers'], fn ($a) => $a !== '') !== [];
            if (! $hasWork) {
                return [null, 'empty'];
            }

            DB::table(self::TABLE)->where('id', $id)->update([
                'status' => 'submitted',
                'response' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                'submitted_at' => now(),
                'updated_at' => now(),
            ]);

            return [DB::table(self::TABLE)->find($id), null];
        });

        [$row, $error] = $locked;
        if ($row === null) {
            return $error === 'empty'
                ? $this->failure('empty', 'Write at least an answer, your explanation or your conclusion before submitting.')
                : $this->failure('not_found', 'That activity was not found.');
        }

        if ($row->status !== 'evaluated') {
            $this->evaluate($row, $subInstituteId);
        }

        return ['ready' => true, 'record' => $this->present(DB::table(self::TABLE)->find($id))];
    }

    /** Evaluate a submitted row from its own rubric and the student's actual words. */
    private function evaluate(object $row, $subInstituteId): void
    {
        $activity = json_decode($row->activity, true) ?: [];
        $response = json_decode((string) $row->response, true) ?: [];

        $questions = [];
        foreach ($activity['investigation_questions'] ?? [] as $q) {
            $questions[] = ['question' => $q['question'], 'skill' => $q['skill'], 'student_answer' => $response['answers'][$q['id']] ?? ''];
        }

        $payload = [
            'activity' => [
                'title' => $activity['title'] ?? '', 'mode' => $activity['mode'] ?? '',
                'mission' => $activity['mission'] ?? '', 'concepts_applied' => $activity['concepts_applied'] ?? [],
                'learning_objectives' => $activity['learning_objectives'] ?? [],
                'explanation_task' => $activity['explanation_task'] ?? '', 'conclusion_task' => $activity['conclusion_task'] ?? '',
                'rubric' => $activity['rubric'] ?? [],
            ],
            'submission' => [
                'steps_marked_done' => count($response['steps_done'] ?? []).' of '.count($activity['procedure'] ?? []),
                'data_table_columns' => $activity['data_collection']['columns'] ?? [],
                'data_table_rows' => $response['table'] ?? [],
                'questions_and_answers' => $questions,
                'explanation' => $response['explanation'] ?? '',
                'conclusion' => $response['conclusion'] ?? '',
                'evidence_note_self_reported' => $response['evidence_note'] ?? '',
            ],
        ];

        $system = <<<'PROMPT'
You evaluate one student's Application Lab submission for a K-12 PAL system. Judge ONLY what the student actually wrote, against the activity's own rubric.

Rules:
- Score each rubric criterion from the evidence in the submission. Empty or off-topic work earns low points; never give credit for effort you cannot see.
- You cannot verify that a physical experiment was performed. Only the written data, answers and explanation are evidence. The student's evidence note is self-reported and unverified; say so, and never state or imply that the student physically performed anything.
- Name incorrect observations, faulty reasoning and conceptual misunderstandings specifically, and say what to fix.
- Be encouraging, specific and age-appropriate. Do not reveal a full model answer; guide the student to improve it.
Return ONE JSON object only:
{"criteria":[{"criterion":string (exactly as in the rubric),"points_awarded":number,"comment":string}],
 "went_well":[string],"incorrect_or_unclear":[string],"misconceptions":[string],"improvements":[string],
 "feedback":string,"next_action":string,"evidence_statement":string}
PROMPT;

        try {
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
            $raw = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ], null, 3000, 0.3, true, 90);
        } catch (\Throwable $e) {
            Log::warning('Application Lab: evaluation failed', ['error' => $e->getMessage(), 'id' => $row->id]);

            return;
        }

        $evaluation = $this->validateEvaluation($raw, $activity['rubric'] ?? []);
        if ($evaluation === null) {
            Log::warning('Application Lab: evaluation unusable', ['id' => $row->id]);

            return;
        }

        $evaluation['model'] = $client->lastUsed()?->model;
        DB::table(self::TABLE)->where('id', $row->id)->update([
            'status' => 'evaluated',
            'evaluation' => json_encode($evaluation, JSON_UNESCAPED_UNICODE),
            'evaluated_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The score is recomputed here from the per-criterion points, clamped to the
     * rubric's own maximums; a total the model reports is never trusted.
     *
     * @return array<string,mixed>|null
     */
    public function validateEvaluation(?string $raw, array $rubric): ?array
    {
        if (! is_string($raw)) {
            return null;
        }
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $data = json_decode($json, true);
        if (! is_array($data) || ! is_array($data['criteria'] ?? null)) {
            return null;
        }

        $str = fn ($v, int $max = 1200) => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $list = fn ($v) => array_slice(array_values(array_filter(array_map(fn ($x) => $str($x, 400), is_array($v) ? $v : []), fn ($x) => $x !== '')), 0, 8);

        $given = [];
        foreach ($data['criteria'] as $c) {
            if (is_array($c)) {
                $given[mb_strtolower($str($c['criterion'] ?? ''))] = $c;
            }
        }

        // A reply that names the criteria slightly differently is still usable when it gave exactly one
        // per rubric row, in order. A reply with a different number of rows is not: we cannot tell which is which.
        $ordered = array_values(array_filter($data['criteria'], 'is_array'));
        $inOrder = count($ordered) === count($rubric);

        $rows = [];
        $awarded = 0.0;
        $max = 0.0;
        foreach (array_values($rubric) as $i => $r) {
            $c = $given[mb_strtolower($r['criterion'])] ?? ($inOrder ? $ordered[$i] : null);
            if ($c === null) {
                return null;
            }
            $cap = (float) $r['max_points'];
            $pts = max(0.0, min($cap, (float) ($c['points_awarded'] ?? 0)));
            $rows[] = ['criterion' => $r['criterion'], 'points_awarded' => $pts, 'max_points' => $cap, 'comment' => $str($c['comment'] ?? '')];
            $awarded += $pts;
            $max += $cap;
        }
        if ($rows === [] || $max <= 0) {
            return null;
        }

        return [
            'score_percent' => (int) round($awarded / $max * 100),
            'points_awarded' => $awarded,
            'max_points' => $max,
            'criteria' => $rows,
            'went_well' => $list($data['went_well'] ?? []),
            'incorrect_or_unclear' => $list($data['incorrect_or_unclear'] ?? []),
            'misconceptions' => $list($data['misconceptions'] ?? []),
            'improvements' => $list($data['improvements'] ?? []),
            'feedback' => $str($data['feedback'] ?? '', 2000),
            'next_action' => $str($data['next_action'] ?? ''),
            'evidence_statement' => $str($data['evidence_statement'] ?? '')
                ?: 'Evaluated from the written work only; any physical evidence is unverified.',
        ];
    }

    /** @return array<string,mixed> */
    private function failure(string $reason, string $message): array
    {
        return ['ready' => false, 'reason' => $reason, 'message' => $message];
    }
}
