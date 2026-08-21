<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stateless aggregate for the Next.js Admissions dashboard.
 *
 * Modeled directly on FeesDashboardApiController: accepts tenant/year in the
 * request body (no browser session required) so it can live on Laravel's
 * plain `api` middleware group.
 *
 * The admissions pipeline spans three legacy tables with inconsistent keys:
 *   admission_enquiry      — the enquiry itself (has syear, sub_institute_id)
 *   admission_form         — application step; enquiry_id stores the
 *                             enquiry's `enquiry_no` (string), no syear column
 *   admission_registration — confirmation step; enquiry_id is the enquiry's
 *                             numeric `id` (bigint FK), no syear column
 * Both are scoped to the requested tenant/year via a join back to
 * admission_enquiry rather than trusting their own (partial) columns.
 *
 * Status values on admission_form.status / admission_registration.status /
 * admission_registration.admission_status are free-text and tenant-specific
 * (never enumerated in a migration), so — same defensive approach as
 * FeesDashboardApiController's payment_mode_mix — this groups by whatever
 * raw value each tenant actually has rather than assuming an enum.
 */
class AdmissionsDashboardApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_institute_id' => ['required'],
            'syear' => ['required'],
        ]);

        $subInstituteId = (string) $validated['sub_institute_id'];
        $syear = (string) $validated['syear'];

        $enquiries = DB::table('admission_enquiry')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereNull('deleted_at');

        $totalEnquiries = (int) (clone $enquiries)->count();

        $totalApplications = (int) DB::table('admission_form as af')
            ->join('admission_enquiry as ae', function ($join) {
                $join->on('ae.enquiry_no', '=', 'af.enquiry_id');
            })
            ->where('ae.sub_institute_id', $subInstituteId)
            ->where('ae.syear', $syear)
            ->whereNull('ae.deleted_at')
            ->count();

        $totalRegistrations = (int) DB::table('admission_registration as ar')
            ->join('admission_enquiry as ae', 'ae.id', '=', 'ar.enquiry_id')
            ->where('ae.sub_institute_id', $subInstituteId)
            ->where('ae.syear', $syear)
            ->whereNull('ae.deleted_at')
            ->count();

        $registrationsByStatus = DB::table('admission_registration as ar')
            ->join('admission_enquiry as ae', 'ae.id', '=', 'ar.enquiry_id')
            ->where('ae.sub_institute_id', $subInstituteId)
            ->where('ae.syear', $syear)
            ->whereNull('ae.deleted_at')
            ->selectRaw("COALESCE(NULLIF(TRIM(ar.admission_status), ''), 'Unspecified') as status, COUNT(*) as total")
            ->groupBy('ar.admission_status')
            ->get();

        $enquiriesByStandard = (clone $enquiries)
            ->selectRaw('admission_standard, COUNT(*) as total')
            ->whereNotNull('admission_standard')
            ->groupBy('admission_standard')
            ->orderBy('admission_standard')
            ->get();

        $recentEnquiries = (clone $enquiries)
            ->selectRaw("id, enquiry_no, CONCAT_WS(' ', first_name, middle_name, last_name) as student_name, admission_standard, source_of_enquiry, created_on")
            ->orderByDesc('created_on')
            ->limit(8)
            ->get();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'context' => [
                'sub_institute_id' => (int) $subInstituteId,
                'syear' => (int) $syear,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => [
                'total_enquiries' => $totalEnquiries,
                'total_applications' => $totalApplications,
                'total_registrations' => $totalRegistrations,
                'conversion_rate' => $totalEnquiries > 0 ? round(($totalRegistrations / $totalEnquiries) * 100, 1) : 0,
            ],
            'registrations_by_status' => $registrationsByStatus,
            'enquiries_by_standard' => $enquiriesByStandard,
            'recent_enquiries' => $recentEnquiries,
        ]);
    }
}
