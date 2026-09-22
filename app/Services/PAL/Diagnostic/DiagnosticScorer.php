<?php

namespace App\Services\PAL\Diagnostic;

use App\Services\PAL\Questions\DifficultyBands;
use Illuminate\Support\Facades\Config;

/**
 * Turns a set of answered diagnostic responses into a percentage, a level and
 * the two breakdowns the result screen and the adaptive engine read.
 *
 * Pure: no database, no models. Everything it needs is passed in, which is
 * what makes the band boundaries cheap to test at the exact cut points.
 *
 * ---------------------------------------------------------------------------
 * WHERE THE BANDS COME FROM
 * ---------------------------------------------------------------------------
 * 40 and 70 are not new numbers. They are the cut points the legacy PAL quiz
 * has always used - palController::getCurrentLevelForChapter() picks easy
 * below 40, medium below 70, hard above - and they are documented as the
 * "legacy PAL difficulty band" convention in BloomLadderService. Reusing them
 * means the diagnostic and the existing Learn flow describe a learner the same
 * way instead of contradicting each other.
 *
 * 85 is the one addition. It only SUBDIVIDES the existing top region, so no
 * learner moves across an existing boundary because of it; it exists because
 * the result blade already renders four badges (beginner / developing /
 * proficient / advanced) and without it two of them would be unreachable.
 *
 * The same 40/70 cut points are reused for the per-concept weak/moderate/strong
 * banding, so the whole feature speaks one vocabulary.
 */
class DiagnosticScorer
{
    public const LEVEL_BEGINNER = 'beginner';
    public const LEVEL_DEVELOPING = 'developing';
    public const LEVEL_PROFICIENT = 'proficient';
    public const LEVEL_ADVANCED = 'advanced';

    public const BAND_WEAK = 'weak';
    public const BAND_MODERATE = 'moderate';
    public const BAND_STRONG = 'strong';

    /** Thresholds loaded from config/pal_diagnostic.php with sensible defaults. */
    private float $lowerCut;
    private float $upperCut;
    private float $masteryCut;

    public function __construct()
    {
        $thresholds = Config::get('pal_diagnostic.level_thresholds', [
            'beginner'   => 0,
            'developing' => 40,
            'proficient' => 70,
            'advanced'   => 85,
        ]);

        $this->lowerCut   = (float) ($thresholds['developing'] ?? 40);
        $this->upperCut   = (float) ($thresholds['proficient'] ?? 70);
        $this->masteryCut = (float) ($thresholds['advanced'] ?? 85);
    }

    public function level(float $percentage): string
    {
        return match (true) {
            $percentage < $this->lowerCut => self::LEVEL_BEGINNER,
            $percentage < $this->upperCut => self::LEVEL_DEVELOPING,
            $percentage < $this->masteryCut => self::LEVEL_PROFICIENT,
            default => self::LEVEL_ADVANCED,
        };
    }

    public function band(float $percentage): string
    {
        return match (true) {
            $percentage < $this->lowerCut => self::BAND_WEAK,
            $percentage < $this->upperCut => self::BAND_MODERATE,
            default => self::BAND_STRONG,
        };
    }

    /**
     * The difficulty adaptive practice should open at.
     *
     * The hard-band override is the interesting part. A learner who cleared
     * easy and medium cleanly but missed every hard item can score into the
     * proficient band on volume alone - yet the one thing the paper actually
     * demonstrated is that they cannot do hard items yet. Serving them hard
     * questions on that basis wastes the session. So when the level points at
     * hard but the hard bucket came back empty-handed, the baseline is capped
     * one band down and the reason is recorded rather than silently applied.
     *
     * @param  array<string,array<string,mixed>>  $difficultyBreakdown
     * @return array{difficulty: string, reason: string}
     */
    public function baselineDifficulty(string $level, array $difficultyBreakdown): array
    {
        $difficulty = match ($level) {
            self::LEVEL_BEGINNER => DifficultyBands::EASY,
            self::LEVEL_DEVELOPING => DifficultyBands::MEDIUM,
            default => DifficultyBands::HARD,
        };

        if ($difficulty === DifficultyBands::HARD) {
            $hard = $difficultyBreakdown[DifficultyBands::HARD] ?? null;
            $served = (int) ($hard['served'] ?? 0);
            $correct = (int) ($hard['correct'] ?? 0);

            if ($served >= 2 && $correct === 0) {
                return ['difficulty' => DifficultyBands::MEDIUM, 'reason' => 'hard_band_zero'];
            }
        }

        return ['difficulty' => $difficulty, 'reason' => 'level_' . $level];
    }

