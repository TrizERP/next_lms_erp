<?php

namespace App\Services\StudyDeck;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use Illuminate\Support\Facades\Http;

/**
 * Finds, licence-checks, downloads and stores one picture per planned visual.
 *
 * Source: Openverse only (openly licensed), through the estate's existing
 * ConceptImageSearchService - no AI image generation.
 *
 * Every accepted picture is DOWNLOADED and stored under our own storage, never
 * hot-linked: an earlier pilot lost images to hot-linking (blocked or moved
 * third-party URLs). The record it keeps - source page, licence, creator,
 * attribution and the match evidence - is what DeckValidator checks.
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

    /** @var callable(string):?array{bytes:string,mime:string,width:int,height:int} */
    private $download;

    /** Real downloads are paced so a deck full of images is not read as a burst. */
    private bool $paced;

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

            return [
                'type' => 'photo',
                // Written AFTER the choice, from what the picture is actually called - never from
                // what the plan hoped it would show.
                'alt' => $this->altFor($image),
                'caption' => $this->cleanTitle((string) ($image['title'] ?? '')),
                'url' => $this->store->store($file['bytes'], $file['mime']),
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
                'match' => $match,
                'review' => $this->judge ? ['relevant' => true, 'reason' => (string) ($verdict['reasons'][$i] ?? '')] : null,
            ];
        }

        return ['missing' => $lastMissing];
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

    /** @return array{bytes:string,mime:string,width:int,height:int}|null */
    private function fetch(string $url): ?array
    {
        $url = $this->preferThumbnail($url);
        $cached = storage_path('app/study-deck/_image-cache/' . sha1($url));
        if (is_file($cached)) {
            $bytes = (string) file_get_contents($cached);
            $info = @getimagesizefromstring($bytes);
            if ($info !== false) {
                return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1]];
            }
        }

        try {
            // Wikimedia and others refuse requests without a descriptive User-Agent,
            // and answer a burst of downloads with 429; wait as asked and try again.
            $response = null;
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $response = Http::withUserAgent('EduERP-StudyDeck/1.0 (educational content; openly licensed images only)')
                    ->timeout(25)->get($url);
                if (!in_array($response->status(), [429, 503], true)) {
                    break;
                }
                sleep(min(30, max((int) $response->header('Retry-After'), 3 * ($attempt + 1))));
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

            if (!is_dir(dirname($cached))) {
                @mkdir(dirname($cached), 0775, true);
            }
            @file_put_contents($cached, $bytes);

            return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1]];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Wikimedia serves resized copies from a lighter, separately limited path than
     * the full original, and a slide never needs more than ~960px.
     */
    private function preferThumbnail(string $url): string
    {
        if (preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons)/([0-9a-f]/[0-9a-f]{2})/([^/]+\.(?:jpe?g|png))$#i', $url, $m)) {
            return $m[1] . '/thumb/' . $m[2] . '/' . $m[3] . '/960px-' . $m[3];
        }

        return $url;
    }
}
