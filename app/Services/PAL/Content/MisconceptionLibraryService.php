<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\LearnerContentExposure;
use App\Models\PAL\MisconceptionCorrective;
use App\Models\PAL\MisconceptionLibrary;
use App\Models\PAL\QuestionMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The misconception library and its detect -> correct pipeline (spec §4).
 *
 * Spec §4.1 calls this the most important content type, and the reason is the
 * routing rule: when a learner matches a known misconception the system must NOT
 * re-show the same explanation. It shows a DIFFERENT modality or analogy.
 *
 * CONTENT LAW C6 is enforced at the query level, not by convention: a
 * misconception with no approved corrective is never returned as detected-and-
 * actionable, because telling a learner "you have the denominator-add error" and
 * then showing them nothing is worse than saying nothing at all.
 *
 * This service is the library + routing layer. The existing
 * MisconceptionIntelligenceEngine keeps its clustering/analysis role; this fills
 * the library it was always meant to read from (it was running against 2 rows).
 */
class MisconceptionLibraryService
{
    public function __construct(
        protected ?VariantRouterService $router = null
    ) {
        $this->router = $router ?? new VariantRouterService();
    }

    /**
     * Detect a misconception from a wrong answer and route to a corrective.
     * Implements the 5 steps of spec §4.4.
     *
     * @param  array  $context  optional: session_id, mother_tongue, class_student_ids, class_size
     */
    public function detectAndRoute(
        int $learnerId,
        int $questionId,
        $studentAnswer,
        int $subInstituteId,
        array $context = []
    ): array {
        $cfg = config('pal_content.misconception');

        $meta = QuestionMetadata::where('question_id', $questionId)
            ->forTenant($subInstituteId)
            ->first();

        $candidateTags = $meta?->misconception_tags ?? [];
        $conceptId = $meta?->concept_ref_id
            ?? DB::table('lms_question_master')->where('id', $questionId)->value('concept_id');

        $result = [
            'learner_id' => $learnerId,
            'question_id' => $questionId,
            'concept_id' => $conceptId ? (int) $conceptId : null,
            'misconception' => null,
            'match_type' => null,
            'confirmed' => false,
            'occurrences' => 0,
            'corrective_content' => null,
            'teacher_alert' => false,
            'majority_problem' => false,
        ];

        if ($candidateTags === []) {
            $result['reason'] = 'question_has_no_misconception_tags';

            return $result;
        }

        // ── Step 1: exact match against known wrong answers ──
        $detected = $this->matchExact($candidateTags, $studentAnswer, $subInstituteId);
        $matchType = $detected ? 'exact' : null;

        // ── Step 2: regex pattern match (open-ended answers) ──
        if (! $detected) {
            $detected = $this->matchPattern($candidateTags, $studentAnswer, $subInstituteId);
            $matchType = $detected ? 'pattern' : null;
        }

        if (! $detected) {
            $result['reason'] = 'no_misconception_matched';
            $result['candidates_checked'] = $candidateTags;

            return $result;
        }

        $result['misconception'] = [
            'id' => (int) $detected->id,
            'tag' => $detected->tag,
            'description' => $detected->description,
            'error_pattern' => $detected->error_pattern,
            'corrective_action' => $detected->corrective_action,
            'corrective_format' => $detected->corrective_format,
            'priority_level' => (int) $detected->priority_level,
            'prevalence_rate' => $detected->prevalence_rate,
        ];
        $result['match_type'] = $matchType;

        // ── Step 3: frequency check — confirmed at N occurrences ──
        $occurrences = $this->occurrenceCount($learnerId, $detected->tag) + 1;
        $result['occurrences'] = $occurrences;
        $result['confirmed'] = $occurrences >= $cfg['confirm_after_occurrences'];
        $result['teacher_alert'] = $occurrences >= $cfg['teacher_alert_after_occurrences'];

        // ── Step 4 + 5: pick a corrective the learner has NOT already seen ──
        $corrective = $this->selectCorrective(
            (int) $detected->id,
            $learnerId,
            $subInstituteId,
            $context['mother_tongue'] ?? null
        );

        // CONTENT LAW C6 — detected but nothing to serve is a library defect.
        if ($corrective === null) {
            $result['corrective_content'] = null;
            $result['c6_violation'] = true;
            $result['teacher_alert'] = true;
            $result['reason'] = 'misconception_has_no_servable_corrective';

            Log::warning('PAL C6: misconception detected with no approved corrective', [
                'tag' => $detected->tag,
                'misconception_id' => $detected->id,
                'learner_id' => $learnerId,
                'sub_institute_id' => $subInstituteId,
            ]);
        } else {
            $result['corrective_content'] = $corrective;

            $this->recordCorrectiveServe(
                $learnerId,
                $subInstituteId,
                $conceptId ? (int) $conceptId : null,
                $detected->tag,
                $corrective,
                $context['session_id'] ?? null
            );
        }

        // Majority check — is this a whole-class problem worth a teacher intervention?
        if (! empty($context['class_student_ids'])) {
            $result['majority_problem'] = $this->isMajorityProblem(
                $detected->tag,
                $context['class_student_ids'],
                (int) ($context['class_size'] ?? count($context['class_student_ids']))
            );
        }

        MisconceptionLibrary::where('id', $detected->id)->increment('detection_count');

        return $result;
    }

