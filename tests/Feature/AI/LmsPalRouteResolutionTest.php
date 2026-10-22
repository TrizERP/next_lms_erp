<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Level 3 screen under LMS + PAL resolves to its own module, so the chatbot opened on it
 * is bound to that module's tools. Config patterns widen the existing ai_modules rows, which
 * predate these screens and cannot be edited without a migration.
 *
 * READ-ONLY: the resolver reads the registry and the module rows and writes nothing.
 */
class LmsPalRouteResolutionTest extends TestCase
{
    /** @return array<string, array{0:string,1:string}> */
    public static function screens(): array
    {
        return [
            'course catalog' => ['/course-master', 'course-master'],
            'global mapping' => ['/lms/global-mapping', 'teach_learn'],
            'homework' => ['/lms/homework', 'exam_assessment'],
            'homework submission' => ['/lms/homework/submission', 'exam_assessment'],
            'homework review' => ['/lms/homework/review', 'exam_assessment'],
            'online exam' => ['/exam/online', 'exam_assessment'],
            'exam progress report' => ['/exam/progress-report', 'exam_assessment'],
            'assignment' => ['/lms/lmsAssignment', 'exam_assessment'],
            'worksheet' => ['/lms/worksheet', 'exam_assessment'],
            'project' => ['/lms/project', 'exam_assessment'],
            'question wise report' => ['/lms/question-wise-report', 'exam_assessment'],
            'leader board' => ['/lmsLeaderboard', 'engagement'],
            'portfolio' => ['/lmsPortfolio', 'engagement'],
            'virtual classroom' => ['/lmsVirtualClassroom', 'engagement'],
            'curriculum plan' => ['/lms/curriculum-planning', 'curriculum_planning'],
            'monthly plan' => ['/lms/monthly-plan', 'curriculum_planning'],
            'lesson plan' => ['/lms/lesson-plan', 'curriculum_planning'],
            'teacher diary' => ['/lms/teacher-diary', 'curriculum_planning'],
            'book list' => ['/lms/book-list', 'curriculum_planning'],
            'syllabus plan' => ['/lms/syllabus-plan', 'curriculum_planning'],
            'student analysis' => ['/lms/student-analysis', 'interactions'],
            'lms dashboard' => ['/lms/dashboard', 'interactions'],
            'new pal' => ['/new_pal/content_model', 'new_pal'],
        ];
    }

    #[DataProvider('screens')]
    public function test_a_screen_resolves_to_its_own_module(string $route, string $module): void
    {
        $resolution = app(ModuleResolver::class)->resolve('Give me a summary', ['route' => $route], 1);

        $this->assertSame($module, $resolution['module']->key, $route);
    }

    public function test_an_unrelated_screen_is_not_claimed(): void
    {
        $resolution = app(ModuleResolver::class)->resolve('Give me a summary', ['route' => '/fees/onboarding'], 1);

        $this->assertSame('fees', $resolution['module']->key);
    }
}
