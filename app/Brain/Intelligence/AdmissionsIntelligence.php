<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\AcademicYear;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Admissions Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `admission_registration_v1` is ONE CANDIDATE'S PROGRESS through
 * the admission process: the home-visit round, the parent interview and its
 * outcome, the confirmation and its outcome, and whether the fee was paid.
 *
 * ── THE YEAR WAS BEING READ FROM THE CALENDAR ───────────────────────────────
 *
 * This table has no `syear`. The previous version filtered on
 * `YEAR(created_at) = syear`, which is the CALENDAR year and is not the
 * academic one: at the largest institute that returned 554 registrations where
 * the academic year — 31 March 2025 to 20 March 2026, read from that
 * institute's own `academic_year` rows — holds 668. A hundred and fourteen
 * candidates were in the wrong year, and every rate computed from the number
 * was wrong with them. Scoping now goes through {@see AcademicYear::window()},
 * so a school whose year starts in April is asked about April.
 *
 * ── THE STATUS CODES ARE THE INSTITUTE'S OWN ────────────────────────────────
 *
 * `p_int` and `confi` hold short codes — I, NO, W/L, C, C/A — and nothing in
 * the schema expands them. They are NOT expanded here either. What IS used is
 * the grouping the LMS's own admission controller applies to them: it treats
 * `["C", "C/A"]` as the confirmed branch and `["NO", "W/L"]` as the
 * not-proceeding branch when it decides which email to send. That grouping is
 * read from the application's behaviour rather than guessed, and the codes are
 * shown verbatim beside it.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `new_admission_inquiry_registration` carries caste, sub-caste, religion,
 * Aadhaar numbers for both parents, blood pressure, diabetes, drug allergies
 * and whether a four-year-old wets the bed. Only the standard applied for and
 * the row count are read from it. Nothing else belongs in an aggregate that any
 * office-holder can open, and no admissions question needs it.
 */
final class AdmissionsIntelligence
{
    private const REGISTRATION_TABLE = 'admission_registration_v1';

    private const INQUIRY_TABLE = 'new_admission_inquiry_registration';

    /**
     * Confirmation codes the LMS's own admission controller treats as
     * confirmed when it decides which email a candidate receives.
     */
    public const CONFIRMED_CODES = ['C', 'C/A'];

    /** And the codes it treats as not proceeding. */
    public const NOT_PROCEEDING_CODES = ['NO', 'W/L'];

    /** Below this many candidates, a conversion rate is about the individuals. */
    public const MIN_FUNNEL_COHORT = 20;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /** @return array{start:string,end:string}|null */
    public function yearWindow(): ?array
    {
        return $this->memo['window'] ??= AcademicYear::window($this->tenantId, $this->syear);
    }

    /* -------------------------------------------------------- L0: coverage */

