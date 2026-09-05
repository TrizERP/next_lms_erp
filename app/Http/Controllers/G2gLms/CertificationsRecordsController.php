<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Certifications & Records — G2G LMS migration (Package 3).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\LmsLearningController`
 * (the `certificates`, `downloadCertificate`, `verifyCertificate`,
 * `reissueCertificate`, `issueCertificate` methods, plus the private
 * `makeVerificationCode`/`certificateWarningDays` helpers). Logic and
 * response shapes are preserved; only identity resolution changed (session,
 * not a bearer token — see `ResolvesLmsIdentity`) and PDF rendering uses an
 * inline HTML string via dompdf rather than a Blade view, since no
 * `lms.certificate` view or `config('lms.certificate_templates')` map exists
 * in this repo yet (source templates were never ported).
 *
 * NOTE ON SCOPE: this is the course-completion certificate domain
 * (`lms_certificates`, course_id/enrollment_id-scoped), NOT the
 * externally-issued credential + compliance domain (`s_competency_*`), which
 * already has a full port at `App\Http\Controllers\api\TalentManagement\
 * Competency\CertificationController` / `app/talent-management/certifications`
 * in the frontend. The two are intentionally not merged — see the task
 * brief's DB section.
 *
 * `transcript()` / `completionHistory()` read `lms_course_enroll` /
 * `content_master` / `lms_content_progress` — tables owned by Packages 1/2.
 * Every read is guarded by `lmsTableExists()` so this controller degrades to
 * an empty list rather than a 500 if those migrations have not landed yet in
 * this database.
 */
class CertificationsRecordsController extends Controller
{
    use ResolvesLmsIdentity;

    /** How near an expiry counts as "expiring soon". Matches the source default. */
    private function certificateWarningDays(): int
    {
        return (int) config('lms.certificate_warning_days', env('LMS_CERT_EXPIRY_WARNING_DAYS', 90));
    }

    /* ================================================================== *
     * Certificates
     * ================================================================== */

    /** GET /api/g2g-lms/certifications-records/certificates */
    public function index(Request $request)
    {
        $context = $this->lmsContext($request);
        if (! $context['user_id']) {
            return $this->lmsError('user_id is required', 422);
        }

        $wantsAll = $request->input('scope') === 'all' && $this->isLmsStaffAdmin($context);

        $now = now();
        $soon = $now->copy()->addDays($this->certificateWarningDays());

        $certificates = DB::table('lms_certificates as c')
            ->leftJoin('sub_std_map as s', 's.id', '=', 'c.course_id')
            ->leftJoin('s_users_skills as k', 'k.id', '=', 'c.skill_id')
            ->leftJoin('tbluser as u', 'u.id', '=', 'c.user_id')
            ->when(
                $wantsAll,
                fn ($q) => $q->where('c.sub_institute_id', $context['sub_institute_id']),
                fn ($q) => $q->where('c.user_id', $context['user_id'])
            )
            ->when($request->input('course_id'), fn ($q, $id) => $q->where('c.course_id', $id))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('c.course_title', 'like', "%{$search}%")
                        ->orWhere('c.certificate_number', 'like', "%{$search}%")
                        ->orWhere('u.first_name', 'like', "%{$search}%")
                        ->orWhere('u.last_name', 'like', "%{$search}%")
                        ->orWhere('u.employee_no', 'like', "%{$search}%");
                });
            })
            ->whereNull('c.deleted_at')
            ->orderByDesc('c.issued_at')
            ->get([
                'c.id', 'c.user_id', 'c.course_id', 'c.skill_id', 'c.certificate_number',
                'c.course_title', 'c.issued_at', 'c.expires_at', 'c.status',
                'c.name', 'c.description', 'c.tags', 'c.verification_code',
                'c.supersedes', 'c.superseded_by', 'c.reissued_at',
                's.display_image', 's.subject_category',
                'k.title as skill_title',
                'u.employee_no',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ])
            ->map(function ($certificate) use ($now, $soon) {
                if (! $certificate->expires_at) {
                    $certificate->expiry_state = 'active';
                    $certificate->days_to_expiry = null;
                } else {
                    $expiresAt = \Carbon\Carbon::parse($certificate->expires_at);
                    $certificate->days_to_expiry = (int) $now->diffInDays($expiresAt, false);

                    if ($expiresAt < $now) {
                        $certificate->expiry_state = 'expired';
                    } elseif ($expiresAt <= $soon) {
                        $certificate->expiry_state = 'expiring';
                    } else {
                        $certificate->expiry_state = 'active';
                    }
                }

                $decoded = $certificate->tags ? json_decode($certificate->tags, true) : null;
                $certificate->tags = is_array($decoded) ? array_values($decoded) : null;

                return $certificate;
            });

        return response()->json([
            'status' => true,
            'data' => $certificates,
            'meta' => [
                'scope' => $wantsAll ? 'all' : 'mine',
                'warning_days' => $this->certificateWarningDays(),
            ],
        ]);
    }

    /** GET /api/g2g-lms/certifications-records/certificates/{id}/download */
    public function download(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        $isAdmin = $this->isLmsStaffAdmin($context);

        $certificate = DB::table('lms_certificates as c')
            ->leftJoin('tbluser as u', 'u.id', '=', 'c.user_id')
            ->where('c.id', $id)
            ->when(
                $isAdmin,
                fn ($q) => $q->where('c.sub_institute_id', $context['sub_institute_id']),
                fn ($q) => $q->where('c.user_id', $context['user_id'])
            )
            ->whereNull('c.deleted_at')
            ->first([
                'c.*',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ]);

        if (! $certificate) {
            return $this->lmsError('Certificate not found', 404);
        }

        return $this->renderCertificatePdf($certificate);
    }

    /**
     * GET /api/g2g-lms/certifications-records/certificates/verify/{code}
     *
     * Public by design — registered OUTSIDE the api.session/staff.only group
     * in routes/g2g_lms.php, matching the source's unauthenticated verify
     * endpoint. Checking a credential must not require an account.
     */
    public function verify(Request $request, string $code)
    {
        $certificate = DB::table('lms_certificates as c')
            ->leftJoin('tbluser as u', 'u.id', '=', 'c.user_id')
            ->where('c.verification_code', $code)
            ->whereNull('c.deleted_at')
            ->first([
                'c.certificate_number', 'c.name', 'c.course_title', 'c.issued_at',
                'c.expires_at', 'c.status', 'c.superseded_by',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ]);

        if (! $certificate) {
            return response()->json([
                'status' => false,
                'valid' => false,
                'message' => 'No certificate matches that verification code.',
            ], 404);
        }

        $expired = $certificate->expires_at !== null
            && \Carbon\Carbon::parse($certificate->expires_at)->isPast();
        $superseded = $certificate->superseded_by !== null;

        if ($superseded) {
            $message = 'This certificate is genuine but has been superseded by a newer issue.';
        } elseif ($expired) {
            $message = 'This certificate is genuine but expired on '
                . \Carbon\Carbon::parse($certificate->expires_at)->format('d M Y') . '.';
        } else {
            $message = 'This is a valid, current certificate.';
        }

        return response()->json([
            'status' => true,
            'valid' => ! $expired && ! $superseded,
            'message' => $message,
            'data' => [
                'certificate_number' => $certificate->certificate_number,
                'name' => $certificate->name ?? $certificate->course_title,
                'course_title' => $certificate->course_title,
                'learner_name' => $certificate->learner_name,
                'issued_at' => $certificate->issued_at,
                'expires_at' => $certificate->expires_at,
                'is_expired' => $expired,
                'is_superseded' => $certificate->superseded_by !== null,
            ],
        ]);
    }

    /**
     * POST /api/g2g-lms/certifications-records/certificates/{id}/reissue
     *
     * Admin/HR only. Non-destructive: the original is marked superseded and
     * kept; a new row is issued with a fresh number, code and expiry.
     */
    public function reissue(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        if (! $this->isLmsStaffAdmin($context)) {
            return $this->lmsError('You do not have permission to perform this action.', 403);
        }

        $original = DB::table('lms_certificates')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (! $original) {
            return $this->lmsError('Certificate not found', 404);
        }

        if ($original->superseded_by !== null) {
            return $this->lmsError('That certificate has already been re-issued.', 422);
        }

        $course = DB::table('sub_std_map')->where('id', $original->course_id)->first();
        $now = now();

        try {
            $newId = DB::table('lms_certificates')->insertGetId([
                'user_id' => $original->user_id,
                'course_id' => $original->course_id,
                'enrollment_id' => $original->enrollment_id,
                'skill_id' => $original->skill_id,
                'certificate_number' => $original->certificate_number . '-R'
                    . (DB::table('lms_certificates')
                        ->whereNotNull('supersedes')
                        ->where('course_id', $original->course_id)
                        ->where('user_id', $original->user_id)
                        ->count() + 1),
                'verification_code' => $this->makeVerificationCode(),
                'name' => $original->name,
                'description' => $original->description,
                'tags' => $original->tags,
                'course_title' => $original->course_title,
                'issued_at' => $now,
                'expires_at' => ($course && ! empty($course->certificate_validity_months))
                    ? $now->copy()->addMonths((int) $course->certificate_validity_months)
                    : null,
                'status' => 'active',
                'supersedes' => $original->id,
                'reissued_at' => $now,
                'reissued_by' => $context['user_id'],
                'sub_institute_id' => $context['sub_institute_id'],
                'created_by' => $context['user_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('lms_certificates')->where('id', $original->id)->update([
                'superseded_by' => $newId,
                'status' => 'superseded',
                'updated_by' => $context['user_id'],
                'updated_at' => $now,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Certificate re-issued',
                'data' => DB::table('lms_certificates')->find($newId),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to re-issue the certificate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/g2g-lms/certifications-records/certificates
     *
     * Issue the certificate for a completed course. Idempotent: calling
     * again returns the existing certificate. Requires `content_master` and
     * `lms_content_progress` (Package 1/2 tables) to determine completion —
     * guarded, so it fails gracefully rather than 500ing before those land.
     */
    public function issue(Request $request)
    {
        $context = $this->lmsContext($request);
        if (! $context['user_id']) {
            return $this->lmsError('user_id is required', 422);
        }

        $validator = Validator::make($request->all(), ['course_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $context['user_id'];
        $courseId = $request->input('course_id');

        $existing = DB::table('lms_certificates')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => true,
                'message' => 'Certificate already issued',
                'data' => $existing,
            ]);
        }

        $course = DB::table('sub_std_map')->where('id', $courseId)->whereNull('deleted_at')->first();
        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        if (! $this->lmsTableExists('content_master') || ! $this->lmsTableExists('lms_content_progress')) {
            return $this->lmsError(
                'Course content tracking is not available yet — try again once course content is set up.',
                503
            );
        }

        $total = DB::table('content_master')->where('subject_id', $courseId)->whereNull('deleted_at')->count();
        $done = DB::table('lms_content_progress')
            ->where('user_id', $userId)->where('course_id', $courseId)
            ->where('status', 'completed')->whereNull('deleted_at')->count();

        if ($total === 0 || $done < $total) {
            return response()->json([
                'status' => false,
                'message' => 'Finish every lesson in this course before claiming the certificate.',
                'data' => ['total_content' => $total, 'completed_content' => $done],
            ], 422);
        }

        $enrollment = $this->lmsTableExists('lms_course_enroll')
            ? DB::table('lms_course_enroll')
                ->where('user_id', $userId)->where('course_id', $courseId)
                ->whereNull('deleted_at')->orderByDesc('created_at')->first()
            : null;

        try {
            $now = now();

            $certificateId = DB::table('lms_certificates')->insertGetId([
                'user_id' => $userId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollment->id ?? null,
                'skill_id' => $this->resolveCourseSkillId($course),
                'certificate_number' => 'CERT-' . $now->format('Y') . '-'
                    . str_pad((string) $courseId, 5, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
                'verification_code' => $this->makeVerificationCode(),
                'course_title' => $course->display_name,
                'name' => $course->display_name,
                'description' => "Awarded on successful completion of {$course->display_name}.",
                'tags' => json_encode(array_values(array_filter([
                    $course->subject_category ?? null,
                    $course->subject_type ?? null,
                ]))),
                'issued_at' => $now,
                'expires_at' => ! empty($course->certificate_validity_months)
                    ? $now->copy()->addMonths((int) $course->certificate_validity_months)
                    : null,
                'status' => 'active',
                'sub_institute_id' => $context['sub_institute_id'],
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($enrollment && $enrollment->status !== 'completed') {
                DB::table('lms_course_enroll')->where('id', $enrollment->id)->update([
                    'status' => 'completed',
                    'updated_at' => $now,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Certificate issued',
                'data' => DB::table('lms_certificates')->find($certificateId),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to issue the certificate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================== *
     * Transcript / Completion history
     *
     * These back the "Learning Transcript" and "Completion History" tabs on
     * the Certifications & Records screen. Both read tables Packages 1/2
     * own — guarded via lmsTableExists() so a not-yet-landed migration
     * degrades to an empty list, not a 500.
     * ================================================================== */

    /** GET /api/g2g-lms/certifications-records/transcript — completed enrolments. */
    public function transcript(Request $request)
    {
        $context = $this->lmsContext($request);

        if (! $this->lmsTableExists('lms_course_enroll')) {
            return $this->lmsOk([]);
        }

        $rows = DB::table('lms_course_enroll as e')
            ->join('sub_std_map as s', 's.id', '=', 'e.course_id')
            ->where('e.user_id', $context['user_id'])
            ->where('e.status', 'completed')
            ->whereNull('e.deleted_at')
            ->orderByDesc('e.updated_at')
            ->get([
                'e.id as enrollment_id', 's.id', 's.display_name', 's.subject_category',
                'e.start_date', 'e.end_date',
            ]);

        return $this->lmsOk($rows);
    }

    /** GET /api/g2g-lms/certifications-records/completion-history — every enrolled course with progress. */
    public function completionHistory(Request $request)
    {
        $context = $this->lmsContext($request);

        if (! $this->lmsTableExists('lms_course_enroll')) {
            return $this->lmsOk([]);
        }

        $hasProgress = $this->lmsTableExists('content_master') && $this->lmsTableExists('lms_content_progress');

        $rows = DB::table('lms_course_enroll as e')
            ->join('sub_std_map as s', 's.id', '=', 'e.course_id')
            ->where('e.user_id', $context['user_id'])
            ->whereNull('e.deleted_at')
            ->orderByDesc('e.updated_at')
            ->get(['e.id', 's.id as course_id', 's.display_name', 'e.status as enrollment_status', 'e.end_date']);

        $data = $rows->map(function ($row) use ($hasProgress, $context) {
            $total = 0;
            $done = 0;

            if ($hasProgress) {
                $total = DB::table('content_master')->where('subject_id', $row->course_id)->whereNull('deleted_at')->count();
                $done = DB::table('lms_content_progress')
                    ->where('user_id', $context['user_id'])->where('course_id', $row->course_id)
                    ->where('status', 'completed')->whereNull('deleted_at')->count();
            }

            return [
                'id' => $row->id,
                'display_name' => $row->display_name,
                'total_content' => $total,
                'completed_content' => $done,
                'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'enrollment_status' => $row->enrollment_status,
                'end_date' => $row->end_date,
            ];
        });

        return $this->lmsOk($data);
    }

    /* ================================================================== *
     * Helpers
     * ================================================================== */

    /** Ported from hp_erp's private `resolveCourseSkillId`. */
    private function resolveCourseSkillId($course): ?int
    {
        $entityCategories = ['task', 'jobrole', 'course', 'sub'];
        $category = strtolower(trim((string) ($course->subject_category ?? '')));

        if ($category === '' || in_array($category, $entityCategories, true)) {
            return null;
        }

        if (! $course->subject_id) {
            return null;
        }

        $exists = DB::table('s_users_skills')->where('id', $course->subject_id)->whereNull('deleted_at')->exists();

        return $exists ? (int) $course->subject_id : null;
    }

    private function makeVerificationCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(8)));
        } while (DB::table('lms_certificates')->where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * Renders the certificate as a PDF via an inline HTML string (dompdf).
     * No `lms.certificate` Blade view or `config('lms.certificate_templates')`
     * map exists in this repo, so the source's per-template view lookup is
     * replaced with one clean, self-contained layout.
     */
    private function renderCertificatePdf($certificate)
    {
        $tags = [];
        if (! empty($certificate->tags)) {
            $decoded = json_decode($certificate->tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }

        $isExpired = $certificate->expires_at !== null
            && \Carbon\Carbon::parse($certificate->expires_at)->isPast();

        $verifyUrl = $certificate->verification_code
            ? rtrim(config('app.url'), '/') . '/verify/certificate/' . $certificate->verification_code
            : null;

        $title = e($certificate->name ?? $certificate->course_title ?? 'Certificate');
        $learner = e($certificate->learner_name ?? '');
        $number = e($certificate->certificate_number);
        $issued = $certificate->issued_at ? \Carbon\Carbon::parse($certificate->issued_at)->format('d M Y') : '—';
        $expires = $certificate->expires_at ? \Carbon\Carbon::parse($certificate->expires_at)->format('d M Y') : 'Never expires';
        $description = e($certificate->description ?? '');
        $tagsHtml = implode('', array_map(fn ($tag) => '<span style="display:inline-block;margin:2px;padding:3px 10px;border-radius:999px;background:#eef2ff;color:#4f46e5;font-size:11px;">' . e($tag) . '</span>', $tags));
        $statusLine = $isExpired ? '<p style="color:#b91c1c;font-weight:bold;">This certificate has expired.</p>' : '';

        $html = <<<HTML
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: DejaVu Sans, sans-serif; text-align:center; padding:60px;">
    <div style="border:4px solid #4f46e5; padding:50px;">
        <p style="letter-spacing:4px; text-transform:uppercase; color:#64748b; font-size:12px;">Certificate of Completion</p>
        <h1 style="font-size:32px; margin:20px 0;">{$title}</h1>
        <p style="font-size:14px; color:#334155;">This certifies that</p>
        <h2 style="font-size:24px; margin:10px 0;">{$learner}</h2>
        <p style="font-size:14px; color:#334155;">{$description}</p>
        <div style="margin:20px 0;">{$tagsHtml}</div>
        {$statusLine}
        <table style="margin:30px auto; font-size:12px; color:#475569;">
            <tr><td style="padding:4px 12px;">Certificate No.</td><td style="padding:4px 12px; font-family: monospace;">{$number}</td></tr>
            <tr><td style="padding:4px 12px;">Issued on</td><td style="padding:4px 12px;">{$issued}</td></tr>
            <tr><td style="padding:4px 12px;">Valid until</td><td style="padding:4px 12px;">{$expires}</td></tr>
        </table>
HTML;

        if ($verifyUrl) {
            $html .= '<p style="font-size:10px; color:#94a3b8;">Verify at ' . e($verifyUrl) . '</p>';
        }

        $html .= '</div></body></html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');

        $output = $pdf->output();
        $headerAt = strpos($output, '%PDF');
        if ($headerAt !== false && $headerAt > 0) {
            $output = substr($output, $headerAt);
        }

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $certificate->certificate_number . '.pdf"',
            'Content-Length' => (string) strlen($output),
        ]);
    }
}
