<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Register videos that are ALREADY in DigitalOcean Spaces as 'Recorded Videos' rows of one chapter. Nothing is uploaded.
 *
 *   php artisan lms:register-spaces-videos 41675 videos.json --standard=4258 --subject=48385 --tenant=341 --dry-run
 *
 * videos.json is a list of {"title": "...", "url": "https://.../name.mp4"}; the URL is stored exactly as written. Before any
 * write, each URL must answer a 1 KB byte-range request with a video/* type (the video itself is never downloaded). If any
 * URL fails, nothing is written. A URL already stored for the chapter is skipped, and all new rows go in ONE transaction.
 */
class RegisterSpacesVideosCommand extends Command
{
    private const CATEGORY = 'Recorded Videos';

    protected $signature = 'lms:register-spaces-videos
        {chapter : chapter_master.id}
        {manifest : path to the JSON list of {title, url}}
        {--standard= : expected standard id of the chapter (required)}
        {--subject= : expected subject id of the chapter (required)}
        {--tenant= : expected sub_institute_id of the chapter (required)}
        {--syear=2026 : academic year}
        {--dry-run : verify and report, write nothing}';

    protected $description = 'Register already-uploaded Spaces MP4s as content_master "Recorded Videos" rows, URLs verified first';

    public function handle(): int
    {
        $chapterId = (int) $this->argument('chapter');
        $items = is_file((string) $this->argument('manifest')) ? json_decode((string) file_get_contents((string) $this->argument('manifest')), true) : null;
        if (!is_array($items) || $items === []) {
            $this->error('The manifest is not a readable, non-empty JSON list.');

            return self::FAILURE;
        }
        foreach (['standard', 'subject', 'tenant'] as $required) {
            if ((int) $this->option($required) <= 0) {
                $this->error("--$required is required so the chapter can be cross-checked.");

                return self::FAILURE;
            }
        }
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        if (!$chapter
            || (int) $chapter->standard_id !== (int) $this->option('standard')
            || (int) $chapter->subject_id !== (int) $this->option('subject')
            || (int) $chapter->sub_institute_id !== (int) $this->option('tenant')) {
            $this->error("Chapter $chapterId is not a chapter of standard {$this->option('standard')}, subject {$this->option('subject')}, school {$this->option('tenant')}.");

            return self::FAILURE;
        }
        $gradeId = $chapter->grade_id ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');
        $this->line(sprintf('%sChapter %d "%s", school %d, %d video(s) in the manifest', $this->option('dry-run') ? 'DRY RUN. ' : '', $chapterId, $chapter->chapter_name, $chapter->sub_institute_id, count($items)));

        $plans = [];
        $failed = false;
        foreach ($items as $i => $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $url = (string) ($item['url'] ?? '');
            $path = (string) parse_url($url, PHP_URL_PATH);
            if ($title === '' || !preg_match('#^https://[^/]+/.+\.mp4$#i', $url) || in_array($url, array_column($plans, 'url'), true)) {
                $this->error("  #" . ($i + 1) . ": needs a title and a unique https .mp4 URL.");
                $failed = true;
                continue;
            }
            $stored = DB::table('content_master')->where('chapter_id', $chapterId)->where('sub_institute_id', $chapter->sub_institute_id)->where('url', $url)->value('id');
            if ($stored) {
                $this->warn("  #" . ($i + 1) . " \"$title\": already stored as content_master $stored, skipped.");
                continue;
            }
            $probe = $this->probe($url);
            if ($probe['error']) {
                $this->error("  #" . ($i + 1) . " \"$title\": NOT usable - {$probe['error']}");
                $failed = true;
                continue;
            }
            $this->line("  #" . ($i + 1) . " \"$title\": ok, {$probe['type']}, " . number_format($probe['size'] / 1048576, 1) . ' MB, ranges ' . ($probe['ranges'] ? 'yes' : 'no'));
            $plans[] = ['title' => $title, 'url' => $url, 'filename' => rawurldecode(basename($path)), 'size' => $probe['size']];
        }
        if ($failed) {
            $this->error('At least one video could not be verified. Nothing written.');

            return self::FAILURE;
        }
        if ($this->option('dry-run') || $plans === []) {
            $this->info($plans === [] ? 'Nothing new to write.' : 'Dry run - nothing written.');

            return self::SUCCESS;
        }

        try {
            $ids = DB::transaction(function () use ($plans, $chapter, $gradeId, $chapterId) {
                $sort = (int) DB::table('content_master')->where('chapter_id', $chapterId)->where('content_category', self::CATEGORY)->max('sort_order');
                $ids = [];
                foreach ($plans as $plan) {
                    $ids[$plan['url']] = (int) DB::table('content_master')->insertGetId([
                        'grade_id' => $gradeId,
                        'standard_id' => $chapter->standard_id,
                        'subject_id' => $chapter->subject_id,
                        'chapter_id' => $chapterId,
                        // Empty on purpose: the chapter's Classroom Resource list only reads rows with no topic.
                        'topic_id' => null,
                        'title' => $plan['title'],
                        'file_folder' => '/lms_content_file',
                        'filename' => $plan['filename'],
                        'file_type' => 'mp4',
                        'file_size' => (string) $plan['size'],
                        'url' => $plan['url'],
                        'sort_order' => ++$sort,
                        'show_hide' => 1,
                        'content_category' => self::CATEGORY,
                        'syear' => (int) $this->option('syear'),
                        'sub_institute_id' => $chapter->sub_institute_id,
                        'user_profile_name' => 'Admin',
                    ]);
                }

                return $ids;
            });
        } catch (Throwable $e) {
            $this->error('Nothing stored: ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($plans as $plan) {
            $row = DB::table('content_master')->where('id', $ids[$plan['url']])->first(['id', 'chapter_id', 'title', 'url']);
            if (!$row || $row->url !== $plan['url'] || $row->title !== $plan['title'] || (int) $row->chapter_id !== $chapterId) {
                $this->error("content_master {$ids[$plan['url']]} does not read back as written. Check it.");

                return self::FAILURE;
            }
            $this->info("Stored content_master #{$row->id}: {$row->title}");
        }

        return self::SUCCESS;
    }

    /** @return array{error:?string,type:?string,size:int,ranges:bool} */
    private function probe(string $url): array
    {
        $fail = fn (string $why) => ['error' => $why, 'type' => null, 'size' => 0, 'ranges' => false];
        try {
            $range = Http::timeout(30)->withHeaders(['Range' => 'bytes=0-1023'])->get($url);
            $head = Http::timeout(30)->head($url);
        } catch (Throwable $e) {
            return $fail('request failed: ' . substr($e->getMessage(), 0, 120));
        }
        $type = (string) $range->header('Content-Type');
        if (!in_array($range->status(), [200, 206], true)) {
            return $fail('HTTP ' . $range->status());
        }
        if (stripos($type, 'video/') !== 0) {
            return $fail("served as '$type', not video/*");
        }

        return ['error' => null, 'type' => $type, 'size' => (int) $head->header('Content-Length'), 'ranges' => $range->status() === 206];
    }
}
