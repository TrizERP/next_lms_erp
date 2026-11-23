<?php

namespace App\Services\PAL\ApplicationLab;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Services\PAL\LabSpec\LabSpecEvaluator;
use App\Services\PAL\LabSpec\LabSpecRepository;
use Illuminate\Support\Facades\Log;

/**
 * Application Lab, activity 2 where the chapter has an experiment model: Design Your Own Experiment, as a virtual lab.
 *
 * The student is given a problem, not an experiment. They build the bench from a library of equipment, choose what
 * to change, measure and keep the same, run as many trials as they like in a simulation that responds to THEIR
 * set-up, and interpret what it shows. The AI never designs or runs anything: the server works out facts about the
 * design (was it a fair test, was anything repeated, was the right instrument there), recomputes every trial's
 * result from the settings chosen, and asks the model only to judge reasoning against the chapter and a key the
 * browser never receives. A few caps on the score are applied here, not left to the model.
 *
 * Stored like the other creations, apart from them by an "x:" prefix on the scope key.
 */
class ExperimentBuilderService extends StudentCreationService
{
    private const RUBRIC = [
        ['criterion' => 'Question and variables', 'max_points' => 15],
        ['criterion' => 'Choice of equipment', 'max_points' => 10],
        ['criterion' => 'Fair test and controls', 'max_points' => 20],
        ['criterion' => 'Trials and repetition', 'max_points' => 10],
        ['criterion' => 'Observations and data', 'max_points' => 10],
        ['criterion' => 'Interpretation of results', 'max_points' => 20],
        ['criterion' => 'Applying the chapter concept', 'max_points' => 15],
    ];

    /** When the lab provides the equipment (the student does not choose it), choosing equipment is not marked and its points go to the skills that remain. */
    private const RUBRIC_PROVIDED = [
        ['criterion' => 'Question and variables', 'max_points' => 20],
        ['criterion' => 'Fair test and controls', 'max_points' => 25],
        ['criterion' => 'Trials and repetition', 'max_points' => 10],
        ['criterion' => 'Observations and data', 'max_points' => 10],
        ['criterion' => 'Interpretation of results', 'max_points' => 20],
        ['criterion' => 'Applying the chapter concept', 'max_points' => 15],
    ];

    private const PHASES = ['challenge', 'question', 'design', 'variables', 'run', 'interpret', 'review'];

    private const MAX_TRIALS = 6;

    /** The school of the request being handled (a school can have its own approved version of a lab). */
    private int $school = 0;

    private function keyed(array $scope): array
    {
        $scope['key'] = 'x:'.$scope['key'];

        return $scope;
    }

    /** @return array<string,mixed> */
    public function current($studentId, int $chapterId, $subInstituteId, array $scope): array
    {
        $this->school = (int) $subInstituteId;
        $out = parent::current($studentId, $chapterId, $subInstituteId, $this->keyed($scope));
        $model = ExperimentModelCatalogue::forChapter($chapterId, $this->school);
        $out['model_id'] = $model['id'] ?? null;
        $out['spec_version'] = $model['version'] ?? null;

        return $out;
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
        $model = ExperimentModelCatalogue::get((string) ($c['modelId'] ?? ''), isset($c['specVersion']) ? (int) $c['specVersion'] : null, $this->school);
        if ($model === null) {
            return ['modelId' => '', 'focus' => [], 'phase' => 'challenge'];
        }

        return $this->cleanDesign($c, $scopeConcepts, $model);
    }

    /**
     * @param  array<string,mixed>  $c
     * @param  array<int,string>  $scopeConcepts
     * @param  array<string,mixed>  $model
     * @return array<string,mixed>
     */
    public function cleanDesign(array $c, array $scopeConcepts, array $model): array
    {
        $text = fn ($v, int $max = 1500) => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);
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

