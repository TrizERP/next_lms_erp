<?php

namespace App\Services\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;
use App\Services\PAL\ContentModel\ContentModelProjector;
use App\Services\PAL\ContentModel\SemanticSourceRepository;
use Illuminate\Support\Facades\DB;

/**
 * "What is the richest learning object that genuinely exists for this node?"
 *
 * ESO teaching has until now been a text instruction plus an MCQ, while the
 * PAL Content Model already models four delivery formats per concept
 * (text+diagram, video, story/audio, simulation — config('pal_content_model.variant_blueprint')).
 * This class is the missing join between the two. It builds nothing: every
 * value it returns is read from the content model or from an authored
 * override, and when neither has anything it returns null so the caller keeps
 * today's plain-text teaching.
 *
 * Two things about the existing architecture that this deliberately does NOT
 * paper over:
 *
 *  1. ContentModelProjector::conceptLearningVariants() emits an AUTHORING
 *     SPECIFICATION, not a deliverable asset. A slot with format 'video' means
 *     "a video belongs here, and here is the extracted material to author it
 *     from" — it carries no URL. Treating that slot as a video to play would
 *     be inventing content. So a derived variant is served as the rich text it
 *     actually is, and only an authored override can carry real media.
 *
 *  2. The extractor's concept namespace is not lms_concept's. The join runs
 *     lms_concept.id -> chapter_id -> semantic_intelligence row -> name slug,
 *     the same join ConceptRelevanceResolver already makes. A chapter's
 *     extraction can omit concepts entirely (Chapter 1014's extraction has 13
 *     concepts and does not include Concept 114), so "no content" is a normal,
 *     expected outcome, not an error.
 */
class EsoLearningContentResolver
{
    /** Where an authored asset (including any real media URL) lives. */
    protected const OVERRIDE_TABLE = 'pal_cm_node_overrides';

    public function __construct(
        protected SemanticSourceRepository $semantic,
        protected ContentModelProjector $projector,
    ) {
    }

