<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Models\lms\h5p\H5pCoursePresentation;
use App\Models\lms\h5p\h5pFlashcard;
use App\Models\lms\h5p\H5pPresentationSlide;
use App\Models\lms\h5p\H5pSlideElement;
use App\Models\lms\h5p\H5pTrueFalse;
use App\Models\lms\h5p\H5pTrueFalseQuestion;
use App\Models\PAL\H5PNodeMetadata;
use App\Services\PAL\H5P\H5PContentRepository;
use App\Services\PAL\H5P\H5PEngagementService;
use App\Services\PAL\H5P\H5PInsightService;
use App\Services\PAL\H5P\H5PIntelligenceService;
use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PTaggingService;
use App\Services\PAL\H5P\H5PXapiPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAL V4 — H5P Model API.
 *
 * The backend for LMS+PAL → Tech/Learn → Subject → Chapter → H5P Content.
 *
 * Everything served here is read from the database at request time: the
 * registry (`pal_vocabulary`), the H5P content itself (`h5p_scenarios`,
 * `h5p_interactive_video`, `h5p_flashcard`, the MCQ slice of
 * `lms_question_master`), the tags (`pal_h5p_node_metadata`) and the telemetry
 * (`pal_telemetry_events`). No catalog, mapping or metric in a response is
 * written into this class.
 *
 * Mounted under the same `pal.auth` middleware as the rest of /api/pal/*.
 * H5P content has no learner for the middleware to scope through, so tenancy
 * is resolved here from the caller's own token — the same rule
 * NewPalContentModelController and PalContentIntelligenceController use.
 *
 * Envelope: {success: true, data: …} / {success: false, message: …}.
 */
class PalH5PModelController extends Controller
{
    public function __construct(
        protected H5PModelRegistry $registry,
        protected H5PContentRepository $repository,
        protected H5PTaggingService $tagging,
        protected H5PEngagementService $engagement,
        protected H5PIntelligenceService $intelligence,
        protected H5PXapiPipeline $pipeline
    ) {
    }

