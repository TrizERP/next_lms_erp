<?php

namespace App\Services\CoursePresentation\Templates;

use App\Services\CoursePresentation\PresentationQuality;

use App\Services\StudyDeck\StudyDeckImages;

/**
 * The pictures that may be shown beside a concept, taken from the EXISTING picture store and nothing else.
 *
 * A picture row (`study_deck_images`) has no concept, slide or licence column, and a link row
 * (`study_deck_image_links`) only ties a picture to a stored deck. The one place that says which concept a picture was
 * made for is a deck: the slide it sits on names the concepts it teaches. So the only verified picture-to-concept
 * relation for a chapter with no stored deck is an earlier draft's own records (plan.json says which concepts a slide
 * teaches, images.json says which picture sits on it). A picture is offered for a concept only when ALL of this holds:
 *
 *   - the earlier draft's slide TEACHES that concept (not merely the same chapter);
 *   - the library still has the picture and the school may read it, with a real size;
 *   - what is drawn on it is grounded in the Semantic Intelligence of the concepts the slide taught: at least 80% of a
 *     diagram's labels have all their words in that intelligence (name, definition, statements, examples, ...); a photograph is left out unless `preview_photographs` is on, and then needs a recorded relevance review and a recorded licence;
 *   - the concept is still one of the chapter's concepts.
 *
 * Nothing is fetched, generated, stored or linked.
 */
class ImagePool
{
    private StudyDeckImages $images;

