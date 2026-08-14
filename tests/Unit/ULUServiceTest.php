<?php

namespace Tests\Unit;

use App\Services\PAL\Framework\FrameworkCatalogService;
use App\Services\PAL\ULU\ULUGraphService;
use App\Services\PAL\ULU\ULUService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ULUServiceTest extends TestCase
{
    public function test_it_requires_two_branches_and_reflections(): void
    {
        $service = new ULUService(new FrameworkCatalogService(), new ULUGraphService(null));

        $this->expectException(ValidationException::class);

        $service->create([
            'title' => 'Incomplete ULU',
            'grade' => 6,
            'subject' => 'Science',
            'academic_concept' => 'SCI_FORCE',
            'casel_domain' => 'social_awareness',
            'ngss_practice' => 'developing_models',
            'ncdg_goal' => 'CM2',
            'riasec_signal' => 'R',
            'career_cluster' => 'sports_science',
            'real_skill_name' => 'Physical literacy',
            'scenario' => [
                'context' => 'Test',
                'academic_hook' => 'Hook',
                'decision_point' => 'Decision',
                'reflection' => 'Reflection',
            ],
            'branches' => [
                ['key' => 'path_a', 'choice' => 'Only one path'],
            ],
            'reflections' => [
                'stream' => 'One',
            ],
        ]);
    }

    public function test_it_generates_ulu_ids_for_seed_like_payloads(): void
    {
        $service = new ULUService(new FrameworkCatalogService(), new ULUGraphService(null));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'title' => "Ravi's Bank Decision",
            'grade' => 7,
            'subject' => 'Mathematics',
            'academic_concept' => 'MATH_PERCENTAGE',
            'casel_domain' => 'responsible_decision_making',
            'ngss_practice' => 'math_computation',
            'ncdg_goal' => 'CM2',
            'riasec_signal' => 'C',
            'career_cluster' => 'business_finance',
            'real_skill_name' => 'Financial literacy',
            'scenario' => [
                'context' => 'Context',
                'academic_hook' => 'Hook',
                'decision_point' => 'Decision',
                'reflection' => 'Reflection',
            ],
            'branches' => [
                ['key' => 'path_a'],
                ['key' => 'path_b'],
            ],
            'reflections' => [
                'stream' => 'Stream',
                'mountain' => 'Mountain',
                'sky' => 'Sky',
            ],
        ]);

        $this->assertStringStartsWith('ULU_MATH_G7_MATHPERCEN_', $result['ulu_id']);
    }
}
