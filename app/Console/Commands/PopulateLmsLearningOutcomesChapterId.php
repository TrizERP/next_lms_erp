<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateLmsLearningOutcomesChapterId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lms:populate-lo-chapter-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate chapter_id column in lms_learning_outcomes table with actual chapter_id values';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting chapter_id population for lms_learning_outcomes...');

        $curricula = DB::table('lms_curriculum')->get();
        $totalUpdated = 0;

        foreach ($curricula as $curriculum) {
            $units = DB::table('lms_units')
                ->where('curriculum_id', $curriculum->id)
                ->orderBy('unit_number')
                ->get();

            $chapters = collect();
            foreach ($units as $unit) {
                $unitChs = DB::table('chapter_master')
                    ->where('unit_id', $unit->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
                $chapters = $chapters->concat($unitChs);
            }
            $chapters = $chapters->values();

            if ($chapters->isEmpty()) {
                $chapters = DB::table('chapter_master')
                    ->where('subject_id', $curriculum->subject_id)
                    ->where('standard_id', $curriculum->standard_id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            }

            if ($chapters->isEmpty()) {
                continue;
            }

            $outcomes = DB::table('lms_learning_outcomes')
                ->where('curriculum_id', $curriculum->id)
                ->whereNotNull('parent_id')
                ->get();

            $markdown = $curriculum->extraction_id
                ? DB::table('document_extractions')->where('id', $curriculum->extraction_id)->value('md_content')
                : null;

            $sections = $markdown
                ? preg_split('/(?=^\s*(?:#{1,6}\s+|(?:chapter|unit)\s+\d+[\s:.-])|<h[1-6]\\b)/mi', $markdown)
                : [];

            foreach ($outcomes as $lo) {
                $assignedChapterId = null;
                $loCodeNormalized = preg_replace('/\s+/', '', mb_strtolower((string) $lo->code));

                // Pass 1: Match code within extracted chapter sections of the markdown
                if (!empty($sections)) {
                    foreach ($chapters as $ch) {
                        $chNameLower = mb_strtolower(trim(html_entity_decode(strip_tags((string) $ch->chapter_name))));
                        foreach ($sections as $section) {
                            $plain = mb_strtolower(html_entity_decode(strip_tags((string) $section)));
                            $secNormalized = preg_replace('/\s+/', '', $plain);
                            if (str_contains($plain, $chNameLower) && str_contains($secNormalized, $loCodeNormalized)) {
                                $assignedChapterId = $ch->id;
                                break 2;
                            }
                        }
                    }
                }

                // Pass 2: Positional index matching from code (e.g. C 1.1 -> chapter index 1, C 2.1 -> chapter index 2)
                if (!$assignedChapterId) {
                    if (preg_match('/C\s*[-=.]?\s*(\d+)/i', (string) $lo->code, $m)) {
                        $idx = ((int) $m[1]) - 1;
                        if (isset($chapters[$idx])) {
                            $assignedChapterId = $chapters[$idx]->id;
                        } elseif ($chapters->isNotEmpty()) {
                            $assignedChapterId = $chapters->last()->id;
                        }
                    }
                }

                // Fallback: Default to first chapter of the curriculum
                if (!$assignedChapterId && $chapters->isNotEmpty()) {
                    $assignedChapterId = $chapters->first()->id;
                }

                if ($assignedChapterId) {
                    DB::table('lms_learning_outcomes')
                        ->where('id', $lo->id)
                        ->update(['chapter_id' => $assignedChapterId]);
                    $totalUpdated++;
                }
            }
        }

        $this->info("Successfully updated chapter_id for {$totalUpdated} lms_learning_outcomes rows.");

        return 0;
    }
}
