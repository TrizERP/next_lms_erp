<?php

namespace App\Services\PAL\Coherence;

use App\Services\PAL\Coherence\Concerns\TokenisesTitles;
use Illuminate\Support\Facades\DB;

/**
 * Links content and questions to concepts - the join without which the
 * coherence map has a spine but nothing hanging off it.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NEEDED AT ALL
 * ---------------------------------------------------------------------------
 * `content_master.concept_id` and `lms_question_master.concept_id` exist and
 * are dead: measured 2026-08-18, Class 7 Maths has 339 content rows and 492
 * questions with ZERO distinct concept_id between them, and across the whole
 * estate `pal_content_metadata.concept_ref_id` was populated on 0 rows. Chapter
 * is the only join key that actually carries. So concepts are inferred from the
 * chapter they share, narrowed by name overlap.
 *
 * ---------------------------------------------------------------------------
 * CONTENT LAW C5 - PROPOSALS ONLY
 * ---------------------------------------------------------------------------
 * Every write here lands with `tagged_by='derived'`, a `confidence` float and
 * `quality_status='draft'`, exactly as pal:tag-content does. This class cannot
 * approve anything. A concept link is a claim about what a question measures,
 * and a wrong one corrupts mastery for every learner who answers it - so the
 * bar for it entering the live map is a human, not a token count.
 *
 * Rows it cannot decide are LEFT ALONE and counted. Guessing would hide the
 * coverage hole; reporting it is what gets the content fixed.
 */
class ConceptTagger
{
    use TokenisesTitles;

    /** Below this overlap the match is noise, not signal. */
    private const MIN_CONFIDENCE = 0.34;

    public function __construct(private readonly ChapterAligner $aligner)
    {
    }

