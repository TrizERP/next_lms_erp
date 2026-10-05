<?php

namespace App\Domain\PAL\CoherenceRisk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\DetectorCoverage;
use App\Domain\AI\Signals\SignalDetector;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Domain\K12\AcademicRisk\StudentScope;
use App\Services\Mcp\McpRequestContext;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects a student whose gaps are systemic rather than local: several weak
 * concepts whose unmastered root blockers (CoherenceMapRepository::rootBlockers(),
 * the same transitive-closure engine Phases 3-5 already use) span TWO OR MORE
 * distinct chapters.
 *
 * This is deliberately not "the student has a weak concept" — that is common
 * and, on its own, unremarkable; PedagogySuggestedContentService already
 * surfaces it per-concept. What is worth a teacher's attention is a student
 * whose root causes are scattered across chapters, because no single lesson
 * or piece of practice content can close that gap — it needs a plan, which is
 * exactly what this agent, once approved, flags for.
 *
 * Reuses StudentScope as-is (App\Domain\K12\AcademicRisk\StudentScope) rather
 * than duplicating it: it is scoped only by tenant/tblstudent, with no K12-
 * specific coupling, and CoherenceMapController already reads the same
 * tblstudent_enrollment tables directly for PAL's own scope resolution.
 */
class CompoundingRootBlockerDetector implements SignalDetector
{
    public const KEY = 'pal_compounding_root_blockers';

    /** Below this mastery, a concept counts as weak enough to check for a root cause. */
    private const WEAK_MASTERY_FLOOR = 0.5;

    /** How many of a student's weakest concepts get walked for root blockers. */
    private const MAX_WEAK_CONCEPTS_CHECKED = 5;

    /** Below this many distinct chapters among the blockers, it is a local gap, not a compounding one. */
    private const MIN_DISTINCT_CHAPTERS = 2;

    /** Chapter spread saturates the chapter component of the score at this count. */
    private const CHAPTER_CEILING = 4;

    private ?DetectorCoverage $coverage = null;

    public function __construct(
        private readonly StudentScope $scope,
        private readonly ThresholdRegistry $thresholds,
        private readonly CoherenceMapRepository $coherenceMap,
    ) {
    }