        $bench = [];
        $seen = [];
        foreach (array_slice((array) ($c['bench'] ?? []), 0, 20) as $b) {
            $b = (array) $b;
            $id = $slug($b['item'] ?? '');
            if (isset($model['items'][$id]) && ! isset($seen[$id])) {
                $seen[$id] = true;
                $bench[] = ['item' => $id, 'x' => $unit($b['x'] ?? 0.5), 'y' => $unit($b['y'] ?? 0.5)];
            }
        }
        // A lab that provides its equipment: the bench is every piece that belongs to the investigation, whatever the browser sent.
        $autoBench = (bool) ($c['autoBench'] ?? false);
        if ($autoBench) {
            $bench = [];
            foreach (array_keys($model['items']) as $n => $id) {
                if (! in_array($id, $model['irrelevant'], true)) {
                    $bench[] = ['item' => $id, 'x' => round(0.12 + (count($bench) % 6) * 0.15, 3), 'y' => round(0.3 + intdiv(count($bench), 6) * 0.4, 3)];
                }
            }
        }
        $benchIds = array_column($bench, 'item');

        $trials = [];
        foreach (array_slice((array) ($c['trials'] ?? []), 0, self::MAX_TRIALS) as $i => $t) {
            $t = (array) $t;
            $props = [];
            foreach ($model['items'] as $item) {
                foreach ($item['props'] as $key => $p) {
                    $v = $text(((array) ($t['props'] ?? []))[$key] ?? '', 20);
                    if (isset($p['options'][$v])) {
                        $props[$key] = $v;
                    }
                }
            }
            $ran = (bool) ($t['ran'] ?? false);
            $trial = ['id' => 't'.($i + 1), 'label' => 'Trial '.($i + 1), 'props' => $props, 'seconds' => null, 'ran' => $ran,
                // What the student wrote down from the instrument (their own observation), if they did.
                'recorded' => is_numeric($t['recorded'] ?? null) ? round((float) $t['recorded'], 2) : null];
            // The result is the model's, not the browser's: a number that does not match what the settings give is replaced.
            if ($ran && in_array($model['measure']['instrument'], $benchIds, true)) {
                $ideal = LabSpecEvaluator::value($model['spec'], ExperimentModelCatalogue::effective($model, $benchIds, $props));
                $given = is_numeric($t['seconds'] ?? null) ? (float) $t['seconds'] : 0.0;
                $trial['seconds'] = round(($given > 0 && abs($given - $ideal) <= 0.06 * $ideal) ? $given : $ideal, 1);
            }
            $trials[] = $trial;
        }
        if ($trials === []) {
            $trials[] = ['id' => 't1', 'label' => 'Trial 1', 'props' => [], 'seconds' => null, 'ran' => false, 'recorded' => null];
        }

        $keys = ExperimentModelCatalogue::propKeys($model);
        $iv = $slug($c['iv'] ?? '');
        $controls = [];
        foreach (array_slice((array) ($c['controls'] ?? []), 0, 12) as $k) {
            $k = $slug($k);
            if (in_array($k, $keys, true) && $k !== $iv) {
                $controls[$k] = true;
            }
        }
        $i = (array) ($c['interpretation'] ?? []);
        $hints = [];
        foreach (array_slice((array) ($c['hints'] ?? []), 0, 30) as $h) {
            $h = (array) $h;
            $hints[] = [
                'phase' => in_array($h['phase'] ?? '', self::PHASES, true) ? $h['phase'] : 'design',
                'topic' => $slug($h['topic'] ?? ''), 'level' => max(1, min(3, (int) ($h['level'] ?? 1))),
            ];
        }
        $log = [];
        foreach (array_slice((array) ($c['log'] ?? []), -120) as $l) {
            if (is_string($l)) {
                $log[] = $text($l, 140);
            }
        }

