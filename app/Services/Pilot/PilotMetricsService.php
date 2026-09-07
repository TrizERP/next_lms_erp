<?php

namespace App\Services\Pilot;

use App\Models\Eso\PilotEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes the Developer Brief's five pilot metrics (§7) for both arms of
 * the Chapter 1014 pilot — see docs/CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md,
 * which this class implements exactly. Read-only: it writes nothing, and
 * touches neither EsoPolicyService (D1-D5) nor any legacy Arm A controller.
 *
 * Arm B metrics are derived entirely from `eso_decision_log` (no
 * duplication — see the plan's §10 on why no new event table exists for
 * either arm). Arm A metrics are derived entirely from the pre-existing
 * `lms_online_exam` / `suggested_content` tables, exactly as the legacy
 * flow already writes them.
 */
class PilotMetricsService
{
    /** Arm A's score-based mastery bar — plan §7.1. */
    protected const ARM_A_MASTERY_THRESHOLD = 0.70;

    /** Plan §7.3 — retention observation window for Arm A. */
    protected const ARM_A_RETENTION_MIN_DAYS = 3;

    protected const ARM_A_RETENTION_MAX_DAYS = 10;

    /** Plan §8 — a mastered enrollment isn't retention-eligible until this many days pass. */
    protected const RETENTION_ELIGIBLE_AFTER_DAYS = 3;

    /**
     * The five metrics, aggregated per arm, for one chapter (optionally one
     * cohort). This is the entire public surface — no dashboard, one report.
     */
    public function summary(int $chapterId, ?string $cohortLabel = null): array
    {
        return [
            'chapter_id' => $chapterId,
            'cohort_label' => $cohortLabel,
            'arm_a' => $this->armSummary($chapterId, PilotEnrollment::ARM_A, $cohortLabel),
            'arm_b' => $this->armSummary($chapterId, PilotEnrollment::ARM_B, $cohortLabel),
        ];
    }

    protected function armSummary(int $chapterId, string $arm, ?string $cohortLabel): array
    {
        $enrollments = PilotEnrollment::forChapter($chapterId)->arm($arm)->countable()
            ->when($cohortLabel !== null, fn ($q) => $q->where('cohort_label', $cohortLabel))
            ->get();

        $observations = $arm === PilotEnrollment::ARM_B
            ? $this->armBObservations($enrollments, $chapterId)
            : $this->armAObservations($enrollments, $chapterId);

        return $this->aggregate($enrollments->count(), $observations);
    }

    // ── Arm B: read from eso_decision_log ───────────────────────────────

