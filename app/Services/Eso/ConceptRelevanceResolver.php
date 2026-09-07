<?php

namespace App\Services\Eso;

use App\Services\PAL\ContentModel\SemanticSourceRepository;
use Illuminate\Support\Facades\DB;

/**
 * "Why does this concept matter in real life?" — the one fact the practice
 * motivation message is built from (see EsoPalRenderer::practiceMotivationInstruction()).
 *
 * The extraction pipeline already captures this per concept as
 * `semantic_intelligence.real_world_applications` (entries shaped
 * `{application_type, example, relevance, concept_name}`), and
 * SemanticSourceRepository already knows how to read it — but it is keyed by
 * `semantic_intelligence.id` + a name-derived slug, NOT by `lms_concept.id`,
 * which is the id space the engine works in (the extractor's own concept ids
 * are a separate namespace and do not reference lms_concept — see
 * config/pal_content_model.php's `source.chapter_join` note). This class is
 * that missing join: lms_concept.id -> chapter -> semantic row -> name match.
 *
 * Not every concept has this data. The extraction for a chapter can omit
 * concepts entirely (Chapter 1014's own Concept 114 is one of four that were
 * dropped from its extraction), so a caller must always be able to proceed
 * without it — hence the fallback to `lms_concept.description`, which is the
 * only per-concept text guaranteed to be populated. The fallback is a
 * definition rather than a real-world hook, and is labelled as such in the
 * return value so the caller never presents it as something it isn't.
 */
class ConceptRelevanceResolver
{
    public function __construct(protected SemanticSourceRepository $semantic)
    {
    }

    /**
     * @return array{source:'real_world'|'definition'|'none', text:?string, application_type:?string}
     */
    public function forConcept(int $conceptId, ?int $subInstituteId = null): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'name', 'chapter_id', 'description']);

        if ($concept === null) {
            return ['source' => 'none', 'text' => null, 'application_type' => null];
        }

        $application = $this->realWorldApplication($concept, $subInstituteId);
        if ($application !== null) {
            return $application;
        }

        $description = trim((string) ($concept->description ?? ''));

        return $description === ''
            ? ['source' => 'none', 'text' => null, 'application_type' => null]
            : ['source' => 'definition', 'text' => $description, 'application_type' => null];
    }

    /**
     * The concept's first high-relevance real-world application, or the first
     * of any relevance. Returns null when this chapter was never extracted,
     * when this particular concept was omitted from the extraction, or when
     * its applications slice is empty — all real, common states.
     */
    protected function realWorldApplication(object $concept, ?int $subInstituteId): ?array
    {
        $semanticId = DB::table('semantic_intelligence')
            ->where('chapter_id', $concept->chapter_id)
            ->value('id');

        if ($semanticId === null) {
            return null;
        }

        try {
            $entry = $this->semantic->concept((int) $semanticId, $this->semantic->slug((string) $concept->name), $subInstituteId);
        } catch (\Throwable) {
            // The semantic blob is extractor-owned and its shape varies; a
            // motivational line is never worth failing a learning step over.
            return null;
        }

        $applications = $entry['real_world_applications'] ?? [];
        if (! is_array($applications) || $applications === []) {
            return null;
        }

        $chosen = null;
        foreach ($applications as $application) {
            if (! is_array($application) || trim((string) ($application['example'] ?? '')) === '') {
                continue;
            }
            $chosen ??= $application;
            if (strcasecmp((string) ($application['relevance'] ?? ''), 'high') === 0) {
                $chosen = $application;
                break;
            }
        }

        if ($chosen === null) {
            return null;
        }

        return [
            'source' => 'real_world',
            'text' => trim((string) $chosen['example']),
            'application_type' => trim((string) ($chosen['application_type'] ?? '')) ?: null,
        ];
    }
}
