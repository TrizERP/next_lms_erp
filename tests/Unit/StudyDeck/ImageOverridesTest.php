<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\DiagramRenderer;
use App\Services\StudyDeck\ImageOverrides;
use App\Services\StudyDeck\ImagePlanner;
use Tests\TestCase;

/**
 * Pictures a person chose, swapped into a planned deck: a photograph from a named source, a figure drawn for the
 * lesson, a diagram drawn again. No database, no network (the store and the download are stubs).
 */
class ImageOverridesTest extends TestCase
{
    use StudyDeckFixture;

    private function planner(?callable $download = null): ImagePlanner
    {
        // A picture with real detail, so the sharpness gate lets it through.
        $download ??= fn () => $this->detailed();

        return new ImagePlanner($this->imageSearch(null), $this->memoryStore(), $download);
    }

    /** @return array{bytes:string,mime:string,width:int,height:int} */
    private function detailed(): array
    {
        $im = imagecreatetruecolor(400, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        for ($i = 0; $i < 400; $i += 4) {
            imageline($im, $i, 0, 399 - $i, 299, imagecolorallocate($im, ($i * 7) % 255, ($i * 3) % 255, ($i * 11) % 255));
        }
        ob_start();
        imagepng($im);

        return ['bytes' => (string) ob_get_clean(), 'mime' => 'image/png', 'width' => 400, 'height' => 300];
    }

    private function pick(array $over = []): array
    {
        return $over + [
            'image_url' => 'https://upload.example.org/onion.png', 'source_url' => 'https://commons.example.org/File:Onion.png',
            'title' => 'Onion epidermal cells', 'creator' => 'A. Person', 'license' => 'BY-SA 4.0', 'provider' => 'wikimedia',
            'alt' => 'Stained onion peel cells with visible cell walls and nuclei.', 'shows' => 'onion peel cells under a light microscope',
        ];
    }

    private function plan(): array
    {
        return ['slides' => [
            ['n' => 1, 'slide_type' => 'cover', 'visual' => null],
            ['n' => 2, 'slide_type' => 'concept_visual', 'visual' => ['required' => true, 'role' => 'diagram', 'query' => 'cell parts', 'purpose' => 'Shows the parts.']],
        ]];
    }

    private function illustration(string $name, array $meta = []): string
    {
        $path = sys_get_temp_dir() . '/' . $name;
        file_put_contents($path, $this->detailed()['bytes']);

        return $path;
    }

    public function test_a_chosen_photograph_is_stored_credited_and_given_a_planned_visual(): void
    {
        [$plan, $images] = (new ImageOverrides($this->planner()))->apply(
            ['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells microscope', 'purpose' => 'Real cells behind the chapter title.'])]]],
            $this->plan(), [], []
        );

        $this->assertSame('photo', $images[1]['type']);
        $this->assertSame('BY-SA 4.0', $images[1]['licence']);
        $this->assertStringContainsString('A. Person', $images[1]['attribution']);
        $this->assertStringStartsWith('study-deck-image:', $images[1]['url']);
        $this->assertTrue($images[1]['review']['relevant']);
        $this->assertGreaterThanOrEqual(1, $images[1]['match']['shared']);
        // The cover had no planned visual: it has one now, so the validator holds its picture to the picture rules.
        $this->assertSame('onion cells microscope', $plan['slides'][0]['visual']['query']);
    }

    public function test_when_the_download_fails_the_copy_the_reviewer_saved_is_used_and_nothing_else_is(): void
    {
        $saved = $this->illustration('ov-test-saved.png');
        [, $images] = (new ImageOverrides($this->planner(fn () => null)))->apply(
            ['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells', 'local_file' => $saved])]]], $this->plan(), [], []
        );
        $this->assertSame('photo', $images[1]['type']);
        $this->assertSame('https://upload.example.org/onion.png', $images[1]['source_image_url']);

        // A saved copy that is not a picture is no help: the run still stops.
        $notAPicture = sys_get_temp_dir() . '/ov-test-not-a-picture.png';
        file_put_contents($notAPicture, 'not a picture');
        $this->expectExceptionMessageMatches('/could not be downloaded/');
        (new ImageOverrides($this->planner(fn () => null)))->apply(['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells', 'local_file' => $notAPicture])]]], $this->plan(), [], []);
    }

    public function test_a_picture_can_be_marked_unsuitable_as_a_background_texture(): void
    {
        [, $images] = (new ImageOverrides($this->planner()))->apply(['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells', 'backdrop' => false])]]], $this->plan(), [], []);
        $this->assertFalse($images[1]['backdrop']);

        [, $images] = (new ImageOverrides($this->planner()))->apply(['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells'])]]], $this->plan(), [], []);
        $this->assertArrayNotHasKey('backdrop', $images[1], 'no flag unless the reviewer set one');
    }

    public function test_a_licence_that_forbids_reuse_is_refused(): void
    {
        foreach (['CC BY-NC 4.0', 'BY-ND 2.0', '', 'All rights reserved'] as $licence) {
            try {
                (new ImageOverrides($this->planner()))->apply(['slides' => ['1' => ['photo' => $this->pick(['license' => $licence, 'query' => 'onion cells'])]]], $this->plan(), [], []);
                $this->fail("licence \"$licence\" should have been refused");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Slide 1', $e->getMessage());
                $this->assertStringContainsString('licence', $e->getMessage());
            }
        }
    }

    public function test_a_credit_is_required_where_the_licence_needs_one(): void
    {
        $this->expectExceptionMessageMatches('/attribution/');
        (new ImageOverrides($this->planner()))->apply(['slides' => ['1' => ['photo' => $this->pick(['creator' => '', 'title' => '', 'query' => 'onion cells', 'shows' => 'onion cells'])]]], $this->plan(), [], []);
    }

    public function test_a_picture_that_cannot_be_fetched_or_has_no_detail_stops_the_run_rather_than_being_skipped(): void
    {
        try {
            (new ImageOverrides($this->planner(fn () => null)))->apply(['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells'])]]], $this->plan(), [], []);
            $this->fail('a failed download should stop the run');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('could not be downloaded', $e->getMessage());
        }

        // Flat grey: nothing to learn from.
        $blank = function () {
            $im = imagecreatetruecolor(400, 300);
            imagefill($im, 0, 0, imagecolorallocate($im, 120, 120, 120));
            ob_start();
            imagepng($im);

            return ['bytes' => (string) ob_get_clean(), 'mime' => 'image/png', 'width' => 400, 'height' => 300];
        };
        // A reviewer who looked at it can accept a soft subject; the record says the gate was waived.
        [, $images] = (new ImageOverrides($this->planner($blank)))->apply(['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells', 'accept_soft' => true])]]], $this->plan(), [], []);
        $this->assertStringContainsString('gate was waived', $images[1]['review']['reason']);

        $this->expectExceptionMessageMatches('/out of focus/');
        (new ImageOverrides($this->planner($blank)))->apply(['slides' => ['1' => ['photo' => $this->pick(['query' => 'onion cells'])]]], $this->plan(), [], []);
    }

    public function test_a_picture_nothing_in_whose_title_matches_the_query_is_refused(): void
    {
        $this->expectExceptionMessageMatches('/matches the query/');
        (new ImageOverrides($this->planner()))->apply(['slides' => ['1' => ['photo' => $this->pick(['title' => 'Sunset over a harbour', 'shows' => 'a harbour', 'query' => 'chloroplast thylakoid'])]]], $this->plan(), [], []);
    }

    public function test_an_illustration_replaces_a_diagram_and_moves_its_hotspots_by_label(): void
    {
        $interactions = [2 => ['interaction' => ['kind' => 'hotspots', 'spots' => [
            ['id' => 'h1', 'label' => 'Nucleus', 'x' => 50, 'y' => 28.9, 'w' => 25, 'h' => 18.1, 'text' => 'a'],
            ['id' => 'h2', 'label' => 'Ribosomes', 'x' => 77.7, 'y' => 43.6, 'w' => 25, 'h' => 18.1, 'text' => 'b'],
        ]]]];
        $entry = ['illustration' => [
            'file' => $this->illustration('ov-test-16.png'), 'caption' => 'A cell', 'alt' => 'A cell cut open.',
            'texts' => ['Nucleus', 'Ribosomes'],
            'anchors' => [['label' => 'ribosomes', 'x' => 12.5, 'y' => 51.1, 'w' => 18, 'h' => 12], ['label' => 'Nucleus', 'x' => 38.1, 'y' => 27.4, 'w' => 18, 'h' => 20]],
        ]];

        [, $images, $out] = (new ImageOverrides($this->planner()))->apply(['slides' => ['2' => $entry]], $this->plan(), [], $interactions);

        $this->assertSame('diagram', $images[2]['type']);
        $this->assertSame(['Nucleus', 'Ribosomes'], $images[2]['texts']);
        $this->assertNull($images[2]['licence']);
        $spots = $out[2]['interaction']['spots'];
        $this->assertEquals(['x' => 38.1, 'y' => 27.4, 'w' => 18.0, 'h' => 20.0], array_intersect_key($spots[0], array_flip(['x', 'y', 'w', 'h'])));
        $this->assertEquals(12.5, $spots[1]['x']);
        // The words and the explanations are untouched.
        $this->assertSame('a', $spots[0]['text']);
        $this->assertSame('h1', $spots[0]['id']);
    }

    public function test_a_hotspot_without_an_anchor_or_not_drawn_on_the_picture_stops_the_run(): void
    {
        $interactions = [2 => ['interaction' => ['kind' => 'hotspots', 'spots' => [['id' => 'h1', 'label' => 'Nucleus', 'x' => 1, 'y' => 1, 'w' => 1, 'h' => 1, 'text' => 'a']]]]];
        $meta = ['file' => $this->illustration('ov-test-2.png'), 'alt' => 'x', 'texts' => ['Nucleus']];

        $this->expectExceptionMessageMatches('/no anchor/');
        (new ImageOverrides($this->planner()))->apply(['slides' => ['2' => ['illustration' => $meta + ['anchors' => []]]]], $this->plan(), [], $interactions);
    }

    public function test_an_unknown_slide_and_an_empty_file_are_errors(): void
    {
        try {
            (new ImageOverrides($this->planner()))->apply(['slides' => ['9' => ['remove' => 'x']]], $this->plan(), [], []);
            $this->fail('slide 9 does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('slide 9', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        (new ImageOverrides($this->planner()))->apply(['slides' => []], $this->plan(), [], []);
    }

    public function test_remove_leaves_the_slide_without_a_picture_and_says_why(): void
    {
        [, $images] = (new ImageOverrides($this->planner()))->apply(['slides' => ['2' => ['remove' => 'screenshot of a card']]], $this->plan(), [2 => ['type' => 'photo']], []);

        $this->assertStringContainsString('screenshot of a card', $images[2]['missing']);
    }

    public function test_redraw_draws_a_diagram_again_with_the_same_words_and_leaves_other_pictures_alone(): void
    {
        $planner = $this->planner();
        $spec = ['layout' => 'flow', 'title' => 'Steps', 'nodes' => ['One', 'Two', 'Three']];
        $old = $planner->diagram($spec) + ['purpose' => 'p'];
        $old['url'] = 'study-deck-image:999';
        $photo = ['type' => 'photo', 'url' => 'study-deck-image:5'];

        $slides = [['n' => 1, 'diagram' => $spec], ['n' => 2, 'diagram' => $spec], ['n' => 3, 'diagram' => null]];
        $out = $planner->redraw($slides, [1 => $old, 2 => $photo, 3 => ['type' => 'diagram', 'layout' => 'illustration', 'url' => 'study-deck-image:7', 'texts' => ['x']]]);

        $this->assertNotSame('study-deck-image:999', $out[1]['url']);
        $this->assertSame($old['texts'], $out[1]['texts']);
        $this->assertSame('p', $out[1]['purpose']);
        $this->assertSame($photo, $out[2]);
        $this->assertSame('study-deck-image:7', $out[3]['url']);
        $this->assertSame(DiagramRenderer::texts($spec), $out[1]['texts']);
    }
}
