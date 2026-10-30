<?php

namespace App\Services\CoursePresentation;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckPublisher;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * One Course Presentation generation run: a draft made by the EXISTING study-deck pipeline, kept in its own review
 * folder, and stored only when a teacher approves it.
 *
 *   request()  -> queued     checks nothing is already in progress, creates the run folder
 *   execute()  -> running    runs `lms:generate-study-deck` (the pipeline, with the chosen teaching method) into
 *                            <run>/bundle, never into the stored decks' folder
 *              -> ready      the pipeline's own validation passed, and the teaching method was followed
 *              -> failed     anything else (the reason is kept, never a credential)
 *   approve()  -> stored     runs `lms:store-study-deck` on that bundle: the existing storage workflow, the same
 *                            command an administrator would run after reviewing a bundle
 *   discard()  -> discarded  the draft is dropped; nothing was stored
 *
 * State lives in <run>/status.json: no table, no migration. A run belongs to one chapter and one school, and the
 * run id is checked against a strict pattern before it ever becomes part of a path.
 */
class GenerationRun
{
    public const ACTIVE = ['queued', 'running', 'ready'];
    private const STALE_AFTER_SECONDS = 1800;
    /** A saving mark older than this belongs to a request that died; approval may be tried again. */
    private const SAVING_LOCK_SECONDS = 600;

    /**
     * @param Closure(string $command, array<string,mixed> $args, BufferedOutput $out):int|null $artisan the command runner (tests replace it)
     * @param Closure(int $chapterId):(array<int,string>|null)|null $sourceConcepts the chapter's own concepts (id => name) a complete deck must
     *        teach; null from it means "could not be read", which is reported and never counted as verified (tests replace it)
     */
    public function __construct(private ?Closure $artisan = null, private ?ImageReadiness $imageReadiness = null, private ?Closure $sourceConcepts = null)
    {
        $this->artisan ??= fn (string $command, array $args, BufferedOutput $out): int => Artisan::call($command, $args, $out);
        $this->imageReadiness ??= new ImageReadiness();
        $this->sourceConcepts ??= fn (int $chapterId): array => (new SourceLoader())->conceptNames($chapterId);
    }

    public static function validId(string $run): bool
    {
        return (bool) preg_match('/^r[0-9a-f]{12}$/', $run);
    }

    private function chapterDir(int $chapterId): string
    {
        return storage_path((string) config('course_presentation_pilot.generation.run_dir', 'app/course-presentation') . '/chapter-' . $chapterId);
    }

    private function runDir(int $chapterId, string $run): string
    {
        return $this->chapterDir($chapterId) . '/' . $run;
    }

    /** @return array<string,mixed>|null */
    public function read(int $chapterId, int $tenant, string $run): ?array
    {
        if (!self::validId($run)) {
            return null;
        }
        $path = $this->runDir($chapterId, $run) . '/status.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        // A run is found only under its own chapter AND school.
        return is_array($data) && (int) ($data['chapter_id'] ?? 0) === $chapterId && (int) ($data['tenant'] ?? 0) === $tenant
            ? $this->failIfStale($data)
            : null;
    }

