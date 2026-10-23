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
        return is_array($data) && (int) ($data['chapter_id'] ?? 0) === $chapterId && (int) ($data['tenant'] ?? 0) === $tenant ? $data : null;
    }

    /** @param array<string,mixed> $data */
    private function write(array $data): void
    {
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
            if (!is_array($data) || (int) ($data['tenant'] ?? 0) !== $tenant || (int) ($data['chapter_id'] ?? 0) !== $chapterId || ($data['state'] ?? '') === 'discarded') {
                continue;
            }
            $runs[] = $data;
        }
        usort($runs, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $runs;
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

    /** Runs the pipeline. Never throws: a failure becomes the run's state. */
    public function execute(int $chapterId, int $tenant, string $run): void
    {
        $status = $this->read($chapterId, $tenant, $run);
        if ($status === null || $status['state'] !== 'queued') {
            return;
        }
        $status['state'] = 'running';
        $this->write($status);

        $bundle = $this->runDir($chapterId, $run) . '/bundle';
        $out = new BufferedOutput();
        try {
            // The story format is the same pipeline built with the story template; the teaching method shapes the story.
            $args = ['chapter' => $chapterId, '--tenant' => $tenant, '--out' => $bundle, '--teaching-method' => $status['method']];
            if (($status['format'] ?? '') === 'branching_scenario') {
                $args['--template'] = \App\Services\StudyDeck\Story\StoryTemplate::KEY;
            } else {
                // Slides: every concept slide must show a picture or diagram, and the model is the configured Claude one or else Gemini.
                $args['--require-concept-visuals'] = true;
                $args['--max-visuals'] = (int) config('course_presentation_pilot.generation.max_visuals', 24);
                $args['--repair-rounds'] = (int) config('course_presentation_pilot.generation.repair_rounds', 4);
                $args['--executor'] = app(ModelAvailability::class)->slidesExecutor();
            }
            $code = ($this->artisan)('lms:generate-study-deck', $args, $out);
        } catch (\Throwable $e) {
            $code = 1;
            $out->writeln($e->getMessage());
        }

        $status['log'] = array_slice(array_values(array_filter(array_map('trim', explode("\n", $out->fetch())))), -30);
        $report = is_file($bundle . '/report.json') ? json_decode((string) file_get_contents($bundle . '/report.json'), true) : null;

        $pictures = $code === 0 && is_array($report) && !empty($report['ok']) ? $this->imageReadiness->deckImages($bundle, $tenant) : null;

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
            $status['error'] = self::clean(is_array($report) && !empty($report['errors'])
                ? 'The generated deck did not pass validation: ' . implode(' ', array_slice((array) $report['errors'], 0, 3))
                : 'Generation did not finish. ' . (end($status['log']) ?: 'No details were recorded.'));
        }
        $this->write($status);
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
        foreach ((new PresentationQuality())->check($deck, $source === null ? null : array_keys($source), $source ?? []) as $p) {
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

        $out = new BufferedOutput();
        try {
            $code = ($this->artisan)('lms:store-study-deck', ['chapter' => $chapterId, '--bundle' => $bundle, '--tenant' => $tenant, '--user' => $userId, '--profile' => $profile, '--teaching-method' => $method], $out);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'The draft could not be stored: ' . $e->getMessage(), 'status' => $status];
        }
        $row = $code === 0 ? $this->storedPresentation($chapterId, $tenant, $format, $method) : null;
        if ($row === null) {
            return ['ok' => false, 'message' => 'The draft could not be stored. ' . (trim($out->fetch()) ?: 'No details were recorded.'), 'status' => $status];
        }

        // The store workflow links every picture the deck uses to the stored deck (study_deck_image_links). Check it
        // did: a picture with no link looks unused to the library's clean-up and could be removed from under the deck.
        $wanted = $this->imageReadiness->deckImages($bundle, $tenant)['ids'];
        $linked = array_values(array_intersect($wanted, (new StudyDeckImages())->linkedImageIds((int) $row['id'])));

        $status['state'] = 'stored';
        $status['stored'] = ['content_id' => (int) $row['id'], 'deep_link' => ContentGenerationService::studyDeckDeepLink($row), 'images' => ['referenced' => count($wanted), 'linked' => count($linked)]];
        $this->write($status);

        $ok = count($linked) === count($wanted);

        return ['ok' => true, 'message' => $ok ? 'Stored.' : sprintf('Stored, but %d of %d pictures are not linked to the stored deck. Ask an administrator to check them before the library is cleaned up.', count($wanted) - count($linked), count($wanted)), 'status' => $status];
    }

    public function discard(int $chapterId, int $tenant, string $run): ?array
    {
        $status = $this->read($chapterId, $tenant, $run);
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
