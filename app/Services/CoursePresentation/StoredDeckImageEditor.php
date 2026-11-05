<?php

namespace App\Services\CoursePresentation;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckImageUrls;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces ONE slide's picture in a presentation that is already approved and stored. Nothing is regenerated.
 *
 * A stored presentation is a row in content_master plus the JSON beside it that the player reads (the sidecar). The pictures are rows of
 * study_deck_images that the sidecar names by reference. An edit therefore changes exactly three things, and only for the picture being changed:
 *   1. a new row in study_deck_images (or the identical picture already there), and a link from this presentation to it;
 *   2. that slide's `image` in the sidecar, written in ONE write that is the commit point (a failure before it changes nothing visible);
 *   3. `image_edits` in the sidecar: the picture the slide began with, and what was done to it since.
 * The original picture's row and link are never deleted, so "restore the original" is always possible and a teacher's replacement stays
 * until they say otherwise. The first time a presentation is edited its untouched sidecar is also copied beside it.
 *
 * The same path serves the picture of one ITEM of a slide's interaction (`slides[n].interaction.items[id].image`): a slide whose items are opened
 * one at a time beside its picture (a "reveal" of two tools, say) shows the open item's own picture there. An item is named by `item`; its
 * original and history are kept under `image_edits["<slide>/<item>"]`, so every item restores on its own and no item's picture is ever shared
 * with another's.
 *
 * What it does not do: re-draw the downloadable PPTX or PDF files stored when the presentation was approved. The player, which reads the
 * sidecar, and the practice copy, which is drawn from it on request, show the new picture; the files made at approval time still hold the old one.
 *
 * Pilot only (chapter 8592, school 1). The search, the candidate list, the licence and credit checks and the upload checks are the draft
 * editor's (DraftImageEditor), reused, so a picture faces the same rules whether it is chosen for a draft or for a stored presentation.
 */
class StoredDeckImageEditor
{
    private const KEY = 'stored';

    /** The interactions whose items the player opens one at a time beside the slide's picture, where an open item's own picture is drawn. */
    public const ITEM_KINDS = ['reveal', 'steps', 'timeline', 'compare'];

    public function __construct(private readonly DraftImageEditor $pictures, private readonly ?StudyDeckImages $images = null)
    {
    }

    private function images(): StudyDeckImages
    {
        return $this->images ?? new StudyDeckImages();
    }

    // ------------------------------------------------------------------------------------------------ reading

    /**
     * @return array{row:object,deck:array<string,mixed>,path:string}
     * @throws DraftImageException
     */
    private function load(int $chapterId, int $tenant, int $contentId): array
    {
        if (!app(PedagogicalWorkflow::class)->appliesToIds($chapterId, $tenant)) {
            throw new DraftImageException('Changing a picture in a stored presentation is available for the pilot chapter only.', 403);
        }
        $row = DB::table('content_master')
            ->where('id', $contentId)->where('chapter_id', $chapterId)->where('sub_institute_id', $tenant)
            ->where('content_category', 'Classroom Presentation')->where('file_type', 'pptx')->where('filename', 'like', 'study\_deck\_%')
            ->first(['id', 'filename']);
        if (!$row) {
            throw new DraftImageException('That presentation was not found.', 404);
        }
        $path = ContentGenerationService::studyDeckSidecarPath((string) $row->filename);
        try {
            $deck = json_decode((string) Storage::disk('digitalocean')->get($path), true);
        } catch (\Throwable) {
            $deck = null;
        }
        if (!is_array($deck) || ($deck['version'] ?? 0) < 3 || !is_array($deck['slides'] ?? null)) {
            throw new DraftImageException('The stored presentation could not be read.', 502);
        }

        return ['row' => $row, 'deck' => $deck, 'path' => $path];
    }

    /** @return array{0:int,1:array<string,mixed>} the slide's position in the deck, and the slide */
    private function slideAt(array $deck, int $n): array
    {
        foreach ($deck['slides'] as $i => $slide) {
            if ((int) ($slide['n'] ?? 0) === $n) {
                return [(int) $i, $slide];
            }
        }
        throw new DraftImageException("The presentation has no slide $n.", 404);
    }

