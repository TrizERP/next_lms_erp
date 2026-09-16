<?php

namespace Tests\Feature\AI\Fees;

use App\Domain\AI\Recommendations\FeesRecommendationService;
use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use PHPUnit\Framework\TestCase;

class FeesRecommendationTest extends TestCase
{
    public function test_service_exists(): void
    {
        $service = new FeesRecommendationService(
            new FeesKnowledgeBaseService()
        );

        $this->assertInstanceOf(FeesRecommendationService::class, $service);
    }

    public function test_service_has_required_methods(): void
    {
        $service = new FeesRecommendationService(
            new FeesKnowledgeBaseService()
        );

        $this->assertTrue(method_exists($service, 'draftForStudent'));
        $this->assertTrue(method_exists($service, 'draftCollectionReport'));
        $this->assertTrue(method_exists($service, 'forModule'));
    }
}