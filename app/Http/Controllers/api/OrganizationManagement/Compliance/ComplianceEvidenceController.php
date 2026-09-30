<?php

namespace App\Http\Controllers\api\OrganizationManagement\Compliance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrganizationManagement\ComplianceEvidence;
use App\Models\OrganizationManagement\ComplianceLibraryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Compliance evidence upload/verify/reject/delete - Compliance Management,
 * frontend-completion pass. Replaces the register's single `attachment`
 * column with proper multi-document evidence (see
 * 2026_09_28_100200_create_compliance_evidence_table and
 * App\Models\OrganizationManagement\ComplianceEvidence).
 *
 * Storage follows the exact convention ComplianceLibraryController already
 * uses for the legacy single attachment - local `public` disk - just under
 * its own `compliance_library/evidence/{compliance_id}/` path so evidence
 * files don't collide with the legacy attachment path.
 */
class ComplianceEvidenceController extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /** GET /organization-management/compliance-library/{id}/evidence */
    public function index(Request $request, $id)
    {
        $tenant = $this->tenant();

        $compliance = ComplianceLibraryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$compliance) {
            return $this->error('Compliance record not found', 404);
        }

        $evidence = ComplianceEvidence::with(['uploadedByUser', 'verifiedByUser'])
            ->where('compliance_id', $compliance->id)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->get();

        return $this->response($evidence->map(fn (ComplianceEvidence $item) => $this->present($item))->values());
    }

    /** POST /organization-management/compliance-library/{id}/evidence */
    public function store(Request $request, $id)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $compliance = ComplianceLibraryRecord::where('sub_institute_id', $tenant)->find($id);
        if (!$compliance) {
            return $this->error('Compliance record not found', 404);
        }

        $data = $request->validate([
            'file' => 'required|file|max:20480',
            'document_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'expiry_date' => 'nullable|date',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = "compliance_library/evidence/{$compliance->id}";
        Storage::disk('public')->putFileAs($path, $file, $filename);

        $evidence = ComplianceEvidence::create([
            'compliance_id' => $compliance->id,
            'sub_institute_id' => $tenant,
            'file_path' => "{$path}/{$filename}",
            'file_name' => $file->getClientOriginalName(),
            'document_type' => $data['document_type'] ?? null,
            'description' => $data['description'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'verification_status' => ComplianceEvidence::STATUS_PENDING,
            'uploaded_by' => $actorId,
        ]);

        // A compliance actively being worked (Upcoming/Due Soon/Overdue) moves
        // to In Progress the moment evidence lands - manual completion still
        // requires the explicit Complete action (ComplianceLibraryController::complete).
        if (!in_array($compliance->status, ComplianceLibraryRecord::MANUALLY_CONTROLLED_STATUSES, true)) {
            $compliance->status = ComplianceLibraryRecord::STATUS_IN_PROGRESS;
            $compliance->save();
        }

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_evidence_uploaded',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $compliance->id,
            'new_values' => ['evidence_id' => $evidence->id, 'file_name' => $evidence->file_name],
        ]);

        return $this->response($this->present($evidence->fresh(['uploadedByUser'])), 'Evidence uploaded successfully', 201);
    }

    /** POST /organization-management/compliance-library/evidence/{evidenceId}/verify */
    public function verify(Request $request, $evidenceId)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $evidence = ComplianceEvidence::where('sub_institute_id', $tenant)->find($evidenceId);
        if (!$evidence) {
            return $this->error('Evidence not found', 404);
        }

        $evidence->verification_status = ComplianceEvidence::STATUS_VERIFIED;
        $evidence->verified_by = $actorId;
        $evidence->verified_at = now();
        $evidence->rejection_reason = null;
        $evidence->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_evidence_verified',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $evidence->compliance_id,
            'new_values' => ['evidence_id' => $evidence->id],
        ]);

        return $this->response($this->present($evidence->fresh(['uploadedByUser', 'verifiedByUser'])), 'Evidence verified');
    }

    /** POST /organization-management/compliance-library/evidence/{evidenceId}/reject */
    public function reject(Request $request, $evidenceId)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $evidence = ComplianceEvidence::where('sub_institute_id', $tenant)->find($evidenceId);
        if (!$evidence) {
            return $this->error('Evidence not found', 404);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $evidence->verification_status = ComplianceEvidence::STATUS_REJECTED;
        $evidence->verified_by = $actorId;
        $evidence->verified_at = now();
        $evidence->rejection_reason = $data['rejection_reason'];
        $evidence->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_evidence_rejected',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $evidence->compliance_id,
            'new_values' => ['evidence_id' => $evidence->id, 'reason' => $data['rejection_reason']],
        ]);

        return $this->response($this->present($evidence->fresh(['uploadedByUser', 'verifiedByUser'])), 'Evidence rejected');
    }

    /** DELETE /organization-management/compliance-library/evidence/{evidenceId} */
    public function destroy(Request $request, $evidenceId)
    {
        $tenant = $this->tenant();

        $evidence = ComplianceEvidence::where('sub_institute_id', $tenant)->find($evidenceId);
        if (!$evidence) {
            return $this->error('Evidence not found', 404);
        }

        if (Storage::disk('public')->exists($evidence->file_path)) {
            Storage::disk('public')->delete($evidence->file_path);
        }

        $complianceId = $evidence->compliance_id;
        $evidence->delete();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_evidence_deleted',
            'entity_type' => 'org_compliance_library',
            'entity_id' => $complianceId,
            'new_values' => ['evidence_id' => (int) $evidenceId],
        ]);

        return $this->response(['id' => (int) $evidenceId], 'Evidence deleted successfully');
    }

    private function present(ComplianceEvidence $evidence): array
    {
        return [
            'id' => (int) $evidence->id,
            'compliance_id' => (int) $evidence->compliance_id,
            'file_name' => $evidence->file_name,
            'file_url' => Storage::disk('public')->url($evidence->file_path),
            'document_type' => $evidence->document_type,
            'description' => $evidence->description,
            'expiry_date' => optional($evidence->expiry_date)->toDateString(),
            'verification_status' => $evidence->verification_status,
            'rejection_reason' => $evidence->rejection_reason,
            'uploaded_by' => $evidence->uploaded_by ? (int) $evidence->uploaded_by : null,
            'uploaded_by_name' => optional($evidence->uploadedByUser)->full_name,
            'verified_by_name' => optional($evidence->verifiedByUser)->full_name,
            'verified_at' => optional($evidence->verified_at)->toDateTimeString(),
            'created_at' => optional($evidence->created_at)->toDateTimeString(),
        ];
    }

    private function response($data, string $message = 'Success', int $code = 200)
    {
        return response()->json(['status' => 1, 'message' => $message, 'data' => $data], $code);
    }

    private function error(string $message, int $code = 400)
    {
        return response()->json(['status' => 0, 'message' => $message], $code);
    }
}
