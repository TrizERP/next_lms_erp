<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\DiagramRenderer;
use Tests\TestCase;

class DiagramRendererTest extends TestCase
{
    private function flow(): array
    {
        return ['layout' => 'flow', 'title' => 'Estimating air breathed in a day', 'nodes' => ['Breaths per minute', 'Minutes in a day', 'Litres per breath', 'Litres in a day']];
    }

    public function test_each_layout_renders_a_valid_png(): void
    {
        $specs = [
            $this->flow(),
            ['layout' => 'hub', 'title' => 'One problem, many branches', 'center' => 'A real problem', 'nodes' => ['Physics', 'Chemistry', 'Biology', 'Mathematics']],
            ['layout' => 'compare', 'title' => 'Law and theory', 'left' => ['heading' => 'Law', 'items' => ['Says what happens']], 'right' => ['heading' => 'Theory', 'items' => ['Says why it happens', 'Rests on evidence']]],
        ];

        foreach ($specs as $spec) {
            $out = (new DiagramRenderer())->render($spec);
            $info = getimagesizefromstring($out['bytes']);

            $this->assertSame('image/png', $info['mime'], $spec['layout']);
            $this->assertSame([1280, 720], [$info[0], $info[1]], $spec['layout']);
            $this->assertGreaterThan(5000, strlen($out['bytes']), $spec['layout'] . ' looks blank');
        }
    }

    public function test_six_nodes_wrap_onto_two_rows_without_failing(): void
    {
        $spec = ['layout' => 'flow', 'title' => 'Six steps', 'nodes' => ['One', 'Two', 'Three', 'Four', 'Five', 'Six']];

        $this->assertSame([], DiagramRenderer::problems($spec));
        $this->assertSame('image/png', (new DiagramRenderer())->render($spec)['mime']);
    }

    public function test_the_alt_text_describes_only_what_the_spec_drew(): void
    {
        $this->assertSame(
            'Diagram: Estimating air breathed in a day. Steps in order: Breaths per minute, then Minutes in a day, then Litres per breath, then Litres in a day.',
            DiagramRenderer::describe($this->flow())
        );
        $this->assertSame(
            "Diagram: Many branches. 'A real problem' connected to Physics, Biology.",
            DiagramRenderer::describe(['layout' => 'hub', 'title' => 'Many branches', 'center' => 'A real problem', 'nodes' => ['Physics', 'Biology']])
        );
        $this->assertSame(
            'Diagram: Law and theory. Law: Says what happens. Theory: Says why.',
            DiagramRenderer::describe(['layout' => 'compare', 'title' => 'Law and theory', 'left' => ['heading' => 'Law', 'items' => ['Says what happens']], 'right' => ['heading' => 'Theory', 'items' => ['Says why']]])
        );
    }

    public function test_texts_lists_every_label_so_the_grounding_check_can_read_them(): void
    {
        $this->assertSame(
            ['Estimating air breathed in a day', 'Breaths per minute', 'Minutes in a day', 'Litres per breath', 'Litres in a day'],
            DiagramRenderer::texts($this->flow())
        );
    }

    public function test_a_spec_that_cannot_be_drawn_is_reported_not_drawn(): void
    {
        $this->assertStringContainsString('layout must be one of', implode(' ', DiagramRenderer::problems(['layout' => 'pie'])));
        $this->assertStringContainsString('needs a title', implode(' ', DiagramRenderer::problems(['layout' => 'flow', 'nodes' => ['a', 'b']])));
        $this->assertStringContainsString('flow needs 2-6 nodes', implode(' ', DiagramRenderer::problems(['layout' => 'flow', 'title' => 't', 'nodes' => ['a']])));
        $this->assertStringContainsString('hub needs a centre', implode(' ', DiagramRenderer::problems(['layout' => 'hub', 'title' => 't', 'nodes' => ['a', 'b']])));
        $this->assertStringContainsString('compare needs a heading', implode(' ', DiagramRenderer::problems(['layout' => 'compare', 'title' => 't', 'left' => ['heading' => 'x', 'items' => ['a']], 'right' => ['heading' => '', 'items' => []]])));
        $this->assertStringContainsString('longer than 70', implode(' ', DiagramRenderer::problems(['layout' => 'flow', 'title' => 't', 'nodes' => ['a', str_repeat('long ', 20)]])));

        $this->expectException(\InvalidArgumentException::class);
        (new DiagramRenderer())->render(['layout' => 'flow', 'title' => 't', 'nodes' => ['only']]);
    }

