<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * File a document that was written elsewhere into the content library.
 *
 * WHY THIS EXISTS
 * The four document content types a teacher can ask for - Revision Notes,
 * Classroom Activity, Remedial Class - are all routed to Gemini by
 * contentController::storeGammaContent, and that branch reads
 * env('GEMINI_API_KEY'), which is not set on this deployment. Presentations are
 * unaffected: they go to Gamma, which is configured and working.
 *
 * ContentGenerationService already has the other half of the answer.
 * storeAuthoredContent() runs the entire persist path - sanitise, render to
 * PDF, upload to Spaces, insert content_master - and skips only the step that
 * asks a model for the text. It had no callers. This command is that caller.
 *
 * So the document is authored outside the app and handed to this command as an
 * HTML file. Nothing here talks to a provider, and no provider key is needed.
 *
 * WHAT IT WRITES
 * Exactly one content_master row per invocation, through the same code path a
 * Claude-generated document would take, so the content library cannot tell the
 * difference and no second storage path exists to drift. `description` holds
 * the HTML, which is what makes the document readable straight out of the
 * database and renderable by the library without fetching the PDF.
 *
 * WHAT IT REFUSES
 * It will not add a second document of the same type to a chapter unless
 * --force is passed. A bulk run that is interrupted and restarted is the
 * expected case, and content_master has no unique key that would stop it
 * quietly doubling every chapter's library.
 */
class StoreAuthoredContentCommand extends Command
{
    protected $signature = 'lms:store-authored-content
        {chapter : chapter_master.id this document belongs to}
        {type : content_category to file it under, e.g. "Revision Notes"}
        {file : path to a UTF-8 HTML file holding the document body}
        {--concept= : lms_concept.id when the document is scoped to one concept}
        {--tenant=1 : sub_institute_id to file it under}
        {--user=1 : content_master.created_by}
        {--profile= : user_profile_name (truncated to 10 chars by the table)}
        {--syear=2026 : academic year}
        {--author=authored : label recorded as the authoring model}
        {--force : store even when this chapter already has this content type}
        {--dry-run : report what would be stored, write nothing}';

    protected $description = 'Store an externally authored HTML document as chapter content (no provider call)';

    /**
     * Sources that mark a row as generated rather than uploaded.
     *
     * Mirrors ApiLmsCourseController::GENERATED_CONTENT_SOURCES - the duplicate
     * check has to agree with what the read API considers generated content, or
     * a re-run would not see the rows it created last time.
     */
    private const GENERATED_SOURCES = ['Gamma AI', 'aiGenerated', 'Claude AI'];

    public function handle(ContentGenerationService $contentGeneration): int
    {
        $chapterId = (int) $this->argument('chapter');
        $contentType = trim((string) $this->argument('type'));
        $path = (string) $this->argument('file');

        if ($contentType === '') {
            $this->error('A content type is required.');

            return self::FAILURE;
        }

        if (!is_file($path) || !is_readable($path)) {
            $this->error('Document not readable: ' . $path);

            return self::FAILURE;
        }

        $html = (string) file_get_contents($path);
        if (trim($html) === '') {
            $this->error('Document is empty: ' . $path);

            return self::FAILURE;
        }

        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        if (!$chapter) {
            $this->error('Chapter not found: ' . $chapterId);

            return self::FAILURE;
        }

        // chapter_master.grade_id is nullable but content_master.grade_id is
        // not. Same fallback storeGammaContent and uploadContent both use.
        $gradeId = $chapter->grade_id
            ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');

        if (!$gradeId) {
            $this->error('Grade could not be resolved for chapter ' . $chapterId . '. Set a grade on the chapter or its standard.');

            return self::FAILURE;
        }

        $conceptId = $this->option('concept') !== null ? (int) $this->option('concept') : null;
        if ($conceptId) {
            // A concept from another chapter would claim a mapping that is not
            // true, which is the one thing resolveContentConceptId refuses to
            // do on the request path either.
            $belongs = DB::table('lms_concept')
                ->where('id', $conceptId)
                ->where('chapter_id', $chapterId)
                ->exists();

            if (!$belongs) {
                $this->error('Concept ' . $conceptId . ' does not belong to chapter ' . $chapterId . '.');

                return self::FAILURE;
            }
        }

        $existing = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('content_category', $contentType)
            ->whereIn('source', self::GENERATED_SOURCES)
            ->where(function ($q) {
                $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0);
            })
            ->count();

        if ($existing > 0 && !$this->option('force')) {
            $this->warn(sprintf(
                'Skipped: chapter %d already has %d generated "%s" item(s). Pass --force to add another.',
                $chapterId,
                $existing,
                $contentType
            ));

            // Not a failure: a resumed bulk run reaching already-finished work
            // is the expected path, and it must not stop the run.
            return self::SUCCESS;
        }

        $input = [
            'content_type' => $contentType,
            'is_presentation' => false,
            'chapter' => $chapter,
            'chapter_name' => (string) $chapter->chapter_name,
            'grade_id' => $gradeId,
            'concept_id' => $conceptId,
            'sub_institute_id' => (int) $this->option('tenant'),
            'syear' => (int) $this->option('syear'),
            'created_by' => (int) $this->option('user'),
            'user_profile_name' => (string) ($this->option('profile') ?? ''),
        ];

        $this->line(sprintf(
            'Chapter %d "%s" (standard %d, subject %d) | %s | %s chars of HTML%s',
            $chapterId,
            $chapter->chapter_name,
            $chapter->standard_id,
            $chapter->subject_id,
            $contentType,
            number_format(strlen($html)),
            $conceptId ? ' | concept ' . $conceptId : ''
        ));

        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing written.');

            return self::SUCCESS;
        }

        $result = $contentGeneration->storeAuthoredContent(
            $input,
            $html,
            (string) $this->option('author')
        );

        $body = $result['body'];

        if (empty($body['success'])) {
            $this->error('Store failed: ' . ($body['message'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $data = $body['data'] ?? [];
        $this->info(sprintf(
            'Stored content_master #%s -> %s',
            $data['id'] ?? '?',
            $data['file_url'] ?? '(no url)'
        ));

        return self::SUCCESS;
    }
}
