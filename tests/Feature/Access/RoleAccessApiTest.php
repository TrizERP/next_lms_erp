<?php

namespace Tests\Feature\Access;

use App\Services\Access\RoleRightsService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Roles & Access decides what everybody else can open, so the first thing worth proving is that
 * nobody gets in without a token.
 *
 * These tests write nothing. Every request is sent with no Authorization header, and the
 * controller validates the token before it reads or writes anything. The rules that decide what
 * a save means are checked on the pure functions of the service, with no database. The full
 * write path (grant, parent menus, copy, undo) was exercised against a temporary role.
 */
class RoleAccessApiTest extends TestCase
{
    /** @return array<string, array{0:string,1:string}> */
    public static function routes(): array
    {
        return [
            'roles' => ['GET', 'api/role-access/roles'],
            'role modules' => ['GET', 'api/role-access/roles/1/modules'],
            'save rights' => ['POST', 'api/role-access/roles/1/rights'],
            'copy preview' => ['POST', 'api/role-access/roles/1/copy/preview'],
            'copy apply' => ['POST', 'api/role-access/roles/1/copy/apply'],
            'user search' => ['GET', 'api/role-access/users'],
            'effective access' => ['GET', 'api/role-access/users/1/effective'],
            'coverage' => ['GET', 'api/role-access/coverage'],
            'history' => ['GET', 'api/role-access/history'],
            'history detail' => ['GET', 'api/role-access/history/00000000-0000-0000-0000-000000000000'],
            'undo' => ['POST', 'api/role-access/history/00000000-0000-0000-0000-000000000000/undo'],
        ];
    }

    #[DataProvider('routes')]
    public function test_every_route_refuses_a_request_with_no_token(string $method, string $uri): void
    {
        $response = $this->json($method, '/'.$uri, [
            'sub_institute_id' => 1,
            'user_id' => 1,
            'changes' => [['menu_id' => 1, 'view' => true]],
            'source_profile_id' => 2,
            'mode' => 'merge',
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            "$method $uri answered {$response->getStatusCode()} to a request with no token."
        );
    }

    public function test_doing_anything_to_a_menu_requires_seeing_it(): void
    {
        $service = new RoleRightsService();

        $this->assertSame(
            ['view' => true, 'add' => true, 'edit' => false, 'delete' => false],
            $service->normalise(['add' => true])
        );
        $this->assertSame(
            ['view' => true, 'add' => false, 'edit' => false, 'delete' => true],
            $service->normalise(['delete' => true])
        );
        $this->assertSame(
            ['view' => false, 'add' => false, 'edit' => false, 'delete' => false],
            $service->normalise([])
        );
    }

    public function test_dashboard_and_mobile_imply_view_and_are_only_carried_when_named(): void
    {
        $service = new RoleRightsService();

        $mobile = $service->normalise(['mobile' => true]);
        $this->assertTrue($mobile['view']);
        $this->assertTrue($mobile['mobile']);
        $this->assertArrayNotHasKey('dashboard', $mobile);

        // A change that does not mention them must leave what the menu already has.
        $this->assertArrayNotHasKey('mobile', $service->normalise(['view' => true, 'add' => true]));
        $this->assertArrayNotHasKey('dashboard', $service->normalise(['view' => true]));

        // Naming one as off does not by itself grant anything.
        $off = $service->normalise(['mobile' => false]);
        $this->assertFalse($off['view']);
        $this->assertFalse($off['mobile']);
    }

    public function test_the_mobile_flag_is_part_of_the_state_so_changing_it_is_a_change(): void
    {
        $service = new RoleRightsService();
        $web = ['view' => true, 'add' => true, 'edit' => true, 'delete' => true, 'dashboard' => false, 'mobile' => false];

        $this->assertFalse($service->same($web, ['mobile' => true] + $web));
        $this->assertTrue($service->same($web, $web));
    }

    public function test_a_row_with_every_flag_off_is_not_a_grant(): void
    {
        $service = new RoleRightsService();

        $this->assertFalse($service->grants(['view' => false, 'add' => false, 'edit' => false, 'delete' => false]));
        $this->assertTrue($service->grants(['view' => true, 'add' => false, 'edit' => false, 'delete' => false]));
        $this->assertTrue($service->grants(['view' => false, 'add' => false, 'edit' => true, 'delete' => false]));
    }

    public function test_two_states_are_the_same_only_when_both_are_absent_or_every_flag_matches(): void
    {
        $service = new RoleRightsService();
        $view = ['view' => true, 'add' => false, 'edit' => false, 'delete' => false, 'dashboard' => false];

        $this->assertTrue($service->same(null, null));
        $this->assertFalse($service->same(null, $view));
        $this->assertFalse($service->same($view, null));
        $this->assertTrue($service->same($view, $view));
        $this->assertFalse($service->same($view, ['edit' => true] + $view));
        // The dashboard right is part of the state: dropping it is a change.
        $this->assertFalse($service->same($view, ['dashboard' => true] + $view));
    }
}
