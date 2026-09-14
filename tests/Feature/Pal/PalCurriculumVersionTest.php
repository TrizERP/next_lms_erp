<?php

namespace Tests\Feature\Pal;

use App\Models\PAL\ContentMetadata;
use App\Models\PAL\CurriculumVersion;
use App\Services\PAL\Content\CurriculumVersionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Curriculum versioning.
 *
 * The property everything else rests on: superseding a version must never
 * touch content tagged against it. That is what keeps a past cohort's evidence
 * attached to the material they were actually taught from, and it is the
 * destructive migration this feature exists to make unnecessary.
 */
class PalCurriculumVersionTest extends TestCase
{
    use DatabaseTransactions;

    private CurriculumVersionService $versions;

    private int $subInstituteId;

    private int $standardId;

    private int $subjectId;

    private int $contentCounter = 6600000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versions = app(CurriculumVersionService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Versioning School',
            'ShortCode' => 'CV' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'cv@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'cv@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Mathematics ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '8',
            'short_name' => '8',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);
    }

    private function resolve(string $year): CurriculumVersion
    {
        return $this->versions->resolve(
            $this->subInstituteId,
            'CBSE',
            $this->standardId,
            $this->subjectId,
            $year
        );
    }

    private function contentTaggedTo(CurriculumVersion $version): ContentMetadata
    {
        return ContentMetadata::create([
            'content_master_id' => $this->contentCounter++,
            'sub_institute_id' => $this->subInstituteId,
            'curriculum_version_id' => $version->id,
            'content_type' => 'concept',
            'quality_status' => 'approved',
        ]);
    }

    public function test_superseding_a_version_leaves_its_content_where_it_was(): void
    {
        $y1 = $this->resolve('2025-26');
        $this->versions->activate($y1);

        $lastYearsContent = $this->contentTaggedTo($y1);

        $y2 = $this->versions->supersede($y1, '2026-27');

        $this->assertSame(
            (int) $y1->id,
            (int) $lastYearsContent->fresh()->curriculum_version_id,
            'Repointing content at the new version would silently rewrite what last year’s cohort was taught, and detach their evidence from the material behind it. This is the destructive migration the feature exists to avoid.'
        );
        $this->assertNotSame((int) $y2->id, (int) $lastYearsContent->fresh()->curriculum_version_id);
    }

    public function test_a_revision_creates_a_successor_rather_than_editing_the_old_year(): void
    {
        $y1 = $this->versions->activate($this->resolve('2025-26'));
        $y2 = $this->versions->supersede($y1, '2026-27');

        $y1->refresh();

        $this->assertSame(CurriculumVersion::STATUS_SUPERSEDED, $y1->status);
        $this->assertSame((int) $y2->id, (int) $y1->superseded_by_id);
        $this->assertSame(CurriculumVersion::STATUS_ACTIVE, $y2->status);

        // Both still exist. The point is keeping them, not replacing one.
        $this->assertSame('2025-26', $y1->academic_year);
        $this->assertSame('2026-27', $y2->academic_year);
    }

    public function test_only_one_version_is_in_force_for_a_scope_at_a_time(): void
    {
        $y1 = $this->versions->activate($this->resolve('2025-26'));
        $this->versions->supersede($y1, '2026-27');

        $active = CurriculumVersion::query()
            ->forTenant($this->subInstituteId)
            ->forScope('CBSE', $this->standardId, $this->subjectId)
            ->active()
            ->get();

        $this->assertCount(1, $active, '"Which syllabus is in force" has exactly one answer at a time.');
        $this->assertSame('2026-27', $active->first()->academic_year);
    }

    public function test_a_new_version_starts_as_a_draft_not_in_force(): void
    {
        $version = $this->resolve('2026-27');

        $this->assertSame(CurriculumVersion::STATUS_DRAFT, $version->status);
        $this->assertFalse($version->isServable());
        $this->assertNull($this->versions->activeFor($this->subInstituteId, 'CBSE', $this->standardId, $this->subjectId));
    }

    public function test_resolving_the_same_year_twice_returns_the_same_version(): void
    {
        $first = $this->resolve('2026-27');
        $second = $this->resolve('2026-27');

        $this->assertSame(
            (int) $first->id,
            (int) $second->id,
            'A second version of the same year is a contradiction, not a variant — the unique key makes two callers converge rather than fork.'
        );
    }

    public function test_a_superseded_version_cannot_be_brought_back(): void
    {
        $y1 = $this->versions->activate($this->resolve('2025-26'));
        $this->versions->supersede($y1, '2026-27');

        $this->expectException(InvalidArgumentException::class);

        $this->versions->activate($y1->refresh());
    }

    public function test_a_version_cannot_supersede_itself(): void
    {
        $version = $this->versions->activate($this->resolve('2026-27'));

        $this->expectException(InvalidArgumentException::class);

        // An in-year revision is an edit to that version, not a new one.
        $this->versions->supersede($version, '2026-27');
    }

    public function test_a_malformed_academic_year_is_rejected(): void
    {
        foreach (['2026', '2026-2027', 'next year', '26-27'] as $bad) {
            try {
                $this->resolve($bad);
                $this->fail("Accepted a malformed academic year: {$bad}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('YYYY-YY', $e->getMessage());
            }
        }
    }

    public function test_the_chain_from_an_old_version_leads_to_the_one_in_force(): void
    {
        $y1 = $this->versions->activate($this->resolve('2024-25'));
        $y2 = $this->versions->supersede($y1, '2025-26');
        $y3 = $this->versions->supersede($y2, '2026-27');

        $chain = $this->versions->successorChain($y1->refresh());

        $this->assertSame(
            [(int) $y2->id, (int) $y3->id],
            array_map(fn ($v) => (int) $v->id, $chain),
            'Answering "what replaced the syllabus this evidence was recorded against?" must not require mutating the historical row.'
        );
    }

    public function test_a_standard_wide_version_is_still_one_per_year(): void
    {
        // subject_id null = "the whole standard". This is the case the scope
        // index used NOT to constrain: MySQL treats NULLs as distinct, so two
        // callers each created a row and "which syllabus is in force" had two
        // answers. Stored as the sentinel 0 now.
        $first = $this->versions->resolve($this->subInstituteId, 'CBSE', $this->standardId, null, '2026-27');
        $second = $this->versions->resolve($this->subInstituteId, 'CBSE', $this->standardId, null, '2026-27');

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(0, (int) $first->subject_id);
        $this->assertTrue($first->isStandardWide());

        // And a standard-wide version is a different scope from a subject one.
        $subjectScoped = $this->versions->resolve(
            $this->subInstituteId,
            'CBSE',
            $this->standardId,
            $this->subjectId,
            '2026-27'
        );

        $this->assertNotSame((int) $first->id, (int) $subjectScoped->id);
    }

    public function test_versions_for_different_subjects_do_not_collide(): void
    {
        $otherSubjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Science ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $maths = $this->versions->activate($this->resolve('2026-27'));
        $science = $this->versions->activate($this->versions->resolve(
            $this->subInstituteId,
            'CBSE',
            $this->standardId,
            $otherSubjectId,
            '2026-27'
        ));

        $this->assertNotSame((int) $maths->id, (int) $science->id);
        $this->assertSame(CurriculumVersion::STATUS_ACTIVE, $maths->refresh()->status);
        $this->assertSame(
            CurriculumVersion::STATUS_ACTIVE,
            $science->refresh()->status,
            'Activating one subject’s syllabus must not supersede another subject’s.'
        );
    }

    protected function tearDown(): void
    {
        DB::table('pal_content_metadata')->where('sub_institute_id', $this->subInstituteId)->delete();
        DB::table('pal_curriculum_versions')->where('sub_institute_id', $this->subInstituteId)->delete();

        parent::tearDown();
    }
}
