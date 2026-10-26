<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Store one finished document (a lesson plan as .pdf or .docx) as a 'Classroom Activity' row of a chapter.
 *
 *   php artisan lms:store-classroom-document 41675 path/to/file.pdf "A1. Heat Up and Cool Down" --slug=g4-water-ch1-lesson-plan-a1 --standard=4258 --subject=48385 --tenant=341 --dry-run
 *
 * The file is uploaded to public/lms_content_file/<slug>-<first 8 of sha1>.<ext> (same folder and URL pattern as the other
 * Classroom Activity PDFs), then fetched back from its public CDN URL and compared by hash. The row is inserted only after
 * that check passes, so a row never points at a file that is not there. Nothing is overwritten or deleted: a chapter that
 * already shows the same title, or the same stored file name, in this category is left alone.
 */
class StoreClassroomDocumentCommand extends Command
{
    private const CATEGORY = 'Classroom Activity';

    private const CDN = 'https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/lms_content_file/';

    private const MIME = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    protected $signature = 'lms:store-classroom-document
        {chapter : chapter_master.id}
        {file : path to the finished .pdf or .docx}
        {title : the title shown on the card}
        {--slug= : stored file name stem, letters, digits and hyphens (required)}
        {--description= : content_master.description}
        {--standard= : expected standard id of the chapter (required, cross-checked)}
        {--subject= : expected subject id of the chapter (required, cross-checked)}
        {--tenant= : expected sub_institute_id of the chapter (required, cross-checked)}
        {--sort= : content_master.sort_order}
        {--source=HES publisher material : content_master.source label}
        {--syear=2026 : academic year}
        {--dry-run : report what would happen, upload and write nothing}';

    protected $description = 'Upload a lesson plan document and store it as a content_master "Classroom Activity" row, verified by its public URL';

    public function handle(): int
    {
        $chapterId = (int) $this->argument('chapter');
        $path = (string) $this->argument('file');
        $title = trim((string) $this->argument('title'));
        $slug = trim((string) $this->option('slug'));

        if ($title === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $this->error('A title and a --slug of lowercase letters, digits and hyphens are required.');

            return self::FAILURE;
        }
        foreach (['standard', 'subject', 'tenant'] as $required) {
            if ((int) $this->option($required) <= 0) {
                $this->error("--$required is required so the chapter can be cross-checked.");

                return self::FAILURE;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$ext]) || !is_file($path) || !is_readable($path)) {
            $this->error('Not a readable .pdf or .docx file: ' . $path);

            return self::FAILURE;
        }
        $binary = (string) file_get_contents($path);
        if (!$this->looksLike($ext, $path, $binary)) {
            $this->error("That file is named .$ext but is not a real $ext document. Refusing to store it.");

            return self::FAILURE;
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
        if (!$gradeId) {
            $this->error('Grade could not be resolved for chapter ' . $chapterId . '.');

            return self::FAILURE;
        }

        $hash = sha1($binary);
        $fileName = $slug . '-' . substr($hash, 0, 8) . '.' . $ext;
        $url = self::CDN . $fileName;

        $duplicates = DB::table('content_master')
            ->where('chapter_id', $chapterId)->where('sub_institute_id', $chapter->sub_institute_id)
            ->where('content_category', self::CATEGORY)
            ->where(fn ($q) => $q->where('title', $title)->orWhere('filename', $fileName))
            ->pluck('id');

        $this->line(sprintf('%sChapter %d "%s", school %d | "%s" | %s, %s KB | %s', $this->option('dry-run') ? 'DRY RUN. ' : '', $chapterId, $chapter->chapter_name, $chapter->sub_institute_id, $title, $ext, number_format(strlen($binary) / 1024, 1), $url));

        if ($duplicates->isNotEmpty()) {
            $this->warn('Already stored as content_master ' . $duplicates->implode(', ') . '. Nothing uploaded, nothing written.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing uploaded or written.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('digitalocean');
        $key = 'public/lms_content_file/' . $fileName;
        try {
            if (!$disk->exists($key)) {
                $disk->put($key, $binary, ['visibility' => 'public', 'ContentType' => self::MIME[$ext]]);
            }
        } catch (Throwable $e) {
            $this->error('Upload failed, nothing written: ' . $e->getMessage());

            return self::FAILURE;
        }

        // The row is written only for a file that can be fetched from its public URL and is byte-for-byte the one sent.
        $response = Http::timeout(60)->get($url);
        if (!$response->successful() || sha1($response->body()) !== $hash) {
            $this->error(sprintf('Uploaded, but the public URL did not serve the same file (HTTP %d). No row written: %s', $response->status(), $url));

            return self::FAILURE;
        }
        $this->line(sprintf('  verified: HTTP %d, %d bytes, %s, sha1 matches', $response->status(), strlen($response->body()), $response->header('Content-Type')));

        $id = (int) DB::table('content_master')->insertGetId([
            'grade_id' => $gradeId,
            'standard_id' => $chapter->standard_id,
            'subject_id' => $chapter->subject_id,
            'chapter_id' => $chapterId,
            // Left empty on purpose: the chapter's Classroom Resource list only reads rows with no topic.
            'topic_id' => null,
            'title' => $title,
            'description' => $this->option('description') ?: null,
            'file_folder' => '/lms_content_file',
            'filename' => $fileName,
            'file_type' => $ext,
            'file_size' => (string) strlen($binary),
            'url' => $url,
            'sort_order' => $this->option('sort') !== null ? (int) $this->option('sort') : null,
            'show_hide' => 1,
            'content_category' => self::CATEGORY,
            'source' => (string) $this->option('source'),
            'syear' => (int) $this->option('syear'),
            'sub_institute_id' => $chapter->sub_institute_id,
            'user_profile_name' => 'Admin',
        ]);

        $saved = DB::table('content_master')->where('id', $id)->first(['id', 'chapter_id', 'title', 'url', 'file_type', 'content_category', 'show_hide', 'sub_institute_id']);
        if (!$saved || $saved->url !== $url || (int) $saved->chapter_id !== $chapterId || $saved->title !== $title) {
            $this->error("Content_master $id was written but does not read back as expected. Check it before use.");

            return self::FAILURE;
        }
        $this->info("Stored content_master #$id -> $url");

        return self::SUCCESS;
    }

    private function looksLike(string $ext, string $path, string $binary): bool
    {
        if ($ext === 'pdf') {
            return strncmp($binary, '%PDF-', 5) === 0;
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('word/document.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $ok;
    }
}
