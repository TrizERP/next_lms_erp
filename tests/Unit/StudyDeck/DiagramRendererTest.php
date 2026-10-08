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
}
