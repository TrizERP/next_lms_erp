<?php

namespace App\Console\Commands\PAL;

use App\Models\PAL\ContentMetadata;
use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\AI\AIOrchestrationService;
use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase P4(b) — propose metadata over the untagged content estate.
 *
 * CONTENT LAW C5 governs every write here: proposals only. Rows are written with
 * tagged_by='ai' (or 'derived' for the heuristic engine), a confidence float, and
 * quality_status='draft'. This command CANNOT write 'approved' — the constraint
 * is enforced by PalVocabulary::isMachineWritable(), not by discipline.
 *
 * Two engines:
 *
 *   --engine=heuristic  (default) Deterministic. Classifies Bloom from the
 *                       question's verb, difficulty from derived psychometrics,
 *                       and inherits any Bloom/pedagogy tag the LMS already
 *                       carries in content_mapping_type. Free, auditable,
 *                       reproducible, and it runs over all 62,209 rows in
 *                       minutes. Confidence reflects the evidence used.
 *
 *   --engine=ai         Calls AIOrchestrationService for the fields a verb list
 *                       cannot reach — cultural context, misconception
 *                       candidates, knowledge type. Costs money per batch and is
 *                       non-deterministic, so it is opt-in and ordered by usage.
 *
 * R1 (plan §8): review is the bottleneck, not inference. `--order-by-usage` tags
 * the highest-exposure questions first so a partial run still covers most of what
 * learners actually see.
 */
class TagContentCommand extends Command
{
    protected $signature = 'pal:tag-content
        {--estate=questions : questions | content}
        {--engine=heuristic : heuristic | ai}
        {--tenant= : restrict to one sub_institute_id}
        {--concept= : restrict to one lms_concept.id}
        {--limit=0 : stop after N rows (0 = all)}
        {--batch=500 : rows per batch}
        {--order-by-usage : tag the highest-exposure rows first (recommended)}
        {--retag : also re-propose for rows that already have a proposal}
        {--dry-run : compute and report, write nothing}';

    protected $description = 'PAL V4: propose Bloom/difficulty/pedagogy/cultural metadata over untagged content (writes drafts only — C5)';

    /**
     * Bloom classification by task verb (spec §3.1 task column).
     *
     * Ordered most-specific first: "identify" must not be caught by a looser
     * pattern later in the list. Matched against the opening of the question stem,
     * which is where the command verb lives in practice.
     */
    protected array $verbMap = [
        'create'     => ['design', 'invent', 'compose', 'construct a plan', 'devise', 'formulate', 'propose a', 'create a'],
        'evaluate'   => ['justify', 'critique', 'evaluate', 'judge', 'defend', 'argue', 'do you agree', 'which is better'],
        'analyze'    => ['compare', 'contrast', 'differentiate', 'distinguish', 'examine', 'analyse', 'analyze', 'why do you think', 'what causes'],
        'apply'      => ['solve', 'calculate', 'compute', 'find the', 'how much', 'how many', 'determine', 'use the', 'apply'],
        'understand' => ['explain', 'describe', 'classify', 'summarise', 'summarize', 'interpret', 'give reason', 'what is meant'],
        'recall'     => ['name', 'list', 'state', 'define', 'identify', 'recall', 'label', 'what is the', 'who', 'when did'],
    ];

    /**
     * Cultural-context detection (spec §2.3). Keyword evidence only — a proposal,
     * never a fact. Reviewed by a human before it can influence routing.
     */
    protected array $contextMap = [
        'sports_cricket'    => ['cricket', 'batting', 'bowler', 'wicket', 'runs scored', 'over', 'stadium'],
        'agriculture_farm'  => ['farmer', 'crop', 'harvest', 'plough', 'field', 'sowing', 'irrigation', 'tractor'],
        'urban_market'      => ['market', 'shop', 'shopkeeper', 'price', 'discount', 'bill', 'mall', 'sabzi'],
        'festival_cultural' => ['diwali', 'festival', 'rangoli', 'holi', 'eid', 'pongal', 'sweets', 'procession'],
        'coastal_fishing'   => ['fisherman', 'boat', 'catch', 'sea', 'net', 'coastal', 'harbour'],
        'rural_village'     => ['village', 'well', 'bullock', 'panchayat', 'hut', 'pond'],
    ];

