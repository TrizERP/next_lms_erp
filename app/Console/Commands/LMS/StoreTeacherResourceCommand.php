<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Store one finished teacher resource PDF (Learning Outcomes, KSA Framework or Assessment Rubrics) for a chapter.
 *
 * Teacher resources live in content_master with category 'Teacher Training' and the resource name as the title, the same rows
 * the chapter 8592, 8593, 25837 and 25838 resources use (file folder /lms_teacher_resource). The PDFs are built outside this
 * command; this only stores them, so what is published is what was reviewed.
 *
 *   php artisan lms:store-teacher-resource 41675 "Learning Outcomes" path/to/file.pdf --dry-run
 *   php artisan lms:store-teacher-resource 41675 "Learning Outcomes" path/to/file.pdf
 *
 * It refuses to store a second visible copy of the same chapter + resource: pass --replace to hide the earlier one
 * (hidden, not deleted, so it stays recoverable).
 */
class StoreTeacherResourceCommand extends Command
{
    private const TYPES = ['Learning Outcomes', 'KSA Framework', 'Assessment Rubrics'];

    protected $signature = 'lms:store-teacher-resource
        {chapter : chapter_master.id}
        {type : one of "Learning Outcomes", "KSA Framework", "Assessment Rubrics"}
        {file : path to the finished .pdf}
        {--tenant= : sub_institute_id the row belongs to (default: the chapter\'s own)}
        {--user=1 : content_master.created_by}
        {--syear=2026 : academic year}
        {--source=Claude AI : content_master.source label}
        {--filename= : the stored file name (default: built from the resource, chapter and time). The three PDFs link to one another by name, so build them with the same name you store them under}
        {--replace : hide the earlier visible copy of this resource on this chapter}
        {--dry-run : report what would happen, write nothing}';

    protected $description = 'Store a finished teacher resource PDF as a content_master "Teacher Training" row';

    public function handle(): int
    {
        $chapterId = (int) $this->argument('chapter');
        $type = trim((string) $this->argument('type'));
        $path = (string) $this->argument('file');

        if (!in_array($type, self::TYPES, true)) {
            $this->error('Unknown resource "' . $type . '". Use one of: ' . implode(', ', self::TYPES));

            return self::FAILURE;
        }
        if (!is_file($path) || !is_readable($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->error('Not a readable .pdf file: ' . $path);

            return self::FAILURE;
        }
        $binary = (string) file_get_contents($path);
        if (strncmp($binary, '%PDF-', 5) !== 0) {
            $this->error('That file has a .pdf name but is not a PDF. Refusing to store it.');

            return self::FAILURE;
        }

        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        if (!$chapter) {
            $this->error('Chapter not found: ' . $chapterId);

            return self::FAILURE;
        }
        $gradeId = $chapter->grade_id ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');
        if (!$gradeId) {
            $this->error('Grade could not be resolved for chapter ' . $chapterId . '.');

            return self::FAILURE;
        }
        $tenant = (int) ($this->option('tenant') ?: $chapter->sub_institute_id);
        if ($tenant !== (int) $chapter->sub_institute_id) {
            $this->error("Chapter $chapterId belongs to tenant {$chapter->sub_institute_id}, not $tenant.");

            return self::FAILURE;
        }

        $existing = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('content_category', 'Teacher Training')
            ->where('title', $type)
            ->where(function ($q) {
                $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0);
            })
            ->pluck('id');

        $this->line(sprintf('%sChapter %d "%s", tenant %d | %s | %s KB', $this->option('dry-run') ? 'DRY RUN. ' : '', $chapterId, $chapter->chapter_name, $tenant, $type, number_format(strlen($binary) / 1024, 0)));

        if ($existing->isNotEmpty() && !$this->option('replace')) {
            $this->warn('Already stored and visible as content_master ' . $existing->implode(', ') . '. Nothing written; pass --replace to hide it and store this one.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing written' . ($existing->isNotEmpty() ? '; would hide ' . $existing->implode(', ') : '') . '.');

            return self::SUCCESS;
        }

        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $type), '_'));
        $fileName = trim((string) $this->option('filename')) ?: 'lms_teacher_resource_' . $slug . '_' . $chapterId . '_' . time() . '.pdf';
        if (!preg_match('/^[A-Za-z0-9_.-]+\.pdf$/', $fileName)) {
            $this->error('The file name may only hold letters, digits, underscore, dot and hyphen, and must end in .pdf.');

            return self::FAILURE;
        }
        $spacesPath = 'public/lms_teacher_resource/' . $fileName;

        Storage::disk('digitalocean')->put($spacesPath, $binary, ['visibility' => 'public', 'ContentType' => 'application/pdf']);
        $url = Storage::disk('digitalocean')->url($spacesPath);

        DB::table('content_master')->insert([
            'grade_id' => $gradeId,
            'standard_id' => $chapter->standard_id,
            'subject_id' => $chapter->subject_id,
            'chapter_id' => $chapterId,
            'topic_id' => null,
            'concept_id' => null,
            'title' => $type,
            'description' => null,
            'file_folder' => '/lms_teacher_resource',
            'filename' => $fileName,
            'url' => $url,
            'file_type' => 'pdf',
            'file_size' => (string) strlen($binary),
            'show_hide' => '1',
            'sort_order' => null,
            'meta_tags' => null,
            'content_category' => 'Teacher Training',
            'source' => (string) $this->option('source'),
            'created_by' => (int) $this->option('user'),
            'sub_institute_id' => $tenant,
            'restrict_date' => null,
            'pre_grade_topic' => null,
            'post_grade_topic' => null,
            'cross_curriculum_grade_topic' => null,
            'basic_advance' => '1',
            'user_profile_name' => null,
            'syear' => (int) $this->option('syear'),
        ]);
        $id = DB::getPdo()->lastInsertId();

        if ($existing->isNotEmpty()) {
            $hidden = DB::table('content_master')->whereIn('id', $existing)->update(['show_hide' => '0']);
            $this->line('  hid ' . $hidden . ' earlier item(s): ' . $existing->implode(', '));
        }

        $this->info("Stored content_master #$id -> $url");

        return self::SUCCESS;
    }
}
