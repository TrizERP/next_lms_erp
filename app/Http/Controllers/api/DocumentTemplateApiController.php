<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Document Templates API — backs the drag-and-drop template designer at
 * /document-templates in the Next frontend.
 *
 * Every response uses the app-wide envelope: {status_code, message, data}.
 * status_code 1 = ok, 0 = validation/business failure, 2 = auth failure.
 *
 * Tenant resolution mirrors the other headless controllers: the JWT payload on
 * the Authorization header wins, request params are the fallback. A request
 * that resolves to no tenant is rejected — templates are tenant data and must
 * never leak across schools.
 */
class DocumentTemplateApiController extends Controller
{
    /** Categories a template can be filed under, and the entity each merges against. */
    public const CATEGORIES = [
        'certificate' => 'Certificates (TC, bonafide, character)',
        'id_card' => 'ID cards',
        'fees' => 'Fee receipts and notices',
        'admission' => 'Admission letters and forms',
        'exam' => 'Exam and result documents',
        'circular' => 'Circulars and notices',
        'general' => 'General documents',
    ];

    // -----------------------------------------------------------------------
    // Context
    // -----------------------------------------------------------------------

    /**
     * Resolve {tenant, user} for the request, or an error response.
     *
     * @return array{0: array|null, 1: \Illuminate\Http\JsonResponse|null}
     */
    private function context(Request $request): array
    {
        $claims = $this->tokenClaims($request);

        $subInstituteId = (int) ($claims['sub_institute_id'] ?? 0) ?: (int) $request->input('sub_institute_id');
        $userId = (int) ($claims['id'] ?? 0) ?: (int) $request->input('user_id');

        if ($subInstituteId <= 0) {
            return [null, $this->fail('A school context (sub_institute_id) is required.', 2, 401)];
        }

        return [[
            'sub_institute_id' => $subInstituteId,
            'user_id' => $userId ?: null,
            'syear' => (string) $request->input('syear', ''),
        ], null];
    }

    /** Decode the (already-issued) JWT payload without verifying — same as the sibling LMS controllers. */
    private function tokenClaims(Request $request): array
    {
        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        $decoded = base64_decode(strtr($parts[1], '-_', '+/'));

        return json_decode($decoded ?: '{}', true) ?: [];
    }

    private function ok($data = [], string $message = 'Success')
    {
        return response()->json(['status_code' => 1, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $statusCode = 0, int $httpStatus = 422)
    {
        return response()->json(['status_code' => $statusCode, 'message' => $message, 'data' => []], $httpStatus);
    }

    /** Shape a template row for the client (list rows omit the heavy `content`). */
    private function present(DocumentTemplate $template, bool $withContent = true): array
    {
        $row = [
            'id' => $template->id,
            'name' => $template->name,
            'category' => $template->category,
            'description' => $template->description,
            'status' => $template->status,
            'version' => $template->version,
            'syear' => $template->syear,
            'created_by' => $template->created_by,
            'updated_by' => $template->updated_by,
            'created_at' => optional($template->created_at)->toDateTimeString(),
            'updated_at' => optional($template->updated_at)->toDateTimeString(),
        ];

        if ($withContent) {
            $row['content'] = $template->content;
        }

        return $row;
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $query = DocumentTemplate::forTenant($context['sub_institute_id']);

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderByDesc('updated_at')->get()
            ->map(fn (DocumentTemplate $template) => $this->present($template, false))
            ->values();

        return $this->ok([
            'templates' => $templates,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function show(Request $request, $id)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $template = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $template) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        return $this->ok(['template' => $this->present($template)]);
    }

    public function store(Request $request)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:draft,published,archived',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->messages()->first());
        }

        $template = DocumentTemplate::create([
            'sub_institute_id' => $context['sub_institute_id'],
            'name' => trim((string) $request->input('name')),
            'category' => $this->normalizeCategory($request->input('category')),
            'description' => $request->input('description'),
            'content' => $request->input('content'),
            'version' => 1,
            'status' => $request->input('status', 'draft'),
            'syear' => $context['syear'] ?: null,
            'created_by' => $context['user_id'],
            'updated_by' => $context['user_id'],
        ]);

        return $this->ok(['template' => $this->present($template)], 'Template saved.');
    }

    public function update(Request $request, $id)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:draft,published,archived',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->messages()->first());
        }

