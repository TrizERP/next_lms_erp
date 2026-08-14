<?php

namespace Tests\Unit;

use App\Services\PAL\Framework\FrameworkCatalogService;
use Tests\TestCase;

class FrameworkCatalogServiceTest extends TestCase
{
    public function test_it_normalizes_legacy_pedagogy_aliases(): void
    {
        $service = new FrameworkCatalogService();

        $this->assertSame('inquiry_based', $service->normalizePedagogy('concept-based'));
        $this->assertSame('activity_based', $service->normalizePedagogy('practice-based'));
        $this->assertSame('game_based', $service->normalizePedagogy('game_based'));
    }

    public function test_it_normalizes_framework_metadata_payloads(): void
    {
        $service = new FrameworkCatalogService();

        $metadata = $service->normalizeContentMetadata([
            'pedagogy_tag' => 'scenario-based',
            'casel_domain' => 'responsible_decision_making',
            'ngss_practice' => 'argumentation',
            'gardner_intelligence' => ['logical_mathematical', 'invalid'],
            'h5p_type' => 'branching_scenario',
        ]);

        $this->assertSame('scenario_based', $metadata['pedagogy_tag']);
        $this->assertContains('scenario_based', $metadata['pedagogy_tags']);
        $this->assertSame('responsible_decision_making', $metadata['casel_domain']);
        $this->assertSame(['logical_mathematical'], $metadata['gardner_intelligence']);
    }
}
