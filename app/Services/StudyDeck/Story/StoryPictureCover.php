<?php

namespace App\Services\StudyDeck\Story;

use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\StudyDeckImages;

/**
 * The picture ladder that gives every scene and ending of a story a picture of its own (when the run asks for picture coverage).
 *
 * The scenes the normal picture step already found an EXACT picture for are left alone. Each of the others goes down this ladder and stops at the
 * first rung that gives a picture:
 *
 *   related      a picture of the same kind of object, activity or environment, found through the same search with relaxed relevance gates
 *   topic        a picture of the wider chapter topic, the same way
 *   reused       a verified picture accepted earlier (this run or an earlier one) that shares at least two meaningful words with the scene
 *   ai_generated an illustration made by an image model, when the run enabled it and the picture check agrees it shows the scene
 *   placeholder  a drawn, labelled stand-in; it never fails, so a scene is never left bare
 *
 * Only the relevance gates are relaxed: the licence, file type, size, sharpness, download and credit rules are the same as for an exact picture, a
 * reused picture keeps its original credit, and an AI illustration carries no licence and no credit and says what made it. Every picture records its
 * `match_level` and has a caption and alt text that say how close it is: nothing but an exact match is ever described as the scene itself.
 *
 * A search that cannot be made (a timeout, a rate limit) is not a verdict on the query: after three in a row the searching rungs are skipped for the
 * rest of the run and the ladder goes straight to reuse, so an outage costs seconds, not a bare scene.
 */
final class StoryPictureCover
{
    public const RELATED = ImagePlanner::LEVEL_RELATED;
    public const TOPIC = ImagePlanner::LEVEL_TOPIC;
    public const REUSED = 'reused';
    /** A picture from the chapter's own textbook: used only after the web search found nothing suitable. */
    public const TEXTBOOK = 'textbook';
    public const AI = 'ai_generated';
    public const GENERIC = ImagePlanner::LEVEL_GENERIC;
    public const PLACEHOLDER = 'placeholder';

    /** The levels whose pictures may carry hotspots: their parts were described for THIS scene's idea. */
    private const HOTSPOT_LEVELS = ['exact', self::RELATED, self::AI];

    /** @var array<string,array<int,string>> scene ref => what each rung did, in order */
    public array $log = [];

    /** @var array<string,int> pictures this pass gave, by rung */
    public array $given = [self::RELATED => 0, self::TOPIC => 0, self::TEXTBOOK => 0, self::REUSED => 0, self::AI => 0, self::GENERIC => 0, self::PLACEHOLDER => 0];

    /** @var array<int,array<string,mixed>> the generic educational pictures this run can use (found now, or kept from an earlier run) */
    private array $genericPool = [];

    /** @var array<string,int> source address => how many scenes it has served as a generic picture */
    private array $genericUses = [];

    /** @var array<int,string> things that went wrong on the way (a search that failed, a picture that was refused) */
    public array $problems = [];

    public int $searches = 0;

    public int $searchFailures = 0;

    private int $failedInARow = 0;

    private bool $searchOut = false;

    /** Searching rungs: how many different queries one scene tries on each of them. */
    private const QUERIES_PER_RUNG = 2;

    public function __construct(
        private readonly ImagePlanner $images,
        private readonly StoryImageLibrary $library,
        private readonly ?SceneIllustrator $ai = null,
        /** A drawn, labelled placeholder is NOT a picture: it is used only when this is asked for (--allow-placeholder). */
        private readonly bool $allowPlaceholder = false,
    ) {
    }

