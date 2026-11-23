<?php

namespace App\Services\PAL\ApplicationLab;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Services\PAL\ExplanationLab\ExplanationLabService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Application Lab, activity 2: the student creates their own demonstration.
 *
 * The student first chose a scope (some concepts they have studied, or the whole
 * chapter). Nothing is generated for them. Inside that scope they pick which
 * concepts their activity shows, then design something that shows them:
 * components, arrows, notes, a drawing, in as many steps as they need. Then they
 * explain it. This class stores that work, per scope, and marks it.
 *
 * Marking is against the chapter's own concept descriptions, because there is no
 * answer key for something the student invented. A creation about a different idea
 * than the one chosen is capped by the server, whatever the model says.
 *
 * Storage: pal_application_creation when it exists, otherwise the cache for 30
 * days, so it works before the migration is applied.
 */
class StudentCreationService
{
    private const TABLE = 'pal_application_creation';

    private const CACHE_DAYS = 30;

    private const SHAPES = ['box', 'circle', 'pill', 'diamond'];

    private const SIZES = ['s', 'm', 'l'];

    private const SCENES = ['plain', 'grass', 'water', 'sky', 'bench', 'space', 'road'];

    private const COLOURS = ['#bae6fd', '#bbf7d0', '#fde68a', '#fecaca', '#e9d5ff', '#e2e8f0'];

    private const RUBRIC = [
        ['criterion' => 'Applying the chapter concept', 'max_points' => 25],
        ['criterion' => 'Correctness of the demonstration', 'max_points' => 20],
        ['criterion' => 'Independent design and creation', 'max_points' => 20],
        ['criterion' => 'Reasoning behind your decisions', 'max_points' => 15],
        ['criterion' => 'Explaining what you learned', 'max_points' => 10],
        ['criterion' => 'Connection to the chapter', 'max_points' => 10],
    ];

    public function __construct(
        protected ApplicationLabService $applicationLab,
        protected ExplanationLabService $chapterContext,
    ) {
    }

    // ------------------------------------------------------------------
    // Storage (keyed by student, chapter and scope)
    // ------------------------------------------------------------------

    protected function useTable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function cacheKey($studentId, int $chapterId, string $scopeKey): string
    {
        return 'pal_app_creation:'.(int) $studentId.':'.$chapterId.':'.$scopeKey;
    }

    /** @return array<string,mixed>|null */
    protected function load($studentId, int $chapterId, string $scopeKey): ?array
    {
        if ($this->useTable()) {
            $row = DB::table(self::TABLE)->where('student_id', (int) $studentId)
                ->where('chapter_id', $chapterId)->where('scope_key', $scopeKey)->orderByDesc('id')->first();

            return $row === null ? null : [
                'id' => (int) $row->id,
                'status' => $row->status,
                'scope' => ['key' => $row->scope_key] + (json_decode((string) $row->scope, true) ?: []),
                'creation' => $row->creation ? json_decode($row->creation, true) : null,
                'evaluation' => $row->evaluation ? json_decode($row->evaluation, true) : null,
                'submitted_at' => $row->submitted_at,
            ];
        }

        $rec = Cache::get($this->cacheKey($studentId, $chapterId, $scopeKey));

        return is_array($rec) ? $rec : null;
    }

