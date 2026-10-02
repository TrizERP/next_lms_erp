<?php

namespace App\Jobs;

use App\Models\Documents\DocumentMaster;
use App\Services\Documents\DocumentStorageService;
use App\Services\Documents\Extraction\TextExtractionManager;
use App\Services\Documents\Understanding\DocumentClassificationService;
use App\Services\Documents\Duplicate\DocumentDuplicateDetector;
use App\Services\Documents\DocumentAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDocumentPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $documentId;
    public int $userId;
    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(int $documentId, int $userId)
    {
        $this->documentId = $documentId;
        $this->userId = $userId;
    }

    /**
     * Execute the job: Extraction -> Understanding/AI -> Metadata & Tags -> Duplicates -> Ready for Review
     */
    public function handle(
        DocumentStorageService $storage,
        TextExtractionManager $extractionManager,
        DocumentClassificationService $classifierService,
        DocumentDuplicateDetector $duplicateDetector
    ): void {
        $document = DocumentMaster::find($this->documentId);
        if (!$document) {
            return;
        }

        // Set status to processing
        $document->update(['processing_status' => 'processing']);

        $tempPath = null;
        try {
            // 1. Download file stream to a temporary local file for parsing
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'idms_' . uniqid() . '.' . pathinfo($document->original_file_name, PATHINFO_EXTENSION);
            $content = $storage->get($document->storage_path);
            file_put_contents($tempPath, $content);

            // 2. Extract Text
            $extractionResult = $extractionManager->extractText($tempPath, $document->mime_type, $document->original_file_name);
            $document->extracted_text = $extractionResult['text'];

            // 3. AI / Rule-based Classification
            $classification = $classifierService->classify(
                $extractionResult['text'],
                $document->original_file_name,
                $document->sub_institute_id
            );

            $meta = $classification['metadata'];
            $document->document_type = $meta['document_type'] ?? 'General Correspondence';
            $document->category = $meta['category'] ?? 'Administrative';
            $document->department_id = $classification['department_id'] ?? $document->department_id;
            $document->subject = $meta['subject'] ?? pathinfo($document->original_file_name, PATHINFO_FILENAME);
            $document->document_date = $meta['document_date'] ?? null;
            $document->academic_year = $meta['academic_year'] ?? null;
            $document->people = $meta['people'] ?? [];
            $document->organization = $meta['organization'] ?? '';
            $document->project = $meta['project'] ?? null;
            $document->lifecycle_status = $meta['lifecycle_status'] ?? 'active';
            $document->summary = $meta['summary'] ?? '';
            $document->confidence = $meta['confidence'] ?? 0.50;
            $document->keywords = $meta['keywords'] ?? [];

            // 4. Tags
            $suggestedTags = [];
            foreach (($meta['suggested_tags'] ?? []) as $tag) {
                $suggestedTags[] = [
                    'name' => trim($tag),
                    'source' => 'ai',
                    'status' => 'suggested',
                ];
            }
            $document->syncTags($suggestedTags);

            // 5. Recompute View Principals
            $document->recomputeViewPrincipals();

            // 6. Duplicate Detection
            $user = (object)['id' => $this->userId, 'is_admin' => 0];
            $dupWarnings = $duplicateDetector->detectDuplicates($document, $user, $document->sub_institute_id);

            $allWarnings = array_merge($classification['warnings'] ?? [], $dupWarnings);
            if ($extractionResult['used_ocr']) {
                $allWarnings[] = ['type' => 'ocr_applied', 'message' => 'Scanned text extracted via OCR'];
            }
            $document->warnings = $allWarnings;

            // Pipeline complete -> Set ready_for_review
            $document->processing_status = 'ready_for_review';
            $document->save();

            DocumentAuditService::log($document, 'pipeline_completed', $this->userId, [
                'status' => 'ready_for_review',
                'confidence' => $document->confidence,
                'type' => $document->document_type,
            ]);

        } catch (Throwable $e) {
            Log::error("IDMS Pipeline Error for Document #{$this->documentId}: " . $e->getMessage());
            $document->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Handle job failure without losing file
     */
    public function failed(Throwable $exception): void
    {
        Log::error("IDMS Pipeline Job permanently failed for Document #{$this->documentId}: " . $exception->getMessage());
        $document = DocumentMaster::find($this->documentId);
        if ($document) {
            $document->update([
                'processing_status' => 'failed',
                'processing_error' => $exception->getMessage(),
            ]);
        }
    }
}