    public function __construct(?StudyDeckImages $images = null)
    {
        $this->images = $images ?? new StudyDeckImages();
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @return array{by_concept:array<int,array<string,mixed>>,rejected:array<int,array{slide:int,reason:string}>,considered:int}
     */
    public function forChapter(array $context, int $tenant): array
    {
        $dir = storage_path((string) config('course_presentation_pilot.earlier_bundle_base', 'app/study-deck') . '/chapter-' . (int) $context['chapter']['id']);
        $images = $this->json($dir . '/images.json');
        $plan = $this->json($dir . '/plan.json');
        // The earlier draft's own hotspots (the marked parts of a drawn diagram) are carried only when that draft passed its own validation.
        $report = $this->json($dir . '/report.json');
        $draft = !empty($report['ok']) ? $this->json($dir . '/deck.json') : null;
        $draftSlides = [];
        foreach ((array) ($draft['slides'] ?? []) as $ds) {
            $draftSlides[(int) ($ds['n'] ?? 0)] = $ds;
        }
        if ($images === null || $plan === null) {
            return ['by_concept' => [], 'rejected' => [], 'considered' => 0];
        }

        $taught = [];
        foreach ((array) ($plan['slides'] ?? []) as $s) {
            $taught[(int) ($s['n'] ?? 0)] = array_map('intval', (array) ($s['taught_concept_ids'] ?? []));
        }
        // Labels are checked against the concept's own source data (its intelligence, with the chapter text kept out of sight as a fallback).
        $grounds = (array) ($context['ground_by_concept'] ?? []);
        $known = array_map('intval', array_keys((array) $context['concepts']));

        $refs = [];
        foreach ($images as $n => $img) {
            if (($id = StudyDeckImages::idFromRef($img['url'] ?? null)) !== null) {
                $refs[(int) $n] = $id;
            }
        }
        $metas = $this->images->visibleMetas(array_values($refs), $tenant);

        $byConcept = [];
        $rejected = [];
        foreach ($images as $n => $img) {
            $n = (int) $n;
            $id = $refs[$n] ?? null;
            $meta = $id !== null ? ($metas[$id] ?? null) : null;
            if ($meta === null || (int) $meta['width'] < 1 || (int) $meta['height'] < 1) {
                $rejected[] = ['slide' => $n, 'reason' => 'the picture is not in the library or this school cannot read it'];
                continue;
            }
            $concepts = array_values(array_intersect($taught[$n] ?? [], $known));
            if ($concepts === []) {
                $rejected[] = ['slide' => $n, 'reason' => 'the earlier slide did not teach a concept of this chapter'];
                continue;
            }
            $text = $this->norm(implode(' ', array_map(fn ($cid) => (string) ($grounds[$cid] ?? ''), $concepts)));
            if (($why = $this->ungrounded($img, $text)) !== null) {
                $rejected[] = ['slide' => $n, 'reason' => $why];
                continue;
            }
            $concept = $concepts[0];
            if (isset($byConcept[$concept])) {
                continue; // one picture per concept; another picture for the same concept is not an error
            }
            $byConcept[$concept] = [
                'type' => ($img['type'] ?? 'diagram') === 'diagram' ? 'diagram' : 'photo',
                'url' => StudyDeckImages::ref($id),
                'alt' => (string) ($img['alt'] ?? ''),
                'caption' => $img['caption'] ?? null,
                'width' => (int) $meta['width'],
                'height' => (int) $meta['height'],
                'texts' => array_values((array) ($img['texts'] ?? [])),
                'licence' => $img['licence'] ?? null,
                'source_url' => $img['source_url'] ?? null,
                'creator' => $img['creator'] ?? null,
                'attribution' => (string) ($img['attribution'] ?? ''),
                'attribution_required' => (bool) ($img['attribution_required'] ?? false),
                'interaction' => $this->hotspots($draftSlides[$n]['interaction'] ?? null, ($img['type'] ?? 'diagram') === 'diagram'),
            ];
        }

        return ['by_concept' => $byConcept, 'rejected' => $rejected, 'considered' => count($images)];
    }

    /**
     * The earlier draft's hotspots for a picture, or null. Only the shape the viewer plays (parts with their own boxes and text)
     * is carried, and only when it passes the same shape rules as the viewer; nothing is placed by guesswork.
     *
     * @return array<string,mixed>|null
     */
    private function hotspots(mixed $interaction, bool $diagram): ?array
    {
        if (!$diagram || !is_array($interaction) || ($interaction['kind'] ?? '') !== 'hotspots') {
            return null;
        }
        $spots = [];
        foreach ((array) ($interaction['spots'] ?? []) as $s) {
            $spots[] = array_diff_key((array) $s, ['image' => 1]);
        }
        $clean = ['kind' => 'hotspots', 'reason' => (string) ($interaction['reason'] ?? ''), 'intro' => (string) ($interaction['intro'] ?? ''), 'spots' => $spots, 'wrapup' => (string) ($interaction['wrapup'] ?? '')];

        return PresentationQuality::interactionProblem($clean, true) === null ? $clean : null;
    }

    /** Why a picture is not grounded in the chapter text, or null when it is. @param array<string,mixed> $img */
    private function ungrounded(array $img, string $text): ?string
    {
        if (($img['type'] ?? '') === 'diagram') {
            $labels = array_values(array_filter(array_map('strval', (array) ($img['texts'] ?? []))));
            if ($labels === []) {
                return 'the diagram has no recorded labels to check';
            }
            $found = 0;
            foreach ($labels as $label) {
                $words = array_filter(explode(' ', $this->norm($label)), fn ($w) => strlen($w) >= 3);
                // A word is in the text if it is there as written, or (for a plural label) as its singular.
                $inText = fn (string $w) => str_contains($text, $w) || (strlen($w) > 3 && str_ends_with($w, 's') && str_contains($text, substr($w, 0, -1)));
                if ($words === [] || array_filter($words, fn ($w) => !$inText($w)) === []) {
                    $found++;
                }
            }

            return $found / count($labels) >= 0.8 ? null : sprintf('only %d of %d diagram labels are found in the concept\'s source data', $found, count($labels));
        }

        if (!config('course_presentation_pilot.preview_photographs', false)) {
            return 'a photograph cannot be checked against the concept\'s source data, so only verified diagrams are placed';
        }

        return !empty($img['review']) && !empty($img['licence']) ? null : 'a photograph needs a recorded relevance review and licence';
    }

    private function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
    }

    /** @return array<string,mixed>|null */
    private function json(string $path): ?array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : null;
    }
}
