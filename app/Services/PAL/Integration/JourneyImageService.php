<?php

namespace App\Services\PAL\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The image-based "Your Journey" map: one openly-licensed picture per journey
 * stage, searched live and built entirely from this learner's own subject,
 * chapter and concept.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS A SEPARATE SERVICE, AND NOT A LOOP IN THE FRONTEND
 * ---------------------------------------------------------------------------
 * `ConceptImageSearchService` answers "what does the web have for THIS
 * concept". A journey map needs a different question, asked ten times: "what
 * does the web have for this concept AS A CHAPTER DIAGNOSTIC, as a PLAN, as
 * PRACTICE". The subject/chapter/concept vocabulary is identical across the
 * ten — only the intent differs. So this service owns the intent and delegates
 * every actual search, ranking, license check and URL verification to
 * `ConceptImageSearchService`, which is not forked or duplicated here.
 *
 * ---------------------------------------------------------------------------
 * WHAT MAKES IT DYNAMIC RATHER THAN A SET OF PICTURES
 * ---------------------------------------------------------------------------
 * Every query is assembled at request time from three real sources:
 *
 *   1. `config('pal_content.journey_image.stages.*')` — the stage words. The
 *      only authored vocabulary in the whole feature, and it describes what a
 *      step IS ("a marked worksheet", "flashcards"), never a subject, chapter
 *      or concept. Nothing here is subject-specific, so a school that adds
 *      Physics tomorrow gets a working map with no code and no config change.
 *   2. `lms_concept.name` / `chapter_master.chapter_name` — the anchor that
 *      makes one chapter's map differ from another's.
 *   3. `subject.subject_name` — one word of disambiguator, because two
 *      chapters called "Light" in two subjects must not get the same pictures.
 *
 * No image URL is ever stored or hardcoded, and nothing is chosen by hand per
 * chapter. A different subject and chapter produce different queries, which
 * produce different pictures, because the only fixed part of the query is the
 * stage.
 *
 * ---------------------------------------------------------------------------
 * WHY A STAGE MAY REUSE THE CHAPTER'S OWN IMAGE
 * ---------------------------------------------------------------------------
 * `ConceptImageSearchService` sets a deliberately conservative `min_score`
 * (3.0 — see config/pal_content.php) because a confidently wrong picture is
 * worse than none. Applied to ten stage-specific queries per learner, most
 * chapters would render an almost entirely empty map: the search is honest
 * about what it knows, but a journey made of nine empty tiles is not a
 * journey. So each stage falls back to the chapter/concept's own searched
 * image when its own stage-specific queries find nothing usable. The map is
 * then always complete and always honest about the subject, and only the
 * stage-specific nodes — the ones that found their own picture — claim to
 * depict their step. The frontend renders `query` next to each node, so which
 * of the two happened is visible rather than implied.
 *
 * ---------------------------------------------------------------------------
 * SAME TRUST BOUNDARY AS THE LEARN PAGE IMAGE, AND FOR THE SAME REASON
 * ---------------------------------------------------------------------------
 * No review gate, nothing persisted (see `ConceptImageSearchService`'s class
 * note). These are illustrative pictures for a chapter a teacher already put
 * in front of students through the syllabus, carrying real license and
 * attribution metadata, picked and served in one request.
 */
class JourneyImageService
{
    /**
     * The ten stages, in `JOURNEY_STAGES` order on the frontend.
     *
     * Duplicated here rather than read from the frontend because this is a
     * server-side loop: the payload is an ordered map keyed by stage, and a
     * stage missing from config must still appear in the response (with a
     * null image) so the map never renders a gap in the learner's journey.
     */
    public const STAGES = [
        'diagnostic',
        'adaptive',
        'plan',
        'learn',
        'practice',
        'feedback',
        'check',
        'intervention',
        'mastery',
        'recall',
    ];

