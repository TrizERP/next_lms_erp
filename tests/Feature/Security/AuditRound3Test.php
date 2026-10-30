<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 2026-10-29 audit: routes that were reachable without a login, and the legacy public scripts.
 * No request here reaches the database: unauthenticated calls are refused before the controller runs.
 */
class AuditRound3Test extends TestCase
{
    private function middlewareOf(string $uri): array
    {
        foreach (Route::getRoutes() as $r) {
            if ($r->uri() === $uri && in_array('GET', $r->methods(), true)) {
                return (array) $r->gatherMiddleware();
            }
        }
        $this->fail("no GET route $uri");
    }

    /** @dataProvider guardedRoutes */
    public function test_student_data_routes_need_a_token(string $uri): void
    {
        $this->assertContains('api.session', $this->middlewareOf($uri));
        $this->getJson('/' . $uri . '?student_id=1&sub_institute_id=1&syear=2025')->assertStatus(401);
    }

    public static function guardedRoutes(): array
    {
        return array_map(fn ($u) => [$u], [
            'map_student/fetchData', 'calendar/fetchData', 'lms/spaced-repetition',
            'lms/forgetting-curve', 'lms/concept-prerequisites', 'lms/pedagogy-recommendations',
        ]);
    }

    public function test_anonymous_otp_and_cron_endpoints_are_throttled(): void
    {
        $this->assertContains('throttle:3,10', $this->middlewareOf('Resend_otp'));
        $this->assertContains('throttle:2,60', $this->middlewareOf('send_birthday_notification'));
        $this->assertContains('throttle:20,1', $this->middlewareOf('ajax_checkEmailExist'));
    }

    public function test_resend_otp_rejects_a_bad_number_before_sending_anything(): void
    {
        $this->getJson('/Resend_otp?mobile=12ab')->assertStatus(422);
    }

    public function test_birthday_cron_refuses_without_the_secret_once_it_is_set(): void
    {
        putenv('CRON_SECRET=s3cret');
        $_ENV['CRON_SECRET'] = 's3cret';
        try {
            $this->getJson('/send_birthday_notification')->assertStatus(401);
            $this->getJson('/send_birthday_notification?secret=wrong')->assertStatus(401);
        } finally {
            putenv('CRON_SECRET');
            unset($_ENV['CRON_SECRET']);
        }
    }

    public function test_getworkflow_script_is_closed_to_the_web_without_the_key(): void
    {
        $src = file_get_contents(base_path('public/getworkflow.php'));
        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $src);
        $this->assertStringContainsString('hash_equals', $src);
        $this->assertStringContainsString('getworkflow.php?k=', file_get_contents(resource_path('views/show_workflow.blade.php')));
    }

    public function test_no_standalone_script_in_public_is_left_open(): void
    {
        foreach (['test', 'general_integration', 'student_integration', 'user_integration', 'school_integration',
                     'subject_batch_timetable', 'student_photo_integration', 'student_document_integration'] as $f) {
            $this->assertStringContainsString("PHP_SAPI !== 'cli'", file_get_contents(base_path("public/$f.php")), $f);
        }
        foreach (['hrms_api', 'library_api'] as $f) {
            $src = file_get_contents(base_path("public/$f.php"));
            $this->assertStringContainsString('auth/auth.php', $src, $f);
            $this->assertStringContainsString("(int) \$_REQUEST['sub_institute_id']", $src, $f);
        }
    }
}