    /** @return Collection<int, array> one row per enrollment with raw per-student facts */
    protected function armBObservations(Collection $enrollments, int $chapterId): Collection
    {
        if ($enrollments->isEmpty()) {
            return collect();
        }

        $studentIds = $enrollments->pluck('student_id')->all();
        $conceptIds = DB::table('lms_concept')->where('chapter_id', $chapterId)->pluck('id')->all();

        if ($conceptIds === []) {
            return $enrollments->map(fn ($e) => $this->emptyObservation($e->student_id));
        }

        $log = DB::table('eso_decision_log')
            ->whereIn('student_id', $studentIds)
            ->whereIn('concept_id', $conceptIds)
            ->orderBy('created_at')
            ->get(['student_id', 'node_id', 'action', 'rule_fired', 'state_snapshot', 'llm_instruction', 'created_at']);

        return $enrollments->map(function ($enrollment) use ($log) {
            $rows = $log->where('student_id', $enrollment->student_id)->values();
            if ($rows->isEmpty()) {
                return $this->emptyObservation($enrollment->student_id);
            }

            $entryAt = $rows->first()->created_at;

            $masteryRow = $rows->first(fn ($r) => $r->action === 'mastered_stop_practice');
            $masteryAt = $masteryRow?->created_at;

            $served = $rows->whereIn('action', ['teach', 'practice'])
                ->filter(fn ($r) => $r->llm_instruction !== null)
                ->count();
            $skipped = $rows->where('action', 'skip_instruction')->count();

            // Retention: per node_id, take the LATEST D5 event; the
            // enrollment passes retention if at least one node has a D5
            // event and every node's latest D5 event is 'retained' (no node
            // is left on an unresolved reloop).
            $d5Rows = $rows->whereIn('action', ['retained', 'reloop_node']);
            $retentionResult = null;
            if ($d5Rows->isNotEmpty()) {
                $latestByNode = $d5Rows->groupBy('node_id')->map(fn ($g) => $g->last());
                $retentionResult = $latestByNode->every(fn ($r) => $r->action === 'retained') ? 'pass' : 'fail';
            }

            // Misconception corrected / recurred, keyed by (node_id, misconception_id).
            $correctedCount = 0;
            $recurredCount = 0;
            $flagRows = $rows->whereIn('action', ['serve_contrast_pair', 'misconception_corrected']);
            $byKey = $flagRows->groupBy(function ($r) {
                $snapshot = json_decode((string) $r->state_snapshot, true) ?? [];
                return $r->node_id . ':' . ($snapshot['misconception_id'] ?? 'unknown');
            });
            foreach ($byKey as $events) {
                $correctedAt = null;
                foreach ($events->sortBy('created_at') as $event) {
                    if ($event->action === 'misconception_corrected') {
                        $correctedCount++;
                        $correctedAt = $event->created_at;
                    } elseif ($event->action === 'serve_contrast_pair' && $correctedAt !== null) {
                        $recurredCount++;
                    }
                }
            }

            return [
                'student_id' => $enrollment->student_id,
                'entry_at' => $entryAt,
                'mastery_at' => $masteryAt,
                'retention_result' => $retentionResult,
                'retention_eligible' => $masteryAt !== null && now()->diffInDays($masteryAt) >= self::RETENTION_ELIGIBLE_AFTER_DAYS,
                'explanations_served' => $served,
                'explanations_skipped' => $skipped,
                'misconceptions_corrected' => $correctedCount,
                'misconceptions_recurred' => $recurredCount,
            ];
        });
    }

    // ── Arm A: read from lms_online_exam / suggested_content ───────────

    protected function armAObservations(Collection $enrollments, int $chapterId): Collection
    {
        if ($enrollments->isEmpty()) {
            return collect();
        }

        $studentIds = $enrollments->pluck('student_id')->all();

        $attempts = DB::table('lms_online_exam as e')
            ->join('question_paper as qp', 'qp.id', '=', 'e.question_paper_id')
            ->where('qp.exam_type', 'PAL')
            ->where('qp.paper_desc', $chapterId)
            ->whereIn('e.student_id', $studentIds)
            ->orderBy('e.start_time')
            ->get(['e.student_id', 'e.total_right', 'e.total_wrong', 'e.start_time']);

        $suggested = DB::table('suggested_content')
            ->where('chapter_id', $chapterId)
            ->whereIn('student_id', $studentIds)
            ->whereIn('type', ['pal_content', 'misconception'])
            ->get(['student_id', 'type']);

        return $enrollments->map(function ($enrollment) use ($attempts, $suggested) {
            $studentAttempts = $attempts->where('student_id', $enrollment->student_id)->values();
            if ($studentAttempts->isEmpty()) {
                return $this->emptyObservation($enrollment->student_id);
            }

            $entryAt = $studentAttempts->first()->start_time;

            $scored = $studentAttempts->map(fn ($a) => (object) [
                'start_time' => $a->start_time,
                'score' => ($a->total_right + $a->total_wrong) > 0
                    ? $a->total_right / ($a->total_right + $a->total_wrong)
                    : 0.0,
            ]);

            $masteryAttempt = $scored->first(fn ($a) => $a->score >= self::ARM_A_MASTERY_THRESHOLD);
            $masteryAt = $masteryAttempt?->start_time;

            $retentionResult = null;
            $retentionEligible = false;
            if ($masteryAt !== null) {
                $masteryMoment = \Illuminate\Support\Carbon::parse($masteryAt);
                $retentionEligible = now()->diffInDays($masteryMoment) >= self::RETENTION_ELIGIBLE_AFTER_DAYS;

                $windowStart = $masteryMoment->copy()->addDays(self::ARM_A_RETENTION_MIN_DAYS);
                $windowEnd = $masteryMoment->copy()->addDays(self::ARM_A_RETENTION_MAX_DAYS);
                $retentionAttempt = $scored->first(function ($a) use ($windowStart, $windowEnd) {
                    $at = \Illuminate\Support\Carbon::parse($a->start_time);
                    return $at->between($windowStart, $windowEnd);
                });
                if ($retentionAttempt !== null) {
                    $retentionResult = $retentionAttempt->score >= self::ARM_A_MASTERY_THRESHOLD ? 'pass' : 'fail';
                }
            }

            $studentSuggested = $suggested->where('student_id', $enrollment->student_id);

            return [
                'student_id' => $enrollment->student_id,
                'entry_at' => $entryAt,
                'mastery_at' => $masteryAt,
                'retention_result' => $retentionResult,
                'retention_eligible' => $retentionEligible,
                'explanations_served' => $studentSuggested->count(),
                'explanations_skipped' => null, // Arm A has no skip mechanism — structural, not missing data.
                'misconceptions_corrected' => null, // Arm A has no correction/retest mechanism — structural.
                'misconceptions_recurred' => null,
            ];
        });
    }

