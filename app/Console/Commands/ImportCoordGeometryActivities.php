<?php

namespace App\Console\Commands;

use App\Services\QuestionGeneration\QuestionFormat;
use App\Services\QuestionGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-off import of the authored Grade 10 Coordinate Geometry (chapter 13) activities
 * into lms_question_master, through the same row-building code the question generator
 * uses, so the existing Question Bank / H5P pages read them unchanged.
 *
 * No model is called: the content is authored in database/data/coord_geometry_ch13_activities.json.
 *
 * DRY RUN BY DEFAULT. Nothing is written unless --commit is passed. The dry run still
 * reads the database (concept, chapter, user, duplicate check) but never writes it.
 *
 *   php artisan qbank:import-coord-geometry --created-by=7013
 *   php artisan qbank:import-coord-geometry --created-by=7013 --commit
 */
class ImportCoordGeometryActivities extends Command
{
    protected $signature = 'qbank:import-coord-geometry
        {--file=database/data/coord_geometry_ch13_activities.json : Authored activities}
        {--created-by= : tbluser.id to record as created_by (required)}
        {--commit : Actually insert. Without it this is a dry run}';

    protected $description = 'Import authored Class 10 Coordinate Geometry activities into lms_question_master (dry run unless --commit)';

    private const DIFFICULTY = ['Easy' => 'Easy', 'Medium' => 'Medium', 'Hard' => 'Hard'];

