<?php

namespace App\Domain\Admissions\Risk;

use App\Domain\AI\Evidence\EvidenceItem;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\AdmissionMcpService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Admission enquiries whose own follow-up date has passed, as a signal the lifecycle can
 * build on.
 *
 * WHY IT DELEGATES RATHER THAN QUERIES
 *
 * There is exactly one correct answer to "what enquiries does this school have open", and
 * it is the one the Admission screens and the `admissions.listEnquiries` tool already
 * give: `AdmissionMcpService`, which reads `admission_enquiry` scoped to the institute and
 * academic year on the caller's token. This detector therefore computes nothing about an
 * enquiry's identity, status or class. It asks that service and turns what comes back into
 * signals.
 *
 * That is the same rule `LowAttendanceDetector` and `FeeArrearsDetector` follow, for the
 * same reason: a detector with its own SQL would be a second opinion about a family's
 * application, and an officer would be shown a list no admissions screen could reproduce.
 *
 * WHAT "STALLED" MEANS HERE, AND WHAT IT DELIBERATELY DOES NOT
 *
 * An enquiry is stalled when the school's own `followup_date` on it is in the past and the
 * enquiry is still open. That is a fact the record states about itself — somebody promised
 * to come back to this family by a date, and the date has gone.
 *
 * It is NOT "an old enquiry". `admissions.listEnquiries` returns no creation date, so the
 * age of an enquiry is not knowable from what this detector can read, and guessing it from
 * the enquiry number would be inventing a fact about a family. An open enquiry with no
 * follow-up date recorded is therefore **not signalled at all** — it is counted in
 * `coverage()` as unschedulable, exactly as `LowAttendanceDetector` counts a student with
 * too few marked days. "Nobody has scheduled a follow-up" and "the follow-up is overdue"
 * are different problems, and only the second is one this detector can evidence.
 *
 * SEVERITY IS A SCALE, NOT A HARD-CODED BAND
 *
 * The score handed to `ThresholdRegistry::classify()` is the overdue interval scaled onto
 * 0..1 between the floor and ceiling below, so the registry's own bands — and any
 * per-school override in `ai_signal_definitions.thresholds` — decide what counts as
 * serious. Nothing here hard-codes a band.
 *
 * NOTHING IN THIS CLASS CAN WRITE. `AdmissionMcpService::listEnquiries()` and
 * `validateConfirmation()` are both reads, and they are the only two methods it calls. The
 * service's write methods — `updateEnquiry`, `confirm` — are never reachable from here.
 */
final class StalledEnquiryDetector
{
    public const KEY = 'admissions_enquiry_stalled';

    /**
     * How many enquiries one sweep reads when the caller names no limit.
     *
     * 100 is the tool's own maximum, so a sweep reads as much of the pipeline as the
     * service will report rather than silently stopping at its default of 25.
     */
    private const DEFAULT_LIMIT = 100;

    /**
     * Days overdue at which concern begins to register, and at which it saturates.
     *
     * Zero days overdue is not a finding: a follow-up due today has not been missed. At
     * three weeks the school has not spoken to a family it promised to call, and a family
     * four weeks unanswered is not usefully distinguished from one six weeks unanswered —
     * both need the same call today.
     */
    private const OVERDUE_FLOOR_DAYS = 0;

    private const OVERDUE_CEILING_DAYS = 21;

    /**
     * How many enquiries get their missing-field check read for evidence.
     *
     * Each is a second call into the admission service. The cap bounds a pipeline sweep's
     * cost without changing who is found — the list itself is not capped here, only how
     * many get the second evidence item.
     */
    private const MAX_ITEMISED = 25;

    /** Statuses the service itself treats as no longer open. Mirrored, not re-decided. */
    private const CLOSED_STATUSES = ['approved', 'converted', 'closed', 'cancel'];

    /** One enquiry read per (scope, filters) within a request. See sweep(). */
    private array $memo = [];

