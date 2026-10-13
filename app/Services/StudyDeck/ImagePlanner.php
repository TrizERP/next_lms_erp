<?php

namespace App\Services\StudyDeck;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\Contracts\SourceImageCache;
use Illuminate\Support\Facades\Http;

/**
 * Finds, licence-checks, downloads and stores one picture per planned visual.
 *
 * Source: Openverse only (openly licensed), through the estate's existing
 * ConceptImageSearchService - no AI image generation.
 *
 * Every accepted picture is DOWNLOADED and stored in our own database (see
 * StudyDeckImages), never hot-linked: an earlier pilot lost images to
 * hot-linking (blocked or moved third-party URLs). Nothing is written to a
 * folder; the record's `url` is the picture's stable reference. The record it
 * keeps - source page, licence, creator, attribution and the match evidence -
 * is what DeckValidator checks.
 *
 * LIMIT, stated plainly: "does this picture show the concept" is judged here by
 * the words the picture's own title and tags share with the query. That catches
 * the junk case (a street photo for a diagram query) but cannot see pixels, so
 * every record carries its source page for a human to confirm before publishing.
 */
class ImagePlanner
{
    /** Licences that allow reuse and adaptation inside a lesson. NC and ND variants are refused. */
    private const ACCEPTED = '/^(CC0|PDM|BY|BY-SA)(\s|$)/i';

    /** Licences that require the creator to be credited. */
    private const NEEDS_CREDIT = '/^(BY|BY-SA)(\s|$)/i';

    private const SIGNIFICANT_MIN_LENGTH = 3;

    /** Ranked candidates examined per search pass. */
    private const CANDIDATES = 4;

    /** @var callable(string):?array{bytes:string,mime:string,width:int,height:int,source?:string} */
    private $download;

    /** Real downloads are paced so a deck full of images is not read as a burst. */
    private bool $paced;

    /** Seconds between two real downloads. Wikimedia answers a faster stream with 429 ("robot policy"), and a blocked address stays blocked for a while. */
    private const DOWNLOAD_GAP_SECONDS = 3.0;

    private float $lastDownloadAt = 0.0;

    /** @var array<string,int> host => unix time before which it has asked not to be called again */
    private array $blockedUntil = [];

    public function __construct(
        private readonly ConceptImageSearchService $search,
        private readonly DiagramImageStore $store,
        ?callable $download = null,
        private readonly float $minScore = 2.0,
        /** @var callable(string,string,array<int,array<string,mixed>>):array{accepted:array<int,int>,reasons:array<int,string>,reason:string}|null */
        private $judge = null,
        private readonly ?DiagramRenderer $diagrams = null,
    ) {
        $this->paced = $download === null;
        $this->download = $download ?? [$this, "fetch"];
    }

    /**
     * @param array<int,array<string,mixed>> $slides plan slides
     * @param array<int,array<string,mixed>> $content stage-2 content by slide number
     * @return array<int,array<string,mixed>> slide number => found image record, or ['missing' => reason]
     */
    public function plan(array $slides, array $content, ?callable $progress = null): array
    {
        $out = [];
        $usedUrls = [];

        foreach ($slides as $slide) {
            $visual = $slide['visual'] ?? null;
            if (!$visual) {
                continue;
            }

            $n = $slide['n'];
            $spec = is_array($slide['diagram'] ?? null) && !DiagramRenderer::problems($slide['diagram']) ? $slide['diagram'] : null;
            $role = (string) ($visual['role'] ?? 'object');
            $teaches = (string) ($slide['teaches'] ?? '');

            // A diagram of reasoning or structure is drawn, not searched for: no photograph
            // of an abstract idea exists, and a photograph of an incidental prop teaches nothing.
            if ($role === 'diagram' && $spec) {
                $out[$n] = $this->diagram($spec) + ['purpose' => $visual['purpose']];
            } else {
                $found = $this->find((string) $visual['query'], $usedUrls, (string) $visual['purpose'], $teaches);
                if (isset($found['missing'])) {
                    $out[$n] = $spec
                        ? $this->diagram($spec) + ['purpose' => $visual['purpose'], 'photo_missing' => $found['missing']]
                        : $found + ['query' => $visual['query'], 'purpose' => $visual['purpose']];
                } else {
                    $usedUrls[] = $found['source_image_url'];
                    $out[$n] = $found + ['purpose' => $visual['purpose']];
                }
            }

            if ($progress) {
                $progress($n, $out[$n]);
            }
        }

        return $out;
    }

