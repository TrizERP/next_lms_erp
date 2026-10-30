<?php

namespace App\Services\CoursePresentation;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckImageUrls;
use App\Services\StudyDeck\StudyImageStores;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Adds, replaces, removes and restores ONE picture of a generated draft that is waiting for review. Nothing is regenerated.
 *
 * A draft is a bundle in its run folder and approval stores exactly that bundle. Two kinds of picture are editable, each in the place the
 * existing renderer and player already keep it:
 *   slide  `slides[n].image`                          a study deck or Classroom Presentation slide (also a cover)
 *   scene  `slides[n].interaction.nodes[id].image`    a scene of a story / branching-scenario lesson
 * An edit changes only that picture. For a slide it also changes its figure and its credit line in out/presentation.html (the rendered
 * presentation the store step reads); a story scene's picture is drawn by the player alone, so only deck.json changes. images.json (the
 * generator's list of slide pictures) follows. Text, interactions, decisions and choices are never touched.
 *
 * Unsafe edits are refused, never silently made: a picture that carries hotspot pins (or a slide whose hotspots sit on it) cannot be swapped
 * or taken away, and a story slide's pictures are changed scene by scene. A highlighted region (`focus`) belongs to the old picture, so a
 * new picture has none; restoring brings it back with the original.
 *
 * The first state of every picture (including "had none") is kept in image-edits.json so it can be restored. All files are written together
 * or not at all (temporary names, then rename, earlier contents put back on a failure), one edit at a time per run. An approved (stored)
 * presentation is not edited here: it is immutable, and a changed picture needs a new draft.
 */
class DraftImageEditor
{
    public const MIN_SIDE = 200;
    public const CANDIDATES = 8;
    private const CACHE_MINUTES = 60;
    private const LOCK_TRIES = 40;
    private const LOCK_WAIT_MICROSECONDS = 50_000;

    /** @param Closure(int $tenant,int $chapterId):ImagePlanner|null $planner builds the planner that checks and downloads a chosen picture (tests replace it) */
    public function __construct(
        private readonly GenerationRun $runs,
        private readonly ?ConceptImageSearchService $search = null,
        private readonly ?StudyDeckImages $images = null,
        private readonly ?Closure $planner = null,
    ) {
    }

    private function images(): StudyDeckImages
    {
        return $this->images ?? new StudyDeckImages();
    }

    private function dir(int $chapterId, string $run): string
    {
        return storage_path((string) config('course_presentation_pilot.generation.run_dir', 'app/course-presentation') . '/chapter-' . $chapterId . '/' . $run);
    }

    /** The name of one picture in the edit log and the candidate cache: the slide, or `slide/scene`. */
    public static function key(int $n, ?string $node): string
    {
        return $node === null ? (string) $n : $n . '/' . $node;
    }

    // ------------------------------------------------------------------------------------------------ what may be done

    /** A story slide keeps its pictures on its scenes. */
    private static function isStorySlide(array $slide): bool
    {
        return ($slide['interaction']['kind'] ?? null) === 'scenario' && !empty($slide['interaction']['template']);
    }

    /**
     * What a teacher may do with one picture, and why not.
     *
     * @param array<string,mixed> $slide a deck slide
     * @param array<string,mixed>|null $nodeRecord the scene, for a scene's picture
     * @return array{has_image:bool,can_add:bool,can_replace:bool,can_remove:bool,reason:?string}
     */
    public static function rules(array $slide, ?array $nodeRecord = null): array
    {
        $image = $nodeRecord !== null ? ($nodeRecord['image'] ?? null) : ($slide['image'] ?? null);
        $has = is_array($image) && StudyDeckImages::idFromRef($image['url'] ?? null) !== null;
        $block = null;
        if ($nodeRecord === null && self::isStorySlide($slide)) {
            $block = 'The pictures of a story slide belong to its scenes. Change them one scene at a time.';
        } elseif ($nodeRecord === null && ($slide['interaction']['kind'] ?? null) === 'hotspots') {
            $block = 'This slide\'s hotspots are placed on its picture, so the picture cannot be added, swapped or removed without moving them.';
        } elseif ($has && !empty($image['parts'])) {
            $block = 'This picture carries numbered pins that readers open, so it cannot be swapped or removed without placing them again.';
        } elseif (!empty($image) && !$has) {
            $block = 'This picture is not in the picture store, so it cannot be edited here.';
        }

        return ['has_image' => $has, 'can_add' => $block === null && !$has, 'can_replace' => $block === null && $has, 'can_remove' => $block === null && $has, 'reason' => $block];
    }