        return [
            'modelId' => $model['id'],
            'specVersion' => $model['version'],
            'recording' => (bool) ($c['recording'] ?? false),
            'autoBench' => $autoBench,
            'focus' => array_keys($focus),
            'question' => $text($c['question'] ?? '', 300),
            'iv' => in_array($iv, $keys, true) ? $iv : '',
            'dv' => isset($model['measures'][$slug($c['dv'] ?? '')]) ? $slug($c['dv']) : '',
            'controls' => array_keys($controls),
            'prediction' => $text($c['prediction'] ?? ''),
            'reason' => $text($c['reason'] ?? ''),
            'bench' => $bench,
            'trials' => $trials,
            'interpretation' => [
                'seen' => $text($i['seen'] ?? ''), 'claim' => $text($i['claim'] ?? ''), 'evidence' => $text($i['evidence'] ?? ''),
                'reasoning' => $text($i['reasoning'] ?? ''), 'limits' => $text($i['limits'] ?? '', 800),
            ],
            'hints' => $hints,
            'log' => $log,
            'actions' => max(0, min(100000, (int) ($c['actions'] ?? 0))),
            'phase' => in_array($c['phase'] ?? '', self::PHASES, true) ? $c['phase'] : 'challenge',
        ];
    }

    // ------------------------------------------------------------------
    // Facts (the same rules as the browser's designFacts)
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $d
     * @param  array<string,mixed>  $model
     * @return array<string,mixed>
     */
    public function designFacts(array $d, array $model): array
    {
        $benchIds = array_column($d['bench'], 'item');
        $ran = array_values(array_filter($d['trials'], fn ($t) => $t['ran']));
        $eff = array_map(fn ($t) => ExperimentModelCatalogue::effective($model, $benchIds, $t['props']), $ran);

        $changed = [];
        if (count($ran) >= 2) {
            foreach (ExperimentModelCatalogue::propKeys($model) as $k) {
                if (count(array_unique(array_map(fn ($e) => $e[$k] ?? '', $eff))) > 1) {
                    $changed[] = $k;
                }
            }
        }
        $sigs = array_map(fn ($e) => json_encode($e), $eff);
        $repeats = count($sigs) - count(array_unique($sigs));

        $groups = array_map(fn ($id) => $model['items'][$id]['group'], $benchIds);
        $instrument = $model['measure']['instrument'];
        $measured = array_values(array_filter($ran, fn ($t) => $t['seconds'] !== null));
        $recordedRight = count(array_filter($measured, fn ($t) => $t['recorded'] !== null && abs($t['recorded'] - $t['seconds']) <= 0.05 * max(abs($t['seconds']), 1e-9)));

        return [
            'placed' => $benchIds,
            'missing_groups' => array_values(array_diff($model['essential'], $groups)),
            'irrelevant_items' => count(array_intersect($benchIds, $model['irrelevant'])),
            'ran' => count($ran),
            'measured' => count(array_filter($ran, fn ($t) => $t['seconds'] !== null)),
            'changed' => $changed,
            'unfair' => array_values(array_filter($changed, fn ($k) => $k !== $d['iv'])),
            'broken_controls' => array_values(array_intersect($d['controls'], $changed)),
            'fair' => count($ran) >= 2 && count($changed) === 1,
            'repeats' => $repeats,
            'iv_claimed' => $d['iv'] !== '',
            'dv_claimed' => $d['dv'] !== '',
            'instrument_placed' => in_array($instrument, $benchIds, true),
            'hints_used' => count($d['hints']),
            'recording' => $d['recording'],
            'recorded' => count(array_filter($measured, fn ($t) => $t['recorded'] !== null)),
            'recorded_right' => $recordedRight,
        ];
    }

    /** What the student must have done before the work is marked, or null when it is enough. */
    public function missing(array $d, array $scope, array $facts): ?string
    {
        $needed = $scope['type'] === 'chapter' ? min(2, count($scope['concepts'])) : 1;
        if (count($d['focus']) < max(1, $needed)) {
            return $needed > 1 ? 'Choose at least two concepts your experiment connects.' : 'Choose the concept your experiment is about first.';
        }
        if (mb_strlen($d['question']) < 10) {
            return 'Write the question your experiment asks.';
        }
        if ($facts['missing_groups'] !== []) {
            return 'Your bench is missing something every experiment needs.';
        }
        if ($d['iv'] === '' || $d['dv'] === '') {
            return 'Say what you change and what you measure.';
        }
        if ($facts['measured'] < 2) {
            return 'Run at least two trials and measure each one.';
        }
        if ($d['recording'] && $facts['recorded_right'] < 2) {
            return 'Write down the reading of at least two trials, as the instrument showed it.';
        }
        if (mb_strlen($d['interpretation']['seen']) < 5 || mb_strlen($d['interpretation']['claim']) < 10) {
            return 'Describe what you saw and write your conclusion.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Save and submit
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    public function save($studentId, int $chapterId, $subInstituteId, array $creation, array $scope, bool $fresh = false): array
    {
        $this->school = (int) $subInstituteId;
        $creation = $this->forChapter($chapterId, $creation);

        return parent::save($studentId, $chapterId, $subInstituteId, $creation, $this->keyed($scope), $fresh);
    }

    /** A design can only be about the model written for this chapter. @param array<string,mixed> $creation @return array<string,mixed> */
    private function forChapter(int $chapterId, array $creation): array
    {
        $current = ExperimentModelCatalogue::forChapter($chapterId, $this->school);
        $creation['modelId'] = $current['id'] ?? '';
        // A student keeps the version they began on, as long as it is still one students may use.
        $asked = isset($creation['specVersion']) ? (int) $creation['specVersion'] : null;
        $keep = $asked !== null && $current !== null ? ExperimentModelCatalogue::get($current['id'], $asked, $this->school) : null;
        $creation['specVersion'] = $keep !== null && $keep['version'] === $asked ? $asked : ($current['version'] ?? null);

        return $creation;
    }

    /** @return array<string,mixed> */
    public function submit($studentId, int $chapterId, $subInstituteId, array $creation, array $scope): array
    {
        $this->school = (int) $subInstituteId;
        $scope = $this->keyed($scope);
        $current = ExperimentModelCatalogue::forChapter($chapterId, $this->school);
        if ($current === null) {
            return $this->failure('no_model', 'There is no experiment lab for this chapter.');
        }
        $rec = $this->load($studentId, $chapterId, $scope['key']);
        // Marked against the version the student ran, even if a newer one has been approved since.
        $began = (int) ($rec['creation']['specVersion'] ?? $creation['specVersion'] ?? 0);
        $model = $began > 0 ? (ExperimentModelCatalogue::get($current['id'], $began, $this->school) ?? $current) : $current;

        if ($rec === null || $rec['status'] === 'in_progress') {
            $clean = $this->cleanDesign($creation, $scope['concepts'], $model);
            if (($why = $this->missing($clean, $scope, $this->designFacts($clean, $model))) !== null) {
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
            $evaluation = $this->mark($rec['creation'], $model, $scope, $studentId, $chapterId, $subInstituteId);
            if ($evaluation !== null) {
                $rec['evaluation'] = $evaluation;
                $rec['status'] = 'evaluated';
                $rec = $this->store($studentId, $chapterId, $subInstituteId, $rec);
            }
        }

        return ['ready' => true, 'record' => $this->present($rec)];
    }

    // ------------------------------------------------------------------
    // Marking
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $d
     * @param  array<string,mixed>  $model
     * @return array<int,array<string,mixed>>
     */
    private function trialTable(array $d, array $model): array
    {
        $benchIds = array_column($d['bench'], 'item');
        $rows = [];
        foreach ($d['trials'] as $t) {
            if (! $t['ran']) {
                continue;
            }
            $eff = ExperimentModelCatalogue::effective($model, $benchIds, $t['props']);
            $settings = [];
            foreach ($model['items'] as $id => $item) {
                if (! in_array($id, $benchIds, true)) {
                    continue;
                }
                foreach (array_keys($item['props']) as $k) {
                    $settings[ExperimentModelCatalogue::propLabel($model, $k)] = ExperimentModelCatalogue::valueLabel($model, $k, $eff[$k]);
                }
            }
            $rows[] = [
                'trial' => $t['label'], 'settings' => $settings,
                'result_the_instrument_showed' => $t['seconds'] === null ? null : $t['seconds'].' '.$model['measure']['unit'],
                'reading_the_student_wrote_down' => $t['recorded'],
            ];
        }

        return $rows;
    }

    /**
     * Caps the server applies itself, whatever the model awarded, then the total is recomputed.
     *
     * @param  array<string,mixed>  $evaluation
     * @param  array<string,mixed>  $facts
     * @return array<string,mixed>
     */
    public function applyCaps(array $evaluation, array $facts, ?string $raw): array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim((string) $raw));
        $accuracy = strtolower((string) (json_decode($json, true)['claim_accuracy'] ?? 'yes'));

        $caps = [
            'Fair test and controls' => ! $facts['fair'] ? 0.4 : (! $facts['iv_claimed'] || $facts['broken_controls'] !== [] ? 0.6 : 1.0),
            'Choice of equipment' => ($facts['irrelevant_items'] >= 2 || ! $facts['instrument_placed']) ? 0.5 : 1.0,
            'Trials and repetition' => $facts['repeats'] === 0 ? 0.6 : 1.0,
            'Interpretation of results' => ['no' => 0.4, 'partly' => 0.75][$accuracy] ?? 1.0,
            // Reading an instrument and writing the number down is itself a skill; when the lab asked for it, it is marked.
            'Observations and data' => ! $facts['recording'] || $facts['measured'] === 0 ? 1.0 : ($facts['recorded_right'] === 0 ? 0.4 : ($facts['recorded_right'] < $facts['measured'] ? 0.8 : 1.0)),
        ];

        $awarded = 0.0;
        foreach ($evaluation['criteria'] as &$c) {
            $factor = $caps[$c['criterion']] ?? 1.0;
            $c['points_awarded'] = min($c['points_awarded'], round($c['max_points'] * $factor, 1));
            $awarded += $c['points_awarded'];
        }
        unset($c);
        $evaluation['points_awarded'] = $awarded;
        $evaluation['score_percent'] = (int) round($awarded / max(1.0, (float) $evaluation['max_points']) * 100);

        if (! $facts['fair']) {
            array_unshift($evaluation['incorrect_or_unclear'], $facts['ran'] >= 2
                ? 'Your trials differ in '.count($facts['changed']).' settings, so the result cannot tell you which one caused the difference. A fair test changes only one.'
                : 'You need at least two trials that differ in one thing to compare.');
        }

        return $evaluation;
    }

    /**
     * @param  array<string,mixed>  $d
     * @param  array<string,mixed>  $model
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>|null
     */
    private function mark(array $d, array $model, array $scope, $studentId, int $chapterId, $subInstituteId): ?array
    {
        $ctx = $this->chapterContext->scopedContext($studentId, $chapterId, $subInstituteId, $scope);
        $chosen = array_values(array_filter($ctx['concepts'], fn ($c) => in_array($c['name'], $d['focus'], true)));
        $facts = $this->designFacts($d, $model);
        $label = fn ($k) => ExperimentModelCatalogue::propLabel($model, (string) $k);

        $payload = [
            'chapter' => ['name' => $ctx['chapter'], 'subject' => $ctx['subject'], 'standard' => $ctx['standard'], 'summary' => $ctx['summary'], 'key_concepts' => $ctx['key_concepts']],
            'scope_the_student_chose' => $scope['type'] === 'chapter' ? 'the whole chapter, connecting several concepts' : 'only the concepts listed below',
            'concepts_the_student_says_it_tests' => $chosen,
            'problem_the_student_was_given' => $model['title'],
            'what_the_student_designed' => [
                'question' => $d['question'],
                'says_they_change' => $d['iv'] !== '' ? $label($d['iv']) : null,
                'says_they_measure' => $model['measures'][$d['dv']]['label'] ?? null,
                'says_they_keep_the_same' => array_map($label, $d['controls']),
                'equipment_on_bench' => $d['bench'] === [] ? [] : array_column($d['bench'], 'item'),
                'prediction' => $d['prediction'], 'reason_for_prediction' => $d['reason'],
            ],
            'trials_they_ran_and_what_the_simulation_gave' => $this->trialTable($d, $model),
            'computed_by_the_system' => [
                'was_it_a_fair_test_only_one_setting_differs' => $facts['fair'],
                'settings_that_actually_differ_between_trials' => array_map($label, $facts['changed']),
                'settings_that_differ_but_were_not_named_as_the_one_changed' => array_map($label, $facts['unfair']),
                'repeated_trials' => $facts['repeats'],
                'readings_the_student_wrote_down_that_match_the_instrument' => $facts['recording'] ? $facts['recorded_right'].' of '.$facts['measured'] : 'not asked',
                'items_on_the_bench_with_nothing_to_do_with_the_problem' => $facts['irrelevant_items'],
                'measuring_instrument_on_bench' => $facts['instrument_placed'],
                'hints_the_student_asked_for' => $facts['hints_used'],
            ],
            'student_interpretation' => $d['interpretation'],
            'FOR_THE_MARKER_ONLY_what_this_simulation_is_built_to_show' => $model['expected'],
            'chapter_concept_focus_for_this_problem' => $model['concept_focus'],
        ];

        $system = "You mark one student's self-designed virtual experiment for the Application Lab of a K-12 PAL system.\n"
            ."Nobody gave the student an experiment or a method: they were given a problem, built their own bench from a library of equipment (which included items that are not needed), chose what to change, measure and keep the same, ran their own trials in a simulation, and interpreted the results. You are marking THEIR decisions and reasoning, not the experiment.\n"
            ."Use the rubric exactly (criterion names unchanged).\n"
            ."Rules:\n"
            ."- The 'computed_by_the_system' facts are exact; trust them over the student's own words. Do not contradict them.\n"
            ."- Trial times are what the simulation gave for the student's own settings. Judge the student's interpretation against THOSE numbers (the data they actually have) and against the chapter. FOR_THE_MARKER_ONLY says what the model is built to show; use it to check the claim and the reasoning, and NEVER quote it, reveal it, or write the answer for the student.\n"
            ."- Interpretation: does the claim follow from the numbers they collected? Does the reasoning use an idea from the chapter correctly? A conclusion about a setting the student did not test, or one that goes beyond two trials, should be called out.\n"
            ."- Be a fair, demanding marker. Near-full marks need a fair test, a repeat, an accurate claim backed by their own numbers, and a correct reason. Do not give credit for things the student did not do.\n"
            ."- Quote concrete evidence from their work. Name misconceptions specifically and kindly, and give one concrete improvement. Be encouraging and age-appropriate. Never state a complete method for them.\n"
            ."Return ONE JSON object only:\n"
            .'{"concept_match":"yes"|"partly"|"no" (does the investigation really apply the concepts they chose?),"claim_accuracy":"yes"|"partly"|"no" (does their conclusion follow from their own trial results and is it scientifically sound?),"criteria":[{"criterion":string,"points_awarded":number,"comment":string}],"went_well":[string],"incorrect_or_unclear":[string],"misconceptions":[string],"improvements":[string],"feedback":string,"next_action":string,"evidence_statement":string}'
            ."\nRubric (points are maximums): ".json_encode(($d['autoBench'] ?? false) ? self::RUBRIC_PROVIDED : self::RUBRIC)
            .(($d['autoBench'] ?? false) ? "\nThe lab PROVIDED the equipment; the student did not choose it. Do not judge or mention equipment choice." : '');

        try {
            $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
            $raw = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ], null, 3500, 0.3, true, 90);
        } catch (\Throwable $e) {
            Log::warning('Experiment builder: marking failed', ['error' => $e->getMessage()]);

            return null;
        }

        $evaluation = $this->applicationLab->validateEvaluation($raw, ($d['autoBench'] ?? false) ? self::RUBRIC_PROVIDED : self::RUBRIC);
        if ($evaluation === null) {
            Log::warning('Experiment builder: marking unusable');

            return null;
        }
        $evaluation = $this->applyConceptMatch($evaluation, $raw, implode('; ', $d['focus']));
        $evaluation = $this->applyCaps($evaluation, $facts, $raw);
        $evaluation['evidence'] = [
            "You ran {$facts['ran']} trial(s), measured {$facts['measured']}, and repeated ".($facts['repeats'] > 0 ? 'a trial' : 'none').'.',
            $facts['fair']
                ? 'Your trials changed exactly one setting: '.$label($facts['changed'][0]).'.'
                : 'Settings that differed between your trials: '.($facts['changed'] === [] ? 'none' : implode(', ', array_map($label, $facts['changed']))).'.',
            ($facts['hints_used'] === 0 ? 'You did not need any hints.' : "You asked for {$facts['hints_used']} hint(s).")
                .' '.($scope['type'] === 'chapter' ? 'Whole chapter, connecting: ' : 'Concept(s): ').implode('; ', $d['focus']).'.',
        ];
        $evaluation['model'] = $client->lastUsed()?->model;

        return $evaluation;
    }
}
