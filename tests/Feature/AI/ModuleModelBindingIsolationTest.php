<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Configuration\AiModuleRegistry;
use App\Domain\AI\Configuration\ModelCatalog;
use App\Domain\AI\Configuration\ModuleModelBindings;
use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Support\ProviderKeyResolver;
use App\Domain\AI\Support\SchemaCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Isolation for a product module's own model choice — `ai_module_model_bindings`.
 *
 * WHAT THIS PROVES
 *
 * The whole point of a decentralised Models tab is that saving a choice on Fees's own AI
 * Stack changes Fees, and changes nothing else: not another module, not another
 * capability on the same module, not another school, and not a call that named no module
 * at all. Every one of those is a way the feature could leak, and each gets its own test:
 *
 *   1. A binding is reported with source `module_binding` and changes what the module
 *      resolves to.
 *   2. A different product module, same capability, same institute — untouched.
 *   3. The same module's other capabilities — untouched.
 *   4. A capability-only call that names no product module — untouched, because it is
 *      asking a different question ("what does conversational AI run on by default")
 *      than a module's own binding answers.
 *   5. An institute's own binding does not leak to another institute; that institute
 *      keeps resolving through whatever it did before.
 *   6. An institute's binding overrides a platform-wide one for the same module and
 *      capability, and clearing the institute's row falls back to the platform's rather
 *      than to nothing.
 *   7. Clearing the only binding a module has returns it to exactly what it resolved to
 *      before the binding existed.
 *   8. A row whose provider was cleared to empty (not deleted) chooses nothing.
 *
 * WHY THE RESOLVER IS BUILT BY HAND, NOT PULLED FROM THE CONTAINER
 *
 * `AiConfigurationResolver` is registered `singleton()` in `AiServiceProvider` — correct
 * in production, where one resolver serves the whole request. It means the container's
 * copy is built once and keeps whichever `ModuleModelBindings` instance the container
 * handed it for its entire lifetime, including that instance's per-institute row cache.
 * A test that calls `app(ModuleModelBindings::class)` to save a row gets a SEPARATE
 * instance — the class carries no singleton binding of its own — so saving through it
 * and then resolving through `app(AiConfigurationResolver::class)` would read the
 * singleton's stale, pre-save cache: a staleness a real request never produces, because a
 * request never saves a binding and then resolves through the same object afterwards.
 * Building the resolver directly, wired to the one `ModuleModelBindings` instance this
 * test itself saves through, is what makes "after `save()`" mean what it says.
 */
class ModuleModelBindingIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private const INSTITUTE_A = 900001;
    private const INSTITUTE_B = 900002;

    private ModuleModelBindings $bindings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindings = app(ModuleModelBindings::class);

        if (! $this->bindings->available()) {
            $this->markTestSkipped('ai_module_model_bindings is not present on this connection.');
        }
    }

    private function resolver(): AiConfigurationResolver
    {
        return new AiConfigurationResolver(
            app(ProviderCatalog::class),
            app(ModelCatalog::class),
            app(AiModuleRegistry::class),
            app(ProviderKeyResolver::class),
            app(SchemaCache::class),
            $this->bindings,
        );
    }

    private function resolve(string $capability, int $subInstituteId, ?string $productModule)
    {
        return $this->resolver()->resolve($capability, $subInstituteId, $productModule);
    }

    public function test_a_saved_binding_is_reported_as_module_binding_and_changes_resolution(): void
    {
        $before = $this->resolve('conversational_ai', self::INSTITUTE_A, 'fees');
        $this->assertNotSame('module_binding', $before->source);

        $this->bindings->save('fees', 'conversational_ai', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $after = $this->resolve('conversational_ai', self::INSTITUTE_A, 'fees');

        $this->assertSame('module_binding', $after->source);
        $this->assertSame('gemini', $after->provider);
        $this->assertSame('gemini-2.5-flash', $after->model);
        $this->assertSame('institute', $after->scope);
    }

    public function test_a_module_binding_does_not_change_a_different_module(): void
    {
        $hostelBefore = $this->resolve('conversational_ai', self::INSTITUTE_A, 'hostel');

        $this->bindings->save('fees', 'conversational_ai', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $hostelAfter = $this->resolve('conversational_ai', self::INSTITUTE_A, 'hostel');

        $this->assertNotSame('module_binding', $hostelAfter->source);
        $this->assertSame($hostelBefore->source, $hostelAfter->source);
        $this->assertSame($hostelBefore->provider, $hostelAfter->provider);
    }

    public function test_a_module_binding_does_not_change_the_same_modules_other_capability(): void
    {
        $generativeBefore = $this->resolve('generative_ai', self::INSTITUTE_A, 'fees');

        $this->bindings->save('fees', 'conversational_ai', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $generativeAfter = $this->resolve('generative_ai', self::INSTITUTE_A, 'fees');

        $this->assertNotSame('module_binding', $generativeAfter->source);
        $this->assertSame($generativeBefore->source, $generativeAfter->source);
    }

    public function test_a_module_binding_does_not_change_a_call_that_names_no_product_module(): void
    {
        $unscopedBefore = $this->resolve('conversational_ai', self::INSTITUTE_A, null);

        $this->bindings->save('fees', 'conversational_ai', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $unscopedAfter = $this->resolve('conversational_ai', self::INSTITUTE_A, null);

        $this->assertNotSame('module_binding', $unscopedAfter->source);
        $this->assertSame($unscopedBefore->source, $unscopedAfter->source);
        $this->assertSame($unscopedBefore->provider, $unscopedAfter->provider);
    }

    public function test_one_institutes_binding_does_not_leak_to_another_institute(): void
    {
        $instituteBBefore = $this->resolve('conversational_ai', self::INSTITUTE_B, 'fees');
        $this->assertNotSame('module_binding', $instituteBBefore->source);

        $this->bindings->save('fees', 'conversational_ai', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $instituteAAfter = $this->resolve('conversational_ai', self::INSTITUTE_A, 'fees');
        $instituteBAfter = $this->resolve('conversational_ai', self::INSTITUTE_B, 'fees');

        $this->assertSame('module_binding', $instituteAAfter->source);
        $this->assertNotSame('module_binding', $instituteBAfter->source);
        $this->assertSame($instituteBBefore->source, $instituteBAfter->source);
        $this->assertSame($instituteBBefore->provider, $instituteBAfter->provider);
    }

    public function test_an_institutes_binding_overrides_a_platform_binding_for_the_same_module_and_capability(): void
    {
        // The platform row applies to every school, including one that has made no
        // choice of its own.
        $this->bindings->save('fees', 'agent_reasoning', null, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $platformScoped = $this->resolve('agent_reasoning', self::INSTITUTE_A, 'fees');
        $this->assertSame('module_binding_platform', $platformScoped->source);
        $this->assertSame('platform', $platformScoped->scope);

        // Institute A then makes its own, more specific choice.
        $this->bindings->save('fees', 'agent_reasoning', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-pro',
        ]);

        $instituteScoped = $this->resolve('agent_reasoning', self::INSTITUTE_A, 'fees');
        $this->assertSame('module_binding', $instituteScoped->source);
        $this->assertSame('institute', $instituteScoped->scope);
        $this->assertSame('gemini-2.5-pro', $instituteScoped->model);

        // A different school with no row of its own still gets the platform's.
        $instituteBStillPlatform = $this->resolve('agent_reasoning', self::INSTITUTE_B, 'fees');
        $this->assertSame('module_binding_platform', $instituteBStillPlatform->source);
        $this->assertSame('gemini-2.5-flash', $instituteBStillPlatform->model);

        // Clearing the institute's own row falls back to the platform's, not to nothing.
        $this->bindings->clear('fees', 'agent_reasoning', self::INSTITUTE_A);

        $instituteAFallenBack = $this->resolve('agent_reasoning', self::INSTITUTE_A, 'fees');
        $this->assertSame('module_binding_platform', $instituteAFallenBack->source);
        $this->assertSame('gemini-2.5-flash', $instituteAFallenBack->model);
    }

    public function test_clearing_a_modules_only_binding_returns_it_to_what_it_resolved_to_before(): void
    {
        $before = $this->resolve('conversational_ai', self::INSTITUTE_A, 'fees');

        $this->bindings->save('fees', 'conversational_ai', self::INSTITUTE_A, [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);
        $this->assertSame('module_binding', $this->resolve('conversational_ai', self::INSTITUTE_A, 'fees')->source);

        $cleared = $this->bindings->clear('fees', 'conversational_ai', self::INSTITUTE_A);
        $this->assertTrue($cleared);

        $after = $this->resolve('conversational_ai', self::INSTITUTE_A, 'fees');

        $this->assertSame($before->source, $after->source);
        $this->assertSame($before->provider, $after->provider);
        $this->assertSame($before->model, $after->model);
    }

    public function test_a_row_cleared_field_by_field_chooses_nothing_and_does_not_shadow_the_estate(): void
    {
        // A binding with an empty provider can exist — somebody cleared the field on the
        // form without deleting the row — and must not be treated as a real choice.
        DB::table(ModuleModelBindings::TABLE)->insert([
            'product_module' => 'fees',
            'capability' => 'generative_ai',
            'provider' => null,
            'model' => null,
            'status' => 1,
            'sub_institute_id' => self::INSTITUTE_A,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->resolve('generative_ai', self::INSTITUTE_A, 'fees');

        $this->assertNotSame('module_binding', $result->source);
    }
}
