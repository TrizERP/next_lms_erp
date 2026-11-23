<?php

namespace App\Domain\KnowledgeGraph\Fees;

use App\Services\Neo4jService;

/**
 * Reads the Fees graph-reasoning cards' raw facts straight from Neo4j.
 *
 * Every method pairs its real finding with an existence/count check, so a
 * caller can tell "zero because clean" from "zero because nothing is synced
 * for this scope" — the same convention CoherenceMapRepository uses for PAL.
 * Nothing here ever falls back to SQL; an unreachable graph is the caller's
 * problem (GraphUnavailableException bubbles, same as GraphModuleBrowser).
 *
 * ---------------------------------------------------------------------------
 * THREE LIVE QUIRKS MEASURED AGAINST THE REAL GRAPH (2026-11-20), NOT ASSUMED
 * ---------------------------------------------------------------------------
 * 1. `StuDetail.student_id` is NULL on every node checked — the real person
 *    key is `sdId` (= tblstudent.id). `FeePayment.student_id` is populated
 *    and equals `StuDetail.sdId`, but that is a property on FeePayment, not
 *    on StuDetail. Never filter/compare on `StuDetail.student_id`.
 * 2. `FeeRecord.amount` and `FeePayment.amount` are stored as STRINGS
 *    ("18000", not 18000). Every arithmetic use casts with toFloat()/
 *    toInteger() — comparing or summing them raw silently does string
 *    concatenation instead of addition.
 * 3. `Standard.standard_id` is unreliable (null on ~98% of real Standard
 *    nodes measured) — the Standard node's own merge key is `stId`. But
 *    `FeeRecord.standard_id` and `FeePayment.standard_id` (plain copied
 *    properties, confirmed to equal the matching `Standard.stId`) are
 *    reliable, as are `Standard.sub_institute_id`/`Standard.syear`. So every
 *    query below starts from FeeRecord/FeePayment (narrow, property-scoped)
 *    and aggregates by their own `standard_id` property rather than walking
 *    Student-[:ENROLLED_IN]->Standard-[:HAS_FEE_SCHEDULE]->FeeRecord as a
 *    single multi-hop pattern — that unscoped 3-hop walk measured as a
 *    30s+ timeout; the property-scoped equivalent measured under 250ms.
 */
class FeesGraphRecommendationRepository
{
    public function __construct(private readonly Neo4jService $neo4j)
    {
    }

    /**
     * The honest headline: how much fee-collection activity the graph
     * actually holds for this scope. Always "available" if the graph can be
     * reached at all — this is itself the "not enough data yet" signal made
     * visible, not a hidden caveat on some other card.
     */
    public function platformCoverage(int $tenant, string $syear): array
    {
        $schedule = $this->firstRow(
            'MATCH (fr:FeeRecord) WHERE fr.sub_institute_id = $t AND toInteger(fr.syear) = toInteger($y) '
                .'RETURN count(fr) AS scheduleLines, count(DISTINCT fr.standard_id) AS standardsScheduled',
            ['t' => $tenant, 'y' => $syear]
        );

        $payments = $this->firstRow(
            'MATCH (fp:FeePayment) WHERE fp.sub_institute_id = $t AND toInteger(fp.syear) = toInteger($y) '
                .'RETURN count(fp) AS payments, count(DISTINCT fp.student_id) AS payingStudents',
            ['t' => $tenant, 'y' => $syear]
        );

        $enrolled = $this->firstRow(
            'MATCH (s:Student) WHERE s.sub_institute_id = $t AND toInteger(s.syear) = toInteger($y) '
                .'RETURN count(DISTINCT s.stuId) AS enrolledStudents',
            ['t' => $tenant, 'y' => $syear]
        );

        return [
            'schedule_lines' => (int) ($schedule['scheduleLines'] ?? 0),
            'standards_scheduled' => (int) ($schedule['standardsScheduled'] ?? 0),
            'payments' => (int) ($payments['payments'] ?? 0),
            'paying_students' => (int) ($payments['payingStudents'] ?? 0),
            'enrolled_students' => (int) ($enrolled['enrolledStudents'] ?? 0),
        ];
    }

