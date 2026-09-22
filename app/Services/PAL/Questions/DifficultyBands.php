<?php

namespace App\Services\PAL\Questions;

use App\Models\lms\lmsmappingtypeModel;
use Illuminate\Support\Facades\Cache;

/**
 * The single place that knows what "easy / medium / hard" means in this database.
 *
 * ---------------------------------------------------------------------------
 * WHY TWO SOURCES
 * ---------------------------------------------------------------------------
 * This estate carries two independent difficulty signals and they cover
 * DIFFERENT questions, so neither one alone can drive a 5/5/5 diagnostic.
 *
 * Measured on vivek_erp, 2026-09-17, over servable MCQs (question_type_id = 1
 * with >= 2 options and a correct answer):
 *
 *   (A) lms_question_mapping, mapping_type_id = 9 ("Depth of Knowledge"),
 *       children 10 = Easy, 11 = Medium, 12 = Hard.
 *       easy 13094 / medium 8535 / hard 6457.
 *
 *   (B) lms_question_master.g_difficulty - a STORED GENERATED column derived
 *       from the AI `answer` JSON envelope ($.difficulty). Only rows produced
 *       by QuestionGenerationService carry it at all.
 *       easy 731 / medium 1245 / hard 375.
 *
 * Counting chapters a learner can actually reach (the 147 rows of
 * chapter_master), the ones that can supply 5 of every band are:
 *
 *   source (A) alone .................  1 chapter
 *   source (B) alone .................  a handful
 *   (A), falling back to (B) ......... 23 chapters
 *
 * The two barely overlap: chapter 1012 has 679 MCQs all tagged by g_difficulty
 * and only 9 tagged by DoK; chapter 8677 is the reverse. Combining them is not
 * a nicety, it is the difference between a feature that works on one chapter
 * and one that works on twenty-three.
 *
 * ---------------------------------------------------------------------------
 * WHY DoK WINS WHERE BOTH EXIST
 * ---------------------------------------------------------------------------
 * Where a question carries both signals they DISAGREE about 47% of the time.
 * So g_difficulty is consulted only for rows with no DoK tag at all - that is
 * what excludeAnyDok() is for. Letting the weaker-coverage signal overrule an
 * explicit DoK tag would make the difficulty_served column we persist a lie,
 * and that column is what the adaptive engine later reasons from.
 */
final class DifficultyBands
{
    public const EASY = 'easy';
    public const MEDIUM = 'medium';
    public const HARD = 'hard';

    /** Ordered easiest -> hardest. Order matters: stepUp/stepDown walk it. */
    public const BANDS = [self::EASY, self::MEDIUM, self::HARD];

    /** lms_mapping_type.id of the "Depth of Knowledge (Easy, Medium, Hard)" parent row. */
    public const DOK_PARENT_ID = 9;

    /**
     * Used only when the lookup below returns nothing (a stripped test DB).
     * Never read these directly at a call site - go through mappingValueIds().
     */
    private const DOK_FALLBACK = [self::EASY => 10, self::MEDIUM => 11, self::HARD => 12];

    /** @var array<string,int>|null process-local memo, on top of the cache */
    private static ?array $memo = null;

    /**
     * Band name => lms_mapping_type.id, resolved by NAME under parent 9.
     *
     * Same lookup palController::getLevelId() has always used, so the Learn
     * flow and the diagnostic can never drift onto different ids.
     *
     * @return array<string,int>
     */
    public static function mappingValueIds(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $resolved = Cache::remember('pal.dok.value_ids', 3600, function () {
            $map = [];

            foreach (lmsmappingtypeModel::where('parent_id', self::DOK_PARENT_ID)->get(['id', 'name']) as $row) {
                $name = strtolower(trim((string) $row->name));

                if (in_array($name, self::BANDS, true)) {
                    $map[$name] = (int) $row->id;
                }
            }

            // Partial resolution is worse than none: one missing band would
            // silently make that bucket unfillable and every paper short.
            return count($map) === count(self::BANDS) ? $map : self::DOK_FALLBACK;
        });

        return self::$memo = $resolved;
    }

