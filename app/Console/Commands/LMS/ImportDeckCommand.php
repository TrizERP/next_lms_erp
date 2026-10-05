<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Import a deck that was designed outside this repo.
 *
 * WHY THIS EXISTS
 * Our own PPTX renderer positions every shape by arithmetic, and PHP cannot
 * measure text - PHPPresentation exposes no font metrics. On a real chapter
 * that produced 33 overlapping pairs and 22 shapes running off the bottom of
 * the slide. A tool that can actually lay text out does better.
 *
 * So the workflow splits: `lms:export-chapter-bundle` writes the inputs and a
 * paste-ready PROMPT.md, a designer (or a design tool) builds the deck, and
 * this command puts the finished file back into the content library so it
 * appears for teachers exactly like a generated one.
 *
 * WHAT IT DOES NOT DO
 * It stores an artifact, not structured content. An imported deck carries no
 * design-system blocks, so `lms:validate-content` cannot check its coverage or
 * pedagogy, and a token change will not re-render it. That is the deliberate
 * trade: better-looking decks, at the cost of the re-render guarantee. Keep the
 * HTML source authoritative for everything else.
 */
class ImportDeckCommand extends Command
{
    protected $signature = 'lms:import-deck
        {chapter : chapter_master.id this deck belongs to}
        {type : content_category, e.g. "Presentation" or "Teacher training presentation"}
        {file : path to the .pptx (or .pdf/.html) that was designed elsewhere}
        {--tenant=1 : sub_institute_id}
        {--user=1 : content_master.created_by}
        {--syear=2026 : academic year}
        {--source=Designed : content_master.source label}
        {--replace : hide any existing generated item of this type on this chapter}
        {--dry-run : report what would happen, write nothing}';

    protected $description = 'Import an externally designed deck (.pptx) into the content library';

    /** Mirrors ApiLmsCourseController::GENERATED_CONTENT_SOURCES plus our own. */
    private const GENERATED_SOURCES = ['Gamma AI', 'aiGenerated', 'Claude AI', 'Designed'];

    /** Extension => [file_type stored, servable media type]. */
    private const ACCEPTED = [
        'pptx' => ['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'pdf' => ['pdf', 'application/pdf'],
        'html' => ['link', 'text/html'],
    ];

    public function handle(): int
    {
        $chapterId = (int) $this->argument('chapter');
        $contentType = trim((string) $this->argument('type'));
        $path = (string) $this->argument('file');

        if (!is_file($path) || !is_readable($path)) {
            $this->error('File not readable: ' . $path);

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::ACCEPTED[$extension])) {
            $this->error('Unsupported file type ".' . $extension . '". Accepted: ' . implode(', ', array_keys(self::ACCEPTED)));

            return self::FAILURE;
        }

        [$fileType, $mime] = self::ACCEPTED[$extension];

        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        $standardName = $chapter
            ? DB::table('standard')->where('id', $chapter->standard_id)->value('name')
            : null;
        if (!$chapter) {
            $this->error('Chapter not found: ' . $chapterId);

            return self::FAILURE;
        }

        // chapter_master.grade_id is nullable but content_master.grade_id is
        // not. Same fallback storeGammaContent and uploadContent both use.
        $gradeId = $chapter->grade_id
            ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');

        if (!$gradeId) {
            $this->error('Grade could not be resolved for chapter ' . $chapterId . '.');

            return self::FAILURE;
        }

        if ($extension === 'pptx' && !$this->looksLikePptx($path)) {
            $this->error('That file has a .pptx name but is not a PowerPoint package. Refusing to store it.');

            return self::FAILURE;
        }

        $existing = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('content_category', $contentType)
            ->whereIn('source', self::GENERATED_SOURCES)
            ->where(function ($q) {
                $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0);
            })
            ->pluck('id');

        $binary = (string) file_get_contents($path);

        $this->line(sprintf(
            'Chapter %d "%s" (Class %s) | %s | %s | %s',
            $chapterId,
            $chapter->chapter_name,
            $standardName ?: $chapter->standard_id,
            $contentType,
            strtoupper($extension),
            $this->humanSize(strlen($binary))
        ));

        if ($existing->isNotEmpty()) {
            $this->warn(sprintf(
                '  %d existing "%s" item(s): %s. %s',
                $existing->count(),
                $contentType,
                $existing->implode(', '),
                $this->option('replace')
                    ? 'They will be hidden.'
                    : 'They will REMAIN visible - pass --replace to retire them, or teachers see duplicates.'
            ));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing written.');

            return self::SUCCESS;
        }

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $contentType . '_' . $chapter->chapter_name));
        $fileName = trim($slug, '_') . '_' . time() . '.' . $extension;
        $spacesPath = 'public/lms_content_file/' . $fileName;

        Storage::disk('digitalocean')->put($spacesPath, $binary, [
            'visibility' => 'public',
            'ContentType' => $mime,
        ]);
        $url = Storage::disk('digitalocean')->url($spacesPath);

        DB::table('content_master')->insert([
            'grade_id' => $gradeId,
            'standard_id' => $chapter->standard_id,
            'subject_id' => $chapter->subject_id,
            'chapter_id' => $chapterId,
            'topic_id' => null,
            'concept_id' => null,
            // varchar(250) on the live table; a long chapter name would
            // otherwise fail the insert under strict mode.
            'title' => mb_substr($chapter->chapter_name . ' ' . $contentType, 0, 250),
            // No HTML to store: this artifact was designed elsewhere, so there
            // is no design-system markup to keep. Naming that here beats an
            // empty column nobody can explain later.
            'description' => 'Designed externally and imported. Source file: ' . basename($path),
            'file_folder' => '/lms_content_file',
            'filename' => $fileName,
            'url' => $url,
            'file_type' => $fileType,
            'file_size' => strlen($binary) ?: null,
            'show_hide' => '1',
            'sort_order' => null,
            'meta_tags' => null,
            'content_category' => $contentType,
            'source' => (string) $this->option('source'),
            'created_by' => (int) $this->option('user'),
            'sub_institute_id' => (int) $this->option('tenant'),
            'restrict_date' => null,
            'pre_grade_topic' => null,
            'post_grade_topic' => null,
            'cross_curriculum_grade_topic' => null,
            'basic_advance' => '1',
            'user_profile_name' => null,
            'syear' => (int) $this->option('syear'),
        ]);

        $lastId = DB::getPDO()->lastInsertId();

        if ($this->option('replace') && $existing->isNotEmpty()) {
            // Hidden, not deleted: a superseded deck stays recoverable, and
            // this matches how the content library already filters.
            $hidden = DB::table('content_master')->whereIn('id', $existing)->update(['show_hide' => '0']);
            $this->line('  retired ' . $hidden . ' superseded item(s)');
        }

        $this->info(sprintf('Imported content_master #%s -> %s', $lastId, $url));

        return self::SUCCESS;
    }

    /**
     * A .pptx is a ZIP carrying ppt/presentation.xml.
     *
     * Checked because the whole point of this command is that the file came
     * from outside: a renamed PDF, a half-finished download or an HTML export
     * would otherwise be stored as a PowerPoint and fail silently for teachers.
     */
    private function looksLikePptx(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->warn('  ZipArchive unavailable - skipping the .pptx integrity check.');

            return true;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $ok = $zip->locateName('ppt/presentation.xml') !== false;
        $slides = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'ppt/slides/slide')) {
                $slides++;
            }
        }
        $zip->close();

        if ($ok) {
            $this->line('  valid PowerPoint package, ' . $slides . ' slide(s)');
        }

        return $ok;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}
