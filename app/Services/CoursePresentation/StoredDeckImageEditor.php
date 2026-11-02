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
 * What it does not do: re-draw the downloadable PPTX or PDF files stored when the presentation was approved. The player, which reads the
 * sidecar, and the practice copy, which is drawn from it on request, show the new picture; the files made at approval time still hold the old one.
 *
 * Pilot only (chapter 8592, school 1). The search, the candidate list, the licence and credit checks and the upload checks are the draft
 * editor's (DraftImageEditor), reused, so a picture faces the same rules whether it is chosen for a draft or for a stored presentation.
 */
class StoredDeckImageEditor
{
    private const KEY = 'stored';

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
     * Per slide: what may be done with its picture and whether the original can be put back.
     *
     * @return array<int,array<string,mixed>> slide number => state
     * @throws DraftImageException
     */
    public function states(int $chapterId, int $tenant, int $contentId): array
    {
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        $out = [];
        foreach ($deck['slides'] as $slide) {
            $out[(int) ($slide['n'] ?? 0)] = $this->state($deck, $slide);
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------ the operations

    /**
     * Candidate photographs for one slide, searched with its own words or the teacher's. Nothing is written.
     *
     * @return array{query:string,candidates:array<int,array<string,mixed>>,note:?string}
     * @throws DraftImageException
     */
    public function search(int $chapterId, int $tenant, int $contentId, int $n, ?string $query = null): array
    {
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        [, $slide] = $this->slideAt($deck, $n);
        $state = $this->state($deck, $slide);
        if (!$state['can_add'] && !$state['can_replace']) {
            throw new DraftImageException($state['reason'] ?? 'This picture cannot be changed.', 422);
        }

        return $this->pictures->candidates($tenant, $chapterId, self::KEY . $contentId, (string) $n, $deck, $slide, null, $query);
    }

    /** @throws DraftImageException */
    public function replaceFromSearch(int $chapterId, int $tenant, int $contentId, int $n, string $candidateId, int $userId): array
    {
        // The slide and the candidate are checked before any work, so a refusal about either is the answer.
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        [, $slide] = $this->slideAt($deck, $n);
        $state = $this->state($deck, $slide);
        if (!$state['can_add'] && !$state['can_replace']) {
            throw new DraftImageException($state['reason'] ?? 'This picture cannot be changed.', 422);
        }
        $make = $this->pictures->pick($tenant, $chapterId, self::KEY . $contentId, (string) $n, $candidateId);

        return $this->apply($chapterId, $tenant, $contentId, $n, $userId, 'search', $make);
    }

    /** @throws DraftImageException */
    public function replaceFromUpload(int $chapterId, int $tenant, int $contentId, int $n, string $bytes, string $fileName, ?string $credit, ?string $alt, int $userId): array
    {
        return $this->apply($chapterId, $tenant, $contentId, $n, $userId, 'upload', $this->pictures->upload($tenant, $chapterId, $bytes, $fileName, $credit, $alt));
    }

    /**
     * Put the original back: the first picture this slide had, or none when it began without one.
     *
     * @throws DraftImageException
     */
    public function restore(int $chapterId, int $tenant, int $contentId, int $n, int $userId): array
    {
        ['deck' => $deck] = $this->load($chapterId, $tenant, $contentId);
        $entry = $deck['image_edits'][(string) $n] ?? null;
        if (!is_array($entry) || !array_key_exists('original', $entry)) {
            throw new DraftImageException('This picture has not been changed.', 422);
        }
        $original = is_array($entry['original']) ? $entry['original'] : null;

        return $this->apply($chapterId, $tenant, $contentId, $n, $userId, 'restore', fn (?array $old): ?array => $original);
    }

    // ------------------------------------------------------------------------------------------------ the one write path

    /**
     * @param Closure(?array<string,mixed> $old):?array<string,mixed> $make builds the slide's new picture record (may throw DraftImageException)
     * @return array<string,mixed>
     * @throws DraftImageException
     */
    private function apply(int $chapterId, int $tenant, int $contentId, int $n, int $userId, string $kind, Closure $make): array
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
            $state = $this->state($deck, $slide);
            if ($kind !== 'restore' && !$state['can_add'] && !$state['can_replace']) {
                throw new DraftImageException($state['reason'] ?? 'This picture cannot be changed.', 422);
            }
            $old = is_array($slide['image'] ?? null) && $slide['image'] !== [] ? $slide['image'] : null;

            $new = $make($old);
            if ($new !== null) {
                // The picture must be one this school can read back before the presentation points at it.
                $id = StudyDeckImages::idFromRef($new['url'] ?? null);
                $meta = $id === null ? null : $this->images()->visibleMeta($id, $tenant);
                if ($meta === null) {
                    throw new DraftImageException('The new picture could not be read back from the picture store, so nothing was changed.', 500);
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

            $key = (string) $n;
            $entry = $deck['image_edits'][$key] ?? ['original' => $old, 'original_missing' => $slide['image_missing'] ?? null, 'history' => []];
            $entry['history'][] = ['at' => date('c'), 'by' => $userId, 'kind' => $kind === 'restore' ? 'restore' : ($old === null ? 'add' : 'replace'), 'from' => $old['url'] ?? null, 'to' => $new['url'] ?? null];
            $entry['current'] = $new['url'] ?? null;
            $deck['image_edits'][$key] = $entry;

            $deck['slides'][$i]['image'] = $new;
            // "No suitable picture" was the generator's note; it stops being true once a teacher has chosen one, and comes back with the original.
            if (array_key_exists('image_missing', $deck['slides'][$i])) {
                $deck['slides'][$i]['image_missing'] = $new !== null ? null : ($kind === 'restore' ? ($entry['original_missing'] ?? null) : ($slide['image_missing'] ?? null));
            }

            $this->commit($path, $deck, $contentId, $chapterId, $new === null ? null : (int) StudyDeckImages::idFromRef($new['url']));
        } finally {
            $lock->release();
        }

        $after = $this->state($deck, $deck['slides'][$i]);

        return [
            'slide' => $n,
            'node' => null,
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