    /**
     * What the review screen needs: for every slide that can carry a picture, what may be done and whether the original can be restored,
     * and the same for each scene of a story slide.
     *
     * @param array<string,mixed> $deck
     * @return array<string,array<string,mixed>> slide number => state (and `nodes` => scene id => state)
     */
    public function slideStates(int $chapterId, string $run, array $deck): array
    {
        $edits = $this->readJson($this->dir($chapterId, $run) . '/image-edits.json');
        $restorable = function (string $key, mixed $now) use ($edits): bool {
            $entry = is_array($edits) ? ($edits['slides'][$key] ?? null) : null;

            return is_array($entry) && ($entry['original']['url'] ?? null) !== (is_array($now) ? ($now['url'] ?? null) : null);
        };
        $out = [];
        foreach ((array) ($deck['slides'] ?? []) as $slide) {
            $n = (int) ($slide['n'] ?? 0);
            $state = self::rules($slide) + ['can_restore' => $restorable((string) $n, $slide['image'] ?? null)];
            if (self::isStorySlide($slide)) {
                $nodes = [];
                foreach ((array) ($slide['interaction']['nodes'] ?? []) as $node) {
                    $id = (string) ($node['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $nodes[$id] = self::rules($slide, $node) + ['can_restore' => $restorable(self::key($n, $id), $node['image'] ?? null), 'title' => (string) ($node['title'] ?? $id)];
                }
                $state['nodes'] = $nodes;
            }
            $out[$n] = $state;
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------ reading

    /**
     * @return array{status:array<string,mixed>,deck:array<string,mixed>,index:int,node:?int}
     * @throws DraftImageException
     */
    private function load(int $chapterId, int $tenant, string $run, int $n, ?string $node): array
    {
        $status = $this->runs->read($chapterId, $tenant, $run);
        if ($status === null) {
            throw new DraftImageException('That draft was not found.', 404);
        }
        if ($status['state'] === 'stored') {
            throw new DraftImageException('This presentation is already approved and stored, so its pictures cannot be changed here. Generate a new draft to use a different picture.', 409);
        }
        if ($status['state'] !== 'ready') {
            throw new DraftImageException('Only a draft that is waiting for review can be edited.', 409);
        }
        if (!empty($status['saving'])) {
            throw new DraftImageException('This draft is being saved. Wait for that to finish.', 409);
        }
        $deck = $this->readJson($this->dir($chapterId, $run) . '/bundle/deck.json');
        if (!is_array($deck)) {
            throw new DraftImageException('The draft\'s files could not be read.', 500);
        }
        foreach ((array) ($deck['slides'] ?? []) as $i => $slide) {
            if ((int) ($slide['n'] ?? 0) !== $n) {
                continue;
            }
            if ($node === null) {
                return ['status' => $status, 'deck' => $deck, 'index' => (int) $i, 'node' => null];
            }
            foreach ((array) ($slide['interaction']['nodes'] ?? []) as $j => $record) {
                if ((string) ($record['id'] ?? '') === $node) {
                    return ['status' => $status, 'deck' => $deck, 'index' => (int) $i, 'node' => (int) $j];
                }
            }
            throw new DraftImageException("Slide $n has no scene \"$node\".", 404);
        }
        throw new DraftImageException("The draft has no slide $n.", 404);
    }

    /** @return mixed */
    private function readJson(string $path): mixed
    {
        return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    }

    /** @return array{0:array<string,mixed>,1:?array<string,mixed>} the slide and the scene (or null) */
    private function parts(array $deck, int $i, ?int $j): array
    {
        $slide = $deck['slides'][$i];

        return [$slide, $j === null ? null : $slide['interaction']['nodes'][$j]];
    }

    // ------------------------------------------------------------------------------------------------ search

    /**
     * Candidate pictures, searched with the slide's (or scene's) own words, or the teacher's. They are kept on the server and named by an id:
     * a replacement is made from one of THESE, never from an address the browser supplies.
     *
     * @return array{query:string,candidates:array<int,array<string,mixed>>,note:?string}
     * @throws DraftImageException
     */
    public function search(int $chapterId, int $tenant, string $run, int $n, ?string $query = null, ?string $node = null): array
    {
        ['deck' => $deck, 'index' => $i, 'node' => $j] = $this->load($chapterId, $tenant, $run, $n, $node);
        [$slide, $scene] = $this->parts($deck, $i, $j);
        $rules = self::rules($slide, $scene);
        if (!$rules['can_add'] && !$rules['can_replace']) {
            throw new DraftImageException($rules['reason'] ?? 'This picture cannot be changed.', 422);
        }
        $service = $this->search ?? app(ConceptImageSearchService::class);
        $query = trim((string) $query) !== '' ? trim((string) $query) : $this->defaultQuery($deck, $slide, $scene, $service);
        if (!$service->available()) {
            return ['query' => $query, 'candidates' => [], 'note' => 'Picture search is not available right now. You can upload a picture instead.'];
        }

        try {
            $rows = $service->rankedImagesFor($query, self::CANDIDATES);
        } catch (\Throwable) {
            return ['query' => $query, 'candidates' => [], 'note' => 'The picture search did not answer. Try again in a moment, or upload a picture instead.'];
        }

        $keep = [];
        $out = [];
        $gate = $this->planner($tenant, $chapterId);
        foreach ($rows as $row) {
            $licence = trim((string) ($row['license'] ?? ''));
            if (empty($row['url']) || !$gate->licenceAccepted($licence)) {
                continue;   // only openly licensed pictures are offered
            }
            $id = substr(sha1((string) $row['url']), 0, 16);
            $keep[$id] = $row;
            $out[] = [
                'id' => $id,
                'thumbnail_url' => (string) ($row['thumbnail_url'] ?? $row['url']),
                'title' => (string) ($row['title'] ?? ''),
                'creator' => $row['creator'] ?? null,
                'licence' => $licence,
                'provider' => (string) ($row['provider'] ?? 'openverse'),
                'source_url' => (string) ($row['source_url'] ?? ''),
            ];
        }
        Cache::put($this->cacheKey($tenant, $run, self::key($n, $node)), ['query' => $query, 'rows' => $keep], now()->addMinutes(self::CACHE_MINUTES));

        return ['query' => $query, 'candidates' => $out, 'note' => $out === [] ? 'No openly licensed picture was found for this search. Change the words, or upload a picture.' : null];
    }

    private function cacheKey(int $tenant, string $run, string $key): string
    {
        return "cp-image-candidates:$tenant:$run:$key";
    }

    /** The title and the concepts it teaches, in the search service's own phrasing. */
    private function defaultQuery(array $deck, array $slide, ?array $scene, ConceptImageSearchService $service): string
    {
        $names = [];
        foreach ((array) ($scene['concept_ids'] ?? $slide['taught_concept_ids'] ?? $slide['concept_ids'] ?? []) as $id) {
            if (!empty($deck['concepts'][$id]['name'])) {
                $names[] = (string) $deck['concepts'][$id]['name'];
            }
        }
        $title = (string) ($scene['title'] ?? $slide['title'] ?? '');
        $concept = $names !== [] ? implode(' ', array_slice($names, 0, 2)) : $title;
        $chapter = (string) ($deck['chapter']['name'] ?? '');
        $query = $service->queryFor($concept, $chapter, $title !== $concept ? $title : null);

        return $query !== '' ? $query : $title;
    }

    // ------------------------------------------------------------------------------------------------ the operations

    /**
     * Add (when the slide has no picture) or replace (when it has one) with a picture the search offered.
     *
     * @throws DraftImageException
     */
    public function replaceFromSearch(int $chapterId, int $tenant, string $run, int $n, string $candidateId, int $userId, ?string $node = null): array
    {
        // The draft's state and the picture come first: a refusal about those is the more useful answer.
        ['deck' => $deck, 'index' => $i, 'node' => $j] = $this->load($chapterId, $tenant, $run, $n, $node);
        [$slide, $scene] = $this->parts($deck, $i, $j);
        $rules = self::rules($slide, $scene);
        if (!$rules['can_add'] && !$rules['can_replace']) {
            throw new DraftImageException($rules['reason'] ?? 'This picture cannot be changed.', 422);
        }
        $kept = Cache::get($this->cacheKey($tenant, $run, self::key($n, $node)));
        $row = is_array($kept) ? ($kept['rows'][$candidateId] ?? null) : null;
        if (!is_array($row)) {
            throw new DraftImageException('That picture is no longer on the list. Search again and choose one.', 422);
        }

        return $this->apply($chapterId, $tenant, $run, $n, $node, $userId, 'search', function (?array $old) use ($row, $tenant, $chapterId, $kept): array {
            // The teacher looked at the picture and chose it; the same checks a found picture faces still apply (licence, credit, a real
            // download, enough detail). The search words stand in for "what it shows", so the choice is not refused as off-topic.
            $pick = $row + ['license' => $row['license'] ?? '', 'image_url' => $row['url'], 'shows' => (string) ($kept['query'] ?? ''), 'accept_soft' => true];
            try {
                return $this->planner($tenant, $chapterId)->adopt($pick, (string) ($kept['query'] ?? ''), (string) ($old['purpose'] ?? ''));
            } catch (\InvalidArgumentException $e) {
                throw new DraftImageException('This picture cannot be used: ' . $e->getMessage() . '.', 422);
            } catch (\RuntimeException $e) {
                throw new DraftImageException('This picture could not be fetched: ' . $e->getMessage() . '. Choose another.', 422);
            }
        });
    }

    /**
     * Add or replace with a picture the teacher uploaded.
     *
     * @throws DraftImageException
     */
    public function replaceFromUpload(int $chapterId, int $tenant, string $run, int $n, string $bytes, string $fileName, ?string $credit, ?string $alt, int $userId, ?string $node = null): array
    {
        return $this->apply($chapterId, $tenant, $run, $n, $node, $userId, 'upload', function (?array $old) use ($bytes, $fileName, $credit, $alt, $tenant, $chapterId): array {
            $store = $this->images();
            try {
                $info = $store->inspect($bytes);
            } catch (\InvalidArgumentException $e) {
                throw new DraftImageException($e->getMessage(), 422);
            }
            if ($info['width'] < self::MIN_SIDE || $info['height'] < self::MIN_SIDE) {
                throw new DraftImageException(sprintf('The picture is %dx%d; it must be at least %d pixels on each side to be clear on a slide.', $info['width'], $info['height'], self::MIN_SIDE), 422);
            }
            try {
                $put = $store->put($bytes, $tenant, $chapterId);
            } catch (\Throwable) {
                throw new DraftImageException('The picture could not be stored. Your original picture is unchanged.', 500);
            }
            $credit = trim((string) $credit);
            $name = trim(preg_replace('/[^\p{L}\p{N} _.-]+/u', ' ', pathinfo($fileName, PATHINFO_FILENAME)) ?? '');
            $alt = trim((string) $alt) !== '' ? trim((string) $alt) : ('Picture added by the teacher' . ($name !== '' ? ': ' . $name : ''));

            return [
                'type' => 'photo',
                'alt' => mb_substr($alt, 0, 300),
                'caption' => (string) ($old['caption'] ?? ''),
                'url' => $put['ref'],
                'width' => $put['width'],
                'height' => $put['height'],
                'query' => (string) ($old['query'] ?? ''),
                'source_image_url' => null,
                'source_url' => null,
                'title' => $name !== '' ? $name : null,
                'creator' => $credit !== '' ? mb_substr($credit, 0, 200) : null,
                'provider' => 'teacher_upload',
                'licence' => 'Teacher upload',
                'attribution_required' => false,
                'attribution' => $credit !== '' ? mb_substr($credit, 0, 200) : '',
                'sha1' => sha1($bytes),
                'sha256' => $put['sha256'],
                'review' => ['relevant' => true, 'reason' => 'Added by the teacher, who confirmed the right to use it.'],
                'purpose' => (string) ($old['purpose'] ?? ''),
                'source_kind' => 'teacher_upload',
            ];
        });
    }

    /**
     * Take the picture off the slide or scene. Its text and everything else stay.
     *
     * @throws DraftImageException
     */
    public function remove(int $chapterId, int $tenant, string $run, int $n, int $userId, ?string $node = null): array
    {
        return $this->apply($chapterId, $tenant, $run, $n, $node, $userId, 'remove', fn (?array $old): ?array => null);
    }

    /**
     * Put the original back: the first picture, or no picture when the slide began without one.
     *
     * @throws DraftImageException
     */
    public function restore(int $chapterId, int $tenant, string $run, int $n, int $userId, ?string $node = null): array
    {
        $edits = $this->readJson($this->dir($chapterId, $run) . '/image-edits.json');
        $entry = is_array($edits) ? ($edits['slides'][self::key($n, $node)] ?? null) : null;
        if (!is_array($entry) || !array_key_exists('original', $entry)) {
            throw new DraftImageException('This picture has not been changed.', 422);
        }
        $original = is_array($entry['original']) ? $entry['original'] : null;

        return $this->apply($chapterId, $tenant, $run, $n, $node, $userId, 'restore', fn (?array $old): ?array => $original);
    }

    // ------------------------------------------------------------------------------------------------ the one write path

    /**
     * @param Closure(?array<string,mixed> $old):?array<string,mixed> $make builds the new image record, or null for no picture (may throw DraftImageException)
     * @return array<string,mixed>
     * @throws DraftImageException
     */
    private function apply(int $chapterId, int $tenant, string $run, int $n, ?string $node, int $userId, string $kind, Closure $make): array
    {
        $dir = $this->dir($chapterId, $run);
        if (!is_dir($dir)) {
            throw new DraftImageException('That draft was not found.', 404);
        }
        $lock = fopen($dir . '/.image-edit.lock', 'c');
        // One edit at a time: wait a moment for the one in progress, then say so instead of hanging a request.
        $locked = false;
        for ($tries = 0; $lock !== false && $tries < self::LOCK_TRIES && !($locked = flock($lock, LOCK_EX | LOCK_NB)); $tries++) {
            usleep(self::LOCK_WAIT_MICROSECONDS);
        }
        if ($lock === false || !$locked) {
            throw new DraftImageException('Another change to this draft is in progress. Try again in a moment.', 409);
        }
        try {
            ['status' => $status, 'deck' => $deck, 'index' => $i, 'node' => $j] = $this->load($chapterId, $tenant, $run, $n, $node);   // read AFTER the lock
            [$slide, $scene] = $this->parts($deck, $i, $j);
            $rules = self::rules($slide, $scene);
            $old = $scene !== null ? ($scene['image'] ?? null) : ($slide['image'] ?? null);
            $old = is_array($old) && $old !== [] ? $old : null;

            $allowed = match ($kind) {
                'search', 'upload' => $rules['can_add'] || $rules['can_replace'],
                'remove' => $rules['can_remove'],
                default => $rules['can_add'] || $rules['can_replace'] || $rules['can_remove'] || $rules['reason'] === null,
            };
            if (!$allowed) {
                throw new DraftImageException($rules['reason'] ?? ($kind === 'remove' ? 'This slide has no picture to remove.' : 'This picture cannot be changed.'), 422);
            }
            $did = in_array($kind, ['search', 'upload'], true) ? ($old === null ? 'add' : 'replace') : $kind;

            $new = $make($old);
            if ($new !== null) {
                // The picture must be one this school can read back before the draft points at it.
                $id = StudyDeckImages::idFromRef($new['url'] ?? null);
                if ($id === null || $this->images()->visibleMeta($id, $tenant) === null) {
                    throw new DraftImageException('The new picture could not be read back from the picture store, so nothing was changed.', 500);
                }
                // What stays: the picture's role in the lesson. What changes: the picture and everything that describes it (a highlight
                // belongs to the old picture, so the new one has none unless it is the original coming back).
                $new = $new + array_intersect_key($old ?? [], array_flip(['purpose', 'source_kind', 'role']));
            }

            $missingBefore = $slide['image_missing'] ?? null;
            if ($scene !== null) {
                // A scene that began with no `image` key at all gets exactly that back when its original is restored.
                $hadKey = array_key_exists('image', $scene);
                $logged = $this->readJson($dir . '/image-edits.json')['slides'][self::key($n, $node)]['had_key'] ?? null;
                if ($new === null && ($logged ?? $hadKey) === false) {
                    unset($deck['slides'][$i]['interaction']['nodes'][$j]['image']);
                } else {
                    $deck['slides'][$i]['interaction']['nodes'][$j]['image'] = $new;
                }
            } else {
                $deck['slides'][$i]['image'] = $new;
                // "No suitable picture" is the generator's note for a picture it could not find; it is no longer true once a teacher decides.
                if (array_key_exists('image_missing', $deck['slides'][$i])) {
                    $deck['slides'][$i]['image_missing'] = $kind === 'restore' ? ($this->originalMissing($dir, $n) ?? $missingBefore) : null;
                }
            }

            $bundle = $dir . '/bundle';
            $images = $scene === null ? $this->readJson($bundle . '/images.json') : null;
            if (is_array($images)) {
                $key = array_key_exists((string) $n, $images) ? (string) $n : $n;
                $images[$key] = $new ?? ['missing' => 'taken out by the teacher', 'query' => (string) ($old['query'] ?? ''), 'purpose' => (string) ($old['purpose'] ?? '')];
            }
            $htmlPath = $bundle . '/out/presentation.html';
            $html = $scene === null && is_file($htmlPath) ? (string) file_get_contents($htmlPath) : null;
            $earlier = $this->readJson($dir . '/image-edits.json')['slides'][self::key($n, $node)] ?? null;
            $figureOpen = $html === null ? null : ($this->figureOpenTag($html, $n) ?? ($earlier['figure_open'] ?? null));
            $newHtml = $html === null ? null : $this->patchHtml($html, $n, $old, $new, ($slide['slide_type'] ?? '') === 'cover', $figureOpen);

            $editsPath = $dir . '/image-edits.json';
            $edits = $this->readJson($editsPath);
            $edits = is_array($edits) ? $edits : ['slides' => []];
            $logKey = self::key($n, $node);
            // The FIRST state is kept for ever: the original picture, or `null` when the slide began without one.
            $entry = $edits['slides'][$logKey] ?? ['original' => $old, 'original_missing' => $missingBefore, 'had_key' => $scene === null ? true : array_key_exists('image', $scene), 'figure_open' => $figureOpen, 'history' => []];
            $entry['history'][] = ['at' => date('c'), 'by' => $userId, 'kind' => $did, 'from' => $old['url'] ?? null, 'to' => $new['url'] ?? null];
            $entry['current'] = $new['url'] ?? null;
            $edits['slides'][$logKey] = $entry;

            $writes = [$bundle . '/deck.json' => $deck, $editsPath => $edits];
            if (is_array($images)) {
                $writes[$bundle . '/images.json'] = $images;
            }
            $texts = $newHtml !== null && $newHtml !== $html ? [$htmlPath => $newHtml] : [];
            $this->writeAll($writes, $texts);

            // The draft's own picture summary (what the review screen shows) is recomputed from the saved deck.
            $status['images'] = array_diff_key((new ImageReadiness($this->images()))->deckImages($bundle, $tenant), ['ids' => 1]);
            $this->runs->save($status);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $canRestore = ($entry['original']['url'] ?? null) !== ($new['url'] ?? null);
        [$slideAfter, $sceneAfter] = $this->parts($deck, $i, $j);

        return [
            'slide' => $n,
            'node' => $node,
            'action' => $did,
            'image' => $new === null ? null : (['url' => (new StudyDeckImageUrls($this->images()))->signed((int) StudyDeckImages::idFromRef($new['url']), $tenant)] + $new),
            'edited' => $canRestore,
            'can_restore' => $canRestore,
            'state' => self::rules($slideAfter, $sceneAfter) + ['can_restore' => $canRestore],
        ];
    }

    private function originalMissing(string $dir, int $n): ?string
    {
        $edits = $this->readJson($dir . '/image-edits.json');

        return is_array($edits) ? ($edits['slides'][(string) $n]['original_missing'] ?? null) : null;
    }

    /**
     * Write every file or none: each to a temporary name first, then renamed over the old one; a failure part-way puts the earlier contents back.
     *
     * @param array<string,mixed> $json path => data
     * @param array<string,string> $text path => contents
     * @throws DraftImageException
     */
    private function writeAll(array $json, array $text): void
    {
        $new = [];
        foreach ($json as $path => $data) {
            $new[$path] = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $new += $text;
        $before = [];
        foreach (array_keys($new) as $path) {
            $before[$path] = is_file($path) ? file_get_contents($path) : null;
        }
        $done = [];
        try {
            foreach ($new as $path => $contents) {
                $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
                if (file_put_contents($tmp, $contents) === false) {
                    @unlink($tmp);
                    throw new \RuntimeException('write failed');
                }
                if (!@rename($tmp, $path)) {
                    // Windows will not rename over an existing file in every case.
                    if (!@copy($tmp, $path)) {
                        @unlink($tmp);
                        throw new \RuntimeException('replace failed');
                    }
                    @unlink($tmp);
                }
                $done[] = $path;
            }
        } catch (\Throwable) {
            foreach ($done as $path) {
                if ($before[$path] === null) {
                    @unlink($path);
                } else {
                    file_put_contents($path, $before[$path]);
                }
            }
            throw new DraftImageException('The draft could not be saved, so nothing was changed. Your original picture is still in place.', 500);
        }
    }

    /**
     * The rendered presentation after one slide's picture was added, replaced or removed: that slide's figure and its line in the image credits,
     * nothing else. A cover is drawn without a picture in the HTML, so only deck.json changes for it.
     *
     * @param array<string,mixed>|null $old
     * @param array<string,mixed>|null $new
     * @throws DraftImageException
     */
    public function patchHtml(string $html, int $n, ?array $old, ?array $new, bool $cover = false, ?string $figureOpen = null): string
    {
        if ($cover) {
            return $html;
        }
        $e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
        $start = strpos($html, '<span class="slide-num">Slide ' . $n . '</span>');
        if ($start === false) {
            if ($old === null && $new === null) {
                return $html;
            }
            throw new DraftImageException("The rendered presentation has no slide $n to update.", 500);
        }
        $next = strpos($html, '<section class="slide">', $start);
        $end = $next === false ? strlen($html) : $next;
        $segment = substr($html, $start, $end - $start);

        $figure = null;
        if ($new !== null) {
            $credit = ($new['type'] ?? 'photo') === 'photo' ? '. ' . trim((($new['creator'] ?? '') ?: 'Openverse') . ', ' . ($new['licence'] ?? '')) : '';
            $img = '<img src="' . $e((string) $new['url']) . '" alt="' . $e((string) $new['alt']) . '">';
            $cap = '<figcaption>' . $e(rtrim((string) ($new['caption'] ?? ''), '.') . $credit) . '</figcaption>';
            $figure = [$img, $cap];
        }
        $pattern = '#(<figure\b[^>]*>)<img [^>]*>\s*<figcaption>.*?</figcaption>(</figure>)#s';
        $hasFigure = (bool) preg_match($pattern, $segment);

        if ($hasFigure && $figure !== null) {
            $patched = preg_replace_callback($pattern, fn ($m) => $m[1] . $figure[0] . $figure[1] . $m[2], $segment, 1);
        } elseif ($hasFigure) {
            $patched = preg_replace($pattern, '', $segment, 1);
        } elseif ($figure !== null) {
            // A slide that had no picture: the figure goes where the renderer puts one, before the slide's first callout (or at its end).
            // The figure's own opening tag (its design-system and teaching-level attributes) is the one it had before, when that is known.
            $added = ($figureOpen ?? '<figure class="fig-md" data-block="visual">') . $figure[0] . $figure[1] . '</figure>';
            $at = strpos($segment, '<section class="callout');
            if ($at === false) {
                // No callout: before the slide's own closing tag.
                $at = strrpos($segment, '</section>');
                $at = $at === false ? strlen($segment) : $at;
            }
            $patched = substr($segment, 0, $at) . $added . substr($segment, $at);
        } else {
            $patched = $segment;
        }
        if ($patched === null) {
            throw new DraftImageException("The rendered presentation's picture for slide $n could not be updated.", 500);
        }
        $html = substr($html, 0, $start) . $patched . substr($html, $end);

        // The image-credits section lists every photograph by slide.
        $line = null;
        if ($new !== null && $this->sourceOf($new) !== '') {
            $line = '<p>' . $e('Slide ' . $n . ': ' . ($this->creditOf($new)) . ' Source: ' . $this->sourceOf($new)) . '</p>';
        }
        $linePattern = '#<p>Slide ' . $n . ': [^<]*</p>#u';
        if (preg_match($linePattern, $html)) {
            return (string) preg_replace_callback($linePattern, fn () => $line ?? '', $html, 1);
        }
        // This slide had no credit line (it had no picture, a drawing or an upload): put one back in slide order.
        $label = strpos($html, '<span class="callout-label">Image credits</span>');
        if ($line === null || $label === false) {
            return $html;
        }
        $close = strpos($html, '</section>', $label);
        $at = $close === false ? strlen($html) : $close;
        if (preg_match_all('#<p>Slide (\d+): #', substr($html, $label, $at - $label), $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $k => [$num]) {
                if ((int) $num > $n) {
                    $at = $label + $m[0][$k][1];
                    break;
                }
            }
        }

        return substr($html, 0, $at) . $line . substr($html, $at);
    }

    /** The opening tag of a slide's figure in the rendered presentation, or null when it has none. */
    public function figureOpenTag(string $html, int $n): ?string
    {
        $start = strpos($html, '<span class="slide-num">Slide ' . $n . '</span>');
        if ($start === false) {
            return null;
        }
        $next = strpos($html, '<section class="slide">', $start);
        $segment = substr($html, $start, ($next === false ? strlen($html) : $next) - $start);

        return preg_match('#<figure\b[^>]*>(?=<img )#', $segment, $m) ? $m[0] : null;
    }

    /** @param array<string,mixed> $image */
    private function sourceOf(array $image): string
    {
        return ($image['type'] ?? 'photo') === 'photo' ? (string) ($image['source_url'] ?? '') : '';
    }

    /** @param array<string,mixed> $image */
    private function creditOf(array $image): string
    {
        return (string) (($image['attribution'] ?? '') ?: (($image['title'] ?? '') . ' by ' . ($image['creator'] ?? '') . ' (' . ($image['licence'] ?? '') . ')'));
    }

    private function planner(int $tenant, int $chapterId): ImagePlanner
    {
        if ($this->planner !== null) {
            return ($this->planner)($tenant, $chapterId);
        }
        $search = ($this->search ?? app(ConceptImageSearchService::class))->forBatch();

        return new ImagePlanner($search, StudyImageStores::database($tenant, $chapterId, $this->images));
    }
}
