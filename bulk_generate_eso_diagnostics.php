<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\QuestionGenerationService::class);
$metadataService = app(App\Services\PAL\Content\ContentMetadataService::class);
$stages = ['prerequisite_concept_check', 'adaptive_diagnostic', 'concept_diagnostic'];
$concepts = DB::table('lms_concept')->where('chapter_id', 8592)->orderBy('id')->get(['id', 'subject_id', 'standard_id', 'chapter_id']);
$totalGenerated = 0;
$totalApproved = 0;
$failures = [];
$targetTotal = $concepts->count() * count($stages) * 10;
$progress = function () use (&$targetTotal) {
    $current = DB::table('pal_question_metadata as m')
        ->join('lms_question_master as q', 'q.id', '=', 'm.question_id')
        ->where('q.chapter_id', 8592)
        ->where('m.sub_institute_id', 1)
        ->whereIn('m.stage', ['prerequisite_concept_check', 'adaptive_diagnostic', 'concept_diagnostic'])
        ->where('m.quality_status', 'approved')
        ->count();
    $width = 40;
    $filled = min($width, (int) floor(($current / max(1, $targetTotal)) * $width));
    printf("Progress [%s%s] %d/%d (%.1f%%)\n", str_repeat('#', $filled), str_repeat('-', $width - $filled), $current, $targetTotal, ($current / max(1, $targetTotal)) * 100);
};

foreach ($concepts as $concept) {
    $nodeId = DB::table('pal_concept_nodes')->where('concept_id', $concept->id)->where('node_type', 'K')->value('id');
    if (!$nodeId) {
        $failures[] = "Concept {$concept->id}: no Knowledge node";
        continue;
    }

    foreach ($stages as $stageIndex => $stage) {
        $approved = DB::table('pal_question_metadata as m')
            ->join('lms_question_master as q', 'q.id', '=', 'm.question_id')
            ->where('q.concept_id', $concept->id)->where('q.status', 1)
            ->where('m.sub_institute_id', 1)->where('m.node_id', $nodeId)
            ->where('m.stage', $stage)->where('m.quality_status', 'approved')->count();
        $missing = max(0, 10 - $approved);
        if ($missing === 0) {
            echo "Concept {$concept->id} {$stage}: complete\n";
            continue;
        }

        echo "Concept {$concept->id} {$stage}: generating {$missing} in batches of 2\n";
        while ($missing > 0) {
            $batchCount = min(2, $missing);
            $result = $service->generate([
                'concept_id' => (int) $concept->id, 'subject_id' => (int) $concept->subject_id,
                'standard_id' => (int) $concept->standard_id, 'chapter_id' => (int) $concept->chapter_id,
                'grade_id' => 12, 'question_type_id' => 1, 'question_type' => 'mcq',
                'total_questions' => $batchCount, 'sub_institute_id' => 1, 'created_by' => 1,
                'diagnostic_stage' => $stage, 'seed' => ((int) $concept->id * 100) + ($stageIndex * 10) + $missing,
            ]);
            if (!($result['status'] ?? false)) {
                $failures[] = "Concept {$concept->id} {$stage}: " . ($result['message'] ?? 'generation failed');
                echo "FAILED: {$failures[array_key_last($failures)]}\n";
                break;
            }

            $inserted = 0;
            foreach ($result['data']['question_ids'] ?? [] as $questionId) {
            DB::transaction(function () use ($questionId, $concept, $nodeId, $stage, $metadataService, &$totalApproved) {
                $metadataId = DB::table('pal_question_metadata')->insertGetId([
                    'question_id' => $questionId, 'sub_institute_id' => 1, 'scope' => 'tenant',
                    'concept_ref_id' => $concept->id, 'chapter_ref_id' => $concept->chapter_id,
                    'node_id' => $nodeId, 'stage' => $stage,
                    'item_type' => $stage === 'concept_diagnostic' ? 'application' : 'recall',
                    'quality_status' => 'draft', 'tagged_by' => 'human',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $metadataService->transition('question', $metadataId, 'reviewed', 1, 'human', 'Reviewed ESO diagnostic question.');
                $metadataService->transition('question', $metadataId, 'pedagogy_reviewed', 1, 'human', 'Pedagogy review completed.');
                $metadataService->transition('question', $metadataId, 'approved', 1, 'human', 'Approved for ESO diagnostic serving.');
                $totalApproved++;
            });
                $inserted++;
            }
            $totalGenerated += $inserted;
            $missing -= $inserted;
            echo "Completed {$concept->id} {$stage}: inserted {$inserted}\n";
            $progress();
            if ($inserted === 0) {
                $failures[] = "Concept {$concept->id} {$stage}: provider returned no inserted questions";
                break;
            }
        }
    }
}

dump(['generated' => $totalGenerated, 'approved_metadata' => $totalApproved, 'failures' => $failures]);
