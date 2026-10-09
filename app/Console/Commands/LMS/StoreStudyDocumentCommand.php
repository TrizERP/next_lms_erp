<?php

namespace App\Console\Commands\LMS;

use App\Models\lms\chapterModel;
use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Documents\DocumentKind;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Store a REVIEWED study-document bundle: exactly what `lms:generate-study-document` wrote and a person looked at.
 *
 * It stores the bundle; it does not generate anything, so what is published is what was reviewed.
 *
 *   php artisan lms:store-study-document 8592 revision_notes --dry-run     report what would happen, write nothing
 *   php artisan lms:store-study-document 8592 revision_notes               store it
 *
 * What it writes (see ContentGenerationService::publishStudyDocument):
 *   - nothing for the diagrams themselves: the run that wrote the bundle already put them in the study_deck_images
 *     table, and publishing only checks they are there and records that this document uses them
 *   - the document's PDF, which is the content row's own file, and its structured source (<name>.pdf.doc.json) beside it
 *   - ONE content_master row, filed under the category the content library already uses for it (Revision Notes, Remedial
 *     Class or Classroom Activity), which is how the Classroom Resource list and the student tabs find it
 * and nothing else: no h5p_* row, no question-bank row, no new table. A document of the same kind and scope that this
 * one replaces is hidden in the same transaction (not deleted: storing it again, or setting show_hide back, brings it
 * back).
 *
 * Safe to run twice: the same document changes nothing, a changed document is a new row and hides the old one.
 */
class StoreStudyDocumentCommand extends Command
{
    protected $signature = 'lms:store-study-document
        {chapter : chapter_master.id}
        {kind : revision_notes | remedial | activities}
        {--bundle= : the bundle folder (default storage/app/study-deck/chapter-<id>/<kind>)}
        {--tenant= : sub_institute_id the row belongs to (default: the chapter\'s own)}
        {--user=1 : content_master.created_by}
        {--profile=ADMIN : content_master.user_profile_name}
        {--redraw-pdf : draw the PDF again (after a layout change) and replace the stored one; the row and the source file are left as they are}
        {--dry-run : check everything and report, write nothing}';

    protected $description = 'Store a reviewed study-document bundle: one content_master row, its PDF and its structured source';

    public function handle(ContentGenerationService $content): int
    {
        $kind = DocumentKind::fromValue((string) $this->argument('kind'));
        if ($kind === null) {
            $this->error('Unknown kind "' . $this->argument('kind') . '". Use revision_notes, remedial or activities.');

            return self::FAILURE;
        }

        $chapterId = (int) $this->argument('chapter');
        $dir = rtrim((string) ($this->option('bundle') ?: storage_path('app/study-deck/chapter-' . $chapterId . '/' . $kind->value)), '/\\');
        $dry = (bool) $this->option('dry-run');

        $chapter = chapterModel::find($chapterId);
        if (!$chapter) {
            $this->error("Chapter $chapterId does not exist.");

            return self::FAILURE;
        }

        $files = ['document' => $dir . '/document.json', 'html' => $dir . '/out/' . $kind->value . '.html', 'report' => $dir . '/report.json'];
        foreach ($files as $name => $path) {
            if (!is_file($path)) {
                $this->error("The bundle has no $name ($path). Run lms:generate-study-document first.");

                return self::FAILURE;
            }
        }

        $document = json_decode((string) file_get_contents($files['document']), true);
        $report = json_decode((string) file_get_contents($files['report']), true);
        if (!is_array($document) || (int) ($document['version'] ?? 0) !== DocumentKind::VERSION) {
            $this->error('The bundle\'s document.json is not a version ' . DocumentKind::VERSION . ' study document.');

            return self::FAILURE;
        }
        if (($document['kind'] ?? null) !== $kind->value) {
            $this->error('The bundle holds a "' . ($document['kind'] ?? '?') . '" document, not "' . $kind->value . '".');

            return self::FAILURE;
        }
        if (empty($report['ok'])) {
            $this->error('The bundle did not pass validation; it will not be stored.');

            return self::FAILURE;
        }
        if ((int) ($document['chapter']['id'] ?? 0) !== $chapterId) {
            $this->error('The bundle is for chapter ' . ($document['chapter']['id'] ?? '?') . ', not ' . $chapterId . '.');

            return self::FAILURE;
        }

        $gradeId = $chapter->grade_id ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');
        $tenant = (int) ($this->option('tenant') ?: $chapter->sub_institute_id);

        $this->line(($dry ? 'DRY RUN - nothing will be written. ' : '') . "Chapter $chapterId \"{$chapter->chapter_name}\", tenant $tenant, " . $kind->label() . ', ' . count($document['sections'] ?? []) . ' parts');

        $result = $content->publishStudyDocument($kind, [
            'chapter' => $chapter,
            'chapter_name' => $chapter->chapter_name,
            'grade_id' => $gradeId,
            'concept_id' => null,
            'sub_institute_id' => $tenant,
            'syear' => $chapter->syear,
            'created_by' => (int) $this->option('user'),
            'user_profile_name' => (string) $this->option('profile'),
            'refresh_pdf' => (bool) $this->option('redraw-pdf'),
        ], $document, (string) file_get_contents($files['html']), $dry, 'study-document');

        $body = $result['body'];
        if (($result['http'] ?? 0) >= 400) {
            $this->error($body['message'] ?? 'The ' . strtolower($kind->label()) . ' was not stored.');

            return self::FAILURE;
        }

        $data = $body['data'] ?? $body;
        $this->newLine();
        $this->line('status:            <options=bold>' . ($body['status'] ?? $data['status'] ?? '?') . '</>');
        $this->line('content_master id: ' . ($body['content_id'] ?? $data['content_id'] ?? '(none yet)'));
        $this->line('category:          ' . $kind->category());
        $this->line('filename:          ' . ($data['filename'] ?? ''));
        $this->line('source file:       ' . ($data['document_metadata_path'] ?? ''));
        $this->line('pdf file:          ' . ($data['pdf_path'] ?? ''));
        $this->line('pictures:          ' . count($data['images'] ?? []) . ' (' . count($data['images_uploaded'] ?? []) . ' newly stored in the database, ' . count($data['images_reused'] ?? []) . ' already stored)');

        return self::SUCCESS;
    }
}
