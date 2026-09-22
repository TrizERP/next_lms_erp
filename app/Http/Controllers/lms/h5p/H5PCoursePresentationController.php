<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\h5p\H5pCoursePresentation;
use App\Models\lms\h5p\H5pDragDrop;
use App\Models\lms\h5p\H5pPresentationSlide;
use App\Models\lms\h5p\H5pSlideElement;
use App\Services\lms\H5P\H5PCoursePresentationBuilder;
use App\Services\lms\H5P\H5PCoursePresentationPackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * H5P Course Presentation (H5P.CoursePresentation).
 *
 * The only three-level type in the family, and the ref handling is why it
 * needs more than the base class gives it.
 *
 * IDS ARE ASSIGNED IN TWO PASSES. Slides reference each other (a branch
 * destination) and elements reference slides (a go-to button), so the client
 * sends stable client-side REFS, never ids. A save:
 *
 *   1. inserts every slide, building ref -> real id;
 *   2. inserts every element with its target refs already resolved;
 *   3. back-fills each slide's `next_slide_id` from the same map.
 *
 * That is one UPDATE per branching slide, which is why refs never reach the
 * database and why a brand new slide can branch to another brand new slide in
 * the same save -- the normal case when authoring from scratch.
 *
 * CROSS-DECK REFERENCES ARE REFUSED, not clamped. A branch or a go-to button
 * pointing at a slide in another presentation is a dead end a learner cannot
 * get out of, and silently dropping it would leave a button that does nothing.
 * Both are validation errors naming the slide.
 *
 * AN EMBEDDED DRAG AND DROP IS A REFERENCE, not a copy -- see the migration.
 * The referenced activity is checked on save: it must exist, belong to this
 * tenant and this chapter, and be published, because a deck that embeds a
 * draft would show students an activity its author has not released.
 */
class H5PCoursePresentationController extends H5PContentTypeController
{
    public function __construct(
        private readonly H5PCoursePresentationBuilder $builder,
        private readonly H5PCoursePresentationPackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pCoursePresentation::class;
    }

    protected function registryCode(): string
    {
        return 'course_presentation';
    }

    protected function routePrefix(): string
    {
        return 'h5p_course_presentation';
    }

    protected function payloadKey(): string
    {
        return 'coursePresentation';
    }

    protected function listKey(): string
    {
        return 'coursePresentationLists';
    }

    protected function label(): string
    {
        return 'Course presentation';
    }

    protected function relations(): array
    {
        return ['slides.elements'];
    }

    /**
     * Both child tables, deepest first.
     *
     * The base class derives this from relations() by stripping nested paths,
     * which would give `slides` alone and leave the elements behind as orphans
     * on every update. A deck is the one type where that derivation is wrong.
     */
    protected function childRelations(): array
    {
        return ['elements', 'slides'];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/coursepresentation';
    }

    protected function mediaRoles(): array
    {
        return [
            'slide_background' => 'image',
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
        ];
    }