    /** Forget the memo. Tests that seed lms_mapping_type need this. */
    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget('pal.dok.value_ids');
    }

    public static function isBand(?string $band): bool
    {
        return $band !== null && in_array($band, self::BANDS, true);
    }

    /** The DoK mapping id for one band, or null when the band is not a band. */
    public static function mappingValueId(string $band): ?int
    {
        return self::mappingValueIds()[$band] ?? null;
    }

    /**
     * Restrict to questions carrying the DoK tag for $band.
     *
     * whereExists rather than a join: a question can hold several mapping rows
     * (Bloom, interests and skills all live in the same table), and a join
     * would multiply it into the result set once per matching row.
     */
    public static function constrainByDok($query, string $questionIdColumn, string $band)
    {
        $valueId = self::mappingValueId($band);

        if ($valueId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($sub) use ($questionIdColumn, $valueId) {
            $sub->selectRaw('1')
                ->from('lms_question_mapping as pal_dok')
                ->whereColumn('pal_dok.questionmaster_id', $questionIdColumn)
                ->where('pal_dok.mapping_type_id', self::DOK_PARENT_ID)
                ->where('pal_dok.mapping_value_id', $valueId);
        });
    }

    /**
     * Restrict to questions with NO DoK opinion at all, in any band.
     *
     * This is the guard that stops g_difficulty contradicting DoK.
     */
    public static function excludeAnyDok($query, string $questionIdColumn)
    {
        $valueIds = array_values(self::mappingValueIds());

        return $query->whereNotExists(function ($sub) use ($questionIdColumn, $valueIds) {
            $sub->selectRaw('1')
                ->from('lms_question_mapping as pal_dok_any')
                ->whereColumn('pal_dok_any.questionmaster_id', $questionIdColumn)
                ->where('pal_dok_any.mapping_type_id', self::DOK_PARENT_ID)
                ->whereIn('pal_dok_any.mapping_value_id', $valueIds);
        });
    }

    /**
     * Restrict to the generated g_difficulty column.
     *
     * Lower-cased on both sides: the estate holds "Easy" and "easy" both, and
     * every other filter site in this codebase lowercases too.
     */
    public static function constrainByGenerated($query, string $questionAlias, string $band)
    {
        return $query->whereRaw('LOWER(' . $questionAlias . '.g_difficulty) = ?', [$band]);
    }

    /** Restrict to rows carrying no difficulty opinion from either source. */
    public static function constrainUntagged($query, string $questionAlias, string $questionIdColumn)
    {
        $query->where(function ($w) use ($questionAlias) {
            $w->whereNull($questionAlias . '.g_difficulty')
                ->orWhereRaw('TRIM(' . $questionAlias . '.g_difficulty) = ?', ['']);
        });

        return self::excludeAnyDok($query, $questionIdColumn);
    }

    /**
     * Bands to borrow from when $band runs short, nearest first.
     *
     * medium sits between the other two so it can borrow either way; easy and
     * hard each have one neighbour. Borrowing from the far end would drop a
     * hard item into an easy slot, which misreads the learner badly.
     *
     * @return array<int,string>
     */
    public static function adjacent(string $band): array
    {
        return match ($band) {
            self::EASY => [self::MEDIUM],
            self::HARD => [self::MEDIUM],
            self::MEDIUM => [self::EASY, self::HARD],
            default => [],
        };
    }

    /** One band harder, saturating at hard. */
    public static function stepUp(?string $band): string
    {
        $i = array_search($band, self::BANDS, true);

        return $i === false ? self::EASY : self::BANDS[min($i + 1, count(self::BANDS) - 1)];
    }

    /** One band easier, saturating at easy. */
    public static function stepDown(?string $band): string
    {
        $i = array_search($band, self::BANDS, true);

        return $i === false ? self::EASY : self::BANDS[max($i - 1, 0)];
    }

    /**
     * Nearest band that actually has stock, preferring easier first.
     *
     * Dropping a learner below their level costs them a little time; pushing
     * them above it costs them the answer, so the walk goes down before up.
     *
     * @param  array<string,int>  $available  band => count
     */
    public static function nearestAvailable(string $band, array $available): ?string
    {
        if (($available[$band] ?? 0) > 0) {
            return $band;
        }

        $i = array_search($band, self::BANDS, true);
        if ($i === false) {
            $i = 0;
        }

        for ($d = 1; $d < count(self::BANDS); $d++) {
            foreach ([$i - $d, $i + $d] as $j) {
                if (isset(self::BANDS[$j]) && ($available[self::BANDS[$j]] ?? 0) > 0) {
                    return self::BANDS[$j];
                }
            }
        }

        return null;
    }
}