    /**
     * Draw a planned diagram. Its alt text and caption come from the spec alone, so they
     * can only describe what was drawn; it has no source or licence because nothing was found.
     *
     * @return array<string,mixed>
     */
    public function diagram(array $spec): array
    {
        $file = ($this->diagrams ?? new DiagramRenderer())->render($spec);

        return [
            'type' => 'diagram',
            'url' => $this->store->store($file['bytes'], $file['mime']),
            'width' => $file['width'],
            'height' => $file['height'],
            'sha1' => sha1($file['bytes']),
            'sha256' => hash('sha256', $file['bytes']),
            'layout' => $spec['layout'],
            'texts' => DiagramRenderer::texts($spec),
            'alt' => DiagramRenderer::describe($spec),
            'caption' => trim((string) $spec['title']),
            'licence' => null,
            'source_url' => null,
            'creator' => null,
            'attribution_required' => false,
            'attribution' => '',
            'review' => ['relevant' => true, 'reason' => 'drawn from the plan\'s own diagram spec'],
        ];
    }
    /**
     * Draw the deck's diagrams again with the current DiagramRenderer, from the specs the plan already holds.
     *
     * For when the look of a drawn diagram changed. The geometry never does (see DiagramRenderer::anchors), so the
     * hotspots written on a diagram stay where its labels are. Photographs and illustrations are not touched.
     *
     * @param array<int,array<string,mixed>> $slides plan slides
     * @param array<int,array<string,mixed>> $images slide number => image record
     * @return array<int,array<string,mixed>>
     */
    public function redraw(array $slides, array $images): array
    {
        foreach ($slides as $slide) {
            $n = $slide['n'];
            $image = $images[$n] ?? null;
            $spec = $slide['diagram'] ?? null;
            if (!is_array($image) || ($image['type'] ?? '') !== 'diagram' || !in_array($image['layout'] ?? '', DiagramRenderer::LAYOUTS, true) || !is_array($spec) || DiagramRenderer::problems($spec)) {
                continue;
            }
            // Same words in the same places: only the drawing changes.
            $drawn = $this->diagram($spec);
            if ($drawn['texts'] !== ($image['texts'] ?? null)) {
                continue;
            }
            $images[$n] = $drawn + array_diff_key($image, $drawn);
        }

        return $images;
    }