    /** Words with no search value — stripped before anything reaches Openverse. */
    protected const STOPWORDS = [
        'a', 'an', 'the', 'as', 'of', 'is', 'are', 'was', 'were', 'with', 'to',
        'in', 'on', 'for', 'and', 'or', 'using', 'use', 'by', 'from', 'this',
        'that', 'these', 'those', 'into', 'than', 'then', 'its', 'their',
    ];

    public function available(): bool
    {
        return (bool) config('pal_content.image.enabled', true)
            && (bool) config('pal_content.image.external.enabled', true)
            && (bool) config('pal_content.journey_image.enabled', true);
    }

    /**
     * The whole map for one (chapter, concept) pair, cached as a single
     * payload.
     *
     * Either argument alone is enough. A concept resolves its own chapter and
     * subject; a chapter resolves its own subject. The frontend sends
     * whichever it happens to be holding — the diagnostic exam screen knows
     * only its chapter, a Learn screen knows only its concept — so neither
     * caller has to go and fetch the other's id first.
     *
     * Never throws and never returns null: a chapter with no resolvable name,
     * a disabled provider, a timeout or a provider outage all produce the same
     * well-formed payload with null images, which the frontend renders as a
     * completed journey with icons rather than as an error.
     *
     * @return array<string, mixed>
     */
    public function forContext(?int $chapterId, ?int $conceptId = null): array
    {
        $context = $this->resolveContext($chapterId, $conceptId);

        if ($context['chapter_name'] === null && $context['concept_name'] === null) {
            return $this->payload($context, null, null, []);
        }

        if (! $this->available()) {
            return $this->payload($context, null, null, []);
        }

        $cacheKey = 'pal:journey-images:v1:' . sha1(
            ($context['chapter_name'] ?? '') . '|' . ($context['concept_name'] ?? '') . '|' . ($context['subject_name'] ?? '')
        );

        return Cache::remember(
            $cacheKey,
            now()->addHours((int) config('pal_content.journey_image.cache_hours', 1440)),
            fn () => $this->build($context),
        );
    }

    /**
     * The chapter/concept/subject vocabulary this map is built from, resolved
     * from the same tables `palController::learnConceptImage()` reads.
     *
     * `lms_concept` wins for the concept when it has one, `chapter_master` for
     * the chapter, and `subject`/`standard` follow whichever of the two
     * actually carried a subject id — a concept row with a null `subject_id`
     * falls back to its chapter's rather than dropping the subject and
     * returning a chapter-only query.
     *
     * @return array{concept_name:?string, concept_description:?string, chapter_name:?string, subject_name:?string, standard_name:?string}
     */
    protected function resolveContext(?int $chapterId, ?int $conceptId): array
    {
        $concept = null;

        if ($conceptId !== null && $conceptId > 0) {
            $concept = DB::table('lms_concept')->where('id', $conceptId)->first([
                'id', 'name', 'description', 'chapter_id', 'subject_id', 'standard_id',
            ]);
        }

        // A concept implies its chapter when the caller did not name one.
        $chapterRef = $chapterId !== null && $chapterId > 0
            ? $chapterId
            : (int) ($concept->chapter_id ?? 0);

        $chapter = $chapterRef > 0
            ? DB::table('chapter_master')->where('id', $chapterRef)->first([
                'id', 'chapter_name', 'subject_id', 'standard_id',
            ])
            : null;

        $subjectId = (int) ($concept->subject_id ?? 0) ?: (int) ($chapter->subject_id ?? 0);
        $standardId = (int) ($concept->standard_id ?? 0) ?: (int) ($chapter->standard_id ?? 0);

        return [
            'concept_name' => $this->cleanText($concept->name ?? null),
            'concept_description' => $this->cleanText($concept->description ?? null),
            'chapter_name' => $this->cleanText($chapter->chapter_name ?? null),
            'subject_name' => $subjectId > 0
                ? $this->cleanText(DB::table('subject')->where('id', $subjectId)->value('subject_name'))
                : null,
            'standard_name' => $standardId > 0
                ? $this->cleanText(DB::table('standard')->where('id', $standardId)->value('name'))
                : null,
        ];
    }

