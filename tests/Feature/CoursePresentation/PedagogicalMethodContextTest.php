<?php

namespace Tests\Feature\CoursePresentation;

use App\Models\PedagogicalMethodContext;
use App\Services\CoursePresentation\MethodContextRepository;
use App\Services\CoursePresentation\PedagogicalWorkflow;
use App\Services\StudyDeck\TeachingMethodProfile;
use Database\Seeders\PedagogicalMethodContextSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The stored teaching-method contexts. Runs on the throwaway in-memory sqlite of CoursePresentationTestCase; the migration and
 * seeder are applied HERE only, never to a real database.
 */
class PedagogicalMethodContextTest extends CoursePresentationTestCase
{
    protected bool $workflowPilot = true;

    private const KEYS = ['explicit_instruction', 'inquiry_based', 'problem_based', 'project_based', 'experiential', 'collaborative', 'socratic', 'flipped_classroom', 'differentiated_mastery', 'gamified'];

    private function migrate(): void
    {
        $migration = require base_path('database/migrations/2026_11_02_100000_create_pedagogical_method_contexts_table.php');
        $migration->up();
    }

    private function seedContexts(): void
    {
        $this->migrate();
        (new PedagogicalMethodContextSeeder())->run();
    }

    private function workflow(): PedagogicalWorkflow
    {
        return new PedagogicalWorkflow(null, new MethodContextRepository());
    }

    public function test_the_seeder_stores_all_ten_methods_and_can_run_again_without_duplicates(): void
    {
        $this->seedContexts();
        (new PedagogicalMethodContextSeeder())->run();
        $this->assertSame(10, PedagogicalMethodContext::count());
        $this->assertEqualsCanonicalizing(self::KEYS, PedagogicalMethodContext::pluck('method_key')->all());
        $this->assertSame(0, PedagogicalMethodContext::where('is_active', false)->count());
    }

    public function test_the_method_key_is_unique(): void
    {
        $this->seedContexts();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('pedagogical_method_contexts')->insert(['method_key' => 'gamified', 'method_name' => 'x', 'method_definition' => 'x', 'teaching_philosophy' => 'x', 'core_principles' => '[]', 'teacher_role' => 'x', 'student_role' => 'x', 'teaching_strategies' => '[]', 'workflow_stages' => '[]', 'classroom_activities' => '[]', 'questioning_strategies' => '[]', 'assessment_strategy' => '[]', 'ai_generation_instructions' => 'x']);
    }

    public function test_every_method_has_all_eleven_context_fields_populated_and_keeps_its_detailed_stages(): void
    {
        $this->seedContexts();
        $config = config('pedagogical_workflow.methods');
        foreach (PedagogicalMethodContext::all() as $row) {
            foreach (PedagogicalMethodContext::CONTEXT_FIELDS as $field) {
                $this->assertNotEmpty($row->{$field}, "{$row->method_key} lacks $field");
            }
            // The detailed workflow is carried over whole, not summarised.
            $this->assertSame($config[$row->method_key]['stages'], $row->workflow_stages, $row->method_key);
            $this->assertStringContainsString(TeachingMethodProfile::get($row->method_key)['directive'], $row->ai_generation_instructions);
            foreach ($row->workflow_stages as $stage) {
                foreach (['name', 'objective', 'teacher', 'student', 'activity', 'questions', 'interactions', 'visual', 'outcome', 'minutes'] as $f) {
                    $this->assertNotEmpty($stage[$f], "{$row->method_key} stage lacks $f");
                }
            }
        }
    }

    public function test_inquiry_based_keeps_its_six_stages_in_order(): void
    {
        $this->seedContexts();
        $stages = PedagogicalMethodContext::where('method_key', 'inquiry_based')->first()->workflow_stages;
        $this->assertCount(6, $stages);
        foreach (['Engage', 'Ask', 'Explore', 'Explain', 'Elaborate', 'Evaluate'] as $i => $name) {
            $this->assertStringStartsWith($name, $stages[$i]['name']);
        }
    }

    public function test_the_workflow_reads_roles_stages_and_instructions_from_the_database(): void
    {
        $this->seedContexts();
        $row = PedagogicalMethodContext::where('method_key', 'inquiry_based')->first();
        $stages = $row->workflow_stages;
        $stages[0]['name'] = 'Engage: stored in the database';
        $row->update(['workflow_stages' => $stages, 'teacher_role' => 'STORED TEACHER ROLE', 'ai_generation_instructions' => 'STORED AI INSTRUCTIONS']);

        $w = $this->workflow();
        $wf = $w->workflow('inquiry_based');
        $this->assertSame('Engage: stored in the database', $wf['stages'][0]['name']);
        $this->assertSame('STORED TEACHER ROLE', $wf['teacher_role']);

        $ctx = $w->generationContext('inquiry_based', 'interactive_slides');
        $this->assertSame('database', $ctx['method']['source']);
        $this->assertSame('STORED AI INSTRUCTIONS', $ctx['method']['ai_instructions']);
    }

