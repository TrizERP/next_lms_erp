<?php

namespace App\Services\Remap;

use App\Services\Remap\Support\TextNormalizer as T;
use Illuminate\Support\Facades\DB;

/**
 * Finds orphaned questions that are near-duplicates of questions
 * already sitting on a valid chapter.
 *
 * This is the strongest evidence in the whole pipeline because it needs
 * no model and no semantics: if the same question text already lives on
 * chapter C, the orphaned copy belongs on chapter C. Question banks in
 * this dataset are heavily duplicated across imports, so it fires more
 * often than one might expect.
 *
 * Matching is Sorensen-Dice over character trigrams, pre-filtered by a
 * rare-trigram inverted index to keep it near-linear instead of
 * comparing every orphan against every valid question.
 */
class DuplicateTitleFinder
{
    /** How many of a title's rarest trigrams are indexed. */
    private const PROBE_TRIGRAMS = 6;

    private array $scope;

    /** @var array<string, array<int,true>> trigram => set of valid question ids */
    private array $inverted = [];

    /** @var array<int, array{chapter_id:int, subject_id:int, tri:array}> */
    private array $valid = [];

    /** @var array<string,int> trigram => document frequency */
    private array $trigramDf = [];

    private bool $built = false;

    public function __construct(?array $scope = null)
    {
        $this->scope = $scope ?? config('remap.scope');
    }

    /**
     * Index every std-10 question that already has a live chapter.
     */
    public function build(): void
    {
        if ($this->built) {
            return;
        }

        $rows = DB::table('lms_question_master as q')
            ->join('chapter_master as cm', 'cm.id', '=', 'q.chapter_id')
            ->where('q.sub_institute_id', $this->scope['sub_institute_id'])
            ->where('q.standard_id', $this->scope['standard_id'])
            ->whereNull('q.deleted_at')
            ->get(['q.id', 'q.question_title', 'q.chapter_id', 'q.subject_id']);

        $trigrams = [];

        foreach ($rows as $row) {
            $title = T::flatten($row->question_title);

            // Very short titles produce meaningless trigram overlap.
            if (mb_strlen($title, 'UTF-8') < 25) {
                continue;
            }

            $tri = T::trigrams($row->question_title);
            if (!$tri) {
                continue;
            }

            $id = (int) $row->id;

            $this->valid[$id] = [
                'chapter_id' => (int) $row->chapter_id,
                'subject_id' => (int) $row->subject_id,
                'tri'        => $tri,
            ];

            $trigrams[$id] = $tri;

            foreach (array_keys($tri) as $t) {
                $this->trigramDf[$t] = ($this->trigramDf[$t] ?? 0) + 1;
            }
        }

        // Index each question under only its rarest trigrams: common
        // ones would make the posting lists useless.
        foreach ($trigrams as $id => $tri) {
            foreach ($this->rarest($tri) as $t) {
                $this->inverted[$t][$id] = true;
            }
        }

        $this->built = true;
    }

    public function indexedCount(): int
    {
        return count($this->valid);
    }

    /**
     * Evidence for one legacy group: do its questions have twins that
     * already live on a known chapter, and do those twins agree?
     *
     * @param  array $items question items from LegacyProfileBuilder
     * @return array{chapter_id:?int, subject_id:?int, share:float, matches:int, examined:int, samples:array}
     */
    public function evidenceFor(array $items): array
    {
        $this->build();

        $threshold = (float) config('remap.thresholds.duplicate_dice', 0.90);

        $titles = [];
        foreach ($items as $item) {
            if (($item['source'] ?? null) === 'questions' && !empty($item['title'])) {
                $titles[$item['id']] = $item['title'];
            }
        }

        $votes    = [];
        $samples  = [];
        $matches  = 0;
        $examined = count($titles);

        foreach ($titles as $orphanId => $title) {
            $twin = $this->bestTwin($title, $threshold);

            if ($twin === null) {
                continue;
            }

            $matches++;
            $votes[$twin['chapter_id']] = ($votes[$twin['chapter_id']] ?? 0) + 1;

            if (count($samples) < 5) {
                $samples[] = [
                    'orphan_question_id' => $orphanId,
                    'twin_question_id'   => $twin['id'],
                    'twin_chapter_id'    => $twin['chapter_id'],
                    'dice'               => round($twin['dice'], 3),
                    'title'              => mb_substr(T::flatten($title), 0, 120),
                ];
            }
        }

        if (!$votes || $examined === 0) {
            return ['chapter_id' => null, 'subject_id' => null, 'share' => 0.0, 'matches' => 0, 'examined' => $examined, 'samples' => []];
        }

        arsort($votes);
        $winner = (int) array_key_first($votes);

        return [
            'chapter_id' => $winner,
            'subject_id' => $this->subjectOf($winner),
            // Share of the group's questions that have a twin on the
            // WINNING chapter, not merely any twin.
            'share'      => $votes[$winner] / $examined,
            'matches'    => $matches,
            'examined'   => $examined,
            'samples'    => $samples,
        ];
    }

    /**
     * Best near-duplicate above the Dice threshold, or null.
     */
    private function bestTwin(string $title, float $threshold): ?array
    {
        $tri = T::trigrams($title);
        if (mb_strlen(T::flatten($title), 'UTF-8') < 25 || !$tri) {
            return null;
        }

        $candidates = [];
        foreach ($this->rarest($tri) as $t) {
            foreach (array_keys($this->inverted[$t] ?? []) as $id) {
                $candidates[$id] = true;
            }
        }

        $best = null;

        foreach (array_keys($candidates) as $id) {
            $dice = T::diceFromTrigrams($tri, $this->valid[$id]['tri']);

            if ($dice >= $threshold && ($best === null || $dice > $best['dice'])) {
                $best = [
                    'id'         => $id,
                    'dice'       => $dice,
                    'chapter_id' => $this->valid[$id]['chapter_id'],
                ];
            }
        }

        return $best;
    }

    /**
     * The least common trigrams of a title, which make the most
     * selective index probes.
     *
     * @param  array<string,true> $tri
     * @return string[]
     */
    private function rarest(array $tri): array
    {
        $scored = [];
        foreach (array_keys($tri) as $t) {
            $scored[$t] = $this->trigramDf[$t] ?? 1;
        }

        asort($scored);

        return array_slice(array_keys($scored), 0, self::PROBE_TRIGRAMS);
    }

    private function subjectOf(int $chapterId): ?int
    {
        foreach ($this->valid as $row) {
            if ($row['chapter_id'] === $chapterId) {
                return $row['subject_id'];
            }
        }

        return null;
    }
}
