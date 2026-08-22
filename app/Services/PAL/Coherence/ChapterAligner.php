<?php

namespace App\Services\PAL\Coherence;

use App\Services\Neo4jService;
use App\Services\PAL\Coherence\Concerns\TokenisesTitles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pairs the chapter ids the CONTENT ESTATE uses with the chapter ids the
 * CONCEPT LAYER uses, by name.
 *
 * ---------------------------------------------------------------------------
 * WHERE THE NAMES COME FROM
 * ---------------------------------------------------------------------------
 * Concept-side names are in `chapter_master` (8594 "The World of Numbers").
 * Estate-side names are NOT: chapter ids 934-948 exist in no MariaDB table at
 * all. They survive only as :Chapter nodes in Neo4j, seeded from the 2026-08-10
 * rescue export. So this matcher reads one side from SQL and the other from the
 * graph - which is also why it lives in the Coherence namespace rather than
 * being a query somewhere.
 *
 * ---------------------------------------------------------------------------
 * MATCHING, AND ITS LIMITS
 * ---------------------------------------------------------------------------
 * Symmetric token containment after stemming, which handles the shapes that
 * actually occur here:
 *
 *   "POLYNOMIALS"    -> "Introduction to Linear Polynomials"   0.50
 *   "NUMBER SYSTEMS" -> "The World of Numbers"                 0.50
 *   "Triangle"       -> "A Tale of Three Intersecting Lines"   0.00  (correctly no match)
 *
 * The third case is the point: renamed-beyond-recognition chapters are LEFT
 * UNPAIRED and reported, because a wrong pairing routes a learner's answers
 * into the wrong concept's mastery and is worse than a gap. Everything written
 * lands `status='proposed'` for a human to confirm.
 */
class ChapterAligner
{
    use TokenisesTitles;

    /** Below this, a pairing is coincidence. */
    private const MIN_CONFIDENCE = 0.34;