    public function test_the_ai_prompt_receives_the_database_context(): void
    {
        $this->seedContexts();
        PedagogicalMethodContext::where('method_key', 'inquiry_based')->update(['ai_generation_instructions' => 'STORED AI INSTRUCTIONS', 'teaching_philosophy' => 'STORED PHILOSOPHY']);
        $ctx = $this->workflow()->generationContext('inquiry_based', 'branching_scenario');

        foreach (['planner', 'content', 'interaction', 'story'] as $kind) {
            $rule = PedagogicalWorkflow::rule($ctx, $kind);
            $this->assertStringContainsString('STORED AI INSTRUCTIONS', $rule, $kind);
            // The interaction prompt gets the student role, activities, interaction types and questioning style instead of the philosophy.
            if ($kind !== 'interaction') {
                $this->assertStringContainsString('STORED PHILOSOPHY', $rule, $kind);
            }
            $this->assertStringContainsString('Evaluate', $rule, $kind);
        }
        $this->assertStringContainsString('STORED AI INSTRUCTIONS', TeachingMethodProfile::plannerRule('inquiry_based', $ctx));
    }

    public function test_generation_never_changes_the_stored_context(): void
    {
        $this->seedContexts();
        $before = DB::table('pedagogical_method_contexts')->orderBy('id')->get()->all();
        $w = $this->workflow();
        foreach ($w->compatibility() as $method => $formats) {
            foreach ($formats as $format) {
                $ctx = $w->generationContext($method, $format);
                if ($ctx !== null) {
                    PedagogicalWorkflow::rule($ctx, 'planner');
                }
            }
        }
        $this->assertEquals($before, DB::table('pedagogical_method_contexts')->orderBy('id')->get()->all());
    }

    public function test_without_the_table_or_a_row_the_config_is_used_and_nothing_breaks(): void
    {
        // Table not created: the migration has not been applied yet.
        $w = $this->workflow();
        $this->assertCount(10, $w->methods());
        $this->assertSame('config', $w->generationContext('inquiry_based', 'interactive_slides')['method']['source']);

        // Table present, one method not stored.
        $this->seedContexts();
        PedagogicalMethodContext::where('method_key', 'gamified')->delete();
        $w = $this->workflow();
        $this->assertCount(10, $w->methods());
        $this->assertSame('config', $w->generationContext('gamified', 'interactive_slides')['method']['source']);
        $this->assertSame('database', $w->generationContext('socratic', 'interactive_slides')['method']['source']);
    }

    public function test_an_incomplete_stored_row_is_ignored_in_favour_of_the_config(): void
    {
        $this->seedContexts();
        PedagogicalMethodContext::where('method_key', 'socratic')->update(['teaching_philosophy' => '']);
        $this->assertSame('config', $this->workflow()->generationContext('socratic', 'interactive_slides')['method']['source']);
    }

    public function test_an_inactive_method_is_unavailable_and_refused(): void
    {
        $this->seedContexts();
        PedagogicalMethodContext::where('method_key', 'gamified')->update(['is_active' => false]);
        $w = $this->workflow();
        $this->assertArrayNotHasKey('gamified', $w->methods());
        $this->assertCount(9, $w->methods());
        $this->assertNull($w->workflow('gamified'));
        $this->assertNull($w->generationContext('gamified', 'interactive_slides'));
        $this->assertStringContainsString('not available', (string) $w->refusal('gamified', 'interactive_slides'));
        $this->assertNull($w->refusal('inquiry_based', 'interactive_slides'));
    }

    public function test_scope_compatibility_and_generator_availability_are_unchanged_with_stored_contexts(): void
    {
        $this->seedContexts();
        $w = $this->workflow();
        $this->assertTrue($w->appliesTo(['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => 1]));
        foreach ([['id' => 8593], ['standard_id' => 43], ['subject_id' => 1], ['sub_institute_id' => 2]] as $change) {
            $this->assertFalse($w->appliesTo($change + ['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => 1]));
        }
        $this->assertNull($w->refusal('inquiry_based', 'interactive_slides'));
        $this->assertNull($w->refusal('inquiry_based', 'branching_scenario'));
        $this->assertStringContainsString('No generator', (string) $w->refusal('inquiry_based', 'socratic_dialogue'));
        $this->assertStringContainsString('does not suit', (string) $w->refusal('explicit_instruction', 'branching_scenario'));
    }

    public function test_a_chapter_outside_the_pilot_gets_no_workflow_even_though_the_contexts_are_global(): void
    {
        $this->seedContexts();
        $this->seedPilotChapter();
        config(['pedagogical_workflow.scope.chapter_id' => 9999]);
        $json = $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=' . self::CHAPTER)->assertOk()->json();
        $this->assertArrayNotHasKey('workflow', $json);
        $this->assertSame(PedagogicalMethodContext::count(), 10);
    }
}