    /**
     * Totals over the served rows.
     *
     * `answer_master_id === null` is the ONE test for unanswered - is_correct
     * is a plain flag and defaults to 0 on a row nobody touched, so counting
     * "not correct" as "wrong" would turn every skipped question into a
     * mistake the learner never made.
     *
     * @param  iterable<object|array>  $responses
     * @return array{total:int,correct:int,incorrect:int,unanswered:int,percentage:float}
     */
    public function totals(iterable $responses): array
    {
        $total = $correct = $incorrect = $unanswered = 0;

        foreach ($responses as $row) {
            $total++;

            if ($this->value($row, 'answer_master_id') === null) {
                $unanswered++;
            } elseif ((int) $this->value($row, 'is_correct') === 1) {
                $correct++;
            } else {
                $incorrect++;
            }
        }

        return [
            'total' => $total,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'unanswered' => $unanswered,
            // Against what was actually served, never a hardcoded 15: a thin
            // chapter can legally yield fewer and still be scored honestly.
            'percentage' => $total > 0 ? round($correct / $total * 100, 2) : 0.0,
        ];
    }

    /**
     * Per-band scoring, grouped on the SLOT each item filled.
     *
     * All three keys are always present, even at served = 0, because the
     * result blade iterates this map directly and handles the empty case
     * itself. Grouping on the slot (not on where the difficulty claim came
     * from) is what keeps the three buckets summing to total_questions.
     *
     * @param  iterable<object|array>  $responses
     * @return array<string,array<string,mixed>>
     */
    public function difficultyBreakdown(iterable $responses): array
    {
        $out = [];

        foreach (DifficultyBands::BANDS as $band) {
            $out[$band] = [
                'label' => ucfirst($band),
                'served' => 0, 'correct' => 0, 'incorrect' => 0, 'unanswered' => 0, 'percentage' => 0.0,
            ];
        }

        foreach ($responses as $row) {
            $band = (string) $this->value($row, 'difficulty_served');

            if (! isset($out[$band])) {
                continue;
            }

            $out[$band]['served']++;

            if ($this->value($row, 'answer_master_id') === null) {
                $out[$band]['unanswered']++;
            } elseif ((int) $this->value($row, 'is_correct') === 1) {
                $out[$band]['correct']++;
            } else {
                $out[$band]['incorrect']++;
            }
        }

        foreach ($out as $band => $stats) {
            $out[$band]['percentage'] = $stats['served'] > 0
                ? round($stats['correct'] / $stats['served'] * 100, 2)
                : 0.0;
        }

        return $out;
    }

    /**
     * Per-concept scoring, for the result screen and the adaptive engine.
     *
     * Groups on the concept snapshot taken when the paper was drawn, falling
     * back to the chapter when the question could not be tied to a concept.
     * `exact` is carried through so the screen can say "via chapter" instead
     * of overstating how precisely the gap was located.
     *
     * Sorted weakest first: the first row a learner reads should be the thing
     * most worth their next half hour.
     *
     * @param  iterable<object|array>  $responses
     * @param  array<int,string>  $conceptNames
     * @param  array<int,string>  $chapterNames
     * @return array<int,array<string,mixed>>
     */
    public function conceptBreakdown(iterable $responses, array $conceptNames = [], array $chapterNames = []): array
    {
        $groups = [];

        foreach ($responses as $row) {
            $conceptId = $this->value($row, 'concept_id_snapshot');
            $chapterId = $this->value($row, 'chapter_id_snapshot');
            $conceptId = $conceptId !== null ? (int) $conceptId : null;
            $chapterId = $chapterId !== null ? (int) $chapterId : null;

            $key = $conceptId !== null ? 'c' . $conceptId : 'ch' . ($chapterId ?? 0);

            $groups[$key] ??= [
                'concept_id' => $conceptId,
                'chapter_id' => $chapterId,
                'name' => $conceptId !== null
                    ? ($conceptNames[$conceptId] ?? 'Concept ' . $conceptId)
                    : 'Chapter: ' . ($chapterNames[$chapterId] ?? $chapterId),
                'served' => 0, 'correct' => 0, 'incorrect' => 0, 'unanswered' => 0,
                'percentage' => 0.0, 'band' => self::BAND_WEAK,
                'exact' => (bool) $this->value($row, 'concept_exact'),
            ];

            $groups[$key]['served']++;

            if ($this->value($row, 'answer_master_id') === null) {
                $groups[$key]['unanswered']++;
            } elseif ((int) $this->value($row, 'is_correct') === 1) {
                $groups[$key]['correct']++;
            } else {
                $groups[$key]['incorrect']++;
            }

            // One exact hit is enough to call the group exactly located.
            if ($this->value($row, 'concept_exact')) {
                $groups[$key]['exact'] = true;
            }
        }

        foreach ($groups as $key => $g) {
            $pct = $g['served'] > 0 ? round($g['correct'] / $g['served'] * 100, 2) : 0.0;
            $groups[$key]['percentage'] = $pct;
            $groups[$key]['band'] = $this->band($pct);
        }

        $out = array_values($groups);

        usort($out, function ($a, $b) {
            return [$a['percentage'], -$a['served']] <=> [$b['percentage'], -$b['served']];
        });

        return $out;
    }

    /** Reads a field from either an Eloquent model or a plain array. */
    private function value($row, string $key)
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
