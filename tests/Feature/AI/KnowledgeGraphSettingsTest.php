<?php

namespace Tests\Feature\AI;

use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeGraphSettingsTest extends TestCase
{
    private const INSTITUTE_A = 999101;

    private const INSTITUTE_B = 999102;

    private const MODULE_A = '__kg_settings_test_module_a__';

    private const MODULE_B = '__kg_settings_test_module_b__';

    private const TREE_MODULE = '__kg_settings_test_tree_module__';

    private const TREE_SUBMODULE_A = '__kg_settings_test_tree_submodule_a__';

    private const TREE_SUBMODULE_B = '__kg_settings_test_tree_submodule_b__';

    private const NODE_TYPE_A = '__kg_settings_test_node_type_a__';

    private const NODE_TYPE_B = '__kg_settings_test_node_type_b__';

    protected function tearDown(): void
    {
        DB::table('kg_module_settings')
            ->where(fn ($q) => $q->whereIn('sub_institute_id', [self::INSTITUTE_A, self::INSTITUTE_B])
                ->orWhereIn('module_key', [self::MODULE_A, self::MODULE_B]))
            ->delete();

        DB::table('kg_explorer_tree_settings')
            ->where(fn ($q) => $q->whereIn('sub_institute_id', [self::INSTITUTE_A, self::INSTITUTE_B])
                ->orWhere('module_label', self::TREE_MODULE))
            ->delete();

        parent::tearDown();
    }

    public function test_default_is_enabled_for_every_module_when_nothing_is_saved(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $this->assertTrue($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
        $this->assertTrue($resolver->isEnabledFor(null, self::INSTITUTE_A));
    }

    public function test_module_specific_override_does_not_leak_to_other_modules_or_institutes(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->save(self::MODULE_A, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
        // A different module, same institute: unaffected.
        $this->assertTrue($resolver->isEnabledFor(self::MODULE_B, self::INSTITUTE_A));
        // The same module, a different institute: unaffected.
        $this->assertTrue($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_B));
    }

    public function test_institute_wide_override_applies_to_every_module_unless_overridden(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->save(null, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
        $this->assertFalse($resolver->isEnabledFor(self::MODULE_B, self::INSTITUTE_A));

        // A module-specific override for this institute still wins over its own wildcard.
        $resolver->save(self::MODULE_A, self::INSTITUTE_A, true);
        $this->assertTrue($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
        $this->assertFalse($resolver->isEnabledFor(self::MODULE_B, self::INSTITUTE_A));

        // Another institute's modules are never touched by this institute's wildcard.
        $this->assertTrue($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_B));
    }

    public function test_platform_wide_module_default_applies_to_every_institute_unless_overridden(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        // The platform baseline for this module — seeded directly, never through a
        // school's own admin screen. See KnowledgeGraphSettingsController's docblock.
        $resolver->save(self::MODULE_A, null, false);

        $this->assertFalse($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
        $this->assertFalse($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_B));

        // One school overriding it for itself touches neither the platform default
        // nor any other school.
        $resolver->save(self::MODULE_A, self::INSTITUTE_A, true);
        $this->assertTrue($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
        $this->assertFalse($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_B));
    }

    public function test_clearing_an_override_returns_to_the_rest_of_the_chain(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->save(self::MODULE_A, self::INSTITUTE_A, false);
        $this->assertFalse($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));

        $cleared = $resolver->clear(self::MODULE_A, self::INSTITUTE_A);

        $this->assertTrue($cleared);
        $this->assertTrue($resolver->isEnabledFor(self::MODULE_A, self::INSTITUTE_A));
    }

    public function test_resolved_map_reflects_an_override_for_every_module_the_school_has(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->save(null, self::INSTITUTE_A, false);

        $map = $resolver->resolvedMap(self::INSTITUTE_A);

        $this->assertIsArray($map);

        foreach ($map as $enabled) {
            $this->assertFalse($enabled, 'Every module should resolve to disabled under an institute-wide override.');
        }
    }

    // ------------------------------------------------------------------
    // The Explorer's own browse tree — module -> submodule.
    // ------------------------------------------------------------------

    public function test_tree_default_is_enabled_for_every_module_and_submodule_when_nothing_is_saved(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $this->assertTrue($resolver->isModuleTreeEnabledFor(self::TREE_MODULE, self::INSTITUTE_A));
        $this->assertTrue($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A));
    }

    public function test_disabling_a_tree_module_disables_every_submodule_under_it(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        // A submodule explicitly turned ON for itself is still shut by its
        // module's own switch — the module is the parent gate.
        $resolver->saveSubmoduleTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A, true);
        $resolver->saveModuleTree(self::TREE_MODULE, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isModuleTreeEnabledFor(self::TREE_MODULE, self::INSTITUTE_A));
        $this->assertFalse($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A));
    }

    public function test_tree_submodule_override_does_not_leak_to_siblings_or_institutes(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->saveSubmoduleTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A));
        // A sibling submodule in the same module: unaffected.
        $this->assertTrue($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_B, self::INSTITUTE_A));
        // The module itself: unaffected.
        $this->assertTrue($resolver->isModuleTreeEnabledFor(self::TREE_MODULE, self::INSTITUTE_A));
        // The same submodule, a different institute: unaffected.
        $this->assertTrue($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_B));
    }

    public function test_clearing_a_tree_submodule_override_returns_to_its_module(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->saveSubmoduleTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A, false);
        $this->assertFalse($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A));

        $cleared = $resolver->clearSubmoduleTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A);

        $this->assertTrue($cleared);
        $this->assertTrue($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A));
    }

    // ------------------------------------------------------------------
    // Node types — a third level under module -> submodule.
    // ------------------------------------------------------------------

    public function test_node_type_default_is_enabled_when_nothing_is_saved(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $this->assertTrue($resolver->isNodeTypeTreeEnabledFor(
            self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A
        ));
    }

    public function test_node_type_override_does_not_leak_to_siblings_or_institutes(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->saveNodeTypeTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A));
        // A sibling node type on the same submodule: unaffected.
        $this->assertTrue($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_B, self::INSTITUTE_A));
        // The submodule itself: unaffected.
        $this->assertTrue($resolver->isSubmoduleTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A));
        // The same node type under a different submodule: unaffected.
        $this->assertTrue($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_B, self::NODE_TYPE_A, self::INSTITUTE_A));
        // The same node type, a different institute: unaffected.
        $this->assertTrue($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_B));
    }

    public function test_disabling_a_submodule_disables_every_node_type_under_it(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        // A node type explicitly turned ON for itself is still shut by its
        // submodule's own switch — the submodule is the parent gate.
        $resolver->saveNodeTypeTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A, true);
        $resolver->saveSubmoduleTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A));
    }

    public function test_disabling_a_module_disables_every_node_type_under_it(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->saveNodeTypeTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A, true);
        $resolver->saveModuleTree(self::TREE_MODULE, self::INSTITUTE_A, false);

        $this->assertFalse($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A));
    }

    public function test_clearing_a_node_type_override_returns_to_its_submodule(): void
    {
        $resolver = app(KnowledgeGraphSettingsResolver::class);

        $resolver->saveNodeTypeTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A, false);
        $this->assertFalse($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A));

        $cleared = $resolver->clearNodeTypeTree(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A);

        $this->assertTrue($cleared);
        $this->assertTrue($resolver->isNodeTypeTreeEnabledFor(self::TREE_MODULE, self::TREE_SUBMODULE_A, self::NODE_TYPE_A, self::INSTITUTE_A));
    }
}