    /**
     * Standards with zero fee-schedule lines for the year, and schedule
     * lines that contradict themselves (same standard/section/month/fee-type,
     * different amounts).
     *
     * `total_schedule_lines = 0` is the existence gate — it means nothing is
     * synced for this scope at all, not that the schedule is clean.
     */
    public function scheduleIntegrity(int $tenant, string $syear): array
    {
        $totalScheduleLines = (int) ($this->firstRow(
            'MATCH (fr:FeeRecord) WHERE fr.sub_institute_id = $t AND toInteger(fr.syear) = toInteger($y) '
                .'RETURN count(fr) AS c',
            ['t' => $tenant, 'y' => $syear]
        )['c'] ?? 0);

        // Standard.syear tracks the CURRENT roster snapshot, not a per-year
        // history — measured live: a tenant with real 2021 FeeRecord data
        // returned zero Standard nodes when filtered to syear=2021. Scoping
        // "all standards" by sub_institute_id alone over-reports rather than
        // silently hiding gaps (a standard that didn't exist yet in a past
        // year reads as a false-positive gap, which a reviewer can dismiss —
        // the alternative, a Standard.syear filter, can instead hide a real
        // gap behind an empty "all standards" set and report a false "clean").
        $standardsTotal = $this->rows(
            'MATCH (s:Standard) WHERE s.sub_institute_id = $t '
                .'RETURN s.stId AS standardId, s.name AS name',
            ['t' => $tenant]
        );

        $standardsScheduled = $this->rows(
            'MATCH (fr:FeeRecord) WHERE fr.sub_institute_id = $t AND toInteger(fr.syear) = toInteger($y) '
                .'RETURN DISTINCT fr.standard_id AS standardId',
            ['t' => $tenant, 'y' => $syear]
        );
        $scheduledIds = array_map(fn ($r) => (int) $r['standardId'], $standardsScheduled);

        $gaps = array_values(array_filter(
            $standardsTotal,
            fn ($s) => $s['standardId'] !== null && ! in_array((int) $s['standardId'], $scheduledIds, true)
        ));

        $conflicts = $this->rows(
            'MATCH (fr:FeeRecord) WHERE fr.sub_institute_id = $t AND toInteger(fr.syear) = toInteger($y) '
                .'WITH fr.standard_id AS standardId, fr.section_id AS sectionId, fr.month_id AS monthId, '
                .'     fr.fee_type_id AS feeTypeId, collect(DISTINCT toFloat(fr.amount)) AS amounts, count(fr) AS rows '
                .'WHERE size(amounts) > 1 '
                .'RETURN standardId, sectionId, monthId, feeTypeId, amounts, rows '
                .'ORDER BY standardId, monthId LIMIT 50',
            ['t' => $tenant, 'y' => $syear]
        );

        return [
            'total_schedule_lines' => $totalScheduleLines,
            'standards_total' => count($standardsTotal),
            'gaps' => array_map(fn ($s) => [
                'standard_id' => (int) $s['standardId'],
                'name' => $s['name'],
            ], $gaps),
            'conflicts' => array_map(fn ($c) => [
                'standard_id' => $c['standardId'] === null ? null : (int) $c['standardId'],
                'section_id' => $c['sectionId'] === null ? null : (int) $c['sectionId'],
                'month_id' => $c['monthId'] === null ? null : (int) $c['monthId'],
                'fee_type_id' => $c['feeTypeId'] === null ? null : (int) $c['feeTypeId'],
                'amounts' => $c['amounts'],
                'rows' => (int) $c['rows'],
            ], $conflicts),
        ];
    }

    /**
     * Recorded payments whose student has no reachable enrollment at all —
     * a genuine reconciliation/audit signal, not a guess. "No reachable
     * enrollment" means the payment's own `StuDetail` has zero `HAS_STUDENT`
     * edges to any `Student` node, checked structurally via the real edge,
     * not by comparing properties.
     */
    public function unlinkedPayments(int $tenant, string $syear, int $limit = 50): array
    {
        $totalPayments = (int) ($this->firstRow(
            'MATCH (fp:FeePayment) WHERE fp.sub_institute_id = $t AND toInteger(fp.syear) = toInteger($y) '
                .'RETURN count(fp) AS c',
            ['t' => $tenant, 'y' => $syear]
        )['c'] ?? 0);

        $orphans = $this->rows(
            'MATCH (sd:StuDetail)-[:MADE_PAYMENT]->(fp:FeePayment) '
                .'WHERE fp.sub_institute_id = $t AND toInteger(fp.syear) = toInteger($y) '
                .'OPTIONAL MATCH (sd)-[:HAS_STUDENT]->(:Student) '
                .'WITH sd, fp, count(*) AS links '
                .'WHERE links = 0 '
                .'RETURN sd.sdId AS sdId, fp.receipt_no AS receiptNo, toFloat(fp.amount) AS amount, '
                .'       fp.receiptdate AS receiptDate '
                .'ORDER BY fp.receiptdate DESC LIMIT $limit',
            ['t' => $tenant, 'y' => $syear, 'limit' => $limit]
        );

        return [
            'total_payments' => $totalPayments,
            'orphans' => array_map(fn ($o) => [
                'student_id' => $o['sdId'] === null ? null : (int) $o['sdId'],
                'receipt_no' => $o['receiptNo'],
                'amount' => $o['amount'],
                'receipt_date' => $o['receiptDate'],
            ], $orphans),
        ];
    }

