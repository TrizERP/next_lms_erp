<?php

namespace App\Services\PAL\ApplicationLab;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Storage and marking for the 3D Virtual Lab (Application Lab).
 *
 * One row per student, chapter and lab. The browser runs the simulation and
 * sends back what happened; this class stores it, checks it is well formed and
 * bounded, and marks it. It writes nothing outside its own table.
 *
 * What is, and is not, verified: the simulation runs on the student's device, so
 * the trial records (temperatures, amounts, times) are the device's report, not
 * something the server can re-run. The marking says so. The notebook and the
 * answers are the student's own words and are marked as such.
 */
class VirtualLabService
{
    private const TABLE = 'pal_virtual_lab_session';

    private const MAX_WORLD_BYTES = 400000;

    private const MAX_DIAGRAM_BYTES = 200000;

    public function __construct(private ApplicationLabService $applicationLab)
    {
    }

    public function persistent(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Reading and writing the record
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function present(?object $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'status' => $row->status,
            'progress' => $row->progress ? json_decode($row->progress, true) : null,
            'evaluation' => $row->evaluation ? json_decode($row->evaluation, true) : null,
            'submitted_at' => $row->submitted_at,
        ];
    }

    /**
     * Every mission a student has worked on in a chapter, with where it stands. Used to choose the next experiment (a finished
     * mission is not offered again) and to show what they have done. Empty when the table is not there.
     *
     * @return array<int,array<string,mixed>> [format, key, status, scope_key, scope_label, score, finished_at]
     */
    public function history($studentId, int $chapterId): array
    {
        if (! $this->persistent()) {
            return [];
        }

        return DB::table(self::TABLE)->where('student_id', (int) $studentId)->where('chapter_id', $chapterId)->orderBy('id')
            ->get(['lab_id', 'status', 'evaluation', 'submitted_at'])
            ->map(fn ($r) => [
                'format' => 'mission', 'key' => (string) $r->lab_id, 'status' => (string) $r->status, 'scope_key' => null, 'scope_label' => null,
                'score' => $r->evaluation ? (json_decode($r->evaluation, true)['score_percent'] ?? null) : null, 'finished_at' => $r->submitted_at,
            ])->all();
    }

    /** @return array{persisted:bool,record:?array<string,mixed>} */
    public function current($studentId, int $chapterId, string $labId): array
    {
        if (! $this->persistent() || VirtualLabCatalogue::find($labId) === null) {
            return ['persisted' => false, 'record' => null];
        }

        $row = DB::table(self::TABLE)->where('student_id', (int) $studentId)
            ->where('chapter_id', $chapterId)->where('lab_id', $labId)->first();

        return ['persisted' => true, 'record' => $this->present($row)];
    }

