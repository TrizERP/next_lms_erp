<?php

namespace Tests\Feature\AI\Fees;

use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeesKnowledgeBaseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_service_exists(): void
    {
        $service = new FeesKnowledgeBaseService();
        $this->assertInstanceOf(FeesKnowledgeBaseService::class, $service);
    }

    public function test_findPolicies_returns_empty_without_table(): void
    {
        $service = new FeesKnowledgeBaseService();

        if (! Schema::hasTable('knowledge_base_detail')) {
            $result = $service->findPolicies(1);
            $this->assertSame([], $result);
        } else {
            // Table exists but lacks fee-policy columns; service guards against this.
            $result = $service->findPolicies(1);
            $this->assertIsArray($result);
        }
    }

    public function test_listCategories_returns_empty_without_table(): void
    {
        $service = new FeesKnowledgeBaseService();

        if (! Schema::hasTable('knowledge_base_detail')) {
            $result = $service->listCategories(1);
            $this->assertSame([], $result);
        } else {
            $result = $service->listCategories(1);
            $this->assertIsArray($result);
        }
    }

    public function test_findByTags_returns_empty_without_table(): void
    {
        $service = new FeesKnowledgeBaseService();

        if (! Schema::hasTable('knowledge_base_detail')) {
            $result = $service->findByTags(1, ['due-date']);
            $this->assertSame([], $result);
        } else {
            $result = $service->findByTags(1, ['due-date']);
            $this->assertIsArray($result);
        }
    }

    public function test_policyFor_returns_null_without_table(): void
    {
        $service = new FeesKnowledgeBaseService();

        if (! Schema::hasTable('knowledge_base_detail')) {
            $result = $service->policyFor('late fee', 1);
            $this->assertNull($result);
        } else {
            $result = $service->policyFor('late fee', 1);
            $this->assertNull($result);
        }
    }

    public function test_summaryForStudent_returns_string(): void
    {
        $service = new FeesKnowledgeBaseService();

        $result = $service->summaryForStudent(1, 1, 2024);

        $this->assertIsString($result);
    }
}