    public function handle(): int
    {
        $path = base_path((string) $this->option('file'));
        if (!File::exists($path)) {
            $this->error("Data file not found: {$path}");

            return self::FAILURE;
        }
        $data = json_decode(File::get($path), true);
        $scope = $data['scope'] ?? [];
        $activities = $data['activities'] ?? [];
        $excluded = $data['excluded'] ?? [];

        $createdBy = (int) $this->option('created-by');
        if ($createdBy < 1) {
            $this->error('--created-by is required. It is never guessed.');

            return self::FAILURE;
        }
        $user = DB::table('tbluser')->where('id', $createdBy)->where('sub_institute_id', $scope['sub_institute_id'])->first(['id', 'user_name', 'first_name', 'last_name', 'user_profile_id', 'status']);
        if (!$user || (int) $user->status !== 1) {
            $this->error("User {$createdBy} is not an active user of sub_institute_id {$scope['sub_institute_id']}.");

            return self::FAILURE;
        }

        $chapter = DB::table('chapter_master')->where('id', $scope['chapter_id'])->first();
        if (!$chapter || (int) $chapter->subject_id !== (int) $scope['subject_id'] || (int) $chapter->standard_id !== (int) $scope['standard_id']) {
            $this->error('chapter_master does not match the declared chapter / subject / standard.');

            return self::FAILURE;
        }
        $concepts = DB::table('lms_concept')->where('chapter_id', $scope['chapter_id'])->get()->keyBy('id');

        $svc = $this->service();
        $registry = $svc->formats();

        $this->info(sprintf('Mode: %s | created_by: %d (%s %s, profile %s) | chapter %d "%s" | subject %d | standard %d | sub_institute %d',
            $this->option('commit') ? 'COMMIT' : 'DRY RUN', $user->id, $user->first_name, $user->last_name, $user->user_profile_id,
            $chapter->id, $chapter->chapter_name, $scope['subject_id'], $scope['standard_id'], $scope['sub_institute_id']));
        $this->line('Target tables: lms_question_master (+ answer_master for choice rows, + lms_question_mapping for DOK/Bloom). No h5p_* table is written here: the H5P pages read these rows from the question bank.');
        $this->newLine();

        $report = [];
        $valid = [];
        $failures = 0;

        foreach ($activities as $a) {
            $concept = $concepts->get($a['concept_id']);
            $format = $registry->get($a['format']);
            $problems = [];

            if (!$concept) {
                $problems[] = "concept {$a['concept_id']} is not in chapter {$scope['chapter_id']}";
            }
            if (!$format) {
                $problems[] = "format {$a['format']} is not registered";
            }

            $row = null;
            if ($concept && $format) {
                $row = $this->buildRow($a, $concept->name, $format, $a['format'] === 'drag_drop' ? count($a['zones']) : $registry->defaultMarksFor($format));
                foreach ($this->contentProblems($a) as $p) {
                    $problems[] = $p;
                }
                foreach ($this->mathProblems($a['checks'] ?? []) as $p) {
                    $problems[] = $p;
                }
                $reason = $svc->validateOne($format->engine() === 'mcq' ? 'mcq' : 'narrative', $row, $format);
                if ($reason !== null) {
                    $problems[] = 'format validator: ' . $reason;
                }
                if (!$format->isLegacy()) {
                    $row = $format->prepareRow($row);
                }
            }

            $ok = $problems === [];
            $failures += $ok ? 0 : 1;

            $entry = [
                'activity_id' => $a['id'],
                'h5p_type_requested' => $a['source_type'],
                'stored_format' => $a['format'],
                'title' => $row['question_title'] ?? ($a['title'] ?? $a['question'] ?? $a['statement'] ?? $a['text'] ?? null),
                'concept_id' => $a['concept_id'],
                'concept_name' => $concept->name ?? null,
                'chapter_id' => (int) $scope['chapter_id'],
                'subject_id' => (int) $scope['subject_id'],
                'standard_id' => (int) $scope['standard_id'],
                'sub_institute_id' => (int) $scope['sub_institute_id'],
                'created_by' => $createdBy,
                'target_table' => 'lms_question_master' . (in_array($a['format'], ['mcq', 'true_false'], true) ? ' + answer_master' : '') . ' + lms_question_mapping',
                'validation' => $ok ? 'PASS' : 'FAIL',
                'problems' => $problems,
            ];

            if ($ok) {
                $entry['content'] = $row;
                $valid[] = ['activity' => $a, 'row' => $row, 'concept' => $concept, 'chapter' => $chapter, 'format' => $format];
            }
            $report[] = $entry;

            $this->line(sprintf('%s  %-14s -> %-15s  concept %d %-30s  %s%s',
                $a['id'], $a['source_type'], $a['format'], $a['concept_id'], '"' . ($concept->name ?? '?') . '"',
                $ok ? 'PASS' : 'FAIL', $ok ? '' : '  ' . implode(' | ', $problems)));
        }

        // Duplicate check against what is already stored (read-only).
        $dups = 0;
        $built = [];
        foreach ($valid as $v) {
            $ctx = $this->ctx($svc, $v, $scope, $createdBy);
            $hash = $svc->hashOf($v['row']['question_title']);
            $exists = $svc->duplicateExists($ctx, (int) $v['concept']->id, $hash);
            $dups += $exists ? 1 : 0;
            $built[] = ['v' => $v, 'ctx' => $ctx, 'hash' => $hash, 'exists' => $exists];
        }

        $out = storage_path('app/dry-run/coord_geometry_ch13_dryrun.json');
        File::ensureDirectoryExists(dirname($out));
        File::put($out, json_encode(['mode' => $this->option('commit') ? 'commit' : 'dry-run', 'created_by' => $createdBy, 'excluded' => $excluded, 'activities' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info(sprintf('%d activities | %d PASS | %d FAIL | %d already stored (would be skipped)', count($activities), count($valid), $failures, $dups));
        $this->line("Full dry-run (every generated row) written to: {$out}");

        $this->info('Stored question_format_code (validated activities):');
        $counts = array_count_values(array_map(fn ($v) => $v['format']->code(), $valid));
        foreach (['mcq', 'true_false', 'fill_blank', 'match_following', 'drag_drop'] as $code) {
            $this->line(sprintf('  %-16s %d', $code, $counts[$code] ?? 0));
        }
        $this->info(sprintf('Excluded as unsupported: %d', count($excluded)));
        foreach ($excluded as $x) {
            $this->line("  {$x['id']}  {$x['source_type']}  - {$x['reason']}");
        }

        if ($failures > 0) {
            $this->error('Validation failures present. Nothing will be imported.');

            return self::FAILURE;
        }

        if (!$this->option('commit')) {
            $this->warn('Dry run only. Re-run with --commit after approval to insert.');

            return self::SUCCESS;
        }

        // ---- the actual write -------------------------------------------------
        $groups = [];
        foreach ($built as $b) {
            if ($b['exists']) {
                continue;
            }
            $groups[$b['v']['concept']->id . '|' . $b['v']['format']->code()][] = $b;
        }

        // Pictures first, through the existing store, so the stored URL is the planned one.
        $store = app(\App\Services\QuestionGeneration\DragDrop\SpacesDiagramImageStore::class);
        $uploaded = [];
        foreach ($built as $b) {
            $img = $b['v']['activity']['image'] ?? null;
            if ($img === null || $b['exists'] || isset($uploaded[$img['sha1']])) {
                continue;
            }
            $url = $store->store(file_get_contents(database_path('data/' . $img['file'])), $img['mime']);
            if ($url !== $this->plannedImageUrl($img)) {
                $this->error("Picture stored at {$url}, expected " . $this->plannedImageUrl($img) . '. Aborting before any database write.');

                return self::FAILURE;
            }
            $uploaded[$img['sha1']] = $url;
            $this->line("  picture stored: {$url}");
        }

        $inserted = [];
        DB::transaction(function () use ($groups, $svc, &$inserted) {
            foreach ($groups as $items) {
                $first = $items[0];
                $format = $first['v']['format'];
                $meta = [
                    'model' => 'claude (authored)',
                    'temperature' => null,
                    'seed' => null,
                    'prompt_version' => 'authored-coord-geometry-ch13-v1',
                    'format_code' => $format->persistedFormatCode(),
                    'format_prompt_version' => 'authored-coord-geometry-ch13-v1',
                    'batch_id' => (string) Str::uuid(),
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'diagnostic_stage' => null,
                ];
                $resp = [
                    'semantic_concept_key' => $first['ctx']['semantic_concept_key'],
                    'question_type' => $format->responseType(),
                    'rows' => array_map(fn ($i) => $i['v']['row'], $items),
                ];
                $res = $svc->store($resp, $format->engine() === 'mcq' ? 'mcq' : 'narrative', $first['ctx'], $meta);
                foreach ($res['ids'] as $k => $id) {
                    $inserted[] = ['id' => $id, 'title' => $resp['rows'][$k]['question_title'] ?? ''];
                }
            }
        });

        $this->info('Inserted ' . count($inserted) . ' question(s) into lms_question_master:');
        foreach ($inserted as $i) {
            $this->line('  ' . $i['id'] . '  ' . Str::limit(str_replace("\n", ' ', $i['title']), 90));
        }

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // Rows
    // -----------------------------------------------------------------------

    private function ctx(QuestionGenerationService $svc, array $v, array $scope, int $createdBy): array
    {
        $key = $svc->semKey((int) $v['concept']->id, $v['concept']->name);
        $qtid = $svc->formats()->questionTypeIdFor($v['format']);

        return $svc->context(
            ['concept' => $v['concept'], 'chapter' => $v['chapter']],
            ['sub_institute_id' => $scope['sub_institute_id'], 'created_by' => $createdBy, 'standard_id' => $scope['standard_id'],
             'subject_id' => $scope['subject_id'], 'chapter_id' => $scope['chapter_id']],
            $qtid, $key, $v['format']
        );
    }

    private function buildRow(array $a, string $conceptName, QuestionFormat $format, int $marks): array
    {
        $type = $a['source_type'];
        $seconds = ['true_false' => 30, 'mcq' => 60, 'fill_blank' => 45, 'match_following' => 90, 'drag_drop' => 30 + 20 * count($a['zones'] ?? [])][$a['format']] ?? 60;

        $answer = [
            'v' => 'ans-2.0',
            'question_type' => $format->responseType(),
            'bloom_level' => $a['bloom'],
            'dok_level' => $a['dok'],
            'difficulty' => self::DIFFICULTY[$a['difficulty']],
            'stimulus' => null,
            'estimated_time_seconds' => $seconds,
            'explanation' => $a['explanation'],
            'remediation' => "Reteach {$conceptName} with a worked example before retrying.",
            'knowledge_refs' => [$conceptName],
            'ability_ref' => null,
            'misconception_refs' => [],
            'authored_as' => $type,
            'authored_activity_id' => $a['id'],
        ];

        switch ($a['format']) {
            case 'mcq':
                $title = $a['question'];
                $letters = ['A', 'B', 'C', 'D'];
                $options = [];
                foreach ($a['options'] as $i => $text) {
                    $isCorrect = $i === $a['correct'];
                    $options[] = [
                        'label' => $letters[$i], 'text' => $text, 'is_correct' => $isCorrect,
                        'distractor_type' => $isCorrect ? 'correct' : 'plausible',
                        'rationale' => $isCorrect ? $a['explanation'] : '',
                    ];
                }
                $answer += [
                    'sub_type' => 'MCQ', 'options' => $options, 'correct_option' => $letters[$a['correct']],
                    'model_answer' => $a['options'][$a['correct']],
                ];
                break;
            case 'true_false':
                $title = $a['statement'];
                $answer += ['sub_type' => 'True/False', 'model_answer' => $a['answer'] ? 'True' : 'False'];
                break;
            case 'fill_blank':
                $title = $a['text'];
                $answer += [
                    'sub_type' => 'Fill in the Blank', 'answers' => $a['answers'],
                    'accepted_alternatives' => $a['alts'], 'model_answer' => implode('; ', $a['answers']),
                ];
                break;
            case 'drag_drop':
                $title = $a['title'];
                $zones = array_map(fn ($z) => ['id' => $z['id'], 'label' => $z['label'], 'x' => $z['x'], 'y' => $z['y'], 'width' => $z['width'], 'height' => $z['height']], $a['zones']);
                $elements = array_map(fn ($e) => ['id' => $e['id'], 'text' => $e['text'], 'zone_ids' => [$e['zone_id']]], $a['elements']);
                $labels = array_column($a['zones'], 'label', 'id');
                $answer += [
                    'sub_type' => 'Drag and Drop',
                    'drag_drop' => [
                        'v' => 1,
                        'image' => [
                            // The URL the existing SpacesDiagramImageStore will return for these bytes.
                            'url' => $this->plannedImageUrl($a['image']),
                            'width_px' => $a['image']['width_px'],
                            'height_px' => $a['image']['height_px'],
                            'mime' => $a['image']['mime'],
                            'alt' => $a['image']['alt'],
                            'provider' => null,
                            'licence' => 'Original artwork generated for this activity',
                            'creator' => null,
                            'attribution' => null,
                            'source_url' => null,
                            'sha1' => $a['image']['sha1'],
                        ],
                        'image_fit' => 'contain',
                        'zones' => $zones,
                        'elements' => $elements,
                    ],
                    'model_answer' => implode('; ', array_map(fn ($e) => $e['text'] . ' -> ' . $labels[$e['zone_id']], $a['elements'])),
                ];
                break;
            default: // match_following
                $title = $a['title'];
                $pairs = array_map(fn ($p) => ['left' => $p[0], 'right' => $p[1]], $a['pairs']);
                $answer += [
                    'sub_type' => 'Match the Following', 'pairs' => $pairs,
                    'model_answer' => implode('; ', array_map(fn ($p) => $p['left'] . ' - ' . $p['right'], $pairs)),
                ];
        }

        return [
            'question_title' => $title,
            'description' => mb_substr("Tests {$conceptName} at {$a['bloom']} ({$a['difficulty']}).", 0, 240),
            'subconcept' => $conceptName,
            'points' => $marks,
            'multiple_answer' => 0,
            'hint_text' => null,
            'learning_outcome' => ["Apply {$conceptName} in Class 10 Coordinate Geometry"],
            'answer' => $answer,
        ];
    }

    /** Structural checks the format validators do not cover. */
    private function contentProblems(array $a): array
    {
        $p = [];
        if ($a['format'] === 'mcq') {
            $opts = array_map(fn ($o) => mb_strtolower(trim($o)), $a['options']);
            if (count($opts) !== 4) {
                $p[] = 'MCQ needs exactly 4 options';
            }
            if (count(array_unique($opts)) !== count($opts)) {
                $p[] = 'duplicate options';
            }
            if (!isset($a['options'][$a['correct']] ) ) {
                $p[] = 'correct index out of range';
            }
        }
        if ($a['format'] === 'drag_drop') {
            array_push($p, ...$this->dragDropProblems($a));
        }
        if ($a['format'] === 'match_following') {
            $pairKeys = array_map(fn ($x) => mb_strtolower($x[0] . '=>' . $x[1]), $a['pairs']);
            if (count(array_unique($pairKeys)) !== count($pairKeys)) {
                $p[] = 'duplicate pair';
            }
        }

        return $p;
    }

    // -----------------------------------------------------------------------
    // Drag & Drop: the picture, the zones and the answer key
    // -----------------------------------------------------------------------

    /** The URL SpacesDiagramImageStore returns for these bytes. Computed, not uploaded: nothing is written. */
    private function plannedImageUrl(array $image): string
    {
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$image['mime']] ?? 'png';

        return Storage::disk('digitalocean')->url('public/h5p_content/dragdrop_gen_' . $image['sha1'] . '.' . $ext);
    }

    /** Recomputes every drag_drop claim from the picture file and the plane it was drawn on. */
    private function dragDropProblems(array $a): array
    {
        $bad = [];
        $img = $a['image'];
        $file = database_path('data/' . $img['file']);
        if (!is_file($file)) {
            return ["image file missing: {$img['file']}"];
        }
        $bytes = file_get_contents($file);
        $info = @getimagesizefromstring($bytes);

        if (sha1($bytes) !== $img['sha1']) {
            $bad[] = 'image sha1 differs from the file on disk';
        }
        if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            $bad[] = 'image is not PNG, JPEG or WebP';
        } else {
            if ($info['mime'] !== $img['mime']) {
                $bad[] = "image is {$info['mime']}, declared {$img['mime']}";
            }
            if ($info[0] !== $img['width_px'] || $info[1] !== $img['height_px']) {
                $bad[] = "image is {$info[0]}x{$info[1]}, declared {$img['width_px']}x{$img['height_px']}";
            }
            if ($info[0] < 400 || $info[1] < 300) {
                $bad[] = "image {$info[0]}x{$info[1]} is below 400x300";
            }
        }
        if (strlen($bytes) > 5 * 1024 * 1024) {
            $bad[] = 'image is larger than 5 MB';
        }

        $zones = $a['zones'];
        if (count($zones) < 3 || count($zones) > 8) {
            $bad[] = 'needs 3 to 8 zones';
        }

        $pl = $img['plane'];
        $W = $img['width_px'];
        $H = $img['height_px'];
        $box = function (array $z) use ($pl, $W, $H) {
            $x0 = $z['x'] / 100 * $W;
            $x1 = ($z['x'] + $z['width']) / 100 * $W;
            $y0 = $z['y'] / 100 * $H;
            $y1 = ($z['y'] + $z['height']) / 100 * $H;
            $d = fn ($px, $py) => [($px - $pl['ox']) / $pl['u'], ($pl['oy'] - $py) / $pl['u']];

            return ['tl' => $d($x0, $y0), 'tr' => $d($x1, $y0), 'bl' => $d($x0, $y1), 'br' => $d($x1, $y1), 'c' => $d(($x0 + $x1) / 2, ($y0 + $y1) / 2)];
        };
        $inside = function (array $b, array $pt) {
            return $pt[0] >= $b['tl'][0] && $pt[0] <= $b['tr'][0] && $pt[1] <= $b['tl'][1] && $pt[1] >= $b['bl'][1];
        };
        $quad = fn ($p) => $p[0] > 0 && $p[1] > 0 ? 'I' : ($p[0] < 0 && $p[1] > 0 ? 'II' : ($p[0] < 0 && $p[1] < 0 ? 'III' : ($p[0] > 0 && $p[1] < 0 ? 'IV' : 'axis')));
        $posOf = function ($p) {
            if ($p[0] == 0 && $p[1] == 0) {
                return 'origin';
            }
            if ($p[1] == 0) {
                return $p[0] > 0 ? 'x+' : 'x-';
            }
            if ($p[0] == 0) {
                return $p[1] > 0 ? 'y+' : 'y-';
            }

            return 'none';
        };

        $byId = [];
        foreach ($zones as $z) {
            $byId[$z['id']] = $z;
            $b = $box($z);

            if (isset($z['quadrant'])) {
                foreach (['tl', 'tr', 'bl', 'br'] as $corner) {
                    if ($quad($b[$corner]) !== $z['quadrant']) {
                        $bad[] = "zone {$z['id']} corner {$corner} is not inside quadrant {$z['quadrant']}";
                    }
                }
            }
            if (isset($z['contains']) && !$inside($b, $z['contains'])) {
                $bad[] = "zone {$z['id']} does not cover the point " . json_encode($z['contains']);
            }
            if (isset($z['axis'])) {
                $ok = match ($z['axis']) {
                    'x+' => $b['tl'][0] > 0 && abs($b['c'][1]) < 0.5,
                    'x-' => $b['tr'][0] < 0 && abs($b['c'][1]) < 0.5,
                    'y+' => $b['bl'][1] > 0 && abs($b['c'][0]) < 0.5,
                    'y-' => $b['tl'][1] < 0 && abs($b['c'][0]) < 0.5,
                    'origin' => $inside($b, [0, 0]),
                    default => false,
                };
                if (!$ok) {
                    $bad[] = "zone {$z['id']} does not sit on the {$z['axis']} part of the axes";
                }
            }
        }

        // Exactly one draggable per zone, every zone answerable, no draggable on two zones.
        $zoneUse = [];
        foreach ($a['elements'] as $e) {
            if (!isset($byId[$e['zone_id']])) {
                $bad[] = "draggable {$e['id']} points at unknown zone {$e['zone_id']}";
                continue;
            }
            $zoneUse[$e['zone_id']] = ($zoneUse[$e['zone_id']] ?? 0) + 1;
            $z = $byId[$e['zone_id']];

            if (($e['point'] ?? null) !== null) {
                if (isset($z['quadrant']) && $quad($e['point']) !== $z['quadrant']) {
                    $bad[] = "draggable {$e['text']} is in quadrant {$quad($e['point'])}, not {$z['quadrant']}";
                }
                if (isset($z['axis']) && $posOf($e['point']) !== $z['axis']) {
                    $bad[] = "draggable {$e['text']} is at {$posOf($e['point'])}, not {$z['axis']}";
                }
                if (isset($z['contains']) && $e['point'] != $z['contains']) {
                    $bad[] = "draggable {$e['text']} is not the point the zone covers";
                }
            }
        }
        foreach ($zones as $z) {
            if (($zoneUse[$z['id']] ?? 0) !== 1) {
                $bad[] = "zone {$z['id']} has " . ($zoneUse[$z['id']] ?? 0) . ' correct draggables, needs exactly 1';
            }
        }
        if (count(array_unique(array_column($a['elements'], 'text'))) !== count($a['elements'])) {
            $bad[] = 'two draggables have the same text';
        }

        // Segment lengths: the draggable on each segment must equal its recomputed length.
        foreach ($a['segments'] ?? [] as [$zid, $p, $q]) {
            $len = sqrt(($q[0] - $p[0]) ** 2 + ($q[1] - $p[1]) ** 2);
            $el = current(array_filter($a['elements'], fn ($e) => $e['zone_id'] === $zid));
            if (($el['text'] ?? null) !== rtrim(rtrim(number_format($len, 4, '.', ''), '0'), '.') . ' units') {
                $bad[] = "segment {$zid} has length {$len} but the draggable says {$el['text']}";
            }
            $mid = [($p[0] + $q[0]) / 2, ($p[1] + $q[1]) / 2];
            if (($byId[$zid]['contains'] ?? null) != $mid) {
                $bad[] = "zone {$zid} is not centred on the midpoint of its segment";
            }
        }

        // Ratio points: AP : PB = m : n must put P exactly where the zone sits.
        if (isset($a['ends'])) {
            [$A, $B] = $a['ends'];
            foreach ($a['elements'] as $e) {
                if (preg_match('/AP : PB = (\d+) : (\d+)/', $e['text'], $m)) {
                    $mm = (int) $m[1];
                    $nn = (int) $m[2];
                    $pt = [($mm * $B[0] + $nn * $A[0]) / ($mm + $nn), ($mm * $B[1] + $nn * $A[1]) / ($mm + $nn)];
                    if ($pt != $e['point']) {
                        $bad[] = "ratio {$mm}:{$nn} gives " . json_encode($pt) . ', draggable maps to ' . json_encode($e['point']);
                    }
                }
            }
        }

        return $bad;
    }

    // -----------------------------------------------------------------------
    // Independent maths verification (recomputed here, not copied from the answer text)
    // -----------------------------------------------------------------------

    private function mathProblems(array $checks): array
    {
        $bad = [];
        $same = fn ($x, $y) => abs($x - $y) < 1e-9;
        foreach ($checks as $c) {
            [$px, $py] = $c['p'];
            switch ($c['t']) {
                case 'dist':
                    [$qx, $qy] = $c['q'];
                    $got = sqrt(($qx - $px) ** 2 + ($qy - $py) ** 2);
                    if (!$same($got, $c['expect'])) {
                        $bad[] = "distance {$this->pt($c['p'])}-{$this->pt($c['q'])} = {$got}, expected {$c['expect']}";
                    }
                    break;
                case 'mid':
                    [$qx, $qy] = $c['q'];
                    $g = [($px + $qx) / 2, ($py + $qy) / 2];
                    if (!$same($g[0], $c['expect'][0]) || !$same($g[1], $c['expect'][1])) {
                        $bad[] = "midpoint {$this->pt($c['p'])}-{$this->pt($c['q'])} = {$this->pt($g)}, expected {$this->pt($c['expect'])}";
                    }
                    break;
                case 'sec':
                    [$qx, $qy] = $c['q'];
                    $m = $c['m'];
                    $n = $c['n'];
                    $g = [($m * $qx + $n * $px) / ($m + $n), ($m * $qy + $n * $py) / ($m + $n)];
                    if (!$same($g[0], $c['expect'][0]) || !$same($g[1], $c['expect'][1])) {
                        $bad[] = "section {$m}:{$n} of {$this->pt($c['p'])}-{$this->pt($c['q'])} = {$this->pt($g)}, expected {$this->pt($c['expect'])}";
                    }
                    break;
                case 'area':
                    [$qx, $qy] = $c['q'];
                    [$rx, $ry] = $c['r'];
                    $g = 0.5 * abs($px * ($qy - $ry) + $qx * ($ry - $py) + $rx * ($py - $qy));
                    if (!$same($g, $c['expect'])) {
                        $bad[] = "area = {$g}, expected {$c['expect']}";
                    }
                    break;
                case 'quad':
                    $g = $px > 0 && $py > 0 ? 'I' : ($px < 0 && $py > 0 ? 'II' : ($px < 0 && $py < 0 ? 'III' : ($px > 0 && $py < 0 ? 'IV' : 'axis')));
                    if ($g !== $c['expect']) {
                        $bad[] = "quadrant of {$this->pt($c['p'])} = {$g}, expected {$c['expect']}";
                    }
                    break;
                case 'pos':
                    if ($px == 0 && $py == 0) {
                        $accept = ['Origin'];
                    } elseif ($py == 0) {
                        $accept = [$px > 0 ? 'Positive x-axis' : 'Negative x-axis', 'On the x-axis'];
                    } elseif ($px == 0) {
                        $accept = [$py > 0 ? 'Positive y-axis' : 'Negative y-axis', 'On the y-axis'];
                    } else {
                        $accept = ['not on an axis'];
                    }
                    if (!in_array($c['expect'], $accept, true)) {
                        $bad[] = "position of {$this->pt($c['p'])} is " . implode(' / ', $accept) . ", expected {$c['expect']}";
                    }
                    break;
                default:
                    $bad[] = "unknown check type {$c['t']}";
            }
        }

        return $bad;
    }

    private function pt(array $p): string
    {
        return '(' . implode(', ', array_map(fn ($v) => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.'), $p)) . ')';
    }

    // -----------------------------------------------------------------------
    // The generator's own row-building code, reached through a thin subclass so the
    // existing service is not modified.
    // -----------------------------------------------------------------------

    private function service(): QuestionGenerationService
    {
        return new class extends QuestionGenerationService {
            public function validateOne(string $type, array $row, QuestionFormat $format): ?string
            {
                $r = $this->validateRows($type, [$row], [], $format);

                return $r['skipped'][0] ?? null;
            }

            public function context(array $slice, array $input, int $qtid, string $key, QuestionFormat $format): array
            {
                return $this->buildPersistenceContext($slice, $input, $qtid, $key, $format);
            }

            public function semKey(int $id, ?string $name): string
            {
                return $this->semanticConceptKey($id, $name);
            }

            public function hashOf(string $title): string
            {
                $norm = preg_replace('/[^a-z0-9 ]/', '', strtolower(preg_replace('/\s+/', ' ', trim($title))));

                return hash('sha256', $norm);
            }

            public function duplicateExists(array $ctx, int $conceptId, string $hash): bool
            {
                return $this->contentHashQuery($ctx, $conceptId, $ctx['question_type_id'], $hash)->exists();
            }

            public function store(array $resp, string $type, array $ctx, array $meta): array
            {
                return $this->persist($resp, $type, $ctx, $meta);
            }
        };
    }
}
