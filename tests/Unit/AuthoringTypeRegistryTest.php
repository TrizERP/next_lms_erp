<?php

namespace Tests\Unit;

use App\Services\lms\Content\AuthoringTypeRegistry;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The registry that makes ONE authoring endpoint serve three surfaces — tracker row 3 /
 * Decision #36.
 *
 * NO DATABASE, no network. phpunit.xml points at the live shared vivek_erp.
 *
 * The real assertion of this phase is the last test: adding an authoring surface must be a
 * config entry, not a new controller. If that ever stops being true, the three flows were
 * moved rather than consolidated.
 */
class AuthoringTypeRegistryTest extends TestCase
{
    private function registry(): AuthoringTypeRegistry
    {
        return new AuthoringTypeRegistry();
    }

    public function test_it_covers_all_three_surfaces_the_tracker_names(): void
    {
        $keys = $this->registry()->keys();

        // Classroom Resource, Teacher Workspace, Question Bank — the 3-way split.
        $this->assertContains('presentation', $keys, 'Classroom Resource authoring.');
        $this->assertContains('teacher_training', $keys, 'Teacher Workspace authoring.');
        $this->assertContains('question', $keys, 'Question Bank authoring.');
    }

    public function test_each_surface_declares_the_estate_it_writes(): void
    {
        $r = $this->registry();

        $this->assertSame('content', $r->entityType('presentation'));
        $this->assertSame('content', $r->entityType('teacher_training'));
        $this->assertSame('question', $r->entityType('question'));
    }

    public function test_permission_module_differs_between_content_and_question_bank(): void
    {
        // The gate is per surface: creating a question is not the same right as creating
        // a classroom resource.
        $this->assertSame('lms.content', $this->registry()->permissionModule('presentation'));
        $this->assertSame('lms.question_bank', $this->registry()->permissionModule('question'));
    }

    public function test_a_surface_with_no_generator_does_not_advertise_generate(): void
    {
        // Declaring a mode with no provider behind it would promise something the endpoint
        // cannot deliver, and would surface as a runtime error instead of a clear 422.
        $r = $this->registry();

        $this->assertFalse($r->supportsMode('video', 'generate'));
        $this->assertTrue($r->supportsMode('video', 'upload'));
        $this->assertNull($r->provider('video'));
    }

    public function test_question_generation_is_generate_only(): void
    {
        $r = $this->registry();

        $this->assertTrue($r->supportsMode('question', 'generate'));
        $this->assertFalse($r->supportsMode('question', 'upload'), 'There is no file to upload for a question.');
    }

    /**
     * The reason upload_mimes is per type rather than one global list.
     *
     * The mobile writer accepts pdf,mp3,mp4,html,jpg,jpeg,png,link
     * (teacherapiController.php:343) while the web upload accepts only pdf,ppt,pptx
     * (ApiLmsCourseController.php:1505). A single hardcoded list would break one of them,
     * which is the main reason the mobile writer cannot migrate onto this endpoint yet.
     */
    public function test_upload_allowlists_are_per_surface_not_global(): void
    {
        $r = $this->registry();

        $this->assertSame(['pdf', 'ppt', 'pptx'], $r->uploadMimes('presentation'));
        $this->assertContains('mp4', $r->uploadMimes('video'));
        $this->assertNotContains('mp4', $r->uploadMimes('presentation'));
        $this->assertSame([], $r->uploadMimes('question'));
    }

    public function test_an_unknown_type_throws_with_the_registered_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown authoring content_type "nope"/');

        $this->registry()->get('nope');
    }

    public function test_the_vocabulary_payload_can_drive_a_form_without_a_react_change(): void
    {
        $vocab = $this->registry()->vocabulary();

        $this->assertNotEmpty($vocab);

        foreach ($vocab as $entry) {
            foreach (['content_type', 'label', 'modes', 'entity_type', 'permission', 'upload_mimes', 'has_generator'] as $key) {
                $this->assertArrayHasKey($key, $entry, "Vocabulary entry is missing \"{$key}\".");
            }
            $this->assertNotEmpty($entry['modes'], "\"{$entry['content_type']}\" declares no modes, so it can never be used.");
        }
    }

    /**
     * THE test of this phase.
     *
     * If a new surface needs a controller, the three flows were relocated rather than
     * consolidated. Every registered type must be fully describable from config alone.
     */
    public function test_every_registered_type_is_fully_resolvable_from_config_alone(): void
    {
        $r = $this->registry();

        foreach ($r->keys() as $type) {
            $spec = $r->get($type);

            $this->assertArrayHasKey('modes', $spec);
            $this->assertContains($r->entityType($type), ['content', 'question', 'teacher_resource', 'h5p']);
            $this->assertNotSame('', $r->permissionModule($type));

            // A generate-capable type must name a provider, and vice versa.
            $this->assertSame(
                $r->supportsMode($type, 'generate'),
                $r->provider($type) !== null,
                "\"{$type}\" must declare a provider if and only if it supports generate."
            );
        }
    }
}