    public function handle(AIOrchestrationService $ai): int
    {
        $estate = $this->option('estate');
        $engine = $this->option('engine');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($estate, ['questions', 'content'], true)) {
            $this->error("--estate must be 'questions' or 'content'.");

            return self::FAILURE;
        }
        if (! in_array($engine, ['heuristic', 'ai'], true)) {
            $this->error("--engine must be 'heuristic' or 'ai'.");

            return self::FAILURE;
        }

        // The AI engine is useless without a key and would write 62k rows of
        // "AI generation temporarily unavailable" — check before starting.
        if ($engine === 'ai' && ! $this->aiAvailable()) {
            $this->error('AI engine selected but OpenRouter is not configured (config/openrouter.php).');
            $this->line('Run with --engine=heuristic, or configure the key first.');

            return self::FAILURE;
        }

        $this->info('PAL V4 — proposing content metadata');
        $this->line("estate={$estate}  engine={$engine}" . ($dryRun ? '   [DRY RUN — no writes]' : ''));
        $this->line('CONTENT LAW C5: every row is written as a DRAFT proposal. This command cannot approve anything.');
        $this->line(str_repeat('─', 78));

        $rows = $estate === 'questions' ? $this->fetchQuestions() : $this->fetchContent();

        if ($rows->isEmpty()) {
            $this->warn('Nothing to tag with the given filters.');

            return self::SUCCESS;
        }

        $this->line('Rows to process: ' . number_format($rows->count()));