    /**
     * What may be done with a slide's picture. A stored presentation's picture can be changed or put back; it is never taken away, so a slide
     * is not left bare by a click.
     *
     * @param array<string,mixed> $deck
     * @param array<string,mixed> $slide
     * @return array<string,mixed>
     */
    private function state(array $deck, array $slide): array
    {
        $rules = DraftImageEditor::rules($slide);
        $image = is_array($slide['image'] ?? null) && ($slide['image'] ?? []) !== [] ? $slide['image'] : null;
        $entry = $deck['image_edits'][(string) ($slide['n'] ?? '')] ?? null;
        $restorable = is_array($entry) && array_key_exists('original', $entry) && ($entry['original']['url'] ?? null) !== ($image['url'] ?? null);

        return [
            'has_image' => $rules['has_image'],
            'can_add' => $rules['can_add'],
            'can_replace' => $rules['can_replace'],
            'can_remove' => false,
            'can_restore' => $restorable,
            'reason' => $rules['reason'],
            // What is on the slide now, so the window can say so plainly: a real photograph, a drawn diagram, or nothing.
            'kind' => $image === null ? 'none' : (($image['type'] ?? 'photo') === 'diagram' ? 'diagram' : 'photo'),
        ];
    }

    /**
     * True when the player draws this slide's items beside its picture, each with the picture of its own when it has one: a slide with a picture
     * and a list of items opened one at a time. Anything else (hotspots, a story, a slide without a picture) keeps one picture for the slide.
     *
     * @param array<string,mixed> $slide
     */
    private static function hasItemPictures(array $slide): bool
    {
        $interaction = $slide['interaction'] ?? null;

        return is_array($interaction)
            && in_array($interaction['kind'] ?? null, self::ITEM_KINDS, true)
            && is_array($interaction['items'] ?? null) && $interaction['items'] !== []
            && is_array($slide['image'] ?? null) && (string) ($slide['image']['url'] ?? '') !== '';
    }

    /**
     * @param array<string,mixed> $slide
     * @return array{0:int,1:array<string,mixed>} the item's position in the interaction, and the item
     * @throws DraftImageException
     */
    private function itemAt(array $slide, string $item): array
    {
        $n = (int) ($slide['n'] ?? 0);
        if (!self::hasItemPictures($slide)) {
            throw new DraftImageException("The items of slide $n do not carry pictures of their own.", 422);
        }
        foreach ($slide['interaction']['items'] as $j => $record) {
            if ((string) ($record['id'] ?? '') === $item) {
                return [(int) $j, $record];
            }
        }
        throw new DraftImageException("Slide $n has no item \"$item\".", 404);
    }

    /**
     * What may be done with one item's picture. The same rules and the same answer as a slide's, kept under the item's own name in the edit log.
     *
     * @param array<string,mixed> $deck
     * @param array<string,mixed> $slide
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function itemState(array $deck, array $slide, array $record): array
    {
        $rules = DraftImageEditor::rules($slide, $record);
        $image = is_array($record['image'] ?? null) && ($record['image'] ?? []) !== [] ? $record['image'] : null;
        $entry = $deck['image_edits'][DraftImageEditor::key((int) ($slide['n'] ?? 0), (string) ($record['id'] ?? ''))] ?? null;
        $restorable = is_array($entry) && array_key_exists('original', $entry) && ($entry['original']['url'] ?? null) !== ($image['url'] ?? null);

        return [
            'has_image' => $rules['has_image'],
            'can_add' => $rules['can_add'],
            'can_replace' => $rules['can_replace'],
            'can_remove' => false,
            'can_restore' => $restorable,
            'reason' => $rules['reason'],
            'kind' => $image === null ? 'none' : (($image['type'] ?? 'photo') === 'diagram' ? 'diagram' : 'photo'),
            'title' => (string) ($record['label'] ?? $record['id'] ?? ''),
        ];
    }

    /**
     * Per slide: what may be done with its picture and whether the original can be put back; for a slide whose items carry pictures, the same
     * for each item under `items`.
     *
     * @return array<int,array<string,mixed>> slide number => state
     * @throws DraftImageException
     */
    public function states(int $chapterId, int $tenant, int $contentId): array
    {
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        $out = [];
        foreach ($deck['slides'] as $slide) {
            $state = $this->state($deck, $slide);
            if (self::hasItemPictures($slide)) {
                $items = [];
                foreach ($slide['interaction']['items'] as $record) {
                    $id = (string) ($record['id'] ?? '');
                    if ($id !== '') {
                        $items[$id] = $this->itemState($deck, $slide, $record);
                    }
                }
                $state['items'] = $items;
            }
            $out[(int) ($slide['n'] ?? 0)] = $state;
        }

        return $out;
    }