    /**
     * A photograph a person chose (and looked at), instead of one a search ranked. It faces the same checks as a found
     * one: an accepted licence, a credit where the licence needs one, a real download, enough detail to learn from, and
     * keyword evidence that it is what the slide needs; the record it returns is the same shape, so the validator, the
     * renderers and the player treat it alike. What differs is the review note, which says a person chose it.
     *
     * @param array<string,mixed> $pick image_url, source_url, title, creator, license, provider, alt, caption, thumbnail_url, tags, shows, local_file (the reviewer's saved copy, used only when the download fails)
     * @return array<string,mixed>
     * @throws \InvalidArgumentException when the picture may not be used, or is not what the query asks for
     * @throws \RuntimeException when it cannot be downloaded or has no detail
     */
    public function adopt(array $pick, string $query, string $purpose): array
    {
        $licence = trim((string) ($pick['license'] ?? ''));
        if (!$this->licenceAccepted($licence)) {
            throw new \InvalidArgumentException('licence "' . $licence . '" is not accepted (CC0, PDM, BY and BY-SA only)');
        }
        $needsCredit = (bool) preg_match(self::NEEDS_CREDIT, $licence);
        $credit = $this->credit($pick);
        if ($needsCredit && $credit === '') {
            throw new \InvalidArgumentException('the licence requires attribution and none was supplied');
        }
        if (empty($pick['source_url'])) {
            throw new \InvalidArgumentException('a picture needs its source page recorded');
        }

        $url = (string) ($pick['image_url'] ?? '');
        $file = ($this->download)($url);
        if ($file === null && !empty($pick['thumbnail_url']) && $pick['thumbnail_url'] !== $url) {
            $file = ($this->download)((string) $pick['thumbnail_url']);
        }
        // A host that refuses a download (Wikimedia answers a large copy it has not cached with 429) must not cost a picture a
        // reviewer already downloaded and looked at: the copy the reviewer saved, from this same address, is used then.
        if ($file === null && !empty($pick['local_file'])) {
            $file = $this->localCopy((string) $pick['local_file'], $url);
        }
        if ($file === null) {
            throw new \RuntimeException('the picture could not be downloaded from ' . $url);
        }
        // The gate reads sharpness, which a soft subject (large smooth cells under a low-power lens) scores low on. A reviewer who
        // has looked at the picture can accept it as it is (`accept_soft`); the record says so.
        $soft = !self::hasDetail($file['bytes']);
        if ($soft && empty($pick['accept_soft'])) {
            throw new \RuntimeException('the picture is out of focus or has no detail to learn from');
        }

        // Keyword evidence from the picture's title and the reviewer's own note of what it shows.
        $match = $this->matchEvidence($query, ['title' => (string) ($pick['title'] ?? ''), 'tags' => array_merge((array) ($pick['tags'] ?? []), [(string) ($pick['shows'] ?? '')])]);
        if ($match['shared'] < 1) {
            throw new \InvalidArgumentException('nothing in the title or the note of what it shows matches the query "' . $query . '"');
        }
        $alt = trim((string) ($pick['alt'] ?? ''));

        return [
            'type' => 'photo',
            'alt' => $alt !== '' ? $alt : $this->altFor($pick),
            'caption' => trim((string) ($pick['caption'] ?? '')) ?: $this->cleanTitle((string) ($pick['title'] ?? '')),
            'url' => $this->store->store($file['bytes'], $file['mime'], (string) ($file['source'] ?? $url)),
            'width' => $file['width'],
            'height' => $file['height'],
            'query' => $query,
            'source_image_url' => $url,
            'source_url' => (string) $pick['source_url'],
            'title' => $pick['title'] ?? null,
            'creator' => $pick['creator'] ?? null,
            'provider' => $pick['provider'] ?? 'openverse',
            'licence' => $licence,
            'attribution_required' => $needsCredit,
            'attribution' => $credit,
            'sha1' => sha1($file['bytes']),
            'sha256' => hash('sha256', $file['bytes']),
            'match' => $match,
            // False when the picture makes a dull or busy texture behind another slide (see the player's isBackdropPhoto).
            ...(array_key_exists('backdrop', $pick) ? ['backdrop' => (bool) $pick['backdrop']] : []),
            'review' => ['relevant' => true, 'reason' => 'Chosen by a reviewer who looked at the picture: ' . trim((string) ($pick['shows'] ?? 'it shows what the slide teaches')) . '.' . ($soft ? ' Soft subject: the sharpness gate was waived by the reviewer.' : '')],
            'purpose' => $purpose,
        ];
    }