    /** @return array<string,mixed> */
    public function save($studentId, int $chapterId, string $labId, $subInstituteId, array $progress): array
    {
        $lab = VirtualLabCatalogue::find($labId);
        if ($lab === null) {
            return $this->failure('unknown_lab', 'That lab was not found.');
        }
        if (! $this->persistent()) {
            return $this->failure('not_persistent', 'Saving to the server is not available yet.');
        }

        $clean = $this->cleanProgress($progress, $lab);
        $now = now();

        $row = DB::table(self::TABLE)->where('student_id', (int) $studentId)
            ->where('chapter_id', $chapterId)->where('lab_id', $labId)->first();

        if ($row !== null && in_array($row->status, ['submitted', 'evaluated'], true)) {
            return $this->failure('locked', 'This lab has already been submitted.');
        }

        if ($row === null) {
            $id = DB::table(self::TABLE)->insertGetId([
                'student_id' => (int) $studentId,
                'sub_institute_id' => (int) $subInstituteId,
                'chapter_id' => $chapterId,
                'lab_id' => $labId,
                'status' => 'in_progress',
                'stage' => $clean['stage'],
                'progress' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $id = (int) $row->id;
            DB::table(self::TABLE)->where('id', $id)->update([
                'stage' => $clean['stage'],
                'progress' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }

        return ['ready' => true, 'record' => $this->present(DB::table(self::TABLE)->find($id))];
    }

    /**
     * Submit once. A second call never overwrites the first; when the first was
     * saved but the marking failed, it only retries the marking.
     *
     * @return array<string,mixed>
     */
    public function submit($studentId, int $chapterId, string $labId, $subInstituteId, array $progress): array
    {
        $lab = VirtualLabCatalogue::find($labId);
        if ($lab === null) {
            return $this->failure('unknown_lab', 'That lab was not found.');
        }
        if (! $this->persistent()) {
            return $this->failure('not_persistent', 'Submitting is not available yet.');
        }

        $clean = $this->cleanProgress($progress, $lab);

        $result = DB::transaction(function () use ($studentId, $chapterId, $labId, $subInstituteId, $clean) {
            $row = DB::table(self::TABLE)->where('student_id', (int) $studentId)
                ->where('chapter_id', $chapterId)->where('lab_id', $labId)->lockForUpdate()->first();

            if ($row !== null && in_array($row->status, ['submitted', 'evaluated'], true)) {
                return [$row, null];
            }

            $answered = array_filter($clean['answers'], fn ($a) => is_array($a) ? $a !== [] : trim((string) $a) !== '');
            if ($answered === [] && trim($clean['conclusion']) === '') {
                return [null, 'empty'];
            }
            if ($clean['trials'] === []) {
                return [null, 'no_experiment'];
            }

            $now = now();
            $fields = [
                'status' => 'submitted',
                'stage' => 'feedback',
                'progress' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                'submitted_at' => $now,
                'updated_at' => $now,
            ];

            if ($row === null) {
                $id = DB::table(self::TABLE)->insertGetId($fields + [
                    'student_id' => (int) $studentId, 'sub_institute_id' => (int) $subInstituteId,
                    'chapter_id' => $chapterId, 'lab_id' => $labId, 'created_at' => $now,
                ]);
            } else {
                $id = (int) $row->id;
                DB::table(self::TABLE)->where('id', $id)->update($fields);
            }

            return [DB::table(self::TABLE)->find($id), null];
        });

        [$row, $error] = $result;
        if ($row === null) {
            return match ($error) {
                'empty' => $this->failure('empty', 'Answer at least one question or write your conclusion before submitting.'),
                'no_experiment' => $this->failure('no_experiment', 'Run the experiment in the lab first, so there is something to mark.'),
                default => $this->failure('not_found', 'That lab was not found.'),
            };
        }

        if ($row->status !== 'evaluated') {
            $this->evaluate($row, $lab, $subInstituteId);
        }

        return ['ready' => true, 'record' => $this->present(DB::table(self::TABLE)->find($row->id))];
    }

    // ------------------------------------------------------------------
    // Cleaning what the browser sends
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $p
     * @param  array<string,mixed>  $lab
     * @return array<string,mixed>
     */
    public function cleanProgress(array $p, array $lab): array
    {
        $text = fn ($v, int $max = 4000) => mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);

        $stage = $text($p['stage'] ?? 'intro', 20);
        $stage = in_array($stage, $lab['stages'], true) ? $stage : 'intro';

        $choice = $text($p['prediction']['choice'] ?? '', 20);
        $choice = in_array($choice, $lab['prediction_options'], true) ? $choice : null;

        $questions = [];
        foreach ($lab['questions'] as $q) {
            $questions[$q['id']] = $q;
        }
        $answers = [];
        foreach ((array) ($p['answers'] ?? []) as $id => $value) {
            $q = $questions[(string) $id] ?? null;
            if ($q === null) {
                continue;
            }
            if ($q['type'] === 'multi') {
                $answers[$q['id']] = array_values(array_intersect($q['options'], array_map('strval', array_filter((array) $value, 'is_scalar'))));
            } elseif ($q['type'] === 'choice') {
                $v = $text($value, 30);
                $answers[$q['id']] = in_array($v, $q['options'], true) ? $v : '';
            } else {
                $answers[$q['id']] = $text($value);
            }
        }

        $nb = (array) ($p['notebook'] ?? []);
        $table = [];
        foreach (array_slice((array) ($nb['table'] ?? []), 0, 8) as $row) {
            $table[] = array_map(fn ($c) => $text($c, 60), array_slice((array) $row, 0, 4));
        }
        $diagram = $text($nb['diagram'] ?? '', self::MAX_DIAGRAM_BYTES + 100);
        if ($diagram === '' || strlen($diagram) > self::MAX_DIAGRAM_BYTES
            || ! preg_match('#^data:image/(jpeg|png);base64,[A-Za-z0-9+/=]+$#', $diagram)) {
            $diagram = null;
        }

        $log = [];
        foreach (array_slice((array) ($p['log'] ?? []), -300) as $e) {
            if (! is_array($e)) {
                continue;
            }
            $data = [];
            foreach (array_slice((array) ($e['data'] ?? []), 0, 6, true) as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $data[mb_substr((string) $k, 0, 20)] = is_string($v) ? mb_substr($v, 0, 40) : $v;
                }
            }
            $log[] = [
                't' => is_numeric($e['t'] ?? null) ? round((float) $e['t'], 1) : 0,
                'type' => $text($e['type'] ?? '', 30),
                'obj' => isset($e['obj']) ? $text($e['obj'], 30) : null,
                'data' => $data,
            ];
        }

        $world = $p['world'] ?? null;
        if (! is_array($world) || strlen((string) json_encode($world)) > self::MAX_WORLD_BYTES) {
            $world = null;
        }

        return [
            'stage' => $stage,
            'prediction' => ['choice' => $choice, 'reason' => $text($p['prediction']['reason'] ?? '', 1000)],
            'world' => $world,
            'log' => $log,
            'notebook' => [
                'table' => $table,
                'observationA' => $text($nb['observationA'] ?? ''),
                'observationB' => $text($nb['observationB'] ?? ''),
                'comparison' => $text($nb['comparison'] ?? ''),
                'diagram' => $diagram,
            ],
            'answers' => $answers,
            'conclusion' => $text($p['conclusion'] ?? ''),
            'trials' => $this->trialsFrom($world, $lab['id']),
            'stats' => $this->statsFrom($world, $lab['id']),
        ];
    }

    /** @return array<int,array<string,mixed>> Trial records the simulation reported, numbers only. */
    private function trialsFrom(?array $world, string $labId = 'dissolving'): array
    {
        $out = [];
        if ($labId === 'ice_melting') {
            $num = fn ($v) => is_numeric($v) ? round((float) $v, 2) : null;
            foreach (array_slice((array) ($world['trials'] ?? []), 0, 4, true) as $id => $t) {
                if (! is_array($t) || ! is_numeric($t['startedAt'] ?? null)) {
                    continue;
                }
                $out[] = [
                    'beaker' => mb_substr((string) $id, 0, 20),
                    'environment' => in_array($t['env'] ?? null, ['cool', 'warm'], true) ? $t['env'] : null,
                    'temp_c' => $num($t['envTempC'] ?? null),
                    'water_ml' => null, 'sugar_g' => 0, 'stir_seconds' => 0, 'stir_intensity_sum' => 0,
                    'dissolve_seconds' => $num($t['meltSeconds'] ?? null),
                    'done' => is_numeric($t['doneAt'] ?? null),
                ];
            }

            return $out;
        }

        foreach (array_slice((array) ($world['trials'] ?? []), 0, 4, true) as $id => $t) {
            if (! is_array($t) || ! is_numeric($t['firstSugarAt'] ?? null)) {
                continue;
            }
            $num = fn ($v) => is_numeric($v) ? round((float) $v, 2) : null;
            $out[] = [
                'beaker' => mb_substr((string) $id, 0, 20),
                'temp_c' => $num($t['tempC'] ?? null),
                'water_ml' => $num($t['waterMl'] ?? null),
                'sugar_g' => $num($t['sugarG'] ?? 0),
                'stir_seconds' => $num($t['stirSeconds'] ?? 0),
                'stir_intensity_sum' => $num($t['stirIntensitySum'] ?? 0),
                'dissolve_seconds' => $num($t['dissolveSeconds'] ?? null),
                'done' => is_numeric($t['doneAt'] ?? null),
            ];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function statsFrom(?array $world, string $labId = 'dissolving'): array
    {
        $s = (array) ($world['stats'] ?? []);
        $num = fn ($v) => is_numeric($v) ? round((float) $v, 1) : 0;

        if ($labId === 'ice_melting') {
            return [
                'wrong_placements' => (int) array_sum(array_map('intval', array_slice((array) ($s['wrong'] ?? []), 0, 20))),
                'first_try_setup' => is_bool($s['firstTrySetup'] ?? null) ? $s['firstTrySetup'] : null,
                'grabs' => array_sum(array_map('intval', array_slice((array) ($s['grabs'] ?? []), 0, 20))),
                'clicked_to_learn' => array_sum(array_map('intval', array_slice((array) ($s['clicks'] ?? []), 0, 20))),
            ];
        }

        return [
            'spilled_ml' => $num($s['spilledMl'] ?? 0),
            'spilled_sugar_g' => $num($s['spilledSugarG'] ?? 0),
            'dry_sugar_drops' => (int) ($s['dryDrops'] ?? 0),
            'temps_checked' => array_map($num, array_slice((array) ($s['checkedTempC'] ?? []), 0, 4, true)),
            'grabs' => array_sum(array_map('intval', (array) ($s['grabs'] ?? []))),
        ];
    }

    // ------------------------------------------------------------------
    // Objective checks (no model involved)
    // ------------------------------------------------------------------

    /**
     * @param  array<int,array<string,mixed>>  $trials
     * @return array<int,array{id:string,ok:bool,text:string}>
     */
    public function experimentChecks(array $trials, string $labId = 'dissolving', array $stats = []): array
    {
        if ($labId === 'ice_melting') {
            $finished = array_values(array_filter($trials, fn ($t) => $t['done'] && $t['temp_c'] !== null));
            if (count($finished) < 2) {
                return [[
                    'id' => 'finished', 'ok' => false,
                    'text' => 'Only '.count($finished).' of the 2 ice cubes melted completely, so the two could not be compared.',
                ]];
            }

            return [
                ['id' => 'ice', 'ok' => true, 'text' => 'Both beakers had an identical ice cube (same size, same starting temperature).'],
                ['id' => 'temp', 'ok' => abs($finished[0]['temp_c'] - $finished[1]['temp_c']) >= 10, 'text' => 'The two places had clearly different temperatures (the one thing you change).'],
                ['id' => 'setup', 'ok' => ($stats['first_try_setup'] ?? null) === true, 'text' => 'You put every beaker, ice cube and thermometer in the right place the first time.'],
            ];
        }

        $done = array_values(array_filter($trials, fn ($t) => $t['done'] && $t['temp_c'] !== null && $t['water_ml'] !== null));
        if (count($done) < 2) {
            return [[
                'id' => 'finished', 'ok' => false,
                'text' => 'Only '.count($done).' of the 2 beakers was taken all the way to a clear solution, so the two could not be compared.',
            ]];
        }
        [$a, $b] = $done;
        $meanStir = fn ($t) => $t['stir_seconds'] > 0 ? $t['stir_intensity_sum'] / $t['stir_seconds'] : 0;
        $stirOk = max($a['stir_seconds'], $b['stir_seconds']) <= 0.01
            || (min($a['stir_seconds'], $b['stir_seconds']) > 0
                && abs($meanStir($a) - $meanStir($b)) < 0.35
                && min($a['stir_seconds'], $b['stir_seconds']) / max($a['stir_seconds'], $b['stir_seconds']) >= 0.5);

        return [
            ['id' => 'temp', 'ok' => abs($a['temp_c'] - $b['temp_c']) >= 15, 'text' => 'The two beakers had clearly different water temperatures (the variable you change).'],
            ['id' => 'water', 'ok' => abs($a['water_ml'] - $b['water_ml']) <= 15, 'text' => 'Both beakers had about the same amount of water.'],
            ['id' => 'sugar', 'ok' => abs($a['sugar_g'] - $b['sugar_g']) <= 1.5, 'text' => 'Both beakers got about the same amount of sugar.'],
            ['id' => 'stir', 'ok' => $stirOk, 'text' => 'Both beakers were stirred in a similar way (or neither was).'],
        ];
    }

    /** @return 'matched'|'different'|'unknown' */
    public function predictionOutcome(?string $choice, array $trials): string
    {
        $done = array_values(array_filter($trials, fn ($t) => $t['done'] && $t['dissolve_seconds'] !== null && $t['temp_c'] !== null));
        if ($choice === null || count($done) < 2) {
            return 'unknown';
        }
        [$a, $b] = $done;
        $faster = $a['dissolve_seconds'] <= $b['dissolve_seconds'] ? $a : $b;
        $slower = $faster === $a ? $b : $a;
        $gap = ($slower['dissolve_seconds'] - $faster['dissolve_seconds']) / max(1.0, $slower['dissolve_seconds']);

        if ($gap < 0.1) {
            return $choice === 'same' ? 'matched' : 'different';
        }

        $warmerFaster = $faster['temp_c'] > $slower['temp_c'];

        return match ($choice) {
            'warm' => $warmerFaster ? 'matched' : 'different',
            'cold', 'cool' => $warmerFaster ? 'different' : 'matched',
            default => 'different',
        };
    }

    /**
     * Does what the student wrote in the measurement table agree with what the
     * simulation recorded? Returned as plain statements for the marker.
     *
     * @param  array<int,array<string,mixed>>  $trials
     * @param  array<int,array<int,string>>  $table  rows: temp, volume, sugar, time; columns: beaker 1, beaker 2
     * @return array<int,string>
     */
    public function recordedVsSimulation(array $trials, array $table, string $labId = 'dissolving'): array
    {
        $map = VirtualLabCatalogue::find($labId)['table_map'] ?? null;
        $byBeaker = [];
        foreach ($trials as $t) {
            $byBeaker[$t['beaker']] = $t;
        }
        $notes = [];
        $rows = $map['rows'] ?? [0 => ['temp_c', 'water temperature (°C)', 3.0], 3 => ['dissolve_seconds', 'time to dissolve (s)', 0.3]];
        foreach ([0 => $map['beakers'][0] ?? 'beaker1', 1 => $map['beakers'][1] ?? 'beaker2'] as $col => $beaker) {
            $t = $byBeaker[$beaker] ?? null;
            foreach ($rows as $r => [$key, $label, $tol]) {
                $cell = $table[$r][$col] ?? '';
                if (! preg_match('/-?\d+(?:\.\d+)?/', (string) $cell, $m)) {
                    $notes[] = "Beaker ".($col + 1).": the student left {$label} blank.";

                    continue;
                }
                if ($t === null || $t[$key] === null) {
                    continue;
                }
                $value = (float) $m[0];
                $actual = (float) $t[$key];
                $ok = $key === 'temp_c' ? abs($value - $actual) <= $tol : abs($value - $actual) <= max(10.0, $tol * $actual);
                $notes[] = "Beaker ".($col + 1).": student wrote {$value} for {$label}; the simulation recorded {$actual} ("
                    .($ok ? 'close enough' : 'does not agree').').';
            }
        }

        return $notes;
    }

    // ------------------------------------------------------------------
    // Marking
    // ------------------------------------------------------------------

    private function evaluate(object $row, array $lab, $subInstituteId): void
    {
        $progress = json_decode((string) $row->progress, true) ?: [];
        $trials = $progress['trials'] ?? [];
        $checks = $this->experimentChecks($trials, $lab['id'], $progress['stats'] ?? []);
        $choice = $progress['prediction']['choice'] ?? null;
        $outcome = $this->predictionOutcome($choice, $trials);
        $notebook = $progress['notebook'] ?? [];

        $answers = [];
        foreach ($lab['questions'] as $q) {
            $answers[] = [
                'skill' => $q['skill'], 'question' => $q['prompt'],
                'student_answer' => $progress['answers'][$q['id']] ?? '',
            ];
        }

        $payload = [
            'lab' => ['title' => $lab['title'], 'vocabulary_expected' => $lab['vocabulary']],
            'simulation_record_reported_by_the_students_device' => [
                'trials' => $trials,
                'objective_checks' => $checks,
                'prediction' => ['choice' => $choice, 'reason' => $progress['prediction']['reason'] ?? '', 'compared_with_results' => $outcome],
                'handling' => $progress['stats'] ?? [],
                'interactions_logged' => count($progress['log'] ?? []),
            ],
            'student_work' => [
                'measurement_table_recorded_by_student' => $notebook['table'] ?? [],
                'recorded_vs_simulation' => $this->recordedVsSimulation($trials, $notebook['table'] ?? [], $lab['id']),
                'observation_beaker_1' => $notebook['observationA'] ?? '',
                'observation_beaker_2' => $notebook['observationB'] ?? '',
                'comparison' => $notebook['comparison'] ?? '',
                'drew_a_diagram' => ($notebook['diagram'] ?? null) !== null,
                'answers' => $answers,
                'conclusion' => $progress['conclusion'] ?? '',
            ],
        ];

        $system = "You mark one student's {$lab['marker_brief']}, for a K-12 PAL system.\n"
            ."Judge ONLY the student's own words, notebook entries and the experiment record given. Use the rubric exactly (criterion names unchanged).\n"
            ."The scientific key below is for YOU. Never copy it out as a model answer; point the student towards the idea instead.\n"
            ."Key:\n- ".implode("\n- ", $lab['teacher_key'])."\n\n"
            ."Rules:\n"
            ."- The simulation record comes from the student's own device and is treated as what the student did in the virtual lab, not as a physical experiment. Never say a real experiment was performed.\n"
            ."- A diagram was drawn or not; you cannot see it, so do not describe it.\n"
            ."- Reward {$lab['marker_focus']}.\n"
            ."- Explain mistakes and misconceptions kindly and specifically, and say how to fix them. Do not simply state the right answer.\n"
            ."- A prediction that turned out different from the result is not a mistake: say so.\n"
            ."Return ONE JSON object only:\n"
            .'{"criteria":[{"criterion":string,"points_awarded":number,"comment":string}],"went_well":[string],"incorrect_or_unclear":[string],"misconceptions":[string],"improvements":[string],"feedback":string,"next_action":string,"evidence_statement":string}'
            ."\nRubric (points are maximums): ".json_encode($lab['rubric'], JSON_UNESCAPED_UNICODE);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
        $names = implode('; ', array_column($lab['rubric'], 'criterion'));

        $evaluation = null;
        $client = null;
        for ($attempt = 1; $attempt <= 2 && $evaluation === null; $attempt++) {
            $send = $messages;
            if ($attempt === 2) {
                $send[] = ['role' => 'user', 'content' => 'Your previous reply could not be used. Return ONE JSON object with a "criteria" list containing exactly one entry per rubric row, in this order, using these exact criterion names: '.$names.'.'];
            }
            try {
                $client = app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
                $raw = $client->chat($send, null, 3500, 0.3, true, 90);
            } catch (AiConfigurationException $e) {
                Log::warning('Virtual lab: AI not configured', ['error' => $e->getMessage()]);

                return;
            } catch (\Throwable $e) {
                // A timeout or a refusal is not retried here: the student can ask for feedback again.
                Log::warning('Virtual lab: evaluation failed', ['error' => $e->getMessage(), 'id' => $row->id]);

                return;
            }

            $evaluation = $this->applicationLab->validateEvaluation($raw, $lab['rubric']);
            if ($evaluation === null) {
                Log::warning('Virtual lab: evaluation unusable', ['id' => $row->id, 'attempt' => $attempt, 'reply' => mb_substr((string) $raw, 0, 600)]);
            }
        }
        if ($evaluation === null) {
            return;
        }

        $evaluation['experiment_checks'] = $checks;
        $evaluation['prediction'] = $choice !== null ? ['choice' => $choice, 'outcome' => $outcome] : null;
        $evaluation['model'] = $client->lastUsed()?->model;

        DB::table(self::TABLE)->where('id', $row->id)->update([
            'status' => 'evaluated',
            'evaluation' => json_encode($evaluation, JSON_UNESCAPED_UNICODE),
            'evaluated_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function failure(string $reason, string $message): array
    {
        return ['ready' => false, 'reason' => $reason, 'message' => $message];
    }
}