        $template = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $template) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        // Snapshot the outgoing content before overwriting it.
        DocumentTemplateVersion::create([
            'document_template_id' => $template->id,
            'sub_institute_id' => $template->sub_institute_id,
            'name' => $template->name,
            'content' => $template->content,
            'version' => $template->version,
            'created_by' => $context['user_id'],
        ]);

        $template->update([
            'name' => trim((string) $request->input('name')),
            'category' => $this->normalizeCategory($request->input('category', $template->category)),
            'description' => $request->input('description', $template->description),
            'content' => $request->input('content'),
            'status' => $request->input('status', $template->status),
            'version' => $template->version + 1,
            'updated_by' => $context['user_id'],
        ]);

        return $this->ok(['template' => $this->present($template)], 'Template updated.');
    }

    public function destroy(Request $request, $id)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $template = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $template) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        $template->delete();

        return $this->ok([], 'Template deleted.');
    }

    public function duplicate(Request $request, $id)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $source = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $source) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        $copy = DocumentTemplate::create([
            'sub_institute_id' => $source->sub_institute_id,
            'name' => mb_substr($source->name . ' (copy)', 0, 255),
            'category' => $source->category,
            'description' => $source->description,
            'content' => $source->content,
            'version' => 1,
            'status' => 'draft',
            'syear' => $context['syear'] ?: $source->syear,
            'created_by' => $context['user_id'],
            'updated_by' => $context['user_id'],
        ]);

        return $this->ok(['template' => $this->present($copy)], 'Template duplicated.');
    }

    // -----------------------------------------------------------------------
    // Versions
    // -----------------------------------------------------------------------

    public function versions(Request $request, $id)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $template = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $template) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        $versions = $template->versions()
            ->orderByDesc('version')
            ->get()
            ->map(fn (DocumentTemplateVersion $version) => [
                'id' => $version->id,
                'version' => $version->version,
                'name' => $version->name,
                'created_by' => $version->created_by,
                'created_at' => optional($version->created_at)->toDateTimeString(),
            ])
            ->values();

        return $this->ok(['versions' => $versions, 'current_version' => $template->version]);
    }

    public function restore(Request $request, $id, $version)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $template = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $template) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        $snapshot = DocumentTemplateVersion::where('document_template_id', $template->id)
            ->where('version', (int) $version)
            ->first();
        if (! $snapshot) {
            return $this->fail('That version was not found for this template.', 0, 404);
        }

        // The version being replaced is itself snapshotted, so a restore is undoable.
        DocumentTemplateVersion::create([
            'document_template_id' => $template->id,
            'sub_institute_id' => $template->sub_institute_id,
            'name' => $template->name,
            'content' => $template->content,
            'version' => $template->version,
            'created_by' => $context['user_id'],
        ]);

        $template->update([
            'content' => $snapshot->content,
            'version' => $template->version + 1,
            'updated_by' => $context['user_id'],
        ]);

        return $this->ok(['template' => $this->present($template)], "Restored version {$version}.");
    }

    /** Read one archived version's content (for preview / compare before restoring). */
    public function versionContent(Request $request, $id, $version)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $template = DocumentTemplate::forTenant($context['sub_institute_id'])->find($id);
        if (! $template) {
            return $this->fail('That template was not found for this school.', 0, 404);
        }

        $snapshot = DocumentTemplateVersion::where('document_template_id', $template->id)
            ->where('version', (int) $version)
            ->first();
        if (! $snapshot) {
            return $this->fail('That version was not found for this template.', 0, 404);
        }

        return $this->ok(['version' => $snapshot->version, 'content' => $snapshot->content]);
    }

    // -----------------------------------------------------------------------
    // Merge fields
    // -----------------------------------------------------------------------

    /**
     * The catalog of {{placeholders}} the designer can insert. Grouped so the
     * editor can render a picker; the same keys drive mergeData() below, which
     * is what keeps preview and print honest.
     */
    public function mergeFields()
    {
        return $this->ok(['groups' => $this->fieldCatalog(), 'categories' => self::CATEGORIES]);
    }

    private function fieldCatalog(): array
    {
        return [
            [
                'key' => 'school',
                'label' => 'School',
                'fields' => [
                    ['token' => 'school_name', 'label' => 'School name'],
                    ['token' => 'school_address', 'label' => 'School address'],
                    ['token' => 'school_email', 'label' => 'School email'],
                    ['token' => 'school_mobile', 'label' => 'School mobile'],
                    ['token' => 'school_logo', 'label' => 'School logo (image)'],
                    ['token' => 'school_code', 'label' => 'School short code'],
                    ['token' => 'school_header', 'label' => 'Receipt header'],
                    ['token' => 'principal_name', 'label' => 'Principal name'],
                    ['token' => 'academic_year', 'label' => 'Academic year'],
                ],
            ],
            [
                'key' => 'student',
                'label' => 'Student',
                'fields' => [
                    ['token' => 'student_name', 'label' => 'Full name'],
                    ['token' => 'student_first_name', 'label' => 'First name'],
                    ['token' => 'student_last_name', 'label' => 'Last name'],
                    ['token' => 'admission_no', 'label' => 'Admission number'],
                    ['token' => 'enrollment_no', 'label' => 'Enrollment number'],
                    ['token' => 'roll_no', 'label' => 'Roll number'],
                    ['token' => 'class', 'label' => 'Class / standard'],
                    ['token' => 'division', 'label' => 'Division'],
                    ['token' => 'class_division', 'label' => 'Class and division'],
                    ['token' => 'gender', 'label' => 'Gender'],
                    ['token' => 'dob', 'label' => 'Date of birth'],
                    ['token' => 'admission_date', 'label' => 'Admission date'],
                    ['token' => 'student_mobile', 'label' => 'Student mobile'],
                    ['token' => 'student_email', 'label' => 'Student email'],
                    ['token' => 'student_address', 'label' => 'Address'],
                    ['token' => 'student_photo', 'label' => 'Photo (image)'],
                    ['token' => 'blood_group', 'label' => 'Blood group'],
                    ['token' => 'religion', 'label' => 'Religion'],
                    ['token' => 'caste', 'label' => 'Caste'],
                    ['token' => 'category', 'label' => 'Reserved category'],
                    ['token' => 'nationality', 'label' => 'Nationality'],
                    ['token' => 'mother_tongue', 'label' => 'Mother tongue'],
                    ['token' => 'house', 'label' => 'House'],
                    ['token' => 'aadhar_no', 'label' => 'Aadhaar number'],
                    ['token' => 'pen_no', 'label' => 'PEN number'],
                    ['token' => 'place_of_birth', 'label' => 'Place of birth'],
                ],
            ],
            [
                'key' => 'guardian',
                'label' => 'Parent / guardian',
                'fields' => [
                    ['token' => 'father_name', 'label' => "Father's name"],
                    ['token' => 'mother_name', 'label' => "Mother's name"],
                    ['token' => 'guardian_mobile', 'label' => 'Guardian mobile'],
                    ['token' => 'mother_mobile', 'label' => 'Mother mobile'],
                    ['token' => 'guardian_email', 'label' => 'Guardian email'],
                ],
            ],
            [
                'key' => 'staff',
                'label' => 'Issuing staff',
                'fields' => [
                    ['token' => 'user_name', 'label' => 'Issued by (name)'],
                    ['token' => 'user_email', 'label' => 'Issued by (email)'],
                    ['token' => 'user_designation', 'label' => 'Issued by (designation)'],
                ],
            ],
            [
                'key' => 'document',
                'label' => 'Document',
                'fields' => [
                    ['token' => 'document_date', 'label' => 'Issue date'],
                    ['token' => 'document_place', 'label' => 'Place'],
                    ['token' => 'reference_no', 'label' => 'Reference number'],
                ],
            ],
        ];
    }

    /**
     * Resolve real values for every {{token}} in the catalog, for one student
     * (optional) at one school. The designer calls this to preview a template
     * with live data, and the print path calls it to produce the final document.
     */
    public function mergeData(Request $request)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $tenantId = $context['sub_institute_id'];
        $syear = $context['syear'];
        $values = [];

        // --- school -------------------------------------------------------
        $school = DB::table('school_setup')->where('Id', $tenantId)->first();
        $instituteDetail = DB::table('institute_detail')->where('sub_institute_id', $tenantId)->first();

        $values['school_name'] = (string) ($school->SchoolName ?? '');
        $values['school_address'] = (string) ($school->ReceiptAddress ?? '');
        $values['school_email'] = (string) ($school->Email ?? $school->FeeEmail ?? '');
        $values['school_mobile'] = (string) ($school->Mobile ?? $school->ReceiptContact ?? '');
        $values['school_code'] = (string) ($school->ShortCode ?? '');
        $values['school_header'] = (string) ($school->ReceiptHeader ?? '');
        $values['school_logo'] = $this->logoUrl($request, $school->Logo ?? '');
        $values['principal_name'] = (string) ($instituteDetail->principal_name ?? '');
        $values['academic_year'] = $syear ?: (string) ($school->syear ?? '');

        // --- student (optional) -------------------------------------------
        $studentId = (int) $request->input('student_id');
        if ($studentId > 0) {
            $student = DB::table('tblstudent')
                ->where('id', $studentId)
                ->where('sub_institute_id', $tenantId)
                ->first();

            if (! $student) {
                return $this->fail('That student was not found for this school.', 0, 404);
            }

            // Class/division come from the enrollment row. Prefer the requested
            // academic year, but fall back to the most recent enrollment —
            // printing a TC for a student who has left should still name the
            // class they were last in, rather than leaving it blank.
            $enrollment = $this->latestEnrollment($studentId, $syear);
            $student->roll_no = $enrollment->roll_no ?? null;
            $student->standard_name = $enrollment->standard_name ?? null;
            $student->division_name = $enrollment->division_name ?? null;

            $fullName = trim(implode(' ', array_filter([
                $student->first_name ?? '',
                $student->middle_name ?? '',
                $student->last_name ?? '',
            ])));
            $standard = (string) ($student->standard_name ?? '');
            $division = (string) ($student->division_name ?? '');

            $values['student_name'] = $fullName;
            $values['student_first_name'] = (string) ($student->first_name ?? '');
            $values['student_last_name'] = (string) ($student->last_name ?? '');
            $values['admission_no'] = (string) ($student->admission_id ?? '');
            $values['enrollment_no'] = (string) ($student->enrollment_no ?? '');
            $values['roll_no'] = (string) ($student->roll_no ?? $student->roll_no_1 ?? '');
            $values['class'] = $standard;
            $values['division'] = $division;
            $values['class_division'] = trim($standard . ($division !== '' ? ' - ' . $division : ''));
            $values['gender'] = (string) ($student->gender ?? '');
            $values['dob'] = $this->formatDate($student->dob ?? null);
            $values['admission_date'] = $this->formatDate($student->admission_date ?? null);
            $values['student_mobile'] = (string) ($student->student_mobile ?? $student->mobile ?? '');
            $values['student_email'] = (string) ($student->email ?? '');
            $values['student_address'] = trim(implode(', ', array_filter([
                $student->address ?? '',
                $student->city ?? '',
                $student->state ?? '',
                $student->pincode ?? '',
            ])));
            $values['student_photo'] = $this->studentPhotoUrl($request, $student->image ?? '');
            $values['blood_group'] = (string) ($student->bloodgroup ?? '');
            $values['religion'] = (string) ($student->religion ?? '');
            $values['caste'] = (string) ($student->cast ?? $student->caste ?? '');
            $values['category'] = (string) ($student->reserve_categorey ?? '');
            $values['nationality'] = (string) ($student->nationality ?? '');
            $values['mother_tongue'] = (string) ($student->mother_tongue ?? '');
            $values['house'] = (string) ($student->house ?? '');
            $values['aadhar_no'] = (string) ($student->adharnumber ?? '');
            $values['pen_no'] = (string) ($student->student_pen_no ?? '');
            $values['place_of_birth'] = (string) ($student->place_of_birth ?? '');

            $values['father_name'] = (string) ($student->father_name ?? '');
            $values['mother_name'] = (string) ($student->mother_name ?? '');
            $values['guardian_mobile'] = (string) ($student->mobile ?? '');
            $values['mother_mobile'] = (string) ($student->mother_mobile ?? '');
            $values['guardian_email'] = (string) ($student->email ?? '');
        }

        // --- issuing staff --------------------------------------------------
        if ($context['user_id']) {
            $user = DB::table('tbluser as u')
                ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
                ->selectRaw('u.first_name, u.middle_name, u.last_name, u.email, p.name as profile_name')
                ->where('u.id', $context['user_id'])
                ->first();

            if ($user) {
                $values['user_name'] = trim(implode(' ', array_filter([
                    $user->first_name ?? '',
                    $user->middle_name ?? '',
                    $user->last_name ?? '',
                ])));
                $values['user_email'] = (string) ($user->email ?? '');
                $values['user_designation'] = (string) ($user->profile_name ?? '');
            }
        }

        // --- document -------------------------------------------------------
        $values['document_date'] = $this->formatDate(now()->toDateString());
        $values['document_place'] = (string) ($school->ShortCode ?? '');
        $values['reference_no'] = (string) $request->input('reference_no', '');

        // Guarantee every catalog token exists, so an unresolved field renders
        // blank instead of leaking a raw "{{token}}" onto a printed document.
        foreach ($this->fieldCatalog() as $group) {
            foreach ($group['fields'] as $field) {
                $values[$field['token']] = $values[$field['token']] ?? '';
            }
        }

        return $this->ok(['values' => $values]);
    }

    /** Students the designer can preview a template against. */
    public function previewStudents(Request $request)
    {
        [$context, $error] = $this->context($request);
        if ($error) {
            return $error;
        }

        $search = trim((string) $request->input('q', ''));

        // The enrollment join takes the student's latest row (highest syear) so
        // the picker still shows a class for students without a current-year
        // enrollment. Nameless rows exist in the legacy data and are excluded —
        // they are unusable in a picker.
        $query = DB::table('tblstudent as s')
            ->leftJoin('tblstudent_enrollment as e', function ($join) {
                $join->on('e.student_id', '=', 's.id')
                    ->whereRaw('e.id = (SELECT MAX(e2.id) FROM tblstudent_enrollment e2 WHERE e2.student_id = s.id)');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->selectRaw("s.id, TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)) as name,
                s.admission_id, s.enrollment_no, e.roll_no, st.name as standard_name, d.name as division_name")
            ->where('s.sub_institute_id', $context['sub_institute_id'])
            ->where('s.status', 1)
            ->whereRaw("TRIM(CONCAT_WS('', s.first_name, s.last_name)) <> ''");

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->whereRaw("CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) like ?", ["%{$search}%"])
                    ->orWhere('s.admission_id', 'like', "%{$search}%")
                    ->orWhere('s.enrollment_no', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('s.first_name')->limit(50)->get();

        return $this->ok(['students' => $students]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * The enrollment row to describe a student by: the requested academic year
     * when one exists, otherwise the most recent one on file.
     */
    private function latestEnrollment(int $studentId, string $syear)
    {
        $base = fn () => DB::table('tblstudent_enrollment as e')
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->selectRaw('e.roll_no, e.syear, st.name as standard_name, d.name as division_name')
            ->where('e.student_id', $studentId);

        if ($syear !== '') {
            $match = $base()->where('e.syear', $syear)->first();
            if ($match) {
                return $match;
            }
        }

        return $base()->orderByDesc('e.syear')->orderByDesc('e.id')->first();
    }

    private function normalizeCategory($category): string
    {
        $category = (string) $category;

        return array_key_exists($category, self::CATEGORIES) ? $category : 'general';
    }

    private function formatDate($value): string
    {
        if (! $value || $value === '0000-00-00') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception $exception) {
            return (string) $value;
        }
    }

    private function logoUrl(Request $request, $logo): string
    {
        $logo = (string) $logo;
        if ($logo === '') {
            return '';
        }
        if (str_starts_with($logo, 'http')) {
            return $logo;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/admin_dep/images/' . ltrim($logo, '/');
    }

    private function studentPhotoUrl(Request $request, $image): string
    {
        $image = (string) $image;
        if ($image === '') {
            return '';
        }
        if (str_starts_with($image, 'http')) {
            return $image;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/student/' . ltrim($image, '/');
    }
}
