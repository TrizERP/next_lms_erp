<?php

namespace Tests\Unit;

use App\Services\Lms\PedagogicalContentBuilder;
use PHPUnit\Framework\TestCase;

/** No database, no framework boot: pure image-selection rules of the content builder. */
class TeachLearnSelectedImagesTest extends TestCase
{
    public function test_sanitize_keeps_http_urls_and_site_paths_only(): void
    {
        $out = PedagogicalContentBuilder::sanitizeSelectedImages([
            'hero' => 'https://cdn.example.com/a.jpg',
            'hook' => '/storage/b.png',
        ]);
        $this->assertSame(['hero' => 'https://cdn.example.com/a.jpg', 'hook' => '/storage/b.png'], $out);
    }

    public function test_sanitize_rejects_unsafe_values(): void
    {
        foreach (['javascript:alert(1)', 'data:image/png;base64,AAAA', '//evil.example/x.png', 'https://x.com/a b.png', 'https://x.com/a".png', '<img>'] as $bad) {
            $this->assertSame([], PedagogicalContentBuilder::sanitizeSelectedImages(['hero' => $bad, 'hook' => $bad]), $bad);
        }
        $this->assertSame([], PedagogicalContentBuilder::sanitizeSelectedImages('nope'));
        $this->assertSame([], PedagogicalContentBuilder::sanitizeSelectedImages(['hero' => 'https://x.com/' . str_repeat('a', 2100)]));
    }

    public function test_single_image_fills_both_slots(): void
    {
        $out = PedagogicalContentBuilder::sanitizeSelectedImages(['hero' => 'https://x.com/one.jpg']);
        $this->assertSame(['hero' => 'https://x.com/one.jpg', 'hook' => 'https://x.com/one.jpg'], $out);
    }

    public function test_selected_images_override_curated_defaults_and_defaults_remain_otherwise(): void
    {
        $default = (new PedagogicalContentBuilder())->resolveCuratedDomainVisuals('physics', 'Motion');
        $this->assertStringContainsString('unsplash.com', $default['hero']);

        $chosen = (new PedagogicalContentBuilder())
            ->useSelectedImages(['hero' => 'https://cdn.example.com/h.jpg', 'hook' => 'https://cdn.example.com/k.jpg'])
            ->resolveCuratedDomainVisuals('physics', 'Motion');
        $this->assertSame('https://cdn.example.com/h.jpg', $chosen['hero']);
        $this->assertSame('https://cdn.example.com/k.jpg', $chosen['hook']);
        $this->assertSame($default['caption'], $chosen['caption']);
    }
}