    public function test_anchors_land_on_the_boxes_that_were_drawn(): void
    {
        $specs = [
            $this->flow() + [],
            ['layout' => 'flow', 'title' => 'Six steps', 'nodes' => ['One', 'Two', 'Three', 'Four', 'Five', 'Six']],
            ['layout' => 'hub', 'title' => 'One problem, many branches', 'center' => 'A real problem', 'nodes' => ['Physics', 'Chemistry', 'Biology', 'Mathematics']],
            ['layout' => 'hub', 'title' => 'Three branches', 'center' => 'Science', 'nodes' => ['Observation', 'Measurement', 'Models']],
            ['layout' => 'compare', 'title' => 'Law and theory', 'left' => ['heading' => 'Law', 'items' => ['Says what happens']], 'right' => ['heading' => 'Theory', 'items' => ['Says why it happens']]],
        ];

        foreach ($specs as $spec) {
            $anchors = DiagramRenderer::anchors($spec);
            $png = imagecreatefromstring((new DiagramRenderer())->render($spec)['bytes']);

            $labels = $spec['layout'] === 'compare' ? [$spec['left']['heading'], $spec['right']['heading']] : $spec['nodes'];
            $this->assertSame($labels, array_column($anchors, 'label'), $spec['title']);

            foreach ($anchors as $a) {
                $this->assertGreaterThanOrEqual(0, $a['x']);
                $this->assertLessThanOrEqual(100, $a['x']);
                $this->assertGreaterThanOrEqual(0, $a['y']);
                $this->assertLessThanOrEqual(100, $a['y']);

                // A box, not blank paper: most of the pixels around the anchor are the box's own fill or ink.
                $cx = (int) round($a['x'] / 100 * 1280);
                $cy = (int) round($a['y'] / 100 * 720);
                $fill = 0;
                for ($x = $cx - 20; $x <= $cx + 20; $x += 4) {
                    for ($y = $cy - 20; $y <= $cy + 20; $y += 4) {
                        $rgb = imagecolorat($png, $x, $y);
                        $white = (($rgb >> 16) & 255) > 250 && (($rgb >> 8) & 255) > 250 && ($rgb & 255) > 250;
                        $fill += $white ? 0 : 1;
                    }
                }
                $this->assertGreaterThan(60, $fill, "anchor \"{$a['label']}\" in {$spec['title']} is not on a drawn box");
            }
        }
    }

    /** Dark text pixels in a rectangle of the picture. */
    private function ink($png, int $x1, int $y1, int $x2, int $y2): int
    {
        $n = 0;
        for ($x = max(0, $x1); $x <= min(1279, $x2); $x++) {
            for ($y = max(0, $y1); $y <= min(719, $y2); $y++) {
                $rgb = imagecolorat($png, $x, $y);
                $n += (($rgb >> 16) & 255) < 45 && (($rgb >> 8) & 255) < 55 && ($rgb & 255) < 75 ? 1 : 0;
            }
        }

        return $n;
    }

    public function test_long_labels_stay_inside_their_boxes(): void
    {
        // Labels as long as a real chapter produces ("Physics: particle motion and electrostatic attraction").
        $specs = [
            ['layout' => 'hub', 'title' => 'Understanding how a mask works', 'center' => 'How a mask works', 'nodes' => ['Physics: particle motion and electrostatic attraction', 'Chemistry: properties of polymer fibres', 'Biology: size and behaviour of viruses', 'Mathematics: airflow and filtration efficiency']],
            ['layout' => 'hub', 'title' => 'Six long branches', 'center' => 'Science', 'nodes' => ['Observation of natural phenomena in detail', 'Measurement with a standard unit', 'Models that simplify real systems', 'Testing a prediction against data', 'Communicating the result to others', 'Revising the idea when evidence changes']],
            ['layout' => 'flow', 'title' => 'A strategy for estimating', 'nodes' => ['Understand the situation', 'Identify the quantities that matter', 'Make a rough estimate', 'Check if the answer makes sense']],
            ['layout' => 'flow', 'title' => 'Refining the cricket ball model', 'nodes' => ['Simple model: mass, speed, direction', 'Add air resistance', 'Add spin', 'Add seam stitching', 'Compare with the real flight', 'More accurate model']],
        ];

        foreach ($specs as $spec) {
            $png = imagecreatefromstring((new DiagramRenderer())->render($spec)['bytes']);
            $anchors = DiagramRenderer::anchors($spec);
            foreach ($anchors as $k => $a) {
                // The first and last box of a flow are filled with the accent colour and carry white text.
                $accent = $spec['layout'] === 'flow' && ($k === 0 || $k === count($anchors) - 1);
                $left = (int) round(($a['x'] - $a['w'] / 2) / 100 * 1280);
                $right = (int) round(($a['x'] + $a['w'] / 2) / 100 * 1280);
                $top = (int) round(($a['y'] - $a['h'] / 2) / 100 * 720);
                $bottom = (int) round(($a['y'] + $a['h'] / 2) / 100 * 720);
                $mid = intdiv($left + $right, 2);

                // Just above and below the box (the connector column is a thin slate line, not ink).
                $this->assertSame(0, $this->ink($png, $left, $top - 14, $right, $top - 3), "text spills above \"{$a['label']}\" in {$spec['title']}");
                $this->assertSame(0, $this->ink($png, $left, $bottom + 3, $right, $bottom + 14), "text spills below \"{$a['label']}\" in {$spec['title']}");
                // And it is really in the box.
                $accent || $this->assertGreaterThan(40, $this->ink($png, $left + 4, $top + 4, $right - 4, $bottom - 4), "\"{$a['label']}\" is not drawn in its box");
            }
        }
    }

    public function test_a_label_gets_smaller_before_it_spills(): void
    {
        $r = new DiagramRenderer();
        $font = $r->font();

        [$size, $lines] = $r->fit($font, 'Prediction matches observation', 512, 62, [28, 24, 22, 20], 1.25);
        $this->assertLessThan(28, $size, 'two lines at 28 would not fit the heading band');
        $this->assertLessThanOrEqual(62, count($lines) * $size * 1.25);

        [$size] = $r->fit($font, 'Law', 512, 70, [28, 24, 22, 20], 1.25);
        $this->assertSame(28, $size, 'a short label keeps the biggest size');

        [$size, $lines] = $r->fit($font, str_repeat('unbreakable ', 30), 100, 20, [26, 20, 18], 1.4);
        $this->assertSame(18, $size, 'when nothing fits, the smallest size is used rather than failing');
        $this->assertNotEmpty($lines);
    }
}