    /** @param array<string,mixed> $data */
    private function write(array $data): void
    {
        // A run that was failed or discarded (by the watchdog, or by a teacher) is not brought back to life by the worker still writing to it.
        $path = $this->runDir((int) $data['chapter_id'], (string) $data['run']) . '/status.json';
        if (in_array($data['state'] ?? '', ['queued', 'running', 'ready'], true) && is_file($path)) {
            $disk = json_decode((string) @file_get_contents($path), true);
            if (is_array($disk) && in_array($disk['state'] ?? '', ['failed', 'discarded'], true)) {
                return;
            }
        }
        $data['updated_at'] = date('c');
        file_put_contents($this->runDir((int) $data['chapter_id'], (string) $data['run']) . '/status.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * True when the run was made for exactly this teaching method and content format. A run written before formats were
     * recorded is an interactive-slides run, as summary() already reports it.
     *
     * @param array<string,mixed> $status
     */
    public static function matches(array $status, string $method, string $format): bool
    {
        return (string) ($status['method'] ?? '') === $method && (string) ($status['format'] ?? 'interactive_slides') === $format;
    }

    /**
     * Every run of the chapter and school that is not discarded, newest first. Only status files are read.
     *
     * @return array<int,array<string,mixed>>
     */
    private function runs(int $chapterId, int $tenant): array
    {
        $runs = [];
        foreach (glob($this->chapterDir($chapterId) . '/r*/status.json') ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data) || !self::validId((string) ($data['run'] ?? '')) || (int) ($data['tenant'] ?? 0) !== $tenant || (int) ($data['chapter_id'] ?? 0) !== $chapterId || ($data['state'] ?? '') === 'discarded') {
                continue;
            }
            $data = $this->failIfStale($data);
            $runs[] = $data;
        }
        usort($runs, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $runs;
    }

    /**
     * The run whose approval stored this content item, or null (a deck stored by hand, or before runs existed, has none).
     * Read from the runs' own status files, so the link is the one recorded when the item was stored.
     */
    public function runStoring(int $chapterId, int $tenant, int $contentId): ?string
    {
        foreach ($this->runs($chapterId, $tenant) as $run) {
            if (($run['state'] ?? '') === 'stored' && (int) ($run['stored']['content_id'] ?? 0) === $contentId) {
                return (string) $run['run'];
            }
        }

        return null;
    }

    /** A worker that stopped updating a run must not leave it blocking this chapter forever. */
    private function failIfStale(array $status): array
    {
        if (!in_array($status['state'] ?? '', ['queued', 'running'], true)) {
            return $status;
        }
        $lastUpdate = strtotime((string) ($status['updated_at'] ?? $status['created_at'] ?? ''));
        if ($lastUpdate === false || time() - $lastUpdate <= self::STALE_AFTER_SECONDS) {
            return $this->failIfStuckInStage($status);
        }

        $wasQueued = $status['state'] === 'queued';
        $status['state'] = 'failed';
        $status['error'] = $wasQueued
            ? 'The generation worker did not start within 30 minutes. Start a new draft.'
            : 'Generation stopped reporting progress for more than 30 minutes. Start a new draft.';
        $this->write($status);
        app(\Illuminate\Bus\UniqueLock::class)->release(
            new \App\Jobs\GenerateCoursePresentationJob(
                (int) $status['chapter_id'],
                (int) $status['tenant'],
                (string) $status['run'],
            ),
        );

        return $status;
    }

    /**
     * A run that keeps reporting but has sat in ONE stage far too long (a stage that never ends is a stuck run, however busy its log looks) is
     * failed with the stage named. The worker's own later writes cannot bring it back (see write()).
     *
     * @param array<string,mixed> $status
     * @return array<string,mixed>
     */
    private function failIfStuckInStage(array $status): array
    {
        $cap = (int) config('course_presentation_pilot.generation.stage_max_seconds', 900);
        $stage = (string) ($status['stage'] ?? '');
        $since = isset($status['stage_times'][$stage]) ? strtotime((string) $status['stage_times'][$stage]) : false;
        if (($status['state'] ?? '') !== 'running' || $cap <= 0 || $since === false || time() - $since <= $cap) {
            return $status;
        }
        $label = GenerationProgress::describe($status, null, 'active')['stages'][array_search($stage, GenerationProgress::stageKeys(), true) ?: 0]['label'] ?? $stage;
        $status['state'] = 'failed';
        $status['error'] = sprintf('The run spent more than %d minutes in "%s" and was stopped. Nothing was stored. Start a new draft.', (int) round($cap / 60), $label);
        $status['finished_at'] = date('c');
        $this->write($status);
        try {
            app(\Illuminate\Bus\UniqueLock::class)->release(new \App\Jobs\GenerateCoursePresentationJob((int) $status['chapter_id'], (int) $status['tenant'], (string) $status['run']));
        } catch (\Throwable) {
        }

        return $status;
    }

    /**
     * Fails a run that has not started (or is running) with a reason the teacher can read. Used by the job when it cannot honestly run.
     */
    public function failRun(int $chapterId, int $tenant, string $run, string $reason): void
    {
        $status = $this->read($chapterId, $tenant, $run);
        if ($status === null || !in_array($status['state'], ['queued', 'running'], true)) {
            return;
        }
        $status['state'] = 'failed';
        $status['error'] = $reason;
        $status['finished_at'] = date('c');
        $this->write($status);
    }

    /** True when a file of the generation pipeline was changed after this process started: a long-running worker still holds the older code. */
    public function codeChangedSince(int $startedAt): bool
    {
        foreach (['Services/CoursePresentation/GenerationRun.php', 'Services/CoursePresentation/GenerationProgress.php', 'Services/StudyDeck/StudyDeckService.php', 'Services/StudyDeck/ImagePlanner.php', 'Services/StudyDeck/SlideContentGenerator.php', 'Services/StudyDeck/InteractionPlanner.php', 'Services/StudyDeck/SlidePlanner.php', 'Console/Commands/LMS/GenerateStudyDeckCommand.php'] as $file) {
            $path = app_path($file);
            if (is_file($path) && (int) filemtime($path) > $startedAt + 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Newest run of the chapter and school that is not discarded, or null. With a teaching method AND a content format,
     * only a run made for exactly that choice (a draft of another format is never offered for this one).
     *
     * @return array<string,mixed>|null
     */
    public function latest(int $chapterId, int $tenant, ?string $method = null, ?string $format = null): ?array
    {
        foreach ($this->runs($chapterId, $tenant) as $run) {
            if ($method === null || $format === null || self::matches($run, $method, $format)) {
                return $run;
            }
        }

        return null;
    }

    /**
     * A run that is queued, running or waiting for approval. With a teaching method AND a content format, the newest such
     * run made for exactly that choice: a draft of another format or method does not stand in the way of this one.
     *
     * @return array<string,mixed>|null
     */
    public function active(int $chapterId, int $tenant, ?string $method = null, ?string $format = null): ?array
    {
        if ($method === null || $format === null) {
            $latest = $this->latest($chapterId, $tenant);

            return $latest !== null && in_array($latest['state'], self::ACTIVE, true) ? $latest : null;
        }
        foreach ($this->runs($chapterId, $tenant) as $run) {
            if (self::matches($run, $method, $format) && in_array($run['state'], self::ACTIVE, true)) {
                return $run;
            }
        }

        return null;
    }

    /**
     * The run of this chapter and school that is being made right now (queued or running), whatever its choice. The
     * background job is unique per chapter and school, so a second one started meanwhile would be dropped and its run would
     * wait for ever: a draft in the making blocks every new generation of the chapter, a finished draft only its own choice.
     *
     * @return array<string,mixed>|null
     */
    public function inFlight(int $chapterId, int $tenant): ?array
    {
        foreach ($this->runs($chapterId, $tenant) as $run) {
            if (in_array($run['state'], ['queued', 'running'], true)) {
                return $run;
            }
        }

        return null;
    }

    /** @return array<string,mixed> the new run's status */
    public function request(int $chapterId, int $tenant, ?int $userId, string $method, string $format = 'interactive_slides'): array
    {
        $run = 'r' . bin2hex(random_bytes(6));
        mkdir($this->runDir($chapterId, $run), 0775, true);
        $status = ['run' => $run, 'chapter_id' => $chapterId, 'tenant' => $tenant, 'user_id' => $userId, 'method' => $method, 'format' => $format,
            'state' => 'queued', 'created_at' => date('c'), 'log' => [], 'error' => null, 'report' => null, 'outline' => null, 'stored' => null];
        $this->write($status);

        return $status;
    }

    /**
     * Writes a run's status back (used by the draft picture editor after it changed the draft's files).
     *
     * @param array<string,mixed> $status
     */
    public function save(array $status): void
    {
        $this->write($status);
    }

    /** Runs the pipeline. Never throws: a failure becomes the run's state. */
    public function execute(int $chapterId, int $tenant, string $run): void
    {
        $status = $this->read($chapterId, $tenant, $run);
        if ($status === null || $status['state'] !== 'queued') {
            return;
        }
        $status['state'] = 'running';
        $status['started_at'] = date('c');
        $status['stage'] = 'prepare';
        $status['stage_times'] = ['queued' => $status['created_at'], 'prepare' => $status['started_at']];
        $this->write($status);

        $bundle = $this->runDir($chapterId, $run) . '/bundle';
        $out = new class(function (string $message) use (&$status): void {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $message) ?: [])));
            if ($lines === []) {
                return;
            }
            $status['log'] = array_slice(array_merge((array) ($status['log'] ?? []), $lines), -30);
            GenerationProgress::advance($status, $lines);
            $this->write($status);
        }) extends BufferedOutput {
            private Closure $onWrite;

            public function __construct(Closure $onWrite)
            {
                parent::__construct();
                $this->onWrite = $onWrite;
            }

            protected function doWrite(string $message, bool $newline): void
            {
                parent::doWrite($message, $newline);
                ($this->onWrite)($message);
            }
        };
        try {
            // The story format is the same pipeline built with the story template; the teaching method shapes the story.
            $args = ['chapter' => $chapterId, '--tenant' => $tenant, '--out' => $bundle, '--teaching-method' => $status['method']];
            if (($status['format'] ?? '') === 'branching_scenario') {
                $args['--template'] = \App\Services\StudyDeck\Story\StoryTemplate::KEY;
            } else {
                // Slides: every concept slide must show a picture or diagram, and the model is the configured Claude one or else Gemini.
                $args['--require-concept-visuals'] = true;
                $args['--require-every-slide-image'] = true;
                // The request is capped at 35 meaningful slides. Allow enough visuals for every teaching slide
                // (the planner limits itself to concept_intro/concept_visual slides); the final deck validator
                // still rejects missing, unreadable or over-limit images.
                $args['--max-visuals'] = 35;
                $args['--repair-rounds'] = (int) config('course_presentation_pilot.generation.repair_rounds', 4);
                $args['--executor'] = app(ModelAvailability::class)->slidesExecutor();
                if (config('course_presentation_pilot.generation.images.fast', true)) {
                    $args['--fast-images'] = true;
                }
                $args['--target-seconds'] = (int) config('course_presentation_pilot.generation.target_seconds', 60);
                $args['--hard-limit-seconds'] = (int) config('course_presentation_pilot.generation.hard_limit_seconds', 240);
            }
            $code = ($this->artisan)('lms:generate-study-deck', $args, $out);
        } catch (\Throwable $e) {
            $code = 1;
            $out->writeln($e->getMessage());
        }

        $commandOutput = $out->fetch();
        $status['log'] = array_slice(array_values(array_filter(array_map('trim', explode("\n", $commandOutput)))), -30);
        // The command has returned: what follows (readable pictures, quality rules, coverage) is the final verification.
        if ($code === 0) {
            $status['stage'] = 'verify';
            $status['stage_times']['verify'] = date('c');
            $this->write($status);
        }
        $report =is_file($bundle . '/report.json') ? json_decode((string) file_get_contents($bundle . '/report.json'), true) : null;

        $pictures = $code === 0 && is_array($report) && !empty($report['ok']) ? $this->imageReadiness->deckImages($bundle, $tenant) : null;
        $missingSlideImages = $code === 0 && is_array($report) && !empty($report['ok']) && ($status['format'] ?? '') === 'interactive_slides' ? $this->slidesWithoutReadableImages($bundle, $tenant) : [];

        // The same presentation rules the source-driven preview is held to (repeated topic lists, empty or overfull slides,
        // unplayable interactions, unshowable pictures, concepts nobody teaches). Structural faults stop the draft; the rest are reported.
        [$blocking, $advice, $coverage] = $code === 0 && is_array($report) && !empty($report['ok']) ? $this->quality($bundle, $chapterId) : [[], [], null];
        $status['coverage'] = $coverage;

        if ($blocking !== []) {
            $status['state'] = 'failed';
            $status['error'] = 'The generated deck did not meet the presentation quality rules: ' . implode(' ', array_slice(array_map(fn ($p) => ($p['slide'] ? 'Slide ' . $p['slide'] . ': ' : '') . $p['message'], $blocking), 0, 3));
        } elseif ($pictures !== null && $pictures['unreadable'] > 0) {
            // A deck whose pictures cannot be read would show broken-image placeholders: it is never offered for review.
            $status['state'] = 'failed';
            $status['error'] = sprintf('The draft names %d picture(s) that the library cannot give back (%d of %d are readable), so it was not offered for review.', $pictures['unreadable'], $pictures['resolved'], $pictures['referenced']);
        } elseif ($missingSlideImages !== []) {
            $status['state'] = 'failed';
            $status['error'] = 'Every slide must have a readable image. The following slides have none: ' . implode(', ', array_slice($missingSlideImages, 0, 12)) . (count($missingSlideImages) > 12 ? ', and others' : '.') . ' Add a relevant image or a generated diagram and try again.';
        } elseif ($code === 0 && is_array($report) && !empty($report['ok']) && !empty($report['teaching_method']['followed']) && !$this->readsBack($bundle)) {
            // "Ready" means the draft is on disk and can be opened again, not that the model answered.
            $status['state'] = 'failed';
            $status['error'] = 'The draft was generated but could not be read back from where it was saved, so it was not offered for review.';
        } elseif ($code === 0 && is_array($report) && !empty($report['ok']) && !empty($report['teaching_method']['followed'])) {
            $status['state'] = 'ready';
            $status['images'] = array_diff_key($pictures ?? [], ['ids' => 1]);
            $status['report'] = [
                'slides' => (int) ($report['stats']['slides'] ?? 0),
                'interactive_slides' => (int) ($report['stats']['interactive_slides'] ?? 0),
                'concepts' => (int) ($report['stats']['concepts'] ?? 0),
                'warnings' => count((array) ($report['warnings'] ?? [])),
                'method_followed' => true,
                'quality_notes' => count($advice),
            ];
            $status['outline'] = $this->outline($bundle);
        } else {
            $status['state'] = 'failed';
            $detail = is_array($report) && !empty($report['errors'])
                ? 'The generated deck did not pass validation: ' . implode(' ', array_slice((array) $report['errors'], 0, 3))
                : $this->failureDetail($commandOutput);
            $status['error'] = self::clean($detail);
        }
        // The real time this run took, kept so later runs of the same kind can offer an honest estimate.
        $status['finished_at'] = date('c');
        $status['duration_seconds'] = max(1, strtotime($status['finished_at']) - strtotime((string) $status['started_at']));
        if ($status['state'] === 'ready') {
            $status['stage'] = 'ready';
            $status['stage_times']['ready'] = $status['finished_at'];
        }
        // Where the time went, kept with the run and written to the log so a slow run can be traced to its stage.
        $status['timings'] = GenerationProgress::timings($status, (int) config('course_presentation_pilot.generation.target_seconds', 60));
        $this->write($status);
        try {
            \Illuminate\Support\Facades\Log::info('course-presentation.run', ['run' => $status['run'], 'chapter' => $chapterId, 'state' => $status['state'], 'format' => $status['format'] ?? null, 'timings' => $status['timings'], 'error' => $status['error'] ?? null]);
        } catch (\Throwable) {
            // A log that cannot be written never changes the run.
        }
    }