    /**
     * The learning object to serve for this node right now, or null to fall
     * back to plain-text teaching.
     *
     * Which of the four format variants is chosen is NOT a new rule invented
     * here: the blueprint already declares its own re-route order
     * (config('pal_content.variant_ladder')) and its own `when_served`
     * conditions ("First delivery", "Variant 1 failed", "Variant 2 failed, or
     * auditory learner"). This walks that ladder using `cfu_attempts` — the
     * count of failed checks of understanding the CFU gate already records —
     * so a re-explanation is genuinely a different FORMAT rather than the same
     * text again.
     *
     * @return array{
     *     variant:int, format:string, format_label:string, h5p_type:?string,
     *     title:?string, body:?string, media_url:?string, source:string
     * }|null
     */
    public function forNode(ConceptNode $node, LearnerNodeState $state, ?int $subInstituteId = null): ?array
    {
        try {
            $variants = $this->variantsForConcept((int) $node->concept_id, $subInstituteId);
        } catch (\Throwable) {
            // The semantic blob is extractor-owned and its shape varies. A
            // richer format is never worth failing a learning step over —
            // same principle as ConceptRelevanceResolver.
            return null;
        }

        if ($variants === []) {
            return null;
        }

        foreach ($this->ladderFrom((int) $state->cfu_attempts) as $slot) {
            $variant = $variants[$slot] ?? null;
            if ($variant === null) {
                continue;
            }

            $resolved = $this->resolveVariant($variant, $subInstituteId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * The blueprint's re-route order, rotated so the student's next
     * re-explanation starts at the next rung rather than repeating the format
     * that just failed. Later rungs still fall back through the earlier ones,
     * because a gap in a later slot must not dead-end a student who has
     * something perfectly serviceable in slot 1.
     *
     * @return array<int, int>
     */
    protected function ladderFrom(int $failedChecks): array
    {
        $ladder = config('pal_content.variant_ladder', [1, 2, 3, 4]);
        if (! is_array($ladder) || $ladder === []) {
            $ladder = [1, 2, 3, 4];
        }

        $offset = $failedChecks % count($ladder);

        return array_merge(array_slice($ladder, $offset), array_slice($ladder, 0, $offset));
    }

    /**
     * Prefer an authored override (the only place a real media asset can
     * exist) over the derived specification. A derived variant is usable only
     * when the extraction actually backs it — `asset_state === 'specified'`;
     * a 'gap' slot is skipped rather than served as an empty promise.
     *
     * @param  array<string, mixed>  $variant
     */
    protected function resolveVariant(array $variant, ?int $subInstituteId): ?array
    {
        $override = $this->authoredOverride((string) $variant['node_key'], $subInstituteId);

        if ($override !== null) {
            $body = trim((string) ($override->body ?? '')) ?: trim((string) ($variant['body'] ?? ''));
            $mediaUrl = trim((string) ($override->media_url ?? '')) ?: null;

            if ($body !== '' || $mediaUrl !== null) {
                return $this->shape($variant, $override->title ?? $variant['title'], $body, $mediaUrl, 'authored');
            }
        }

        if (($variant['asset_state'] ?? 'gap') !== 'specified') {
            return null;
        }

        $body = trim((string) ($variant['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        // Derived: real extracted material, but no asset behind it. It is
        // served as the text it is — the format is reported for context, never
        // as a media file the student can play.
        return $this->shape($variant, $variant['title'] ?? null, $body, null, 'derived');
    }

    /** @param  array<string, mixed>  $variant */
    protected function shape(array $variant, ?string $title, string $body, ?string $mediaUrl, string $source): array
    {
        return [
            'variant' => (int) ($variant['variant_number'] ?? 0),
            'format' => (string) ($variant['format'] ?? 'text_diagram'),
            'format_label' => (string) ($variant['format_label'] ?? 'Learning content'),
            'h5p_type' => $variant['h5p_type'] ?? null,
            'title' => $title !== null && trim((string) $title) !== '' ? trim((string) $title) : null,
            'body' => $body !== '' ? $body : null,
            'media_url' => $mediaUrl,
            'source' => $source,
        ];
    }

    /**
     * An approved authored asset for this variant. Draft and in-review rows are
     * deliberately ignored — unreviewed content must never reach a student,
     * which is the same `quality_status` gate QuestionMetadata::servable()
     * applies to questions.
     */
    protected function authoredOverride(string $nodeKey, ?int $subInstituteId): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable(self::OVERRIDE_TABLE)) {
            return null;
        }

        return DB::table(self::OVERRIDE_TABLE)
            ->where('node_key', $nodeKey)
            ->whereIn('sub_institute_id', array_unique([(int) ($subInstituteId ?? 0), 0]))
            ->where('quality_status', 'approved')
            // A tenant's own authored asset outranks the shared (0) one.
            ->orderByDesc('sub_institute_id')
            ->first(['title', 'body', 'media_url']);
    }

    /**
     * The concept's four variant slots, keyed by variant number.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function variantsForConcept(int $conceptId, ?int $subInstituteId): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'name', 'chapter_id']);
        if ($concept === null) {
            return [];
        }

        $semanticId = DB::table('semantic_intelligence')
            ->where('chapter_id', $concept->chapter_id)
            ->value('id');

        if ($semanticId === null) {
            return [];
        }

        $loaded = $this->semantic->conceptsFor((int) $semanticId, $subInstituteId);
        $header = $loaded['header'] ?? null;
        if ($header === null) {
            return [];
        }

        $slug = $this->semantic->slug((string) $concept->name);
        $entry = null;
        foreach ($loaded['concepts'] ?? [] as $candidate) {
            if (($candidate['slug'] ?? null) === $slug) {
                $entry = $candidate;
                break;
            }
        }

        // The chapter was extracted but this particular concept was not part of
        // the extraction — common, and not an error.
        if ($entry === null) {
            return [];
        }

        $projected = $this->projector->conceptLearningVariants(
            (int) $semanticId,
            $entry,
            $header,
            $loaded['chapter'] ?? []
        );

        $byVariant = [];
        foreach ($projected['variants'] ?? [] as $variant) {
            $byVariant[(int) ($variant['variant_number'] ?? 0)] = $variant;
        }

        return $byVariant;
    }
}