    /** Words that carry no subject meaning and would inflate every overlap score. */
    private const CONTENT_STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with',
        'is', 'are', 'was', 'were', 'be', 'by', 'as', 'at', 'from', 'that',
        'this', 'it', 'its', 'which', 'what', 'how', 'why', 'when', 'introduction',
        'chapter', 'class', 'notes', 'video', 'pdf', 'ppt', 'activity', 'worksheet',
        'part', 'lesson', 'topic', 'concept', 'exercise', 'question', 'answer',
    ];

    /**
     * Propose concept links for content in one standard+subject.
     *
     * @return array{scanned: int, tagged: int, ambiguous: int, no_concepts: int, samples: array}
     */
    public function tagContent(int $tenant, int $standardId, int $subjectId, bool $dryRun = false, bool $chapterFallback = false): array
    {
        $byChapter = $this->conceptsByChapter($tenant, $standardId, $subjectId);

        $rows = DB::table('content_master')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->get(['id', 'chapter_id', 'title', 'description', 'meta_tags']);

        return $this->tagEstate(
            $rows,
            $byChapter,
            fn ($r) => trim(($r->title ?? '') . ' ' . ($r->meta_tags ?? '') . ' ' . strip_tags((string) ($r->description ?? ''))),
            fn (int $id, int $conceptId, float $confidence) => $this->writeContentLink($tenant, $id, $conceptId, $confidence),
            $dryRun,
            $chapterFallback
        );
    }

    /**
     * Propose concept links for questions in one standard+subject.
     *
     * `concept` and `subconcept` on lms_question_master are free-text columns
     * that are empty on the Class 7 estate but populated elsewhere - they are
     * included in the match text because where they DO exist they are the
     * strongest signal available.
     *
     * @return array{scanned: int, tagged: int, ambiguous: int, no_concepts: int, samples: array}
     */
    public function tagQuestions(int $tenant, int $standardId, int $subjectId, bool $dryRun = false, bool $chapterFallback = false): array
    {
        $byChapter = $this->conceptsByChapter($tenant, $standardId, $subjectId);

        $rows = DB::table('lms_question_master')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->get(['id', 'chapter_id', 'question_title', 'concept', 'subconcept', 'learning_outcome']);

        return $this->tagEstate(
            $rows,
            $byChapter,
            fn ($r) => trim(
                ($r->concept ?? '') . ' ' . ($r->subconcept ?? '') . ' '
                . ($r->learning_outcome ?? '') . ' ' . strip_tags((string) ($r->question_title ?? ''))
            ),
            fn (int $id, int $conceptId, float $confidence) => $this->writeQuestionLink($tenant, $id, $conceptId, $confidence),
            $dryRun,
            $chapterFallback
        );
    }

    // ==================================================================

    /**
     * Shared body: for each row, narrow to its chapter's concepts, then rank by
     * name overlap.
     *
     * @param  iterable<object>  $rows
     * @param  array<int, array<int, array{id: int, name: string, tokens: array<int, string>}>>  $byChapter
     */
    private function tagEstate(
        iterable $rows,
        array $byChapter,
        callable $textOf,
        callable $write,
        bool $dryRun,
        bool $chapterFallback
    ): array {
        $scanned = 0;
        $tagged = 0;
        $ambiguous = 0;
        $noConcepts = 0;
        $samples = [];

        foreach ($rows as $row) {
            $scanned++;

            $candidates = $byChapter[(int) $row->chapter_id] ?? [];

            // The chapter itself is not in the map. This is the Class 7 case:
            // 15 of 23 chapter ids exist in no table and no CSV, so nothing can
            // be inferred and saying so is the useful output.
            if ($candidates === []) {
                $noConcepts++;
                continue;
            }

            $tokens = $this->tokenise((string) $textOf($row));

            $best = null;
            $bestScore = 0.0;

            foreach ($candidates as $concept) {
                $score = $this->overlap($tokens, $concept['tokens']);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $concept;
                }
            }

            // Exactly one concept in the chapter: the chapter link alone is
            // enough to be useful, at chapter-level confidence rather than
            // name-match confidence.
            if ($best === null && count($candidates) === 1) {
                $best = $candidates[0];
                $bestScore = 0.5;
            }

            if ($best === null || $bestScore < self::MIN_CONFIDENCE) {
                if (! $chapterFallback) {
                    $ambiguous++;
                    continue;
                }

                // Opt-in last resort: attach to the chapter's highest-priority
                // concept at a confidence that makes clear it is a placeholder.
                $best = $candidates[0];
                $bestScore = 0.2;
            }

            if (count($samples) < 10) {
                $samples[] = [
                    'row'        => (int) $row->id,
                    'concept'    => $best['name'],
                    'confidence' => round($bestScore, 3),
                ];
            }

            if (! $dryRun) {
                $write((int) $row->id, $best['id'], $bestScore);
            }

            $tagged++;
        }

        return [
            'scanned'     => $scanned,
            'tagged'      => $tagged,
            'ambiguous'   => $ambiguous,
            'no_concepts' => $noConcepts,
            'samples'     => $samples,
        ];
    }

    /**
     * Concepts grouped by chapter, pre-tokenised so the inner loop is a set
     * intersection rather than a string split per comparison.
     *
     * Ordered by authored priority so `--chapter-fallback` attaches to the
     * concept most likely to be the chapter's spine.
     *
     * @return array<int, array<int, array{id: int, name: string, tokens: array<int, string>}>>
     */
    private function conceptsByChapter(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('lms_concept as c')
            ->leftJoin('pal_concept_metadata as m', function ($join) use ($tenant) {
                $join->on('m.concept_ref_id', '=', 'c.id')
                    ->whereIn('m.sub_institute_id', [$tenant, 0]);
            })
            ->where('c.sub_institute_id', $tenant)
            ->where('c.standard_id', $standardId)
            ->where('c.subject_id', $subjectId)
            ->orderByDesc('m.priority_score')
            ->orderBy('c.id')
            ->get(['c.id', 'c.name', 'c.chapter_id']);

        $byChapter = [];

        foreach ($rows as $r) {
            $byChapter[(int) $r->chapter_id][] = [
                'id'     => (int) $r->id,
                'name'   => (string) $r->name,
                'tokens' => $this->tokenise((string) $r->name),
            ];
        }

        // Expand through the chapter alignment. Content and questions point at
        // the OLD chapter ids (Class 9 Maths: 934-948) while concepts were
        // extracted against the NEW ones (8594-8603), so without this every
        // lookup misses and the tagger reports 100% no_concepts - which is
        // exactly what it did before the aligner existed.
        foreach ($this->aligner->lookup($tenant, $standardId, $subjectId) as $estateId => $conceptChapterId) {
            if (isset($byChapter[$estateId]) || ! isset($byChapter[$conceptChapterId])) {
                continue;
            }

            $byChapter[(int) $estateId] = $byChapter[$conceptChapterId];
        }

        return $byChapter;
    }

    private function writeContentLink(int $tenant, int $contentId, int $conceptId, float $confidence): void
    {
        DB::table('pal_content_metadata')->updateOrInsert(
            ['content_master_id' => $contentId, 'sub_institute_id' => $tenant],
            [
                'concept_ref_id' => $conceptId,
                'confidence'     => round($confidence, 3),
                'tagged_by'      => 'derived',
                'quality_status' => 'draft',
                'updated_at'     => now(),
                'created_at'     => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    private function writeQuestionLink(int $tenant, int $questionId, int $conceptId, float $confidence): void
    {
        DB::table('pal_question_metadata')->updateOrInsert(
            ['question_id' => $questionId, 'sub_institute_id' => $tenant],
            [
                'concept_ref_id' => $conceptId,
                'confidence'     => round($confidence, 3),
                'tagged_by'      => 'derived',
                'quality_status' => 'draft',
                'updated_at'     => now(),
                'created_at'     => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    /**
     * Containment rather than Jaccard.
     *
     * A concept name is short ("Density of rational numbers") and the text it is
     * matched against is long (a whole question stem). Jaccard would punish that
     * length difference and reject correct matches; what actually matters is how
     * much of the CONCEPT NAME appears in the text.
     *
     * @param  array<int, string>  $text
     * @param  array<int, string>  $concept
     */
    private function overlap(array $text, array $concept): float
    {
        if ($concept === [] || $text === []) {
            return 0.0;
        }

        $hits = count(array_intersect($concept, $text));

        return $hits / count($concept);
    }

    /**
     * Content and question titles are padded with FORMAT nouns ("Revision
     * Notes.pdf", "Classroom Activity"), not with narration - so this list
     * differs from ChapterAligner's even though the tokeniser is shared.
     *
     * @return array<int, string>
     */
    protected function stopwords(): array
    {
        return self::CONTENT_STOPWORDS;
    }
}
