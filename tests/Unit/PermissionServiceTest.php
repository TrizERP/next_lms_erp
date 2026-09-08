<?php

namespace Tests\Unit;

use App\Services\Rbac\PermissionService;
use Tests\TestCase;

/**
 * permission_check(user, module, action) — tracker row 5 / Decision #37.
 *
 * NO DATABASE. phpunit.xml has its sqlite/:memory: lines commented out (:24-25), so a test
 * that really queried tblgroupwise_rights would be reading 56 tenants' production rights on
 * the live shared vivek_erp. The storage lookup is substituted through the protected
 * rightsFor() seam instead.
 *
 * The behaviour under test is mostly about what happens when something is MISSING, because
 * that is precisely where the existing checkPermission middleware gets it wrong: its
 * "no rights row at all" rejection is commented out (checkPermission.php:66-69), so today a
 * user with no grant is allowed through.
 */
class PermissionServiceTest extends TestCase
{
    /** @param array<string,mixed>|null $rights */
    private function service(?array $rights): PermissionService
    {
        return new class($rights) extends PermissionService {
            public function __construct(private ?array $rights)
            {
            }

            protected function rightsFor(int $userId, ?int $profileId, int|string $subInstituteId, string $module): ?array
            {
                return $this->rights;
            }
        };
    }

    public function test_it_grants_only_the_action_the_rights_row_allows(): void
    {
        // A real shape: view + edit, but no add and no delete.
        $svc = $this->service(['can_view' => 1, 'can_add' => 0, 'can_edit' => 1, 'can_delete' => 0]);

        $this->assertTrue($svc->check(1, 2, 1, 'lms.content', 'view'));
        $this->assertFalse($svc->check(1, 2, 1, 'lms.content', 'create'));
        $this->assertTrue($svc->check(1, 2, 1, 'lms.content', 'update'));
        $this->assertFalse($svc->check(1, 2, 1, 'lms.content', 'delete'));
    }

    /**
     * The whole point of row 5.
     *
     * checkPermission.php:66-69 has this rejection commented out, so a user with no rights
     * row currently falls through to ALLOWED. Here, absent means denied.
     */
    public function test_no_rights_row_at_all_is_denied_not_allowed(): void
    {
        $svc = $this->service(null);

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $this->assertFalse(
                $svc->check(1, 2, 1, 'lms.content', $action),
                "Action \"{$action}\" must be denied when the user has no rights row."
            );
        }
    }

    public function test_an_unregistered_action_is_denied_rather_than_guessed(): void
    {
        // Fully-permissive rights row - the denial must come from the unknown action.
        $svc = $this->service(['can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);

        $this->assertFalse($svc->check(1, 2, 1, 'lms.content', 'publish'));
        $this->assertFalse($svc->check(1, 2, 1, 'lms.content', ''));
    }

    public function test_a_missing_column_counts_as_no_grant(): void
    {
        // tblindividual_rights and tblgroupwise_rights do not carry identical columns.
        // A column that is simply absent must not read as permission.
        $svc = $this->service(['can_view' => 1]);

        $this->assertTrue($svc->check(1, 2, 1, 'lms.content', 'view'));
        $this->assertFalse($svc->check(1, 2, 1, 'lms.content', 'create'));
    }

    public function test_only_an_exact_1_grants(): void
    {
        // Guards against a truthy string or a stray value being read as permission.
        $this->assertFalse($this->service(['can_add' => 0])->check(1, 2, 1, 'lms.content', 'create'));
        $this->assertFalse($this->service(['can_add' => null])->check(1, 2, 1, 'lms.content', 'create'));
        $this->assertFalse($this->service(['can_add' => 2])->check(1, 2, 1, 'lms.content', 'create'));
        $this->assertTrue($this->service(['can_add' => 1])->check(1, 2, 1, 'lms.content', 'create'));
        $this->assertTrue($this->service(['can_add' => '1'])->check(1, 2, 1, 'lms.content', 'create'));
    }

    public function test_actions_for_returns_every_registered_action(): void
    {
        $flags = $this->service(['can_view' => 1, 'can_add' => 0, 'can_edit' => 1, 'can_delete' => 0])
            ->actionsFor(1, 2, 1, 'lms.content');

        $this->assertSame(
            ['view' => true, 'create' => false, 'update' => true, 'delete' => false],
            $flags
        );
    }

    public function test_actions_for_is_all_false_when_there_is_no_rights_row(): void
    {
        $flags = $this->service(null)->actionsFor(1, 2, 1, 'lms.content');

        $this->assertSame(
            ['view' => false, 'create' => false, 'update' => false, 'delete' => false],
            $flags
        );
    }

    public function test_the_registry_names_modules_but_grants_nothing(): void
    {
        // Decision #23: configuration can never grant a permission. Every module in the
        // registry must still be denied when the user has no rights row.
        $svc = $this->service(null);

        $modules = $svc->modules();
        $this->assertContains('lms.content', $modules);

        foreach ($modules as $module) {
            $this->assertFalse(
                $svc->check(1, 2, 1, $module, 'create'),
                "Registering module \"{$module}\" must not grant anything by itself."
            );
        }
    }

    public function test_registered_actions_map_onto_the_columns_checkpermission_already_uses(): void
    {
        // One meaning of "can add" in the system, not two.
        $this->assertSame(
            ['view' => 'can_view', 'create' => 'can_add', 'update' => 'can_edit', 'delete' => 'can_delete'],
            config('rbac_modules.actions')
        );
    }

    /**
     * Regression: module names contain a dot, and config() treats dots as path separators.
     *
     * `config("rbac_modules.modules.lms.content.links")` resolves to
     * modules -> lms -> content -> links, which does not exist, so it silently returns []
     * and every permission check denies. The symptom is "nobody can create content", with
     * no error anywhere. PermissionService must therefore fetch the modules array and index
     * it directly, never interpolate a module name into a config path.
     */
    public function test_module_names_contain_dots_so_config_path_lookup_must_not_be_used(): void
    {
        $modules = config('rbac_modules.modules');

        $this->assertArrayHasKey('lms.content', $modules, 'The registry key is literally "lms.content".');
        $this->assertNotEmpty($modules['lms.content']['links'] ?? [], 'It must declare at least one menu link.');

        // The trap itself: this is what the naive lookup would return.
        $this->assertNull(
            config('rbac_modules.modules.lms.content.links'),
            'config() dot-path cannot reach a key containing a dot - do not use it for module names.'
        );
    }

    public function test_it_fails_closed_by_configuration(): void
    {
        $this->assertFalse(
            config('rbac_modules.allow_when_unresolved'),
            'allow_when_unresolved must stay false; it is a debugging escape hatch, not a default.'
        );
    }
}