        $stats = ['written' => 0, 'skipped' => 0, 'rejected' => 0, 'low_confidence' => 0, 'per_tenant' => []];
        $rejections = [];

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows->chunk((int) $this->option('batch')) as $batch) {
            $inherited = $estate === 'content' ? $this->inheritedTags($batch->pluck('id')->all()) : [];

            foreach ($batch as $row) {
                $bar->advance();

                $proposal = $estate === 'questions'
                    ? $this->proposeForQuestion($row, $engine, $ai)
                    : $this->proposeForContent($row, $inherited[$row->id] ?? [], $engine, $ai);

                if ($proposal === null) {
                    $stats['skipped']++;
                    continue;
                }

                $errors = PalVocabulary::validate($proposal);
                if ($errors !== []) {
                    $stats['rejected']++;
                    if (count($rejections) < 10) {
                        $rejections[] = [$row->id, implode('; ', array_slice($errors, 0, 2))];
                    }
                    continue;
                }

                // C5, belt and braces: refuse to write a status a machine may not write.
                if (! PalVocabulary::isMachineWritable($proposal['quality_status'])) {
                    $stats['rejected']++;
                    $rejections[] = [$row->id, 'C5: machine attempted to write ' . $proposal['quality_status']];
                    continue;
                }

                if (($proposal['confidence'] ?? 1.0) < config('pal_content.ai_tagging.low_confidence_below')) {
                    $stats['low_confidence']++;
                }

                $tenantId = (int) $row->sub_institute_id;
                $stats['per_tenant'][$tenantId] = ($stats['per_tenant'][$tenantId] ?? 0) + 1;

                if (! $dryRun) {
                    $this->persist($estate, $row, $proposal);
                }

                $stats['written']++;
            }
        }

        $bar->finish();
        $this->line('');
        $this->line(str_repeat('─', 78));

        $this->table(['Result', 'Count'], [
            [$dryRun ? 'Would write (draft)' : 'Drafts written', number_format($stats['written'])],
            ['Skipped (no evidence)', number_format($stats['skipped'])],
            ['Rejected (validation)', number_format($stats['rejected'])],
            ['Flagged low confidence (< ' . config('pal_content.ai_tagging.low_confidence_below') . ')', number_format($stats['low_confidence'])],
        ]);

        if ($rejections !== []) {
            $this->warn('Sample rejections:');
            $this->table(['Row id', 'Reason'], $rejections);
        }

        $this->comment('Per-tenant counts:');
        foreach ($stats['per_tenant'] as $t => $c) {
            $this->line('  sub_institute_id ' . str_pad((string) $t, 6) . number_format($c));
        }

        $this->line('');
        $this->line('Zero rows written as approved. Review them in the authoring console before delivery (C4/C5).');

        return self::SUCCESS;
    }

    // ── Row fetching ─────────────────────────────────────────────────────────

    protected function fetchQuestions()
    {
        $q = DB::table('lms_question_master as q')
            ->select('q.id', 'q.question_title', 'q.description', 'q.concept', 'q.subconcept',
                'q.concept_id', 'q.chapter_id', 'q.topic_id', 'q.sub_institute_id',
                'q.hint_text', 'q.learning_outcome', 'q.answer');

        if ($this->option('tenant') !== null) {
            $q->where('q.sub_institute_id', (int) $this->option('tenant'));
        }
        if ($this->option('concept') !== null) {
            $q->where('q.concept_id', (int) $this->option('concept'));
        }

        if (! $this->option('retag')) {
            $q->whereNotExists(function ($sub) {
                $sub->selectRaw(1)->from('pal_question_metadata as m')
                    ->whereColumn('m.question_id', 'q.id')
                    ->whereNotNull('m.bloom_level');
            });
        }

        if ($this->option('order-by-usage')) {
            // R1: highest learner exposure first, so a partial run still covers
            // most of what learners actually see.
            $q->leftJoin(DB::raw('(SELECT question_id, COUNT(*) AS n FROM lms_online_exam_answer GROUP BY question_id) AS u'),
                'u.question_id', '=', 'q.id')
                ->orderByDesc(DB::raw('COALESCE(u.n, 0)'));
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $q->limit($limit);
        }

        return $q->get();
    }

    protected function fetchContent()
    {
        $q = DB::table('content_master as c')
            ->select('c.id', 'c.title', 'c.description', 'c.file_type', 'c.url', 'c.meta_tags',
                'c.content_category', 'c.basic_advance', 'c.concept_id', 'c.chapter_id',
                'c.topic_id', 'c.sub_institute_id', 'c.filename');

        if ($this->option('tenant') !== null) {
            $q->where('c.sub_institute_id', (int) $this->option('tenant'));
        }
        if ($this->option('concept') !== null) {
            $q->where('c.concept_id', (int) $this->option('concept'));
        }

        if (! $this->option('retag')) {
            $q->whereNotExists(function ($sub) {
                $sub->selectRaw(1)->from('pal_content_metadata as m')
                    ->whereColumn('m.content_master_id', 'c.id')
                    ->whereNotNull('m.content_type');
            });
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $q->limit($limit);
        }

        return $q->get();
    }

    // ── Proposals ────────────────────────────────────────────────────────────

    protected function proposeForQuestion($row, string $engine, AIOrchestrationService $ai): ?array
    {
        $text = trim(strip_tags((string) $row->question_title . ' ' . (string) $row->description));

        if ($text === '') {
            return null;
        }

        [$bloom, $bloomConfidence, $evidence] = $this->classifyBloom($text);

        if ($bloom === null) {
            return null;
        }

        $proposal = [
            'bloom_level' => $bloom,
            'practice_level' => PalVocabulary::practiceLevelForBloom($bloom),
            'concept_ref_id' => $row->concept_id ?: null,
            // concept_id is empty on ~all of the estate; chapter_id is the key
            // that is actually populated, so the layer routes on it meanwhile.
            'chapter_ref_id' => $row->chapter_id ?: null,
            'topic_ref_id' => $row->topic_id ?: null,
            'sub_concept_ref' => $row->subconcept ?: null,
            'language' => $this->detectLanguage($text),
            'cultural_context' => $this->classifyContext($text),
            'knowledge_type' => $this->classifyKnowledgeType($text, $bloom),
            'scaffold_type' => ! empty($row->hint_text) ? 'hint_available' : 'none',
            'reading_level_fk' => $this->fleschKincaid($text),
            'quality_status' => config('pal_content.ai_tagging.forced_status'),
            'tagged_by' => $engine === 'ai' ? 'ai' : 'derived',
            'confidence' => $bloomConfidence,
            'ai_rationale' => ['engine' => $engine, 'bloom_evidence' => $evidence],
        ];

        // Difficulty from real psychometrics when they have been derived —
        // observed difficulty beats any guess from the question text.
        $irt = QuestionMetadata::where('question_id', $row->id)
            ->where('sub_institute_id', $row->sub_institute_id)
            ->first(['irt_b', 'first_attempt_correct_rate', 'discrimination_index']);

        if ($irt && $irt->first_attempt_correct_rate !== null) {
            $proposal['difficulty_1_to_5'] = $this->difficultyFromPValue((float) $irt->first_attempt_correct_rate);
            $proposal['ai_rationale']['difficulty_source'] = 'derived_p_value';
            $proposal['confidence'] = round(min(1.0, $bloomConfidence + 0.15), 3);
        } else {
            $proposal['difficulty_1_to_5'] = PalVocabulary::practiceLevelForBloom($bloom);
            $proposal['ai_rationale']['difficulty_source'] = 'bloom_proxy';
        }

        if ($engine === 'ai') {
            $enriched = $this->aiEnrich($ai, $text, $bloom);
            if ($enriched !== []) {
                $proposal = array_merge($proposal, $enriched);
                $proposal['ai_rationale']['ai_fields'] = array_keys($enriched);
            }
        }

        return $proposal;
    }

    protected function proposeForContent($row, array $inherited, string $engine, AIOrchestrationService $ai): ?array
    {
        $text = trim(strip_tags((string) $row->title . ' ' . (string) $row->description));

        if ($text === '') {
            return null;
        }

        $format = $this->formatFromFileType($row->file_type, $row->url);
        $contentType = $this->contentTypeFor($row);

        $proposal = [
            'content_type' => $contentType,
            'format' => $format,
            'variant_number' => PalVocabulary::defaultVariantForFormat($format),
            'concept_ref_id' => $row->concept_id ?: null,
            'chapter_ref_id' => $row->chapter_id ?: null,
            'topic_ref_id' => $row->topic_id ?: null,
            'language' => $this->detectLanguage($text),
            'cultural_context' => $this->classifyContext($text),
            'reading_level_fk' => $this->fleschKincaid($text),
            'quality_status' => config('pal_content.ai_tagging.forced_status'),
            'tagged_by' => $engine === 'ai' ? 'ai' : 'derived',
            'confidence' => 0.55,
            'ai_rationale' => ['engine' => $engine, 'format_source' => 'file_type:' . ($row->file_type ?: 'unknown')],
        ];

        // Anything the LMS already tags through content_mapping_type is
        // inherited rather than re-guessed — a human already chose it.
        if (isset($inherited['bloom'])) {
            $proposal['bloom_level_served'] = $inherited['bloom'];
            $proposal['practice_level'] = PalVocabulary::practiceLevelForBloom($inherited['bloom']);
            $proposal['confidence'] = 0.9;
            $proposal['ai_rationale']['bloom_source'] = 'inherited_from_content_mapping_type';
        } else {
            [$bloom, $conf] = $this->classifyBloom($text);
            if ($bloom !== null) {
                $proposal['bloom_level_served'] = $bloom;
                $proposal['practice_level'] = PalVocabulary::practiceLevelForBloom($bloom);
                $proposal['confidence'] = round($conf * 0.8, 3);
            }
        }

        if (isset($inherited['pedagogy'])) {
            $proposal['pedagogy_mapping_id'] = $inherited['pedagogy'];
            $proposal['ai_rationale']['pedagogy_source'] = 'inherited_from_content_mapping_type';
        }

        // basic_advance is an existing free-text difficulty signal on content_master.
        $ba = strtolower((string) $row->basic_advance);
        if (str_contains($ba, 'basic')) {
            $proposal['difficulty_1_to_5'] = 2;
        } elseif (str_contains($ba, 'advance')) {
            $proposal['difficulty_1_to_5'] = 4;
        }

        if ($engine === 'ai') {
            $enriched = $this->aiEnrich($ai, $text, $proposal['bloom_level_served'] ?? null);
            unset($enriched['knowledge_type']);   // not a content field
            if ($enriched !== []) {
                $proposal = array_merge($proposal, $enriched);
            }
        }

        return $proposal;
    }

    /**
     * Bloom classification from the command verb.
     *
     * @return array{0:?string,1:float,2:?string} [level, confidence, matched phrase]
     */
    protected function classifyBloom(string $text): array
    {
        $t = ' ' . strtolower(preg_replace('/\s+/', ' ', $text)) . ' ';
        $head = substr($t, 0, 120);   // the command verb lives near the start

        foreach ($this->verbMap as $level => $phrases) {
            foreach ($phrases as $p) {
                if (str_contains($head, ' ' . $p)) {
                    // A verb at the start of the stem is strong evidence;
                    // the same verb buried later is weaker.
                    $conf = str_starts_with(trim($head), $p) ? 0.8 : 0.65;

                    return [$level, $conf, $p];
                }
            }
        }

        // Fall back to a whole-text scan at lower confidence before giving up.
        foreach ($this->verbMap as $level => $phrases) {
            foreach ($phrases as $p) {
                if (str_contains($t, ' ' . $p)) {
                    return [$level, 0.45, $p];
                }
            }
        }

        // No command verb. Before giving up, try STRUCTURAL evidence — the
        // highest-traffic items in this estate are verbless fill-in-the-blank
        // stems ("GUI stands for ______", "1 byte is equal to ____ bits").
        // A verb-only classifier skips ~85% of them, which would leave the
        // busiest content in the system untagged.
        return $this->classifyStructural($t);
    }

    /**
     * Bloom classification from sentence structure rather than a command verb.
     *
     * @return array{0:?string,1:float,2:?string}
     */
    protected function classifyStructural(string $t): array
    {
        $hasBlank = (bool) preg_match('/_{2,}|\.{4,}/', $t);

        // A blank inside a calculation is an application item, not recall.
        $isCalculation = (bool) preg_match('/\d+\s*[\+\-\×\*\/÷]\s*\d+|how (much|many)|total (of|is)|find the (value|sum|area|cost)/', $t);

        if ($hasBlank && $isCalculation) {
            return ['apply', 0.6, 'blank_with_calculation'];
        }

        // Definition/recognition frames — recall regardless of a blank.
        $recallFrames = [
            'stands for', 'is called', 'is known as', 'is an example of',
            'is equal to', 'full form of', 'abbreviation of', 'was developed',
            'is the smallest', 'is the largest', 'is the main',
        ];
        foreach ($recallFrames as $frame) {
            if (str_contains($t, $frame)) {
                return ['recall', 0.7, $frame];
            }
        }

        // True/false and matching are recognition tasks.
        if (preg_match('/\btrue or false\b|\bmatch the follow/', $t)) {
            return ['recall', 0.65, 'recognition_format'];
        }

        // A bare blank with no other signal is most likely a recall cloze, but
        // the evidence is weak — low confidence sends it to the top of the
        // reviewer's queue rather than quietly into the routing engine.
        if ($hasBlank) {
            return ['recall', 0.4, 'bare_blank'];
        }

        // Genuinely no evidence. Propose nothing — a default of "recall" here
        // would silently mis-route items whose demand we cannot read.
        return [null, 0.0, null];
    }

    protected function classifyContext(string $text): ?string
    {
        $t = strtolower($text);

        foreach ($this->contextMap as $context => $keywords) {
            foreach ($keywords as $k) {
                if (str_contains($t, $k)) {
                    return $context;
                }
            }
        }

        return null;
    }

    protected function classifyKnowledgeType(string $text, string $bloom): string
    {
        $t = strtolower($text);

        if (preg_match('/\b(steps?|method|procedure|algorithm|calculate|solve)\b/', $t)) {
            return 'procedural';
        }
        if (preg_match('/\b(why|explain|reason|because|relationship)\b/', $t)) {
            return 'conceptual';
        }

        return in_array($bloom, ['recall'], true) ? 'factual' : 'conceptual';
    }

    /**
     * Difficulty band from the observed pass rate. Inverted: a low pass rate
     * means a hard item.
     */
    protected function difficultyFromPValue(float $p): int
    {
        return match (true) {
            $p >= 0.85 => 1,
            $p >= 0.70 => 2,
            $p >= 0.50 => 3,
            $p >= 0.30 => 4,
            default => 5,
        };
    }

    /**
     * Devanagari/Gujarati/Tamil/Telugu block detection. Enough to separate
     * vernacular content from English; not a full language identifier.
     */
    protected function detectLanguage(string $text): string
    {
        return match (true) {
            (bool) preg_match('/[\x{0A80}-\x{0AFF}]/u', $text) => 'gu',
            (bool) preg_match('/[\x{0B80}-\x{0BFF}]/u', $text) => 'ta',
            (bool) preg_match('/[\x{0C00}-\x{0C7F}]/u', $text) => 'te',
            (bool) preg_match('/[\x{0980}-\x{09FF}]/u', $text) => 'bn',
            (bool) preg_match('/[\x{0C80}-\x{0CFF}]/u', $text) => 'kn',
            (bool) preg_match('/[\x{0D00}-\x{0D7F}]/u', $text) => 'ml',
            // Devanagari covers both Hindi and Marathi; Hindi is the safer default
            // and a reviewer corrects it. Marked as a proposal like everything else.
            (bool) preg_match('/[\x{0900}-\x{097F}]/u', $text) => 'hi',
            default => config('pal_content.default_language'),
        };
    }

    /**
     * Flesch-Kincaid grade level (spec §9.1 "reading level display").
     * English-only; returns null for vernacular text where the syllable
     * heuristic does not apply.
     */
    protected function fleschKincaid(string $text): ?float
    {
        if (preg_match('/[\x{0900}-\x{0DFF}]/u', $text)) {
            return null;
        }

        $sentences = max(1, preg_match_all('/[.!?]+/', $text));
        preg_match_all('/[a-zA-Z]+/', $text, $m);
        $words = $m[0] ?? [];

        if (count($words) < 5) {
            return null;
        }

        $syllables = 0;
        foreach ($words as $w) {
            $syllables += max(1, preg_match_all('/[aeiouy]+/i', strtolower(rtrim($w, 'e'))));
        }

        $wordCount = count($words);
        $fk = 0.39 * ($wordCount / $sentences) + 11.8 * ($syllables / $wordCount) - 15.59;

        return round(max(0.0, min(18.0, $fk)), 2);
    }

    protected function formatFromFileType(?string $fileType, ?string $url): string
    {
        $t = strtolower((string) $fileType);
        $u = strtolower((string) $url);

        return match (true) {
            str_contains($t, 'video') || str_contains($u, 'youtu') || str_contains($u, 'vimeo') => 'video',
            str_contains($t, 'audio') || str_contains($t, 'mp3') => 'story_audio',
            str_contains($t, 'pdf') || str_contains($t, 'doc') => 'pdf',
            str_contains($t, 'h5p') => 'h5p',
            str_contains($t, 'image') || str_contains($t, 'png') || str_contains($t, 'jpg') => 'text_diagram',
            $u !== '' => 'external',
            default => 'text_diagram',
        };
    }

    /**
     * Map to the 4-type model (spec §1). content_master.content_category holds 21
     * tenant-specific values, so it is used as evidence rather than as a mapping.
     */
    protected function contentTypeFor($row): string
    {
        $c = strtolower((string) $row->content_category . ' ' . (string) $row->title);

        return match (true) {
            str_contains($c, 'assess') || str_contains($c, 'test') || str_contains($c, 'exam') || str_contains($c, 'quiz') => 'assessment',
            str_contains($c, 'practice') || str_contains($c, 'worksheet') || str_contains($c, 'exercise') => 'practice',
            str_contains($c, 'remedial') || str_contains($c, 'correct') => 'corrective',
            default => 'concept',
        };
    }

    /**
     * Bloom and pedagogy tags the LMS already carries for these content ids.
     * These are human choices already made — inherit, never re-guess.
     */
    protected function inheritedTags(array $contentIds): array
    {
        if ($contentIds === []) {
            return [];
        }

        $pedagogyIds = DB::table('lms_mapping_type')
            ->where('parent_id', (int) config('pal_content.pedagogy_source.parent_id'))
            ->pluck('id')->all();

        $rows = DB::table('content_mapping_type')
            ->whereIn('content_id', $contentIds)
            ->get(['content_id', 'mapping_value_id']);

        $out = [];
        foreach ($rows as $r) {
            $bloom = PalVocabulary::bloomFromMappingTypeId((int) $r->mapping_value_id);
            if ($bloom !== null) {
                $out[$r->content_id]['bloom'] = $bloom;
            }
            if (in_array((int) $r->mapping_value_id, $pedagogyIds, true)) {
                $out[$r->content_id]['pedagogy'] = (int) $r->mapping_value_id;
            }
        }

        return $out;
    }

    /**
     * Ask the LLM for the fields a verb list cannot reach. Anything the model
     * returns that is not in the closed vocabulary is dropped here rather than
     * failing validation downstream.
     */
    protected function aiEnrich(AIOrchestrationService $ai, string $text, ?string $bloom): array
    {
        $contexts = implode('|', config('pal_content.cultural_contexts'));
        $knowledge = implode('|', config('pal_content.knowledge_types'));

        $prompt = <<<PROMPT
        You are tagging an Indian K-12 assessment item for an adaptive learning system.
        Return ONLY a JSON object, no prose, with these keys:
          cultural_context: one of {$contexts}
          knowledge_type: one of {$knowledge}
          misconception_candidates: array of up to 3 lower_snake_case tags naming the
            likely wrong reasoning a student would use. Empty array if none is obvious.
        The item's Bloom level has already been determined as: {$bloom}.
        Item text: {$text}
        PROMPT;

        $response = $ai->batchProcess([['prompt' => $prompt]])[0] ?? null;

        if (! $response || ! empty($response['error'])) {
            return [];
        }

        $json = json_decode($this->extractJson((string) ($response['content'] ?? '')), true);
        if (! is_array($json)) {
            return [];
        }

        $out = [];

        if (in_array($json['cultural_context'] ?? null, config('pal_content.cultural_contexts'), true)) {
            $out['cultural_context'] = $json['cultural_context'];
        }
        if (in_array($json['knowledge_type'] ?? null, config('pal_content.knowledge_types'), true)) {
            $out['knowledge_type'] = $json['knowledge_type'];
        }

        $tags = array_filter(
            (array) ($json['misconception_candidates'] ?? []),
            fn ($t) => is_string($t) && preg_match('/^[a-z][a-z0-9_]{2,95}$/', $t)
        );
        if ($tags !== []) {
            $out['misconception_tags'] = array_values(array_slice($tags, 0, 3));
        }

        return $out;
    }

    /** Models like to wrap JSON in prose or a fence. Pull the object out. */
    protected function extractJson(string $s): string
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $s, $m)) {
            return $m[0];
        }

        return $s;
    }

    protected function aiAvailable(): bool
    {
        $headers = config('openrouter.headers', []);
        $auth = $headers['Authorization'] ?? '';

        return config('openrouter.base_url') && trim(str_replace('Bearer', '', $auth)) !== '';
    }

    protected function persist(string $estate, $row, array $proposal): void
    {
        $tenantId = (int) $row->sub_institute_id;
        $proposal['scope'] = $tenantId === 0 ? 'global' : 'tenant';

        if ($estate === 'questions') {
            $existing = QuestionMetadata::where('question_id', $row->id)
                ->where('sub_institute_id', $tenantId)->first();

            if ($existing) {
                // Never overwrite a human's work with a machine proposal.
                if ($existing->tagged_by === 'human' || $existing->quality_status !== 'draft') {
                    return;
                }
                $existing->fill($proposal)->save();

                return;
            }

            QuestionMetadata::create(array_merge($proposal, [
                'question_id' => $row->id,
                'sub_institute_id' => $tenantId,
            ]));

            return;
        }

        $existing = ContentMetadata::where('content_master_id', $row->id)
            ->where('sub_institute_id', $tenantId)->first();

        if ($existing) {
            if ($existing->tagged_by === 'human' || $existing->quality_status !== 'draft') {
                return;
            }
            $existing->fill($proposal)->save();

            return;
        }

        ContentMetadata::create(array_merge($proposal, [
            'content_master_id' => $row->id,
            'sub_institute_id' => $tenantId,
        ]));
    }
}
