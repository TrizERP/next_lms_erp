<?php

namespace Tests\Feature\Eso;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * pal:harvest-concept-videos — the only thing that ranks a library or calls a
 * search API.
 *
 * The property worth protecting here is narrow and absolute: this command can
 * propose, but it can never publish. Everything it writes is a draft, so
 * running it can never change what a student sees.
 */
class HarvestConceptVideosCommandTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private int $conceptId;

    protected function setUp(): void
    {
        parent::setUp();

        // Search responses are cached for a week by design; a leaked entry
        // would make these tests depend on their own running order.
        Cache::flush();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Video Harvest School',
            'ShortCode' => 'VID' . random_int(1000, 9999),
            'ContactPerson' => 'Test',
            'Mobile' => '9999999999',
            'Email' => 'video-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'video-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Harvest Science ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '10',
            'short_name' => '10',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Metals and Non-metals',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Extraction of Metals',
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);
    }

    private function seedInstituteVideo(string $title, string $url): int
    {
        return (int) DB::table('content_master')->insertGetId([
            'chapter_id' => $this->chapterId,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'content_category' => 'Recorded Videos',
            'title' => $title,
            'filename' => $url,
            'file_type' => 'link',
            'show_hide' => 1,
            'created_at' => now(),
        ]);
    }

    private function harvest(array $options = []): int
    {
        return $this->artisan('pal:harvest-concept-videos', $options + [
            '--chapter' => $this->chapterId,
            '--tenant' => $this->subInstituteId,
        ])->run();
    }

    private function rows()
    {
        return DB::table('pal_concept_video')
            ->where('concept_id', $this->conceptId)
            ->where('sub_institute_id', $this->subInstituteId);
    }

    public function test_it_files_a_matching_institute_video_as_a_draft(): void
    {
        $this->seedInstituteVideo(
            'Extraction of metals - overview',
            'https://cdn.example.test/extraction.mp4'
        );

        $this->harvest(['--source' => 'institute']);

        $row = $this->rows()->first();

        $this->assertNotNull($row, 'A clearly matching video must be proposed.');
        $this->assertSame('draft', $row->quality_status, 'The harvester may propose but never publish.');
        $this->assertSame('institute', $row->source);
        $this->assertSame('https://cdn.example.test/extraction.mp4', $row->media_url);
        $this->assertNotNull($row->match_score);
        $this->assertNotEmpty($row->match_reason, 'A reviewer needs to know why this was proposed.');
    }

    /** The whole point of the relevance gate: no video beats a wrong video. */
    public function test_it_files_nothing_for_an_irrelevant_video(): void
    {
        $this->seedInstituteVideo('audio3.mp4', 'https://cdn.example.test/audio3.mp4');
        $this->seedInstituteVideo('Introduction to the chapter', 'https://cdn.example.test/intro.mp4');

        $this->harvest(['--source' => 'institute']);

        $this->assertSame(0, $this->rows()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->seedInstituteVideo('Extraction of metals - overview', 'https://cdn.example.test/extraction.mp4');

        $this->harvest(['--source' => 'institute', '--dry-run' => true]);

        $this->assertSame(0, $this->rows()->count());
    }

    public function test_re_running_is_idempotent(): void
    {
        $this->seedInstituteVideo('Extraction of metals - overview', 'https://cdn.example.test/extraction.mp4');

        $this->harvest(['--source' => 'institute']);
        $this->harvest(['--source' => 'institute']);

        $this->assertSame(1, $this->rows()->count(), 'Re-harvesting must update, not duplicate.');
    }

    /** A human's decision is not something a batch job may quietly undo. */
    public function test_it_never_reopens_an_already_approved_video(): void
    {
        $this->seedInstituteVideo('Extraction of metals - overview', 'https://cdn.example.test/extraction.mp4');

        $this->harvest(['--source' => 'institute']);
        $this->rows()->update(['quality_status' => 'approved']);

        $this->harvest(['--source' => 'institute']);

        $this->assertSame('approved', $this->rows()->value('quality_status'));
    }

    public function test_it_degrades_silently_when_external_search_is_not_configured(): void
    {
        Http::fake();
        config(['pal_content.video.external.enabled' => false]);

        $exitCode = $this->harvest(['--source' => 'all']);

        $this->assertSame(0, $exitCode, 'A missing API key is not an error.');
        Http::assertNothingSent();
    }

    public function test_it_files_external_results_as_drafts_when_the_institute_has_nothing(): void
    {
        config([
            'pal_content.video.external.enabled' => true,
            'pal_content.video.external.api_key' => 'test-key',
        ]);

        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [
                [
                    'id' => ['videoId' => 'viVG8POtDXo'],
                    'snippet' => [
                        'title' => 'Extraction of Metals explained | Class 10',
                        'description' => 'How metals are extracted from ores.',
                        'channelTitle' => 'Test Academy',
                        'thumbnails' => ['medium' => ['url' => 'https://i.ytimg.test/x.jpg']],
                    ],
                ],
                [
                    // Off topic — must be discarded before it reaches a
                    // reviewer, not ranked last.
                    'id' => ['videoId' => 'aaaaaaaaaaa'],
                    'snippet' => [
                        'title' => 'Top 10 exam study tips',
                        'description' => 'General advice.',
                        'channelTitle' => 'Other Channel',
                        'thumbnails' => ['medium' => ['url' => 'https://i.ytimg.test/y.jpg']],
                    ],
                ],
            ]], 200),
        ]);

        $this->harvest(['--source' => 'external']);

        $rows = $this->rows()->get();

        $this->assertCount(1, $rows, 'Only the on-topic result may be filed.');
        $this->assertSame('draft', $rows[0]->quality_status);
        $this->assertSame('youtube', $rows[0]->source);
        $this->assertSame('https://www.youtube.com/watch?v=viVG8POtDXo', $rows[0]->media_url);
        $this->assertSame('Test Academy', $rows[0]->attribution);
    }

    /** The school's own material is preferred, and quota is not spent second-guessing it. */
    public function test_a_confident_institute_match_stops_the_external_tier(): void
    {
        config([
            'pal_content.video.external.enabled' => true,
            'pal_content.video.external.api_key' => 'test-key',
        ]);
        Http::fake();

        $this->seedInstituteVideo('Extraction of metals - overview', 'https://cdn.example.test/extraction.mp4');

        $this->harvest(['--source' => 'all']);

        Http::assertNothingSent();
        $this->assertSame('institute', $this->rows()->value('source'));
    }

    /** A URL we cannot trust is not a video we can play. */
    public function test_insecure_and_malformed_urls_are_rejected(): void
    {
        $this->seedInstituteVideo('Extraction of metals - overview', 'http://cdn.example.test/insecure.mp4');
        $this->seedInstituteVideo('Extraction of metals - overview part 2', 'lms_2021_file.mp4');

        $this->harvest(['--source' => 'institute']);

        $this->assertSame(0, $this->rows()->count());
    }

    public function test_a_hidden_video_is_never_a_candidate(): void
    {
        $id = $this->seedInstituteVideo('Extraction of metals - overview', 'https://cdn.example.test/extraction.mp4');
        DB::table('content_master')->where('id', $id)->update(['show_hide' => 0]);

        $this->harvest(['--source' => 'institute']);

        $this->assertSame(0, $this->rows()->count());
    }
}
