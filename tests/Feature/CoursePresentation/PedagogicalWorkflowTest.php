<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\PedagogicalWorkflow;
use App\Services\StudyDeck\TeachingMethodProfile;

/**
 * The pedagogical workflow exists for Std 9 Science chapter 1 (8592, school 1) and nowhere else. No database is read.
 */
class PedagogicalWorkflowTest extends CoursePresentationTestCase
{
    protected bool $workflowPilot = true;

    private const CHAPTER_ROW = ['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => 1];

    private const STAGES = [
        'inquiry_based' => 6, 'explicit_instruction' => 8, 'problem_based' => 7, 'project_based' => 7, 'experiential' => 6,
        'collaborative' => 7, 'socratic' => 6, 'flipped_classroom' => 7, 'differentiated_mastery' => 7, 'gamified' => 7,
    ];

    public function test_applies_only_when_chapter_standard_subject_and_school_all_match(): void
    {
        $w = new PedagogicalWorkflow();
        $this->assertTrue($w->appliesTo(self::CHAPTER_ROW));
        foreach (['id' => 8593, 'standard_id' => 43, 'subject_id' => 1, 'sub_institute_id' => 2] as $field => $other) {
            $this->assertFalse($w->appliesTo([$field => $other] + self::CHAPTER_ROW), "changing $field must switch the workflow off");
        }
        // The other pilot chapters (Std 4 EVS) keep the existing card.
        $this->assertFalse($w->appliesTo(['id' => 41675, 'standard_id' => 4258, 'subject_id' => 48385, 'sub_institute_id' => 341]));
        $this->assertFalse($w->appliesToIds(8592, 2));
        $this->assertTrue($w->appliesToIds(8592, 1));
    }

    public function test_all_ten_methods_load_with_their_own_stages_and_every_stage_field(): void
    {
        $w = new PedagogicalWorkflow();
        $this->assertSame(array_keys(self::STAGES), array_keys(array_intersect_key(self::STAGES, $w->methods())));
        $this->assertCount(10, $w->methods());
        foreach (self::STAGES as $key => $count) {
            $wf = $w->workflow($key);
            $this->assertCount($count, $wf['stages'], $key);
            $this->assertContains($key, TeachingMethodProfile::keys(), "$key must exist in the shared pipeline");
            foreach ($wf['stages'] as $stage) {
                foreach (['name', 'objective', 'teacher', 'student', 'activity', 'questions', 'interactions', 'visual', 'outcome', 'minutes'] as $field) {
                    $this->assertNotEmpty($stage[$field], "$key stage {$stage['number']} lacks $field");
                }
            }
        }
        $this->assertStringStartsWith('Engage', $w->workflow('inquiry_based')['stages'][0]['name']);
        $this->assertStringStartsWith('Present a real-world problem', $w->workflow('problem_based')['stages'][0]['name']);
        $this->assertNull($w->workflow('unknown'));
    }

    public function test_compatibility_follows_the_brief_and_unavailable_generators_cannot_be_selected(): void
    {
        $w = new PedagogicalWorkflow();
        $this->assertSame(['interactive_slides', 'video_lesson', 'worksheet_guide'], $w->compatibility()['explicit_instruction']);
        $this->assertSame(['branching_scenario', 'interactive_slides', 'scenario_lesson'], $w->compatibility()['gamified']);

        $byKey = array_column($w->formatsFor('inquiry_based'), null, 'key');
        $this->assertTrue($byKey['interactive_slides']['available']);
        $this->assertTrue($byKey['branching_scenario']['available']);
        $this->assertFalse($byKey['simulation_activity']['available']);
        $this->assertFalse($byKey['socratic_dialogue']['available']);

        $this->assertNull($w->refusal('inquiry_based', 'interactive_slides'));
        $this->assertNull($w->refusal('inquiry_based', 'branching_scenario'));
        $this->assertStringContainsString('No generator', (string) $w->refusal('inquiry_based', 'socratic_dialogue'));
        $this->assertStringContainsString('does not suit', (string) $w->refusal('explicit_instruction', 'branching_scenario'));
        $this->assertNotNull($w->refusal('nonsense', 'interactive_slides'));
        $this->assertNull($w->generationContext('explicit_instruction', 'branching_scenario'));
    }

    public function test_generation_context_carries_method_format_and_ordered_stages(): void
    {
        $ctx = (new PedagogicalWorkflow())->generationContext('inquiry_based', 'branching_scenario');
        $this->assertSame(8592, $ctx['chapter_id']);
        $this->assertSame('inquiry_based', $ctx['method']['key']);
        $this->assertSame('branching_scenario', $ctx['format']['key']);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($ctx['stages'], 'number'));

        $slides = (new PedagogicalWorkflow())->generationContext('inquiry_based', 'interactive_slides');
        $this->assertNotSame($ctx['format']['structure'], $slides['format']['structure'], 'the structure must change with the format');
    }

    public function test_prompt_rules_are_unchanged_without_a_workflow_and_stage_aware_with_one(): void
    {
        foreach (TeachingMethodProfile::keys() as $key) {
            $this->assertStringNotContainsString('PEDAGOGICAL WORKFLOW', TeachingMethodProfile::plannerRule($key));
            $this->assertSame(TeachingMethodProfile::plannerRule($key), TeachingMethodProfile::plannerRule($key, null));
            $this->assertSame(TeachingMethodProfile::contentRule($key), TeachingMethodProfile::contentRule($key, null));
            $this->assertSame(TeachingMethodProfile::interactionRule($key), TeachingMethodProfile::interactionRule($key, null));
        }
        $this->assertSame('', PedagogicalWorkflow::rule(null, 'planner'));

        $ctx = (new PedagogicalWorkflow())->generationContext('inquiry_based', 'interactive_slides');
        $planner = TeachingMethodProfile::plannerRule('inquiry_based', $ctx);
        $this->assertStringContainsString('PEDAGOGICAL WORKFLOW', $planner);
        $this->assertStringContainsString('Elaborate: real-life application', $planner);
        $this->assertStringContainsString('workflow_stage', $planner);
        $this->assertStringContainsString('Explore: investigation', TeachingMethodProfile::contentRule('inquiry_based', $ctx));
        $this->assertStringContainsString('Evaluate: assessment', TeachingMethodProfile::interactionRule('inquiry_based', $ctx));
    }

    public function test_every_method_and_available_format_pair_builds_a_context(): void
    {
        $w = new PedagogicalWorkflow();
        foreach ($w->compatibility() as $method => $formats) {
            foreach ($formats as $format) {
                $ctx = $w->generationContext($method, $format);
                $this->assertSame($w->formatAvailable($format), $ctx !== null, "$method + $format");
            }
        }
    }
}