    public function __construct(
        private readonly AdmissionMcpService $admissions,
        private readonly ThresholdRegistry $thresholds,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments  `search_text` and `limit` are passed to the
     *                                           admission service; `enquiry_id` narrows the
     *                                           result to one record; `overdue_days` raises
     *                                           the bar an enquiry has to clear.
     * @return array<int, DetectedSignal>
     */
    public function detect(McpRequestContext $scope, array $arguments = []): array
    {
        $enquiries = $this->sweep($scope, $arguments);

        if ($enquiries === []) {
            return [];
        }

        $floor = $this->minimumOverdueDays($arguments);
        $today = Carbon::today();
        $signals = [];
        $itemised = 0;

        foreach ($enquiries as $row) {
            if (! is_array($row) || ! $this->isOpen($row)) {
                continue;
            }

            $due = $this->parseDate($row['followup_date'] ?? null);

            // Null means the school has recorded no follow-up date. Skipping is the whole
            // point: an unscheduled enquiry is not an overdue one, and the two are counted
            // apart in coverage() rather than flattened into one number.
            if ($due === null) {
                continue;
            }

            $overdue = $due->diffInDays($today, false);

            if ($overdue < $floor || $overdue <= self::OVERDUE_FLOOR_DAYS) {
                continue;
            }

            $signal = $this->signalFor($scope, $row, (int) $overdue, $due, $itemised < self::MAX_ITEMISED);

            if ($signal !== null) {
                $signals[] = $signal;
                $itemised++;
            }
        }

        return $signals;
    }

    /**
     * The breadth of the sweep that produced those signals.
     *
     * Reported alongside them because "the pipeline is healthy" and "the pipeline is
     * healthy among the 12 enquiries somebody has scheduled a follow-up on" are different
     * statements, and only the first is a finding about the school.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{read:int, open:int, scheduled:int, unscheduled:int, overdue:int, complete:bool}
     */
    public function coverage(McpRequestContext $scope, array $arguments = []): array
    {
        $enquiries = $this->sweep($scope, $arguments);
        $today = Carbon::today();
        $floor = $this->minimumOverdueDays($arguments);

        $open = 0;
        $scheduled = 0;
        $overdue = 0;

        foreach ($enquiries as $row) {
            if (! is_array($row) || ! $this->isOpen($row)) {
                continue;
            }

            $open++;
            $due = $this->parseDate($row['followup_date'] ?? null);

            if ($due === null) {
                continue;
            }

            $scheduled++;
            $days = $due->diffInDays($today, false);

            if ($days > self::OVERDUE_FLOOR_DAYS && $days >= $floor) {
                $overdue++;
            }
        }

        return [
            'read' => count($enquiries),
            'open' => $open,
            'scheduled' => $scheduled,
            'unscheduled' => $open - $scheduled,
            'overdue' => $overdue,
            // Complete only when every open enquiry carries a follow-up date, so the
            // finding really is about the whole open pipeline.
            'complete' => $open > 0 && $scheduled === $open,
        ];
    }

    /** The bar this sweep applied, so a caller can state it rather than imply it. */
    public function minimumOverdueDays(array $arguments = []): int
    {
        $given = $arguments['overdue_days'] ?? null;

        if (! is_numeric($given)) {
            return self::OVERDUE_FLOOR_DAYS + 1;
        }

        return max(1, (int) $given);
    }

    // ---------------------------------------------------------------- internals

    /**
     * One enquiry read, shared by `detect()` and `coverage()` within a request.
     *
     * The agent asks for both, and they must describe the same sweep — reading twice would
     * let an enquiry edited between the two calls make the coverage disagree with the
     * findings it is supposed to be describing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    private function sweep(McpRequestContext $scope, array $arguments): array
    {
        $filters = array_filter([
            'search_text' => $arguments['search_text'] ?? null,
            'limit' => $arguments['limit'] ?? self::DEFAULT_LIMIT,
        ], static fn ($value) => $value !== null && $value !== '');

        // Always false, never taken from the caller: this detector decides for itself which
        // statuses are still open, using the same list the service uses, so a caller cannot
        // widen the sweep to enquiries that have already been converted.
        $filters['only_pending'] = false;

        $enquiryId = isset($arguments['enquiry_id']) ? (int) $arguments['enquiry_id'] : null;
        $memoKey = $scope->selectedInstituteId . '|' . ($scope->academicYear ?? '-') . '|' . json_encode($filters);

        if (! array_key_exists($memoKey, $this->memo)) {
            try {
                $result = $this->admissions->listEnquiries($scope, $filters);
            } catch (Throwable) {
                // A failed sweep is not a finding of a healthy pipeline. An empty result
                // lets the agent report that it could not judge, rather than reporting that
                // every family has been called — the two look identical in the numbers and
                // mean opposites.
                $result = [];
            }

            $rows = is_array($result['data']['enquiries'] ?? null) ? $result['data']['enquiries'] : [];
            $this->memo[$memoKey] = $rows;
        }

        $rows = $this->memo[$memoKey];

        if ($enquiryId !== null && $enquiryId > 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn ($row) => is_array($row) && (int) ($row['enquiry_id'] ?? 0) === $enquiryId
            ));
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function isOpen(array $row): bool
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));

        return $status === '' || ! in_array($status, self::CLOSED_STATUSES, true);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));

        // '0000-00-00' is what this estate's date columns hold when nothing was entered,
        // and Carbon reads it as a date in year zero rather than as absent.
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row  One entry of `listEnquiries`' own `enquiries`.
     */
    private function signalFor(
        McpRequestContext $scope,
        array $row,
        int $overdueDays,
        Carbon $due,
        bool $itemise
    ): ?DetectedSignal {
        $enquiryId = (int) ($row['enquiry_id'] ?? 0);

        if ($enquiryId <= 0) {
            return null;
        }

        $name = trim((string) ($row['student_name'] ?? '')) ?: ('Enquiry #' . $enquiryId);
        $enquiryNo = trim((string) ($row['enquiry_no'] ?? ''));
        $status = trim((string) ($row['status'] ?? '')) ?: 'new';
        $standard = trim((string) ($row['standard_name'] ?? ''));

        $evidence = [
            // The follow-up date itself is always citable, and is the figure the admissions
            // officer's own screen shows.
            new EvidenceItem(
                kind: 'admission_followup_overdue',
                subjectEntityKey: 'enquiry',
                subjectId: $enquiryId,
                summary: sprintf(
                    'Enquiry %s for %s was due a follow-up on %s, %d day%s ago, and is still recorded as "%s".',
                    $enquiryNo !== '' ? $enquiryNo : ('#' . $enquiryId),
                    $name,
                    $due->toDateString(),
                    $overdueDays,
                    $overdueDays === 1 ? '' : 's',
                    $status
                ),
                sourceTable: 'admission_enquiry',
                sourceColumn: 'followup_date',
                sourceId: $enquiryId,
                sourceService: AdmissionMcpService::class . '::listEnquiries',
                observedAt: $due->toDateString(),
                value: [
                    'enquiry_no' => $enquiryNo,
                    'status' => $status,
                    'followup_date' => $due->toDateString(),
                    'overdue_days' => $overdueDays,
                    'standard_name' => $standard,
                ],
                numericValue: (float) $overdueDays,
                unit: 'days',
                confidence: 1.0,
                verified: true,
                evidenceKey: 'admission_followup_overdue:' . $enquiryId,
            ),
        ];

        if ($itemise) {
            $readiness = $this->readinessEvidence($scope, $enquiryId, $name, $enquiryNo);

            if ($readiness !== null) {
                $evidence[] = $readiness;
            }
        }

        $score = $this->riskScore($overdueDays);

        return new DetectedSignal(
            signalKey: self::KEY,
            subjectEntityKey: 'enquiry',
            subjectId: $enquiryId,
            score: $score,
            severity: $this->thresholds->classify($score, $scope->selectedInstituteId, self::KEY),
            evidence: $evidence,
            components: [
                'overdue_days' => $overdueDays,
                'risk_score' => $score,
                'followup_date' => $due->toDateString(),
                'enquiry_no' => $enquiryNo,
                'status' => $status,
                'standard_name' => $standard,
                'mobile' => trim((string) ($row['mobile'] ?? '')),
            ],
            // The date is a record, not an estimate, so confidence is high — but it is
            // bounded below 1.0 because the record says when somebody meant to call, not
            // whether they did. A call made and not logged looks identical here.
            confidence: 0.9,
            subjectLabel: $name,
            domain: 'k12',
            context: ['academic_year' => $scope->academicYear],
            detectedAt: now()->toDateTimeString(),
        );
    }

    /**
     * What the enquiry still needs before it could be confirmed, as a second citable fact.
     *
     * Read through `validateConfirmation()`, which is the same check the admission
     * confirmation screen runs — so a claim that an enquiry is missing a division or a
     * quota is the school's own answer, not this detector's opinion.
     */
    private function readinessEvidence(
        McpRequestContext $scope,
        int $enquiryId,
        string $name,
        string $enquiryNo
    ): ?EvidenceItem {
        try {
            $result = $this->admissions->validateConfirmation($scope, ['enquiry_id' => $enquiryId]);
        } catch (Throwable) {
            // One enquiry's readiness check failing must not drop that enquiry's signal —
            // the overdue date above still stands, and it is still evidence.
            return null;
        }

        if (($result['success'] ?? false) !== true) {
            return null;
        }

        $missing = is_array($result['data']['missing_fields'] ?? null) ? $result['data']['missing_fields'] : [];
        $labels = array_values(array_filter(array_map(
            static fn ($field) => is_array($field) ? trim((string) ($field['label'] ?? '')) : '',
            $missing
        )));

        $ready = (bool) ($result['data']['ready'] ?? false);

        return new EvidenceItem(
            kind: 'admission_readiness',
            subjectEntityKey: 'enquiry',
            subjectId: $enquiryId,
            summary: $labels === []
                ? sprintf(
                    'Enquiry %s for %s has every field the school requires for confirmation%s.',
                    $enquiryNo !== '' ? $enquiryNo : ('#' . $enquiryId),
                    $name,
                    $ready ? '' : ', and is already recorded against a student'
                )
                : sprintf(
                    'Enquiry %s for %s is missing %s before it could be confirmed.',
                    $enquiryNo !== '' ? $enquiryNo : ('#' . $enquiryId),
                    $name,
                    implode(', ', $labels)
                ),
            sourceTable: 'admission_enquiry',
            sourceId: $enquiryId,
            sourceService: AdmissionMcpService::class . '::validateConfirmation',
            observedAt: now()->toDateTimeString(),
            value: [
                'ready' => $ready,
                'already_confirmed' => (bool) ($result['data']['already_confirmed'] ?? false),
                'missing_fields' => $labels,
            ],
            numericValue: (float) count($labels),
            unit: 'fields',
            confidence: 1.0,
            verified: true,
            evidenceKey: 'admission_readiness:' . $enquiryId,
        );
    }

    /**
     * An overdue interval on the estate's 0..1 risk scale.
     *
     * At or below the floor there is nothing to report; at and above the ceiling the
     * concern is as high as this measure can express.
     */
    private function riskScore(int $overdueDays): float
    {
        if ($overdueDays <= self::OVERDUE_FLOOR_DAYS) {
            return 0.0;
        }

        return round(
            min(1.0, ($overdueDays - self::OVERDUE_FLOOR_DAYS) / (self::OVERDUE_CEILING_DAYS - self::OVERDUE_FLOOR_DAYS)),
            4
        );
    }
}