    /** What to search for an item by default: its own name, without the article ("The compass" -> "compass"). */
    private static function itemWords(array $record): string
    {
        $label = trim((string) preg_replace('/\s+/', ' ', (string) ($record['label'] ?? '')));
        $words = trim((string) preg_replace('/^(the|a|an)\s+/i', '', $label));

        return mb_substr($words !== '' ? $words : $label, 0, 200);
    }

    // ------------------------------------------------------------------------------------------------ the operations

    /**
     * The state of the picture an operation names: the item's when `$item` is given, else the slide's. Refuses what cannot be changed.
     *
     * @param array<string,mixed> $deck
     * @param array<string,mixed> $slide
     * @return array<string,mixed>
     * @throws DraftImageException
     */
    private function changeable(array $deck, array $slide, ?string $item): array
    {
        $state = $item === null ? $this->state($deck, $slide) : $this->itemState($deck, $slide, $this->itemAt($slide, $item)[1]);
        if (!$state['can_add'] && !$state['can_replace']) {
            throw new DraftImageException($state['reason'] ?? 'This picture cannot be changed.', 422);
        }

        return $state;
    }

    /**
     * Candidate photographs for one slide (or one of its items), searched with its own words or the teacher's. Nothing is written.
     *
     * @return array{query:string,candidates:array<int,array<string,mixed>>,note:?string}
     * @throws DraftImageException
     */
    public function search(int $chapterId, int $tenant, int $contentId, int $n, ?string $query = null, ?string $item = null): array
    {
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        [, $slide] = $this->slideAt($deck, $n);
        $this->changeable($deck, $slide, $item);
        if ($item === null) {
            return $this->pictures->candidates($tenant, $chapterId, self::KEY . $contentId, (string) $n, $deck, $slide, null, $query);
        }

        // An item is searched for by its own name, never by the slide's concept: "the compass" must not be offered the slide's diagram topic.
        [, $record] = $this->itemAt($slide, $item);
        $words = self::itemWords($record);
        $found = $this->pictures->candidates($tenant, $chapterId, self::KEY . $contentId, DraftImageEditor::key($n, $item), $deck, $slide, ['title' => $words, 'concept_ids' => []], trim((string) $query) !== '' ? $query : $words);
        if (array_key_exists('suggestions', $found)) {
            $found['suggestions'] = [];
            // An item's name alone can be abstract ("Sudden braking"); what the slide's own picture was planned to show ("bus passenger jerk") is the
            // concrete thing a photograph can be of, so it is one click away.
            foreach ([['Item name', $words], ['Slide picture', (string) ($slide['visual']['query'] ?? '')], ['Slide topic', (string) ($slide['title'] ?? '')]] as [$label, $q]) {
                $q = trim((string) preg_replace('/\s+/', ' ', $q));
                if ($q !== '' && !in_array(mb_strtolower($q), array_map(fn ($s) => mb_strtolower($s['query']), $found['suggestions']), true)) {
                    $found['suggestions'][] = ['label' => $label, 'query' => mb_substr($q, 0, 200)];
                }
            }
        }

        return $found;
    }

