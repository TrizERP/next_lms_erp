<?php

namespace App\Services\Documents;

use App\Models\Documents\DocumentMaster;
use App\Models\Documents\DocumentHistory;
use Illuminate\Support\Facades\Request;

class DocumentAuditService
{
    /**
     * Record an audit event in document_history
     */
    public static function log(DocumentMaster $document, string $action, ?int $userId = null, ?array $details = null): DocumentHistory
    {
        $ip = Request::ip();

        return DocumentHistory::create([
            'document_id' => $document->id,
            'entry_type' => 'audit',
            'action' => $action,
            'user_id' => $userId ?: ($document->owner_id ?? null),
            'ip_address' => $ip,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a new version entry (entry_type=version, action=version_added)
     */
    public static function logVersion(
        DocumentMaster $document,
        int $versionNumber,
        string $storagePath,
        string $checksum,
        int $size,
        ?string $changeNote = null,
        ?int $userId = null,
        ?array $details = null
    ): DocumentHistory {
        $ip = Request::ip();

        return DocumentHistory::create([
            'document_id' => $document->id,
            'entry_type' => 'version',
            'action' => 'version_added',
            'user_id' => $userId ?: ($document->owner_id ?? null),
            'ip_address' => $ip,
            'version_number' => $versionNumber,
            'storage_path' => $storagePath,
            'checksum_sha256' => $checksum,
            'size' => $size,
            'change_note' => $changeNote,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