    /**
     * A picture file a reviewer saved earlier, read as if it had just been downloaded from `$source`.
     *
     * @return array{bytes:string,mime:string,width:int,height:int,source:string}|null
     */
    private function localCopy(string $path, string $source): ?array
    {
        $bytes = is_file($path) ? (string) file_get_contents($path) : '';
        $info = $bytes !== '' && strlen($bytes) <= 8 * 1024 * 1024 ? @getimagesizefromstring($bytes) : false;
        if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1], 'source' => $source];
    }

    /**
     * A figure drawn for this lesson (a vector illustration rendered to PNG), kept like a drawn diagram: no source, no
     * licence, nothing to credit, and the words drawn on it recorded so hotspots can be checked against them.
     *
     * @param array<string,mixed> $meta caption, alt, texts
     * @return array<string,mixed>
     */
    public function illustration(string $file, array $meta, string $purpose): array
    {
        $bytes = is_file($file) ? (string) file_get_contents($file) : '';
        $info = $bytes !== '' ? @getimagesizefromstring($bytes) : false;
        if ($info === false || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
            throw new \RuntimeException('the illustration ' . $file . ' is missing or is not a PNG or JPEG');
        }
        $texts = array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) ($meta['texts'] ?? []))));
        if (!$texts || trim((string) ($meta['alt'] ?? '')) === '') {
            throw new \InvalidArgumentException('an illustration needs the words drawn on it and an alt text');
        }

        return [
            'type' => 'diagram',
            'url' => $this->store->store($bytes, (string) $info['mime']),
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'sha1' => sha1($bytes),
            'sha256' => hash('sha256', $bytes),
            'layout' => 'illustration',
            'texts' => $texts,
            'alt' => trim((string) $meta['alt']),
            'caption' => trim((string) ($meta['caption'] ?? '')),
            'licence' => null,
            'source_url' => null,
            'creator' => null,
            'attribution_required' => false,
            'attribution' => '',
            'review' => ['relevant' => true, 'reason' => 'an illustration drawn for this lesson from the chapter text, checked by a reviewer'],
            'purpose' => $purpose,
        ];
    }

    /**
     * Wikimedia first (the estate's configured, relevance-tuned source); if that
     * yields nothing usable, one more try across every Openverse source. Both
     * passes face the same licence, match and download checks.
     *
     * @return array<string,mixed>
     */
    public function find(string $query, array $excludeUrls = [], string $purpose = "", string $teaches = ""): array
    {
        $first = $this->attempt($query, $excludeUrls, null, $purpose, $teaches);
        if (!isset($first['missing']) || str_contains($first['missing'], 'disabled') || str_contains($first['missing'], 'licence')) {
            return $first;
        }
        $second = $this->attempt($query, $excludeUrls, "", $purpose, $teaches);

        return isset($second['missing']) ? $first : $second;
    }

    private function attempt(string $query, array $excludeUrls, ?string $source, string $purpose, string $teaches = ""): array
    {
        if (!$this->search->available()) {
            return ['missing' => 'image search is disabled'];
        }

        // Several ranked candidates, not just the top one: for abstract topics the
        // best keyword match is often the wrong picture and the right one is third.
        $candidates = $this->search->rankedImagesFor($query, self::CANDIDATES, $this->minScore, $source);
        if (!$candidates) {
            return ['missing' => 'no sufficiently relevant, openly licensed image found'];
        }

        $usable = [];
        $why = [];
        foreach ($candidates as $image) {
            $match = $this->matchEvidence($query, $image);
            if (in_array($image['url'], $excludeUrls, true)) {
                $why[] = 'already used on another slide';
            } elseif (!$this->licenceAccepted((string) ($image['license'] ?? ''))) {
                $why[] = 'licence "' . ($image['license'] ?? 'unknown') . '" is not accepted';
            } elseif (!$this->titleIsDescriptive((string) ($image['title'] ?? ''))) {
                $why[] = 'image title "' . ($image['title'] ?? '') . '" does not say what the picture shows';
            } elseif ($match['shared'] < min(2, max(1, $match['of'])) || $match['shared'] / max(1, $match['of']) < 0.4) {
                $why[] = 'image title and tags do not match enough of the query (' . $match['shared'] . ' of ' . $match['of'] . ' words)';
            } else {
                $usable[] = $image + ['_match' => $match];
            }
        }
        if (!$usable) {
            return ['missing' => $why[0] ?? 'no usable candidate'];
        }

        // Keyword overlap only catches junk. Whether a picture actually shows what the
        // slide needs is judged separately, over all usable candidates at once, before
        // anything is downloaded.
        $order = array_keys($usable);
        $verdict = ['accepted' => $order, 'reasons' => []];
        if ($this->judge) {
            $verdict = ($this->judge)($query, $purpose, $usable, $teaches);
            $order = array_values(array_filter((array) ($verdict['accepted'] ?? []), fn ($i) => isset($usable[$i])));
            if (!$order) {
                return ['missing' => 'rejected on relevance review: ' . ($verdict['reason'] ?? 'none of the candidates fits')];
            }
        }

        $lastMissing = 'image could not be downloaded';
        foreach ($order as $i) {
            $image = $usable[$i];
            $match = $image['_match'];

            $needsCredit = (bool) preg_match(self::NEEDS_CREDIT, (string) $image['license']);
            $credit = $this->credit($image);
            if ($needsCredit && $credit === '') {
                $lastMissing = 'licence requires attribution but none was supplied';
                continue; // cannot be shown lawfully without a credit; try the next
            }

            $file = ($this->download)((string) $image['url']);
            if ($file === null && !empty($image['thumbnail_url']) && $image['thumbnail_url'] !== $image['url']) {
                $file = ($this->download)((string) $image['thumbnail_url']);
            }
            if ($file === null) {
                continue;
            }
            // The relevance review reads a picture's title and tags, not the picture. One that is out of focus or
            // empty teaches nothing whatever it is called, so it is refused here and the next candidate is tried.
            if (!self::hasDetail($file['bytes'])) {
                $lastMissing = 'the picture is out of focus or has no detail to learn from';
                continue;
            }

            return [
                'type' => 'photo',
                // Written AFTER the choice, from what the picture is actually called - never from
                // what the plan hoped it would show.
                'alt' => $this->altFor($image),
                'caption' => $this->cleanTitle((string) ($image['title'] ?? '')),
                // The store also learns where the picture came from, so a re-run reads it back instead of downloading it again.
                'url' => $this->store->store($file['bytes'], $file['mime'], (string) ($file['source'] ?? $image['url'])),
                'width' => $file['width'],
                'height' => $file['height'],
                'query' => $image['query'] ?? $query,
                'source_image_url' => (string) $image['url'],
                'source_url' => $image['source_url'] ?? null,
                'title' => $image['title'] ?? null,
                'creator' => $image['creator'] ?? null,
                'provider' => $image['provider'] ?? 'openverse',
                'licence' => (string) $image['license'],
                'attribution_required' => $needsCredit,
                'attribution' => $credit,
                'sha1' => sha1($file['bytes']),
                'sha256' => hash('sha256', $file['bytes']),
                'match' => $match,
                'review' => $this->judge ? ['relevant' => true, 'reason' => (string) ($verdict['reasons'][$i] ?? '')] : null,
            ];
        }

        return ['missing' => $lastMissing];
    }

    /**
     * Least sharpness (variance of the Laplacian of the picture at 320px) a photograph needs to be shown. Measured on
     * real candidates: an out-of-focus blur scored 8; micrographs, specimens, instruments and labelled diagrams 800 to 6000.
     */
    public const MIN_DETAIL = 100.0;

    /**
     * Does the picture have detail to learn from? True when it cannot be measured (no GD, an unreadable file), so the
     * gate only ever refuses a picture it has actually seen to be featureless.
     */
    public static function hasDetail(string $bytes): bool
    {
        $variance = self::detail($bytes);

        return $variance === null || $variance >= self::MIN_DETAIL;
    }

    /** Variance of the Laplacian of the picture, scaled to 320px on its long side; null if it cannot be measured. */
    public static function detail(string $bytes): ?float
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        // Too small to measure sharpness on (an icon, a one-pixel stand-in): not judged here.
        if (max($w, $h) < 64) {
            return null;
        }
        $scale = 320 / max($w, $h);
        $tw = max(8, (int) round($w * $scale));
        $th = max(8, (int) round($h * $scale));
        $small = imagecreatetruecolor($tw, $th);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $tw, $th, $w, $h);

        $grey = [];
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $grey[$y][$x] = 0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255);
            }
        }
        $sum = 0.0;
        $sumSquares = 0.0;
        $n = 0;
        for ($y = 1; $y < $th - 1; $y++) {
            for ($x = 1; $x < $tw - 1; $x++) {
                $laplacian = $grey[$y - 1][$x] + $grey[$y + 1][$x] + $grey[$y][$x - 1] + $grey[$y][$x + 1] - 4 * $grey[$y][$x];
                $sum += $laplacian;
                $sumSquares += $laplacian * $laplacian;
                $n++;
            }
        }

        return $n > 0 ? $sumSquares / $n - ($sum / $n) ** 2 : null;
    }

    /**
     * Hold the photographs a run already chose to the picture rules again, without a search or a model call.
     *
     * Each photograph is read back (from the school's stored copy of the download) and refused if it fails a rule that
     * did not exist, or was not applied, when it was chosen. A refused photograph is replaced by the slide's own drawn
     * diagram when the plan gave one, else the slide goes without, exactly as plan() does when no picture is found. A
     * diagram is never changed, so the interactions written on it stay true.
     *
     * @param array<int,array<string,mixed>> $slides plan slides
     * @param array<int,array<string,mixed>> $images slide number => the run's image record
     * @param callable(int,array<string,mixed>):void|null $progress told of each photograph that was replaced
     * @return array<int,array<string,mixed>> the image records, with any refused photograph replaced
     */
    public function recheck(array $slides, array $images, ?callable $progress = null): array
    {
        foreach ($slides as $slide) {
            $n = $slide['n'];
            $image = $images[$n] ?? null;
            if (!is_array($image) || ($image['type'] ?? '') !== 'photo') {
                continue;
            }

            $file = ($this->download)((string) ($image['source_image_url'] ?? ''));
            if ($file === null || self::hasDetail($file['bytes'])) {
                // Unreadable here means unchanged, never silently dropped: a picture is only refused for what was seen.
                continue;
            }

            $why = 'the picture is out of focus or has no detail to learn from';
            $visual = (array) ($slide['visual'] ?? []);
            $spec = is_array($slide['diagram'] ?? null) && !DiagramRenderer::problems($slide['diagram']) ? $slide['diagram'] : null;
            $images[$n] = $spec
                ? $this->diagram($spec) + ['purpose' => $visual['purpose'] ?? '', 'photo_missing' => $why]
                : ['missing' => $why, 'query' => $visual['query'] ?? '', 'purpose' => $visual['purpose'] ?? ''];

            if ($progress) {
                $progress($n, $images[$n]);
            }
        }

        return $images;
    }

    /** A title like "IMG_0770" or "P1020304" says nothing, so nothing true can be said about the picture from it. */
    public function titleIsDescriptive(string $title): bool
    {
        $clean = $this->cleanTitle($title);
        if (mb_strlen($clean) < 4 || preg_match('/^(img|dsc|dscn|dscf|pic|image|photo|p|untitled|screenshot)[ _\-]?\d*$/i', $clean) || !preg_match('/\p{L}{3,}/u', $clean)) {
            return false;
        }

        return true;
    }

    /** The picture's own title, without file-name furniture. */
    public function cleanTitle(string $title): string
    {
        $t = preg_replace('/^File:/i', '', trim($title));
        $t = preg_replace('/\.(jpe?g|png|webp|gif|svg)$/i', '', (string) $t);

        return trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string) $t)));
    }

    /** Alt text from the chosen picture's own title and tags only. */
    public function altFor(array $image): string
    {
        $alt = 'Photograph: ' . $this->cleanTitle((string) ($image['title'] ?? ''));
        $tags = array_slice(array_values(array_filter((array) ($image['tags'] ?? []), fn ($t) => is_string($t) && mb_strlen($t) > 2 && !preg_match('/^\d+$|u[0-9a-f]{4}/i', $t))), 0, 3);

        return $tags ? $alt . '. Tags: ' . implode(', ', $tags) . '.' : $alt . '.';
    }

    public function licenceAccepted(string $licence): bool
    {
        $licence = trim($licence);
        if ($licence === '' || stripos($licence, 'NC') !== false || stripos($licence, 'ND') !== false) {
            return false;
        }

        return (bool) preg_match(self::ACCEPTED, $licence);
    }

    /** @return array{shared:int,of:int,terms:array<int,string>} */
    public function matchEvidence(string $query, array $image): array
    {
        $words = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [],
            fn ($w) => mb_strlen($w) >= self::SIGNIFICANT_MIN_LENGTH
        )));
        $haystack = mb_strtolower(trim(($image['title'] ?? '') . ' ' . implode(' ', (array) ($image['tags'] ?? []))));
        $shared = array_values(array_filter($words, fn ($w) => str_contains($haystack, $w)));

        return ['shared' => count($shared), 'of' => count($words), 'terms' => $shared];
    }

    private function credit(array $image): string
    {
        if (!empty($image['attribution'])) {
            return trim((string) $image['attribution']);
        }
        if (!empty($image['creator']) && !empty($image['title'])) {
            return trim($image['title'] . ' by ' . $image['creator'] . ' (' . $image['license'] . ')');
        }

        return '';
    }

    /**
     * A picture's bytes, or null.
     *
     * Wikimedia is asked for its 960px copy first (a lighter path than the full original, and a slide never needs more),
     * but that copy may not have been drawn yet and a copy it has to draw is answered with 429 for a while, whereas the
     * original is served from cache. So when the copy fails the original is tried before the picture is given up.
     *
     * @return array{bytes:string,mime:string,width:int,height:int,source:string}|null
     */
    private function fetch(string $url): ?array
    {
        $preferred = $this->preferThumbnail($url);
        $file = $this->fetchOne($preferred, $preferred !== $url ? 0 : 2);
        if ($file === null && $preferred !== $url && !preg_match('/\.tiff?$/i', $url)) {
            $file = $this->fetchOne($url, 2);
        }

        return $file;
    }

    /**
     * One address, with the courtesy a shared host asks for: a descriptive User-Agent, a gap between downloads, and
     * patience with 429. A host that asks us to wait more than a minute (Wikimedia's "robot policy" says 10) is left alone
     * for that long: every other candidate on it fails at once instead of making the wait longer, and the planner moves to
     * a picture from another source.
     *
     * @param int $retries how many times a 429 or 503 is waited out and tried again
     * @return array{bytes:string,mime:string,width:int,height:int,source:string}|null
     */
    private function fetchOne(string $url, int $retries): ?array
    {
        // A picture downloaded for this school before is in the database already; nothing is kept in a folder.
        if ($this->store instanceof SourceImageCache && ($hit = $this->store->cached($url)) !== null) {
            return $hit + ['source' => $url];
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if (($this->blockedUntil[$host] ?? 0) > time()) {
            return null;
        }

        try {
            $response = null;
            for ($attempt = 0; $attempt <= $retries; $attempt++) {
                $this->pace();
                $response = Http::withUserAgent('EduERP-StudyDeck/1.0 (educational content; openly licensed images only)')
                    ->timeout(25)->get($url);
                if (!in_array($response->status(), [429, 503], true)) {
                    break;
                }
                $wait = (int) $response->header('Retry-After');
                if ($wait > 60) {
                    $this->blockedUntil[$host] = time() + $wait;

                    return null;
                }
                if ($attempt < $retries) {
                    sleep(min(30, max($wait, 4 * ($attempt + 1))));
                }
            }
            if (!$response || !$response->successful()) {
                return null;
            }
            $bytes = $response->body();
            if ($bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
                return null;
            }
            $info = @getimagesizefromstring($bytes);
            if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }

            return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1], 'source' => $url];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Wait out the gap since the last real download; a stubbed download (tests) is never paced. */
    private function pace(): void
    {
        if (!$this->paced) {
            return;
        }
        $wait = self::DOWNLOAD_GAP_SECONDS - (microtime(true) - $this->lastDownloadAt);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->lastDownloadAt = microtime(true);
    }

    /**
     * Wikimedia serves resized copies from a lighter, separately limited path than
     * the full original, and a slide never needs more than ~960px.
     */
    private function preferThumbnail(string $url): string
    {
        if (preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons)/([0-9a-f]/[0-9a-f]{2})/([^/]+\.(jpe?g|png|tiff?))$#i', $url, $m)) {
            // A TIFF (common for micrographs) can only be had as a rendered copy, which Wikimedia serves as a JPEG.
            return $m[1] . '/thumb/' . $m[2] . '/' . $m[3] . '/960px-' . $m[3] . (preg_match('/^tiff?$/i', $m[4]) ? '.jpg' : '');
        }

        return $url;
    }
}