    /**
     * Gather the generic educational pictures the last real rung draws from: pictures kept from an earlier run that can still be brought back, then
     * searches for a clear photograph of a science activity, laboratory or instrument, each looked at by the picture check. Done once, before the scenes,
     * so a run with no way to find a real picture fails at the start and not after hours of writing.
     *
     * @param array{chapter?:string,subject?:string} $ctx
     * @return int how many generic pictures the run now has
     */
    public function prepareGenericPool(array $ctx, int $want = 4, ?float $deadline = null): int
    {
        $this->genericPool = [];
        foreach ($this->library->generic() as $record) {
            if ($this->images->reuse($record) !== null) {
                $this->genericPool[] = $record;
            }
        }
        $subject = trim((string) ($ctx['subject'] ?? '')) ?: 'science';
        // Single, clear objects that read as "science" on their own (a crowd, a building or a museum does not): the picture is only a suitable
        // generic image, so what it shows must be unmistakable. A caller may name its own for another subject.
        $queries = array_values(array_unique(array_filter(array_map([$this, 'clip'], (array) ($ctx['generic_queries'] ?? ['microscope', 'laboratory glassware', 'magnifying glass', 'thermometer', 'telescope'])))));
        $used = array_map(fn ($r) => (string) ($r['source_image_url'] ?? ''), $this->genericPool);
        $this->images->useProviders([]);
        $this->images->skipSources([]);
        foreach ($queries as $q) {
            if (count($this->genericPool) >= $want || ($deadline !== null && microtime(true) >= $deadline) || $this->searchOut) {
                break;
            }
            $this->searches++;
            $found = $this->images->find($q, $used, 'A clear educational picture for a school science lesson', '', ImagePlanner::LEVEL_GENERIC);
            $missing = (string) ($found['missing'] ?? '');
            if ($missing !== '') {
                $this->log['generic'][] = "\"$q\": $missing";
                if (str_starts_with($missing, ImagePlanner::SEARCH_FAILED)) {
                    $this->searchFailures++;
                    if (++$this->failedInARow >= 3) {
                        $this->searchOut = true;
                    }
                } else {
                    $this->failedInARow = 0;
                }
                continue;
            }
            $this->failedInARow = 0;
            $used[] = (string) $found['source_image_url'];
            $record = StoryWriter::photoImage($found, $q);
            $this->library->remember($record, [$subject]);
            $this->genericPool[] = $record;
            $this->log['generic'][] = "\"$q\": " . $found['source_image_url'];
        }

        return count($this->genericPool);
    }

    public function genericPoolSize(): int
    {
        return count($this->genericPool);
    }

    public function library(): StoryImageLibrary
    {
        return $this->library;
    }

    public function illustrator(): ?SceneIllustrator
    {
        return $this->ai;
    }

    /** Keep a picture the normal step found, so a later scene (or run) can reuse it. */
    public function learn(array $record, array $conceptNames = []): void
    {
        $this->library->remember($record, $conceptNames);
    }

    /**
     * @param array<string,mixed> $interaction one act's interaction
     * @param array{key?:string,concepts?:array<int,string>,chapter?:string,subject?:string,topic?:string} $ctx
     * @param array<int,string> $usedUrls source addresses already placed in this lesson (kept up to date)
     * @param array<int,string> $notes what was done, for the report
     * @return array<string,mixed> the interaction, every scene and ending with a picture
     */
    public function cover(array $interaction, array $ctx, array &$usedUrls, array &$notes, ?float $deadline = null): array
    {
        foreach ($interaction['nodes'] as $k => $node) {
            if (!in_array($node['kind'] ?? '', [StoryGraph::SCENE, StoryGraph::ENDING], true) || !empty($node['image']['url'])) {
                continue;
            }
            $ref = (string) ($ctx['key'] ?? '') . '/' . $node['id'];
            $image = $this->coverNode($node, $ref, $ctx, $usedUrls, $deadline);
            if ($image === null) {
                // No real picture could be found and a placeholder was not asked for: the scene stays bare, and the coverage rule says so.
                $notes[] = 'Scene "' . $node['id'] . '": no real picture could be found.';
                $this->problems[] = 'Scene ' . $ref . ': no real picture could be found and a placeholder was not asked for.';
                continue;
            }
            $interaction['nodes'][$k]['image'] = $image;
            unset($interaction['nodes'][$k]['extra_images']);   // a supporting picture needs a main one that was there first
            $notes[] = 'Scene "' . $node['id'] . '": ' . self::describe($image['match_level'] ?? '?') . '.';
        }

        return $interaction;
    }

