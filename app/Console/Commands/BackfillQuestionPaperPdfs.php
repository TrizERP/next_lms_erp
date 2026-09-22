<?php

namespace App\Console\Commands;

use App\Http\Controllers\lms\questionpaperController;
use App\Models\lms\questionpaperModel;
use Illuminate\Console\Command;

class BackfillQuestionPaperPdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lms:backfill-question-pdfs {--id= : Regenerate a single question paper by id} {--force : Regenerate even if the PDF already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the offline exam PDF (storage/QuestionPaper/{id}_{sub_institute_id}_{syear}.pdf) for question papers that are missing one. Rendered with Dompdf, so it needs no external binary.';

    public function handle(): int
    {
        $singleId = $this->option('id');
        $force = (bool) $this->option('force');

        $query = questionpaperModel::query();
        if ($singleId) {
            $query->where('id', $singleId);
        }

        $papers = $query->get(['id', 'sub_institute_id', 'syear']);

        if ($papers->isEmpty()) {
            $this->warn('No matching question papers found.');
            return self::SUCCESS;
        }

        $controller = new questionpaperController();
        $pdfFolder = public_path('storage/QuestionPaper');

        // A paper is only missing if neither root holds it. On a host without
        // the `public/storage` symlink those are two different directories, and
        // judging by `public_path()` alone would regenerate over files that are
        // already there under `storage/app/public` -- the same split that made
        // `/api/question-paper/{id}/pdf` report stored papers as missing.
        $roots = array_unique([
            $pdfFolder,
            storage_path('app/public/QuestionPaper'),
        ]);

        $stored = function (string $filename) use ($roots): ?string {
            foreach ($roots as $root) {
                $candidate = $root.DIRECTORY_SEPARATOR.$filename;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            return null;
        };

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($papers as $paper) {
            $filename = "{$paper->id}_{$paper->sub_institute_id}_{$paper->syear}.pdf";

            if (!$force && $stored($filename) !== null) {
                $skipped++;
                continue;
            }

            try {
                $controller->generatePDF([
                    'sub_institute_id' => $paper->sub_institute_id,
                    'syear' => $paper->syear,
                ], $paper->id);
            } catch (\Throwable $e) {
                $this->error("Paper {$paper->id}: {$e->getMessage()}");
                $failed++;
                continue;
            }

            if ($stored($filename) !== null) {
                $this->info("Paper {$paper->id}: generated {$filename}");
                $generated++;
            } else {
                $this->error("Paper {$paper->id}: PDF still missing after generation attempt (looked in ".implode(', ', $roots).")");
                $failed++;
            }
        }

        $this->line("Done. generated={$generated} skipped(existing)={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