    /** @param array<string,mixed> $rec */
    protected function store($studentId, int $chapterId, $subInstituteId, array $rec): array
    {
        $scopeKey = (string) $rec['scope']['key'];

        if ($this->useTable()) {
            $fields = [
                'scope_key' => $scopeKey,
                'scope' => json_encode(['type' => $rec['scope']['type'] ?? null, 'label' => $rec['scope']['label'] ?? null, 'concepts' => $rec['scope']['concepts'] ?? []], JSON_UNESCAPED_UNICODE),
                'status' => $rec['status'],
                'concept' => mb_substr(implode('; ', (array) ($rec['creation']['focus'] ?? [])), 0, 200) ?: null,
                'creation' => json_encode($rec['creation'], JSON_UNESCAPED_UNICODE),
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
        Cache::put($this->cacheKey($studentId, $chapterId, $scopeKey), $rec, now()->addDays(self::CACHE_DAYS));

        return $rec;
    }

    /** @return array<string,mixed>|null */
    protected function present(?array $rec): ?array
    {
        return $rec === null ? null : [
            'id' => (int) $rec['id'],
            'status' => $rec['status'],
            'creation' => $rec['creation'] ?? null,
            'evaluation' => $rec['evaluation'] ?? null,
            'submitted_at' => $rec['submitted_at'] ?? null,
        ];
    }

    /**
     * The concepts in the chosen scope (to pick the activity's focus from) and the saved creation for it.
     *
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>
     */
    public function current($studentId, int $chapterId, $subInstituteId, array $scope): array
    {
        $ctx = $this->chapterContext->scopedContext($studentId, $chapterId, $subInstituteId, $scope);

        return [
            'persisted' => $this->useTable(),
            'concepts' => array_map(fn ($c) => ['name' => $c['name'], 'description' => $c['description'], 'taught' => (bool) $c['taught']], $ctx['concepts']),
            'record' => $this->present($this->load($studentId, $chapterId, $scope['key'])),
        ];
    }

    // ------------------------------------------------------------------
    // Cleaning what the browser sends
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $c
     * @param  array<int,string>  $scopeConcepts
     * @return array<string,mixed>
     */
    public function cleanCreation(array $c, array $scopeConcepts): array
    {
        $text = fn ($v, int $max = 2000) => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);
        $unit = fn ($v) => is_numeric($v) ? max(0.0, min(1.0, round((float) $v, 3))) : 0.5;
        $slug = fn ($v) => preg_replace('/[^a-z0-9_]/i', '', mb_substr((string) $v, 0, 24));

        $byLower = [];
        foreach ($scopeConcepts as $n) {
            $byLower[mb_strtolower($n)] = $n;
        }
        $focus = [];
        foreach (array_slice((array) ($c['focus'] ?? []), 0, 10) as $f) {
            $k = mb_strtolower($text($f, 200));
            if (isset($byLower[$k])) {
                $focus[$byLower[$k]] = true;
            }
        }

        $frames = [];
        foreach (array_slice((array) ($c['frames'] ?? []), 0, 6) as $i => $f) {
            if (! is_array($f)) {
                continue;
            }
            $components = [];
            $ids = [];
            foreach (array_slice((array) ($f['components'] ?? []), 0, 25) as $p) {
                $id = $slug($p['id'] ?? '');
                $label = $text($p['text'] ?? '', 40);
                if ($id === '' || $label === '' || isset($ids[$id])) {
                    continue;
                }
                $ids[$id] = true;
                $shape = $text($p['shape'] ?? 'box', 10);
                $colour = $text($p['color'] ?? '', 10);
                // A picture is only ever an emoji: anything else the browser sends in its place is dropped.
                $icon = $text($p['icon'] ?? '', 40);
                // A real picture is only ever a Wikimedia upload address (what the picture search returns).
                $image = $text($p['image'] ?? '', 400);
                $image = StudentPictureSearch::isAllowedUrl($image) && ($p['kind'] ?? '') !== 'note' ? $image : '';
                $icon = preg_match('/^[\p{Extended_Pictographic}\x{200D}\x{FE0F}\x{1F3FB}-\x{1F3FF}\x{20E3}]{1,16}$/u', $icon) ? $icon : '';
                $components[] = [
                    'id' => $id, 'text' => $label,
                    'icon' => ($p['kind'] ?? '') === 'note' ? '' : $icon,
                    'image' => $image,
                    'credit' => $image === '' ? '' : $text($p['credit'] ?? '', 220),
                    'size' => in_array($p['size'] ?? '', self::SIZES, true) ? $p['size'] : 'm',
                    // The same thing keeps one identity from step to step, so it can be animated moving between them.
                    'origin' => ($o = $slug($p['origin'] ?? '')) !== '' ? $o : $id,
                    'shape' => in_array($shape, self::SHAPES, true) ? $shape : 'box',
                    'color' => in_array($colour, self::COLOURS, true) ? $colour : self::COLOURS[0],
                    'kind' => ($p['kind'] ?? '') === 'note' ? 'note' : 'part',
                    'x' => $unit($p['x'] ?? 0.5), 'y' => $unit($p['y'] ?? 0.5),
                ];
            }
            $arrows = [];
            foreach (array_slice((array) ($f['arrows'] ?? []), 0, 30) as $a) {
                $from = $slug($a['from'] ?? '');
                $to = $slug($a['to'] ?? '');
                if ($from !== $to && isset($ids[$from]) && isset($ids[$to])) {
                    $arrows[] = ['from' => $from, 'to' => $to, 'label' => $text($a['label'] ?? '', 60)];
                }
            }
            $ink = $text($f['ink'] ?? '', 160000);
            if ($ink === '' || strlen($ink) > 150000 || ! preg_match('#^data:image/(jpeg|png);base64,[A-Za-z0-9+/=]+$#', $ink)) {
                $ink = null;
            }
            $frames[] = [
                'id' => 'f'.($i + 1), 'title' => $text($f['title'] ?? '', 60), 'caption' => $text($f['caption'] ?? '', 500),
                'scene' => in_array($f['scene'] ?? '', self::SCENES, true) ? $f['scene'] : 'plain',
                'components' => $components, 'arrows' => $arrows, 'ink' => $ink,
            ];
        }
        if ($frames === []) {
            $frames[] = ['id' => 'f1', 'title' => '', 'caption' => '', 'scene' => 'plain', 'components' => [], 'arrows' => [], 'ink' => null];
        }

        $answers = [];
        foreach (['c1', 'c2', 'c3', 'c4', 'c5'] as $id) {
            $answers[$id] = $text($c['answers'][$id] ?? '');
        }
        $log = [];
        foreach (array_slice((array) ($c['log'] ?? []), -120) as $l) {
            if (is_string($l)) {
                $log[] = $text($l, 140);
            }
        }

        return [
            'focus' => array_keys($focus),
            'title' => $text($c['title'] ?? '', 80),
            'frames' => $frames,
            'answers' => $answers,
            'log' => $log,
            'actions' => max(0, min(100000, (int) ($c['actions'] ?? 0))),
            'phase' => in_array($c['phase'] ?? '', ['focus', 'build', 'explain', 'review'], true) ? $c['phase'] : 'focus',
        ];
    }

    // ------------------------------------------------------------------
    // Save and submit
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>
     */
    public function save($studentId, int $chapterId, $subInstituteId, array $creation, array $scope, bool $fresh = false): array
    {
        $existing = $this->load($studentId, $chapterId, $scope['key']);
        if ($existing !== null && $existing['status'] !== 'in_progress' && ! $fresh) {
            return $this->failure('locked', 'This creation has already been submitted.');
        }

        $clean = $this->cleanCreation($creation, $scope['concepts']);
        $rec = ($existing !== null && $existing['status'] === 'in_progress')
            ? $existing
            : ['id' => null, 'status' => 'in_progress', 'evaluation' => null];
        $rec['scope'] = $scope;
        $rec['creation'] = $clean;
        $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);

        return ['ready' => true, 'record' => $this->present($rec)];
    }

