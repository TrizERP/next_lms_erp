<?php

namespace Tests\Feature\CoursePresentation;

/**
 * The server, not the screen, enforces the pilot: compatibility, generator availability and the chapter scope.
 */
class PedagogicalWorkflowApiTest extends CoursePresentationTestCase
{
    protected bool $workflowPilot = true;

    private function api(string $path, array $query)
    {
        return $this->asTeacher()->getJson('/api/lms/course-presentation/' . $path . '?' . http_build_query($query));
    }

    public function test_options_carry_the_server_compatibility_map_for_the_pilot_chapter(): void
    {
        $this->seedPilotChapter();
        $json = $this->api('options', ['chapter_id' => self::CHAPTER])->assertOk()->json();
        $this->assertTrue($json['workflow']['enabled']);
        $this->assertCount(10, $json['workflow']['formats_by_method']);
        $inquiry = array_column($json['workflow']['formats_by_method']['inquiry_based'], null, 'key');
        $this->assertTrue($inquiry['interactive_slides']['available']);
        $this->assertFalse($inquiry['socratic_dialogue']['available']);
        $this->assertArrayNotHasKey('branching_scenario', array_column($json['workflow']['formats_by_method']['explicit_instruction'], null, 'key'));
    }

    public function test_the_workflow_endpoint_returns_the_stages_of_the_chosen_method(): void
    {
        $this->seedPilotChapter();
        $json = $this->api('workflow', ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'branching_scenario'])->assertOk()->json();
        $this->assertCount(6, $json['workflow']['stages']);
        $this->assertSame('Engage: curiosity creation', $json['workflow']['stages'][0]['name']);
        $this->assertGreaterThan(0, $json['workflow']['total_minutes']);

        $this->api('workflow', ['chapter_id' => self::CHAPTER, 'pedagogy' => 'gamified'])->assertOk()->assertJsonCount(7, 'workflow.stages');
    }

    public function test_an_incompatible_or_generatorless_pair_is_refused_everywhere_by_the_server(): void
    {
        $this->seedPilotChapter();
        foreach ([['explicit_instruction', 'branching_scenario', 'does not suit'], ['inquiry_based', 'socratic_dialogue', 'No generator']] as [$method, $format, $why]) {
            $query = ['chapter_id' => self::CHAPTER, 'pedagogy' => $method, 'format' => $format];
            foreach (['preflight', 'fit', 'workflow'] as $path) {
                $response = $this->api($path, $query)->assertStatus(422)->assertJsonPath('status_code', 0);
                $this->assertStringContainsString($why, $response->json('message'));
            }
            // Generate also refuses a format with no generator at request validation, before the workflow gate.
            $this->asTeacher()->postJson('/api/lms/course-presentation/generate', $query)->assertStatus(422);
        }
    }

    public function test_a_compatible_pair_is_not_refused_by_the_workflow_gate(): void
    {
        $this->seedPilotChapter();
        $this->api('preflight', ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides'])->assertOk();
    }

    public function test_the_workflow_is_not_offered_for_a_chapter_outside_the_pilot(): void
    {
        $this->seedPilotChapter();
        // The same chapter with the pilot pointed at another chapter id: the card behaves as it always did.
        config(['pedagogical_workflow.scope.chapter_id' => 9999]);
        $json = $this->api('options', ['chapter_id' => self::CHAPTER])->assertOk()->json();
        $this->assertArrayNotHasKey('workflow', $json);
        $this->api('workflow', ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based'])->assertStatus(404);
        $this->api('preflight', ['chapter_id' => self::CHAPTER, 'pedagogy' => 'explicit_instruction', 'format' => 'branching_scenario'])->assertOk();
    }
}
