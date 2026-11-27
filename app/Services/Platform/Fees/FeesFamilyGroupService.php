<?php

namespace App\Services\Platform\Fees;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Family/sibling grouping for the Fees Intelligence banner.
 *
 * There is no usable guardian-link graph anywhere in this estate — not in
 * SQL (`tblstudent_siblings` has 2 rows total; `tblstudent_family_history`
 * is each student's own family/education background, not a cross-student
 * link) and not in Neo4j (`CHILD_OF`/`Parent` were never synced; `SIBLING_OF`
 * mirrors the same 2 SQL rows; `GUARDIAN_OF` is a stale one-time snapshot).
 * A shared guardian mobile number on `tblstudent` is the only real, live
 * signal, so that is the group key — normalised to its last 10 digits so
 * "98765 43210" and "+91-9876543210" group together.
 *
 * This is a real signal with real false-positive risk (a front-desk or
 * admission-agent number reused across unrelated families), so groups are
 * flagged, never silently merged away: `oversized` (more students sharing a
 * number than a household plausibly has), `mixed_guardian` (the group's own
 * father_name values disagree once normalised), and `duplicate_name` (two
 * members share the exact same full name). The last one was found live,
 * not theorised: a group's own two "members" turned out to be one real
 * child on two `tblstudent` rows — a re-admission or a legacy/current
 * enrolment kept active side by side (e.g. a "PP..." pre-primary enrolment
 * number next to a newer numeric one) — not a sibling pair at all. Flagged
 * for a human to resolve, never merged or dropped automatically.
 */
class FeesFamilyGroupService
{
    /** More active students than this sharing one number reads as an office/agent number, not a household. */
    private const OVERSIZED_THRESHOLD = 6;

    /** A family detail view is a drill-down, not a cohort report — bound how many members it resolves. */
    private const DRILLDOWN_MEMBER_CAP = 10;

    private const GROUP_KEY_EXPR = "RIGHT(REGEXP_REPLACE(mobile, '[^0-9]', ''), 10)";

