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
    private const NEEDS_CREDIT = '/^(BY|BY-SA|NASA media usage guidelines|Pexels License)(\s|$)/i';

    /** The licence label of a picture taken from the chapter's own textbook (permitted for this project). Accepted by its exact name only. */
    public const TEXTBOOK_LICENCE = 'Publisher textbook (permitted for this project)';

    /** Exact licence label for a manually selected image published under the Unsplash License. */
    public const UNSPLASH_LICENCE = 'Unsplash License';

    /** Where a deck picture came from: a web search, the chapter's textbook, a drawn diagram, or nowhere (no suitable picture). */
    public const KIND_WEB = 'web';
    public const KIND_TEXTBOOK = 'textbook';
    public const KIND_DIAGRAM = 'diagram';
    public const KIND_NONE = 'none';

    /** What a search stopped by its deadline answers: not a verdict on the query, so it is never remembered as a miss. */
    public const TIME_UP = 'time limit reached';

    /** How close a found picture is to its scene. Only the first is the strict check; the others relax the relevance gates (never licence, type, size or sharpness). */
    public const LEVEL_EXACT = 'exact';
    public const LEVEL_RELATED = 'related';
    public const LEVEL_TOPIC = 'topic';

    /** A clear educational picture for the subject, not for this scene: the last real-picture rung before a labelled placeholder. */
    public const LEVEL_GENERIC = 'generic';

    /** Marks a purpose as "a generic educational picture is acceptable" (both judges read it). */
    public const GENERIC_PREFIX = 'GENERIC PICTURE: ';

    /** Marks a purpose as "a related picture is acceptable": both judges read it and accept the same kind of subject, not only this exact scene. */
    public const RELATED_PREFIX = 'RELATED PICTURE: ';

    /** The purpose the judges are given: the scene's own at the exact level, marked as related or generic below it. */
    private function purposeFor(string $level, string $purpose): string
    {
        return match ($level) {
            self::LEVEL_EXACT => $purpose,
            self::LEVEL_GENERIC => self::GENERIC_PREFIX . $purpose,
            default => self::RELATED_PREFIX . $purpose,
        };
    }

    /** What a related-picture search answers when no relevance review or picture check is available to vouch for what it finds. */
    public const NEEDS_CHECK = 'a related picture needs a relevance check and none is available';

    /** Words that name a whole subject or setting, so they say nothing about whether a picture shows THIS subject (a book cover is not evidence for "science"). */
    private const GENERIC = ['science', 'sciences', 'school', 'class', 'classroom', 'lesson', 'study', 'student', 'students', 'education', 'educational', 'learning', 'teacher', 'general', 'introduction'];

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

    /**
     * Looks at the picture itself (not only its title and tags) before it is kept: given the bytes, the type, the query, what the picture is
     * for and what it should teach, says whether it shows that. Unset for ordinary decks.
     *
     * @var (callable(string,string,string,string,string):array{ok:bool,shows?:string,reason?:string,focus?:array<string,float>,notice?:string})|null
     */
    private $pixelJudge = null;

    /**
     * What the picture search did, for a report: searches made, candidates seen, and why each rejected one was refused.
     *
     * @var array<string,int>
     */
    public array $stats = ['searches' => 0, 'candidates' => 0, 'rejected_duplicate' => 0, 'rejected_licence' => 0, 'rejected_format' => 0, 'rejected_title' => 0, 'rejected_text_review' => 0, 'download_failed' => 0, 'rejected_detail' => 0, 'rejected_pixels' => 0, 'accepted' => 0];

    /** @param callable(string,string,string,string,string):array{ok:bool,shows?:string,reason?:string} $judge */
    public function withPixelJudge(callable $judge): static
    {
        $this->pixelJudge = $judge;

        return $this;
    }

    /** @var array<string,callable(string,int):array<int,array<string,mixed>>> extra picture sources by name (nasa, pexels), each returning candidates */
    private array $providers = [];

    /** @var array<int,string> the extra sources searched for the scene being worked on */
    private array $active = [];

    private ?float $deadline = null;

    /** @var array<int,string> sources left out of the next searches (for example a host that is rate limiting): their candidates are neither downloaded nor counted */
    private array $skip = [];

    /** @param array<int,string> $names provider names as Openverse reports them (wikimedia, flickr, ...) */
    public function skipSources(array $names): static
    {
        $this->skip = array_values(array_map('strtolower', array_map('strval', $names)));

        return $this;
    }

    private ?TextbookImages $textbook = null;

    private int $textbookChapter = 0;

    /** @var array<int,string> textbook files already placed in this lesson */
    private array $textbookUsed = [];

    /** The chapter's textbook pictures, offered only AFTER a web search found nothing suitable (see textbookPicture). */
    public function withTextbookImages(?TextbookImages $textbook, int $chapterId): static
    {
        $this->textbook = $textbook;
        $this->textbookChapter = $chapterId;

        return $this;
    }

    /** @param array<string,callable(string,int):array<int,array<string,mixed>>> $providers */
    public function withProviders(array $providers): static
    {
        $this->providers = $providers;

        return $this;
    }

    /** Which extra sources the next searches may use (those named and configured); Openverse is always searched. */
    public function useProviders(array $names): static
    {
        $this->active = array_values(array_filter($names, fn ($n) => isset($this->providers[$n])));

        return $this;
    }

    /** A time after which no search, download or picture check is started. The pixel check is also told how long it has left. */
    public function withDeadline(?float $unixTime): static
    {
        $this->deadline = $unixTime;
        if (is_object($this->pixelJudge) && property_exists($this->pixelJudge, 'deadline')) {
            $this->pixelJudge->deadline = $unixTime;
        }

        return $this;
    }

    /** Whether the picture check can run now (one tiny call), or null when there is none to run. @return array{available:bool,reason:string}|null */
    public function verificationStatus(): ?array
    {
        return is_object($this->pixelJudge) && method_exists($this->pixelJudge, 'probe') ? $this->pixelJudge->probe() : null;
    }

    private function timeUp(): bool
    {
        return $this->deadline !== null && microtime(true) >= $this->deadline;
    }

    /** @var array<string,array<string,int>> candidates seen and pictures kept, by source (wikimedia, flickr, nasa, ...) */
    public array $bySource = [];

    /** The scene being worked on, and what was decided about each candidate for it, in order (for the report). */
    public string $scene = '';

    /** @var array<int,array<string,mixed>> */
    public array $events = [];

    private function event(array $image, string $decision, string $why = ''): void
    {
        $this->events[] = ['scene' => $this->scene, 'source' => (string) ($image['provider'] ?? 'openverse'), 'title' => mb_substr((string) ($image['title'] ?? ''), 0, 80), 'licence' => $image['license'] ?? null, 'url' => (string) ($image['url'] ?? ''), 'decision' => $decision, 'why' => $why];
    }

    private function tally(string $source, string $what, int $n = 1): void
    {
        $this->bySource[$source][$what] = ($this->bySource[$source][$what] ?? 0) + $n;
    }

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
                // 1. The web first. 2. The chapter's textbook, when the search found nothing suitable. 3. A diagram, when one can be
                // drawn from the plan's verified spec. 4. Otherwise no picture, said plainly.
                $found = $this->find((string) $visual['query'], $usedUrls, (string) $visual['purpose'], $teaches);
                if (isset($found['missing'])) {
                    $book = $this->textbookPicture((string) $visual['query'], (string) $visual['purpose'], $teaches);
                    if ($book !== null) {
                        $usedUrls[] = $book['source_image_url'];
                        $out[$n] = $book + ['purpose' => $visual['purpose'], 'web_search' => $found['missing']];
                    } elseif ($spec) {
                        $out[$n] = $this->diagram($spec) + ['purpose' => $visual['purpose'], 'photo_missing' => $found['missing']];
                    } else {
                        $out[$n] = $found + ['query' => $visual['query'], 'purpose' => $visual['purpose'], 'source_kind' => self::KIND_NONE];
                    }
                } else {
                    $usedUrls[] = $found['source_image_url'];
                    $out[$n] = $found + ['purpose' => $visual['purpose'], 'source_kind' => self::KIND_WEB];
                }
            }

            if ($progress) {
                $progress($n, $out[$n]);
            }
        }

        return $out;
    }

    /** Fill gaps after the normal web, textbook and planned-diagram ladder, using the existing diagram renderer. */
    public function fillMissingWithDiagrams(array $slides, array $map, array $images, ?callable $progress = null): array
    {
        foreach ($slides as $slide) {
            $n = (int) ($slide['n'] ?? 0);
            if ($n < 1 || (isset($images[$n]) && empty($images[$n]['missing']))) {
                continue;
            }
            $name = trim((string) ($slide['title'] ?? 'Lesson image')) ?: 'Lesson image';
            $conceptIds = array_values((array) ($slide['concept_ids'] ?? []));
            $concept = (array) ($map['concepts'][(int) ($conceptIds[0] ?? 0)] ?? []);
            $labels = array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), [
                $concept['name'] ?? '',
                $concept['key_idea'] ?? '',
                ...array_map(fn ($id) => (string) ($map['concepts'][(int) $id]['name'] ?? ''), array_slice($conceptIds, 1)),
                $name,
            ]))));
            while (count($labels) < 2) {
                $labels[] = $name;
            }
            $labels = array_map(fn ($label) => mb_substr($label, 0, 65), array_slice($labels, 0, 6));
            $spec = ['layout' => 'flow', 'title' => mb_substr($name, 0, 65), 'nodes' => $labels];
            if (DiagramRenderer::problems($spec)) {
                $labels = array_slice(array_values(array_filter(array_map(fn ($label) => trim(mb_substr((string) $label, 0, 65)), $labels))), 0, 6);
                while (count($labels) < 2) {
                    $labels[] = 'Learning idea';
                }
                $spec = ['layout' => 'flow', 'title' => 'Learning idea', 'nodes' => $labels];
            }
            $images[$n] = $this->diagram($spec) + ['purpose' => 'Generated educational diagram for ' . $name, 'source_kind' => 'generated_fallback'];
            if ($progress) {
                $progress($n);
            }
        }

        return $images;
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
            'source_kind' => self::KIND_DIAGRAM,
        ];
    }

    /**
     * A picture from the chapter's own textbook that shows what the scene is about, or null. Called only after the web search found
     * nothing suitable. The picture is chosen by the curated manifest's description of what it SHOWS and the words it shares with
     * the scene, never by its file name; where a picture check is available (the pixel judge) it must also agree. The record carries
     * the textbook as its source, the textbook licence label and a credit, and is marked `source_kind: textbook`.
     *
     * @return array<string,mixed>|null
     */
    public function textbookPicture(string $query, string $purpose, string $teaches, array $skip = []): ?array
    {
        if ($this->textbook === null || $this->textbookChapter <= 0) {
            return null;
        }
        $source = $this->textbook->source();
        foreach ($this->textbook->candidates($this->textbookChapter, $query, $purpose, $teaches, array_merge($this->textbookUsed, $skip)) as $c) {
            $e = $c['entry'];
            $file = $this->textbook->read($e);
            if ($file === null || min($file['width'], $file['height']) < 160) {
                continue;
            }
            if ($this->pixelJudge !== null) {
                $verdict = ($this->pixelJudge)($file['bytes'], $file['mime'], $query, $purpose, $teaches);
                if (empty($verdict['ok'])) {
                    continue;
                }
            }
            $caption = trim((string) ($e['caption'] ?? '')) !== '' ? trim((string) $e['caption']) : trim((string) $e['shows']);
            $page = (int) ($e['page'] ?? 0);
            $book = trim((string) ($source['title'] ?? 'the chapter textbook'));
            $credit = 'From ' . $book . ($page > 0 ? ', page ' . $page : '') . (!empty($source['publisher']) ? ' (' . $source['publisher'] . ')' : '') . '.';
            $this->textbookUsed[] = (string) $e['file'];
            $tags = array_merge((array) ($e['keywords'] ?? []), [(string) $e['shows']]);

            return [
                'type' => 'photo',
                'alt' => trim((string) $e['shows']),
                'caption' => $caption,
                'url' => $this->store->store($file['bytes'], $file['mime'], 'textbook:' . (string) $e['file']),
                'width' => $file['width'],
                'height' => $file['height'],
                'query' => $query,
                'source_image_url' => 'textbook:' . (string) $e['file'],
                'source_url' => 'textbook:' . (string) ($source['key'] ?? 'chapter') . ($page > 0 ? '#page-' . $page : ''),
                'title' => $caption,
                'creator' => (string) ($source['publisher'] ?? ''),
                'provider' => 'textbook',
                'licence' => self::TEXTBOOK_LICENCE,
                'attribution_required' => true,
                'attribution' => $credit,
                'sha1' => sha1($file['bytes']),
                'sha256' => hash('sha256', $file['bytes']),
                // The scene's whole text (query, purpose, what it teaches) is what the picture was matched against, so the evidence is read from it.
                'match' => $this->matchEvidence(trim($query . ' ' . $purpose . ' ' . $teaches), ['title' => $caption, 'tags' => $tags]) + ['evidence' => $c['shared']],
                'review' => ['relevant' => true, 'reason' => 'A textbook picture whose recorded content (' . trim((string) $e['shows']) . ') shares ' . implode(', ', $c['shared']) . ' with the scene; it was matched on the page\'s caption and the picture itself, not on its file name.'],
                'source_kind' => self::KIND_TEXTBOOK,
            ];
        }

        return null;
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
     * A picture accepted earlier, brought back from the picture store instead of searched for again. Everything that made it usable the first
     * time is checked again: the bytes are still there and still a PNG, JPEG or WebP, it still has detail, its licence is still accepted and a
     * credit it needs is still recorded. The credit and source are the original's, unchanged. Null when any of that fails.
     *
     * @param array<string,mixed> $record a record ImagePlanner::find() returned earlier (it carries `fetched_from`)
     * @return array<string,mixed>|null
     */
    public function reuse(array $record): ?array
    {
        $from = (string) ($record['fetched_from'] ?? '');
        if ($from === '' || !($this->store instanceof SourceImageCache) || ($hit = $this->store->cached($from)) === null) {
            return null;
        }
        $info = @getimagesizefromstring($hit['bytes']);
        if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || min((int) $info[0], (int) $info[1]) < 240 || !self::hasDetail($hit['bytes'])) {
            return null;
        }
        $licence = (string) ($record['licence'] ?? '');
        // A picture an image model made has no licence and no credit to check; what it is called is recorded on it.
        $generated = ($record['match_level'] ?? '') === 'ai_generated';
        if (!$generated && (!$this->licenceAccepted($licence) || (preg_match(self::NEEDS_CREDIT, $licence) && trim((string) ($record['attribution'] ?? '')) === ''))) {
            return null;
        }

        return [
            'url' => $this->store->store($hit['bytes'], (string) $info['mime'], $from),
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'sha1' => sha1($hit['bytes']),
            'sha256' => hash('sha256', $hit['bytes']),
        ] + $record;
    }

    /**
     * A picture made for this scene by an image model. It is NOT a photograph and carries no licence or credit (nobody else made it); the record
     * says what made it, and the alt text and caption say so too. The bytes face the same checks as any stored picture: a real PNG, JPEG or WebP
     * of a usable size.
     *
     * @param array<string,mixed> $meta kind, model, alt, caption, prompt, query
     * @return array<string,mixed>
     * @throws \InvalidArgumentException when the bytes are not a usable picture
     */
    public function generated(string $bytes, string $mime, array $meta): array
    {
        $info = $bytes !== '' && strlen($bytes) <= 8 * 1024 * 1024 ? @getimagesizefromstring($bytes) : false;
        if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('the generated picture is not a PNG, JPEG or WebP of acceptable size');
        }
        if (min((int) $info[0], (int) $info[1]) < 240) {
            throw new \InvalidArgumentException('the generated picture is too small (' . $info[0] . 'x' . $info[1] . ')');
        }

        return [
            'type' => 'diagram',
            'role' => 'scene',
            'layout' => 'ai_illustration',
            'match_level' => 'ai_generated',
            'url' => $this->store->store($bytes, (string) $info['mime'], $key = 'ai-illustration:' . sha1((string) ($meta['prompt'] ?? $bytes))),
            'fetched_from' => $key,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'sha1' => sha1($bytes),
            'sha256' => hash('sha256', $bytes),
            'alt' => trim((string) ($meta['alt'] ?? '')),
            'caption' => trim((string) ($meta['caption'] ?? '')),
            'query' => (string) ($meta['query'] ?? ''),
            'licence' => null,
            'source_url' => null,
            'creator' => null,
            'attribution_required' => false,
            'attribution' => 'AI-generated illustration' . (($meta['model'] ?? '') !== '' ? ' (' . $meta['model'] . ')' : '') . ' made for this lesson. Not a photograph.',
            'generated' => ['kind' => (string) ($meta['kind'] ?? 'ai_illustration'), 'model' => (string) ($meta['model'] ?? ''), 'prompt_sha1' => sha1((string) ($meta['prompt'] ?? ''))],
            'review' => ['relevant' => true, 'reason' => (string) ($meta['review'] ?? 'generated from the scene text'), 'pixel_checked' => (bool) ($meta['pixel_checked'] ?? false)],
        ];
    }

    /**
     * Look at a picture an image model made, with the picture check (when there is one): does it show the scene? Null when there is no check to
     * ask. An unverified answer (the check could not run) is returned as it is, for the caller to refuse.
     *
     * @return array<string,mixed>|null
     */
    public function checkIllustration(string $bytes, string $mime, string $query, string $purpose, string $teaches): ?array
    {
        return $this->pixelJudge === null ? null : ($this->pixelJudge)($bytes, $mime, $query, \App\Services\StudyDeck\Story\StoryPixelJudge::AI_PREFIX . $purpose, $teaches);
    }

    /**
     * The last rung of the ladder: a drawn, plainly labelled placeholder for a scene nothing better could be found for. A real PNG; no source,
     * licence or credit, and it says on its face (and in its alt text and caption) that it is a placeholder, never that it shows the scene.
     *
     * @param array<int,string> $ideas up to three concept names
     * @return array<string,mixed>
     */
    public function placeholder(string $topic, array $ideas): array
    {
        $stamp = 'Placeholder illustration: no picture of this scene was available';
        $file = ($this->diagrams ?? new DiagramRenderer())->placeholder($topic, $ideas, $stamp);

        return [
            'type' => 'diagram',
            'role' => 'scene',
            'layout' => 'placeholder',
            'match_level' => 'placeholder',
            'url' => $this->store->store($file['bytes'], $file['mime']),
            'width' => $file['width'],
            'height' => $file['height'],
            'sha1' => sha1($file['bytes']),
            'sha256' => hash('sha256', $file['bytes']),
            'texts' => $file['texts'],
            'alt' => 'Placeholder illustration for this scene (topic: ' . trim($topic) . '). No picture of the scene itself was available.',
            'caption' => 'Placeholder: no picture of this scene was available.',
            'licence' => null,
            'source_url' => null,
            'creator' => null,
            'attribution_required' => false,
            'attribution' => '',
            'review' => ['relevant' => true, 'reason' => 'drawn from the scene\'s own topic and ideas; a labelled placeholder, not a picture of the scene'],
        ];
    }

    /**
     * Wikimedia first (the estate's configured, relevance-tuned source); if that
     * yields nothing usable, one more try across every Openverse source. Both
     * passes face the same licence, match and download checks.
     *
     * @return array<string,mixed>
     */
    public function find(string $query, array $excludeUrls = [], string $purpose = "", string $teaches = "", string $level = self::LEVEL_EXACT): array
    {
        // With Wikimedia left out, the one search covers every other Openverse source at once instead of asking Wikimedia first.
        $first = $this->attempt($query, $excludeUrls, in_array('wikimedia', $this->skip, true) ? '' : null, $purpose, $teaches, $level);
        if (in_array('wikimedia', $this->skip, true) && isset($first['missing'])) {
            return $first;
        }
        if (!isset($first['missing']) || str_contains($first['missing'], 'disabled') || str_starts_with($first['missing'], self::SEARCH_FAILED) || str_contains($first['missing'], 'licence') || $first['missing'] === self::TIME_UP || $this->timeUp()) {
            return $first;
        }
        $second = $this->attempt($query, $excludeUrls, "", $purpose, $teaches, $level);

        return isset($second['missing']) ? $first : $second;
    }

    /** Prefix of the reason given when the search itself failed (timeout, HTTP error), as opposed to finding nothing. */
    public const SEARCH_FAILED = 'image search failed: ';

    /**
     * Why this attempt's searches failed technically, or null. Null unless EVERY search the attempt made failed (a timeout, an HTTP
     * error, an unreadable answer): a valid answer with no results, or one served from the cache, is a real "nothing found".
     */
    private function searchFailure(): ?string
    {
        $rows = $this->search->searches;
        if ($rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            if (!in_array($row['outcome'] ?? '', ['timeout', 'http_error', 'error', 'malformed'], true)) {
                return null;
            }
        }
        $first = $rows[0];

        return self::SEARCH_FAILED . match ($first['outcome']) {
            'timeout' => 'timed out',
            'http_error' => 'HTTP ' . ($first['status'] ?? '?') . (!empty($first['retry_after']) ? ' (retry after ' . $first['retry_after'] . ' s)' : ''),
            'malformed' => 'unreadable answer',
            default => 'request error',
        };
    }

    /** A Wikimedia SVG: not stored as is, but Wikimedia renders it as a PNG (see preferThumbnail()). */
    private function isWikimediaSvg(string $url): bool
    {
        return str_starts_with($url, 'https://upload.wikimedia.org/') && (bool) preg_match('/\.svg(\?|$)/i', $url);
    }

    private function attempt(string $query, array $excludeUrls, ?string $source, string $purpose, string $teaches = "", string $level = self::LEVEL_EXACT): array
    {
        if ($this->timeUp()) {
            return ['missing' => self::TIME_UP];
        }
        if (!$this->search->available()) {
            return ['missing' => 'image search is disabled'];
        }
        // A related or topic picture is accepted on weaker keyword evidence, so something must LOOK at it: with neither a relevance review nor a
        // picture check there is nothing to stand behind it, and the ladder goes on to the next rung instead.
        if ($level !== self::LEVEL_EXACT && $this->judge === null && $this->pixelJudge === null) {
            return ['missing' => self::NEEDS_CHECK];
        }
        // A generic picture is accepted on NO keyword evidence, so the picture itself must be looked at.
        if ($level === self::LEVEL_GENERIC && $this->pixelJudge === null) {
            return ['missing' => self::NEEDS_CHECK];
        }

        // Several ranked candidates, not just the top one: for abstract topics the
        // best keyword match is often the wrong picture and the right one is third.
        $this->search->searches = [];   // this attempt's searches only, so an earlier scene's failure is never blamed on this one
        $candidates = $this->search->rankedImagesFor($query, self::CANDIDATES, $level === self::LEVEL_EXACT ? $this->minScore : min(1.0, $this->minScore), $source);
        $this->stats['searches']++;
        $searchFailure = $this->searchFailure();
        // The extra sources (NASA for astronomy and Earth scenes, Pexels when a key is configured) are asked once, with the first pass.
        if ($source === null || ($source === '' && $this->skip !== [])) {
            foreach ($this->active as $name) {
                $extra = ($this->providers[$name])($query, self::CANDIDATES);
                $this->stats['searches']++;
                $candidates = array_merge($candidates, $extra);
            }
        }
        if ($this->skip !== []) {
            $candidates = array_values(array_filter($candidates, fn ($c) => !in_array(strtolower((string) ($c['provider'] ?? 'openverse')), $this->skip, true)
                && !(in_array('wikimedia', $this->skip, true) && str_contains((string) ($c['url'] ?? ''), 'upload.wikimedia.org'))));
        }
        $this->stats['candidates'] += count($candidates);
        foreach ($candidates as $c) {
            $this->tally((string) ($c['provider'] ?? 'openverse'), 'candidates');
        }
        if (!$candidates) {
            // A search that could not be made says nothing about whether a picture exists; only an answer with no usable result does.
            return ['missing' => $searchFailure ?? 'no sufficiently relevant, openly licensed image found'];
        }

        $usable = [];
        $why = [];
        foreach ($candidates as $image) {
            $match = $this->matchEvidence($query, $image);
            if (in_array($image['url'], $excludeUrls, true)) {
                $why[] = 'already used on another slide';
                $this->stats['rejected_duplicate']++;
                $this->event($image, 'rejected', 'already used on another scene');
            } elseif (!$this->licenceAccepted((string) ($image['license'] ?? ''))) {
                $why[] = 'licence "' . ($image['license'] ?? 'unknown') . '" is not accepted';
                $this->stats['rejected_licence']++;
                $this->event($image, 'rejected', 'licence not accepted');
            } elseif (!$this->isWikimediaSvg((string) ($image['url'] ?? '')) && preg_match('/\.(svg|tiff?|pdf|djvu|ogv|webm|ogg|gif)(\?|$)/i', (string) ($image['url'] ?? ''))) {
                // The picture library stores PNG, JPEG and WebP: a candidate in another format would only fail at the download.
                // (A Wikimedia SVG is the exception: it is fetched as the PNG Wikimedia renders from it, and the download still checks the type.)
                $why[] = 'image format is not one the library stores';
                $this->stats['rejected_format']++;
                $this->event($image, 'rejected', 'format the library does not store');
            } elseif (!$this->titleIsDescriptive((string) ($image['title'] ?? ''))) {
                $why[] = 'image title "' . ($image['title'] ?? '') . '" does not say what the picture shows';
                $this->stats['rejected_title']++;
                $this->event($image, 'rejected', 'its title does not say what it shows');
            } elseif ($level === self::LEVEL_GENERIC ? false : ($level === self::LEVEL_EXACT ? ($match["shared"] < min(2, max(1, $match["of"])) || $match["shared"] / max(1, $match["of"]) < 0.4) : count(array_diff($match['terms'], self::GENERIC)) < 1)) {
                $why[] = 'image title and tags do not match enough of the query (' . $match['shared'] . ' of ' . $match['of'] . ' words)';
                $this->stats['rejected_title']++;
                $this->event($image, 'rejected', 'title and tags match only ' . $match['shared'] . ' of ' . $match['of'] . ' query words');
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
            $verdict = ($this->judge)($query, $this->purposeFor($level, $purpose), $usable, $teaches);
            $order = array_values(array_filter((array) ($verdict['accepted'] ?? []), fn ($i) => isset($usable[$i])));
            $this->stats['rejected_text_review'] += count($usable) - count($order);
            foreach ($usable as $k => $u) {
                if (!in_array($k, $order, true)) {
                    $this->event($u, 'rejected', 'strict relevance review: ' . trim((string) ($verdict['reasons'][$k] ?? $verdict['reason'] ?? 'does not fit the scene')));
                }
            }
            if (!$order) {
                return ['missing' => 'rejected on relevance review: ' . ($verdict['reason'] ?? 'none of the candidates fits')];
            }
        }

        $lastMissing = 'image could not be downloaded';
        foreach ($order as $i) {
            if ($this->timeUp()) {
                return ['missing' => self::TIME_UP];
            }
            $image = $usable[$i];
            $match = $image['_match'];

            $needsCredit = (bool) preg_match(self::NEEDS_CREDIT, (string) $image['license']);
            $credit = $this->credit($image);
            if ($needsCredit && $credit === '') {
                $lastMissing = 'licence requires attribution but none was supplied';
                continue; // cannot be shown lawfully without a credit; try the next
            }

            $file = ($this->download)((string) $image['url']);
            $originalFailure = $this->lastDownloadFailure;
            // Openverse's thumbnail proxy cannot fetch Wikimedia files (it answers HTTP 424), so for Wikimedia the original upload address is the only source and its own failure is the one reported.
            $viaProxy = !empty($image['thumbnail_url']) && $image['thumbnail_url'] !== $image['url'] && ((string) ($image['provider'] ?? '')) !== 'wikimedia' && !str_contains((string) $image['url'], 'upload.wikimedia.org');
            if ($file === null && $viaProxy) {
                $file = ($this->download)((string) $image['thumbnail_url']);
                if ($file === null) {
                    $this->lastDownloadFailure = trim('original: ' . $originalFailure . '; thumbnail proxy: ' . $this->lastDownloadFailure);
                }
            } elseif ($file === null) {
                $this->lastDownloadFailure = $originalFailure;
            }
            if ($file === null) {
                $this->stats['download_failed']++;
                $this->event($image, 'download failed', $this->lastDownloadFailure ?: 'no answer');
                continue;
            }
            // A strip or a thumbnail teaches little: the picture has to be big enough, and not a long thin band.
            $short = min($file['width'], $file['height']);
            if ($short < 240 || max($file['width'], $file['height']) / max(1, $short) > 2.6) {
                $lastMissing = 'the picture is too small or too narrow to learn from (' . $file['width'] . 'x' . $file['height'] . ')';
                $this->stats['rejected_shape'] = ($this->stats['rejected_shape'] ?? 0) + 1;
                $this->event($image, 'rejected', 'too small or too narrow: ' . $file['width'] . 'x' . $file['height']);
                continue;
            }
            // The relevance review reads a picture's title and tags, not the picture. One that is out of focus or
            // empty teaches nothing whatever it is called, so it is refused here and the next candidate is tried.
            if (!self::hasDetail($file['bytes'])) {
                $lastMissing = 'the picture is out of focus or has no detail to learn from';
                $this->stats['rejected_detail']++;
                $this->event($image, 'rejected', 'out of focus or no detail');
                continue;
            }
            // The title and tags can be wrong about the picture ("Vernier-coat of arms" for a vernier caliper). When a pixel check is set it
            // looks at the picture itself, and only a picture that really shows what the scene needs is kept.
            $seen = null;
            if ($this->pixelJudge && $this->timeUp()) {
                return ['missing' => self::TIME_UP];
            }
            if ($this->pixelJudge) {
                $seen = ($this->pixelJudge)($file['bytes'], (string) $file['mime'], $query, $this->purposeFor($level, $purpose), $teaches);
                if (!empty($seen['unverified'])) {
                    $this->stats['unverified'] = ($this->stats['unverified'] ?? 0) + 1;
                    $this->event($image, 'unverified', (string) $seen['reason']);

                    return ['missing' => 'the picture could not be checked: ' . $seen['reason']];   // every further candidate would be unchecked too
                }
                if (empty($seen['ok'])) {
                    $lastMissing = 'the picture itself does not fit the scene: ' . trim((string) ($seen['reason'] ?? 'it shows something else'));
                    $this->stats['rejected_pixels']++;
                    $this->event($image, 'rejected', 'picture check: ' . trim((string) ($seen['reason'] ?? 'shows something else')));
                    continue;
                }
            }
            $this->stats['accepted']++;
            $this->event($image, 'ACCEPTED', trim((string) ($seen['shows'] ?? '')));
            $this->tally((string) ($image['provider'] ?? 'openverse'), 'accepted');

            return [
                'type' => 'photo',
                // How close this picture is to the scene (see the ladder in StoryPictureCover), and the address the bytes were really fetched from,
                // so a later run can reuse them from the store without searching again.
                // What the picture check found on the picture to open (see StoryHotspots); only the exact and related levels keep it.
                'parts' => (array) ($seen['parts'] ?? []),
                'match_level' => $level,
                'fetched_from' => (string) ($file['source'] ?? $image['url']),
                // Written AFTER the choice. With a pixel check it is what the picture was seen to show; without one, what the picture is
                // called - never what the plan hoped it would show.
                'alt' => $seen !== null && trim((string) ($seen['shows'] ?? '')) !== '' ? 'Photograph: ' . rtrim(trim((string) $seen['shows']), '. ') . '.' : $this->altFor($image),
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
                ...($seen !== null && !empty($seen['focus']) ? ['focus' => $seen['focus']] : []),
                ...($seen !== null && trim((string) ($seen['notice'] ?? '')) !== '' ? ['notice' => trim((string) $seen['notice'])] : []),
                ...($seen !== null && !empty($seen['focus']) && trim((string) ($seen['label'] ?? '')) !== '' ? ['focus_label' => trim((string) $seen['label'])] : []),
                'review' => $seen !== null
                    ? ['relevant' => true, 'reason' => 'Checked by looking at the picture: ' . trim((string) ($seen['reason'] ?? 'it shows what the scene needs')), 'pixel_checked' => true]
                    : ($this->judge ? ['relevant' => true, 'reason' => (string) ($verdict['reasons'][$i] ?? '')] : null),
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
        if ($licence === self::TEXTBOOK_LICENCE) {
            return true;
        }
        // Two sources whose terms are not Creative Commons labels, accepted by their exact names (see NasaImageSearch and PexelsImageSearch).
        if (in_array($licence, [\App\Services\PAL\Integration\NasaImageSearch::LICENCE, \App\Services\PAL\Integration\PexelsImageSearch::LICENCE, self::UNSPLASH_LICENCE], true)) {
            return true;
        }
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
        if ($file === null && $preferred !== $url && !preg_match('/\.(tiff?|svg)(\?|$)/i', $url)) {   // a TIFF or SVG original is not a picture the library can keep
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
    /** Why the last download returned nothing (an HTTP status, a size or type problem, a time out), for the report. */
    public string $lastDownloadFailure = '';

    private function fetchOne(string $url, int $retries): ?array
    {
        $this->lastDownloadFailure = '';
        // A picture downloaded for this school before is in the database already; nothing is kept in a folder.
        if ($this->store instanceof SourceImageCache && ($hit = $this->store->cached($url)) !== null) {
            return $hit + ['source' => $url];
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        try {
            $this->blockedUntil[$host] = max((int) ($this->blockedUntil[$host] ?? 0), (int) \Illuminate\Support\Facades\Cache::get('imageplanner.blocked.' . $host, 0));
        } catch (\Throwable) {
        }
        if (($this->blockedUntil[$host] ?? 0) > time()) {
            $this->lastDownloadFailure = $host . ' asked not to be called again for a while';

            return null;
        }

        try {
            $response = null;
            for ($attempt = 0; $attempt <= $retries; $attempt++) {
                $this->pace();
                $response = Http::withUserAgent($this->userAgent())
                    ->timeout(25)->get($url);
                if (!in_array($response->status(), [429, 503], true)) {
                    break;
                }
                $wait = (int) $response->header('Retry-After');
                // In a time-boxed pass a host that says "slow down" is left alone (for what it asked, at least two minutes) instead of waited on.
                if ($wait > 60) {
                    try {
                        \Illuminate\Support\Facades\Cache::put('imageplanner.blocked.' . $host, time() + $wait, $wait);
                    } catch (\Throwable) {
                    }
                    $this->blockedUntil[$host] = time() + $wait;
                    $this->lastDownloadFailure = $host . ' answered ' . $response->status() . ' and asked for a wait of ' . $wait . ' s';

                    return null;
                }
                // Never wait past the time limit: a wait that would end after it is not made.
                $nap = min(30, max($wait, 4 * ($attempt + 1)));
                if ($attempt < $retries && ($this->deadline === null || microtime(true) + $nap + 5 < $this->deadline)) {
                    sleep($nap);
                } elseif ($attempt < $retries) {
                    $this->lastDownloadFailure = $host . ' answered ' . $response->status() . ' and there was no time left to wait';

                    return null;
                }
            }
            if ($response && $response->status() === 429 && $this->deadline !== null) {
                // Still limited after the short waits: leave the host alone for a minute so the other candidates fail at once.
                try {
                    \Illuminate\Support\Facades\Cache::put('imageplanner.blocked.' . $host, time() + 60, 60);
                } catch (\Throwable) {
                }
                $this->blockedUntil[$host] = time() + 60;
            }
            if (!$response || !$response->successful()) {
                $this->lastDownloadFailure = $host . ' answered HTTP ' . ($response ? $response->status() : 'nothing') . ($response && $response->status() === 429 ? ' (rate limited, no Retry-After)' : '');

                return null;
            }
            $bytes = $response->body();
            if ($bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
                $this->lastDownloadFailure = $bytes === '' ? 'an empty answer' : 'the picture is larger than 8 MB (' . round(strlen($bytes) / 1048576, 1) . ' MB)';

                return null;
            }
            $info = @getimagesizefromstring($bytes);
            if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $this->lastDownloadFailure = 'the file is not a JPEG, PNG or WebP picture (' . ($info['mime'] ?? 'unreadable') . ')';

                return null;
            }

            return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1], 'source' => $url];
        } catch (\Throwable $e) {
            $this->lastDownloadFailure = $host . ': ' . mb_substr($e->getMessage(), 0, 90);

            return null;
        }
    }

    /** The configured User-Agent (it carries the contact Wikimedia's policy asks for); the earlier fixed string when none is set. */
    private function userAgent(): string
    {
        try {
            $configured = trim((string) config('pal_content.image.user_agent', ''));
        } catch (\Throwable) {
            $configured = '';
        }

        return $configured !== '' ? $configured : 'EduERP-StudyDeck/1.0 (educational content; openly licensed images only)';
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
        // Openverse appends tracking parameters to Wikimedia addresses (?utm_source=...); the thumbnail address has none.
        $bare = preg_replace('/\?.*$/', '', $url);
        if (preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons)/([0-9a-f]/[0-9a-f]{2})/([^/]+\.(jpe?g|png|tiff?|svg))$#i', (string) $bare, $m)) {
            // A TIFF (common for micrographs) can only be had as a rendered copy, which Wikimedia serves as a JPEG; an SVG as a PNG.
            $ext = strtolower($m[4]);

            return $m[1] . '/thumb/' . $m[2] . '/' . $m[3] . '/960px-' . $m[3] . (str_starts_with($ext, 'tif') ? '.jpg' : ($ext === 'svg' ? '.png' : ''));
        }

        return $url;
    }
}
