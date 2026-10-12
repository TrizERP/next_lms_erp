<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Alumni documents (certificates, ID and employment proof, business documents).
 *
 *   GET  alumni/{id}/documents                         list (metadata only - never the storage path)
 *   POST alumni/{id}/documents/upload                  multipart: file, doc_type, remarks?
 *   POST alumni/{id}/documents/{docId}/download        authorised stream
 *   POST alumni/{id}/documents/{docId}/verify          decision: verified | rejected, remarks?
 *   POST alumni/{id}/documents/{docId}/delete
 *   POST alumni/{id}/achievements/{rid}/verify         verify an achievement (+ optional evidence link)
 *
 * STORAGE. Files go on the PRIVATE `local` disk (storage/app/private, outside public/), under a random
 * name chosen here, so neither the filename nor the folder can be guessed or used for path traversal. The
 * browser never receives a URL: it calls the download endpoint, which re-checks the tenant and role on every
 * request. Downloads and exports use POST because the Next relay that carries binary responses only forwards POST.
 *
 * VALIDATION. Type and size are enforced on the server from the file's real content (Laravel's `mimes` rule
 * inspects the bytes, not just the extension); a spoofed extension fails. Executable / script types are not on the
 * list. Original names are sanitised before they are ever echoed in a header.
 */
class AlumniDocumentApiController extends Controller
{
    use AlumniApiSupport;

    public const TYPES = ['graduation_certificate', 'achievement_certificate', 'professional_certificate', 'id_proof',
        'employment_proof', 'business_document', 'profile_document', 'other'];

    public const MAX_KB = 5120;

    public const MIMES = 'pdf,jpg,jpeg,png,doc,docx';

    private const DISK = 'local';

    public function __construct(private AlumniService $alumni)
    {
    }

    public function index(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        return $this->ok(['types' => self::TYPES, 'max_kb' => self::MAX_KB, 'rows' => $this->rows($t, (int) $id)]);
    }