    /**
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>
     */
    public function submit($studentId, int $chapterId, $subInstituteId, array $creation, array $scope): array
    {
        $rec = $this->load($studentId, $chapterId, $scope['key']);

        if ($rec === null || $rec['status'] === 'in_progress') {
            $clean = $this->cleanCreation($creation, $scope['concepts']);
            $needed = $scope['type'] === 'chapter' ? min(2, count($scope['concepts'])) : 1;
            if (count($clean['focus']) < $needed) {
                return $this->failure('no_concept', $needed > 1
                    ? 'Choose at least two concepts your activity connects.'
                    : 'Choose the concept your activity is about first.');
            }
            $parts = array_sum(array_map(fn ($f) => count($f['components']), $clean['frames']));
            $drew = count(array_filter($clean['frames'], fn ($f) => $f['ink'] !== null)) > 0;
            if ($parts < 3 && ! ($drew && $parts >= 1)) {
                return $this->failure('too_little', 'Build your activity first: add at least three components (or a drawing with a component) so there is something to show.');
            }
            if (count(array_filter($clean['answers'], fn ($a) => $a !== '')) < 2) {
                return $this->failure('no_reflection', 'Answer at least two of the questions about what you made.');
            }
            $rec = $rec ?? ['id' => null];
            $rec['scope'] = $scope;
            $rec['creation'] = $clean;
            $rec['status'] = 'submitted';
            $rec['evaluation'] = null;
            $rec['submitted_at'] = now()->toDateTimeString();
            $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);
        }

