<?php

namespace Tests\Unit\Ceds;

use App\Services\Ceds\CedsFitService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * No application boot and no database: plain PHPUnit over the config file.
 */
class CedsFitServiceTest extends TestCase
{
    private array $config;
    private CedsFitService $fit;

    protected function setUp(): void
    {
        $this->config = require __DIR__ . '/../../../config/ceds_fit_matrix.php';
        $this->fit = new CedsFitService($this->config);
    }

    public function test_there_are_eight_methods_and_six_formats(): void
    {
        $this->assertSame(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8'], array_keys($this->fit->pedagogies()));
        $this->assertSame(['D1', 'D2', 'D3', 'D4', 'D5', 'D6'], array_keys($this->fit->formats()));
    }

    public function test_matrix_is_complete_and_uses_only_s_p_w(): void
    {
        foreach (array_keys($this->fit->pedagogies()) as $p) {
            foreach (array_keys($this->fit->formats()) as $d) {
                $this->assertContains($this->fit->fit($p, $d), ['S', 'P', 'W'], "$p.$d");
            }
        }
    }

    /** Spot checks straight from the CEDS prompt's FIT MATRIX. */
    public function test_cells_match_the_prompt(): void
    {
        $expected = [
            'P1.D1' => 'S', 'P1.D4' => 'W', 'P2.D3' => 'S', 'P2.D5' => 'W', 'P3.D4' => 'S',
            'P4.D6' => 'W', 'P5.D1' => 'W', 'P5.D3' => 'S', 'P6.D6' => 'S', 'P6.D2' => 'P',
            'P7.D4' => 'S', 'P7.D6' => 'W', 'P8.D5' => 'S', 'P8.D6' => 'S', 'P8.D3' => 'P',
        ];
        foreach ($expected as $cell => $fit) {
            [$p, $d] = explode('.', $cell);
            $this->assertSame($fit, $this->fit->fit($p, $d), $cell);
        }
    }

    public function test_every_weak_cell_has_a_reason_and_a_stronger_alternative_format(): void
    {
        $weak = 0;
        foreach (array_keys($this->fit->pedagogies()) as $p) {
            foreach (array_keys($this->fit->formats()) as $d) {
                if ($this->fit->fit($p, $d) !== 'W') {
                    continue;
                }
                $weak++;
                $a = $this->fit->assess($p, $d);
                $this->assertArrayHasKey("$p.$d", $this->config['weak_reasons'], "reason for $p.$d");
                $this->assertNotNull($a['warning']);
                $this->assertNotEmpty($a['alternative_formats'], "alternative format for $p.$d");
                foreach ($a['alternative_formats'] as $alt) {
                    $this->assertSame('S', $this->fit->fit($p, $alt));
                }
                foreach ($a['alternative_pedagogies'] as $alt) {
                    $this->assertSame('S', $this->fit->fit($alt, $d));
                }
            }
        }
        $this->assertSame(count($this->config['weak_reasons']), $weak, 'no reason without a weak cell, no weak cell without a reason');
    }

    public function test_strong_and_possible_cells_carry_no_warning(): void
    {
        $a = $this->fit->assess('P1', 'D2');
        $this->assertSame('S', $a['fit']);
        $this->assertNull($a['warning']);
        $this->assertSame([], $a['alternative_formats']);
    }

    public function test_formats_for_a_method_are_ordered_strong_possible_weak(): void
    {
        $rows = $this->fit->formatsForPedagogy('P1');
        $this->assertSame(['D1', 'D2', 'D5', 'D3', 'D6', 'D4'], array_column($rows, 'format'));
        $this->assertSame(['S', 'S', 'S', 'P', 'P', 'W'], array_column($rows, 'fit'));
    }

    public function test_methods_for_a_format_are_ordered_strong_first(): void
    {
        $rows = $this->fit->pedagogiesForFormat('D6');
        $this->assertSame(['P6', 'P8', 'P1', 'P2', 'P3', 'P5', 'P4', 'P7'], array_column($rows, 'pedagogy'));
        $this->assertSame('S', $rows[0]['fit']);
        $this->assertSame('W', end($rows)['fit']);
    }

    public function test_a_weak_choice_is_warned_never_blocked(): void
    {
        // Asking for a weak pairing returns a normal answer (with a warning), it does not throw.
        $a = $this->fit->assess('P7', 'D2');
        $this->assertSame('W', $a['fit']);
        $this->assertNotEmpty($a['warning']);
    }

    public function test_notes_attach_to_qualified_cells(): void
    {
        $this->assertSame('Suits the explain phase only.', $this->fit->assess('P2', 'D2')['note']);
        $this->assertNull($this->fit->assess('P1', 'D1')['note']);
    }

    public function test_phase_weights_sum_to_one_hundred_for_every_method(): void
    {
        foreach (array_keys($this->fit->pedagogies()) as $p) {
            $this->assertSame(100, array_sum(array_column($this->fit->phases($p), 1)), $p);
        }
    }

    public function test_default_period_is_forty_minutes(): void
    {
        $this->assertSame(40, $this->fit->defaultPeriodMinutes());
    }

    public function test_unknown_codes_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fit->fit('P9', 'D1');
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fit->formatsForPedagogy('P1');
        $this->fit->pedagogiesForFormat('D9');
    }
}
