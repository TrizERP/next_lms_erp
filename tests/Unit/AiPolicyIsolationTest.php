<?php

namespace Tests\Unit;

use App\Domain\AI\Support\AiAuditLogger;
use App\Http\Controllers\AI\AiPolicyController;
use App\Services\AI\AiPolicyResolver;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * One institute must not be able to edit or retire another institute's AI policy.
 *
 * WHAT WAS WRONG
 *
 * `AiPolicyController::index()` has always been scoped — it lists this school's policies
 * and the shared platform ones, and nothing else. But `update()` and `destroy()` looked a
 * policy up by id alone and wrote to it. The id in a PUT is supplied by the caller, not
 * chosen from that list, so a school could edit or retire any policy on the estate by
 * naming its id. A scoped read is not a scoped write, and only the second of those stops
 * it.
 *
 * The same lookup also let one school edit a shared platform policy in place, which would
 * have changed the example every other school reads from one school's screen.
 *
 * NO DATABASE, for the reason `PermissionServiceTest` states: phpunit.xml leaves its
 * sqlite lines commented out, so a test that really queried `ai_policies` would be reading
 * and — on the path this test provokes — writing 56 tenants' live policies on the shared
 * estate. The storage read is substituted through the protected `findPolicyRow()` seam,
 * exactly as that test substitutes `rightsFor()`.
 *
 * NOTHING HERE REACHES A WRITE, and that is load-bearing rather than incidental. The cases
 * that go through `update()` and `destroy()` are all REFUSALS: the controller returns
 * before it reaches a write, a rule save, an assignment save or the audit logger, so no
 * other collaborator is touched. The cases that must PASS the guard assert on
 * `readableBy()` directly instead of calling a method that would act — an earlier draft
 * asserted the passing case through `destroy()`, and it retired a real policy on the
 * shared estate.
 *
 * The two paths that do write — a school editing its own policy, and a shared policy
 * forked into a school's own copy — are verified against a real database by
 * `check_module_ai_isolation.php`, inside a transaction it always rolls back.
 */
class AiPolicyIsolationTest extends TestCase
{
    /**
     * A controller whose one storage read returns the row this test chose.
     *
     * @param  array<string, mixed>|null  $policy
     */
    private function controller(?array $policy): AiPolicyController
    {
        $row = $policy === null ? null : (object) $policy;

        return new class($row) extends AiPolicyController
        {
            public function __construct(private readonly ?object $row)
            {
                parent::__construct(new AiPolicyResolver(), app(AiAuditLogger::class));
            }

            protected function findPolicyRow(int $id): ?object
            {
                return $this->row;
            }

            /**
             * The tenant predicate, reachable from a test without going through a write.
             *
             * `destroy()` would answer the same question, but it answers it by retiring
             * the policy — and on this project a test that reaches a write reaches the
             * live shared estate. It did, once, and retired a real row. This exists so
             * the passing case can be asserted on the decision rather than on its effect.
             */
            public function mayAct(object $row, int|string|null $institute): bool
            {
                return $this->readableBy($row, $institute);
            }
        };
    }

    /** A request carrying one institute's context, as the middleware hydrates it. */
    private function request(int $institute, array $body = [], string $method = 'PUT'): Request
    {
        $request = Request::create('/', $method, $body);

        $request->attributes->set('mcp_context', new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: $institute,
            allowedInstituteIds: [$institute],
            userProfileId: 1,
            clientId: null,
            academicYear: null,
            termId: null,
            isAdmin: true,
            isStudent: false,
        ));

        return $request;
    }

    /** A valid edit payload, so a refusal can never be validation failing by accident. */
    private function validEdit(): array
    {
        return [
            'name' => 'Renamed by another school',
            'description' => 'This edit must not land.',
            'policy_type' => 'ai_free',
            'status' => 1,
            'assignments' => [],
        ];
    }

    public function test_a_school_cannot_edit_another_schools_policy(): void
    {
        $controller = $this->controller(['id' => 99, 'sub_institute_id' => 61, 'is_example' => 0]);

        $response = $controller->update($this->request(47, $this->validEdit()), 99);

        // 404, not 403: confirming the id exists is itself a disclosure across the
        // boundary, so another school's policy answers exactly as a missing one.
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('not found', strtolower((string) $response->getContent()));
    }

    public function test_a_school_cannot_retire_another_schools_policy(): void
    {
        $controller = $this->controller(['id' => 99, 'sub_institute_id' => 61, 'is_example' => 0]);

        $response = $controller->destroy($this->request(47, [], 'DELETE'), 99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_a_missing_policy_and_another_schools_policy_are_indistinguishable(): void
    {
        $missing = $this->controller(null)->update($this->request(47, $this->validEdit()), 99);
        $theirs = $this->controller(['id' => 99, 'sub_institute_id' => 61, 'is_example' => 0])
            ->update($this->request(47, $this->validEdit()), 99);

        // Same status and same body. An id probe learns nothing either way, which is the
        // property that makes 404 the right answer rather than a vaguer 403.
        $this->assertSame($missing->getStatusCode(), $theirs->getStatusCode());
        $this->assertSame($missing->getContent(), $theirs->getContent());
    }

    public function test_a_shared_platform_policy_cannot_be_retired_by_one_school(): void
    {
        $controller = $this->controller(['id' => 5, 'sub_institute_id' => null, 'is_example' => 1]);

        $response = $controller->destroy($this->request(47, [], 'DELETE'), 5);

        // 422 and a reason, not a silent success: retiring it would retire the example for
        // every school, and a button that appears to work while doing nothing is worse
        // than one that explains itself.
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('shared example policy', (string) $response->getContent());
    }

    /**
     * The institute comparison is by value, not by type.
     *
     * `selectedInstituteId` is an int on the context and `sub_institute_id` comes back
     * from the database as a string on some drivers. A strict comparison would refuse a
     * school its own policy, which is the failure mode that would have been discovered by
     * an administrator unable to edit anything.
     */
    /**
     * The institute comparison is by value, not by type, and the guard is asserted
     * directly rather than through a method that would write.
     *
     * `selectedInstituteId` is an int on the context and `sub_institute_id` comes back
     * from the database as a string on some drivers. A strict comparison would refuse a
     * school its own policy — an administrator unable to edit anything.
     */
    public function test_a_school_may_act_on_its_own_policy_whatever_the_column_type(): void
    {
        foreach ([47, '47'] as $stored) {
            $controller = $this->controller(null);
            $row = (object) ['id' => 7, 'sub_institute_id' => $stored, 'is_example' => 0];

            $this->assertTrue($controller->mayAct($row, 47), 'stored as '.var_export($stored, true));
            $this->assertTrue($controller->mayAct($row, '47'), 'asked as a string');
        }
    }

    public function test_the_guard_separates_own_shared_and_other(): void
    {
        $controller = $this->controller(null);

        $own = (object) ['sub_institute_id' => 47];
        $shared = (object) ['sub_institute_id' => null];
        $theirs = (object) ['sub_institute_id' => 61];

        $this->assertTrue($controller->mayAct($own, 47), 'a school may act on its own policy');
        $this->assertTrue($controller->mayAct($shared, 47), 'every school may read the shared baseline');
        $this->assertFalse($controller->mayAct($theirs, 47), 'no school may act on another\'s policy');

        // And the boundary is not one-directional: 61 is refused 47's row just as 47 is
        // refused 61's. A guard that only held in the direction it was written for would
        // pass the test above and still leak.
        $this->assertFalse($controller->mayAct($own, 61));
        $this->assertTrue($controller->mayAct($theirs, 61));
    }
}
