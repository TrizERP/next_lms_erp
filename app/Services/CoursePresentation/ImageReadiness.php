<?php

namespace App\Services\CoursePresentation;

use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Support\Facades\DB;

/**
 * What is known about the pictures of a chapter, read from the EXISTING study-deck picture store and nothing else.
 *
 * The store (StudyDeckImages) keeps one row per school and picture in `study_deck_images` (a picture is found again by
 * its exact bytes, and a source address that was already downloaded is not downloaded twice) and records which stored
 * deck uses which picture in `study_deck_image_links` (written when a deck is stored, once per picture and deck). A
 * picture has no concept, slide or licence column: the slide, concept and provenance of a picture live in the deck that
 * uses it. So this class never "matches by chapter": it reports what a plan or a draft really says about each picture,
 * and checks that every picture a deck names can really be read by the school.
 *
 * Read only. It stores nothing, links nothing and fetches nothing.
 */
class ImageReadiness
{
    private StudyDeckImages $images;

    public function __construct(?StudyDeckImages $images = null)
    {
        $this->images = $images ?? new StudyDeckImages();
    }

    /**
     * The chapter's picture library, and what an earlier unstored draft of the chapter says about its pictures.
     *
     * @param array<string,mixed> $chapter chapter_master row
     * @param array<string,mixed> $raw SourceLoader::load()
     * @return array<string,mixed>
     */
    public function forChapter(array $chapter, array $raw): array
    {
        $chapterId = (int) $chapter['id'];
        $tenant = (int) $chapter['sub_institute_id'];

        $rows = DB::table('study_deck_images')->where('sub_institute_id', $tenant)->where('chapter_id', $chapterId)->get(['id', 'source_url']);
        $ids = $rows->pluck('id')->map(fn ($v) => (int) $v)->all();
        $linked = [];
        foreach (array_chunk($ids, 500) as $part) {
            foreach (DB::table('study_deck_image_links')->whereIn('image_id', $part)->distinct()->pluck('image_id') as $id) {
                $linked[(int) $id] = true;
            }
        }
        $photos = $rows->filter(fn ($r) => $r->source_url !== null && $r->source_url !== '')->count();

        return [
            'library' => [
                'stored' => count($ids),
                'photographs' => $photos,
                'drawn_diagrams' => count($ids) - $photos,
                'used_by_a_stored_deck' => count($linked),
                'not_used_by_any_stored_deck' => count($ids) - count($linked),
            ],
            'earlier_draft' => $this->earlierDraft($chapter, $raw),
            'reuse_rule' => 'A picture is reused only when it is exactly the same picture: the library keeps one copy per school of identical bytes, and an address that was already downloaded is not downloaded again. A picture is never reused for a concept just because it is in the same chapter.',
            'requirements_note' => 'Which slides need a picture is decided by the slide plan, which does not exist until a draft is made (a picture only where it teaches). The draft\'s own report lists what was planned, found and not found.',
        ];
    }