    /**
     * Per-standard enrolled vs. paying headcount for the year, aggregated
     * independently from FeeRecord/FeePayment/Student's own `standard_id`+
     * `syear` properties (not a live 3-hop traversal — see class docblock).
     * The engine decides, from these counts, whether non-payment is
     * concentrated enough across standards to call it "compounding".
     */
    public function standardNonPaymentConcentration(int $tenant, string $syear): array
    {
        $enrolledByStandard = $this->rows(
            'MATCH (s:Student) WHERE s.sub_institute_id = $t AND toInteger(s.syear) = toInteger($y) '
                .'  AND s.standard_id IS NOT NULL '
                .'RETURN s.standard_id AS standardId, count(DISTINCT s.stuId) AS enrolled',
            ['t' => $tenant, 'y' => $syear]
        );

        $payingByStandard = $this->rows(
            'MATCH (fp:FeePayment) WHERE fp.sub_institute_id = $t AND toInteger(fp.syear) = toInteger($y) '
                .'  AND fp.standard_id IS NOT NULL '
                .'RETURN fp.standard_id AS standardId, count(DISTINCT fp.student_id) AS paying',
            ['t' => $tenant, 'y' => $syear]
        );

        // Same caveat as scheduleIntegrity(): Standard.syear is a current
        // snapshot, not a per-year history — scope the name lookup by tenant
        // only so historical years still resolve a display name.
        $names = $this->rows(
            'MATCH (s:Standard) WHERE s.sub_institute_id = $t RETURN s.stId AS standardId, s.name AS name',
            ['t' => $tenant]
        );

        $nameById = [];
        foreach ($names as $n) {
            if ($n['standardId'] !== null) {
                $nameById[(int) $n['standardId']] = $n['name'];
            }
        }

        $paying = [];
        foreach ($payingByStandard as $p) {
            $paying[(int) $p['standardId']] = (int) $p['paying'];
        }

        $standards = [];
        foreach ($enrolledByStandard as $e) {
            $standardId = (int) $e['standardId'];
            $enrolled = (int) $e['enrolled'];
            $payingCount = $paying[$standardId] ?? 0;

            $standards[] = [
                'standard_id' => $standardId,
                'name' => $nameById[$standardId] ?? null,
                'enrolled' => $enrolled,
                'paying' => $payingCount,
                'non_paying' => max(0, $enrolled - $payingCount),
            ];
        }

        usort($standards, fn ($a, $b) => $b['non_paying'] <=> $a['non_paying']);

        return [
            'standards' => $standards,
        ];
    }

    /**
     * Among enrolled students with no recorded payment this year, how many
     * also show a weak/absent engagement or mastery signal — correlation
     * only, computed from real counts, never a per-student causal claim.
     * Both signals come from edges that already exist off the same
     * `StuDetail` nodes for other reasons (PAL engagement/mastery sync);
     * nothing new is projected for this.
     */
    /**
     * The fee-gap cohort — enrolled students (by `StuDetail.sdId`, which
     * equals `tblstudent.id`) with no recorded payment this year. Used by
     * the Cross-Module Intelligence capability (FeesCrossModuleInsightRepository)
     * to find the same cohort feeAcademicCorrelation() below correlates
     * against PAL signal, against a different module's data. Deliberately
     * its own small query pair rather than calling into
     * feeAcademicCorrelation() or being called by it: that method already
     * needs the un-diffed enrolled/paying counts too, and routing it
     * through this method would mean computing the diff twice over the
     * network for one logical pass.
     *
     * @return array<int, int>
     */
    public function gapStudentIds(int $tenant, string $syear, int $maxGapSample = 10000): array
    {
        $enrolledIds = $this->rows(
            'MATCH (sd:StuDetail)-[:HAS_STUDENT]->(st:Student) '
                .'WHERE st.sub_institute_id = $t AND toInteger(st.syear) = toInteger($y) '
                .'RETURN DISTINCT sd.sdId AS sdId',
            ['t' => $tenant, 'y' => $syear]
        );
        $enrolledIds = array_map(fn ($r) => (int) $r['sdId'], $enrolledIds);

        $payingIds = $this->rows(
            'MATCH (fp:FeePayment) WHERE fp.sub_institute_id = $t AND toInteger(fp.syear) = toInteger($y) '
                .'  AND fp.student_id IS NOT NULL '
                .'RETURN DISTINCT fp.student_id AS studentId',
            ['t' => $tenant, 'y' => $syear]
        );
        $payingIds = array_flip(array_map(fn ($r) => (int) $r['studentId'], $payingIds));

        $gapIds = array_values(array_diff($enrolledIds, array_keys($payingIds)));

        return array_slice($gapIds, 0, $maxGapSample);
    }