    /**
     * Search every stage, within a time and call budget.
     *
     * The chapter's own image is resolved first because it is both the poster
     * of the map (what the learner sees before choosing a node) and the
     * fallback for any stage the budget never reached.
     *
     * The budget is not defensive padding. `bestImageFor()` internally tries
     * up to four query variants, and each one verifies up to three candidate
     * URLs with a HEAD request — so an unbounded loop over ten stages times
     * two terms is up to 240 outbound calls. Every one of them is cached
     * individually afterwards, which is why the SECOND load of a map is a
     * single cache read; it is also why the FIRST load must not be allowed to
     * run unbounded.
     *
     * A stage the budget never reached is not an error and is not a gap: it
     * gets the chapter's image and reports `stage_specific => false`, which
     * the frontend states in words rather than implying otherwise.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function build(array $context): array
    {
        $search = app(ConceptImageSearchService::class);

        // The topic anchor: the concept when there is one (a learner standing
        // on a concept screen is looking at their concept, not their chapter),
        // otherwise the chapter.
        $topic = $context['concept_name'] ?? $context['chapter_name'];

        $baseQuery = $search->queryFor(
            (string) $topic,
            $context['chapter_name'],
            $context['concept_description'],
            $context['subject_name'],
            $context['standard_name'],
        );

        $base = $search->bestImageFor($baseQuery);
        $poster = $base['image'] ?? null;
        $posterQuery = $base['query'] ?? null;

        $stages = [];
        $budget = max(1, (int) config('pal_content.journey_image.build_time_budget_seconds', 8));
        $maxLookups = max(1, (int) config('pal_content.journey_image.max_stage_lookups', 24));
        $configured = (array) config('pal_content.journey_image.stages', []);

        $topicWords = array_values(array_unique(array_filter(array_merge(
            $this->words((string) $topic),
            $this->words((string) ($context['subject_name'] ?? '')),
        ))));

        // The single strongest topic word, used to build the one query that
        // tries to satisfy BOTH requirements at once. See stageCandidates().
        $anchor = $topicWords[0] ?? null;

        $deadline = microtime(true) + $budget;
        $lookups = 0;

        foreach (self::STAGES as $stage) {
            $image = null;
            $query = null;
            $match = null;
            $term = (string) ($configured[$stage][0] ?? '');

            // Spend the budget on this stage only while there is budget left.
            // Out of budget is NOT a reason to skip the stage's entry below —
            // every stage still gets one, holding the chapter's image.
            foreach ($this->stageCandidates($term, $anchor) as $candidate) {
                if ($lookups >= $maxLookups || microtime(true) >= $deadline) {
                    break;
                }

                $lookups++;

                // Exactly one variant per candidate query, so this is the
                // stage's own query or nothing - see the note on $maxVariants in
                // ConceptImageSearchService::bestImageFor().
                //
                // A LOWER floor than the Learn page's 3.0, because this is a
                // different question: the Learn page needs "is this picture
                // about this concept", while a journey node needs "is this
                // picture about this chapter AND showing this step". Neither the
                // generic score nor the corpus filter can answer that, so both
                // are loosened here and replaced by the two gates below.
                $found = $search->bestImageFor(
                    $candidate['query'],
                    1,
                    (float) config('pal_content.journey_image.min_score', 1.0),
                    // Widened off the Learn page's `wikimedia`. "Which step does
                    // this picture show" is answered by a photograph of
                    // flashcards, and Wikimedia holds encyclopedia diagrams
                    // rather than those - verified live: the wikimedia-only
                    // search for "memory flashcards" returns nothing at all.
                    // The gates below are what make the wider corpus safe.
                    (string) config('pal_content.journey_image.source', ''),
                );

                if ($found === null || ! $this->sameQuery($found['query'], $candidate['query'])) {
                    continue;
                }

                if (! $this->depictsStage($found['image'], $term)) {
                    continue;
                }

                if (! $this->depictsTopic($found['image'], $topicWords, $candidate['anchored'])) {
                    continue;
                }

                $image = $found['image'];
                $query = $found['query'];
                $match = $candidate['match'];
                break;
            }

            $stages[$stage] = [
                // Fallback, not a first choice - see the class-level note on
                // why a stage may legitimately reuse the chapter's image.
                'image' => $image ?? $poster,
                'query' => $query,
                'stage_specific' => $image !== null,
                'match' => $match ?? 'chapter',
            ];
        }

        return $this->payload($context, $poster, $posterQuery, $stages);
    }

    /**
     * The queries to try for one stage, best first.
     *
     * ---------------------------------------------------------------------------
     * WHY THERE ARE TWO, AND WHY THE FIRST ONE ALMOST NEVER WINS
     * ---------------------------------------------------------------------------
     * Verified live against Openverse with a real Science chapter:
     *
     *   "exercise worksheet"                -> 12 results (generic worksheets)
     *   "Chemical Reactions Equations
     *    exercise worksheet Science"        ->  0 results
     *   "chemistry worksheet"               ->  1 result
     *
     * Openverse's full-text matching is effectively conjunctive, so every topic
     * word added to the stage words multiplies the cost in recall. A query
     * satisfying both requirements at once is therefore almost always empty,
     * and one satisfying only the stage requirement is almost never on-topic.
     * Neither alone is usable, and pretending either was would be the exact
     * "confidently shows the wrong picture" failure this pipeline exists to
     * avoid.
     *
     * So both are tried, best first:
     *
     *   `stage_and_topic` - one strong topic word plus the stage words. The only
     *     outcome that is genuinely both, so it is worth a lookup. It wins
     *     rarely, and that is the honest state of this corpus rather than a bug
     *     to tune away.
     *   `stage` - the stage words alone, which still has to clear the topic gate
     *     in `depictsTopic()`, so it is never accepted off-topic.
     *
     * The stage returns which one it got, and the frontend says so under the
     * node. A learner can always tell whether they are looking at a picture
     * chosen for this chapter's step or a stand-in for the chapter.
     *
     * @return array<int, array{query:string, match:string, anchored:bool}>
     */
    protected function stageCandidates(string $term, ?string $anchor): array
    {
        $stageQuery = $this->stageQuery($term);
        if ($stageQuery === '') {
            return [];
        }

        $candidates = [];

        if ($anchor !== null && ! str_contains($stageQuery, $anchor)) {
            $candidates[] = [
                'query' => mb_substr($anchor . ' ' . $stageQuery, 0, 150),
                'match' => 'stage_and_topic',
                'anchored' => true,
            ];
        }

        $candidates[] = ['query' => $stageQuery, 'match' => 'stage', 'anchored' => false];

        return $candidates;
    }