    /** @return array<string,mixed>|null null only when no real picture was found and a placeholder was not asked for */
    private function coverNode(array $node, string $ref, array $ctx, array &$usedUrls, ?float $deadline): ?array
    {
        $title = trim((string) $node['title']);
        $body = trim((string) ($node['body'] ?? ''));
        $concepts = array_values(array_filter(array_map(fn ($id) => (string) ($ctx['concepts'][$id] ?? ''), (array) ($node['concept_ids'] ?? []))));
        if ($concepts === []) {
            $concepts = array_slice(array_values(array_filter((array) ($ctx['act_concepts'] ?? []))), 0, 3);   // the node names none: the act's ideas
        }
        $words = StoryImageLibrary::words($title . ' ' . implode(' ', array_slice(preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 40)) . ' ' . implode(' ', $concepts));
        $purpose = trim($title . '. ' . implode(' ', array_slice(preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 30)));
        $teaches = implode('; ', array_slice($concepts, 0, 3));
        $late = fn (): bool => $deadline !== null && microtime(true) >= $deadline;

        // 1 and 2: the same search, wider questions.
        if (!$this->searchOut && !$late()) {
            $this->images->useProviders([]);
            $this->images->skipSources([]);
            foreach ([self::RELATED => $this->relatedQueries($title, $concepts, $ctx), self::TOPIC => $this->topicQueries($concepts, $ctx)] as $level => $queries) {
                foreach (array_slice($queries, 0, self::QUERIES_PER_RUNG) as $q) {
                    if (($why = $this->library->missed($q)) !== null) {
                        $this->log[$ref][] = "$level \"$q\": skipped, found nothing before ($why)";
                        continue;
                    }
                    if ($late()) {
                        $this->log[$ref][] = "$level: stopped by the time limit";
                        break 2;
                    }
                    $this->searches++;
                    $found = $this->images->find($q, $usedUrls, $purpose, $teaches, $level);
                    $missing = (string) ($found['missing'] ?? '');
                    if ($missing === ImagePlanner::TIME_UP) {
                        $this->log[$ref][] = "$level \"$q\": stopped by the time limit";
                        break 2;
                    }
                    if ($missing !== '') {
                        $this->log[$ref][] = "$level \"$q\": $missing";
                        if (str_starts_with($missing, ImagePlanner::SEARCH_FAILED)) {
                            $this->searchFailures++;
                            if (++$this->failedInARow >= 3) {
                                $this->searchOut = true;
                                $this->problems[] = 'Picture search is failing (' . $missing . '); the remaining scenes use earlier pictures, an illustration or a placeholder.';
                            }
                            break 2;   // the provider, not the query: go to the next rung instead of asking again
                        }
                        $this->failedInARow = 0;
                        $this->library->miss($q, $missing);
                        continue;
                    }
                    $this->failedInARow = 0;
                    $usedUrls[] = (string) $found['source_image_url'];
                    $record = StoryWriter::photoImage($found, $q);
                    $this->library->remember($record, $concepts);

                    return $this->label($record, $level, $title, $ref);
                }
            }
        }

        // 2b: the chapter's own textbook, when the web search found nothing suitable (it comes before any picture reused, generated or generic).
        if (($book = $this->images->textbookPicture($title, $purpose, $teaches)) !== null) {
            $usedUrls[] = (string) $book['source_image_url'];
            $this->log[$ref][] = 'textbook: ' . ($book['source_url'] ?? '?');

            return $this->label(['role' => 'scene'] + $book, self::TEXTBOOK, $title, $ref);
        }

        // 3: a verified picture accepted before, when it is related.
        foreach ($this->library->related($words, $usedUrls) as $candidate) {
            $reused = $this->images->reuse($candidate);
            if ($reused === null) {
                $this->log[$ref][] = 'reused: a related picture could not be brought back (' . ($candidate['source_image_url'] ?? '?') . ')';
                continue;
            }
            $this->library->used((string) $candidate['source_image_url']);

            return $this->label(['type' => 'photo', 'role' => 'scene'] + $reused, self::REUSED, $title, $ref);
        }

        // 4: an illustration made for the scene, when enabled.
        if ($this->ai !== null && ($ai = $this->illustrate($title, $body, $concepts, $purpose, $teaches, $ref, $late())) !== null) {
            return $ai;
        }

        // 5: a generic educational picture for the subject (a real photograph; not about this scene, and says so).
        if (($generic = $this->genericFor($words, $title, $ref)) !== null) {
            return $generic;
        }

        // 6: the labelled placeholder, only when asked for.
        if (!$this->allowPlaceholder) {
            $this->log[$ref][] = 'no real picture was available';

            return null;
        }

        return $this->label($this->images->placeholder($title, $concepts), self::PLACEHOLDER, $title, $ref);
    }

