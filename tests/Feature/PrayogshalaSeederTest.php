<?php

namespace Tests\Feature;

use App\Services\lms\Prayogshala\PrayogshalaService;
use Database\Seeders\PrayogshalaStandard9ScienceSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The first Prayogshala labs (Standard 9 Science, chapters 1 and 2).
 *
 * In-memory SQLite, forced in setUp: phpunit.xml leaves its sqlite lines commented out, so
 * anything else would write to the live shared database. Fixtures reproduce only the NAMES
 * the seeder resolves; ids differ from production to prove nothing is hard-coded.
 */
class PrayogshalaSeederTest extends TestCase
{
    private const STEPS = ['mission', 'predict', 'do', 'observe', 'explain', 'concept', 'apply', 'reflect'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
        });
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name');
        });
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('syear')->nullable();
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('chapter_name');
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
        });
        Schema::create('lms_concept', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->integer('topic_id')->nullable();
            $t->string('name');
        });
        (require base_path('database/migrations/2026_10_08_100000_create_lms_prayogshala_activity_table.php'))->up();

        DB::table('school_setup')->insert([['Id' => 1, 'is_Lms' => 'Y'], ['Id' => 7, 'is_Lms' => 'Y'], ['Id' => 8, 'is_Lms' => 'N']]);
        DB::table('standard')->insert([['id' => 1, 'name' => '9'], ['id' => 2, 'name' => '10']]);
        DB::table('subject')->insert([['id' => 3, 'subject_name' => 'Science']]);
        DB::table('chapter_master')->insert([
            ['id' => 501, 'sub_institute_id' => 1, 'syear' => 2026, 'grade_id' => 12, 'standard_id' => 1, 'subject_id' => 3, 'chapter_name' => 'Exploration: Entering the World of Secondary Science'],
            ['id' => 502, 'sub_institute_id' => 1, 'syear' => 2026, 'grade_id' => 12, 'standard_id' => 1, 'subject_id' => 3, 'chapter_name' => 'Cell: The Building Block of Life'],
        ]);
        $topics = [
            [501, 'Scientific Models and Simplification', 'Question decides relevant details'],
            [501, 'Estimation and Approximate Reasoning', 'Estimate using rates and assumed values'],
            [502, 'How to Study Cells', 'Estimating cell size'],
            [502, 'Cell Membrane and Cell Wall', 'Isotonic, hypotonic and hypertonic solutions'],
        ];
        foreach ($topics as $i => [$chapter, $topic, $concept]) {
            DB::table('topic_master')->insert(['id' => 900 + $i, 'chapter_id' => $chapter, 'name' => $topic]);
            DB::table('lms_concept')->insert(['id' => 700 + $i, 'chapter_id' => $chapter, 'topic_id' => 900 + $i, 'name' => $concept]);
        }
    }

    public function test_it_creates_two_labs_per_chapter_each_with_the_full_eight_step_flow(): void
    {
        $this->seed(PrayogshalaStandard9ScienceSeeder::class);

        $rows = DB::table('lms_prayogshala_activity')->orderBy('chapter_id')->orderBy('sort_order')->get();
        $this->assertCount(4, $rows);
        $this->assertSame([501, 501, 502, 502], $rows->pluck('chapter_id')->map(fn ($v) => (int) $v)->all());
        foreach ($rows as $row) {
            $this->assertSame('review', $row->status, 'AI-authored, so it waits for a teacher');
            $this->assertNotNull($row->topic_id);
            $this->assertNotNull($row->concept_id);
            $lab = json_decode($row->lab_config, true);
            $this->assertNotEmpty($lab['simulation']['type']);
            foreach (self::STEPS as $step) {
                $this->assertArrayHasKey($step, $lab['steps'], "{$row->slug} is missing step {$step}");
            }
            $this->assertNotEmpty($lab['steps']['predict']['options']);
            $this->assertNotEmpty($lab['steps']['apply']['options']);
            $this->assertNotEmpty($lab['outcomes']);
        }
        // Different chapters use different simulations; chapter 2 includes the particle model.
        $engines = $rows->map(fn ($r) => json_decode($r->lab_config, true)['simulation']['type'])->unique()->sort()->values()->all();
        $this->assertSame(['calculator', 'osmosis', 'relevance'], $engines);
    }

    public function test_it_is_idempotent_and_never_regresses_a_teachers_status_change(): void
    {
        $this->seed(PrayogshalaStandard9ScienceSeeder::class);
        DB::table('lms_prayogshala_activity')->where('slug', 'potato-in-water-and-salt-solution')->update(['status' => 'published']);
        $this->seed(PrayogshalaStandard9ScienceSeeder::class);

        $this->assertSame(4, DB::table('lms_prayogshala_activity')->count());
        $this->assertSame('published', DB::table('lms_prayogshala_activity')->where('slug', 'potato-in-water-and-salt-solution')->value('status'));
    }

    public function test_learners_see_nothing_until_published_and_never_get_teacher_material(): void
    {
        $this->seed(PrayogshalaStandard9ScienceSeeder::class);
        $service = app(PrayogshalaService::class);
        $chapter = $service->visibleChapter(502, 7);

        $this->assertSame([], $service->assetsForChapter($chapter, 7, true), 'review status hides it from learners');
        $staff = $service->assetsForChapter($chapter, 7, false);
        $this->assertCount(2, $staff);
        $this->assertArrayHasKey('teacher_script', $staff[0]['prayogshala']['lab_config']);

        DB::table('lms_prayogshala_activity')->where('chapter_id', 502)->update(['status' => 'published']);
        $learner = $service->assetsForChapter($chapter, 7, true);
        $this->assertCount(2, $learner);
        $this->assertArrayNotHasKey('teacher_script', $learner[0]['prayogshala']['lab_config']);
        $this->assertNull($learner[0]['prayogshala']['teacher_instructions']);

        // Chapter isolation: chapter 501's labs never appear under chapter 502.
        $this->assertSame([502, 502], array_map(fn ($a) => $a['chapter_id'], $learner));
        // An institute not linked to the platform tenant gets none of it.
        $this->assertNull($service->visibleChapter(502, 8));
    }

    public function test_it_refuses_to_seed_when_a_chapter_or_topic_is_missing(): void
    {
        DB::table('topic_master')->where('name', 'Cell Membrane and Cell Wall')->delete();

        try {
            $this->seed(PrayogshalaStandard9ScienceSeeder::class);
            $this->fail('expected the seeder to refuse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cell Membrane and Cell Wall', $e->getMessage());
        }
        $this->assertSame(0, DB::table('lms_prayogshala_activity')->count(), 'all or nothing');
    }
}