    /**
     * Grammatical words, plus the DECORATIVE vocabulary the 2024 NCERT titles
     * are written in. "Orienting Yourself: The Use of Coordinates" carries one
     * load-bearing token; the rest is narration, and leaving it in inflates the
     * denominator until a correct pair scores below the floor. Measured: with
     * 'use' and 'world' counted, 936 "Co-ordinate Geometry" scored 0.333
     * against 8594 and was rejected by a hundredth.
     *
     * Only words that are decorative in ANY title belong here. Subject words
     * ("point", "line", "number") must never be added, however common - they
     * are exactly what distinguishes one chapter from another.
     */
    private const TITLE_STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with',
        'is', 'are', 'its', 'introduction', 'chapter', 'class', 'part', 'unit',
        'using', 'about', 'around', 'us', 'you', 'your', 'yourself', 'what',
        'exploring', 'understanding', 'basic', 'basics',
        'use', 'used', 'uses', 'world', 'way', 'ways', 'more', 'some', 'other',
        'others', 'new', 'know', 'knowing', 'let', 'lets', 'look', 'looking',
        'doing', 'make', 'making', 'work', 'working',
    ];

    public function __construct(private readonly Neo4jService $neo4j)
    {
    }

    /**
     * Propose alignments for one standard+subject.
     *
     * @return array{
     *     estate_chapters: int, concept_chapters: int,
     *     matched: int, unmatched: int, written: int,
     *     pairs: array<int, array<string, mixed>>,
     *     unmatched_names: array<int, string>
     * }
     */
    public function align(int $tenant, int $standardId, int $subjectId, bool $dryRun = false): array
    {
        $conceptChapters = $this->conceptChapters($tenant, $standardId, $subjectId);
        $estateChapters = $this->estateChapters($tenant, $standardId, $subjectId, array_keys($conceptChapters));

        $pairs = [];
        $unmatched = [];

        foreach ($estateChapters as $estateId => $estateName) {
            $best = null;
            $bestScore = 0.0;

            $estateTokens = $this->tokenise($estateName);

            foreach ($conceptChapters as $conceptId => $conceptName) {
                $score = $this->similarity($estateTokens, $this->tokenise($conceptName));

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = ['id' => $conceptId, 'name' => $conceptName];
                }
            }

            if ($best === null || $bestScore < self::MIN_CONFIDENCE) {
                $unmatched[$estateId] = $estateName;
                continue;
            }

            $pairs[] = [
                'estate_chapter_id'    => $estateId,
                'estate_chapter_name'  => $estateName,
                'concept_chapter_id'   => $best['id'],
                'concept_chapter_name' => $best['name'],
                'confidence'           => round($bestScore, 3),
            ];
        }

        $written = $dryRun ? 0 : $this->persist($pairs, $tenant, $standardId, $subjectId);

        return [
            'estate_chapters'  => count($estateChapters),
            'concept_chapters' => count($conceptChapters),
            'matched'          => count($pairs),
            'unmatched'        => count($unmatched),
            'written'          => $written,
            'pairs'            => $pairs,
            'unmatched_names'  => $unmatched,
        ];
    }

    /**
     * The alignment map a tagger consumes: estate chapter id -> concept chapter id.
     *
     * Rejected pairs are excluded - that status is a human veto and a re-run
     * must never route content through a pairing somebody has already refused.
     *
     * @return array<int, int>
     */
    public function lookup(int $tenant, int $standardId, int $subjectId): array
    {
        return DB::table('pal_chapter_alignment')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->whereIn('status', ['proposed', 'approved'])
            ->orderByDesc('confidence')
            ->pluck('concept_chapter_id', 'estate_chapter_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // ==================================================================

    /**
     * Chapters that HAVE concepts - the target side. Names from chapter_master.
     *
     * @return array<int, string>
     */
    private function conceptChapters(int $tenant, int $standardId, int $subjectId): array
    {
        $ids = DB::table('lms_concept')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->distinct()
            ->pluck('chapter_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($ids === []) {
            return [];
        }

        $named = DB::table('chapter_master')
            ->whereIn('id', $ids)
            ->pluck('chapter_name', 'id')
            ->map(fn ($v) => (string) $v)
            ->all();

        // A concept chapter missing from chapter_master still needs a name, or
        // it can never be matched. Fall back to the graph, same as the estate side.
        $missing = array_values(array_diff($ids, array_keys($named)));

        return $named + ($missing === [] ? [] : $this->namesFromGraph($missing));
    }

    /**
     * Chapters that content or questions point at but which carry NO concepts -
     * the source side. Their names live only in Neo4j.
     *
     * @param  int[]  $conceptChapterIds
     * @return array<int, string>
     */
    private function estateChapters(int $tenant, int $standardId, int $subjectId, array $conceptChapterIds): array
    {
        $fromContent = DB::table('content_master')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->distinct()
            ->pluck('chapter_id');

        $fromQuestions = DB::table('lms_question_master')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->distinct()
            ->pluck('chapter_id');

        $ids = $fromContent->merge($fromQuestions)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            // A chapter that already has concepts needs no alignment - content
            // on it joins directly.
            ->reject(fn ($id) => in_array($id, $conceptChapterIds, true))
            ->values()
            ->all();

        return $ids === [] ? [] : $this->namesFromGraph($ids);
    }

    /**
     * Chapter names from Neo4j.
     *
     * `chId` is stored as an INTEGER on the legacy nodes and a STRING on the
     * migration-loaded ones - the same type split that let both twins exist
     * despite a uniqueness constraint. Both forms are passed so a name is found
     * whichever twin survives.
     *
     * @param  int[]  $ids
     * @return array<int, string>
     */
    private function namesFromGraph(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            $result = $this->neo4j->run(
                'MATCH (c:Chapter) '
                . 'WHERE c.chId IN $intIds OR c.chId IN $strIds '
                . 'WITH toInteger(c.chId) AS id, c.chapter_name AS name '
                . 'WHERE name IS NOT NULL AND name <> "" '
                . 'RETURN id, head(collect(name)) AS name',
                [
                    'intIds' => array_values(array_map('intval', $ids)),
                    'strIds' => array_values(array_map('strval', $ids)),
                ]
            );
        } catch (Throwable $e) {
            Log::error('Chapter alignment could not read names from Neo4j', ['error' => $e->getMessage()]);

            return [];
        }

        $names = [];

        foreach ($result as $record) {
            $names[(int) $record->get('id')] = (string) $record->get('name');
        }

        return $names;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pairs
     */
    private function persist(array $pairs, int $tenant, int $standardId, int $subjectId): int
    {
        $written = 0;

        foreach ($pairs as $pair) {
            // updateOrInsert on the unique pair: a re-run refreshes confidence
            // and names without disturbing a status a reviewer has already set.
            $existing = DB::table('pal_chapter_alignment')
                ->where('estate_chapter_id', $pair['estate_chapter_id'])
                ->where('concept_chapter_id', $pair['concept_chapter_id'])
                ->where('sub_institute_id', $tenant)
                ->first(['id', 'status']);

            if ($existing !== null && $existing->status === 'rejected') {
                continue;
            }

            DB::table('pal_chapter_alignment')->updateOrInsert(
                [
                    'estate_chapter_id'  => $pair['estate_chapter_id'],
                    'concept_chapter_id' => $pair['concept_chapter_id'],
                    'sub_institute_id'   => $tenant,
                ],
                [
                    'estate_chapter_name'  => $pair['estate_chapter_name'],
                    'concept_chapter_name' => $pair['concept_chapter_name'],
                    'standard_id'          => $standardId,
                    'subject_id'           => $subjectId,
                    'confidence'           => $pair['confidence'],
                    'matched_by'           => 'name',
                    'status'               => $existing->status ?? 'proposed',
                    'updated_at'           => now(),
                    'created_at'           => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );

            $written++;
        }

        return $written;
    }

    /**
     * Symmetric containment: the better of "how much of A is in B" and "how
     * much of B is in A".
     *
     * Asymmetric containment alone would score a one-word chapter ("Triangle")
     * against a long one at 1.0 on a single incidental hit. Taking the max of
     * both directions keeps short-vs-long pairs matchable while still requiring
     * the shared tokens to be a real proportion of one of the two names.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $shared = count(array_intersect($a, $b));

        if ($shared === 0) {
            return 0.0;
        }

        return max($shared / count($a), $shared / count($b));
    }


    /**
     * Chapter titles are padded with narration ("Orienting Yourself", "The
     * World of"), so the decorative list is longer here than for content.
     *
     * @return array<int, string>
     */
    protected function stopwords(): array
    {
        return self::TITLE_STOPWORDS;
    }
}