    /**
     * The generic picture that has served the fewest scenes (the one that shares most words with the scene when they tie), brought back into the store.
     *
     * @param array<int,string> $words
     * @return array<string,mixed>|null
     */
    private function genericFor(array $words, string $title, string $ref): ?array
    {
        $pool = $this->genericPool;
        usort($pool, function (array $a, array $b) use ($words): int {
            $ua = $this->genericUses[(string) ($a['source_image_url'] ?? '')] ?? 0;
            $ub = $this->genericUses[(string) ($b['source_image_url'] ?? '')] ?? 0;
            $wa = count(array_intersect($words, StoryImageLibrary::words((string) ($a['title'] ?? '') . ' ' . (string) ($a['query'] ?? ''))));
            $wb = count(array_intersect($words, StoryImageLibrary::words((string) ($b['title'] ?? '') . ' ' . (string) ($b['query'] ?? ''))));

            return [$ua, $wb] <=> [$ub, $wa];
        });
        foreach ($pool as $record) {
            $back = $this->images->reuse($record);
            if ($back === null) {
                continue;
            }
            $key = (string) ($record['source_image_url'] ?? '');
            $this->genericUses[$key] = ($this->genericUses[$key] ?? 0) + 1;

            return $this->label(['type' => 'photo', 'role' => 'scene'] + $back, self::GENERIC, $title, $ref);
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function illustrate(string $title, string $body, array $concepts, string $purpose, string $teaches, string $ref, bool $late): ?array
    {
        $prompt = SceneIllustrator::prompt($title, $body, $concepts);
        $hash = sha1($prompt);

        // The same scene text was illustrated before: bring that picture back instead of paying for another.
        if (($rec = $this->library->generatedFor($hash)) !== null && ($again = $this->images->reuse($rec)) !== null) {
            $this->given[self::AI]++;
            $this->log[$ref][] = 'ai_generated: the picture made for this scene earlier was reused';

            return $again;
        }
        if ($late || !$this->ai->available()) {
            $this->log[$ref][] = 'ai_generated: ' . ($late ? 'stopped by the time limit' : 'not available' . ($this->ai->unavailable !== null ? ' (' . $this->ai->unavailable . ')' : ''));

            return null;
        }
        $made = $this->ai->illustrate($prompt);
        if ($made === null) {
            $this->log[$ref][] = 'ai_generated: no picture came back' . ($this->ai->unavailable !== null ? ' (' . $this->ai->unavailable . ')' : '');
            $this->problems[] = 'Scene ' . $ref . ': the image model gave no picture.';

            return null;
        }
        $check = $this->images->checkIllustration($made['bytes'], $made['mime'], $title, $purpose, $teaches);
        if ($check !== null && empty($check['ok'])) {
            $why = !empty($check['unverified']) ? 'the picture check could not run' : trim((string) ($check['reason'] ?? 'it does not show the scene'));
            $this->log[$ref][] = 'ai_generated: the illustration was refused: ' . $why;
            $this->problems[] = 'Scene ' . $ref . ': an AI illustration was refused (' . $why . ').';

            return null;
        }
        try {
            $shows = trim((string) ($check['shows'] ?? ''));
            $record = $this->images->generated($made['bytes'], $made['mime'], [
                'kind' => 'ai_illustration', 'model' => $made['model'], 'prompt' => $prompt, 'query' => $title,
                'alt' => 'AI-generated illustration of this scene' . ($shows !== '' ? ': ' . rtrim($shows, '. ') : '') . '. Not a photograph.',
                'caption' => 'AI-generated illustration of this scene, not a photograph.' . (!empty($check['notice']) ? ' ' . $check['notice'] : ''),
                'review' => $check !== null ? 'checked by looking at the picture: ' . trim((string) ($check['reason'] ?? 'it shows the scene')) : 'generated from the scene text; no picture check was available',
                'pixel_checked' => $check !== null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->log[$ref][] = 'ai_generated: ' . $e->getMessage();
            $this->problems[] = 'Scene ' . $ref . ': ' . $e->getMessage() . '.';

            return null;
        }
        if (($parts = StoryHotspots::spots((array) ($check['parts'] ?? []))) !== []) {
            $record['hotspots'] = $parts;
        }
        if (!empty($check['focus'])) {
            $record['focus'] = $check['focus'];
            if (!empty($check['label'])) {
                $record['focus_label'] = $check['label'];
            }
        }
        $this->library->rememberGenerated($hash, $record);
        $this->given[self::AI]++;
        $this->log[$ref][] = 'ai_generated: an illustration was made and accepted';

        return $record;
    }

    /**
     * Say how close the picture is: in the record, in its caption and in its alt text. Nothing but an exact match is described as the scene.
     *
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function label(array $record, string $level, string $title, string $ref): array
    {
        $this->given[$level]++;
        $record['match_level'] = $level;
        $record['role'] = $record['role'] ?? 'scene';
        if (!in_array($level, self::HOTSPOT_LEVELS, true)) {
            unset($record['hotspots']);   // a topic, reused, generic or placeholder picture's parts are not about this scene
        }
        $shown = trim((string) ($record['caption'] ?? '')) !== '' ? trim((string) $record['caption']) : ($record['title'] ?? $title);
        $shown = rtrim((string) $shown, '. ');
        $prefix = match ($level) {
            self::RELATED => 'Related picture, not this exact scene',
            self::TOPIC => 'Picture of the wider topic, not this exact scene',
            self::REUSED => 'A related picture also used earlier in this lesson, not this exact scene',
            self::TEXTBOOK => 'Picture from the textbook, about the same idea',
            self::GENERIC => 'Generic picture for this topic, not this exact scene',
            default => null,
        };
        if ($prefix !== null) {
            $record['caption'] = $prefix . ($shown !== '' ? ': ' . $shown : '') . '.';
            $record['alt'] = $prefix . '. ' . trim((string) ($record['alt'] ?? ''));
            $record['match_note'] = $prefix;
        }
        $this->log[$ref][] = $level . ': ' . ($record['source_image_url'] ?? $record['layout'] ?? 'kept');

        return $record;
    }

    /**
     * Queries for a picture of the same KIND of thing: the idea the scene teaches first, then the scene's own key words.
     *
     * @return array<int,string>
     */
    private function relatedQueries(string $title, array $concepts, array $ctx): array
    {
        $out = [];
        foreach (array_slice($concepts, 0, 2) as $c) {
            $out[] = $this->clip($c);
        }
        if ($out === []) {
            // No idea to search for: the scene's own key words are all there is (a generic setting such as "school gate" is a weak query, used last).
            $out[] = $this->clip(implode(' ', array_slice(StoryImageLibrary::words($title), 0, 3)));
        }

        return array_values(array_unique(array_filter($out, fn ($q) => $q !== '')));
    }

    /** @return array<int,string> */
    private function topicQueries(array $concepts, array $ctx): array
    {
        $out = [];
        foreach ([(string) ($ctx['topic'] ?? ''), trim((string) ($ctx['chapter'] ?? ''))] as $t) {
            $out[] = $this->clip($t);
        }
        if (($ctx['subject'] ?? '') !== '' && $concepts !== []) {
            $out[] = $this->clip($ctx['subject'] . ' ' . $concepts[0]);
        }

        return array_values(array_unique(array_filter($out, fn ($q) => $q !== '')));
    }

    /** A search phrase: a few meaningful words, no digits. */
    private function clip(string $text): string
    {
        $words = array_values(array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn ($w) => mb_strlen($w) >= 3 && !in_array($w, ['the', 'and', 'for', 'with', 'from', 'into', 'how', 'why', 'what'], true)));

        return implode(' ', array_slice($words, 0, 4));
    }

    public static function describe(string $level): string
    {
        return match ($level) {
            'exact' => 'an exact picture',
            self::RELATED => 'a related picture (not this exact scene)',
            self::TOPIC => 'a picture of the wider topic',
            self::REUSED => 'a related picture reused from earlier in the lesson',
            self::TEXTBOOK => 'a picture from the chapter textbook',
            self::GENERIC => 'a generic educational picture for the subject',
            self::AI => 'an AI-generated illustration',
            self::PLACEHOLDER => 'a labelled placeholder',
            default => 'a picture',
        };
    }

    // -----------------------------------------------------------------------------------------------------
    // What the finished lesson holds

    /**
     * Counts over a finished story: how many nodes of each kind, how many have a picture of their own, how many are bare, and what each picture is
     * (exact, related, topic, reused, AI-generated, placeholder, or a drawn diagram of the older kind). Read from the nodes themselves, so it is
     * what the player will show, not what the pass meant to do.
     *
     * @param array<int,array<string,mixed>> $interactions every act's interaction
     * @return array<string,mixed>
     */
    public static function summarise(array $interactions): array
    {
        $kinds = ['scene' => 0, 'decision' => 0, 'ending' => 0];
        $own = ['scene' => 0, 'decision' => 0, 'ending' => 0];
        $levels = ['exact' => 0, self::RELATED => 0, self::TOPIC => 0, self::TEXTBOOK => 0, self::REUSED => 0, self::AI => 0, self::GENERIC => 0, self::PLACEHOLDER => 0, 'drawn_diagram' => 0];
        $withHotspots = 0;
        $hotspotParts = 0;
        $bare = [];
        $uses = [];
        $extras = 0;
        foreach ($interactions as $i => $interaction) {
            $label = (string) ($interaction['key'] ?? ('act' . ($i + 1)));
            foreach ((array) ($interaction['nodes'] ?? []) as $n) {
                $kind = (string) ($n['kind'] ?? '');
                if (!isset($kinds[$kind])) {
                    continue;
                }
                $kinds[$kind]++;
                $img = $n['image'] ?? null;
                if (is_array($img) && trim((string) ($img['url'] ?? '')) !== '') {
                    $own[$kind]++;
                    if (!empty($img['hotspots']) && is_array($img['hotspots'])) {
                        $withHotspots++;
                        $hotspotParts += count($img['hotspots']);
                    }
                    $level = (string) ($img['match_level'] ?? '');
                    $levels[isset($levels[$level]) ? $level : (($img['type'] ?? '') === 'photo' ? 'exact' : 'drawn_diagram')]++;
                    if (($img['match_level'] ?? '') !== self::PLACEHOLDER) {
                        $uses[(string) ($img['sha256'] ?? $img['url'])] = ($uses[(string) ($img['sha256'] ?? $img['url'])] ?? 0) + 1;
                    }
                }
                $extras += count(array_filter((array) ($n['extra_images'] ?? []), 'is_array'));
            }
            foreach (StoryGraph::bareNodes($interaction) as $id => $why) {
                $bare[$label . '/' . $id] = $why;
            }
        }
        $needing = $kinds['scene'] + $kinds['ending'];
        $covered = $own['scene'] + $own['ending'];

        return [
            'nodes' => array_sum($kinds),
            'by_kind' => $kinds,
            'with_own_picture' => $own,
            'scenes_and_endings' => $needing,
            'scenes_and_endings_with_picture' => $covered,
            'coverage_percent' => $needing === 0 ? 100.0 : round(100 * $covered / $needing, 1),
            'would_render_bare' => $bare,
            'by_match_level' => $levels,
            'supporting_pictures' => $extras,
            'scenes_with_hotspots' => $withHotspots,
            'hotspot_parts' => $hotspotParts,
            'pictures_used_more_than_allowed' => count(array_filter($uses, fn ($n) => $n > StoryImageLibrary::MAX_USES)),
        ];
    }

    /**
     * Read every picture of a finished lesson back from the store and check it: the stored bytes are the bytes (checksum and size read in slices),
     * the type is one the player shows, and the size is the size the deck says. Nothing is trusted from the moment of writing.
     *
     * @param array<int,array<string,mixed>> $interactions every act's interaction
     * @return array{checked:int,failures:array<int,string>,unverifiable:int}
     */
    public static function verify(array $interactions, StudyDeckImages $images): array
    {
        $checked = 0;
        $unverifiable = 0;
        $failures = [];
        foreach ($interactions as $i => $interaction) {
            $label = (string) ($interaction['key'] ?? ('act' . ($i + 1)));
            foreach ((array) ($interaction['nodes'] ?? []) as $n) {
                foreach (array_merge([$n['image'] ?? null], (array) ($n['extra_images'] ?? [])) as $img) {
                    if (!is_array($img) || trim((string) ($img['url'] ?? '')) === '') {
                        continue;
                    }
                    $where = $label . '/' . ($n['id'] ?? '?');
                    $id = StudyDeckImages::idFromRef($img['url']);
                    if ($id === null) {
                        $unverifiable++;   // an address, not a stored picture: nothing here to read back
                        continue;
                    }
                    $checked++;
                    $meta = $images->meta($id);
                    if ($meta === null) {
                        $failures[] = "$where: picture $id is not in the store";
                    } elseif (!in_array($meta['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
                        $failures[] = "$where: picture $id is {$meta['mime']}, which the player does not show";
                    } elseif ((int) ($img['width'] ?? 0) !== $meta['width'] || (int) ($img['height'] ?? 0) !== $meta['height']) {
                        $failures[] = "$where: picture $id is {$meta['width']}x{$meta['height']}, the deck says " . (int) ($img['width'] ?? 0) . 'x' . (int) ($img['height'] ?? 0);
                    } elseif (!$images->verify($id, $meta['sha256'], $meta['bytes'])) {
                        $failures[] = "$where: picture $id could not be read back intact";
                    }
                }
            }
        }

        return ['checked' => $checked, 'failures' => $failures, 'unverifiable' => $unverifiable];
    }
}
