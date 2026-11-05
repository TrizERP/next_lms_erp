<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\PedagogicalWorkflow;
use App\Services\StudyDeck\TeachingMethodProfile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Std 9 chapter 1 of Mathematics, English and Social Science run the staged workflow with the methods and formats that suit
 * their subject; Science keeps exactly what it had. No model and no real database is used.
 */
class PedagogicalWorkflowSubjectsTest extends CoursePresentationTestCase
{
    protected bool $workflowPilot = true;

    private const SCIENCE = ['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975, 'sub_institute_id' => 1];
    private const MATHS = ['id' => 8594, 'standard_id' => 42, 'subject_id' => 3976, 'sub_institute_id' => 1];
    private const ENGLISH = ['id' => 8622, 'standard_id' => 42, 'subject_id' => 3978, 'sub_institute_id' => 1];
    private const SOCIAL = ['id' => 8608, 'standard_id' => 42, 'subject_id' => 4469, 'sub_institute_id' => 1];

    /** @return array<string,array{0:array<string,int>,1:array<int,string>,2:array<int,string>}> chapter row, methods offered, methods left out */
    public static function subjects(): array
    {
        return [
            'mathematics' => [self::MATHS, ['explicit_instruction', 'inquiry_based', 'experiential', 'differentiated_mastery', 'gamified', 'problem_based', 'collaborative'], ['project_based', 'flipped_classroom', 'socratic']],
            'english' => [self::ENGLISH, ['experiential', 'explicit_instruction', 'collaborative', 'socratic', 'gamified', 'differentiated_mastery'], ['inquiry_based', 'problem_based', 'project_based', 'flipped_classroom']],
            'social science' => [self::SOCIAL, ['inquiry_based', 'experiential', 'project_based', 'problem_based', 'collaborative', 'socratic', 'explicit_instruction'], ['flipped_classroom', 'gamified', 'differentiated_mastery']],
        ];
    }

    #[DataProvider('subjects')]
    public function test_each_subject_chapter_is_in_scope_only_when_all_four_ids_match(array $chapter): void
    {
        $w = new PedagogicalWorkflow();
        $this->assertTrue($w->appliesTo($chapter));
        $this->assertTrue($w->appliesToIds($chapter['id'], 1));
        foreach (['id' => $chapter['id'] + 1, 'standard_id' => 43, 'subject_id' => 1, 'sub_institute_id' => 2] as $field => $other) {
            $this->assertFalse($w->appliesTo([$field => $other] + $chapter), "changing $field must switch the workflow off");
        }
        $this->assertFalse($w->appliesToIds($chapter['id'], 2));
    }

    public function test_chapter_two_of_the_same_subjects_is_not_in_scope(): void
    {
        $w = new PedagogicalWorkflow();
        foreach ([[8595, 3976], [8623, 3978], [8609, 4469], [8593, 3975]] as [$id, $subject]) {
            $this->assertFalse($w->appliesTo(['id' => $id, 'standard_id' => 42, 'subject_id' => $subject, 'sub_institute_id' => 1]), (string) $id);
        }
    }

    #[DataProvider('subjects')]
    public function test_a_subject_offers_only_its_methods_in_its_order_and_refuses_the_rest(array $chapter, array $offered, array $left): void
    {
        $w = (new PedagogicalWorkflow())->forChapter($chapter);
        $this->assertSame($offered, array_keys($w->methods()));
        $this->assertSame($offered, array_keys($w->compatibility()));
        foreach ($left as $method) {
            $this->assertNull($w->workflow($method), $method);
            $this->assertStringContainsString('not available', (string) $w->refusal($method, 'interactive_slides'), $method);
            $this->assertNull($w->generationContext($method, 'interactive_slides'), $method);
        }
    }

    #[DataProvider('subjects')]
    public function test_every_offered_method_has_stages_formats_and_a_pipeline_profile(array $chapter, array $offered): void
    {
        $w = (new PedagogicalWorkflow())->forChapter($chapter);
        $formats = (array) config('pedagogical_workflow.formats');
        foreach ($offered as $method) {
            $this->assertContains($method, TeachingMethodProfile::keys(), "$method must exist in the shared pipeline");
            $wf = $w->workflow($method);
            $this->assertNotEmpty($wf['stages'], $method);
            foreach ($wf['stages'] as $stage) {
                foreach (['name', 'objective', 'teacher', 'student', 'activity', 'questions', 'interactions', 'visual', 'outcome', 'minutes'] as $field) {
                    $this->assertNotEmpty($stage[$field], "$method stage {$stage['number']} lacks $field");
                }
            }
            $keys = array_column($w->formatsFor($method), 'key');
            $this->assertNotEmpty($keys, $method);
            $this->assertSame($keys, array_values(array_unique($keys)), "$method lists a format twice");
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $formats, "$method names an unknown format $key");
            }
            // The two formats with a generator are the only ones that can be chosen.
            foreach ($w->formatsFor($method) as $f) {
                $this->assertSame(in_array($f['key'], ['interactive_slides', 'branching_scenario'], true), $f['available'], "$method / {$f['key']}");
            }
        }
    }

    #[DataProvider('subjects')]
    public function test_every_subject_method_can_be_generated_as_slides_or_a_story_when_the_format_suits_it(array $chapter, array $offered): void
    {
        $w = (new PedagogicalWorkflow())->forChapter($chapter);
        $reached = [];
        foreach ($offered as $method) {
            foreach (['interactive_slides', 'branching_scenario'] as $format) {
                $ctx = $w->generationContext($method, $format);
                if ($ctx === null) {
                    $this->assertNotNull($w->refusal($method, $format), "$method / $format");
                    continue;
                }
                $reached[$method] = true;
                $this->assertSame($chapter['id'], $ctx['chapter_id'], 'the context belongs to the chapter it was bound to');
                $this->assertSame($format, $ctx['format']['key']);
                $this->assertNotEmpty($ctx['subject']);
            }
        }
        $this->assertSame($offered, array_keys($reached), 'a method that cannot be generated in either available format would be a dead end on the screen');
    }

    public function test_the_subject_note_the_wording_and_the_picture_policy_reach_the_context(): void
    {
        $maths = (new PedagogicalWorkflow())->forChapter(self::MATHS)->generationContext('inquiry_based', 'interactive_slides');
        $this->assertSame('Mathematics', $maths['subject']);
        $this->assertSame('diagrams', $maths['images']);
        $this->assertStringContainsString('Mathematics chapter', $maths['method']['ai_instructions']);
        $this->assertStringContainsString('Never state the concept before the evidence stage.', $maths['method']['ai_instructions'], 'the method instruction is kept');
        $explain = $maths['stages'][3];
        $this->assertStringContainsString('mathematical', $explain['teacher']);
        $this->assertStringNotContainsStringIgnoringCase('scientific', json_encode($maths['stages']));

        $english = (new PedagogicalWorkflow())->forChapter(self::ENGLISH)->generationContext('experiential', 'interactive_slides');
        $this->assertSame('diagrams', $english['images']);
        $this->assertStringContainsString('literary', json_encode($english['stages']));

        $social = (new PedagogicalWorkflow())->forChapter(self::SOCIAL)->generationContext('inquiry_based', 'interactive_slides');
        $this->assertSame('photographs', $social['images'], 'Social Science keeps the photograph-first picture policy');
        $this->assertStringContainsString('social-science', json_encode($social['stages']));
    }

    public function test_the_rule_of_a_subject_reaches_every_prompt_kind(): void
    {
        $ctx = (new PedagogicalWorkflow())->forChapter(self::ENGLISH)->generationContext('collaborative', 'branching_scenario');
        foreach (['planner', 'content', 'interaction', 'story'] as $kind) {
            $this->assertStringContainsString('English chapter', PedagogicalWorkflow::rule($ctx, $kind), $kind);
        }
    }

    public function test_the_wrong_format_for_a_subject_method_is_refused_with_the_reason(): void
    {
        $maths = (new PedagogicalWorkflow())->forChapter(self::MATHS);
        $this->assertStringContainsString('does not suit', (string) $maths->refusal('inquiry_based', 'branching_scenario'));
        $this->assertNull($maths->refusal('gamified', 'branching_scenario'));
        $this->assertNull($maths->refusal('explicit_instruction', 'interactive_slides'));
        $this->assertStringContainsString('No generator', (string) $maths->refusal('explicit_instruction', 'worksheet_guide'));

        $english = (new PedagogicalWorkflow())->forChapter(self::ENGLISH);
        $this->assertNull($english->refusal('experiential', 'branching_scenario'));
        $this->assertStringContainsString('does not suit', (string) $english->refusal('explicit_instruction', 'branching_scenario'));
    }

    public function test_science_is_exactly_what_it_was(): void
    {
        $plain = new PedagogicalWorkflow();
        $bound = $plain->forChapter(self::SCIENCE);
        $this->assertCount(10, $bound->methods());
        $this->assertSame($plain->methods(), $bound->methods());
        $this->assertSame($plain->compatibility(), $bound->compatibility());
        $this->assertSame((array) config('pedagogical_workflow.compatibility'), $bound->compatibility());
        $ctx = $bound->generationContext('inquiry_based', 'interactive_slides');
        $this->assertSame(8592, $ctx['chapter_id']);
        $this->assertArrayNotHasKey('subject', $ctx);
        $this->assertArrayNotHasKey('images', $ctx);
        $this->assertSame($plain->generationContext('inquiry_based', 'interactive_slides'), $ctx);
        // Science still says "scientific" (the replacement belongs to the other subjects only).
        $this->assertStringContainsString('scientific words', json_encode($ctx['stages']));
    }

    public function test_binding_a_copy_does_not_change_the_original(): void
    {
        $plain = new PedagogicalWorkflow();
        $plain->forChapter(self::MATHS);
        $this->assertCount(10, $plain->methods());
        $this->assertSame(8592, $plain->generationContext('inquiry_based', 'interactive_slides')['chapter_id']);
    }

    public function test_the_server_offers_the_subject_methods_and_refuses_the_others(): void
    {
        $this->seedChapter(self::MATHS, 'Mathematics', 'Orienting Yourself: The Use of Coordinates', 48);
        $json = $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=8594')->assertOk()->json();
        $this->assertTrue($json['workflow']['enabled']);
        $this->assertSame(self::subjects()['mathematics'][1], array_keys($json['workflow']['formats_by_method']));
        $slides = array_column($json['workflow']['formats_by_method']['gamified'], null, 'key');
        $this->assertTrue($slides['branching_scenario']['available']);

        $query = fn (string $method, string $format) => '?' . http_build_query(['chapter_id' => 8594, 'pedagogy' => $method, 'format' => $format]);
        $this->asTeacher()->getJson('/api/lms/course-presentation/workflow' . $query('project_based', 'interactive_slides'))->assertStatus(422)
            ->assertJsonPath('message', 'This teaching method is not available for this chapter.');
        $this->asTeacher()->getJson('/api/lms/course-presentation/preflight' . $query('flipped_classroom', 'interactive_slides'))->assertStatus(422);
        $this->asTeacher()->getJson('/api/lms/course-presentation/fit' . $query('inquiry_based', 'branching_scenario'))->assertStatus(422);
        $this->asTeacher()->getJson('/api/lms/course-presentation/workflow' . $query('gamified', 'branching_scenario'))->assertOk()->assertJsonCount(7, 'workflow.stages');
    }

    public function test_the_server_does_not_enable_a_neighbouring_chapter(): void
    {
        $this->seedChapter(['id' => 8595, 'standard_id' => 42, 'subject_id' => 3976, 'sub_institute_id' => 1], 'Mathematics', 'Introduction to Linear Polynomials', 49);
        // Not in the card's scope at all: refused before the workflow is asked.
        $this->asTeacher()->getJson('/api/lms/course-presentation/options?chapter_id=8595')->assertStatus(403);
    }

    /** @param array<string,int> $chapter */
    private function seedChapter(array $chapter, string $subject, string $name, int $extraction): void
    {
        DB::table('standard')->insertOrIgnore(['id' => $chapter['standard_id'], 'name' => '9']);
        DB::table('subject')->insertOrIgnore(['id' => $chapter['subject_id'], 'subject_name' => $subject]);
        DB::table('chapter_master')->insert(['id' => $chapter['id'], 'chapter_name' => $name, 'standard_id' => $chapter['standard_id'], 'subject_id' => $chapter['subject_id'], 'sub_institute_id' => $chapter['sub_institute_id'], 'extraction_id' => $extraction]);
        DB::table('document_extractions')->insert(['id' => $extraction, 'document_type' => 'Chapter', 'chapter_id' => $chapter['id'], 'standard_id' => $chapter['standard_id'], 'subject_id' => $chapter['subject_id'], 'sub_institute_id' => $chapter['sub_institute_id'], 'md_content' => str_repeat('Points lie on a grid. ', 400)]);
    }
}
