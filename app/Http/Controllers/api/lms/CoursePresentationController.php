<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCoursePresentationJob;
use App\Services\CoursePresentation\Templates\ImagePool;
use App\Services\CoursePresentation\Templates\SlideDeckAdapter;
use App\Services\CoursePresentation\Templates\TextbookPicturePool;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\StudyDeckImageUrls;
use App\Services\CoursePresentation\FitValidator;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\ImageReadiness;
use App\Services\CoursePresentation\ModelAvailability;
use App\Services\CoursePresentation\MethodSuitability;
use App\Services\CoursePresentation\Options;
use App\Services\CoursePresentation\PilotScope;
use App\Services\CoursePresentation\SourceLoader;
use App\Services\CoursePresentation\SourceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Course Presentation pilot: the two choices, and a READ-ONLY pre-flight of the chapter's sources.
 *
 * Neither action generates, stores or publishes anything. The school comes from the verified session, never
 * from the request; the chapter must belong to that school and be inside the pilot scope; the sources are
 * found by the server (a client that tries to name sources is refused).
 */
class CoursePresentationController extends Controller
{
    public function __construct(
        private Options $options,
        private PilotScope $scope,
        private SourceLoader $loader,
    ) {
    }

    /** GET lms/course-presentation/options?chapter_id= */
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['chapter_id' => 'required|integer|min:1', 'sources' => 'prohibited']);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }

        // Suitability of every method for this chapter, so the screen can answer the moment a method is chosen.
        $raw = $this->loader->load($chapter);
        $registry = new SourceRegistry((int) config('course_presentation_pilot.min_text_chars', 2000));
        $suitability = (new MethodSuitability())->evaluate($registry->semanticRow($chapter, $raw));

        return response()->json(['status_code' => 1, 'chapter' => ['id' => (int) $chapter['id'], 'name' => $chapter['chapter_name']], 'suitability' => $suitability] + $this->options->forScreen());
    }

    /** GET lms/course-presentation/preflight?chapter_id=&pedagogy=&format= */
    public function preflight(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'pedagogy' => ['required', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required', 'string', Rule::in(array_keys($this->options->formats()))],
            // Sources are never taken from the caller.
            'sources' => 'prohibited',
            'source_ids' => 'prohibited',
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }

        [$found, $methodFit, $fit, $raw] = $this->assess($chapter, $data['pedagogy'], $data['format']);
        // The server decides, not the screen: a combination that is unsuitable or unsupported does not continue.
        if (!$fit['continue_allowed']) {
            return response()->json(['status_code' => 0, 'message' => 'This combination cannot continue. ' . $fit['reason'], 'fit' => $fit], 422);
        }
        $suitability = $methodFit[$data['pedagogy']];
        $capability = $this->options->capability($data['pedagogy'], $data['format']);

        return response()->json([
            'status_code' => 1,
            'chapter' => ['id' => (int) $chapter['id'], 'name' => $chapter['chapter_name']],
            'selection' => [
                'pedagogy' => ['key' => $data['pedagogy'], 'label' => $this->options->pedagogies()[$data['pedagogy']]],
                'format' => ['key' => $data['format'], 'label' => $this->options->formats()[$data['format']]['label']],
            ],
            'fit' => $fit,
            // Only a run made for exactly this teaching method and format: a draft of another choice is never shown under this one.
            'run' => $this->summary(app(GenerationRun::class)->latest((int) $chapter['id'], (int) $chapter['sub_institute_id'], $data['pedagogy'], $data['format'])),
            'suitability' => $suitability,
            'capability' => $capability,
            'sources' => $this->visibleSources($found['sources']),
            'existing_content' => $found['existing_content'],
            'images' => (new ImageReadiness())->forChapter($chapter, $raw),
            'coverage' => $this->coverageReport($found['coverage'], (new ImagePool())->forChapter($this->poolContext($chapter, $found['coverage'], $found['textbook_text']), (int) $chapter['sub_institute_id']), $this->hasOutcomes($found['sources'])),
            // Only what is already stored for THIS format AND teaching method: a presentation is not listed under a story lesson,
            // nor one method's presentation under another method, nor one stored without a method under any.
            'existing_resources' => array_values(array_filter($found['existing_resources'], fn ($r) => ($r['format'] ?? 'interactive_slides') === $data['format'] && ($r['method'] ?? null) === $data['pedagogy'])),
            'not_used' => $found['not_used'],
            'wrote_nothing' => true,
        ]);
    }

    /**
     * POST lms/course-presentation/generate: starts ONE background draft for interactive slides, shaped by the chosen
     * teaching method. Refused unless the server's own fit check says the combination may continue AND can be generated
     * (sources present, a model reachable, nothing stored or in progress already). Stores nothing.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'pedagogy' => ['required', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required', 'string', Rule::in((array) config('course_presentation_pilot.generation.formats', []))],
            'sources' => 'prohibited',
            'source_ids' => 'prohibited',
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        [, , $fit] = $this->assess($chapter, $data['pedagogy'], $data['format']);
        if (!$fit['continue_allowed'] || !$fit['generation_supported']) {
            return response()->json(['status_code' => 0, 'message' => 'A presentation cannot be generated for this combination. ' . implode(' ', $fit['generation_blockers'] ?: [$fit['reason']]), 'fit' => $fit], 422);
        }

        $runs = app(GenerationRun::class);
        $status = $runs->request((int) $chapter['id'], (int) $chapter['sub_institute_id'], (int) $request->session()->get('user_id') ?: null, $data['pedagogy'], $data['format']);
        // A queue worker runs it when there is one; with the synchronous driver it runs right after this response is sent.
        $job = new GenerateCoursePresentationJob((int) $chapter['id'], (int) $chapter['sub_institute_id'], $status['run']);
        config('queue.default') === 'sync' ? dispatch($job)->afterResponse() : dispatch($job);

        return response()->json(['status_code' => 1, 'run' => $this->summary($status)], 202);
    }

    /**
     * GET lms/course-presentation/status?chapter_id=&run= : one run. Without a run, the newest run made for the teaching
     * method and format named in `pedagogy` and `format` (never the chapter's latest of another choice).
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'run' => ['nullable', 'string', 'regex:/^r[0-9a-f]{12}$/'],
            'pedagogy' => ['required_without:run', 'nullable', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required_without:run', 'nullable', 'string', Rule::in(array_keys($this->options->formats()))],
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        $runs = app(GenerationRun::class);
        $status = !empty($data['run'])
            ? $runs->read((int) $chapter['id'], (int) $chapter['sub_institute_id'], $data['run'])
            : $runs->latest((int) $chapter['id'], (int) $chapter['sub_institute_id'], $data['pedagogy'], $data['format']);
        if (!empty($data['run']) && $status === null) {
            return response()->json(['status_code' => 0, 'message' => 'That draft was not found.'], 404);
        }
        // A caller that names both a run and a choice gets the run only if it belongs to that choice.
        if ($status !== null && !empty($data['pedagogy']) && !empty($data['format']) && !GenerationRun::matches($status, $data['pedagogy'], $data['format'])) {
            return response()->json(['status_code' => 0, 'message' => self::WRONG_CHOICE], 422);
        }

        return response()->json(['status_code' => 1, 'run' => $this->summary($status)]);
    }

    private const WRONG_CHOICE = 'That draft was made for a different teaching method or format than the one you chose, so nothing was changed. Choose again, or open the draft for its own choice.';

    /**
     * The draft named by `run`, when it belongs to the chapter and school but NOT to the teaching method and format the
     * caller is acting on: the refusal to send, or null when the run is missing (the service answers that) or matches.
     */
    private function mismatchedChoice(GenerationRun $runs, array $chapter, string $run, string $pedagogy, string $format): ?JsonResponse
    {
        $status = $runs->read((int) $chapter['id'], (int) $chapter['sub_institute_id'], $run);

        return $status !== null && !GenerationRun::matches($status, $pedagogy, $format)
            ? response()->json(['status_code' => 0, 'message' => self::WRONG_CHOICE, 'run' => $this->summary($status)], 422)
            : null;
    }

    /** POST lms/course-presentation/approve: a teacher has reviewed the draft; store it with the existing storage workflow. */
    public function approve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'run' => ['required', 'string', 'regex:/^r[0-9a-f]{12}$/'],
            // The choice the teacher is looking at: a draft made for another one is never stored from this screen.
            'pedagogy' => ['required', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required', 'string', Rule::in(array_keys($this->options->formats()))],
            'reviewed' => 'required|accepted',
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        if (($refused = $this->mismatchedChoice(app(GenerationRun::class), $chapter, $data['run'], $data['pedagogy'], $data['format'])) !== null) {
            return $refused;
        }
        $result = app(GenerationRun::class)->approve(
            (int) $chapter['id'], (int) $chapter['sub_institute_id'], $data['run'],
            (int) $request->session()->get('user_id'), (string) ($request->session()->get('user_profile_name') ?: 'ADMIN')
        );

        return response()->json(['status_code' => $result['ok'] ? 1 : 0, 'message' => $result['message'], 'run' => $this->summary($result['status'])], $result['ok'] ? 200 : 422);
    }

    /** POST lms/course-presentation/discard: drop a draft that was not approved. Nothing was stored. */
    public function discard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'run' => ['required', 'string', 'regex:/^r[0-9a-f]{12}$/'],
            'pedagogy' => ['required', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required', 'string', Rule::in(array_keys($this->options->formats()))],
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        if (($refused = $this->mismatchedChoice(app(GenerationRun::class), $chapter, $data['run'], $data['pedagogy'], $data['format'])) !== null) {
            return $refused;
        }
        $status = app(GenerationRun::class)->discard((int) $chapter['id'], (int) $chapter['sub_institute_id'], $data['run']);

        return $status === null
            ? response()->json(['status_code' => 0, 'message' => 'That draft cannot be discarded.'], 422)
            : response()->json(['status_code' => 1, 'run' => $this->summary($status)]);
    }

    /**
     * What a teacher may see of a run: its state, progress, the draft's outline and validation summary. Never the
     * folder it lives in, the school, the user or the raw command output.
     *
     * @param array<string,mixed>|null $s
     * @return array<string,mixed>|null
     */
    private function summary(?array $s): ?array
    {
        if ($s === null) {
            return null;
        }
        $progress = array_values(array_filter((array) ($s['log'] ?? []), fn ($l) => preg_match('/^(load|context|questions|map|plan|content|images|interact|activities|validate)\s/', (string) $l)));

        return [
            'run' => $s['run'],
            'state' => $s['state'],
            'method' => $s['method'],
            'format' => $s['format'] ?? 'interactive_slides',
            'created_at' => $s['created_at'],
            'progress' => array_slice($progress, -6),
            'error' => $s['error'],
            'report' => $s['report'],
            // How much of the chapter's own concept list the draft teaches; `checked` is false when the list could not be read.
            'coverage' => $s['coverage'] ?? null,
            'outline' => $s['outline'],
            'images' => $s['images'] ?? null,
            'stored' => $s['stored'] === null ? null : ['deep_link' => $s['stored']['deep_link'], 'images' => $s['stored']['images'] ?? null],
        ];
    }
    /**
     * GET lms/course-presentation/draft?chapter_id=&run= : the deck of a draft that is waiting for review, with its pictures as addresses the
     * school may load, so it can be played in the study-deck player BEFORE anything is stored. Read only: nothing is created, stored or published.
     */
    public function draft(Request $request): JsonResponse
    {
        $data = $request->validate(['chapter_id' => 'required|integer|min:1', 'run' => ['required', 'string', 'regex:/^r[0-9a-f]{12}$/']]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        $tenant = (int) $chapter['sub_institute_id'];
        $deck = app(GenerationRun::class)->draftDeck((int) $chapter['id'], $tenant, $data['run']);
        if ($deck === null) {
            return response()->json(['status_code' => 0, 'message' => 'That draft is not waiting for review.'], 404);
        }

        return response()->json([
            'status_code' => 1,
            'notice' => 'This is a draft for review. Nothing has been stored or shown to students yet.',
            'deck' => (new \App\Services\StudyDeck\StudyDeckImageUrls())->hydrate($deck, $tenant),
        ]);
    }

    /**
     * GET lms/course-presentation/preview?chapter_id=&pedagogy=&format= : the chosen format's TEMPLATE, filled from the
     * chapter's stored data. READ ONLY: no AI call, no stored deck, no record or link created, nothing published. It is a
     * preview of source data, not a generated or approved presentation, and it says so.
     *
     * Refused (422) unless the fit check lets the combination continue and a template exists for the format; a format with
     * no template answers with what it would need and whether Create is possible.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'pedagogy' => ['required', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required', 'string', Rule::in(array_keys($this->options->formats()))],
            'sources' => 'prohibited',
            'source_ids' => 'prohibited',
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        [$found, , $fit, $raw] = $this->assess($chapter, $data['pedagogy'], $data['format']);
        if (!$fit['continue_allowed'] || !$fit['template']['available']) {
            return response()->json([
                'status_code' => 0,
                'message' => !$fit['continue_allowed'] ? 'This combination cannot continue. ' . $fit['reason'] : 'There is no template for this format that can be filled from the stored data.',
                'template' => $fit['template'],
                'create' => $fit['create'],
            ], 422);
        }

        // Every concept of the chapter, each explained from the best source that has it (its own intelligence, the textbook
        // where the intelligence has none, both where it is partial): the coverage map, built from the chapter's own rows.
        $registry = new SourceRegistry((int) config('course_presentation_pilot.min_text_chars', 2000));
        $coverage = $registry->coverage($chapter, $raw);
        $pictures = (new ImagePool())->forChapter($this->poolContext($chapter, $coverage, $registry->textbookText($chapter, $raw)), (int) $chapter['sub_institute_id']);
        // Pictures: a verified drawn diagram from an earlier draft where there is one, otherwise the chapter's own textbook picture that shows the
        // concept (curated manifest; no web search, nothing stored). A concept with neither gets none, and its slide says so.
        $book = (new TextbookPicturePool())->forChapter((int) $chapter['id'], $coverage['concepts']);
        $pictures['by_concept'] = $pictures['by_concept'] + $book['by_concept'];
        $built = (new SlideDeckAdapter())->build($chapter, $coverage, $registry->acceptedOutcomes($chapter, $raw), $data['pedagogy'], $pictures['by_concept']);
        // The viewer's own step: pictures become addresses this school may load (signed); nothing is created.
        $deck = (new StudyDeckImageUrls())->hydrate($built['deck'], (int) $chapter['sub_institute_id']);

        return response()->json([
            'status_code' => 1,
            'kind' => 'study_deck',
            'origin' => 'source_data_preview',
            'notice' => 'This is a preview built from the chapter\'s stored data. It is the same lesson text for every teaching method (only its order of slides is adjusted), and it is not a generated, approved or published presentation. Use Create to generate the lesson for the chosen teaching method.',
            'method_specific' => false,
            'deck' => $deck,
            'summary' => $this->publicSummary($built['summary']),
            'missing' => $built['missing'],
            'quality' => $built['quality'],
            'coverage' => $this->coverageReport($coverage, $pictures, $this->hasOutcomes($found['sources'])),
            'pictures' => ['placed' => $built['summary']['pictures_placed'], 'verified_available' => count($pictures['by_concept']), 'rejected' => $pictures['rejected'], 'from_book_pictures' => count($book['by_concept']), 'concepts_without_picture' => $book['missing']],
            'fit' => ['fit_status' => $fit['fit_status'], 'reason' => $fit['reason']],
            'wrote_nothing' => true,
        ]);
    }
    /** Does the school hold learning outcomes for the chapter? (They are optional context: the source list only carries them when present.) @param array<int|string,array<string,mixed>> $sources */
    private function hasOutcomes(array $sources): bool
    {
        foreach ($sources as $s) {
            if (($s['key'] ?? null) === 'outcomes' && ($s['status'] ?? 'missing') !== 'missing') {
                return true;
            }
        }

        return false;
    }

    /** The textbook is a hidden fallback: it is never listed as a source on the screen. @param array<int|string,array<string,mixed>> $sources */
    private function visibleSources(array $sources): array
    {
        $shown = array_values(array_filter($sources, fn ($s) => ($s['key'] ?? null) !== 'textbook'));
        foreach ($shown as &$s) {
            if (isset($s['coverage']['summary'])) {
                $s['coverage']['summary'] = $this->publicSummary($s['coverage']['summary']);
            }
        }

        return $shown;
    }

    /** Counts as shown to a teacher: where each concept's explanation comes from, with no textbook figures. @param array<string,mixed> $s */
    private function publicSummary(array $s): array
    {
        $s['from_intelligence'] = (int) ($s['both_sources'] ?? 0) + (int) ($s['semantic_only'] ?? 0);
        $s['from_concept_record_only'] = (int) ($s['textbook_fallback_only'] ?? 0);
        foreach (['textbook_supported', 'both_sources', 'semantic_only', 'textbook_fallback_only'] as $k) {
            unset($s[$k]);
        }

        return $s;
    }

    /** What the picture pool needs to know about the chapter (its id, the chapter text, its concepts). */
    private function poolContext(array $chapter, array $coverage, string $text): array
    {
        // A picture's labels are checked against the concept's own Semantic Intelligence, then the chapter text where that is not enough.
        $ground = [];
        foreach ($coverage['concepts'] as $id => $c) {
            $parts = [(string) $c['name'], (string) $c['topic']];
            array_walk_recursive($c['semantic'], function ($v) use (&$parts) {
                if (is_string($v)) {
                    $parts[] = $v;
                }
            });
            // The chapter text is a hidden fallback for what a picture must explain; it is never shown.
            $parts[] = $text;
            $ground[(int) $id] = implode(' ', $parts);
        }

        return ['chapter' => ['id' => (int) $chapter['id']], 'ground_by_concept' => $ground, 'concepts' => $coverage['concepts']];
    }

    /**
     * The coverage map as a teacher may see it: counts, and one line per concept saying where its explanation comes from.
     * No record ids and no passage text.
     *
     * @param array<string,mixed> $coverage ConceptCoverage::build()
     * @param array{by_concept:array<int,array<string,mixed>>,rejected:array<int,array<string,mixed>>,considered:int} $pictures
     * @return array<string,mixed>
     */
    private function coverageReport(array $coverage, array $pictures, bool $hasOutcomes = false): array
    {
        $rows = array_values(array_map(fn ($c) => [
            'name' => $c['name'],
            'topic' => $c['topic'],
            'source' => $c['explanation_source'] === 'unresolved' ? 'unresolved' : ($c['semantic']['level'] === 'concept' ? 'semantic' : 'concept_record'),
            'semantic' => $c['semantic']['level'],
            'match' => $c['semantic']['match'],
            'picture' => isset($pictures['by_concept'][$c['id']]),
            'sufficient' => $c['sufficient'],
            'reason' => $c['unresolved_reason'],
        ], $coverage['concepts']));
        $with = count(array_filter($rows, fn ($r) => $r['picture']));

        return [
            'summary' => $this->publicSummary($coverage['summary']),
            'concepts' => $rows,
            'unresolved' => array_values(array_filter(array_map(fn ($r) => $r['sufficient'] ? null : ['name' => $r['name'], 'reason' => $r['reason']], $rows))),
            'intelligence_entries_not_mapped' => $coverage['unmapped_entries'],
            'pictures' => ['with_a_verified_picture' => $with, 'without' => count($rows) - $with, 'candidates_rejected' => count($pictures['rejected'])],
            // Said only where the school has stored outcomes for the chapter: a chapter without any is not told about them.
            'learning_outcomes' => $hasOutcomes ? 'chapter-level: the stored concept-to-outcome links are blanket links, so none is attributed to a single concept' : null,
        ];
    }

    /** GET lms/course-presentation/fit?chapter_id=&pedagogy=&format= : the method-fit result for the two choices. */
    public function fit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'pedagogy' => ['required', 'string', Rule::in(array_keys($this->options->pedagogies()))],
            'format' => ['required', 'string', Rule::in(array_keys($this->options->formats()))],
            'sources' => 'prohibited',
            'source_ids' => 'prohibited',
        ]);
        $chapter = $this->verifiedChapter($request, (int) $data['chapter_id'], $error);
        if ($chapter === null) {
            return $error;
        }
        [, , $fit] = $this->assess($chapter, $data['pedagogy'], $data['format']);

        return response()->json(['status_code' => 1, 'chapter' => ['id' => (int) $chapter['id'], 'name' => $chapter['chapter_name']], 'fit' => $fit]);
    }

    /** @return array{0:array<string,mixed>,1:array<string,array<string,mixed>>,2:array<string,mixed>,3:array<string,mixed>} sources, per-method suitability, fit, the loaded rows */
    private function assess(array $chapter, string $pedagogy, string $format): array
    {
        $registry = new SourceRegistry((int) config('course_presentation_pilot.min_text_chars', 2000));
        $raw = $this->loader->load($chapter);
        $found = $registry->build($chapter, $raw);
        // The concept coverage map and the chapter text it was built from, for the pre-flight and the preview.
        $found['coverage'] = $registry->coverage($chapter, $raw);
        $found['textbook_text'] = $registry->textbookText($chapter, $raw);
        $methodFit = (new MethodSuitability())->evaluate($registry->semanticRow($chapter, $raw));
        $runs = app(GenerationRun::class);
        $context = [
            'model_available' => app(ModelAvailability::class)->availableFor($format),
            'concept_count' => count((array) $raw['concepts']),
            // The reusable story template as Template Management holds it for this school (or the built-in default).
            'story_template' => \App\Services\StudyDeck\Story\StoryTemplate::resolve((int) $chapter['sub_institute_id']),
            // A draft of THIS teaching method and format that is queued, running or waiting for review...
            'active_run' => $runs->active((int) $chapter['id'], (int) $chapter['sub_institute_id'], $pedagogy, $format),
            // ...and a draft of any other choice that is being made right now (the background job is one per chapter).
            'busy_run' => $this->busyRun($runs, $chapter, $pedagogy, $format),
        ];
        $fit = (new FitValidator($this->options))->validate($pedagogy, $format, $found['sources'], $found['existing_resources'], $methodFit, $context);

        return [$found, $methodFit, $fit, $raw];
    }
    /** A run of another choice that is queued or running, with the label of its format; null when none (or it is this choice's own). @return array<string,mixed>|null */
    private function busyRun(GenerationRun $runs, array $chapter, string $pedagogy, string $format): ?array
    {
        $busy = $runs->inFlight((int) $chapter['id'], (int) $chapter['sub_institute_id']);
        if ($busy === null || GenerationRun::matches($busy, $pedagogy, $format)) {
            return null;
        }
        $key = (string) ($busy['format'] ?? 'interactive_slides');

        return ['run' => $busy['run'], 'state' => $busy['state'], 'label' => (string) ($this->options->formats()[$key]['label'] ?? $key)];
    }

    /** @return array<string,mixed>|null the chapter row, or null with $error set */
    private function verifiedChapter(Request $request, int $chapterId, ?JsonResponse &$error): ?array
    {
        $tenant = (int) $request->session()->get('sub_institute_id');
        if ($tenant <= 0) {
            $error = response()->json(['status_code' => 0, 'message' => 'No school in this session.'], 403);

            return null;
        }
        $chapter = $this->loader->chapter($chapterId, $tenant);
        if ($chapter === null) {
            $error = response()->json(['status_code' => 0, 'message' => 'Chapter not found.'], 404);

            return null;
        }
        // Checked after visibility, so a chapter this school cannot see still reads as "not found".
        if (!$this->scope->allows($chapter)) {
            $error = response()->json(['status_code' => 0, 'message' => $this->scope->message()], 403);

            return null;
        }

        return $chapter;
    }
}