    protected function authoringDefaults(): array
    {
        return [
            'theme' => 'default',
            'slide_transition' => 'fade',
            'show_progress_bar' => true,
            'show_keywords' => true,
            'show_summary_slide' => true,
            'enable_print' => false,
            'active_surface' => false,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'pass_percentage' => 60,
            'themes' => H5pCoursePresentation::THEMES,
            'transitions' => H5pCoursePresentation::TRANSITIONS,
            'element_types' => H5pSlideElement::TYPES,
            'scored_element_types' => H5pSlideElement::SCORED_TYPES,
        ];
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    protected function saveRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'theme' => 'nullable|in:' . implode(',', H5pCoursePresentation::THEMES),
            'slide_transition' => 'nullable|in:' . implode(',', H5pCoursePresentation::TRANSITIONS),
            'show_progress_bar' => 'nullable|boolean',
            'show_keywords' => 'nullable|boolean',
            'show_summary_slide' => 'nullable|boolean',
            'enable_print' => 'nullable|boolean',
            'active_surface' => 'nullable|boolean',
            'enable_retry' => 'nullable|boolean',
            'enable_show_solution' => 'nullable|boolean',
            'pass_percentage' => 'nullable|integer|min:0|max:100',

            'feedback_bands' => 'nullable|array',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',

            'slides' => 'required|array|min:1',
            'slides.*.ref' => 'required|string|max:64',
            'slides.*.title' => 'nullable|string|max:255',
            'slides.*.background_image' => 'nullable|string|max:2048',
            'slides.*.background_token' => 'nullable|string|max:32',
            'slides.*.notes' => 'nullable|string|max:20000',
            'slides.*.next_slide_ref' => 'nullable|string|max:64',

            'slides.*.elements' => 'nullable|array',
            'slides.*.elements.*.element_type' => 'required|in:' . implode(',', H5pSlideElement::TYPES),
            'slides.*.elements.*.position_x' => 'required|numeric|min:0|max:100',
            'slides.*.elements.*.position_y' => 'required|numeric|min:0|max:100',
            'slides.*.elements.*.width' => 'required|numeric|min:1|max:100',
            'slides.*.elements.*.height' => 'required|numeric|min:1|max:100',
            'slides.*.elements.*.content_text' => 'nullable|string|max:20000',
            'slides.*.elements.*.media_path' => 'nullable|string|max:2048',
            'slides.*.elements.*.media_alt' => 'nullable|string|max:255',
            'slides.*.elements.*.options' => 'nullable|array',
            'slides.*.elements.*.ref_content_id' => 'nullable|integer',
            'slides.*.elements.*.points' => 'nullable|integer|min:0|max:100',
        ];
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'theme' => $data['theme'] ?? 'default',
            'slide_transition' => $data['slide_transition'] ?? 'fade',
            'show_progress_bar' => $data['show_progress_bar'] ?? true,
            'show_keywords' => $data['show_keywords'] ?? true,
            'show_summary_slide' => $data['show_summary_slide'] ?? true,
            'enable_print' => $data['enable_print'] ?? false,
            'active_surface' => $data['active_surface'] ?? false,
            'enable_retry' => $data['enable_retry'] ?? true,
            'enable_show_solution' => $data['enable_show_solution'] ?? true,
            'pass_percentage' => $data['pass_percentage'] ?? 60,
            'feedback_bands' => $data['feedback_bands'] ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // Children
    // -----------------------------------------------------------------------

    protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        $slides = array_values($data['slides']);

        // Pass 1: slides, building the ref map every reference resolves through.
        $slideIdByRef = [];
        foreach ($slides as $index => $slide) {
            $row = H5pPresentationSlide::create([
                'presentation_id' => $item->id,
                'slide_index' => $index,
                'title' => $slide['title'] ?? null,
                'background_image' => $slide['background_image'] ?? null,
                'background_token' => $slide['background_token'] ?? null,
                'notes' => $slide['notes'] ?? null,
                // Filled in pass 3, once every ref has an id.
                'next_slide_id' => null,
            ] + $audit);

            $slideIdByRef[(string) $slide['ref']] = $row->id;
        }

        // Pass 2: elements, with their targets already resolved.
        foreach ($slides as $slide) {
            $slideId = $slideIdByRef[(string) $slide['ref']];

            foreach (array_values((array) ($slide['elements'] ?? [])) as $order => $element) {
                H5pSlideElement::create($this->elementAttributes($element, $order, $slideIdByRef, $item) + [
                    'presentation_id' => $item->id,
                    'slide_id' => $slideId,
                ] + $audit);
            }
        }