    /**
     * @return array<string, mixed>
     */
    public function summary(int $tenantId, ?int $syear): array
    {
        $groups = $this->loadGroups($tenantId);
        $totalActive = $this->activeStudentsQuery($tenantId)->count();

        if ($groups->isEmpty()) {
            $noContact = $this->activeStudentsQuery($tenantId)
                ->where(function ($q) {
                    $q->whereNull('mobile')->orWhere('mobile', '');
                })->count();

            return [
                'available' => true,
                'family_groups' => 0,
                'students_in_groups' => 0,
                'already_applied' => 0,
                'pending' => 0,
                'ungrouped_students' => $totalActive - $noContact,
                'no_contact_students' => $noContact,
                'families_every_applied' => 0,
                'families_some_applied' => 0,
                'families_none_applied' => 0,
                'ambiguous' => ['oversized_threshold' => self::OVERSIZED_THRESHOLD, 'oversized_groups' => 0, 'oversized_students' => 0, 'mixed_guardian_groups' => 0, 'mixed_guardian_students' => 0, 'duplicate_name_groups' => 0, 'duplicate_name_students' => 0],
                'applied_scope' => $syear !== null ? 'academic_year' : 'all_years',
            ];
        }

        $allMemberIds = $groups->flatMap(fn (array $g) => $g['student_ids'])->unique()->values()->all();
        $members = $this->memberAttributes($tenantId, $allMemberIds);
        $appliedIds = $this->appliedStudentIds($tenantId, $allMemberIds, $syear);

        $everyApplied = 0;
        $someApplied = 0;
        $noneApplied = 0;
        $oversizedGroups = 0;
        $oversizedStudents = 0;
        $mixedGroups = 0;
        $mixedStudents = 0;
        $duplicateGroups = 0;
        $duplicateStudents = 0;

        foreach ($groups as $group) {
            $ids = $group['student_ids'];
            $appliedCount = count(array_intersect($ids, $appliedIds));
            if ($appliedCount === count($ids)) {
                $everyApplied++;
            } elseif ($appliedCount > 0) {
                $someApplied++;
            } else {
                $noneApplied++;
            }

            if (count($ids) > self::OVERSIZED_THRESHOLD) {
                $oversizedGroups++;
                $oversizedStudents += count($ids);
            }

            if ($this->isMixedGuardian($ids, $members)) {
                $mixedGroups++;
                $mixedStudents += count($ids);
            }

            $duplicateCount = $this->duplicateNameCount($ids, $members);
            if ($duplicateCount > 0) {
                $duplicateGroups++;
                $duplicateStudents += $duplicateCount;
            }
        }

        $studentsInGroups = $allMemberIds;
        $noContact = $this->activeStudentsQuery($tenantId)
            ->where(function ($q) {
                $q->whereNull('mobile')->orWhere('mobile', '');
            })->count();

        return [
            'available' => true,
            'family_groups' => $groups->count(),
            'students_in_groups' => count($studentsInGroups),
            'already_applied' => count($appliedIds),
            'pending' => max(0, count($studentsInGroups) - count($appliedIds)),
            'ungrouped_students' => max(0, $totalActive - count($studentsInGroups) - $noContact),
            'no_contact_students' => $noContact,
            'families_every_applied' => $everyApplied,
            'families_some_applied' => $someApplied,
            'families_none_applied' => $noneApplied,
            'ambiguous' => [
                'oversized_threshold' => self::OVERSIZED_THRESHOLD,
                'oversized_groups' => $oversizedGroups,
                'oversized_students' => $oversizedStudents,
                'mixed_guardian_groups' => $mixedGroups,
                'mixed_guardian_students' => $mixedStudents,
                'duplicate_name_groups' => $duplicateGroups,
                'duplicate_name_students' => $duplicateStudents,
            ],
            'applied_scope' => $syear !== null ? 'academic_year' : 'all_years',
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function families(int $tenantId, int $page, int $perPage, string $appliedFilter, string $flag, ?int $syear): array
    {
        $groups = $this->loadGroups($tenantId);
        $allMemberIds = $groups->flatMap(fn (array $g) => $g['student_ids'])->unique()->values()->all();
        $members = $this->memberAttributes($tenantId, $allMemberIds);
        $appliedIds = $this->appliedStudentIds($tenantId, $allMemberIds, $syear);
        $channelByStudent = $this->channelReachByStudent($tenantId, $allMemberIds);

        $rows = $groups->map(function (array $group) use ($members, $appliedIds, $channelByStudent) {
            $ids = $group['student_ids'];
            $appliedCount = count(array_intersect($ids, $appliedIds));
            $flags = [];
            if (count($ids) > self::OVERSIZED_THRESHOLD) {
                $flags[] = 'oversized';
            }
            if ($this->isMixedGuardian($ids, $members)) {
                $flags[] = 'mixed_guardian';
            }
            if ($this->duplicateNameCount($ids, $members) > 0) {
                $flags[] = 'duplicate_name';
            }

            return [
                'anchor_student_id' => min($ids),
                'masked_mobile' => $this->maskMobile($group['mobile']),
                'member_count' => count($ids),
                'applied_count' => $appliedCount,
                'applied_state' => $appliedCount === 0 ? 'none' : ($appliedCount === count($ids) ? 'every' : 'some'),
                'flags' => $flags,
                'members' => array_map(fn (int $id) => [
                    'student_id' => $id,
                    'name' => $members[$id]['name'] ?? null,
                    'enrollment_no' => $members[$id]['enrollment_no'] ?? null,
                    'standard' => $members[$id]['standard'] ?? null,
                    'applied' => in_array($id, $appliedIds, true),
                ], $ids),
                'channel' => [
                    'wa_sent_90d' => array_sum(array_map(fn ($id) => $channelByStudent[$id]['wa_sent_90d'] ?? 0, $ids)),
                    'wa_read_90d' => array_sum(array_map(fn ($id) => $channelByStudent[$id]['wa_read_90d'] ?? 0, $ids)),
                    'dm_replied_90d' => array_sum(array_map(fn ($id) => $channelByStudent[$id]['dm_replied_90d'] ?? 0, $ids)),
                ],
            ];
        });

        if ($appliedFilter !== '' && $appliedFilter !== 'all') {
            $rows = $rows->filter(fn (array $r) => $r['applied_state'] === $appliedFilter || ($appliedFilter === 'none_or_some' && $r['applied_state'] !== 'every'));
        }
        if ($flag !== '') {
            $rows = $rows->filter(fn (array $r) => in_array($flag, $r['flags'], true));
        }

        $rows = $rows->values();
        $total = $rows->count();
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => $rows->slice($offset, $perPage)->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * Drill-down for one family, keyed by its anchor (smallest member id) —
     * arrears per member are attached by the caller (FeesIntelligenceService),
     * since that needs FeesArrearsService/McpRequestContext, which this
     * service deliberately does not depend on.
     *
     * @return array<string, mixed>|null
     */
    public function family(int $tenantId, int $anchorStudentId): ?array
    {
        $anchor = DB::table('tblstudent')
            ->where('sub_institute_id', $tenantId)
            ->where('id', $anchorStudentId)
            ->whereNotNull('mobile')->where('mobile', '!=', '')
            ->first(['mobile']);

        if ($anchor === null) {
            return null;
        }

        $groupKey = $this->normalise($anchor->mobile);

        $ids = DB::table('tblstudent')
            ->where('sub_institute_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            })
            ->whereRaw(self::GROUP_KEY_EXPR.' = ?', [$groupKey])
            ->orderBy('id')
            ->limit(self::DRILLDOWN_MEMBER_CAP)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($ids) < 2) {
            return null;
        }

        $members = $this->memberAttributes($tenantId, $ids);
        $appliedIds = $this->appliedStudentIds($tenantId, $ids, null);

        return [
            'anchor_student_id' => min($ids),
            'masked_mobile' => $this->maskMobile($anchor->mobile),
            'member_ids' => $ids,
            'members' => array_map(fn (int $id) => [
                'student_id' => $id,
                'name' => $members[$id]['name'] ?? null,
                'enrollment_no' => $members[$id]['enrollment_no'] ?? null,
                'standard' => $members[$id]['standard'] ?? null,
                'applied' => in_array($id, $appliedIds, true),
            ], $ids),
            'flags' => array_values(array_filter([
                count($ids) > self::OVERSIZED_THRESHOLD ? 'oversized' : null,
                $this->isMixedGuardian($ids, $members) ? 'mixed_guardian' : null,
                $this->duplicateNameCount($ids, $members) > 0 ? 'duplicate_name' : null,
            ])),
        ];
    }

    /**
     * Maps each of the given student ids to its family anchor and family
     * size, for the Arrears/Channel cards' family-wise rollups. Students
     * with no group simply have no key in the result.
     *
     * @param  list<int>  $studentIds
     * @return array<int, array{anchor_student_id: int, size: int}>
     */
    public function familyKeyFor(int $tenantId, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::table('tblstudent')
            ->where('sub_institute_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            })
            ->whereNotNull('mobile')->where('mobile', '!=', '')
            ->select(DB::raw(self::GROUP_KEY_EXPR.' AS group_key'), DB::raw('MIN(id) AS anchor_id'), DB::raw('COUNT(*) AS member_count'), DB::raw('GROUP_CONCAT(id) AS student_ids'))
            ->groupBy('group_key')
            ->having('member_count', '>', 1)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $ids = array_map('intval', explode(',', (string) $row->student_ids));
            if (array_intersect($ids, $studentIds) === []) {
                continue;
            }
            foreach ($ids as $id) {
                $map[$id] = ['anchor_student_id' => (int) $row->anchor_id, 'size' => (int) $row->member_count];
            }
        }

        return array_intersect_key($map, array_flip($studentIds));
    }

    /**
     * Tenant-wide channel-reach rollup across every family group, for the
     * Channel Engagement card's `family_reach` figure.
     *
     * @return array{family_groups: int, reached_90d: int, read_90d: int, replied_90d: int}
     */
    public function familyReachSummary(int $tenantId): array
    {
        $groups = $this->loadGroups($tenantId);
        if ($groups->isEmpty()) {
            return ['family_groups' => 0, 'reached_90d' => 0, 'read_90d' => 0, 'replied_90d' => 0];
        }

        $allMemberIds = $groups->flatMap(fn (array $g) => $g['student_ids'])->unique()->values()->all();
        $channelByStudent = $this->channelReachByStudent($tenantId, $allMemberIds);

        $reached = 0;
        $read = 0;
        $replied = 0;
        foreach ($groups as $group) {
            foreach ($group['student_ids'] as $id) {
                $stats = $channelByStudent[$id] ?? ['wa_sent_90d' => 0, 'wa_read_90d' => 0, 'dm_replied_90d' => 0];
                $reached += $stats['wa_sent_90d'];
                $read += $stats['wa_read_90d'];
                $replied += $stats['dm_replied_90d'];
            }
        }

        return ['family_groups' => $groups->count(), 'reached_90d' => $reached, 'read_90d' => $read, 'replied_90d' => $replied];
    }

    // ---------------------------------------------------------------- internals

    /** @return Collection<int, array{mobile: string, student_ids: list<int>}> */
    private function loadGroups(int $tenantId): Collection
    {
        $rows = $this->activeStudentsQuery($tenantId)
            ->whereNotNull('mobile')->where('mobile', '!=', '')
            ->select(DB::raw(self::GROUP_KEY_EXPR.' AS group_key'), DB::raw('MIN(mobile) AS mobile'), DB::raw('COUNT(*) AS student_count'), DB::raw('GROUP_CONCAT(id) AS student_ids'))
            ->groupBy('group_key')
            ->having('student_count', '>', 1)
            ->get();

        return $rows->map(fn ($row) => [
            'mobile' => (string) $row->mobile,
            'student_ids' => array_map('intval', explode(',', (string) $row->student_ids)),
        ])->values();
    }

    private function activeStudentsQuery(int $tenantId)
    {
        return DB::table('tblstudent')
            ->where('sub_institute_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            });
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{name: ?string, father_name: ?string, enrollment_no: ?string, standard: ?string}>
     */
    private function memberAttributes(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $standardByStudent = DB::table('tblstudent_enrollment as e')
            ->join('standard as st', 'st.id', '=', 'e.standard_id')
            ->where('e.sub_institute_id', $tenantId)
            ->whereIn('e.student_id', $ids)
            ->orderByDesc('e.syear')
            ->select('e.student_id', 'st.name')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->first()->name);

        return DB::table('tblstudent')
            ->whereIn('id', $ids)
            ->select('id', 'first_name', 'middle_name', 'last_name', 'father_name', 'enrollment_no')
            ->get()
            ->keyBy('id')
            ->map(fn ($row) => [
                'name' => trim(implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name]))) ?: null,
                'father_name' => $row->father_name,
                'enrollment_no' => $row->enrollment_no,
                'standard' => $standardByStudent[$row->id] ?? null,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $memberIds
     * @param  array<int, array<string, mixed>>  $members
     */
    private function isMixedGuardian(array $memberIds, array $members): bool
    {
        $normalised = [];
        foreach ($memberIds as $id) {
            $father = $members[$id]['father_name'] ?? null;
            if ($father === null || trim((string) $father) === '') {
                continue;
            }
            $normalised[] = preg_replace('/[^a-z]/', '', strtolower((string) $father));
        }

        return count(array_unique($normalised)) > 1;
    }

    /**
     * How many members share an exact full name with at least one other
     * member — found live on this feature's first real-tenant check: two
     * "members" sharing a mobile number and an identical name were the same
     * child on two `tblstudent` rows (a re-admission), not siblings.
     *
     * @param  list<int>  $memberIds
     * @param  array<int, array<string, mixed>>  $members
     */
    private function duplicateNameCount(array $memberIds, array $members): int
    {
        $counts = [];
        foreach ($memberIds as $id) {
            $name = $members[$id]['name'] ?? null;
            if ($name === null || trim($name) === '') {
                continue;
            }
            $key = preg_replace('/\s+/', ' ', strtolower(trim($name)));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return array_sum(array_filter($counts, fn (int $c) => $c > 1));
    }

    /**
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    private function appliedStudentIds(int $tenantId, array $studentIds, ?int $syear): array
    {
        if ($studentIds === []) {
            return [];
        }

        $query = fn () => DB::table('fees_collect')
            ->whereIn('student_id', $studentIds)
            ->where('sub_institute_id', $tenantId)
            ->where('is_deleted', 'N')
            ->where('fees_discount', '>', 0);

        if ($syear !== null) {
            $scoped = (clone $query())->where('syear', $syear);
            if ($scoped->exists()) {
                return $scoped->distinct()->pluck('student_id')->map(fn ($id) => (int) $id)->all();
            }
        }

        return $query()->distinct()->pluck('student_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{wa_sent_90d: int, wa_read_90d: int, dm_replied_90d: int}>
     */
    private function channelReachByStudent(int $tenantId, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $since = now()->subDays(90);

        $wa = DB::table('whatsapp_sent_messages')
            ->where('sub_institute_id', $tenantId)
            ->whereIn('student_id', $studentIds)
            ->where('sent_date', '>=', $since)
            ->selectRaw('student_id, COUNT(*) AS sent, SUM(message_status LIKE "read%") AS read_count')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $dm = DB::table('parent_communication')
            ->where('sub_institute_id', $tenantId)
            ->whereIn('student_id', $studentIds)
            ->where('date_', '>=', $since)
            ->selectRaw('student_id, SUM(reply IS NOT NULL AND reply <> "") AS replied')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $perStudent = [];
        foreach ($studentIds as $id) {
            $perStudent[$id] = [
                'wa_sent_90d' => (int) ($wa[$id]->sent ?? 0),
                'wa_read_90d' => (int) ($wa[$id]->read_count ?? 0),
                'dm_replied_90d' => (int) ($dm[$id]->replied ?? 0),
            ];
        }

        return $perStudent;
    }

    private function normalise(string $mobile): string
    {
        return substr(preg_replace('/\D/', '', $mobile), -10);
    }

    private function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);
        $last4 = substr($digits, -4);

        return str_repeat('•', max(0, strlen($digits) - 4)).$last4;
    }
}