    /**
     * Pick the highest-priority approved corrective the learner has not seen.
     *
     * Falls back to an already-seen corrective only when every option is
     * exhausted — a repeat corrective still beats nothing, and the caller is told
     * via `already_seen` so it can escalate.
     */
    public function selectCorrective(int $misconceptionId, int $learnerId, ?int $subInstituteId, ?string $motherTongue = null): ?array
    {
        $all = MisconceptionCorrective::where('misconception_id', $misconceptionId)
            ->servable()
            ->forTenant($subInstituteId)
            ->orderBy('priority_level')
            ->get();

        if ($all->isEmpty()) {
            return null;
        }

        $seen = LearnerContentExposure::where('learner_id', $learnerId)
            ->whereNotNull('corrective_id')
            ->pluck('corrective_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $unseen = $all->reject(fn ($c) => in_array((int) $c->id, $seen, true));
        $pool = $unseen->isNotEmpty() ? $unseen : $all;

        // Prefer the learner's mother tongue when a variant exists.
        $chosen = null;
        if ($motherTongue) {
            $chosen = $pool->first(fn ($c) => $c->language === $motherTongue
                || in_array($motherTongue, $c->language_variants_available ?? [], true));
        }
        $chosen ??= $pool->first();

        return [
            'corrective_id' => (int) $chosen->id,
            'content_id_ref' => $chosen->content_id_ref,
            'content_master_id' => $chosen->content_master_id ? (int) $chosen->content_master_id : null,
            'title' => $chosen->title,
            'body' => $chosen->body,
            'media_url' => $chosen->media_url,
            'format' => $chosen->format,
            'h5p_type' => $chosen->h5p_type,
            'language' => $chosen->language,
            'estimated_duration_minutes' => $chosen->estimated_duration_minutes,
            'priority_level' => (int) $chosen->priority_level,
            'already_seen' => $unseen->isEmpty(),
        ];
    }

    /**
     * Whole-class prevalence of one misconception — spec §6.3 majority check.
     *
     * @param  array<int,int>  $classStudentIds
     */
    public function isMajorityProblem(string $tag, array $classStudentIds, int $classSize): bool
    {
        if ($classSize <= 0 || $classStudentIds === []) {
            return false;
        }

        $affected = LearnerContentExposure::whereIn('learner_id', $classStudentIds)
            ->where('misconception_tag', $tag)
            ->distinct('learner_id')
            ->count('learner_id');

        return ($affected / $classSize) >= config('pal_content.misconception.majority_threshold');
    }

    /**
     * Class-level breakdown for a teacher dashboard: which misconceptions are
     * live in this cohort and which have crossed the majority threshold.
     */
    public function classPrevalence(array $classStudentIds, ?int $conceptId = null, int $days = 30): array
    {
        if ($classStudentIds === []) {
            return ['class_size' => 0, 'misconceptions' => []];
        }

        $q = LearnerContentExposure::whereIn('learner_id', $classStudentIds)
            ->whereNotNull('misconception_tag')
            ->where('served_at', '>=', now()->subDays($days));

        if ($conceptId !== null) {
            $q->where('concept_ref_id', $conceptId);
        }

        $rows = $q->selectRaw('misconception_tag, COUNT(DISTINCT learner_id) AS learners, COUNT(*) AS hits')
            ->groupBy('misconception_tag')
            ->orderByDesc('learners')
            ->get();

        $size = count($classStudentIds);
        $threshold = config('pal_content.misconception.majority_threshold');

        return [
            'class_size' => $size,
            'window_days' => $days,
            'majority_threshold' => $threshold,
            'misconceptions' => $rows->map(function ($r) use ($size, $threshold) {
                $prev = $size > 0 ? round($r->learners / $size, 3) : 0.0;

                return [
                    'tag' => $r->misconception_tag,
                    'learners_affected' => (int) $r->learners,
                    'total_hits' => (int) $r->hits,
                    'prevalence' => $prev,
                    'majority_problem' => $prev >= $threshold,
                ];
            })->all(),
        ];
    }

    /**
     * Mark whether a corrective actually resolved the misconception, and keep
     * the corrective's rolling resolution_rate current. This is what makes the
     * library improvable instead of write-once.
     */
    public function recordOutcome(int $learnerId, string $tag, bool $resolved): void
    {
        $exposure = LearnerContentExposure::where('learner_id', $learnerId)
            ->where('misconception_tag', $tag)
            ->whereNull('resolved')
            ->orderByDesc('id')
            ->first();

        if (! $exposure) {
            return;
        }

        $exposure->resolved = $resolved;
        $exposure->save();

        if (! $exposure->corrective_id) {
            return;
        }

        $stats = LearnerContentExposure::where('corrective_id', $exposure->corrective_id)
            ->whereNotNull('resolved')
            ->selectRaw('COUNT(*) AS n, SUM(resolved) AS r')
            ->first();

        if ($stats && (int) $stats->n > 0) {
            MisconceptionCorrective::where('id', $exposure->corrective_id)
                ->update(['resolution_rate' => round((int) $stats->r / (int) $stats->n, 4)]);
        }
    }

    /**
     * How many correctives this learner has already been through for one tag —
     * drives the BloomLadderService demotion rule (spec §3.2).
     */
    public function correctiveAttempts(int $learnerId, string $tag): int
    {
        return LearnerContentExposure::where('learner_id', $learnerId)
            ->where('misconception_tag', $tag)
            ->whereNotNull('corrective_id')
            ->count();
    }

    /**
     * Library health per concept — the C6 audit surface, and the report that
     * tells an SME where the library still has holes.
     */
    public function libraryHealth(?int $subInstituteId = null, ?int $conceptId = null): array
    {
        $q = MisconceptionLibrary::forTenant($subInstituteId);
        if ($conceptId !== null) {
            $q->where('concept_ref_id', $conceptId);
        }

        $total = (clone $q)->count();
        $approved = (clone $q)->servable()->count();
        $withCorrective = (clone $q)->servable()->withCorrective()->count();

        $orphans = (clone $q)->servable()->whereNotExists(function ($sub) {
            $sub->selectRaw(1)
                ->from('pal_misconception_corrective as c')
                ->whereColumn('c.misconception_id', 'pal_misconception_library.id')
                ->whereIn('c.quality_status', config('pal_content.servable_statuses', ['approved']));
        })->pluck('tag')->all();

        return [
            'total' => $total,
            'approved' => $approved,
            'servable_with_corrective' => $withCorrective,
            'c6_violations' => count($orphans),
            'c6_violation_tags' => $orphans,
            'c6_pass' => $orphans === [],
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** Step 1 — the student's answer is a known wrong answer for this tag. */
    protected function matchExact(array $tags, $studentAnswer, ?int $subInstituteId): ?MisconceptionLibrary
    {
        $needle = $this->normalise($studentAnswer);
        if ($needle === '') {
            return null;
        }

        $rows = MisconceptionLibrary::whereIn('tag', $tags)
            ->servable()
            ->withCorrective()          // C6 — never surface an unserviceable tag
            ->forTenant($subInstituteId)
            ->orderBy('priority_level')
            ->get();

        foreach ($rows as $row) {
            foreach ($row->typical_wrong_answers ?? [] as $wrong) {
                if ($this->normalise($wrong) === $needle) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * Step 2 — regex pattern match for open-ended answers.
     *
     * The spec calls for an AI similarity pass here. A stored regex is used
     * instead because it is deterministic, auditable and costs nothing per wrong
     * answer; AI-proposed patterns land in error_regex through the tagging batch
     * as drafts and only fire once a human approves them (C5).
     */
    protected function matchPattern(array $tags, $studentAnswer, ?int $subInstituteId): ?MisconceptionLibrary
    {
        $answer = $this->normalise($studentAnswer);
        if ($answer === '') {
            return null;
        }

        $rows = MisconceptionLibrary::whereIn('tag', $tags)
            ->whereNotNull('error_regex')
            ->servable()
            ->withCorrective()
            ->forTenant($subInstituteId)
            ->orderBy('priority_level')
            ->get();

        foreach ($rows as $row) {
            // A malformed stored regex must not take down answer submission.
            $matched = @preg_match('/' . str_replace('/', '\/', $row->error_regex) . '/i', $answer);
            if ($matched === 1) {
                return $row;
            }
        }

        return null;
    }

    protected function occurrenceCount(int $learnerId, string $tag): int
    {
        return LearnerContentExposure::where('learner_id', $learnerId)
            ->where('misconception_tag', $tag)
            ->count();
    }

    protected function recordCorrectiveServe(
        int $learnerId,
        int $subInstituteId,
        ?int $conceptId,
        string $tag,
        array $corrective,
        ?int $sessionId
    ): void {
        LearnerContentExposure::create([
            'learner_id' => $learnerId,
            'sub_institute_id' => $subInstituteId,
            'concept_ref_id' => $conceptId,
            'content_type' => 'corrective',
            'corrective_id' => $corrective['corrective_id'],
            'content_master_id' => $corrective['content_master_id'],
            'content_id_ref' => $corrective['content_id_ref'],
            'format' => $corrective['format'],
            'reason' => 'misconception_corrective',
            'misconception_tag' => $tag,
            'session_id' => $sessionId,
            'served_at' => now(),
        ]);

        MisconceptionCorrective::where('id', $corrective['corrective_id'])->increment('served_count');
    }

    /** Case/whitespace-insensitive comparison so "2/5 " matches "2/5". */
    protected function normalise($value): string
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }
}
