<?php

namespace Tests\Unit;

use App\Http\Controllers\api\lms\HomeworkSubmissionApiController;
use App\Support\LearnerContentScope;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * A student may open only their own standard's content, and only their own homework submission.
 *
 * NO LIVE DATABASE. This extends the plain PHPUnit TestCase (not Tests\TestCase, which boots Laravel and reads the
 * shared vivek_erp at boot) and builds a throwaway in-memory sqlite with just the tables these rules read.
 */
class LearnerContentScopeTest extends TestCase
{
    private Capsule $db;
    private object $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Capsule();
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->db->setAsGlobal();

        $this->session = new class {
            public array $data = [];

            public function get($key, $default = null)
            {
                return $this->data[$key] ?? $default;
            }
        };

        $app = new Container();
        $app->instance('db', $this->db->getDatabaseManager());
        $app->instance('session', $this->session);
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('academic_year', function ($t) {
            $t->integer('sub_institute_id');
            $t->integer('syear');
            $t->string('start_date');
            $t->string('end_date');
        });
        $schema->create('tblstudent_enrollment', function ($t) {
            $t->integer('student_id');
            $t->integer('sub_institute_id');
            $t->integer('syear');
            $t->integer('standard_id');
        });
        $schema->create('academic_section', function ($t) {
            $t->integer('id');
            $t->integer('sub_institute_id');
            $t->string('title');
        });
        $schema->create('standard', function ($t) {
            $t->integer('id');
            $t->integer('sub_institute_id');
            $t->integer('grade_id');
        });
        $schema->create('sub_std_map', function ($t) {
            $t->integer('sub_institute_id');
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('elective_subject')->nullable();
        });
        $schema->create('student_optional_subject', function ($t) {
            $t->integer('sub_institute_id');
            $t->integer('student_id');
            $t->integer('subject_id');
        });