    public function coverage(): ?DetectorCoverage
    {
        return $this->coverage;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function subjectEntityKey(): string
    {
        return 'student';
    }

    public function domain(): string
    {
        return 'pal';
    }

    public function detectFor(int|string $subjectId, McpRequestContext $context): ?DetectedSignal
    {
        return $this->detect($context, [(int) $subjectId], 1)[0] ?? null;
    }

    /**
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $context, ?array $subjectIds = null, int $limit = 100): array
    {
        $requirement = sprintf(
            'needs at least %d concepts below %.0f%% mastery, with root blockers in at least %d chapters.',
            2,
            self::WEAK_MASTERY_FLOOR * 100,
            self::MIN_DISTINCT_CHAPTERS
        );

        if (! Schema::hasTable('pal_concept_mastery')) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        $students = $this->scope->students($context, $subjectIds);

        if ($students === []) {
            $this->coverage = new DetectorCoverage(self::KEY, 0, 0, 0, $requirement);

            return [];
        }

        $signals = [];
        $evaluated = 0;

        foreach ($students as $studentId => $studentName) {
            $weakRows = DB::table('pal_concept_mastery')
                ->where('learner_id', $studentId)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->where('p_mastery', '<', self::WEAK_MASTERY_FLOOR)
                ->orderBy('p_mastery')
                ->limit(self::MAX_WEAK_CONCEPTS_CHECKED)
                ->get(['concept_ref_id', 'p_mastery']);

            if ($weakRows->count() < 2) {
                continue;
            }

            $evaluated++;

            $signal = $this->evaluate((int) $studentId, $studentName, $weakRows->all(), $context);

            if ($signal !== null) {
                $signals[] = $signal;
            }

            if (count($signals) >= $limit) {
                break;
            }
        }

        $this->coverage = new DetectorCoverage(
            self::KEY,
            count($students),
            $evaluated,
            count($signals),
            $requirement
        );

        return $signals;
    }

    /**
     * @param  array<int, object>  $weakRows
     */
    private function evaluate(int $studentId, string $studentName, array $weakRows, McpRequestContext $context): ?DetectedSignal
    {
        // Dedupe root blockers across every weak concept checked: the same
        // upstream gap is often the cause of several weak concepts at once,
        // and it must count as one blocker, not one per concept that led to it.
        $blockersById = [];

        foreach ($weakRows as $row) {
            foreach ($this->coherenceMap->rootBlockers((int) $row->concept_ref_id, $studentId) as $blocker) {
                $blockersById[(int) $blocker['id']] = $blocker;
            }
        }

        if ($blockersById === []) {
            return null;
        }

        $chapterIds = DB::table('lms_concept')
            ->whereIn('id', array_keys($blockersById))
            ->pluck('chapter_id', 'id');

        $chaptersByBlocker = [];
        $distinctChapters = [];

        foreach ($blockersById as $id => $blocker) {
            $chapterId = (int) ($chapterIds[$id] ?? 0);

            if ($chapterId <= 0) {
                continue;
            }

            $chaptersByBlocker[$id] = $chapterId;
            $distinctChapters[$chapterId] = true;
        }

        if (count($distinctChapters) < self::MIN_DISTINCT_CHAPTERS) {
            return null;
        }

        $chapterComponent = min(1.0, (count($distinctChapters) - 1) / (self::CHAPTER_CEILING - 1));

        $gaps = array_map(
            fn (array $blocker) => max(0.0, (float) $blocker['gate'] - (float) $blocker['mastery']),
            $blockersById
        );
        $severityComponent = $gaps === [] ? 0.0 : min(1.0, array_sum($gaps) / count($gaps));

        $score = min(1.0, ($chapterComponent * 0.6) + ($severityComponent * 0.4));

        if ($score <= 0.0) {
            return null;
        }

        $severity = $this->thresholds->classify($score, $context->selectedInstituteId, self::KEY);

        $chapterNames = Schema::hasTable('chapter_master')
            ? DB::table('chapter_master')->whereIn('id', array_unique(array_values($chaptersByBlocker)))->pluck('chapter_name', 'id')
            : collect();

        $evidence = [
            EvidenceItem::fromComputation(
                kind: 'compounding_root_blockers',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    '%d weak concept(s) trace back to %d unmastered root blocker(s) spanning %d chapters.',
                    count($weakRows),
                    count($blockersById),
                    count($distinctChapters)
                ),
                sourceService: self::class,
                value: [
                    'weak_concepts_checked' => count($weakRows),
                    'root_blocker_count' => count($blockersById),
                    'distinct_chapters' => count($distinctChapters),
                ],
                numericValue: (float) count($distinctChapters),
                unit: 'chapters',
                observedAt: now()->toDateTimeString(),
            ),
        ];

        // One evidence row per distinct root blocker, so the explanation can
        // name each — capped the same way contentFor()/questionsFor() cap
        // their own reads, not because more blockers would be wrong to cite,
        // but because a case with fifteen cited rows is not one a teacher
        // reads to the end.
        foreach (array_slice($blockersById, 0, 8, true) as $id => $blocker) {
            $chapterName = $chapterNames[$chaptersByBlocker[$id] ?? 0] ?? null;

            $evidence[] = EvidenceItem::fromComputation(
                kind: 'root_blocker',
                subjectEntityKey: 'student',
                subjectId: $studentId,
                summary: sprintf(
                    '"%s"%s — mastery %.2f, needs %.2f.',
                    $blocker['name'] ?? ('Concept #' . $id),
                    $chapterName ? " (chapter: {$chapterName})" : '',
                    (float) $blocker['mastery'],
                    (float) $blocker['gate']
                ),
                sourceService: self::class,
                value: [
                    'concept_id' => $id,
                    'chapter_id' => $chaptersByBlocker[$id] ?? null,
                    'mastery' => round((float) $blocker['mastery'], 4),
                    'gate' => round((float) $blocker['gate'], 4),
                    'depth' => (int) ($blocker['depth'] ?? 1),
                ],
                numericValue: round((float) $blocker['mastery'], 4),
                unit: 'mastery',
                observedAt: now()->toDateTimeString(),
            );
        }

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'student',
            subjectId: $studentId,
            score: $score,
            severity: $severity,
            evidence: $evidence,
            components: [
                'distinct_chapters' => count($distinctChapters),
                'chapter_component' => round($chapterComponent, 4),
                'root_blocker_count' => count($blockersById),
                'severity_component' => round($severityComponent, 4),
                'weak_concepts_checked' => count($weakRows),
            ],
            confidence: round(min(1.0, count($weakRows) / self::MAX_WEAK_CONCEPTS_CHECKED), 2),
            subjectLabel: $studentName,
            domain: 'pal',
            detectedAt: now()->toDateTimeString(),
        );
    }
}
