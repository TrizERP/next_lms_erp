<?php

namespace App\Console\Commands\LMS;

use App\Models\lms\chapterModel;
use App\Services\ContentGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Store a REVIEWED study-deck bundle: exactly what `lms:generate-study-deck` wrote and a person looked at.
 *
 * It stores the bundle; it does not generate anything, so what is published is what was reviewed.
 *
 *   php artisan lms:store-study-deck 8592 --dry-run     report what would happen, write nothing
 *   php artisan lms:store-study-deck 8592               store it
 *
 * What it writes (see ContentGenerationService::publishStudyDeck):
 *   - each picture, once, into the shared object store under a name made from its hash
 *   - the presentation (.pptx) and the interactive deck (.deck.json) beside it
 *   - ONE content_master row, a Classroom Presentation, which is how the Classroom Resource list finds it
 * and nothing else: no h5p_* row, no question-bank row, no new table.
 *
 * Safe to run twice: the same deck changes nothing, a changed deck is a new row and hides the old one.
 */
class StoreStudyDeckCommand extends Command
{
    protected $signature = 'lms:store-study-deck
        {chapter : chapter_master.id}
        {--bundle= : the bundle folder (default storage/app/study-deck/chapter-<id>)}
        {--tenant= : sub_institute_id the row belongs to (default: the chapter\'s own)}
        {--user=1 : content_master.created_by}
        {--profile=ADMIN : content_master.user_profile_name}
        {--redraw-pdf : draw the PDF again (after a layout change) and replace the stored one; the row, presentation and deck file are left as they are}
        {--dry-run : check everything and report, write nothing}';

    protected $description = 'Store a reviewed study-deck bundle: pictures in the shared store, one content_master presentation, one deck file';

    public function handle(ContentGenerationService $content): int
    {
        $chapterId = (int) $this->argument('chapter');
        $dir = rtrim((string) ($this->option('bundle') ?: storage_path('app/study-deck/chapter-' . $chapterId)), '/\\');
        $dry = (bool) $this->option('dry-run');

        $chapter = chapterModel::find($chapterId);
        if (!$chapter) {
            $this->error("Chapter $chapterId does not exist.");

            return self::FAILURE;
        }

        $files = ['deck' => $dir . '/deck.json', 'html' => $dir . '/out/presentation.html', 'report' => $dir . '/report.json'];
        foreach ($files as $name => $path) {
            if (!is_file($path)) {
                $this->error("The bundle has no $name ($path). Run lms:generate-study-deck first.");

                return self::FAILURE;
            }
        }

        $deck = json_decode((string) file_get_contents($files['deck']), true);
        $report = json_decode((string) file_get_contents($files['report']), true);
        if (!is_array($deck) || ($deck['version'] ?? 0) < 3) {
            $this->error('The bundle\'s deck.json is not a version 3 study deck.');

            return self::FAILURE;
        }
        if (empty($report['ok'])) {
            $this->error('The bundle did not pass validation; it will not be stored.');

            return self::FAILURE;
        }
        if ((int) ($deck['chapter']['id'] ?? 0) !== $chapterId) {
            $this->error('The bundle is for chapter ' . ($deck['chapter']['id'] ?? '?') . ', not ' . $chapterId . '.');

            return self::FAILURE;
        }

        $gradeId = $chapter->grade_id ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');
        $tenant = (int) ($this->option('tenant') ?: $chapter->sub_institute_id);

        $this->line(($dry ? 'DRY RUN - nothing will be written. ' : '') . "Chapter $chapterId \"{$chapter->chapter_name}\", tenant $tenant, " . ($deck['slide_count'] ?? '?') . ' slides');

        $result = $content->publishStudyDeck([
            'chapter' => $chapter,
            'chapter_name' => $chapter->chapter_name,
            'grade_id' => $gradeId,
            'concept_id' => null,
            'sub_institute_id' => $tenant,
            'syear' => $chapter->syear,
            'created_by' => (int) $this->option('user'),
            'user_profile_name' => (string) $this->option('profile'),
            'refresh_pdf' => (bool) $this->option('redraw-pdf'),
        ], $deck, (string) file_get_contents($files['html']), $dir . '/out/images', $dry, 'study-deck');

        $body = $result['body'];
        if (($result['http'] ?? 0) >= 400) {
            $this->error($body['message'] ?? 'The study deck was not stored.');

            return self::FAILURE;
        }

        $data = $body['data'] ?? $body;
        $this->newLine();
        $this->line('status:           <options=bold>' . ($body['status'] ?? $data['status'] ?? '?') . '</>');
        $this->line('content_master id: ' . ($body['content_id'] ?? $data['content_id'] ?? '(none yet)'));
        $this->line('filename:          ' . ($data['filename'] ?? ''));
        $this->line('deck file:         ' . ($data['deck_metadata_path'] ?? ''));
        $this->line('pdf file:          ' . ($data['pdf_path'] ?? ''));
        $this->line('pictures:          ' . count($data['images'] ?? []) . ' (' . count($data['images_uploaded'] ?? []) . ' to upload / uploaded, ' . count($data['images_reused'] ?? []) . ' already stored)');

        return self::SUCCESS;
    }
}
