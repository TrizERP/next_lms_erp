<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ImagePlanner;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * How a picture is downloaded: thumbnail first, the original when the thumbnail is refused, TIFF as a rendered JPEG,
 * and a host that asks for a long wait is left alone. The network is faked; nothing leaves the machine.
 */
class ImageDownloadTest extends TestCase
{
    use StudyDeckFixture;

    private const ORIGINAL = 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Compound_microscope.jpg';

    private function planner(): ImagePlanner
    {
        // A null download callable makes the planner use its real downloader, which is what is under test.
        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());
        // The gap between downloads is real in production; here it would only make the test slow.
        (new ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);

        return $planner;
    }

    private function fetch(ImagePlanner $planner, string $url): ?array
    {
        return (new ReflectionMethod(ImagePlanner::class, 'fetch'))->invoke($planner, $url);
    }

    public function test_the_960px_copy_is_used_when_wikimedia_serves_it(): void
    {
        Http::fake([
            'upload.wikimedia.org/wikipedia/commons/thumb/*' => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png']),
        ]);

        $file = $this->fetch($this->planner(), self::ORIGINAL);

        $this->assertSame('image/png', $file['mime']);
        $this->assertSame('https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Compound_microscope.jpg/960px-Compound_microscope.jpg', $file['source']);
        Http::assertSentCount(1);
    }

    public function test_a_refused_copy_falls_back_to_the_original_at_once(): void
    {
        Http::fake([
            'upload.wikimedia.org/wikipedia/commons/thumb/*' => Http::response('slow down', 429),
            self::ORIGINAL => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png']),
        ]);

        $file = $this->fetch($this->planner(), self::ORIGINAL);

        $this->assertNotNull($file);
        $this->assertSame(self::ORIGINAL, $file['source']);
        // One try at the copy (no waiting for a copy Wikimedia has not drawn) and one at the original.
        Http::assertSentCount(2);
    }

    public function test_a_tiff_is_fetched_as_its_rendered_jpeg_copy(): void
    {
        Http::fake([
            'upload.wikimedia.org/wikipedia/commons/thumb/c/cd/Cork_cells.tif/960px-Cork_cells.tif.jpg' => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png']),
        ]);

        $file = $this->fetch($this->planner(), 'https://upload.wikimedia.org/wikipedia/commons/c/cd/Cork_cells.tif');

        $this->assertNotNull($file);
        $this->assertStringEndsWith('/Cork_cells.tif/960px-Cork_cells.tif.jpg', $file['source']);
    }

    public function test_a_host_that_asks_for_a_long_wait_is_left_alone_for_its_other_candidates(): void
    {
        Http::fake([
            'upload.wikimedia.org/*' => Http::response('robot policy', 429, ['Retry-After' => '600']),
        ]);
        $planner = $this->planner();

        $this->assertNull($this->fetch($planner, self::ORIGINAL));
        $sent = count(Http::recorded());

        // The next candidate on the same host fails without another request; a host that did not ask is still tried.
        $this->assertNull($this->fetch($planner, 'https://upload.wikimedia.org/wikipedia/commons/b/bc/Another.jpg'));
        $this->assertCount($sent, Http::recorded());

        Http::fake(['live.staticflickr.com/*' => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png'])]);
        $this->assertNotNull($this->fetch($planner, 'https://live.staticflickr.com/1/2_b.jpg'));
    }

    /** @return string PNG bytes: a smooth grey blur (no detail) or a busy drawing (plenty) */
    private function picture(bool $busy): string
    {
        $im = imagecreatetruecolor(400, 300);
        imagefilledrectangle($im, 0, 0, 400, 300, imagecolorallocate($im, 120, 120, 125));
        if ($busy) {
            mt_srand(7);
            for ($i = 0; $i < 160; $i++) {
                imageline($im, mt_rand(0, 400), mt_rand(0, 300), mt_rand(0, 400), mt_rand(0, 300), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        } else {
            imagefilledellipse($im, 200, 150, 160, 160, imagecolorallocate($im, 40, 40, 50));
            for ($i = 0; $i < 25; $i++) {
                imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    public function test_a_picture_with_no_detail_is_refused_and_one_with_detail_or_unreadable_is_not(): void
    {
        $this->assertLessThan(ImagePlanner::MIN_DETAIL, ImagePlanner::detail($this->picture(false)));
        $this->assertFalse(ImagePlanner::hasDetail($this->picture(false)));

        $this->assertGreaterThan(ImagePlanner::MIN_DETAIL, ImagePlanner::detail($this->picture(true)));
        $this->assertTrue(ImagePlanner::hasDetail($this->picture(true)));

        // What cannot be measured is not refused: the gate only turns away a picture it has seen to be featureless.
        $this->assertNull(ImagePlanner::detail('not an image'));
        $this->assertTrue(ImagePlanner::hasDetail('not an image'));
    }

    public function test_recheck_replaces_a_featureless_photograph_by_the_planned_diagram_and_leaves_the_rest(): void
    {
        $blur = $this->picture(false);
        $sharp = $this->picture(true);
        $planner = new ImagePlanner(
            $this->imageSearch(null),
            $this->memoryStore(),
            fn (string $url) => ['bytes' => str_contains($url, 'blur') ? $blur : $sharp, 'mime' => 'image/png', 'width' => 400, 'height' => 300, 'source' => $url]
        );
        $spec = ['layout' => 'flow', 'title' => 'How it works', 'nodes' => ['First', 'Second', 'Third']];
        $slides = [
            ['n' => 5, 'visual' => ['purpose' => 'see it', 'query' => 'blur one'], 'diagram' => $spec],
            ['n' => 6, 'visual' => ['purpose' => 'see it', 'query' => 'blur two'], 'diagram' => null],
            ['n' => 7, 'visual' => ['purpose' => 'see it', 'query' => 'sharp'], 'diagram' => $spec],
            ['n' => 8, 'visual' => ['purpose' => 'see it', 'query' => 'drawn'], 'diagram' => $spec],
        ];
        $images = [
            5 => ['type' => 'photo', 'source_image_url' => 'https://x.example/blur-1.jpg'],
            6 => ['type' => 'photo', 'source_image_url' => 'https://x.example/blur-2.jpg'],
            7 => ['type' => 'photo', 'source_image_url' => 'https://x.example/sharp.jpg'],
            8 => ['type' => 'diagram', 'url' => 'study-deck-image:99'],
        ];
        $told = [];

        $out = $planner->recheck($slides, $images, function ($n) use (&$told) {
            $told[] = $n;
        });

        $this->assertSame('diagram', $out[5]['type']);
        $this->assertStringContainsString('out of focus', $out[5]['photo_missing']);
        $this->assertSame(['First', 'Second', 'Third'], array_slice($out[5]['texts'], 1));
        $this->assertArrayHasKey('missing', $out[6]);
        $this->assertSame($images[7], $out[7]);
        $this->assertSame($images[8], $out[8]);
        $this->assertSame([5, 6], $told);
    }

    public function test_something_that_is_not_a_picture_is_refused(): void
    {
        Http::fake(['upload.wikimedia.org/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertNull($this->fetch($this->planner(), self::ORIGINAL));
    }
}