    /** The saved draft can be opened again: its deck reads back as non-empty JSON. */
    private function readsBack(string $bundle): bool
    {
        $deck = is_file($bundle . '/deck.json') ? json_decode((string) file_get_contents($bundle . '/deck.json'), true) : null;

        return is_array($deck) && $deck !== [];
    }

    /**
     * Earlier runs of the school that finished a draft, with the time each took. Only status files are read.
     *
     * @return array<int,array<string,mixed>>
     */
    public function finishedRuns(int $tenant): array
    {
        $found = [];
        foreach (glob(storage_path((string) config('course_presentation_pilot.generation.run_dir', 'app/course-presentation')) . '/chapter-*/r*/status.json') ?: [] as $path) {
            $data = json_decode((string) @file_get_contents($path), true);
            if (is_array($data) && (int) ($data['tenant'] ?? 0) === $tenant && !empty($data['duration_seconds']) && in_array($data['state'] ?? '', ['ready', 'stored'], true)) {
                $found[] = array_intersect_key($data, array_flip(['chapter_id', 'method', 'format', 'state', 'duration_seconds']));
            }
        }

        return $found;
    }

    /** Keep the actionable exception/validation text without repeating the progress log in the error alert. */
    private function failureDetail(string $output): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: [])));
        $lines = array_values(array_filter($lines, fn ($line) => !preg_match('/^(Executor:|Gemini model:|(?:load|context|questions|map|plan|content|images|interact|activities|validate)\s+)/i', $line)));
        $detail = trim(implode("\n", $lines));
        if ($detail === '') {
            $detail = end($lines) ?: 'The generation command did not complete and returned no details.';
        }

        return $detail;
    }

    /** Slide numbers with no image address that resolves to a readable image in this school. */
    private function slidesWithoutReadableImages(string $bundle, int $tenant): array
    {
        $deck = is_file($bundle . '/deck.json') ? json_decode((string) file_get_contents($bundle . '/deck.json'), true) : null;
        if (!is_array($deck)) {
            return [];
        }
        $ids = [];
        foreach ((array) ($deck['slides'] ?? []) as $slide) {
            if (($id = StudyDeckImages::idFromRef($slide['image']['url'] ?? null)) !== null) {
                $ids[$id] = $id;
            }
        }
        $metas = (new StudyDeckImages())->visibleMetas(array_values($ids), $tenant);
        $bad = [];
        foreach ((array) ($deck['slides'] ?? []) as $slide) {
            $id = StudyDeckImages::idFromRef($slide['image']['url'] ?? null);
            $meta = $id === null ? null : ($metas[$id] ?? null);
            if (!is_array($meta) || (int) ($meta['width'] ?? 0) < 1 || (int) ($meta['height'] ?? 0) < 1 || (int) ($meta['bytes'] ?? 0) < 1) {
                $bad[] = (int) ($slide['n'] ?? 0);
            }
        }
        return $bad;
    }

    /**
     * @return array{0:array<int,array{code:string,slide:int|null,message:string}>,1:array<int,array{code:string,slide:int|null,message:string}>,2:?array<string,mixed>} blocking faults, advice, coverage of the chapter's own concepts
     */
    private function quality(string $bundle, int $chapterId): array
    {
        $deck = is_file($bundle . '/deck.json') ? json_decode((string) file_get_contents($bundle . '/deck.json'), true) : null;
        if (!is_array($deck)) {
            return [[], [], null];
        }
        // A complete presentation teaches every concept the chapter itself holds, not only those the draft's own map lists.
        // Where the chapter's concepts cannot be read, the deck is held to its own map and the coverage says it was NOT checked.
        $source = $this->sourceConcepts($chapterId);
        $taught = [];
        foreach ((array) ($deck['slides'] ?? []) as $s) {
            foreach ((array) ($s['taught_concept_ids'] ?? []) as $id) {
                $taught[(int) $id] = true;
            }
        }
        $missing = $source === null ? [] : array_values(array_map(fn ($id) => $source[$id], array_values(array_diff(array_keys($source), array_keys($taught)))));
        $coverage = [
            'checked' => $source !== null,
            'source_concepts' => $source === null ? null : count($source),
            'taught' => $source === null ? count($taught) : count($source) - count($missing),
            'missing' => $missing,
        ];
        $blocking = [];
        $advice = [];
        $map = is_file($bundle . '/learning-map.json') ? json_decode((string) file_get_contents($bundle . '/learning-map.json'), true) : null;
        $storyConfig = is_array($map) && is_array($map['story_config'] ?? null) ? $map['story_config'] : null;
        foreach ((new PresentationQuality($storyConfig))->check($deck, $source === null ? null : array_keys($source), $source ?? []) as $p) {
            if (in_array($p['code'], ['repeated_index', 'empty_slide', 'broken_interaction', 'bad_image', 'missing_concept', 'generic_label', 'broken_story'], true)) {
                $blocking[] = $p;
            } else {
                $advice[] = $p;
            }
        }

        return [$blocking, $advice, $coverage];
    }

    /**
     * The chapter's own visible concepts (id => name), or null when they cannot be read or the chapter has none: the caller
     * then reports the coverage as not checked. A failure here is never turned into "covered".
     *
     * @return array<int,string>|null
     */
    private function sourceConcepts(int $chapterId): ?array
    {
        try {
            $found = ($this->sourceConcepts)($chapterId);
        } catch (\Throwable) {
            return null;
        }

        return is_array($found) && $found !== [] ? $found : null;
    }

    /**
     * The deck of a draft that is waiting for review, so a teacher can play it before anything is stored. Null for any other run (queued,
     * failed, discarded, already stored), for another chapter or school, and when the draft's files are gone. Read only.
     *
     * @return array<string,mixed>|null
     */
    public function draftDeck(int $chapterId, int $tenant, string $run): ?array
    {
        $status = $this->read($chapterId, $tenant, $run);
        if ($status === null || $status['state'] !== 'ready') {
            return null;
        }
        $path = $this->runDir($chapterId, $run) . '/bundle/deck.json';
        $deck = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($deck) ? $deck : null;
    }

    /** An error as a teacher may read it: no folder or file path of the server. */
    public static function clean(string $message): string
    {
        $message = preg_replace('/\(raw replies saved to[^)]*\)/i', '', $message);

        return trim((string) preg_replace('~\S*(?:[A-Za-z]:[\\\\/]|storage[\\\\/])\S*~', '[folder]', $message));
    }

    /** @return array<int,array{n:int,title:string,type:string}>|null */
    private function outline(string $bundle): ?array
    {
        $plan = is_file($bundle . '/plan.json') ? json_decode((string) file_get_contents($bundle . '/plan.json'), true) : null;
        if (!is_array($plan) || !is_array($plan['slides'] ?? null)) {
            return null;
        }

        return array_map(fn ($s) => ['n' => (int) $s['n'], 'title' => (string) $s['title'], 'type' => (string) $s['slide_type']], $plan['slides']);
    }

    /**
     * Stores an approved draft with the existing storage workflow. Re-checks everything that could have changed.
     *
     * @return array{ok:bool,message:string,status:?array<string,mixed>}
     */
    public function approve(int $chapterId, int $tenant, string $run, int $userId, string $profile): array
    {
        $status = $this->read($chapterId, $tenant, $run);
        if ($status === null) {
            return ['ok' => false, 'message' => 'That draft was not found.', 'status' => null];
        }
        if ($status['state'] !== 'ready') {
            return ['ok' => false, 'message' => 'Only a draft that passed validation and is waiting for review can be approved.', 'status' => $status];
        }
        // A second approval while the first is still saving would store a duplicate: it is refused, not queued.
        if (!empty($status['saving']) && time() - (int) strtotime((string) $status['saving']) < self::SAVING_LOCK_SECONDS) {
            return ['ok' => false, 'message' => 'This draft is already being saved. Please wait.', 'status' => $status];
        }
        $bundle = $this->runDir($chapterId, $run) . '/bundle';
        $deck = is_file($bundle . '/deck.json') ? json_decode((string) file_get_contents($bundle . '/deck.json'), true) : null;
        $report = is_file($bundle . '/report.json') ? json_decode((string) file_get_contents($bundle . '/report.json'), true) : null;
        if (!is_array($deck) || !is_array($report) || empty($report['ok'])) {
            return ['ok' => false, 'message' => 'The draft is no longer valid and cannot be stored.', 'status' => $status];
        }
        // The same rules once more at approval, so a deck with placeholder headings is never stored.
        [$blocking] = $this->quality($bundle, $chapterId);
        if ($blocking !== []) {
            return ['ok' => false, 'message' => 'The draft does not meet the presentation quality rules and cannot be stored: ' . implode(' ', array_slice(array_map(fn ($p) => ($p['slide'] ? 'Slide ' . $p['slide'] . ': ' : '') . $p['message'], $blocking), 0, 3)), 'status' => $status];
        }
        // No second presentation beside one that is already stored FOR THIS teaching method and format. A presentation stored
        // for another method (or none) is a different item and neither blocks this draft nor is replaced by it.
        $format = (string) ($status['format'] ?? 'interactive_slides');
        $method = (string) ($status['method'] ?? '');
        if ($this->storedPresentation($chapterId, $tenant, $format, $method) !== null) {
            return ['ok' => false, 'message' => 'A presentation is already stored for this chapter, teaching method and format; this draft would be a duplicate.', 'status' => $status];
        }

        // Recorded for as long as the store command runs, so a refresh or a reopened window can say "saving" truthfully.
        $status['saving'] = date('c');
        $this->write($status);
        $unlock = function () use (&$status): void {
            unset($status['saving']);
            $this->write($status);
        };

        $out = new BufferedOutput();
        try {
            $code = ($this->artisan)('lms:store-study-deck', ['chapter' => $chapterId, '--bundle' => $bundle, '--tenant' => $tenant, '--user' => $userId, '--profile' => $profile, '--teaching-method' => $method], $out);
        } catch (\Throwable $e) {
            $unlock();

            return ['ok' => false, 'message' => 'The draft could not be stored: ' . $e->getMessage(), 'status' => $status];
        }
        $row = $code === 0 ? $this->storedPresentation($chapterId, $tenant, $format, $method) : null;
        if ($row === null) {
            $unlock();

            return ['ok' => false, 'message' => 'The draft could not be stored. ' . (trim($out->fetch()) ?: 'No details were recorded.'), 'status' => $status];
        }

        // The store workflow links every picture the deck uses to the stored deck (study_deck_image_links). Check it
        // did: a picture with no link looks unused to the library's clean-up and could be removed from under the deck.
        $wanted = $this->imageReadiness->deckImages($bundle, $tenant)['ids'];
        $linked = array_values(array_intersect($wanted, (new StudyDeckImages())->linkedImageIds((int) $row['id'])));

        $status['state'] = 'stored';
        unset($status['saving']);
        $status['stored_at'] = date('c');
        $status['stored'] = ['content_id' => (int) $row['id'], 'deep_link' => ContentGenerationService::studyDeckDeepLink($row), 'images' => ['referenced' => count($wanted), 'linked' => count($linked)]];
        $this->write($status);

        $ok = count($linked) === count($wanted);

        return ['ok' => true, 'message' => $ok ? 'Stored.' : sprintf('Stored, but %d of %d pictures are not linked to the stored deck. Ask an administrator to check them before the library is cleaned up.', count($wanted) - count($linked), count($wanted)), 'status' => $status];
    }

    /**
     * Reconcile a run after its reviewed, repaired bundle was stored with lms:store-study-deck outside the approval endpoint.
     *
     * @return array{ok:bool,message:string,status:?array<string,mixed>}
     */
    public function reconcileStoredBundle(int $chapterId, int $tenant, string $run, int $contentId, string $bundle): array
    {
        $status = $this->read($chapterId, $tenant, $run);
        if ($status === null) {
            return ['ok' => false, 'message' => 'That draft was not found.', 'status' => null];
        }
        if ($status['state'] === 'stored') {
            return (int) ($status['stored']['content_id'] ?? 0) === $contentId
                ? ['ok' => true, 'message' => 'This draft is already reconciled with its stored resource.', 'status' => $status]
                : ['ok' => false, 'message' => 'This draft is already linked to a different stored resource.', 'status' => $status];
        }
        if (!in_array($status['state'], ['failed', 'ready'], true)) {
            return ['ok' => false, 'message' => 'Only a failed or review-ready draft can be reconciled.', 'status' => $status];
        }

        $bundle = rtrim($bundle, '/\\');
        $deck = is_file($bundle . '/deck.json') ? json_decode((string) file_get_contents($bundle . '/deck.json'), true) : null;
        $report = is_file($bundle . '/report.json') ? json_decode((string) file_get_contents($bundle . '/report.json'), true) : null;
        if (!is_array($deck) || !is_array($report) || empty($report['ok']) || (int) ($deck['chapter']['id'] ?? 0) !== $chapterId) {
            return ['ok' => false, 'message' => 'The supplied bundle is not a validated deck for this chapter.', 'status' => $status];
        }

        $format = (string) ($status['format'] ?? 'interactive_slides');
        $method = (string) ($status['method'] ?? '');
        if (($format === 'branching_scenario') !== \App\Services\StudyDeck\Story\StoryTemplate::isStory($deck)
            || (string) ($report['teaching_method']['key'] ?? '') !== $method
            || empty($report['teaching_method']['followed'])) {
            return ['ok' => false, 'message' => "The supplied bundle does not match this draft's format and teaching method.", 'status' => $status];
        }

        [$blocking, $advice] = $this->quality($bundle, $chapterId);
        if ($blocking !== []) {
            return ['ok' => false, 'message' => 'The supplied bundle does not meet the presentation quality rules.', 'status' => $status];
        }

        $row = $this->storedPresentation($chapterId, $tenant, $format, $method);
        if ($row === null || (int) ($row['id'] ?? 0) !== $contentId) {
            return ['ok' => false, 'message' => 'The stored resource does not match this draft.', 'status' => $status];
        }

        $imageReport = $this->imageReadiness->deckImages($bundle, $tenant);
        $wanted = array_values((array) ($imageReport['ids'] ?? []));
        if (($imageReport['unreadable'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'The stored bundle still references unreadable pictures.', 'status' => $status];
        }
        $linked = $wanted === [] ? [] : array_values(array_intersect($wanted, (new StudyDeckImages())->linkedImageIds($contentId)));
        if (count($linked) !== count($wanted)) {
            return ['ok' => false, 'message' => 'The stored resource does not link every picture in the supplied bundle.', 'status' => $status];
        }

        $status['state'] = 'stored';
        unset($status['error']);
        $status['stored'] = [
            'content_id' => $contentId,
            'deep_link' => ContentGenerationService::studyDeckDeepLink($row),
            'images' => ['referenced' => count($wanted), 'linked' => count($linked)],
        ];
        $status['report'] = [
            'slides' => (int) ($report['stats']['slides'] ?? $deck['slide_count'] ?? 0),
            'interactive_slides' => (int) ($report['stats']['interactive_slides'] ?? 0),
            'concepts' => (int) ($report['stats']['concepts'] ?? 0),
            'warnings' => count((array) ($report['warnings'] ?? [])),
            'method_followed' => true,
            'quality_notes' => count($advice),
        ];
        $status['outline'] = $this->outline($bundle);
        $this->write($status);

        return ['ok' => true, 'message' => 'Stored resource linked to the draft.', 'status' => $status];
    }

    public function discard(int $chapterId, int $tenant, string $run): ?array
    {
        $status = $this->read($chapterId, $tenant, $run);
        // Discarding twice is the same as discarding once: a second click (or a stale screen) is not an error.
        if ($status !== null && $status['state'] === 'discarded') {
            return $status;
        }
        if ($status === null || !in_array($status['state'], ['ready', 'failed', 'queued'], true)) {
            return null;
        }
        $status['state'] = 'discarded';
        $this->write($status);

        return $status;
    }

    /**
     * The visible stored study-deck row of the chapter and school made for exactly this format AND teaching method, if any.
     * With no method it is the deck stored without one (the chapter's original presentation): a deck stored for a method is
     * never returned for another, and never for none.
     *
     * @return array<string,mixed>|null
     */
    public function storedPresentation(int $chapterId, int $tenant, string $format = 'interactive_slides', ?string $method = null): ?array
    {
        // A story lesson and a Classroom Presentation are separate content items: each is a duplicate only of its own kind.
        $story = $format === 'branching_scenario';
        $kind = $story ? 'study\\_story\\_' : 'study\\_deck\\_';
        $delimiter = str_replace('_', '\\_', StudyDeckPublisher::VARIANT_DELIMITER);
        $rows = DB::table('content_master')
            ->where('chapter_id', $chapterId)->where('sub_institute_id', $tenant)
            ->when($story, fn ($q) => $q->where('content_category', \App\Services\StudyDeck\Story\StoryTemplate::CATEGORY),
                fn ($q) => $q->whereIn('content_category', (array) config('course_presentation_pilot.presentation_categories', [])))
            ->when($method !== null,
                fn ($q) => $q->where('filename', 'like', $kind . str_replace('_', '\\_', $method) . $delimiter . '%'),
                fn ($q) => $q->where('filename', 'like', $kind . '%')->where('filename', 'not like', '%' . $delimiter . '%'))
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->orderByDesc('id')->get(['id', 'chapter_id', 'filename'])->map(fn ($r) => (array) $r)->all();
        foreach ($rows as $row) {
            if (ContentGenerationService::studyDeckDeepLink($row) !== null) {
                return $row;
            }
        }

        return null;
    }
}