    // ══════════════════════════════════════════════════════════════════════
    // Registry
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/registry
     *
     * The whole H5P Model vocabulary in one call — every selector, chip and
     * matrix in the UI is rendered from this and nothing else.
     */
    public function registry(Request $request): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        return $this->ok([
            'source' => $this->registry->source($tenant),
            'h5p_types' => array_values($this->registry->types($tenant)),
            'pedagogies' => array_values($this->registry->pedagogies($tenant)),
            'frameworks' => array_map('array_values', $this->registry->frameworks($tenant)),
            'gardner_intelligences' => array_values($this->registry->domain('gardner_intelligences', $tenant)),
            'riasec_signals' => array_values($this->registry->domain('riasec_signals', $tenant)),
            'hpc_lenses' => array_values($this->registry->domain('hpc_lenses', $tenant)),
            'bloom_levels' => array_values($this->registry->domain('bloom_levels', $tenant)),
            'xapi_verbs' => array_values($this->registry->domain('xapi_verbs', $tenant)),
            'engagement_signals' => array_values($this->registry->domain('engagement_signals', $tenant)),
            'engagement_weights' => $this->registry->engagementWeights($tenant),
            'selection_rules' => array_values($this->registry->selectionRules($tenant)),
            'coverage_matrix' => $this->registry->coverageMatrix($tenant),
            'quality_statuses' => array_keys(config('pal_content.quality_statuses', [])),
            'cultural_contexts' => array_keys(config('pal_content.cultural_contexts', [])),
            'ai' => [
                'available' => $this->tagging->aiAvailable(),
                'unavailable_reason' => $this->tagging->aiUnavailableReason(),
            ],
        ]);
    }

    /**
     * GET /api/pal/h5p/coverage-matrix
     * §9 on its own, for the matrix view.
     */
    public function coverageMatrix(Request $request): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        return $this->ok([
            'matrix' => $this->registry->coverageMatrix($tenant),
            'pedagogies' => array_values($this->registry->pedagogies($tenant)),
            'frameworks' => array_map('array_values', $this->registry->frameworks($tenant)),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Hub + chapter model
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/hub
     *
     * One card per natively implemented H5P type, with the chapter's real node
     * counts, the pedagogies that use the type and its measured engagement.
     * This is the replacement for the hard-coded four-card list.
     */
    public function hub(Request $request): JsonResponse
    {
        $context = $this->contextFor($request);

        return $this->ok([
            'context' => $context + $this->repository->resolveContextNames($context),
            'modules' => $this->intelligence->hubModules($context),
            'telemetry' => $this->engagement->summary($context),
            'registry_source' => $this->registry->source($context['sub_institute_id']),
        ]);
    }

    /**
     * GET /api/pal/h5p/chapter-model
     *
     * The complete H5P Model for one chapter: nodes, tags, engagement,
     * §9 coverage, pedagogy distribution and tagging health.
     */
    public function chapterModel(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'subject_id' => 'nullable|integer',
            'standard_id' => 'nullable|integer',
            'type' => 'nullable|string|max:48',
            'limit' => 'nullable|integer|min:1|max:200',
            'window_days' => 'nullable|integer|min:1|max:730',
        ]);

        $context = $this->contextFor($request);

        return $this->ok($this->intelligence->chapterModel($context, [
            'type' => $request->input('type'),
            'limit' => (int) $request->input('limit', H5PContentRepository::DEFAULT_LIMIT),
            'window_days' => $request->filled('window_days') ? (int) $request->input('window_days') : null,
        ]));
    }

    /**
     * GET /api/pal/h5p/chapters
     * Chapters that hold at least one H5P node — the workspace's picker when
     * it is opened without a chapter in the query string.
     */
    public function chapters(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        return $this->ok(['chapters' => $this->repository->chaptersWithContent($this->contextFor($request))]);
    }

    /**
     * GET /api/pal/h5p/coverage
     * §9 read against the chapter's real content, with the pedagogy + H5P type
     * that would close each gap.
     */
    public function coverage(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate(['chapter_id' => 'required|integer|min:1']);
        $context = $this->contextFor($request);

        return $this->ok([
            'context' => $context + $this->repository->resolveContextNames($context),
            'coverage' => $this->intelligence->coverage($context),
            'available_pedagogies' => $this->intelligence->availablePedagogies($context),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Nodes + tagging
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/nodes/{h5pType}/{nodeId}
     * One node, its children, its tags and its engagement.
     */
    public function node(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $context = $this->contextFor($request);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $context['sub_institute_id']]);

        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $engagement = $this->engagement->forNodes([$node['node_key']], $context);

        return $this->ok([
            'node' => $node,
            'model' => $this->tagging->tagNode($node, $context),
            'engagement' => $engagement[$node['node_key']] ?? null,
            'pedagogies' => $this->registry->pedagogiesForH5pType($node['h5p_type'], $context['sub_institute_id']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Generation from existing PAL content
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/pal/h5p/concepts/{conceptId}/generate/flashcard
     *
     * The first "Generate Interactive Version" pilot. PAL already decided
     * this concept is worth teaching - `lms_concept.name` + `.definition` -
     * so this makes an H5P version of THAT content rather than asking a
     * teacher to re-author it from scratch in the flashcard editor. One
     * `h5p_flashcard` row, tagged straight to this concept through the same
     * `H5PTaggingService::store()` the H5P Model workspace uses, so
     * `ConceptLearningResourceService::h5pForConcept()` picks it up on the
     * very next Learn read - no other code path involved, no new table.
     *
     * Written straight to `quality_status: approved`: a teacher explicitly
     * triggered this one card from curriculum data that was already reviewed
     * (the concept itself), which is a different trust level than an
     * autonomous AI sweep - CONTENT LAW C5 is about the latter never
     * self-promoting, not about a human's own click needing a second click to
     * confirm itself.
     *
     * Idempotent: re-running this for a concept that already has a generated
     * flashcard (e.g. after the concept's definition changes) updates that
     * same card instead of creating a second one.
     */
    public function generateFlashcardFromConcept(Request $request, int $conceptId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Generating H5P content is not available to students.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $concept = DB::table('lms_concept')->where('id', $conceptId)->first();
        if ($concept === null) {
            return $this->fail("Unknown concept {$conceptId}.", 404);
        }

        $term = trim((string) $concept->name);
        $definition = trim((string) ($concept->definition ?: $concept->description ?: ''));

        if ($term === '' || $definition === '') {
            return $this->fail('This concept has no name/definition to build a flashcard from yet.', 422);
        }

        $auth = $request->attributes->get('pal_auth');
        $userId = (int) ($auth['user_id'] ?? 0);

        $existingTag = H5PNodeMetadata::where('h5p_type', 'flash_cards')
            ->where('concept_ref_id', $conceptId)
            ->forTenant($tenant)
            ->first();

        $cardAttributes = [
            'sub_institute_id' => $tenant,
            'standard_id' => $concept->standard_id,
            'subject_id' => $concept->subject_id,
            'chapter_id' => $concept->chapter_id,
            // The definition is the stimulus a learner reads first; the term
            // is what they have to recall and type back. Asking for the whole
            // definition verbatim would make the typed-answer check in
            // FlashcardPlayerContent::handleCheck() fail on any paraphrase.
            'content' => $definition,
            'question' => 'Which term does this describe?',
            'correct_answer' => mb_strtolower($term),
            'hint' => $term,
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        $card = $existingTag ? h5pFlashcard::find($existingTag->node_id) : null;

        if ($card === null) {
            $card = h5pFlashcard::create($cardAttributes + ['created_by' => $userId, 'created_at' => now()]);
        } else {
            $card->update($cardAttributes);
        }

        $context = [
            'chapter_id' => (int) $concept->chapter_id,
            'subject_id' => (int) $concept->subject_id,
            'standard_id' => (int) $concept->standard_id,
            'sub_institute_id' => $tenant,
        ];

        $node = $this->repository->node('flash_cards', (int) $card->id, $context);
        if ($node === null) {
            return $this->fail('The flashcard was saved but could not be re-read as an H5P node.', 500);
        }

        $saved = $this->tagging->store($node, $context, [
            'concept_ref_id' => $conceptId,
            'pedagogy_tag' => 'flashcard',
            'quality_status' => 'approved',
        ], [
            'user_id' => $userId,
            'is_ai' => false,
        ]);

        return $this->ok([
            'node' => $node,
            'model' => $saved,
            'concept_id' => $conceptId,
        ]);
    }

    /**
     * POST /api/pal/h5p/chapters/{chapterId}/generate/course-presentation
     *
     * The second "Generate Interactive Version" pilot: teach → question →
     * teach → question, one pair per topic, built from content PAL already
     * has - no separately-authored H5P deck, no invented questions.
     *
     * "Topic" is `lms_concept` (the same breakdown the flashcard pilot reads,
     * and what a chapter's Classroom Resource is scoped to - content_master
     * has no finer-grained link than the chapter). "Teach" is the concept's
     * own definition/description, exactly as the flashcard pilot uses it.
     * "Question" is ONE existing `lms_question_master` row already tagged to
     * that concept_id, materialised into the slide's own `multiple_choice`
     * element - its text and options copied verbatim from `answer_master`,
     * never generated. Only auto-gradable types are eligible (1 = multiple
     * choice, 8 = assertion & reason); narrative/case-based types (2, 4, 7)
     * cannot be checked inline and are left out, not converted into
     * something they are not.
     *
     * UNLIKE THE FLASHCARD PILOT, this node is chapter-scoped, not
     * concept-scoped: one deck covers every topic in the chapter, matching
     * how a Classroom Resource is already chapter-wide in this estate (see
     * ConceptLearningResourceService's own docblock on content_master).
     * `concept_ref_id` is written as null on purpose -
     * ConceptLearningResourceService::h5pForConcept() has the matching read
     * side, surfacing it under every concept in the chapter with a "whole
     * chapter" scope tag, the same honesty rule content_master rows already
     * follow.
     *
     * Idempotent: re-running this for a chapter that already has a generated
     * deck (h5p_type=course_presentation, concept_ref_id null, this chapter)
     * rebuilds that same deck's slides rather than creating a second one.
     */
    public function generateCoursePresentationFromChapter(Request $request, int $chapterId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Generating H5P content is not available to students.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $concepts = DB::table('lms_concept')->where('chapter_id', $chapterId)->orderBy('id')->get();
        if ($concepts->isEmpty()) {
            return $this->fail("Chapter {$chapterId} has no concepts to build a lesson from.", 422);
        }

        $auth = $request->attributes->get('pal_auth');
        $userId = (int) ($auth['user_id'] ?? 0);
        $first = $concepts->first();

        $existingTag = H5PNodeMetadata::where('h5p_type', 'course_presentation')
            ->whereNull('concept_ref_id')
            ->where('chapter_id', $chapterId)
            ->forTenant($tenant)
            ->first();

        $deckAttributes = [
            'sub_institute_id' => $tenant,
            'standard_id' => $first->standard_id,
            'subject_id' => $first->subject_id,
            'chapter_id' => $chapterId,
            'title' => 'Interactive lesson',
            'description' => 'Generated from this chapter\'s concepts and question bank.',
            'status' => 'published',
            'published_at' => now(),
            'pass_percentage' => 60,
            // The player shows this "N/total slides VISITED" counter above the
            // slide, and "current slide X of total" below it - both against the
            // same denominator, easily read as two disagreeing numbers (visited
            // 3, currently on slide 1). One position readout is enough for a
            // linear teach/question walk-through; the visited count is more
            // useful on a deck a learner jumps around in non-linearly.
            'show_progress_bar' => false,
            'library' => 'H5P.CoursePresentation 1.25',
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        $deck = $existingTag ? H5pCoursePresentation::find($existingTag->node_id) : null;

        if ($deck === null) {
            $deck = H5pCoursePresentation::create($deckAttributes + ['created_by' => $userId, 'created_at' => now()]);
        } else {
            $deck->update($deckAttributes);
            // Rebuilt from scratch each time, not merged: a deterministic
            // generator re-running against the same concepts/questions
            // should leave one clean deck, not an accumulation of stale
            // slides alongside the current ones.
            H5pSlideElement::where('presentation_id', $deck->id)->forceDelete();
            H5pPresentationSlide::where('presentation_id', $deck->id)->forceDelete();
        }

        $slideIndex = 0;
        $previousSlide = null;

        // A slide title's prefix is the grouping key the frontend sidebar
        // reads (see the course-presentation player's topic grouping): a
        // bare concept name starts a new topic group, 'Try it:'/'Check:'
        // continue the group started by the most recent bare title. Chosen
        // over a new `group_label` column so this stays a convention any
        // deck can opt into, not a schema change to a table every native
        // type's presentations share.
        $addSlide = function (string $title, array $elementAttributes) use (
            &$slideIndex, &$previousSlide, $deck, $tenant, $userId
        ) {
            $slide = H5pPresentationSlide::create([
                'presentation_id' => $deck->id,
                'slide_index' => $slideIndex++,
                'title' => $title,
                'sub_institute_id' => $tenant,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $previousSlide?->update(['next_slide_id' => $slide->id]);
            $previousSlide = $slide;

            H5pSlideElement::create($elementAttributes + [
                'presentation_id' => $deck->id,
                'slide_id' => $slide->id,
                'position_x' => 10,
                'position_y' => 15,
                'width' => 80,
                'height' => 70,
                'sort_order' => 0,
                'sub_institute_id' => $tenant,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        foreach ($concepts as $concept) {
            $definition = trim((string) ($concept->definition ?: $concept->description ?: ''));
            if ($definition === '') {
                continue;
            }

            $question = $this->pickQuestionForConcept((int) $concept->id, $tenant);

            // Teach — the concept's own definition, plus (when the concept's
            // own question bank happens to carry one) the worked example
            // already authored in that question's `explanation` field. Real,
            // reviewed text pulled from data that already exists; nothing
            // below is generated for this slide.
            $teach = $definition;
            if ($question !== null && $question['explanation'] !== '') {
                $teach .= "\n\nFor example: " . $question['explanation'];
            }

            $addSlide($concept->name, [
                'element_type' => 'text',
                'content_text' => $teach,
            ]);

            // Interact — a cloze built from the SAME definition sentence, not
            // a second piece of content: the blanked word is one already
            // sitting in `$definition`, chosen deterministically (see
            // buildClozeFromDefinition), so completing it is a first,
            // low-stakes check that the explanation just given landed.
            $cloze = $this->buildClozeFromDefinition($definition);
            if ($cloze !== null) {
                $addSlide('Try it: ' . $concept->name, [
                    'element_type' => 'blanks',
                    'content_text' => null,
                    'options' => [
                        'task_description' => 'Complete the idea in your own words.',
                        'passage' => $cloze,
                    ],
                    'points' => 1,
                ]);
            }

            // Practice — an existing, already-approved question bank item for
            // this exact concept, materialised verbatim (see
            // pickQuestionForConcept's own docblock on why only types 1 and 8
            // are eligible).
            if ($question !== null) {
                $addSlide('Check: ' . $concept->name, [
                    'element_type' => 'multiple_choice',
                    'content_text' => $question['title'],
                    'options' => ['answers' => $question['answers']],
                    // Provenance, not a live reference: which question bank
                    // row this slide's text/options were copied from.
                    'ref_content_id' => $question['id'],
                    'points' => 1,
                ]);
            }
        }

        if ($slideIndex === 0) {
            return $this->fail('No concept in this chapter has anything to teach from yet.', 422);
        }

        $context = [
            'chapter_id' => $chapterId,
            'subject_id' => (int) $first->subject_id,
            'standard_id' => (int) $first->standard_id,
            'sub_institute_id' => $tenant,
        ];

        $node = $this->repository->node('course_presentation', (int) $deck->id, $context);
        if ($node === null) {
            return $this->fail('The lesson was saved but could not be re-read as an H5P node.', 500);
        }

        $saved = $this->tagging->store($node, $context, [
            'concept_ref_id' => null,
            'pedagogy_tag' => 'inquiry_based',
            'quality_status' => 'approved',
        ], [
            'user_id' => $userId,
            'is_ai' => false,
        ]);

        return $this->ok([
            'node' => $node,
            'model' => $saved,
            'chapter_id' => $chapterId,
            'slide_count' => $slideIndex,
        ]);
    }

    /**
     * POST /api/pal/h5p/chapters/{chapterId}/generate/true-false
     *
     * The third "Generate Interactive Version" pilot, and the first to give a
     * chapter's topics genuinely DIFFERENT interaction shapes instead of
     * folding every one of them into the same course-presentation deck (see
     * generateCoursePresentationFromChapter() just above). Where a concept's
     * approved question already reads, option by option, as a set of
     * free-standing claims - an MCQ whose answers are full sentences, not bare
     * values - those exact option texts become one H5P.TrueFalse pool for
     * THAT concept: a quick tap-true-or-false check, tagged
     * `concept_ref_id = concept.id` so it surfaces on the Learn page as its
     * own card next to (not instead of) the chapter's deck.
     *
     * NOTHING IS INVENTED. A statement is always an `answer_master.answer`
     * value, used verbatim, carrying that SAME answer's own `correct_answer`
     * flag and `feedback` - the identical source `pickQuestionForConcept()`
     * already trusts for the course-presentation "Check" slide, just read one
     * option at a time instead of collapsed into one four-way choice. The
     * only judgement call this makes is *whether an option reads like a
     * statement* (readsAsStatement() below) - never what it says. A concept
     * whose only eligible question has short/numeric options (most
     * arithmetic, most "which value" questions) yields nothing: no card, no
     * rewritten text. That is an ordinary, expected outcome, not an error -
     * exactly the honesty rule `h5pForConcept()` already applies to every
     * other resource kind.
     *
     * Idempotent per concept, like the other two pilots: re-running this for
     * a chapter replaces each concept's existing pool (matched by
     * `concept_ref_id`) rather than creating a second one.
     */
    public function generateTrueFalseFromChapter(Request $request, int $chapterId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Generating H5P content is not available to students.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $concepts = DB::table('lms_concept')->where('chapter_id', $chapterId)->orderBy('id')->get();
        if ($concepts->isEmpty()) {
            return $this->fail("Chapter {$chapterId} has no concepts to build a check from.", 422);
        }

        $auth = $request->attributes->get('pal_auth');
        $userId = (int) ($auth['user_id'] ?? 0);

        $generated = [];
        $skipped = [];

        foreach ($concepts as $concept) {
            $statements = $this->buildTrueFalseStatements((int) $concept->id, $tenant);

            if (count($statements) < 2) {
                $skipped[] = [
                    'concept_id' => (int) $concept->id,
                    'name' => $concept->name,
                    'reason' => 'no_eligible_statements',
                ];
                continue;
            }

            $existingTag = H5PNodeMetadata::where('h5p_type', 'true_false')
                ->where('concept_ref_id', $concept->id)
                ->forTenant($tenant)
                ->first();

            $poolAttributes = [
                'sub_institute_id' => $tenant,
                'standard_id' => $concept->standard_id,
                'subject_id' => $concept->subject_id,
                'chapter_id' => $chapterId,
                'title' => 'Quick check: ' . $concept->name,
                'description' => 'Generated from this concept\'s own question bank.',
                'task_description' => 'True or false?',
                'status' => 'published',
                'published_at' => now(),
                'randomize_questions' => true,
                // 0 -> the whole pool every attempt. These pools run 2-4
                // statements (one source question's answer options), which is
                // already a short bell-ringer - there is nothing to sample
                // down from.
                'questions_to_ask' => 0,
                'pass_percentage' => 60,
                'library' => 'H5P.TrueFalse 1.8',
                'updated_by' => $userId,
                'updated_at' => now(),
            ];

            $pool = $existingTag ? H5pTrueFalse::find($existingTag->node_id) : null;

            if ($pool === null) {
                $pool = H5pTrueFalse::create($poolAttributes + ['created_by' => $userId, 'created_at' => now()]);
            } else {
                $pool->update($poolAttributes);
                // Rebuilt from scratch, same reasoning as the
                // course-presentation slides just above: a deterministic
                // generator re-run should leave one clean pool, not stale
                // statements sitting alongside the current ones.
                H5pTrueFalseQuestion::where('true_false_id', $pool->id)->forceDelete();
            }

            foreach ($statements as $index => $statement) {
                H5pTrueFalseQuestion::create([
                    'true_false_id' => $pool->id,
                    'question_text' => $statement['text'],
                    'correct_answer' => $statement['correct'],
                    'feedback_correct' => $statement['correct'] ? $statement['feedback'] : null,
                    'feedback_incorrect' => $statement['correct'] ? null : $statement['feedback'],
                    'sort_order' => $index,
                    'sub_institute_id' => $tenant,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $context = [
                'chapter_id' => $chapterId,
                'subject_id' => (int) $concept->subject_id,
                'standard_id' => (int) $concept->standard_id,
                'sub_institute_id' => $tenant,
            ];

            $node = $this->repository->node('true_false', (int) $pool->id, $context);
            if ($node === null) {
                $skipped[] = [
                    'concept_id' => (int) $concept->id,
                    'name' => $concept->name,
                    'reason' => 'could_not_reread',
                ];
                continue;
            }

            $saved = $this->tagging->store($node, $context, [
                'concept_ref_id' => (int) $concept->id,
                'pedagogy_tag' => 'game_based',
                'quality_status' => 'approved',
            ], [
                'user_id' => $userId,
                'is_ai' => false,
            ]);

            $generated[] = [
                'concept_id' => (int) $concept->id,
                'name' => $concept->name,
                'node_id' => (int) $pool->id,
                'statement_count' => count($statements),
                'model' => $saved,
            ];
        }

        if ($generated === []) {
            return $this->fail(
                'No concept in this chapter has question options that read as standalone statements yet.',
                422
            );
        }

        return $this->ok([
            'chapter_id' => $chapterId,
            'generated' => $generated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * The statement pool for one concept's True/False check, or an empty
     * array when nothing qualifies.
     *
     * Source: the SAME already-approved MCQ / assertion-and-reason question
     * `pickQuestionForConcept()` uses for the course-presentation "Check"
     * slide - but every answer option, not just the correct one, each
     * becoming an independent true-or-false claim rather than one four-way
     * choice. That is a genuinely different interaction, not a re-skin: a
     * learner evaluates each statement on its own instead of picking the
     * best of four.
     */
    /**
     * The widest pool this generator will ever write for one concept - see
     * generateTrueFalseFromChapter()'s own note on why a pool authored once
     * can afford to be wider than one attempt asks.
     */
    protected const TRUE_FALSE_POOL_LIMIT = 8;

    protected function buildTrueFalseStatements(int $conceptId, int $tenant): array
    {
        $questions = DB::table('lms_question_master')
            ->where('concept_id', $conceptId)
            ->whereIn('question_type_id', [1, 8])
            ->where('status', 1)
            ->whereIn('sub_institute_id', [$tenant, 0])
            ->orderBy('id')
            ->get(['id']);

        if ($questions->isEmpty()) {
            return [];
        }

        // Every eligible question's options are candidates, not just the
        // first question by id - a concept's questions vary in how
        // sentence-like their options read (see the pilot notes: one
        // question's options are all bare values, the next question's are
        // full rule statements), and taking only the first would leave a
        // concept with real eligible content skipped over a coincidence of
        // ordering. Pooled across questions, deduplicated by text, capped at
        // TRUE_FALSE_POOL_LIMIT so a concept with many eligible questions
        // still gets a short bell-ringer, not an exam.
        $statements = [];
        $seen = [];

        foreach ($questions as $question) {
            if (count($statements) >= self::TRUE_FALSE_POOL_LIMIT) {
                break;
            }

            $answers = DB::table('answer_master')
                ->where('question_id', $question->id)
                ->orderBy('id')
                ->get(['answer', 'correct_answer', 'feedback']);

            foreach ($answers as $answer) {
                $text = trim((string) $answer->answer);
                $key = mb_strtolower($text);

                if (! $this->readsAsStatement($text) || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $statements[] = [
                    'text' => $text,
                    'correct' => (bool) $answer->correct_answer,
                    'feedback' => trim((string) ($answer->feedback ?? '')) ?: null,
                ];

                if (count($statements) >= self::TRUE_FALSE_POOL_LIMIT) {
                    break;
                }
            }
        }

        return $statements;
    }

    /**
     * Heuristic only, never a rewrite: true when `$text` already reads as a
     * self-contained claim rather than a bare value - at least two real
     * words (three-plus letters each) and enough length to plausibly stand
     * alone as a sentence. "$-54$", "12", "Option B" fail this on purpose:
     * most arithmetic / "which value" answers do, which is exactly why they
     * stay on the MCQ / course-presentation path instead of being forced
     * into a shape they were never authored in.
     */
    protected function readsAsStatement(string $text): bool
    {
        if (mb_strlen($text) < 20) {
            return false;
        }

        return (bool) preg_match('/[A-Za-z]{3,}.*[A-Za-z]{3,}/', $text);
    }

    /**
     * One existing, already-approved question bank item for this concept,
     * with its options copied from `answer_master` - or null when the
     * concept has nothing auto-gradable to offer.
     *
     * question_type_id 1 (multiple choice) and 8 (assertion & reason) are
     * both rendered as a single-answer choice; 2/4/7 (narrative, hot
     * questions, CBE) have no fixed answer set an inline player could check,
     * so they are left for a human to review rather than forced into a
     * shape they were never authored in.
     *
     * @return array{id:int,title:string,explanation:string,answers:array<int,array{text:string,correct:bool,feedback:string}>}|null
     */
    protected function pickQuestionForConcept(int $conceptId, int $tenant): ?array
    {
        $question = DB::table('lms_question_master')
            ->where('concept_id', $conceptId)
            ->whereIn('question_type_id', [1, 8])
            ->where('status', 1)
            ->whereIn('sub_institute_id', [$tenant, 0])
            ->orderBy('id')
            ->first(['id', 'question_title', 'answer']);

        if ($question === null) {
            return null;
        }

        $answers = DB::table('answer_master')
            ->where('question_id', $question->id)
            ->orderBy('id')
            ->get(['answer', 'correct_answer', 'feedback']);

        if ($answers->isEmpty()) {
            return null;
        }

        // The worked example already authored alongside this question, e.g.
        // "...for example 6 x (-9) = -54". Best-effort: a question authored
        // before this field existed, or with malformed JSON, simply has no
        // explanation to add - the teach slide still has the concept's own
        // definition either way.
        $explanation = '';
        $decoded = json_decode((string) $question->answer, true);
        if (is_array($decoded) && is_string($decoded['explanation'] ?? null)) {
            $explanation = trim($decoded['explanation']);
        }

        return [
            'id' => (int) $question->id,
            'title' => (string) $question->question_title,
            'explanation' => $explanation,
            'answers' => $answers->map(fn ($row) => [
                'text' => (string) $row->answer,
                'correct' => (bool) $row->correct_answer,
                'feedback' => (string) ($row->feedback ?? ''),
            ])->all(),
        ];
    }

    /**
     * A one-blank cloze built from a concept's own definition, or null when
     * the sentence has nothing worth blanking.
     *
     * Deterministic on purpose, not a second piece of authored content: the
     * blanked word is whichever content word ends the sentence (concept
     * definitions in this estate consistently land on the key term there -
     * "...is always *negative*.", "...is called the *product*." - the same
     * pattern the existing hand-authored narrative questions use, e.g.
     * "The product...is always ______"). A short or stopword-only tail
     * yields no blank rather than a misleading one.
     */
    protected function buildClozeFromDefinition(string $definition): ?string
    {
        $stopwords = ['always', 'never', 'called', 'known', 'their', 'there', 'these', 'those', 'about'];

        if (! preg_match('/([A-Za-z]{4,})\W*$/', rtrim($definition, " \t\n\r\0\x0B."), $match)) {
            return null;
        }

        $word = $match[1];
        if (in_array(mb_strtolower($word), $stopwords, true)) {
            return null;
        }

        $start = mb_strrpos($definition, $word);
        if ($start === false) {
            return null;
        }

        return mb_substr($definition, 0, $start) . '*' . $word . '*' . mb_substr($definition, $start + mb_strlen($word));
    }

    /**
     * POST /api/pal/h5p/nodes/{h5pType}/{nodeId}/tags
     * Save a human tag set. Unregistered vocabulary is rejected.
     */
    public function saveTags(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Tagging H5P content is not available to students.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $context = $this->contextFor($request, $tenant);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $tenant]);
        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $auth = $request->attributes->get('pal_auth');
        $saved = $this->tagging->store($node, $context, $request->all(), [
            'user_id' => (int) ($auth['user_id'] ?? 0),
            'is_ai' => false,
        ]);

        return $this->ok(['node' => $node, 'model' => $saved]);
    }

    /**
     * GET /api/pal/h5p/nodes/{h5pType}/{nodeId}/preview
     *
     * What the node's framework tags would become under a different pedagogy.
     * Read-only — it lets the authoring UI show the consequence of a change
     * before the teacher commits to it.
     */
    public function previewTags(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate(['pedagogy' => 'required|string|max:48']);

        $context = $this->contextFor($request);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $context['sub_institute_id']]);
        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $pedagogy = $this->registry->normalize('pedagogy_tags', (string) $request->input('pedagogy'), $context['sub_institute_id']);
        if ($pedagogy === null) {
            return $this->fail('That pedagogy is not in the registry.', 422);
        }

        return $this->ok([
            'node' => $node,
            'current' => $this->tagging->tagNode($node, $context),
            'preview' => $this->tagging->previewWithPedagogy($node, $context, $pedagogy),
        ]);
    }

    /**
     * POST /api/pal/h5p/nodes/{h5pType}/{nodeId}/transition
     * Promote or reject a stored tag set (draft → in_review → approved).
     */
    public function transitionTags(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Reviewing H5P tags is not available to students.')) {
            return $denied;
        }

        $request->validate(['status' => 'required|string|max:24']);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $context = $this->contextFor($request, $tenant);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $tenant]);
        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $auth = $request->attributes->get('pal_auth');
        $result = $this->tagging->transition($node, $context, (string) $request->input('status'), (int) ($auth['user_id'] ?? 0));

        if ($result === null) {
            return $this->fail('That status is not in the registry, or this node has no saved tags to transition.', 422);
        }

        return $this->ok(['node' => $node, 'model' => $result]);
    }

    /**
     * POST /api/pal/h5p/suggest-tags
     *
     * AI proposals for the fields derivation could not fill. Nothing is
     * written — the proposal comes back for review, and saving it is a
     * separate, human call to saveTags.
     */
    public function suggestTags(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'AI tagging is not available to students.')) {
            return $denied;
        }

        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'node_keys' => 'nullable|array',
            'node_keys.*' => 'string|max:64',
        ]);

        $context = $this->contextFor($request);
        $auth = $request->attributes->get('pal_auth');
        $context['user_id'] = (int) ($auth['user_id'] ?? 0);

        $nodes = $this->repository->nodesForContext($context, null, 200);

        $wanted = (array) $request->input('node_keys', []);
        if ($wanted !== []) {
            $nodes = array_values(array_filter($nodes, fn ($node) => in_array($node['node_key'], $wanted, true)));
        }

        if ($nodes === []) {
            return $this->fail('No H5P nodes matched this request.', 404);
        }

        $tagged = $this->tagging->tagNodes($nodes, $context);

        // Least complete first — AI budget goes where derivation left gaps.
        usort($nodes, fn ($a, $b) => ($tagged[$a['node_key']]['completeness'] ?? 0) <=> ($tagged[$b['node_key']]['completeness'] ?? 0));

        return $this->ok($this->tagging->proposeTags($nodes, $context, $tagged));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Engagement
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/engagement
     *
     * §8.3 engagement metadata per H5P type. Computed figures are null until
     * there is telemetry — they are never defaulted to a plausible number.
     */
    public function engagement(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate(['window_days' => 'nullable|integer|min:1|max:730']);
        $context = $this->contextFor($request);
        $window = $request->filled('window_days') ? (int) $request->input('window_days') : null;

        return $this->ok([
            'context' => $context,
            'summary' => $this->engagement->summary($context, $window),
            'by_type' => array_values($this->engagement->forTypes($context, $window)),
            'signal_weights' => $this->registry->engagementWeights($context['sub_institute_id']),
            'reference' => H5PEngagementService::REFERENCE,
        ]);
    }

    /**
     * GET /api/pal/h5p/insights
     *
     * The DeepSeek layer ON TOP of the xAPI event stream. Returns two things:
     *
     *   evidence  every figure, computed in SQL from the events. Always
     *             present, with or without AI.
     *   insight   DeepSeek reading that evidence. Absent — with an explicit
     *             status and reason — when there are no events to interpret,
     *             or when no provider key resolves.
     *
     * Read-only: nothing is written and no tag is changed.
     */
    public function insights(Request $request, H5PInsightService $insights): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'H5P insights are not available to students.')) {
            return $denied;
        }

        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'learner_id' => 'nullable|integer',
            'window_days' => 'nullable|integer|min:1|max:365',
            // The evidence pack alone, for a fast render before the model runs.
            'evidence_only' => 'nullable|boolean',
        ]);

        $context = $this->contextFor($request);
        $auth = $request->attributes->get('pal_auth');
        $learnerId = $request->filled('learner_id') ? (int) $request->input('learner_id') : null;

        $evidence = $insights->evidencePack(
            $context,
            $learnerId,
            $request->filled('window_days') ? (int) $request->input('window_days') : null
        );

        if ($request->boolean('evidence_only')) {
            return $this->ok([
                'evidence' => $evidence,
                'insight' => null,
                'ai' => [
                    'available' => $insights->available(),
                    'unavailable_reason' => $insights->unavailableReason(),
                ],
            ]);
        }

        return $this->ok([
            'evidence' => $evidence,
            'insight' => $insights->insight($evidence, $context, (int) ($auth['user_id'] ?? 0)),
            'ai' => [
                'available' => $insights->available(),
                'unavailable_reason' => $insights->unavailableReason(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pedagogy selection (§1.3)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/pedagogy/select
     *
     * Runs the registry's selection rules and returns the decision plus the
     * full trace of why each rule did or did not fire.
     */
    public function selectPedagogy(Request $request): JsonResponse
    {
        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'learner_id' => 'nullable|integer',
            'session_type' => 'nullable|string|max:32',
            'engagement_trend' => 'nullable|string|max:32',
            'pedagogy_required' => 'nullable|string|max:48',
        ]);

        $context = $this->contextFor($request);
        $learnerId = $request->filled('learner_id') ? (int) $request->input('learner_id') : null;

        return $this->ok($this->intelligence->selectPedagogy($learnerId, $context, [
            'type' => $request->input('session_type'),
            'engagement_trend' => $request->input('engagement_trend'),
            'pedagogy_required' => $request->input('pedagogy_required'),
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // xAPI ingest (§8.2)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/pal/h5p/xapi
     *
     * One statement. `learner_id` is required so PalApiAuth ownership-scopes
     * the ingest and the event is attributed to a numeric learner rather than
     * the client-supplied actor.
     */
    public function ingestXapi(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'session_id' => 'nullable|integer',
            'statement' => 'required|array',
        ]);

        $result = $this->pipeline->process(
            (array) $request->input('statement'),
            (int) $request->input('learner_id'),
            $request->filled('session_id') ? (int) $request->input('session_id') : null,
            $this->contextFor($request)
        );

        return $this->ok($result);
    }

    /** POST /api/pal/h5p/xapi/batch */
    public function ingestXapiBatch(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'session_id' => 'nullable|integer',
            'statements' => 'required|array|min:1|max:200',
        ]);

        return $this->ok($this->pipeline->processBatch(
            (array) $request->input('statements'),
            (int) $request->input('learner_id'),
            $request->filled('session_id') ? (int) $request->input('session_id') : null,
            $this->contextFor($request)
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The curriculum + tenant scope for a request. The institute always comes
     * from the caller's own token, never from the query string, so a caller
     * cannot read another tenant's H5P estate by passing its id.
     */
    protected function contextFor(Request $request, ?int $tenant = null): array
    {
        return [
            'chapter_id' => $request->filled('chapter_id') ? (int) $request->input('chapter_id') : null,
            'subject_id' => $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
            'standard_id' => $request->filled('standard_id') ? (int) $request->input('standard_id') : null,
            'sub_institute_id' => $tenant ?? $this->tenantFor($request),
        ];
    }

    /** Read scope. The super admin may target an institute explicitly. */
    protected function tenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return $request->filled('sub_institute_id') ? (int) $request->get('sub_institute_id') : null;
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    /** A write must name exactly one institute; an ambiguous CSV is rejected. */
    protected function writeTenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2 && $request->filled('sub_institute_id')) {
            return (int) $request->get('sub_institute_id');
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if ($sub === '' || str_contains($sub, ',')) {
            return null;
        }

        return (int) $sub;
    }

    protected function denyStudents(Request $request, string $message = 'The H5P Model workspace is not available to students.'): ?JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        return ! empty($auth['is_student']) ? $this->fail($message, 403) : null;
    }

    protected function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    protected function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