    public function upload(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:' . self::MAX_KB, 'mimes:' . self::MIMES],
            'doc_type' => ['required', Rule::in(self::TYPES)],
            'remarks' => 'nullable|string|max:500',
        ], [
            'file.max' => 'The file must be 5 MB or smaller.',
            'file.mimes' => 'Upload a PDF, JPG, PNG or Word document.',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        $file = $request->file('file');
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs("alumni/{$t}/{$id}", bin2hex(random_bytes(16)) . '.' . $ext, self::DISK);
        if ($path === false) {
            return response()->json(['status_code' => 0, 'message' => "Couldn't store the file. Try again."], 500);
        }

        $docId = DB::table('alumni_documents')->insertGetId([
            'sub_institute_id' => $t, 'alumni_id' => (int) $id, 'doc_type' => $request->input('doc_type'),
            'original_name' => $this->safeName($file->getClientOriginalName()), 'file_path' => $path,
            'mime' => $file->getMimeType(), 'size_bytes' => (int) $file->getSize(),
            'verification_status' => 'pending', 'remarks' => $request->input('remarks'),
            'uploaded_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->alumni->logActivity($t, (int) $id, 'document_uploaded', 'Document uploaded: ' . $request->input('doc_type'), $this->userId($request));
        AuditTrail::record($t, 'alumni', 'alumni.documents', 'upload', [
            'entity_type' => 'alumni_document', 'entity_id' => $docId,
            'after' => ['alumni_id' => (int) $id, 'doc_type' => $request->input('doc_type'), 'size_bytes' => (int) $file->getSize()],
        ], $request);

        return $this->ok($this->rows($t, (int) $id, $docId)[0] ?? null, 'Document uploaded.', 201);
    }

    public function download(Request $request, $id, $docId)
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $doc = DB::table('alumni_documents')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->where('id', (int) $docId)->first();
        if (! $doc || ! Storage::disk(self::DISK)->exists($doc->file_path)) {
            return $this->notFound('Document');
        }
        AuditTrail::record($t, 'alumni', 'alumni.documents', 'download', [
            'entity_type' => 'alumni_document', 'entity_id' => (int) $doc->id, 'after' => ['alumni_id' => (int) $id],
        ], $request);

        /** @var StreamedResponse $response */
        $response = Storage::disk(self::DISK)->download($doc->file_path, $doc->original_name, [
            'Content-Type' => $doc->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $response;
    }

    public function verify(Request $request, $id, $docId): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['decision' => 'required|in:verified,rejected', 'remarks' => 'nullable|string|max:500']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        if ($request->input('decision') === 'rejected' && trim((string) $request->input('remarks')) === '') {
            return $this->validationError(['remarks' => ['Give a reason when rejecting a document.']]);
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_documents')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->where('id', (int) $docId);
        $doc = (clone $q)->first();
        if (! $doc) {
            return $this->notFound('Document');
        }
        $q->update([
            'verification_status' => $request->input('decision'), 'verified_by' => $this->userId($request),
            'verified_at' => now(), 'remarks' => $request->input('remarks', $doc->remarks), 'updated_at' => now(),
        ]);
        $this->alumni->logActivity($t, (int) $id, 'document_' . $request->input('decision'), 'Document ' . $request->input('decision'), $this->userId($request));
        AuditTrail::record($t, 'alumni', 'alumni.documents', 'verify', [
            'entity_type' => 'alumni_document', 'entity_id' => (int) $docId,
            'before' => ['verification_status' => $doc->verification_status], 'after' => ['verification_status' => $request->input('decision')],
        ], $request);

        return $this->ok($this->rows($t, (int) $id, (int) $docId)[0] ?? null, 'Document ' . $request->input('decision') . '.');
    }

    public function destroy(Request $request, $id, $docId): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $doc = DB::table('alumni_documents')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->where('id', (int) $docId)->first();
        if (! $doc) {
            return $this->notFound('Document');
        }
        DB::table('alumni_documents')->where('id', $doc->id)->delete();
        DB::table('alumni_achievements')->where('sub_institute_id', $t)->where('document_id', $doc->id)->update(['document_id' => null]);
        Storage::disk(self::DISK)->delete($doc->file_path);
        AuditTrail::record($t, 'alumni', 'alumni.documents', 'delete', [
            'entity_type' => 'alumni_document', 'entity_id' => (int) $doc->id, 'before' => ['doc_type' => $doc->doc_type, 'original_name' => $doc->original_name],
        ], $request);

        return $this->ok(null, 'Document removed.');
    }

    /** Verify an achievement; optionally attach one of THIS alumnus's documents as evidence. */
    public function verifyAchievement(Request $request, $id, $rid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['decision' => 'required|in:verified,rejected', 'document_id' => 'nullable|integer|min:1']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_achievements')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->where('id', (int) $rid);
        $row = (clone $q)->first();
        if (! $row) {
            return $this->notFound('Achievement');
        }
        $update = ['verification_status' => $request->input('decision'), 'verified_by' => $this->userId($request), 'updated_at' => now()];
        if ($request->filled('document_id')) {
            $owns = DB::table('alumni_documents')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->where('id', (int) $request->input('document_id'))->exists();
            if (! $owns) {
                return $this->validationError(['document_id' => ['That document does not belong to this alumnus.']]);
            }
            $update['document_id'] = (int) $request->input('document_id');
        }
        $q->update($update);
        AuditTrail::record($t, 'alumni', 'alumni.achievements', 'verify', [
            'entity_type' => 'alumni_achievement', 'entity_id' => (int) $rid,
            'before' => ['verification_status' => $row->verification_status], 'after' => ['verification_status' => $request->input('decision')],
        ], $request);

        if ($request->input('decision') === 'verified') {
            // congratulations are marketing-like: only channels the alumnus consented to, and only once per achievement
            try {
                [$subject, $body] = app(AlumniMessaging::class)->templateFor($t, 'achievement_congratulations');
                app(AlumniMessaging::class)->notify($t, (int) $id, 'achievement_congratulations', $subject, $body, 'achievement:' . (int) $rid, false, ['achievement' => (string) $row->title]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->ok(DB::table('alumni_achievements')->where('id', (int) $rid)->first(), 'Achievement ' . $request->input('decision') . '.');
    }

    /** Metadata rows: file_path is never selected. */
    private function rows(int $t, int $alumniId, ?int $docId = null): array
    {
        $q = DB::table('alumni_documents')->where('sub_institute_id', $t)->where('alumni_id', $alumniId)->orderByDesc('id');
        if ($docId !== null) {
            $q->where('id', $docId);
        }

        return $q->get(['id', 'doc_type', 'original_name', 'mime', 'size_bytes', 'verification_status', 'verified_by', 'verified_at', 'remarks', 'created_at'])->all();
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^\w.\- ]+/u', '_', basename(str_replace('\\', '/', $name))) ?: 'document';

        return mb_substr($name, 0, 200);
    }
}
