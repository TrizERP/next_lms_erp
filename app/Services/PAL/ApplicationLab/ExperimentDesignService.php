<?php

namespace App\Services\PAL\ApplicationLab;

use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Support\Facades\Log;

/**
 * Application Lab, activity 2: Design Your Own Experiment.
 *
 * The student has chosen a scope. Nothing is generated for them. Inside it they
 *   1. pick the concept(s) their experiment is about and write the question it asks,
 *   2. choose the apparatus (each with a real photograph) and say what each is for,
 *   3. plan a fair test: what they change, what they measure, what stays the same,
 *      and the steps in order, each saying which apparatus it uses,
 *   4. predict what will happen and why, and say what they will record and any safety care,
 *   5. write a conclusion and how they would improve the experiment,
 * and then submit. The work is marked against the chapter's own concept descriptions.
 *
 * Storage is the same table (or cache) the previous creation activity used, kept apart by a
 * "d:" prefix on the scope key, so it works before the migration is applied.
 */
class ExperimentDesignService extends StudentCreationService
{
    private const DESIGN_RUBRIC = [
        ['criterion' => 'Applying the chapter concept', 'max_points' => 20],
        ['criterion' => 'Choice of apparatus', 'max_points' => 15],
        ['criterion' => 'Fair test and variables', 'max_points' => 20],
        ['criterion' => 'Clear step-by-step procedure', 'max_points' => 15],
        ['criterion' => 'Prediction and reasoning', 'max_points' => 15],
        ['criterion' => 'Connection to the chapter', 'max_points' => 15],
    ];

    private const PHASES = ['focus', 'apparatus', 'plan', 'predict', 'conclude', 'review'];

    /** The scope this activity stores under, apart from the other activities. */
    private function keyed(array $scope): array
    {
        $scope['key'] = 'd:'.$scope['key'];

        return $scope;
    }

    /** @return array<string,mixed> */
    public function current($studentId, int $chapterId, $subInstituteId, array $scope): array
    {
        return parent::current($studentId, $chapterId, $subInstituteId, $this->keyed($scope));
    }

    /**
     * @param  array<string,mixed>  $c
     * @param  array<int,string>  $scopeConcepts
     * @return array<string,mixed>
     */
    public function cleanCreation(array $c, array $scopeConcepts): array
    {
        $text = fn ($v, int $max = 1500) => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);
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

        $apparatus = [];
        $ids = [];
        foreach (array_slice((array) ($c['apparatus'] ?? []), 0, 14) as $a) {
            $a = (array) $a;
            $id = $slug($a['id'] ?? '');
            $name = $text($a['name'] ?? '', 40);
            if ($id === '' || $name === '' || isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            // A real picture is only ever a Wikimedia upload address (what the picture search returns).
            $image = $text($a['image'] ?? '', 400);
            $image = StudentPictureSearch::isAllowedUrl($image) ? $image : '';
            $icon = $text($a['icon'] ?? '', 40);
            $icon = preg_match('/^[\p{Extended_Pictographic}\x{200D}\x{FE0F}\x{1F3FB}-\x{1F3FF}\x{20E3}]{1,16}$/u', $icon) ? $icon : '';
            $apparatus[] = [
                'id' => $id, 'name' => $name, 'image' => $image,
                'credit' => $image === '' ? '' : $text($a['credit'] ?? '', 220),
                'icon' => $icon, 'purpose' => $text($a['purpose'] ?? '', 200),
            ];
        }

        $steps = [];
        foreach (array_slice((array) ($c['steps'] ?? []), 0, 14) as $i => $s) {
            $s = (array) $s;
            $uses = [];
            foreach (array_slice((array) ($s['uses'] ?? []), 0, 14) as $u) {
                $u = $slug($u);
                if (isset($ids[$u])) {
                    $uses[$u] = true;
                }
            }
            $steps[] = ['id' => 's'.($i + 1), 'text' => $text($s['text'] ?? '', 400), 'uses' => array_keys($uses)];
        }

        $v = (array) ($c['variables'] ?? []);
        $log = [];
        foreach (array_slice((array) ($c['log'] ?? []), -120) as $l) {
            if (is_string($l)) {
                $log[] = $text($l, 140);
            }
        }

        return [
            'focus' => array_keys($focus),
            'title' => $text($c['title'] ?? '', 80),
            'question' => $text($c['question'] ?? '', 300),
            'apparatus' => $apparatus,
            'variables' => ['change' => $text($v['change'] ?? '', 200), 'measure' => $text($v['measure'] ?? '', 200), 'same' => $text($v['same'] ?? '', 300)],
            'steps' => $steps,
            'prediction' => $text($c['prediction'] ?? ''),
            'reason' => $text($c['reason'] ?? ''),
            'record_plan' => $text($c['record_plan'] ?? '', 600),
            'safety' => $text($c['safety'] ?? '', 400),
            'conclusion' => $text($c['conclusion'] ?? ''),
            'improve' => $text($c['improve'] ?? '', 600),
            'log' => $log,
            'actions' => max(0, min(100000, (int) ($c['actions'] ?? 0))),
            'phase' => in_array($c['phase'] ?? '', self::PHASES, true) ? $c['phase'] : 'focus',
        ];
    }