    protected function emptyObservation($studentId): array
    {
        return [
            'student_id' => $studentId,
            'entry_at' => null,
            'mastery_at' => null,
            'retention_result' => null,
            'retention_eligible' => false,
            'explanations_served' => 0,
            'explanations_skipped' => 0,
            'misconceptions_corrected' => 0,
            'misconceptions_recurred' => 0,
        ];
    }

    // ── Aggregation (plan §5) ────────────────────────────────────────────

    protected function aggregate(int $enrolledCount, Collection $observations): array
    {
        // Plan §9: enrollment alone isn't an observation — an entry event is required.
        $started = $observations->filter(fn ($o) => $o['entry_at'] !== null);
        $mastered = $started->filter(fn ($o) => $o['mastery_at'] !== null);

        $timeToMasteryMinutes = $mastered
            ->map(fn ($o) => \Illuminate\Support\Carbon::parse($o['entry_at'])->diffInMinutes(\Illuminate\Support\Carbon::parse($o['mastery_at'])))
            ->values();

        $retentionEligible = $mastered->filter(fn ($o) => $o['retention_eligible'] && $o['retention_result'] !== null);
        $retentionPass = $retentionEligible->filter(fn ($o) => $o['retention_result'] === 'pass');

        $correctedTotal = $mastered->filter(fn ($o) => $o['misconceptions_corrected'] !== null);
        $withAnyCorrection = $correctedTotal->filter(fn ($o) => $o['misconceptions_corrected'] > 0);
        $withRecurrence = $withAnyCorrection->filter(fn ($o) => ($o['misconceptions_recurred'] ?? 0) > 0);

        return [
            'enrolled' => $enrolledCount,
            'started' => $started->count(),
            'mastered' => $mastered->count(),
            'mastery_rate' => $started->count() > 0 ? round($mastered->count() / $started->count(), 4) : null,
            'time_to_mastery_minutes_avg' => $timeToMasteryMinutes->isNotEmpty() ? round($timeToMasteryMinutes->avg(), 1) : null,
            'time_to_mastery_minutes_median' => $timeToMasteryMinutes->isNotEmpty() ? round($timeToMasteryMinutes->median(), 1) : null,
            'retention_eligible_count' => $retentionEligible->count(),
            'retention_rate' => $retentionEligible->count() > 0 ? round($retentionPass->count() / $retentionEligible->count(), 4) : null,
            // Explanation volume is measured over everyone who started, not just
            // masterers — a struggling student who never reaches D4 still had
            // explanations served, and that is exactly the signal the brief's
            // "only teach what's missing" metric cares about.
            'explanations_served_avg' => $started->isNotEmpty() ? round($started->avg('explanations_served'), 2) : null,
            'explanations_skipped_avg' => $started->filter(fn ($o) => $o['explanations_skipped'] !== null)->isNotEmpty()
                ? round($started->filter(fn ($o) => $o['explanations_skipped'] !== null)->avg('explanations_skipped'), 2)
                : null,
            'misconception_recurrence_rate' => $withAnyCorrection->count() > 0 ? round($withRecurrence->count() / $withAnyCorrection->count(), 4) : null,
            'misconception_recurrence_applicable' => $withAnyCorrection->count() > 0,
        ];
    }
}
