<?php

namespace Tests\Unit;

use App\Services\Eso\Video\ConceptVideoRelevanceScorer;
use PHPUnit\Framework\TestCase;

/**
 * The scorer decides whether a struggling student gets a video that addresses
 * their gap, a video that merely shares a chapter with it, or nothing at all.
 *
 * Every fixture below is REAL: the ten rows are chapter 1014's actual video
 * titles and filenames from the live estate, and the seventeen concepts are
 * that chapter's actual lms_concept names. Synthetic fixtures would not have
 * caught the two cases that matter most — the junk export artefacts
 * ("audio3.mp4", "METALS.mp4") sitting alongside genuinely good content.
 */
class ConceptVideoRelevanceScorerTest extends TestCase
{
    protected ConceptVideoRelevanceScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new ConceptVideoRelevanceScorer();
    }

    /** Chapter 1014's real video rows. */
    protected function chapter1014(): array
    {
        $rows = [
            [37750, 'Extraction of metals - overview  Metals and non metals  Chemistry  Khan Academy.mp4'],
            [37751, 'Metals and Nonmetals Chemical Properties - Part 2  Don_t Memorise.mp4'],
            [37752, 'Reaction of metals with water  Class 10  Chemistry  ICSE Board  Home Revise.mp4'],
            [37753, 'Metals reacting with water  Metals and Non metals  Chemistry  Khan Academy.mp4'],
            [37754, 'Metal and Non Metal  Non Metal d.mp4'],
            [37755, 'Metals and Non Metals Video   Properties and Uses  What are metals and non metals.mp4'],
            [37756, 'audio3.mp4'],
            [37757, 'audio2.mp4'],
            [37758, 'audio explanation1.mp4'],
            [37759, 'METALS.mp4'],
        ];

        return array_map(static fn (array $r) => [
            'id' => $r[0],
            'title' => $r[1],
            'filename' => rawurlencode($r[1]),
            'description' => null,
            'meta_tags' => null,
            'sort_order' => null,
        ], $rows);
    }

    /** Chapter 1014's real concept names. */
    protected function concepts(): array
    {
        return [
            'Physical Properties of Metals', 'Physical Properties of Non-metals',
            'Malleability and Ductility', 'Chemical Reactivity of Metals', 'Amphoteric Oxides',
            'Reactivity Series of Metals', 'Ionic Compound Formation', 'Properties of Ionic Compounds',
            'Minerals, Ores, and Gangue', 'Metallurgy', 'Extraction of Metals',
            'Roasting and Calcination', 'Electrolytic Refining', 'Corrosion', 'Rusting of Iron',
            'Prevention of Corrosion', 'Alloys',
        ];
    }

    protected function rank(string $concept): array
    {
        return $this->scorer->rank($concept, $this->chapter1014(), [
            'min_score' => 0.55,
            'small_pool_score' => 0.65,
            'small_pool_size' => 3,
        ]);
    }

    public function test_the_matching_video_wins_for_extraction_of_metals(): void
    {
        $ranked = $this->rank('Extraction of Metals');

        $this->assertNotEmpty($ranked, 'Extraction of Metals has an obviously matching video');
        $this->assertSame(37750, $ranked[0]['id']);
        $this->assertGreaterThanOrEqual(0.55, $ranked[0]['match_score']);
    }

    /**
     * The gate that stops one generic clip becoming the answer to everything.
     * "METALS.mp4" matches only the token every video in a metals chapter
     * matches, so it must never be served for any concept.
     */
    public function test_a_generic_chapter_wide_filename_never_wins_for_any_concept(): void
    {
        foreach ($this->concepts() as $concept) {
            foreach ($this->rank($concept) as $row) {
                $this->assertNotSame(
                    37759,
                    $row['id'],
                    "METALS.mp4 was offered for '{$concept}' — it matches only chapter-wide vocabulary"
                );
            }
        }
    }

    /** Export artefacts carry no teaching signal and must never be served. */
    public function test_junk_filenames_are_never_served_for_any_concept(): void
    {
        $junk = [37756, 37757, 37758];

        foreach ($this->concepts() as $concept) {
            foreach ($this->rank($concept) as $row) {
                $this->assertNotContains(
                    $row['id'],
                    $junk,
                    "A junk audio artefact was offered for '{$concept}'"
                );
            }
        }
    }

    /**
     * The case that justifies the whole external tier: a real concept in a
     * chapter that does have videos, none of which is about it. Serving the
     * chapter's best-scoring clip here would waste the student's time.
     */
    public function test_a_concept_with_no_relevant_video_gets_nothing(): void
    {
        $this->assertSame([], $this->rank('Amphoteric Oxides'));
        $this->assertSame([], $this->rank('Roasting and Calcination'));
        $this->assertSame([], $this->rank('Electrolytic Refining'));
    }

    /**
     * Guards the common-prefix matcher. Strict prefix matching relates neither
     * reaction/reactivity nor chemistry/chemical, and this concept's best
     * video would score below the gate without it.
     */
    public function test_chemical_reactivity_matches_a_reaction_video(): void
    {
        $ranked = $this->rank('Chemical Reactivity of Metals');

        $this->assertNotEmpty($ranked);
        $this->assertContains(
            $ranked[0]['id'],
            [37752, 37753],
            'Expected one of the two "metals reacting with water" videos'
        );
    }

    /** "ion"/"ionic" and "oxide"/"oxidation" are near-misses that must not match. */
    public function test_short_token_overlap_does_not_create_a_false_match(): void
    {
        $candidates = [
            ['id' => 1, 'title' => 'Ionic bonding explained', 'filename' => 'ionic.mp4'],
            ['id' => 2, 'title' => 'Oxidation and reduction', 'filename' => 'redox.mp4'],
        ];

        $ranked = $this->scorer->rank('Amphoteric Oxides', $candidates, [
            'min_score' => 0.55, 'small_pool_score' => 0.65, 'small_pool_size' => 3,
        ]);

        $this->assertSame([], $ranked, 'oxide must not match oxidation');
    }

    /** A concept name made only of stopwords gives nothing to match on. */
    public function test_an_unmatchable_concept_name_returns_nothing(): void
    {
        $this->assertSame([], $this->scorer->rank('The Introduction', $this->chapter1014()));
    }

    public function test_an_empty_candidate_pool_returns_nothing(): void
    {
        $this->assertSame([], $this->scorer->rank('Corrosion', []));
    }

    /**
     * With one or two videos, chapter-local IDF measures nothing, so the bar
     * rises and a title match becomes mandatory.
     */
    public function test_a_small_pool_demands_a_title_match(): void
    {
        $opts = ['min_score' => 0.55, 'small_pool_score' => 0.65, 'small_pool_size' => 3];

        $weak = $this->scorer->rank('Prevention of Corrosion', [
            ['id' => 1, 'title' => 'Chapter walkthrough', 'filename' => 'corrosion-notes.mp4'],
        ], $opts);
        $this->assertSame([], $weak, 'a filename-only match must not clear the small-pool bar');

        $strong = $this->scorer->rank('Prevention of Corrosion', [
            ['id' => 2, 'title' => 'Prevention of corrosion in iron', 'filename' => 'a.mp4'],
        ], $opts);
        $this->assertNotEmpty($strong);
        $this->assertSame(2, $strong[0]['id']);
    }

    /**
     * Search results share the concept's vocabulary because the search worked,
     * not because they are generic. Scoring them with the chapter rules drives
     * every token's IDF to zero and rejects the whole set — which is exactly
     * what happened against the live API before `pool_mode` existed.
     */
    public function test_search_results_are_not_judged_by_chapter_rules(): void
    {
        $results = [
            ['id' => 1, 'title' => 'Amphoteric Oxides–Definition, Examples, Explanation in 5 min | Class 10 Science'],
            ['id' => 2, 'title' => 'Topic- Amphoteric oxide (Metals & non-metals) Class-10 Chemistry'],
            ['id' => 3, 'title' => 'What are Amphoteric oxides || How are amphoteric oxides related to metalloids'],
        ];

        $asChapter = $this->scorer->rank('Amphoteric Oxides', $results, ['min_score' => 0.55]);
        $this->assertSame([], $asChapter, 'chapter rules reject a search result set outright');

        $asSearch = $this->scorer->rank('Amphoteric Oxides', $results, [
            'min_score' => 0.55,
            'pool_mode' => 'search',
        ]);
        $this->assertCount(3, $asSearch);
        $this->assertEqualsWithDelta(1.0, $asSearch[0]['match_score'], 0.001);
    }

    /**
     * "Multiples of a number" reduces to the single token {multiples}, and
     * "multiples"/"multiply" share seven leading characters — so the
     * approximate matcher scored a video about MULTIPLICATION at a perfect
     * 1.00 for a concept about MULTIPLES. With one token carrying the whole
     * verdict, the match has to be exact.
     */
    public function test_a_single_token_concept_requires_an_exact_match(): void
    {
        $opts = ['min_score' => 0.55, 'pool_mode' => 'search'];

        $wrong = $this->scorer->rank('Multiples of a number', [
            ['id' => 1, 'title' => 'How to Add, Subtract, Multiply, and Divide Integers'],
        ], $opts);
        $this->assertSame([], $wrong, 'a multiplication video must not answer "multiples"');

        $right = $this->scorer->rank('Multiples of a number', [
            ['id' => 2, 'title' => 'Multiples | Common Multiples | Maths'],
        ], $opts);
        $this->assertNotEmpty($right);
        $this->assertSame(2, $right[0]['id']);
    }

    /**
     * The approximation still earns its keep where more than one token votes:
     * "Chemical Reactivity of Metals" is best served by a video about metals
     * REACTING, and neither reaction/reactivity nor chemistry/chemical is a
     * prefix of the other.
     */
    public function test_multi_token_concepts_keep_the_approximate_matcher(): void
    {
        $ranked = $this->scorer->rank('Chemical Reactivity of Metals', [
            ['id' => 1, 'title' => 'Reaction of metals with water | Chemistry'],
        ], ['min_score' => 0.55, 'pool_mode' => 'search']);

        $this->assertNotEmpty($ranked);
    }

    /** Search mode still rejects drift — a result must actually name the concept. */
    public function test_search_mode_still_rejects_an_off_topic_result(): void
    {
        $ranked = $this->scorer->rank('Amphoteric Oxides', [
            ['id' => 1, 'title' => 'Industrial corrosion protection for pipelines'],
            ['id' => 2, 'title' => 'Top 10 study tips for exams'],
        ], ['min_score' => 0.55, 'pool_mode' => 'search']);

        $this->assertSame([], $ranked);
    }

    /** Ranking must be stable — rankOffset walks these positions with no stored state. */
    public function test_ranking_is_deterministic(): void
    {
        $first = array_column($this->rank('Chemical Reactivity of Metals'), 'id');

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($first, array_column($this->rank('Chemical Reactivity of Metals'), 'id'));
        }
    }

    public function test_every_result_carries_a_reason_for_the_reviewer(): void
    {
        foreach ($this->rank('Extraction of Metals') as $row) {
            $this->assertNotEmpty($row['match_reason']);
            $this->assertLessThanOrEqual(255, mb_strlen($row['match_reason']));
        }
    }
}