    /** @return array<string,mixed> */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $empty = ['sources' => [], 'counts' => []];

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
            ];
        }

        if (! SchemaCache::hasTable(self::REGISTRATION_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::REGISTRATION_TABLE."' does not exist in this deployment.",
            ];
        }

        $window = $this->yearWindow();

        if ($window === null) {
            // Registrations carry a timestamp and no year, so without the
            // institute's own term dates there is no honest way to scope them.
            // Reporting every year at once under this year's heading would be
            // worse than saying so.
            return $empty + [
                'available' => false,
                'reason' => "This institute has no term dates on file for academic year {$this->syear}. "
                    .'Admission registrations are recorded by date rather than by year, so they cannot be scoped to '
                    .'it without them.',
            ];
        }

        $shape = $this->scopedRegistrations()
            ->selectRaw(
                'COUNT(*) as registrations,
                 SUM(CASE WHEN TRIM(COALESCE(p_int, "")) <> "" THEN 1 ELSE 0 END) as interview_recorded,
                 SUM(CASE WHEN TRIM(COALESCE(confi, "")) <> "" THEN 1 ELSE 0 END) as confirmation_recorded,
                 SUM(CASE WHEN TRIM(COALESCE(paid, "")) <> "" THEN 1 ELSE 0 END) as payment_recorded,
                 SUM(CASE WHEN TRIM(COALESCE(h_n, "")) <> "" THEN 1 ELSE 0 END) as round_recorded'
            )
            ->first();

        $registrations = (int) ($shape->registrations ?? 0);
        $inquiries = $this->inquiryCount();

        if ($registrations === 0 && $inquiries === 0) {
            return $empty + [
                'available' => false,
                'reason' => "No admission registrations or inquiries were recorded between {$window['start']} and "
                    ."{$window['end']}, the dates this institute holds for academic year {$this->syear}.",
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'registrations' => $registrations > 0,
                'interviewOutcomes' => (int) $shape->interview_recorded > 0,
                'confirmationOutcomes' => (int) $shape->confirmation_recorded > 0,
                'paymentStatus' => (int) $shape->payment_recorded > 0,
                'inquiries' => $inquiries > 0,
                'yearWindow' => true,
            ],
            'counts' => [
                'registrations' => $registrations,
                'interviewRecorded' => (int) $shape->interview_recorded,
                'confirmationRecorded' => (int) $shape->confirmation_recorded,
                'paymentRecorded' => (int) $shape->payment_recorded,
                'roundRecorded' => (int) $shape->round_recorded,
                'inquiries' => $inquiries,
            ],
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed>|null */
    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed>|null */
    private function computePosition(): ?array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return null;
        }

        $counts = $coverage['counts'];
        $registrations = (int) $counts['registrations'];

        if ($registrations === 0) {
            return [
                'registrations' => 0,
                'inquiries' => (int) $counts['inquiries'],
            ] + array_fill_keys([
                'interviewRecorded', 'notProceedingAtInterview', 'confirmed', 'notProceeding',
                'paid', 'confirmedUnpaid', 'conversionRate', 'paymentRate', 'undecided',
                'medianDaysToConfirm',
            ], null);
        }

        $outcomes = $this->scopedRegistrations()
            ->selectRaw(
                'SUM(CASE WHEN TRIM(COALESCE(confi, "")) IN (?, ?) THEN 1 ELSE 0 END) as confirmed,
                 SUM(CASE WHEN TRIM(COALESCE(confi, "")) IN (?, ?) THEN 1 ELSE 0 END) as not_proceeding,
                 SUM(CASE WHEN TRIM(COALESCE(p_int, "")) IN (?, ?) THEN 1 ELSE 0 END) as declined_at_interview,
                 SUM(CASE WHEN LOWER(TRIM(COALESCE(paid, ""))) = "yes" THEN 1 ELSE 0 END) as paid,
                 SUM(CASE WHEN TRIM(COALESCE(confi, "")) IN (?, ?) AND LOWER(TRIM(COALESCE(paid, ""))) <> "yes" THEN 1 ELSE 0 END) as confirmed_unpaid,
                 SUM(CASE WHEN TRIM(COALESCE(confi, "")) = "" THEN 1 ELSE 0 END) as undecided',
                [
                    ...self::CONFIRMED_CODES,
                    ...self::NOT_PROCEEDING_CODES,
                    ...self::NOT_PROCEEDING_CODES,
                    ...self::CONFIRMED_CODES,
                ]
            )
            ->first();

        $confirmed = (int) ($outcomes->confirmed ?? 0);

        return [
            'registrations' => $registrations,
            'inquiries' => (int) $counts['inquiries'] > 0 ? (int) $counts['inquiries'] : null,
            'interviewRecorded' => (int) $counts['interviewRecorded'],
            'notProceedingAtInterview' => (int) ($outcomes->declined_at_interview ?? 0),
            'confirmed' => $confirmed,
            'notProceeding' => (int) ($outcomes->not_proceeding ?? 0),
            'undecided' => (int) ($outcomes->undecided ?? 0),
            'paid' => (int) ($outcomes->paid ?? 0),
            'confirmedUnpaid' => (int) ($outcomes->confirmed_unpaid ?? 0),
            'conversionRate' => round($confirmed / $registrations * 100, 1),
            // A payment rate over no confirmations is undefined, not nought.
            'paymentRate' => $confirmed > 0 ? round((int) ($outcomes->paid ?? 0) / $confirmed * 100, 1) : null,
            'medianDaysToConfirm' => $this->medianDaysToConfirm(),
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * The funnel, as the stages the records actually support.
     *
     * EVERY STAGE IS A SUBSET OF THE ONE ABOVE IT, which the previous version's
     * funnel was not: it showed 401 interviewed and 451 confirmed, so more
     * candidates reached stage three than stage two. A funnel whose stages do
     * not nest is not a funnel.
     *
     * @return array<int,array<string,mixed>>
     */
    public function funnel(): array
    {
        return $this->memo['funnel'] ??= (function (): array {
            $position = $this->position();
            if ($position === null || $position['registrations'] === 0) {
                return [];
            }

            $total = $position['registrations'];

            $stages = [
                ['key' => 'registered', 'label' => 'Registered', 'candidates' => $total],
                [
                    'key' => 'interviewed',
                    'label' => 'Interview outcome recorded',
                    'candidates' => $position['interviewRecorded'],
                ],
                [
                    'key' => 'confirmed',
                    'label' => 'Confirmed (codes '.implode(' or ', self::CONFIRMED_CODES).')',
                    'candidates' => $position['confirmed'],
                ],
                [
                    'key' => 'paid',
                    'label' => 'Fee paid',
                    // CONFIRMED and paid, not simply paid. A candidate with a
                    // fee against them and no confirmation is not further along
                    // the funnel, and counting them here would break the nesting
                    // that makes this a funnel at all.
                    'candidates' => $position['confirmed'] - $position['confirmedUnpaid'],
                ],
            ];

            $out = [];
            $previous = null;
            foreach ($stages as $stage) {
                $out[] = $stage + [
                    'share' => round($stage['candidates'] / $total * 100, 1),
                    // The figure a reader actually wants: how many were lost
                    // between this stage and the one before it.
                    'lostFromPrevious' => $previous === null ? null : max(0, $previous - $stage['candidates']),
                ];
                $previous = $stage['candidates'];
            }

            return $out;
        })();
    }

    /**
     * Candidates by the confirmation code recorded against them, VERBATIM.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byConfirmationCode(): array
    {
        return $this->memo['byCode'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->scopedRegistrations()
                ->selectRaw(
                    'CASE
                        WHEN TRIM(COALESCE(confi, "")) = "" THEN "Not recorded"
                        ELSE TRIM(confi)
                     END as code,
                     COUNT(*) as candidates,
                     SUM(CASE WHEN LOWER(TRIM(COALESCE(paid, ""))) = "yes" THEN 1 ELSE 0 END) as paid'
                )
                ->groupBy('code')
                ->orderByDesc('candidates')
                ->get();

            $total = array_sum(array_map(static fn ($r) => (int) $r->candidates, $rows->all()));

            return array_map(static function ($row) use ($total) {
                $code = (string) $row->code;
                $meaning = match (true) {
                    in_array($code, self::CONFIRMED_CODES, true) => 'treated as confirmed by the admission module',
                    in_array($code, self::NOT_PROCEEDING_CODES, true) => 'treated as not proceeding by the admission module',
                    default => 'no grouping recorded for this code',
                };

                return [
                    'key' => substr(md5($code), 0, 12),
                    'label' => $code,
                    'meaning' => $meaning,
                    'candidates' => (int) $row->candidates,
                    'paid' => (int) $row->paid,
                    'share' => $total > 0 ? round((int) $row->candidates / $total * 100, 1) : null,
                ];
            }, $rows->all());
        })();
    }

    /**
     * Demand by the standard applied for.
     *
     * The ONLY field read from the inquiry table besides its row count — the
     * rest of that table is health, caste and identity data that no admissions
     * question needs.
     *
     * @return array<int,array<string,mixed>>
     */
    public function demandByStandard(): array
    {
        return $this->memo['demand'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::INQUIRY_TABLE) || $this->inquiryCount() === 0) {
                return [];
            }

            $rows = DB::table(self::INQUIRY_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->selectRaw(
                    'CASE
                        WHEN TRIM(COALESCE(admission_std, "")) = "" THEN "Not stated"
                        ELSE TRIM(admission_std)
                     END as standard,
                     COUNT(*) as inquiries'
                )
                ->groupBy('standard')
                ->orderByDesc('inquiries')
                ->get();

            $total = array_sum(array_map(static fn ($r) => (int) $r->inquiries, $rows->all()));

            return array_map(static fn ($row) => [
                'key' => substr(md5((string) $row->standard), 0, 12),
                'label' => (string) $row->standard,
                'inquiries' => (int) $row->inquiries,
                'share' => $total > 0 ? round((int) $row->inquiries / $total * 100, 1) : null,
            ], $rows->all());
        })();
    }

    /* ------------------------------------------------------- the DQ ledger */

    /** @return array<string,mixed> */
    public function dataQuality(): array
    {
        return $this->memo['dataQuality'] ??= $this->computeDataQuality();
    }

    /** @return array<string,mixed> */
    private function computeDataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $counts = $coverage['counts'];
        $registrations = (int) $counts['registrations'];
        $position = $this->position();

        $noInterview = $registrations - (int) $counts['interviewRecorded'];
        $noConfirmation = $registrations - (int) $counts['confirmationRecorded'];
        $noPayment = $registrations - (int) $counts['paymentRecorded'];
        $unknownCodes = count(array_filter(
            $this->byConfirmationCode(),
            static fn ($c) => $c['label'] !== 'Not recorded'
                && ! in_array($c['label'], [...self::CONFIRMED_CODES, ...self::NOT_PROCEEDING_CODES], true),
        ));

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'no_confirmation_outcome',
                    'label' => 'Candidates with no confirmation outcome',
                    'value' => $noConfirmation,
                    'format' => 'count',
                    'sharePercent' => $registrations > 0 ? round($noConfirmation / $registrations * 100, 2) : null,
                    'shareLabel' => 'of registrations',
                    'state' => $noConfirmation > 0 ? 'attention' : 'ok',
                    'note' => $noConfirmation > 0
                        ? 'These candidates are neither confirmed nor declined. They count towards the registration '
                            .'total and against the conversion rate, and nobody has told them anything.'
                        : 'Every candidate has a confirmation outcome recorded.',
                ],
                [
                    'key' => 'no_interview_outcome',
                    'label' => 'Candidates with no interview outcome',
                    'value' => $noInterview,
                    'format' => 'count',
                    'sharePercent' => $registrations > 0 ? round($noInterview / $registrations * 100, 2) : null,
                    'shareLabel' => 'of registrations',
                    'state' => $noInterview > 0 ? 'attention' : 'ok',
                    'note' => $noInterview > 0
                        ? 'The interview stage of the funnel is computed over the candidates who have one, so these '
                            .'are absent from it rather than counted as not interviewed.'
                        : 'Every candidate has an interview outcome recorded.',
                ],
                [
                    'key' => 'confirmed_unpaid',
                    'label' => 'Confirmed with no payment recorded',
                    'value' => $position['confirmedUnpaid'] ?? 0,
                    'format' => 'count',
                    'sharePercent' => ($position['confirmed'] ?? 0) > 0
                        ? round(($position['confirmedUnpaid'] ?? 0) / $position['confirmed'] * 100, 2)
                        : null,
                    'shareLabel' => 'of confirmed candidates',
                    'state' => ($position['confirmedUnpaid'] ?? 0) > 0 ? 'attention' : 'ok',
                    'note' => ($position['confirmedUnpaid'] ?? 0) > 0
                        ? 'A confirmed place with no fee against it is either a seat held for nothing or a payment '
                            .'taken outside this system. The records cannot tell those apart.'
                        : 'Every confirmed candidate has a payment recorded.',
                ],
                [
                    'key' => 'no_payment_field',
                    'label' => 'Candidates with no payment field set',
                    'value' => $noPayment,
                    'format' => 'count',
                    'sharePercent' => $registrations > 0 ? round($noPayment / $registrations * 100, 2) : null,
                    'shareLabel' => 'of registrations',
                    'state' => $noPayment > 0 ? 'attention' : 'ok',
                    'note' => $noPayment > 0
                        ? 'The payment field is blank rather than "No". A blank is unknown; it is not counted as an '
                            .'unpaid fee.'
                        : 'Every candidate has the payment field set.',
                ],
                [
                    'key' => 'unrecognised_codes',
                    'label' => 'Confirmation codes the module does not group',
                    'value' => $unknownCodes,
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => $unknownCodes > 0 ? 'attention' : 'ok',
                    'note' => $unknownCodes > 0
                        ? 'These codes are in use but are neither in the confirmed group ('
                            .implode(', ', self::CONFIRMED_CODES).') nor the not-proceeding group ('
                            .implode(', ', self::NOT_PROCEEDING_CODES).') that the admission module itself applies, '
                            .'so candidates carrying them fall outside the conversion rate.'
                        : 'Every confirmation code in use is one the admission module groups.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    private function inquiryCount(): int
    {
        return $this->memo['inquiries'] ??= SchemaCache::hasTable(self::INQUIRY_TABLE)
            ? (int) DB::table(self::INQUIRY_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count()
            : 0;
    }

    /** Days between the interview and the confirmation, for the candidates that have both. */
    private function medianDaysToConfirm(): ?int
    {
        $days = $this->scopedRegistrations()
            ->whereNotNull('p_int_date')
            ->whereNotNull('confi_date')
            ->where('p_int_date', '>', '1900-01-01')
            ->where('confi_date', '>', '1900-01-01')
            ->whereRaw('confi_date >= p_int_date')
            ->orderByRaw('DATEDIFF(confi_date, p_int_date)')
            ->pluck(DB::raw('DATEDIFF(confi_date, p_int_date) as days'))
            ->all();

        if ($days === []) {
            return null;
        }

        return (int) $days[(int) floor(count($days) / 2)];
    }

    /**
     * Registrations inside this academic year's own dates.
     *
     * The window comes from the institute's `academic_year` rows, never from
     * the calendar year the timestamp happens to fall in.
     */
    private function scopedRegistrations(): \Illuminate\Database\Query\Builder
    {
        $window = $this->yearWindow();

        return DB::table(self::REGISTRATION_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->whereBetween('created_at', [$window['start'].' 00:00:00', $window['end'].' 23:59:59']);
    }
}