    /**
     * What the earlier, unstored draft's picture records say, with each picture checked against the store.
     *
     * @return array<string,mixed>
     */
    private function earlierDraft(array $chapter, array $raw): array
    {
        $dir = storage_path((string) config('course_presentation_pilot.earlier_bundle_base', 'app/study-deck') . '/chapter-' . (int) $chapter['id']);
        $images = $this->json($dir . '/images.json');
        if ($images === null) {
            return ['found' => false];
        }
        $plan = $this->json($dir . '/plan.json') ?? [];
        $names = [];
        foreach ((array) ($raw['concepts'] ?? []) as $c) {
            $names[(int) $c['id']] = (string) $c['name'];
        }
        $taught = [];
        foreach ((array) ($plan['slides'] ?? []) as $s) {
            $taught[(int) ($s['n'] ?? 0)] = array_values(array_filter(array_map(fn ($id) => $names[(int) $id] ?? null, (array) ($s['taught_concept_ids'] ?? []))));
        }

        $refs = [];
        foreach ($images as $n => $img) {
            if (($id = StudyDeckImages::idFromRef($img['url'] ?? null)) !== null) {
                $refs[(int) $n] = $id;
            }
        }
        $metas = $this->images->visibleMetas(array_values($refs), (int) $chapter['sub_institute_id']);

        $diagrams = $photos = $resolved = 0;
        $unmet = [];
        foreach ($images as $n => $img) {
            ($img['type'] ?? '') === 'diagram' ? $diagrams++ : $photos++;
            if (isset($refs[(int) $n]) && isset($metas[$refs[(int) $n]])) {
                $resolved++;
            }
            if (!empty($img['photo_missing'])) {
                $unmet[] = ['slide' => (int) $n, 'concepts' => $taught[(int) $n] ?? [], 'reason' => GenerationRun::clean((string) $img['photo_missing'])];
            }
        }

        return [
            'found' => true,
            'slides_with_a_picture' => count($images),
            'drawn_diagrams' => $diagrams,
            'photographs' => $photos,
            'found_in_the_library' => $resolved,
            'not_found_in_the_library' => count($images) - $resolved,
            'photographs_not_found' => $unmet,
            'status' => 'These pictures belong to a draft that is not stored, so no stored deck uses them. They are not reused as they stand: a new draft plans its own pictures.',
        ];
    }

    /**
     * Check every picture a draft's deck names, before the draft may be called ready.
     *
     * @return array{referenced:int,resolved:int,unreadable:int,diagrams:int,photographs:int,photographs_not_found:array<int,array{slide:int,reason:string}>,ids:array<int,int>}
     */
    public function deckImages(string $bundleDir, int $tenant): array
    {
        $deck = $this->json($bundleDir . '/deck.json') ?? [];
        $ids = [];
        $kinds = ['diagram' => 0, 'photo' => 0];
        foreach (['slides', 'sections'] as $part) {
            foreach ((array) ($deck[$part] ?? []) as $item) {
                if (($id = StudyDeckImages::idFromRef($item['image']['url'] ?? null)) !== null) {
                    $ids[$id] = $id;
                    ($item['image']['type'] ?? '') === 'diagram' ? $kinds['diagram']++ : $kinds['photo']++;
                }
                // A story scene's own picture.
                foreach ((array) ($item['interaction']['nodes'] ?? []) as $node) {
                    foreach (array_merge([$node['image'] ?? null], (array) ($node['extra_images'] ?? [])) as $img) {
                        if (($id = StudyDeckImages::idFromRef($img['url'] ?? null)) !== null) {
                            $ids[$id] = $id;
                            ($img['type'] ?? '') === 'diagram' ? $kinds['diagram']++ : $kinds['photo']++;
                        }
                    }
                }
            }
        }
        foreach ((array) ($deck['concept_images'] ?? []) as $image) {
            if (($id = StudyDeckImages::idFromRef($image['url'] ?? null)) !== null) {
                $ids[$id] = $id;
            }
        }
        $metas = $this->images->visibleMetas(array_values($ids), $tenant);
        // Readable means: the school can see the row, and it has a real size (the viewer keeps the aspect ratio from it).
        $readable = array_filter($metas, fn ($m) => (int) $m['width'] > 0 && (int) $m['height'] > 0 && (int) $m['bytes'] > 0);

        $unmet = [];
        foreach ((array) ($this->json($bundleDir . '/images.json') ?? []) as $n => $img) {
            if (!empty($img['photo_missing'])) {
                $unmet[] = ['slide' => (int) $n, 'reason' => GenerationRun::clean((string) $img['photo_missing'])];
            }
        }

        return [
            'referenced' => count($ids),
            'resolved' => count($readable),
            'unreadable' => count($ids) - count($readable),
            'diagrams' => $kinds['diagram'],
            'photographs' => $kinds['photo'],
            'photographs_not_found' => $unmet,
            'ids' => array_values($ids),
        ];
    }

    /** @return array<string,mixed>|null */
    private function json(string $path): ?array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : null;
    }
}
