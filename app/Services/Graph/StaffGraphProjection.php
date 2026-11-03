<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * `tbluser` -> :Staff, the single canonical label for every row.
 *
 * -----------------------------------------------------------------------------
 * :TEACHER IS GONE — HERE IS WHY THAT'S CORRECT, NOT A REGRESSION
 * -----------------------------------------------------------------------------
 * This table used to split into :Teacher (the 118 rows a one-off reference
 * CSV ingest had claimed before :Staff existed) and :Staff (everyone else),
 * purely to avoid relabelling those 118 people out from under themselves.
 * That historical accident is now moot: the 118 :Teacher nodes it existed to
 * protect are gone (wiped by a graph reset, and the reference script that
 * originated them is permanently blocked from re-running — see
 * `CypherRunCommand`'s 00_/01_ guard), and nothing in the live system can
 * ever create a :Teacher node again — this class's own old `labelFor()`
 * could only ever PRESERVE a pre-existing one, never originate one.
 *
 * Reviving :Teacher as a real sibling label would also be the wrong fix even
 * if it were revived cleanly: "is this person a teacher" is an ATTRIBUTE that
 * can change over someone's tenure, not a different kind of node — and other
 * modules (Performance, Mobility, Competency) need to query "any employee"
 * generically, not "only non-teachers". So the real, current signal
 * (`user_profile_id` -> `tbluserprofilemaster.name = 'Teacher'`, the same
 * join `EmployeeDirectoryController::teacherList()` already trusts) is
 * exposed as the `is_teacher` boolean property below, on the one canonical
 * :Staff node every tbluser row becomes.
 *
 * -----------------------------------------------------------------------------
 * SECRETS
 * -----------------------------------------------------------------------------
 * `password`, `plain_password`, `otp`, `login_ip`, `account_no`, `ifsc_code`,
 * `pan_no` and `aadhar_no` are columns on this table and are deliberately NOT
 * projected. Neo4j Community has no role-based access control and one shared
 * credential; a copy of those columns there is a liability with no traversal
 * value. The same rule the module scripts follow.
 */
class StaffGraphProjection implements GraphProjection
{
    private const TABLE = 'tbluser';
    private const LABEL = 'Staff';

    /** tbluserprofilemaster.id => is its name 'Teacher'?, for the life of one drain pass. */
    private array $teacherProfileCache = [];

    public function __construct(
        private readonly GraphOutbox $outbox,
        private readonly Neo4jService $neo4j,
    ) {
    }

    public function tables(): array
    {
        return [self::TABLE];
    }

    public function labels(): array
    {
        return [self::LABEL];
    }

    public function enqueue(string $table, int $recordId, array $hints = []): array
    {
        $row = DB::table(self::TABLE)->where('id', $recordId)->first();

        if (! $row) {
            throw new RuntimeException('tbluser row ' . $recordId . ' not found');
        }

        $row = (array) $row;
        $label = self::LABEL;
        $tenant = $this->intOrNull($row['sub_institute_id'] ?? null);

        $log = [$this->outbox->node($label, $recordId, $this->properties($row, $recordId, $label))];

        $queue = [];

        // Role and department are uid-only labels; the drain resolves them
        // through GraphSchema's uid fallback, taking the tenant from this node.
        if ($roleId = $this->intOrNull($row['user_profile_id'] ?? null)) {
            $queue[] = $this->outbox->relationship($label, $recordId, 'HAS_ROLE', 'Role', $roleId);
        }

        if ($departmentId = $this->intOrNull($row['department_id'] ?? null)) {
            $queue[] = $this->outbox->relationship($label, $recordId, 'IN_DEPARTMENT', 'Department', $departmentId);
        }

        // :Institute is keyed `Institute:<tenant>:0:<tenant>` — the institute id
        // IS the tenant, which is why the target id is the tenant itself.
        if ($tenant !== null) {
            $queue[] = $this->outbox->relationship($label, $recordId, 'WORKS_AT', 'Institute', $tenant);
        }

        $managerId = $this->intOrNull($row['reporting_manager_id'] ?? null);

        if ($managerId !== null && $managerId !== $recordId) {
            // The manager may sit under either label; the drain's sibling
            // resolution picks whichever one actually holds them.
            $queue[] = $this->outbox->relationship($label, $recordId, 'REPORTS_TO', 'Staff', $managerId);
        }

        return ['log' => $log, 'queue' => $queue];
    }

    public function delete(string $table, int $recordId, array $hints = []): array
    {
        return [
            'log' => [
                $this->outbox->node(self::LABEL, $recordId, [], 'DELETE'),
            ],
            'queue' => [],
        ];
    }

    public function entityKey(string $table, int $recordId, array $hints = []): string
    {
        return self::TABLE . ':' . $recordId;
    }

    public function enqueueNode(string $label, int $nodeId): array
    {
        if ($label !== self::LABEL) {
            return ['log' => [], 'queue' => []];
        }

        if (! DB::table(self::TABLE)->where('id', $nodeId)->exists()) {
            return ['log' => [], 'queue' => []];
        }

        return $this->enqueue(self::TABLE, $nodeId);
    }

    // -----------------------------------------------------------------------

    /**
     * The real "is this person a teacher" signal — `user_profile_id` joined
     * to `tbluserprofilemaster.name = 'Teacher'`, the same join
     * `EmployeeDirectoryController::teacherList()` already trusts. Cached per
     * profile id for the life of one drain pass (a batch of 500 staff events
     * typically carries only a handful of distinct profiles).
     *
     * A Neo4j... no — a DB failure here falls through to `false` rather than
     * throwing, same reasoning the old label lookup used: a missed
     * `is_teacher` flag is a property reconcile can fix later, not a reason
     * to stall the whole drain pass behind one lookup.
     */
    private function isTeacherProfile(?int $userProfileId): bool
    {
        if ($userProfileId === null) {
            return false;
        }

        if (isset($this->teacherProfileCache[$userProfileId])) {
            return $this->teacherProfileCache[$userProfileId];
        }

        $isTeacher = false;

        try {
            $isTeacher = DB::table('tbluserprofilemaster')
                ->where('id', $userProfileId)
                ->where('name', 'Teacher')
                ->exists();
        } catch (Throwable $e) {
            // Leave $isTeacher as false; see the note above.
        }

        return $this->teacherProfileCache[$userProfileId] = $isTeacher;
    }

    private function properties(array $row, int $recordId, string $label): array
    {
        $key = GraphSchema::key($label);

        $props = [$key => $recordId];

        $strings = [
            'user_name', 'first_name', 'middle_name', 'last_name', 'email', 'mobile',
            'gender', 'employee_no', 'qualification', 'occupation', 'joined_date',
            'relieving_date', 'subject_ids', 'allocated_standards', 'status',
        ];

        foreach ($strings as $column) {
            $value = $row[$column] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                $props[$column] = trim((string) $value);
            }
        }

        foreach (['user_profile_id', 'department_id', 'jobtitle_id', 'reporting_manager_id',
                  'total_lecture', 'is_admin', 'client_id', 'sub_institute_id'] as $column) {
            $value = $this->intOrNull($row[$column] ?? null);

            if ($value !== null) {
                $props[$column] = $value;
            }
        }

        $props['is_teacher'] = $this->isTeacherProfile($this->intOrNull($row['user_profile_id'] ?? null));

        $props['displayLabel'] = 'Staff:' . trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? ''));

        $props['src'] = self::TABLE;
        $props[$key] = $recordId;

        return $props;
    }

    private function intOrNull($value): ?int
    {
        return (is_numeric($value) && (int) $value > 0) ? (int) $value : null;
    }
}
