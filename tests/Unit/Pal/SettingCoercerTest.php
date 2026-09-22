<?php

namespace Tests\Unit\Pal;

use App\Services\PAL\Support\SettingCoercer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins the coercion rules shared by the architecture and flow admin surfaces.
 *
 * These rules were a private method on ArchitectureRegistry and were lifted
 * into SettingCoercer so EsoFlowValidator applies exactly the same ones.
 *
 * ArchitectureRegistry has no tests of its own — checked, not assumed — so
 * this file is the entire safety net for that extraction. Everything asserted
 * here is behaviour the original method already had; nothing is new. If one of
 * these changes, an administrator's saved architecture settings change meaning
 * with it.
 */
class SettingCoercerTest extends TestCase
{
    // ── toggle ───────────────────────────────────────────────────────────

    /**
     * Truthiness is an explicit allow-list, not PHP's.
     *
     * This matters more than it looks: a toggle arriving as the string "false"
     * from a form post is FALSE here, whereas PHP would call a non-empty
     * string true. Getting that backwards would silently enable subsystems
     * an administrator had switched off.
     */
    #[DataProvider('toggleValues')]
    public function test_a_toggle_accepts_only_an_explicit_allow_list(mixed $raw, bool $expected): void
    {
        $this->assertSame($expected, SettingCoercer::coerce(['type' => 'toggle'], $raw, null, 'Flag'));
    }

    /** @return array<string, array{0:mixed, 1:bool}> */
    public static function toggleValues(): array
    {
        return [
            'boolean true' => [true, true],
            'integer one' => [1, true],
            'string one' => ['1', true],
            'string true' => ['true', true],
            'boolean false' => [false, false],
            'integer zero' => [0, false],
            'string false' => ['false', false],
            'string zero' => ['0', false],
            'empty string' => ['', false],
            'null' => [null, false],
            'arbitrary string is NOT true' => ['yes', false],
        ];
    }

    // ── number ───────────────────────────────────────────────────────────

    public function test_an_integral_number_stays_an_integer(): void
    {
        // A round-trip must not turn 3 into 3.0: these values are written to
        // JSON and read back as engine parameters.
        $this->assertSame(3, SettingCoercer::coerce(['type' => 'number'], '3', null, 'Items'));
        $this->assertSame(3, SettingCoercer::coerce(['type' => 'number'], 3.0, null, 'Items'));
    }

    public function test_a_fractional_step_keeps_the_fraction(): void
    {
        $descriptor = ['type' => 'number', 'step' => 0.05];

        $this->assertSame(0.85, SettingCoercer::coerce($descriptor, '0.85', null, 'Threshold'));
    }

    public function test_a_number_is_held_to_its_declared_bounds(): void
    {
        $descriptor = ['type' => 'number', 'min' => 1, 'max' => 5];

        $this->assertSame(1, SettingCoercer::coerce($descriptor, 1, null, 'Items'));
        $this->assertSame(5, SettingCoercer::coerce($descriptor, 5, null, 'Items'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Items cannot be above 5.');
        SettingCoercer::coerce($descriptor, 6, null, 'Items');
    }

    public function test_a_number_below_the_minimum_names_the_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // The message is surfaced verbatim by the admin UI, so the field name
        // being in it is part of the contract, not cosmetic.
        $this->expectExceptionMessage('Items cannot be below 1.');

        SettingCoercer::coerce(['type' => 'number', 'min' => 1], 0, null, 'Items');
    }

    public function test_a_non_numeric_value_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Items must be a number.');

        SettingCoercer::coerce(['type' => 'number'], 'lots', null, 'Items');
    }

    // ── select ───────────────────────────────────────────────────────────

    public function test_a_select_accepts_only_declared_options_and_lists_them_on_refusal(): void
    {
        $descriptor = ['type' => 'select', 'options' => ['stream', 'mountain', 'sky']];

        $this->assertSame('sky', SettingCoercer::coerce($descriptor, 'sky', null, 'Tier'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier must be one of: stream, mountain, sky.');
        SettingCoercer::coerce($descriptor, 'ocean', null, 'Tier');
    }

    // ── tags ─────────────────────────────────────────────────────────────

    public function test_tags_are_trimmed_deduplicated_and_compacted(): void
    {
        $result = SettingCoercer::coerce(
            ['type' => 'tags'],
            ['  alpha ', 'beta', 'alpha', '', '   '],
            null,
            'Tags'
        );

        // Reindexed, not left with holes — the value is JSON-encoded, and a
        // sparse PHP array would serialise as an object rather than a list.
        $this->assertSame(['alpha', 'beta'], $result);
    }

    public function test_a_tag_is_truncated_rather_than_refused(): void
    {
        $result = SettingCoercer::coerce(['type' => 'tags'], [str_repeat('x', 100)], null, 'Tags');

        $this->assertSame(64, mb_strlen($result[0]));
    }

    public function test_a_non_list_is_refused_for_tags(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tags must be a list.');

        SettingCoercer::coerce(['type' => 'tags'], 'alpha,beta', null, 'Tags');
    }

    // ── text ─────────────────────────────────────────────────────────────

    public function test_text_is_trimmed_and_length_capped(): void
    {
        $this->assertSame('hello', SettingCoercer::coerce(['type' => 'text'], '  hello  ', null, 'Note'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Note is too long (1000 characters maximum).');
        SettingCoercer::coerce(['type' => 'text'], str_repeat('x', 1001), null, 'Note');
    }

    /**
     * An empty string survives when the default is null.
     *
     * Carried over deliberately: for the optional per-agent model pin, empty
     * means "inherit", which is a different instruction from "unset". Folding
     * it back to the fallback would make that distinction unexpressable.
     */
    public function test_an_empty_string_is_preserved_when_the_default_is_null(): void
    {
        $this->assertSame('', SettingCoercer::coerce(['type' => 'text'], '   ', null, 'Model'));
    }

    public function test_an_unknown_type_falls_back_to_text_rather_than_throwing(): void
    {
        // The original switch had no default rejection, and a descriptor with a
        // missing type is treated as text. Pinned so a future "strict" change
        // is a deliberate decision rather than an accident.
        $this->assertSame('plain', SettingCoercer::coerce(['type' => 'something-new'], 'plain', null, 'Field'));
        $this->assertSame('plain', SettingCoercer::coerce([], 'plain', null, 'Field'));
    }
}
