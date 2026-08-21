<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Onboarding\Concerns\ResolvesOnboardingContext;
use App\Models\TalentManagement\TalentOnboardingDocument;
use App\Models\TalentManagement\TalentOnboardingJourney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\OnboardingDocumentController`.
 *
 * The sidebar Documents card and its "View all" sheet.
 *
 * A row is a document REQUESTED from the hire and where that request stands
 * (pending -> sent -> received -> verified / rejected). `staff_document` stores
 * an already-filed HR document with no such lifecycle, so it is not reused;
 * `document_type_id` points at the same `document_type` master, keeping both in
 * one vocabulary.
 */
class OnboardingDocumentController extends Controller
{
    use ResolvesOnboardingContext;

    private const AUDIT_LABELS = [
        'title'            => 'Document',
        'document_type_id' => 'Document Type',
        'status'           => 'Status',
        'is_mandatory'     => 'Mandatory',
        'due_date'         => 'Due Date',
        'remarks'          => 'Remarks',
        'file_name'        => 'File',
    ];

    /**
     * GET /api/onboarding/journeys/{journeyId}/documents
     *
     * Query: status, search (optional)
     */
    public function index(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $status = $this->activeOnbFilter($request->input('status'));
        $search = $this->activeOnbFilter($request->input('search'));

        $documents = TalentOnboardingDocument::where('sub_institute_id', $tenant)
            ->where('journey_id', $journey->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            }))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // No standalone `document_type` table on this target - reuses
        // `student_document_type` (filtered to `user_type = 'staff'`), the
        // same master EmployeeDirectoryController already reads for staff
        // documents. See OnboardingOverviewController's matching note.
        $typeNames = DB::table('student_document_type')
            ->where('user_type', 'staff')
            ->whereIn('id', $documents->pluck('document_type_id')->filter()->unique()->all() ?: [0])
            ->pluck('document_type', 'id');

        return $this->onboardingResponse(
            $documents->map(fn ($doc) => $this->presentDocument($doc, $typeNames))->all(),
            'Success',
            200,
            [
                'summary' => [
                    'total'    => $documents->count(),
                    'pending'  => $documents->where('status', 'pending')->count(),
                    'sent'     => $documents->where('status', 'sent')->count(),
                    'received' => $documents->where('status', 'received')->count(),
                    'verified' => $documents->where('status', 'verified')->count(),
                ],
            ]
        );
    }

    /**
     * POST /api/onboarding/journeys/{journeyId}/documents
     *
     * Accepts either a request-only row (title + type, no file yet) or a
     * multipart upload, so HR can raise the request before the hire responds.
     */
    public function store(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $validated = $request->validate([
            'title'            => 'required|string|max:191',
            'document_type_id' => 'nullable|integer',
            'status'           => 'nullable|in:pending,sent,received,verified,rejected',
            'is_mandatory'     => 'nullable|boolean',
            'due_date'         => 'nullable|date',
            'remarks'          => 'nullable|string',
            'file'             => 'nullable|file|max:20480',
            'file_name'        => 'nullable|string|max:191',
        ]);

        $fileName = $validated['file_name'] ?? null;
        $filePath = null;

        if ($request->hasFile('file')) {
            [$fileName, $filePath] = $this->storeUpload($request, $tenant);
        }

        $status = $validated['status'] ?? ($filePath ? 'received' : 'pending');

        $document = TalentOnboardingDocument::create([
            'sub_institute_id' => $tenant,
            'journey_id'       => (int) $journey->id,
            'document_type_id' => $validated['document_type_id'] ?? null,
            'title'            => $validated['title'],
            'file_name'        => $fileName,
            'file_path'        => $filePath,
            'status'           => $status,
            'is_mandatory'     => $validated['is_mandatory'] ?? true,
            'due_date'         => $validated['due_date'] ?? null,
            'remarks'          => $validated['remarks'] ?? null,
            'requested_at'     => now(),
            'submitted_at'     => $filePath ? now() : null,
            'sort_order'       => (int) TalentOnboardingDocument::where('sub_institute_id', $tenant)
                ->where('journey_id', $journey->id)->max('sort_order') + 1,
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]);

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'created_document',
            'requested document ' . $document->title,
            'document',
            $document->id,
            $document->title,
            null,
            (int) $journey->id
        );

        return $this->onboardingResponse($this->presentDocument($document, collect()), 'Document added', 201);
    }

    /**
     * PUT /api/onboarding/documents/{id} (route also accepts POST for the
     * multipart spoofed-PUT upload).
     *
     * Also the upload endpoint for an existing request - a multipart PUT that
     * carries `file` flips the row to received and stamps submitted_at.
     */
    public function update(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $document = TalentOnboardingDocument::where('sub_institute_id', $tenant)->find($id);

        if (!$document) {
            return $this->onboardingError('Document not found', 404);
        }

        $validated = $request->validate([
            'title'            => 'sometimes|required|string|max:191',
            'document_type_id' => 'nullable|integer',
            'status'           => 'nullable|in:pending,sent,received,verified,rejected',
            'is_mandatory'     => 'nullable|boolean',
            'due_date'         => 'nullable|date',
            'remarks'          => 'nullable|string',
            'file'             => 'nullable|file|max:20480',
        ]);

        $changes = $this->diffOnboardingChanges($document->getOriginal(), $validated, self::AUDIT_LABELS);

        if ($request->hasFile('file')) {
            [$fileName, $filePath] = $this->storeUpload($request, $tenant);
            $document->file_name = $fileName;
            $document->file_path = $filePath;
            $document->submitted_at = now();
            $document->status = $validated['status'] ?? 'received';
            unset($validated['status']);
        }

        $document->fill($validated);

        if (($validated['status'] ?? null) === 'verified') {
            $document->verified_at = now();
            $document->verified_by = $context['user_id'];
        } elseif (array_key_exists('status', $validated) && $validated['status'] !== 'verified') {
            $document->verified_at = null;
            $document->verified_by = null;
        }

        $document->updated_by = $context['user_id'];
        $document->save();

        if (!empty($changes)) {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'updated_document',
                'updated document ' . $document->title,
                'document',
                $document->id,
                $document->title,
                $changes,
                (int) $document->journey_id
            );
        }

        return $this->onboardingResponse($this->presentDocument($document, collect()), 'Document updated');
    }

    /** DELETE /api/onboarding/documents/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $document = TalentOnboardingDocument::where('sub_institute_id', $tenant)->find($id);

        if (!$document) {
            return $this->onboardingError('Document not found', 404);
        }

        $title = $document->title;
        $journeyId = (int) $document->journey_id;

        $document->deleted_by = $context['user_id'];
        $document->save();
        $document->delete();

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'deleted_document',
            'removed document ' . $title,
            'document',
            (int) $id,
            $title,
            null,
            $journeyId
        );

        return $this->onboardingResponse(['id' => (int) $id], 'Document deleted');
    }

    /** @return array{0:string, 1:string} [fileName, storedPath] */
    private function storeUpload(Request $request, int $tenant): array
    {
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension() ?: 'dat';
        $stored = 'onboarding_documents/' . $tenant . '/' . uniqid('onb_', true) . '.' . $extension;

        // 'public' is the disk every other upload in this app writes to.
        Storage::disk('public')->putFileAs(dirname($stored), $file, basename($stored));

        return [$file->getClientOriginalName(), $stored];
    }

    /** @param \Illuminate\Support\Collection<int, string> $typeNames */
    private function presentDocument(TalentOnboardingDocument $document, $typeNames): array
    {
        return [
            'id'               => (int) $document->id,
            'journey_id'       => (int) $document->journey_id,
            'title'            => $document->title,
            'document_type_id' => $document->document_type_id ? (int) $document->document_type_id : null,
            'document_type'    => $document->document_type_id ? ($typeNames[$document->document_type_id] ?? null) : null,
            'file_name'        => $document->file_name,
            'file_path'        => $document->file_path,
            'url'              => $document->file_path ? Storage::disk('public')->url($document->file_path) : null,
            'status'           => $document->status,
            'status_label'     => $this->onbStatusLabel($document->status),
            'is_mandatory'     => (bool) $document->is_mandatory,
            'due_date'         => optional($document->due_date)->toDateString(),
            'due_date_label'   => $this->onbDateLabel($document->due_date),
            'requested_at'     => $this->onbDateTimeLabel($document->requested_at),
            'submitted_at'     => $this->onbDateTimeLabel($document->submitted_at),
            'verified_at'      => $this->onbDateTimeLabel($document->verified_at),
            'remarks'          => $document->remarks,
            'sort_order'       => (int) $document->sort_order,
        ];
    }
}