        if (($rec['evaluation'] ?? null) === null) {
            $evaluation = $this->evaluate($rec['creation'], $scope, $studentId, $chapterId, $subInstituteId);
            if ($evaluation !== null) {
                $rec['evaluation'] = $evaluation;
                $rec['status'] = 'evaluated';
                $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);
            }
        }

        return ['ready' => true, 'record' => $this->present($rec)];
    }

    // ------------------------------------------------------------------
    // Facts and marking
    // ------------------------------------------------------------------

    /**
     * What the student actually built, stated plainly.
     *
     * @param  array<string,mixed>  $creation
     * @return array<string,mixed>
     */
    public function facts(array $creation): array
    {
        $frames = [];
        $parts = 0;
        $arrows = 0;
        $notes = 0;
        $drawings = 0;
        foreach ($creation['frames'] as $f) {
            $label = array_column($f['components'], 'text', 'id');
            $partsHere = array_values(array_filter($f['components'], fn ($c) => $c['kind'] === 'part'));
            $notesHere = array_values(array_filter($f['components'], fn ($c) => $c['kind'] === 'note'));
            $parts += count($partsHere);
            $notes += count($notesHere);
            $arrows += count($f['arrows']);
            $drawings += $f['ink'] !== null ? 1 : 0;
            $frames[] = [
                'step_title' => $f['title'], 'what_the_student_says_is_happening' => $f['caption'],
                'components' => array_map(fn ($c) => $c['text'].' ('.$c['shape'].')', $partsHere),
                'notes_the_student_wrote' => array_column($notesHere, 'text'),
                'arrows' => array_map(fn ($a) => ($label[$a['from']] ?? '?').' -> '.($label[$a['to']] ?? '?').($a['label'] !== '' ? ' ['.$a['label'].']' : ''), $f['arrows']),
                'drew_on_it' => $f['ink'] !== null,
            ];
        }

        return [
            'steps' => count($creation['frames']), 'components' => $parts, 'arrows' => $arrows, 'notes' => $notes,
            'drawings' => $drawings, 'actions' => $creation['actions'] ?? 0, 'frames' => $frames,
        ];
    }

    /**
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>|null
     */
    private function evaluate(array $creation, array $scope, $studentId, int $chapterId, $subInstituteId): ?array
    {
        $ctx = $this->chapterContext->scopedContext($studentId, $chapterId, $subInstituteId, $scope);
        $focusNames = $creation['focus'];
        $chosen = array_values(array_filter($ctx['concepts'], fn ($c) => in_array($c['name'], $focusNames, true)));
        $facts = $this->facts($creation);

        $prompts = [
            'c1' => 'What did you create?', 'c2' => 'Why did you design it this way?', 'c3' => 'What does it show about the concept(s) you chose?',
            'c4' => 'What did you learn from the chapter?', 'c5' => 'How could someone use your activity to understand the concept(s)?',
        ];
        $reflection = [];
        foreach ($prompts as $id => $q) {
            $reflection[] = ['question' => $q, 'student_answer' => $creation['answers'][$id] ?? ''];
        }

        $payload = [
            'chapter' => ['name' => $ctx['chapter'], 'subject' => $ctx['subject'], 'standard' => $ctx['standard'], 'summary' => $ctx['summary'], 'key_concepts' => $ctx['key_concepts']],
            'scope_the_student_chose' => $scope['type'] === 'chapter' ? 'the whole chapter, connecting several concepts' : 'only the concepts listed below',
            'concepts_the_student_says_it_shows' => $chosen,
            'student_created_this' => ['title' => $creation['title']] + $facts,
            'student_reflection' => $reflection,
        ];

        $system = "You mark one student's self-created demonstration for the Application Lab of a K-12 PAL system.\n"
            ."Nobody gave the student a task or an answer: they chose a scope (some concepts they had studied, or the whole chapter), chose which concepts their activity shows, and designed their own model, diagram, experiment or demonstration from components, arrows, notes and drawings, in as many steps as they wanted, then explained it. Mark what they actually made (summarised in student_created_this, computed by the system) AND their explanation.\n"
            ."Judge correctness against the chapter's own concept descriptions, which are given. There is no answer key.\n"
            ."Use the rubric exactly (criterion names unchanged).\n"
            ."Rules:\n"
            ."- Mark independence: did the student make real design decisions (what to include, how to connect it, how it changes across steps), or only put a few labels down? A very thin creation cannot show much, whatever the explanation says. A beautiful one with a wrong idea cannot score highly on correctness.\n"
            ."- Check the work against the concepts the student SAYS it shows (concepts_the_student_says_it_shows). If what they built and explained is really about a different idea, say so plainly. For a whole-chapter scope, the work should connect the chosen concepts meaningfully, not treat them as separate lists.\n"
            ."- Be a fair, demanding marker. Near-full marks need a creation that clearly and correctly shows the chosen concepts AND an explanation that says why. A short, tidy build with a correct idea is a good attempt, not a perfect one.\n"
            ."- You cannot see drawings; never describe one. You may say the student drew on a step.\n"
            ."- Quote concrete evidence from their work (a component, an arrow and its meaning, a note, a step caption, a sentence they wrote).\n"
            ."- Name misconceptions specifically and kindly, and give one concrete way to improve either the demonstration or the explanation. Be encouraging and age-appropriate. Do not simply state the right answer.\n"
            ."Return ONE JSON object only:\n"
            .'{"concept_match":"yes"|"partly"|"no" (does what they built and explained really show the concepts they chose?),"criteria":[{"criterion":string,"points_awarded":number,"comment":string}],"went_well":[string],"incorrect_or_unclear":[string],"misconceptions":[string],"improvements":[string],"feedback":string,"next_action":string,"evidence_statement":string}'
            ."\nRubric (points are maximums): ".json_encode(self::RUBRIC);

        try {
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
            $raw = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ], null, 3500, 0.3, true, 90);
        } catch (\Throwable $e) {
            Log::warning('Student creation: marking failed', ['error' => $e->getMessage()]);

            return null;
        }

        $evaluation = $this->applicationLab->validateEvaluation($raw, self::RUBRIC);
        if ($evaluation === null) {
            Log::warning('Student creation: marking unusable');

            return null;
        }
        $evaluation = $this->applyConceptMatch($evaluation, $raw, implode('; ', $focusNames));
        $evaluation['evidence'] = [
            "You built {$facts['components']} component(s) across {$facts['steps']} step(s).",
            "You made {$facts['arrows']} connection(s) and wrote {$facts['notes']} note(s)."
                .($facts['drawings'] > 0 ? ' You also drew on '.($facts['drawings'] === 1 ? 'one step' : $facts['drawings'].' steps').'.' : ''),
            ($scope['type'] === 'chapter' ? 'Whole chapter, connecting: ' : 'Concept(s) you chose: ').implode('; ', $focusNames).'.',
        ];
        $evaluation['model'] = $client->lastUsed()?->model;

        return $evaluation;
    }

    /**
     * A good demonstration of a different idea than the one chosen must not score as if it matched.
     * The model says how well the work fits the chosen concepts; the server caps the two criteria that
     * depend on it and recomputes the total, so the cap does not rest on the model remembering to apply it.
     *
     * @param  array<string,mixed>  $evaluation
     * @return array<string,mixed>
     */
    public function applyConceptMatch(array $evaluation, ?string $raw, string $concept): array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim((string) $raw));
        $match = strtolower((string) (json_decode($json, true)['concept_match'] ?? 'yes'));
        $factor = ['no' => 0.4, 'partly' => 0.7][$match] ?? null;
        if ($factor === null) {
            return $evaluation;
        }

        $awarded = 0.0;
        foreach ($evaluation['criteria'] as &$c) {
            if (in_array($c['criterion'], ['Applying the chapter concept', 'Connection to the chapter'], true)) {
                $c['points_awarded'] = min($c['points_awarded'], round($c['max_points'] * $factor, 1));
            }
            $awarded += $c['points_awarded'];
        }
        unset($c);
        $evaluation['points_awarded'] = $awarded;
        $evaluation['score_percent'] = (int) round($awarded / max(1.0, (float) $evaluation['max_points']) * 100);
        array_unshift($evaluation['incorrect_or_unclear'], $match === 'no'
            ? "What you built is about a different idea from the one you chose ({$concept}). Either choose the concept that matches your activity, or change the activity to show {$concept}."
            : "Your activity only partly shows what you chose ({$concept}). Make it clearer how each part relates to it.");

        return $evaluation;
    }

    /** @return array<string,mixed> */
    protected function failure(string $reason, string $message): array
    {
        return ['ready' => false, 'reason' => $reason, 'message' => $message];
    }
}