    /** @throws DraftImageException */
    public function replaceFromSearch(int $chapterId, int $tenant, int $contentId, int $n, string $candidateId, int $userId, ?string $item = null): array
    {
        // The slide, the item and the candidate are checked before any work, so a refusal about any of them is the answer.
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        [, $slide] = $this->slideAt($deck, $n);
        $this->changeable($deck, $slide, $item);
        $make = $this->pictures->pick($tenant, $chapterId, self::KEY . $contentId, DraftImageEditor::key($n, $item), $candidateId);

        return $this->apply($chapterId, $tenant, $contentId, $n, $userId, 'search', $make, $item);
    }

    /** @throws DraftImageException */
    public function replaceFromUpload(int $chapterId, int $tenant, int $contentId, int $n, string $bytes, string $fileName, ?string $credit, ?string $alt, int $userId, ?string $item = null): array
    {
        return $this->apply($chapterId, $tenant, $contentId, $n, $userId, 'upload', $this->pictures->upload($tenant, $chapterId, $bytes, $fileName, $credit, $alt), $item);
    }

    /**
     * Put the original back: the first picture this slide (or item) had, or none when it began without one.
     *
     * @throws DraftImageException
     */
    public function restore(int $chapterId, int $tenant, int $contentId, int $n, int $userId, ?string $item = null): array
    {
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        $entry = $deck['image_edits'][DraftImageEditor::key($n, $item)] ?? null;
        if (!is_array($entry) || !array_key_exists('original', $entry)) {
            throw new DraftImageException('This picture has not been changed.', 422);
        }
        $original = is_array($entry['original']) ? $entry['original'] : null;

        return $this->apply($chapterId, $tenant, $contentId, $n, $userId, 'restore', fn (?array $old): ?array => $original, $item);
    }

    // ------------------------------------------------------------------------------------------------ the one write path

    /**
     * @param Closure(?array<string,mixed> $old):?array<string,mixed> $make builds the new picture record (may throw DraftImageException)
     * @return array<string,mixed>
     * @throws DraftImageException
     */
    private function apply(int $chapterId, int $tenant, int $contentId, int $n, int $userId, string $kind, Closure $make, ?string $item = null): array
    {
        // One edit at a time per presentation: the second waits a moment, then is told so instead of overwriting the first.
        $lock = Cache::lock('stored-image-edit:' . $contentId, 30);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new DraftImageException('Another change to this presentation is in progress. Try again in a moment.', 409);
        }