        // Pass 3: branching. Only the slides that have a destination.
        foreach ($slides as $slide) {
            $targetRef = trim((string) ($slide['next_slide_ref'] ?? ''));
            if ($targetRef === '') {
                continue;
            }

            if (! isset($slideIdByRef[$targetRef])) {
                throw ValidationException::withMessages([
                    'slides' => sprintf(
                        'Slide "%s" branches to a slide that is not in this presentation.',
                        $slide['title'] ?? $slide['ref']
                    ),
                ]);
            }

            H5pPresentationSlide::where('id', $slideIdByRef[(string) $slide['ref']])
                ->update(['next_slide_id' => $slideIdByRef[$targetRef]]);
        }
    }

    /**
     * One element row, with refs resolved and the payload narrowed to its kind.
     *
     * @param  array<string,mixed>  $element
     * @param  array<string,int>  $slideIdByRef
     * @return array<string,mixed>
     */
    private function elementAttributes(array $element, int $order, array $slideIdByRef, Model $deck): array
    {
        $type = (string) $element['element_type'];
        $options = (array) ($element['options'] ?? []);
        $refContentId = null;

        if ($type === 'goto_slide') {
            $targetRef = trim((string) ($options['target_slide_ref'] ?? ''));
            if ($targetRef === '' || ! isset($slideIdByRef[$targetRef])) {
                throw ValidationException::withMessages([
                    'slides' => 'A "go to slide" button points at a slide that is not in this presentation.',
                ]);
            }
            $options = [
                'target_slide_id' => $slideIdByRef[$targetRef],
                'label' => (string) ($options['label'] ?? $element['content_text'] ?? 'Continue'),
            ];
        }

        if ($type === 'drag_drop') {
            $refContentId = $this->validEmbeddedDragDropId($element, $deck);
            // The reference is the content. Nothing else is stored, so an
            // edit to the referenced activity is picked up everywhere it is
            // embedded rather than in a stale copy.
            $options = [];
        }

        return [
            'element_type' => $type,
            'position_x' => $element['position_x'],
            'position_y' => $element['position_y'],
            'width' => $element['width'],
            'height' => $element['height'],
            'content_text' => $element['content_text'] ?? null,
            'media_path' => in_array($type, ['image', 'video', 'audio'], true) ? ($element['media_path'] ?? null) : null,
            'media_alt' => $element['media_alt'] ?? null,
            'options' => $options !== [] ? $options : null,
            'ref_content_id' => $refContentId,
            // A static element is worth nothing, whatever the client sent.
            // This is the one place max score is defined, so a text box cannot
            // be saved with points and inflate a deck's total.
            'points' => in_array($type, H5pSlideElement::SCORED_TYPES, true)
                ? max(1, (int) ($element['points'] ?? 1))
                : 0,
            'sort_order' => $order,
        ];
    }

    /**
     * The id of the Drag and Drop activity this element embeds.
     *
     * Checked rather than trusted: same tenant, same chapter, published. An id
     * from another school would otherwise be readable through a deck, which is
     * the one way this type could leak across a tenant boundary.
     *
     * @param  array<string,mixed>  $element
     */
    private function validEmbeddedDragDropId(array $element, Model $deck): int
    {
        $id = (int) ($element['ref_content_id'] ?? 0);

        if ($id <= 0) {
            throw ValidationException::withMessages([
                'slides' => 'An embedded drag and drop has no activity chosen.',
            ]);
        }

        $task = H5pDragDrop::where('id', $id)
            ->where('sub_institute_id', $deck->sub_institute_id)
            ->where('chapter_id', $deck->chapter_id)
            ->first();

        if ($task === null) {
            throw ValidationException::withMessages([
                'slides' => 'An embedded drag and drop activity could not be found in this chapter.',
            ]);
        }

        if ($task->status !== 'published') {
            throw ValidationException::withMessages([
                'slides' => sprintf(
                    'The embedded drag and drop "%s" is still a draft. Publish it before embedding it in a presentation.',
                    $task->title
                ),
            ]);
        }

        return $id;
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'theme', 'slide_transition', 'show_progress_bar', 'show_keywords',
            'show_summary_slide', 'enable_print', 'active_surface', 'enable_retry',
            'enable_show_solution', 'pass_percentage', 'feedback_bands',
            'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $original->loadMissing('slides.elements');

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];

        // Same three passes as a save, for the same reason: the branching
        // targets are ids in the ORIGINAL deck and have to be remapped to the
        // copy's, or a duplicated deck would branch into the one it came from.
        $idMap = [];
        foreach ($original->slides as $slide) {
            $row = H5pPresentationSlide::create($slide->only([
                'slide_index', 'title', 'background_image', 'background_token', 'notes',
            ]) + ['presentation_id' => $copy->id, 'next_slide_id' => null] + $audit);

            $idMap[(int) $slide->id] = $row->id;
        }

        foreach ($original->slides as $slide) {
            foreach ($slide->elements as $element) {
                $options = (array) ($element->options ?? []);
                if ($element->element_type === 'goto_slide' && isset($options['target_slide_id'])) {
                    $options['target_slide_id'] = $idMap[(int) $options['target_slide_id']] ?? null;
                }

                H5pSlideElement::create($element->only([
                    'element_type', 'position_x', 'position_y', 'width', 'height',
                    'content_text', 'media_path', 'media_alt', 'ref_content_id',
                    'points', 'sort_order',
                ]) + [
                    'presentation_id' => $copy->id,
                    'slide_id' => $idMap[(int) $slide->id],
                    'options' => $options !== [] ? $options : null,
                ] + $audit);
            }

            if ($slide->next_slide_id !== null && isset($idMap[(int) $slide->next_slide_id])) {
                H5pPresentationSlide::where('id', $idMap[(int) $slide->id])
                    ->update(['next_slide_id' => $idMap[(int) $slide->next_slide_id]]);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        $item->load('slides.elements');

        if ($item->slides->isEmpty()) {
            return 'Add at least one slide before publishing.';
        }

        /*
         * A deck with no scored element is NOT blocked. A lecture deck is a
         * legitimate presentation -- unlike a drag-and-drop task with no
         * correct mapping, which renders fine and awards nothing without
         * anybody noticing. The difference is that a deck with no questions
         * is obviously a deck with no questions.
         */

        foreach ($item->slides as $slide) {
            foreach ($slide->elements as $element) {
                $problem = $element->authoringProblem();
                if ($problem !== null) {
                    return sprintf('Slide %d has %s.', (int) $slide->slide_index + 1, $problem);
                }
            }
        }

        // An embedded activity that has since been unpublished or deleted.
        // Checked here and not only on save, because the referenced row can
        // change after this deck was last written.
        foreach ($item->elements()->where('element_type', 'drag_drop')->get() as $element) {
            $task = H5pDragDrop::find($element->ref_content_id);
            if ($task === null || $task->status !== 'published') {
                return 'An embedded drag and drop activity is missing or is no longer published.';
            }
        }

        if ($item->active_surface && ! $this->everySlideHasAWayForward($item)) {
            // With the navigation chrome hidden, a slide with no button is a
            // slide a learner is stuck on. This is the one failure mode of
            // this type that a teacher cannot see by clicking through the
            // deck themselves as an author.
            return 'Navigation is hidden on this presentation, but a slide has no way forward. Add a "go to slide" button, or turn navigation back on.';
        }

        return null;
    }

    private function everySlideHasAWayForward(Model $item): bool
    {
        $last = $item->slides->last();

        foreach ($item->slides as $slide) {
            if ($slide->id === $last->id) {
                continue;
            }
            if ($slide->next_slide_id !== null) {
                continue;
            }
            if ($slide->elements->contains(fn (H5pSlideElement $e) => $e->element_type === 'goto_slide')) {
                continue;
            }

            return false;
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Params and packages
    // -----------------------------------------------------------------------

    protected function buildParams(Model $item): array
    {
        return $this->builder->build($item);
    }

    protected function exportPackage(Model $item): array
    {
        return $this->packages->export($item);
    }

    protected function parsePackage(UploadedFile $file, int|string|null $subInstituteId): array
    {
        return $this->packages->import($file, $subInstituteId);
    }

    protected function createFromImport(array $parsed, Request $request, int|string|null $subInstituteId, int|string|null $userId): Model
    {
        $deck = H5pCoursePresentation::create($parsed['deck'] + [
            'title' => $parsed['title'],
            'description' => '',
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'sub_institute_id' => $subInstituteId,
            'syear' => $request->input('syear'),
            'status' => 'draft',
            'library' => $this->libraryVersionString(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];

        // The parse side addresses slides by INDEX, not ref -- a package has
        // an ordered list and nothing else to key on. Same three passes.
        $idByIndex = [];
        foreach ($parsed['slides'] as $slide) {
            $row = H5pPresentationSlide::create([
                'presentation_id' => $deck->id,
                'slide_index' => $slide['slide_index'],
                'title' => $slide['title'],
                'background_image' => $slide['background_image'],
                'background_token' => $slide['background_token'],
                'notes' => $slide['notes'],
                'next_slide_id' => null,
            ] + $audit);

            $idByIndex[(int) $slide['slide_index']] = $row->id;
        }

        foreach ($parsed['slides'] as $slide) {
            foreach ($slide['elements'] as $element) {
                $options = (array) ($element['options'] ?? []);

                if ($element['element_type'] === 'goto_slide') {
                    $targetIndex = (int) ($options['_target_slide_index'] ?? -1);
                    unset($options['_target_slide_index']);
                    // A target outside the imported deck cannot be honoured.
                    // The button is kept and reported by publish rather than
                    // dropped, so the author sees what the package intended.
                    $options['target_slide_id'] = $idByIndex[$targetIndex] ?? null;
                }

                H5pSlideElement::create([
                    'presentation_id' => $deck->id,
                    'slide_id' => $idByIndex[(int) $slide['slide_index']],
                    'element_type' => $element['element_type'],
                    'position_x' => $element['position_x'],
                    'position_y' => $element['position_y'],
                    'width' => $element['width'],
                    'height' => $element['height'],
                    'content_text' => $element['content_text'],
                    'media_path' => $element['media_path'],
                    'media_alt' => $element['media_alt'],
                    'options' => $options !== [] ? $options : null,
                    'ref_content_id' => $element['ref_content_id'],
                    'points' => $element['points'],
                    'sort_order' => $element['sort_order'],
                ] + $audit);
            }

            if ($slide['_next_slide_index'] !== null && isset($idByIndex[$slide['_next_slide_index']])) {
                H5pPresentationSlide::where('id', $idByIndex[(int) $slide['slide_index']])
                    ->update(['next_slide_id' => $idByIndex[$slide['_next_slide_index']]]);
            }
        }

        return $deck;
    }
}