    /**
     * Did this stage's picture come from this stage's OWN query?
     *
     * Load-bearing, and the reason this exists rather than a bare `!== null`.
     *
     * `bestImageFor()` finds a result from a narrowed prefix of the query far
     * more often than from the query itself — "Chemical Reactions quiz paper
     * marked Science" routinely finds nothing, while "Chemical Reactions" finds
     * exactly the chapter's own picture. Both are real hits on the same search,
     * and both used to be reported as `stage_specific => true`, which would
     * have told the frontend that a picture of a chemistry apparatus depicts
     * "Chapter diagnostic".
     *
     * It is not. So a result counts as this stage's own only when the query
     * that found it is the complete stage query. Everything else falls back and
     * says so in words under the node.
     */
    protected function sameQuery(?string $found, string $stageQuery): bool
    {
        return $found !== null
            && mb_strtolower(trim($found)) === mb_strtolower(trim($stageQuery));
    }

    /**
     * Does this picture actually DEPICT this stage?
     *
     * The check the generic relevance score cannot make. `rank()` asks whether
     * the query's words co-occur somewhere in a candidate's title, tags or
     * creator - which is a statement about relevance. This asks whether the
     * STAGE's own word is in there, which is a statement about depiction, and
     * it is the only thing that justifies putting a "Practice" label on a
     * node.
     *
     * Verified live against a real chapter: without this gate, every one of the
     * ten nodes accepted whatever the chapter query surfaced, and ten nodes
     * all showing a photograph of a membrane electrode assembly is a map that
     * looks finished and teaches nothing.
     *
     * The stage term's significant words are matched whole, not as substrings -
     * "mark" must not satisfy "marked", and "test" must not satisfy "contest",
     * because those false pairs are exactly the ones that would let an
     * unrelated picture through. Matching against the candidate's title, tags
     * and creator only; never against its URL, which on Wikimedia is a hashed
     * path and would match nothing useful.
     *
     * @param  array<string, mixed>  $image
     */
    protected function depictsStage(array $image, string $term): bool
    {
        if (! (bool) config('pal_content.journey_image.require_stage_word', true)) {
            return true;
        }

        $haystack = $this->imageText($image);

        if ($haystack === '') {
            return false;
        }

        foreach ($this->words($term) as $word) {
            if (mb_strlen($word) > 2 && str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this picture belong to THIS learner's chapter or concept?
     *
     * The other half of the pair with `depictsStage()`, and the reason the
     * chapter name is not in the search text.
     *
     * Searching "exercise worksheet" across an unrestricted index returns
     * worksheets from every subject on earth. This is what stops a maths
     * learner's Practice node from showing a geography worksheet: the chosen
     * picture's own title/tags must contain a word of their chapter, their
     * concept, or their subject.
     *
     * Two words are enough to clear it, matched whole rather than as
     * substrings — "Fractions and Decimals" needs Fractions or Decimals, and a
     * single incidental word match is exactly the false-positive this gate
     * exists to prevent. A chapter whose name is a single word falls back to
     * its subject, which is why the subject's words are folded into the same
     * list: it is the only broad signal available and being on-topic with the
     * subject beats showing nothing.
     *
     * If the learner's chapter, concept and subject names yield no usable word
     * at all, the gate passes — there is nothing to check against, and refusing
     * every picture because a chapter is called "1" would be worse.
     *
     * `$anchored` relaxes the count to ONE for the query that already contained
     * a topic word. Those two checks are not the same: an anchored query's
     * topic word was part of what Openverse matched on, so a single hit
     * corroborates it rather than repeating it, and demanding two would reject
     * a picture whose title names the topic once and whose tags add nothing.
     * An UNANCHORED result has no such corroboration and must clear the full
     * two.
     *
     * @param  array<int, string>  $topicWords
     * @param  array<string, mixed>  $image
     */
    protected function depictsTopic(array $image, array $topicWords, bool $anchored = false): bool
    {
        $significant = array_values(array_filter(
            $topicWords,
            static fn (string $word): bool => mb_strlen($word) > 2
        ));

        if ($significant === []) {
            return true;
        }

        $haystack = $this->imageText($image);
        if ($haystack === '') {
            return false;
        }

        $needed = $anchored ? 1 : 2;
        $hits = 0;

        foreach ($significant as $word) {
            if (str_contains($haystack, $word)) {
                $hits++;
                if ($hits >= $needed) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Title, tags and creator as one lower-cased haystack, minus punctuation. */
    protected function imageText(array $image): string
    {
        $text = implode(' ', array_filter([
            (string) ($image['title'] ?? ''),
            implode(' ', (array) ($image['tags'] ?? [])),
            (string) ($image['creator'] ?? ''),
        ]));

        return mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text)));
    }

    /** Every word of a phrase, lower-cased, punctuation split off. */
    protected function words(string $text): array
    {
        return array_values(array_filter(
            array_map(
                'mb_strtolower',
                preg_split('/[^\p{L}\p{N}]+/u', $text) ?: []
            )
        ));
    }

    /**
     * One stage's search phrase.
     *
     * ---------------------------------------------------------------------------
     * THE STAGE WORD ALONE, AND NOTHING ELSE. THE CHAPTER IS A GATE, NOT A TERM.
     * ---------------------------------------------------------------------------
     * The obvious query is "<chapter> <stage words>" — and it was tried, and it
     * returns almost nothing. Verified live against Openverse for a real
     * chapter: "Chemical Reactions Equations exercise worksheet Science" returns
     * zero results, while "exercise worksheet" returns a full page. Openverse's
     * full-text matching is effectively conjunctive, so every extra term narrows
     * the corpus toward nothing, and a six-word curriculum-anchored query is
     * past the point where anything comes back at all.
     *
     * So the two requirements are separated instead of being crammed into one
     * string and destroying each other:
     *
     *   SEARCH for the stage  -> "exercise worksheet", which finds pictures that
     *                            show an exercise worksheet.
     *   GATE  on the topic   -> `depictsTopic()` requires the chosen picture's
     *                            own title/tags to contain a word from the
     *                            chapter or concept, so a photograph of a
     *                            worksheet that has nothing to do with chemical
     *                            reactions is rejected.
     *
     * Both halves are real filters on the same result set. Putting the chapter
     * into the query text would have been one filter that produced nothing; this
     * is two filters that produce exactly the pictures wanted.
     */
    protected function stageQuery(string $term): string
    {
        // One significant word of the term is enough to aim the search, and two
        // is enough to be specific — but the whole term is kept so the stage-word
        // gate downstream can match against all of it.
        return $this->significantWords($term, 4);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $stages
     * @return array<string, mixed>
     */
    protected function payload(array $context, ?array $poster, ?string $posterQuery, array $stages): array
    {
        $payload = [
            'success' => $poster !== null || $stages !== [],
            'subject' => $context['subject_name'],
            'chapter' => $context['chapter_name'],
            'concept' => $context['concept_name'],
            'standard' => $context['standard_name'],
            'poster' => $poster === null ? null : $this->presentImage($poster),
            'poster_query' => $posterQuery,
            'stages' => [],
        ];

        // Every stage is present in every response, in order, whether or not a
        // picture was found — the map is the learner's journey and must not
        // change shape depending on what a search engine returned today.
        foreach (self::STAGES as $stage) {
            $entry = $stages[$stage] ?? [
                'image' => null,
                'query' => null,
                'stage_specific' => false,
                'match' => 'chapter',
            ];
            $image = $entry['image'] ?? null;

            $payload['stages'][$stage] = [
                'image' => $image === null ? null : $this->presentImage($image),
                'query' => $entry['query'] ?? null,
                'stage_specific' => (bool) ($entry['stage_specific'] ?? false),
                'match' => (string) ($entry['match'] ?? 'chapter'),
            ];
        }

        return $payload;
    }

    /**
     * The externally-consumable shape of one result. The same eight fields
     * `palController::learnConceptImage()` returns, so the frontend has one
     * image type for both the Learn page and the journey map — including the
     * license and attribution the design system requires next to any
     * third-party picture.
     *
     * @param  array<string, mixed>  $image
     * @return array<string, mixed>
     */
    protected function presentImage(array $image): array
    {
        return [
            'url' => $image['url'] ?? null,
            'thumbnail_url' => $image['thumbnail_url'] ?? null,
            'title' => $image['title'] ?? null,
            'source_url' => $image['source_url'] ?? null,
            'creator' => $image['creator'] ?? null,
            'license' => $image['license'] ?? null,
            'attribution' => $image['attribution'] ?? null,
        ];
    }

    /** The first `$max` meaningful words of a real name — never a fixed list. */
    protected function significantWords(string $text, int $max): string
    {
        $kept = [];

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $clean = trim($word, ".,;:!?()[]{}\"'“”‘’");
            if ($clean === '' || in_array(mb_strtolower($clean), self::STOPWORDS, true)) {
                continue;
            }

            $kept[] = $clean;
            if (count($kept) >= $max) {
                break;
            }
        }

        return implode(' ', $kept);
    }

    /** Null for an absent or blank database value, trimmed otherwise. */
    protected function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return $value === '' ? null : $value;
    }
}