        // School 1: the current academic year is 2022 (the real login year), a student in Std 9 (id 42) now, who was
        // in Std 8 (id 41) the year before.
        $today = date('Y-m-d');
        $this->db->table('academic_year')->insert([
            ['sub_institute_id' => 1, 'syear' => 2022, 'start_date' => '2000-01-01', 'end_date' => '2999-12-31'],
            ['sub_institute_id' => 1, 'syear' => 2021, 'start_date' => '2000-01-01', 'end_date' => '2001-01-01'],
        ]);
        $this->db->table('tblstudent_enrollment')->insert([
            ['student_id' => 500, 'sub_institute_id' => 1, 'syear' => 2022, 'standard_id' => 42],
            ['student_id' => 500, 'sub_institute_id' => 1, 'syear' => 2021, 'standard_id' => 41],
            ['student_id' => 501, 'sub_institute_id' => 1, 'syear' => 2022, 'standard_id' => 43],
            // Enrolled only in a past year: falls back to the most recent enrollment.
            ['student_id' => 502, 'sub_institute_id' => 1, 'syear' => 2021, 'standard_id' => 41],
        ]);
        $this->db->table('academic_section')->insert(['id' => 90, 'sub_institute_id' => 1, 'title' => 'Other']);
        $this->db->table('standard')->insert(['id' => 99, 'sub_institute_id' => 1, 'grade_id' => 90]);
        $this->db->table('sub_std_map')->insert([
            ['sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'elective_subject' => 'No'],
            ['sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 4500, 'elective_subject' => 'Yes'],
        ]);
        unset($today);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function request(array $attributes): Request
    {
        $request = Request::create('/api/lms-chapter-content', 'POST');
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        return $request;
    }

    private function student(int $id, int $school = 1): array
    {
        return ['user_id' => $id, 'school' => $school, 'is_student' => true];
    }

    public function test_identity_comes_only_from_what_the_guard_verified(): void
    {
        $this->assertNull(LearnerContentScope::identity($this->request([])));

        $identity = LearnerContentScope::identity($this->request([
            'verified_user_id' => 500, 'verified_school' => 1, 'verified_is_student' => true,
        ]));
        $this->assertSame(['user_id' => 500, 'school' => 1, 'is_student' => true], $identity);
    }

    public function test_a_request_body_cannot_make_someone_a_student_or_a_staff_member(): void
    {
        // The body is ignored entirely: a "Student" claim in it does not create an identity.
        $request = Request::create('/x', 'POST', ['user_profile_name' => 'Student', 'user_id' => 500, 'is_student' => 1]);
        $this->assertNull(LearnerContentScope::identity($request));
        $this->assertFalse(LearnerContentScope::isLearner(LearnerContentScope::identity($request)));
    }

    public function test_the_standard_of_the_current_year_is_allowed_and_last_years_is_not(): void
    {
        $this->assertSame([42, 99], LearnerContentScope::allowedStandardIds(500, 1));
    }

    public function test_a_student_with_no_current_enrollment_falls_back_to_the_latest_one(): void
    {
        $this->assertSame([41, 99], LearnerContentScope::allowedStandardIds(502, 1));
    }

    public function test_an_unknown_student_or_school_gets_nothing(): void
    {
        $this->assertSame([99], LearnerContentScope::allowedStandardIds(999, 1));
        $this->assertSame([], LearnerContentScope::allowedStandardIds(0, 1));
        $this->assertSame([], LearnerContentScope::allowedStandardIds(500, 0));
        // Another school's enrollment is not this school's.
        $this->assertSame([], LearnerContentScope::allowedStandardIds(500, 2));
    }

    public function test_a_student_opens_their_own_standard_but_not_another(): void
    {
        $this->assertTrue(LearnerContentScope::mayOpen($this->student(500), 42, 3975));
        $this->assertFalse(LearnerContentScope::mayOpen($this->student(500), 43, 3975), 'Std 10 must stay closed to a Std 9 student');
        $this->assertFalse(LearnerContentScope::mayOpen($this->student(500), 41, 3975), 'last year\'s standard is not the current one');
        $this->assertTrue(LearnerContentScope::mayOpen($this->student(501), 43, 3975));
        $this->assertFalse(LearnerContentScope::mayOpen($this->student(501), 42, 3975));
    }

    public function test_the_shared_other_section_stays_open(): void
    {
        $this->assertTrue(LearnerContentScope::mayOpen($this->student(500), 99, 1));
    }

    public function test_an_elective_is_open_only_to_the_student_who_chose_it(): void
    {
        $this->assertFalse(LearnerContentScope::mayOpen($this->student(500), 42, 4500));

        $this->db->table('student_optional_subject')->insert(['sub_institute_id' => 1, 'student_id' => 500, 'subject_id' => 4500]);
        $this->assertTrue(LearnerContentScope::mayOpen($this->student(500), 42, 4500));
    }

    public function test_a_chapter_without_a_standard_is_closed_to_a_student(): void
    {
        $this->assertFalse(LearnerContentScope::mayOpenChapter($this->student(500), null));
        $this->assertFalse(LearnerContentScope::mayOpenChapter($this->student(500), (object) ['standard_id' => null, 'subject_id' => 3975]));
        $this->assertTrue(LearnerContentScope::mayOpenChapter($this->student(500), (object) ['standard_id' => 42, 'subject_id' => 3975]));
    }

    public function test_staff_and_unidentified_callers_are_not_restricted_here(): void
    {
        $staff = ['user_id' => 7, 'school' => 1, 'is_student' => false];
        $this->assertTrue(LearnerContentScope::mayOpen($staff, 43, 3975));
        $this->assertTrue(LearnerContentScope::mayOpen(null, 43, 3975));
        $this->assertTrue(LearnerContentScope::mayOpenChapter($staff, (object) ['standard_id' => 43, 'subject_id' => 1]));
    }

    public function test_the_study_content_routes_are_behind_the_api_guard(): void
    {
        $protect = (require dirname(__DIR__, 2).'/config/api_guard.php')['protect'];
        $covered = function (string $path) use ($protect): bool {
            foreach ($protect as $pattern) {
                if (fnmatch($pattern, $path)) {
                    return true;
                }
            }

            return false;
        };

        foreach ([
            'lms-study-deck', 'lms-study-deck/pdf', 'lms-study-deck/image-urls',
            'lms-vocabulary-class', 'lms-classroom-activity',
            'lms-chapter-content', 'lms-courses', 'lms-courses/search',
            'lms-homework/submission-file/12_0', 'lms-homework/submission/ai-status/12',
        ] as $path) {
            $this->assertTrue($covered($path), "$path is reachable without a token");
        }
    }

    // ------------------------------------------------------------------
    // Homework submissions: the row id alone must not be enough
    // ------------------------------------------------------------------

    private function mayRead(array $session, array $homework): bool
    {
        $this->session->data = $session;
        $method = new \ReflectionMethod(HomeworkSubmissionApiController::class, 'mayReadSubmission');
        $method->setAccessible(true);
        $controller = (new \ReflectionClass(HomeworkSubmissionApiController::class))->newInstanceWithoutConstructor();

        return $method->invoke($controller, (object) $homework);
    }

    public function test_a_student_reads_only_their_own_submission(): void
    {
        $row = ['sub_institute_id' => 1, 'student_id' => 500];

        $this->assertTrue($this->mayRead(['sub_institute_id' => 1, 'user_id' => 500, 'is_student' => true], $row));
        $this->assertFalse($this->mayRead(['sub_institute_id' => 1, 'user_id' => 501, 'is_student' => true], $row), 'another student');
        $this->assertFalse($this->mayRead(['sub_institute_id' => 2, 'user_id' => 500, 'is_student' => true], $row), 'another school');
    }

    public function test_a_teacher_reads_submissions_of_their_own_school_only(): void
    {
        $row = ['sub_institute_id' => 1, 'student_id' => 500];

        $this->assertTrue($this->mayRead(['sub_institute_id' => 1, 'user_id' => 7, 'is_student' => false, 'user_profile_name' => 'Teacher'], $row));
        $this->assertFalse($this->mayRead(['sub_institute_id' => 2, 'user_id' => 7, 'is_student' => false, 'user_profile_name' => 'Teacher'], $row));
        $this->assertTrue($this->mayRead(['sub_institute_id' => 2, 'user_id' => 7, 'is_admin' => 2, 'is_student' => false], $row), 'a platform admin spans schools');
        $this->assertFalse($this->mayRead([], $row), 'no verified session at all');
    }
}