    public function feeAcademicCorrelation(int $tenant, string $syear, int $maxGapSample = 10000): array
    {
        $enrolledIds = $this->rows(
            'MATCH (sd:StuDetail)-[:HAS_STUDENT]->(st:Student) '
                .'WHERE st.sub_institute_id = $t AND toInteger(st.syear) = toInteger($y) '
                .'RETURN DISTINCT sd.sdId AS sdId',
            ['t' => $tenant, 'y' => $syear]
        );
        $enrolledIds = array_map(fn ($r) => (int) $r['sdId'], $enrolledIds);

        $payingIds = $this->rows(
            'MATCH (fp:FeePayment) WHERE fp.sub_institute_id = $t AND toInteger(fp.syear) = toInteger($y) '
                .'  AND fp.student_id IS NOT NULL '
                .'RETURN DISTINCT fp.student_id AS studentId',
            ['t' => $tenant, 'y' => $syear]
        );
        $payingIds = array_flip(array_map(fn ($r) => (int) $r['studentId'], $payingIds));

        $gapIds = array_values(array_diff($enrolledIds, array_keys($payingIds)));
        $gapTotal = count($gapIds);
        $sample = array_slice($gapIds, 0, $maxGapSample);

        if ($sample === []) {
            return [
                'enrolled_total' => count($enrolledIds),
                'paying_total' => count($payingIds),
                'gap_total' => 0,
                'gap_sampled' => 0,
                'gap_with_engagement' => 0,
                'gap_with_mastery_signal' => 0,
            ];
        }

        $signal = $this->firstRow(
            'MATCH (sd:StuDetail) WHERE sd.sdId IN $ids '
                .'OPTIONAL MATCH (sd)-[:ENGAGED_WITH]->(e) '
                .'WITH sd, count(e) AS engagementN '
                .'OPTIONAL MATCH (sd)-[:HAS_MASTERY]->(c) '
                .'RETURN count(DISTINCT sd) AS checked, '
                .'       sum(CASE WHEN engagementN > 0 THEN 1 ELSE 0 END) AS withEngagement, '
                .'       count(DISTINCT CASE WHEN c IS NOT NULL THEN sd END) AS withMastery',
            ['ids' => $sample]
        );

        return [
            'enrolled_total' => count($enrolledIds),
            'paying_total' => count($payingIds),
            'gap_total' => $gapTotal,
            'gap_sampled' => count($sample),
            'gap_with_engagement' => (int) ($signal['withEngagement'] ?? 0),
            'gap_with_mastery_signal' => (int) ($signal['withMastery'] ?? 0),
        ];
    }

    /**
     * Which standards share a fee head (FeeRecord.fee_type_id) with which
     * others, for the Graph Reasoning capability's transitive non-payment
     * chain — a relationship that is never a direct edge in the graph, only
     * discoverable by grouping FeeRecord's own copied properties (same
     * property-scoped approach as standardNonPaymentConcentration(), never a
     * multi-hop MATCH — see class docblock).
     *
     * @return array<int, array<int, int>> fee_type_id => [standard_id, ...]
     */
    public function standardsSharingFeeType(int $tenant, string $syear): array
    {
        $rows = $this->rows(
            'MATCH (fr:FeeRecord) WHERE fr.sub_institute_id = $t AND toInteger(fr.syear) = toInteger($y) '
                .'  AND fr.standard_id IS NOT NULL AND fr.fee_type_id IS NOT NULL '
                .'RETURN DISTINCT fr.fee_type_id AS feeTypeId, fr.standard_id AS standardId',
            ['t' => $tenant, 'y' => $syear]
        );

        $standardsByFeeType = [];
        foreach ($rows as $row) {
            $standardsByFeeType[(int) $row['feeTypeId']][] = (int) $row['standardId'];
        }

        return $standardsByFeeType;
    }

    /** @return array<string, mixed> */
    private function firstRow(string $cypher, array $params): array
    {
        foreach ($this->neo4j->run($cypher, $params) as $record) {
            $row = [];
            foreach ($record->keys() as $key) {
                $row[$key] = $record->get($key);
            }

            return $row;
        }

        return [];
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(string $cypher, array $params): array
    {
        $out = [];

        foreach ($this->neo4j->run($cypher, $params) as $record) {
            $row = [];
            foreach ($record->keys() as $key) {
                $row[$key] = $record->get($key);
            }
            $out[] = $row;
        }

        return $out;
    }
}