        try {
            ['deck' => $deck, 'path' => $path] = $this->load($chapterId, $tenant, $contentId);   // read AFTER the lock
            [$i, $slide] = $this->slideAt($deck, $n);
            // `$holder` is the record whose `image` is being changed: the slide, or one of its items.
            $j = null;
            if ($item === null) {
                $holder = $slide;
                $state = $this->state($deck, $slide);
            } else {
                [$j, $holder] = $this->itemAt($slide, $item);
                $state = $this->itemState($deck, $slide, $holder);
            }
            if ($kind !== 'restore' && !$state['can_add'] && !$state['can_replace']) {
                throw new DraftImageException($state['reason'] ?? 'This picture cannot be changed.', 422);
            }
            $old = is_array($holder['image'] ?? null) && $holder['image'] !== [] ? $holder['image'] : null;

            $new = $make($old);
            if ($new !== null) {
                // The picture must be one this school can read back before the presentation points at it.
                $id = StudyDeckImages::idFromRef($new['url'] ?? null);
                $meta = $id === null ? null : $this->images()->visibleMeta($id, $tenant);
                if ($meta === null) {
                    throw new DraftImageException('The new picture could not be read back from the picture store, so nothing was changed.', 500);
                }
                if ($item !== null && $kind !== 'restore') {
                    // Each item keeps a picture of its own: the same picture is never given to two items of one slide.
                    foreach ($slide['interaction']['items'] as $k => $sibling) {
                        if ($k !== $j && StudyDeckImages::idFromRef($sibling['image']['url'] ?? null) === $id) {
                            throw new DraftImageException('That picture is already used for "' . ($sibling['label'] ?? $sibling['id'] ?? 'another item') . '". Each item keeps a picture of its own, so choose a different one.', 422);
                        }
                    }
                }
                if ($kind !== 'restore') {
                    // The picture's role in the lesson stays; what describes the picture itself (source, licence, credit, kind) is the new picture's.
                    $new['source_kind'] ??= 'web';
                    $new = $new + array_intersect_key($old ?? [], array_flip(['purpose', 'role']));
                }
                if ($kind !== 'restore') {
                    $new['asset_id'] = $meta['sha256'];   // a restored original is returned exactly as it was recorded
                }
                $deck['assets'][$meta['sha256']] = $deck['assets'][$meta['sha256']] ?? [
                    'image_id' => $meta['id'], 'ref' => StudyDeckImages::ref($meta['id']), 'sha256' => $meta['sha256'], 'mime' => $meta['mime'],
                    'format' => $meta['format'], 'bytes' => $meta['bytes'], 'width' => $meta['width'], 'height' => $meta['height'],
                ];
            }

            $key = DraftImageEditor::key($n, $item);
            $entry = $deck['image_edits'][$key] ?? ['original' => $old, 'original_missing' => $item === null ? ($slide['image_missing'] ?? null) : null, 'history' => []];
            $entry['history'][] = ['at' => date('c'), 'by' => $userId, 'kind' => $kind === 'restore' ? 'restore' : ($old === null ? 'add' : 'replace'), 'from' => $old['url'] ?? null, 'to' => $new['url'] ?? null];
            $entry['current'] = $new['url'] ?? null;
            $deck['image_edits'][$key] = $entry;

            if ($item === null) {
                $deck['slides'][$i]['image'] = $new;
                // "No suitable picture" was the generator's note; it stops being true once a teacher has chosen one, and comes back with the original.
                if (array_key_exists('image_missing', $deck['slides'][$i])) {
                    $deck['slides'][$i]['image_missing'] = $new !== null ? null : ($kind === 'restore' ? ($entry['original_missing'] ?? null) : ($slide['image_missing'] ?? null));
                }
            } elseif ($new === null) {
                // An item that began without a picture goes back to having none (it then shows the slide's picture again).
                unset($deck['slides'][$i]['interaction']['items'][$j]['image']);
            } else {
                $deck['slides'][$i]['interaction']['items'][$j]['image'] = $new;
            }

            $this->commit($path, $deck, $contentId, $chapterId, $new === null ? null : (int) StudyDeckImages::idFromRef($new['url']));
        } finally {
            $lock->release();
        }

        $after = $item === null
            ? $this->state($deck, $deck['slides'][$i])
            : $this->itemState($deck, $deck['slides'][$i], $deck['slides'][$i]['interaction']['items'][$j]);

        return [
            'slide' => $n,
            'node' => null,
            'item' => $item,
            'action' => $kind === 'restore' ? 'restore' : ($old === null ? 'add' : 'replace'),
            'image' => $new === null ? null : (['url' => (new StudyDeckImageUrls($this->images()))->signed((int) StudyDeckImages::idFromRef($new['url']), $tenant)] + $new),
            'edited' => $after['can_restore'],
            'can_restore' => $after['can_restore'],
            'state' => $after,
        ];
    }

    /**
     * Link the new picture, keep the untouched original sidecar once, then write the sidecar: the single commit point.
     *
     * @param array<string,mixed> $deck
     * @throws DraftImageException
     */
    private function commit(string $path, array $deck, int $contentId, int $chapterId, ?int $imageId): void
    {
        $disk = Storage::disk('digitalocean');
        try {
            if ($imageId !== null) {
                $this->images()->link($contentId, [$imageId], $chapterId);
            }
            $backup = preg_replace('/\.json$/', '.before-picture-edits.json', $path);
            if ($backup !== null && $backup !== $path && !$disk->exists($backup)) {
                $disk->put($backup, (string) $disk->get($path));
            }
            if (!$disk->put($path, (string) json_encode($deck, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'public')) {
                throw new \RuntimeException('write refused');
            }
        } catch (\Throwable) {
            throw new DraftImageException('The change could not be saved, so nothing was changed. Your original picture is still in place.', 500);
        }
    }
}
