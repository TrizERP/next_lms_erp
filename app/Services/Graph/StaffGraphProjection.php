<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * `tbluser` -> :Teacher for the 118 the reference ingest already claimed,
 *              :Staff for everybody else.
 *
 * -----------------------------------------------------------------------------
 * WHY THIS CANNOT BE A COLUMN MAP
 * -----------------------------------------------------------------------------
 * Every other straightforward entity is declared as data in `config/neo4j.php`
 * and served by TableGraphProjection, because one table maps to one label. This
 * one does not: `tbluser` maps to TWO labels, and which one a given row belongs
 * to is a fact about the GRAPH, not about the row.
 *
 * The reference script (`00_k12_reference.cypher`) loaded `tbluser` into
 * :Teacher, but from a CSV holding only 118 rows. The table has 4,771. When the
 * eight module scripts were written the remaining 4,653 became :Staff, because
 * putting all of them into :Teacher would have labelled the accounts clerk a
 * teacher, and creating :Staff for all of them would have put those 118 people
 * in the graph twice.
 *
 * So the rule is: if this id is already a :Teacher, keep it a :Teacher. If not,
 * it is :Staff. A person stays one node, and the label they were first given
 * never changes underneath them.
 *
 * -----------------------------------------------------------------------------
 * WHY IT ASKS NEO4J RATHER THAN GUESSING FROM `user_profile_id`
 * -----------------------------------------------------------------------------
 * `tbluser.user_profile_id` looks like it should say who is a teacher, and it
 * nearly does — profile 1 is 'Teacher' for 3,561 rows. But the 118 :Teacher
 * nodes that exist carry only five distinct `user_profile_id` values and were
 * seeded from one tenant's CSV, so the two sets do not coincide. Deriving the
 * label from the column would relabel people the graph already holds. Asking
 * the graph is the only answer that cannot drift.
 *
 * The lookup is cached per drain pass — a batch of 500 staff events would
 * otherwise be 500 round trips.
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

    /** tbluser.id => 'Teacher' | 'Staff', for the life of one drain pass. */
    private array $labelCache = [];

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
        return ['Staff', 'Teacher'];
    }

    public function enqueue(string $table, int $recordId, array $hints = []): array
    {
        $row = DB::table(self::TABLE)->where('id', $recordId)->first();

        if (! $row) {
            throw new RuntimeException('tbluser row ' . $recordId . ' not found');
        }

        $row = (array) $row;
        $label = $this->labelFor($recordId);
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
        // The row is gone, so the graph cannot be asked which label held it.
        // Emitting a delete for both is safe: a DETACH DELETE that matches
        // nothing is a no-op, and exactly one of the two can ever match.
        return [
            'log' => [
                $this->outbox->node('Staff', $recordId, [], 'DELETE'),
                $this->outbox->node('Teacher', $recordId, [], 'DELETE'),
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
        if ($label !== 'Staff' && $label !== 'Teacher') {
            return ['log' => [], 'queue' => []];
        }

        if (! DB::table(self::TABLE)->where('id', $nodeId)->exists()) {
            return ['log' => [], 'queue' => []];
        }

        return $this->enqueue(self::TABLE, $nodeId);
    }

    // -----------------------------------------------------------------------

    /**
     * :Teacher when the graph already holds this person as one, :Staff otherwise.
     *
     * A Neo4j failure falls through to :Staff rather than throwing. That is the
     * safe side of the choice: :Staff is where 4,653 of the 4,771 belong, and a
     * wrong :Staff node is a duplicate that reconcile can find, whereas a thrown
     * exception would stall the whole drain pass behind one unreachable lookup.
     */
    private function labelFor(int $userId): string
    {
        if (isset($this->labelCache[$userId])) {
            return $this->labelCache[$userId];
        }

        $label = 'Staff';

        try {
            $result = $this->neo4j->run(
                'MATCH (t:Teacher {teacherId: $id}) RETURN count(t) AS c',
                ['id' => $userId]
            );

            foreach ($result as $record) {
                if ((int) $record->get('c') > 0) {
                    $label = 'Teacher';
                }

                break;
            }
        } catch (Throwable $e) {
            // Leave $label as 'Staff'; see the note above.
        }

        return $this->labelCache[$userId] = $label;
    }

    /**
     * The property set is the same either way apart from the unique key, so a
     * person keeps the same shape whichever label they landed in.
     */
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

        $props['displayLabel'] = $label === 'Teacher'
            // The 118 existing :Teacher nodes carry `Teacher:<user_profile_id>`;
            // reproducing it keeps their label stable across a re-sync.
            ? 'Teacher:' . trim((string) ($row['user_profile_id'] ?? $recordId))
            : 'Staff:' . trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? ''));

        $props['src'] = self::TABLE;
        $props[$key] = $recordId;

        return $props;
    }

    private function intOrNull($value): ?int
    {
        return (is_numeric($value) && (int) $value > 0) ? (int) $value : null;
    }
}
