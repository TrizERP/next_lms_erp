<?php

namespace App\Services\Documents\Duplicate;

use App\Models\Documents\DocumentMaster;

class DocumentDuplicateDetector
{
    /**
     * Check for exact duplicates (checksum) or near-duplicate candidates
     */
    public function detectDuplicates(DocumentMaster $document, $user, int $subInstituteId): array
    {
        $warnings = [];

        // 1. Exact duplicate check by SHA-256 Checksum
        $exact = DocumentMaster::query()
            ->visibleTo($user, $subInstituteId)
            ->where('id', '!=', $document->id)
            ->where('checksum_sha256', $document->checksum_sha256)
            ->first();

        if ($exact) {
            $warnings[] = [
                'type' => 'duplicate_of',
                'document_id' => $exact->id,
                'title' => $exact->title,
                'message' => 'An identical file is already stored in the system.',
            ];
        }

        // 2. Version candidate: Same document_type, same department, same academic_year, similar subject
        if ($document->document_type && $document->department_id) {
            $candidate = DocumentMaster::query()
                ->visibleTo($user, $subInstituteId)
                ->where('id', '!=', $document->id)
                ->where('document_type', $document->document_type)
                ->where('department_id', $document->department_id)
                ->where(function ($q) use ($document) {
                    if ($document->academic_year) {
                        $q->where('academic_year', $document->academic_year);
                    }
                })
                ->where(function ($q) use ($document) {
                    if ($document->subject) {
                        $q->where('subject', 'LIKE', '%' . $document->subject . '%');
                    }
                })
                ->first();

            if ($candidate) {
                $warnings[] = [
                    'type' => 'version_of_candidate',
                    'document_id' => $candidate->id,
                    'title' => $candidate->title,
                    'current_version' => $candidate->current_version,
                    'message' => "This file resembles '{$candidate->title}'. You can save it as version " . ($candidate->current_version + 1) . '.',
                ];
            }
        }

        return $warnings;
    }
}