    /**
     * What the student must have done before the work is marked, or null when it is enough.
     *
     * @param  array<string,mixed>  $d
     * @param  array<string,mixed>  $scope
     */
    public function missing(array $d, array $scope): ?string
    {
        $needed = $scope['type'] === 'chapter' ? min(2, count($scope['concepts'])) : 1;
        if (count($d['focus']) < max(1, $needed)) {
            return $needed > 1 ? 'Choose at least two concepts your experiment connects.' : 'Choose the concept your experiment is about first.';
        }
        if (mb_strlen($d['question']) < 10) {
            return 'Write the question your experiment asks.';
        }
        if (count($d['apparatus']) < 2) {
            return 'Choose at least two pieces of apparatus.';
        }
        $written = array_filter($d['steps'], fn ($s) => mb_strlen($s['text']) >= 5);
        if (count($written) < 3) {
            return 'Write at least three steps of your procedure.';
        }
        if ($d['prediction'] === '') {
            return 'Write your prediction.';
        }
        if ($d['conclusion'] === '') {
            return 'Write what you would conclude.';
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function save($studentId, int $chapterId, $subInstituteId, array $creation, array $scope, bool $fresh = false): array
    {
        return parent::save($studentId, $chapterId, $subInstituteId, $creation, $this->keyed($scope), $fresh);
    }

    /** @return array<string,mixed> */
    public function submit($studentId, int $chapterId, $subInstituteId, array $creation, array $scope): array
    {
        $scope = $this->keyed($scope);
        $rec = $this->load($studentId, $chapterId, $scope['key']);

        if ($rec === null || $rec['status'] === 'in_progress') {
            $clean = $this->cleanCreation($creation, $scope['concepts']);
            if (($why = $this->missing($clean, $scope)) !== null) {
                return $this->failure('incomplete', $why);
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
            $evaluation = $this->mark($rec['creation'], $scope, $studentId, $chapterId, $subInstituteId);
            if ($evaluation !== null) {
                $rec['evaluation'] = $evaluation;
                $rec['status'] = 'evaluated';
                $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);
            }
        }

        return ['ready' => true, 'record' => $this->present($rec)];
    }

    /**
     * What the student actually designed, stated plainly (computed here, not by the model).
     *
     * @param  array<string,mixed>  $d
     * @return array<string,mixed>
     */
    public function designFacts(array $d): array
    {
        $name = array_column($d['apparatus'], 'name', 'id');
        $used = [];
        $steps = [];
        $noApparatus = 0;
        foreach ($d['steps'] as $i => $s) {
            if ($s['text'] === '') {
                continue;
            }
            foreach ($s['uses'] as $u) {
                $used[$u] = true;
            }
            if ($s['uses'] === []) {
                $noApparatus++;
            }
            $steps[] = ['step' => count($steps) + 1, 'text' => $s['text'], 'uses' => array_map(fn ($u) => $name[$u] ?? '?', $s['uses'])];
        }

        return [
            'apparatus' => array_map(fn ($a) => ['name' => $a['name'], 'what_it_is_for' => $a['purpose']], $d['apparatus']),
            'apparatus_never_used_in_a_step' => array_values(array_map(fn ($a) => $a['name'], array_filter($d['apparatus'], fn ($a) => ! isset($used[$a['id']])))),
            'steps' => $steps,
            'steps_naming_no_apparatus' => $noApparatus,
            'variables' => $d['variables'],
        ];
    }

    /**
     * @param  array<string,mixed>  $d
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>|null
     */
    private function mark(array $d, array $scope, $studentId, int $chapterId, $subInstituteId): ?array
    {
        $ctx = $this->chapterContext->scopedContext($studentId, $chapterId, $subInstituteId, $scope);
        $chosen = array_values(array_filter($ctx['concepts'], fn ($c) => in_array($c['name'], $d['focus'], true)));
        $facts = $this->designFacts($d);

        $payload = [
            'chapter' => ['name' => $ctx['chapter'], 'subject' => $ctx['subject'], 'standard' => $ctx['standard'], 'summary' => $ctx['summary'], 'key_concepts' => $ctx['key_concepts']],
            'scope_the_student_chose' => $scope['type'] === 'chapter' ? 'the whole chapter, connecting several concepts' : 'only the concepts listed below',
            'concepts_the_student_says_it_tests' => $chosen,
            'experiment_the_student_designed' => [
                'title' => $d['title'], 'question' => $d['question'],
            ] + $facts + [
                'prediction' => $d['prediction'], 'reason_for_prediction' => $d['reason'],
                'what_they_will_record' => $d['record_plan'], 'safety_care' => $d['safety'],
                'conclusion_they_would_draw' => $d['conclusion'], 'how_they_would_improve_it' => $d['improve'],
            ],
        ];

        $system = "You mark one student's own experiment design for the Application Lab of a K-12 PAL system.\n"
            ."Nobody gave the student a task or an answer: they chose a scope, chose the concept(s) their experiment tests, wrote the question, picked the apparatus, planned a fair test with variables and steps, predicted the result and wrote a conclusion. They did NOT run it; you are marking the DESIGN and the reasoning.\n"
            ."Judge correctness against the chapter's own concept descriptions, which are given. There is no answer key.\n"
            ."Use the rubric exactly (criterion names unchanged).\n"
            ."Rules:\n"
            ."- Fair test: is exactly one thing changed, is one thing measured, and are the other conditions kept the same? An experiment that changes several things at once, or measures nothing, cannot score well on 'Fair test and variables'.\n"
            ."- Apparatus: is each piece needed and used in a step? Unused apparatus or steps that name none are weak design; missing apparatus the steps obviously need is a gap.\n"
            ."- The prediction needs a reason that uses the concept, not just a guess. The conclusion must follow from what the experiment would show; the student has not run it, so it is a conclusion they would expect to draw, and must be worded that way.\n"
            ."- Check the design against the concepts the student SAYS it tests. If it is really about a different idea, say so plainly. For a whole-chapter scope, the experiment should connect the chosen concepts, not treat them as separate lists.\n"
            ."- Be a fair, demanding marker. Near-full marks need a design that would really test the question, with a correct reason. A tidy design with a vague question is a good attempt, not a perfect one.\n"
            ."- Quote concrete evidence from their work (an apparatus, a step, a variable, a sentence they wrote).\n"
            ."- Name misconceptions specifically and kindly, and give one concrete way to improve the design. Be encouraging and age-appropriate. Do not simply state the right answer.\n"
            ."Return ONE JSON object only:\n"
            .'{"concept_match":"yes"|"partly"|"no" (does the experiment really test the concepts they chose?),"criteria":[{"criterion":string,"points_awarded":number,"comment":string}],"went_well":[string],"incorrect_or_unclear":[string],"misconceptions":[string],"improvements":[string],"feedback":string,"next_action":string,"evidence_statement":string}'
            ."\nRubric (points are maximums): ".json_encode(self::DESIGN_RUBRIC);

        try {
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
            $raw = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ], null, 3500, 0.3, true, 90);
        } catch (\Throwable $e) {
            Log::warning('Experiment design: marking failed', ['error' => $e->getMessage()]);

            return null;
        }

        $evaluation = $this->applicationLab->validateEvaluation($raw, self::DESIGN_RUBRIC);
        if ($evaluation === null) {
            Log::warning('Experiment design: marking unusable');

            return null;
        }
        $evaluation = $this->applyConceptMatch($evaluation, $raw, implode('; ', $d['focus']));
        $evaluation['evidence'] = [
            'You chose '.count($d['apparatus']).' piece(s) of apparatus and wrote '.count($facts['steps']).' step(s).',
            'You will change: '.($d['variables']['change'] ?: 'not stated').'. You will measure: '.($d['variables']['measure'] ?: 'not stated').'.',
            ($scope['type'] === 'chapter' ? 'Whole chapter, connecting: ' : 'Concept(s) you chose: ').implode('; ', $d['focus']).'.',
        ];
        $evaluation['model'] = $client->lastUsed()?->model;

        return $evaluation;
    }
}
