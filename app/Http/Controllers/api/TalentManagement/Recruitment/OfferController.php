<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\settings\organizationDetails;
use App\Models\TalentManagement\TalentJobApplication;
use App\Models\TalentManagement\TalentJobPosting;
use App\Models\TalentManagement\TalentOffer;
use App\Models\TalentManagement\TalentOfferTemplate;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\TalentOfferController`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input. `created_by` comes from `session()->get('user_id')`.
 */
class OfferController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * POST talent-offers
     * Ported from TalentOfferController@store.
     *
     * The offer-letter PDF render + DigitalOcean upload + email send block is
     * ported verbatim, but guarded by class_exists()/View::exists() checks
     * around `App\Mail\OfferLetterMail`, the `offer_letter2` Blade view and
     * `App\Support\MailGate` — none of which exist yet in this repo (only the
     * Recruitment feature area's controllers/models/migration were in scope
     * for this port). Without those three files the offer still saves
     * correctly as 'draft' with no exception thrown; once they are added
     * (out of scope here), the exact same ported code path starts sending
     * the email/PDF with no further changes needed. Flagged in the porting
     * report.
     */
    public function store(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $org = organizationDetails::where('sub_institute_id', $subInstituteId)->first();

        $job = TalentJobPosting::find($request->job_id);
        $signerUser = null;
        if ($job && $job->created_by) {
            $signerUser = tbluserModel::find($job->created_by);
        }

        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:talent_job_applications,id',
            'job_id' => 'required|exists:talent_job_postings,id',
            'position' => 'required|string|max:255',
            'salary' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'reportmanager' => 'nullable|string',
            'punchintime' => 'nullable|date_format:H:i',
            'punchouttime' => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        try {
            $offer = new TalentOffer([
                'application_id' => $request->application_id,
                'job_id' => $request->job_id,
                'position' => $request->position,
                'salary' => $request->salary,
                'start_date' => $request->start_date,
                'notes' => $request->notes,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $this->actorId(),
                'reportmanager' => $request->reportmanager,
                'punchintime' => $request->punchintime,
                'punchouttime' => $request->punchouttime,
                'status' => 'draft',
            ]);

            if ($offer->save()) {
                DB::table('talent_offers')->where('id', $offer->id)->update(['updated_at' => null]);

                $existingOffers = TalentOffer::where('application_id', $request->application_id)
                    ->whereNotNull('offer_letter_url')
                    ->where('id', '!=', $offer->id)
                    ->get();
                foreach ($existingOffers as $existingOffer) {
                    $url = $existingOffer->offer_letter_url;
                    $baseUrl = 'https://' . env('DO_SPACES_BUCKET') . '.' . env('DO_SPACES_REGION') . '.digitaloceanspaces.com/';
                    if (str_starts_with($url, $baseUrl)) {
                        $file_path = str_replace($baseUrl, '', $url);
                        try {
                            Storage::disk('digitalocean')->delete($file_path);
                            Log::info('Deleted old offer letter: ' . $file_path);
                            $existingOffer->delete();
                        } catch (\Exception $e) {
                            Log::error('Failed to delete old offer letter: ' . $e->getMessage());
                        }
                    }
                }

                $application = TalentJobApplication::find($offer->application_id);
                $canSendOfferLetter = $application && $application->email
                    && class_exists(\App\Mail\OfferLetterMail::class)
                    && class_exists(\App\Support\MailGate::class)
                    && View::exists('offer_letter2');

                if ($canSendOfferLetter) {
                    $pdfPath = null;

                    $userName = $application->first_name . ' ' . $application->last_name;
                    $todayDate = now()->format('F j, Y');
                    $deadlineDate = $offer->start_date ? \Carbon\Carbon::parse($offer->start_date)->subDays(3)->format('F j, Y') : now()->addDays(7)->format('F j, Y');
                    $signerName = $signerUser ? ($signerUser->first_name . ' ' . ($signerUser->middle_name ? $signerUser->middle_name . ' ' : '') . $signerUser->last_name) : 'Signer Name';

                    $data = [
                        'candidate_name' => $userName,
                        'position' => $offer->position,
                        'start_date' => $offer->start_date ? \Carbon\Carbon::parse($offer->start_date)->format('F d, Y') : null,
                        'salary' => $offer->salary,
                        'deadline' => $deadlineDate,
                        'company_name' => $org->legal_name ?? 'Company Name',
                        'company_address' => $org->registered_address ?? 'Address',
                        'cin' => $org->cin ?? 'CIN',
                        'signer_name' => $signerName,
                        'mobile_no' => $org->mobile_no ?? null,
                        'country_code' => $org->country_code ?? '+91',
                        'email' => $org->email ?? null,
                        'website' => $org->website ?? null,
                    ];

                    $html = view('offer_letter2', $data)->render();

                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                    $fileName = 'offer_letter_' . $offer->id . '_' . str_replace(' ', '_', $userName) . '.pdf';
                    $pdfPath = storage_path('app/public/' . $fileName);
                    $pdf->save($pdfPath);

                    try {
                        $file_path = 'public/offerLetter/' . $fileName;
                        Log::info('Attempting to store offer letter: ' . $file_path);
                        $result = Storage::disk('digitalocean')->put($file_path, file_get_contents($pdfPath), 'public', [
                            'Cache-Control' => 'max-age=0, no-cache, no-store',
                        ]);
                        Log::info('Storage result: ' . ($result ? 'success' : 'failed'));
                    } catch (\Exception $e) {
                        Log::error('Failed to store offer letter in DigitalOcean: ' . $e->getMessage());
                    }

                    if (isset($result) && $result) {
                        $url = 'https://' . env('DO_SPACES_BUCKET') . '.' . env('DO_SPACES_REGION') . '.digitaloceanspaces.com/' . $file_path;
                        $offer->offer_letter_url = $url;
                        $offer->save();
                    }

                    if (!\App\Support\MailGate::allowed()) {
                        return response()->json([
                            'status' => 0, 'message' => \App\Support\MailGate::reason(),
                        ], 503);
                    }

                    Mail::to($application->email)->send(new \App\Mail\OfferLetterMail($offer, $pdfPath));

                    $offer->status = 'sent';
                    $offer->sent_at = now();
                    $offer->save();
                }

                return response()->json([
                    'status' => 1,
                    'message' => 'Talent offer created successfully!',
                    'data' => $offer,
                ], 200);
            }

            return response()->json(['message' => 'Failed to save offer'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * GET offers
     * Ported from TalentOfferController@index.
     */
    public function index(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        if (!$subInstituteId) {
            return response()->json(['message' => 'sub_institute_id not provided'], 400);
        }

        try {
            $offers = TalentOffer::where('sub_institute_id', $subInstituteId)->get();

            return response()->json([
                'status' => 1,
                'message' => 'Offers retrieved successfully!',
                'data' => $offers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * POST talent-offers/{id}/reject
     * Ported from TalentOfferController@reject.
     */
    public function reject(Request $request, $id)
    {
        try {
            $offer = TalentOffer::find($id);

            if (!$offer) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Offer not found',
                ], 404);
            }

            $offer->status = 'rejected';
            DB::table('talent_job_applications')
                ->where('id', $offer->application_id)
                ->update(['status' => 'rejected']);
            $offer->rejected_at = now();
            $offer->save();

            return response()->json([
                'status' => 1,
                'message' => 'Offer rejected successfully!',
                'data' => $offer,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * GET talent-offer-letter/{offerId}
     *
     * NOT present anywhere in hp_erp: `Route::get('talent-offer-letter/{offerId}',
     * [TalentOfferController::class, 'getOfferLetter'])` is bound in hp_erp's
     * routes/api.php, but `TalentOfferController` has no `getOfferLetter`
     * method - a dead route in the source itself. This is a reconstruction,
     * not a port: it returns the offer row (including `offer_letter_url`,
     * the field the store() flow above already populates), tenant-scoped,
     * which is the most direct reasonable interpretation of what this route
     * name implies. Flagged in the porting report as not ported 1:1.
     */
    public function getOfferLetter(Request $request, $offerId)
    {
        $subInstituteId = $this->subInstituteId();

        $offer = TalentOffer::where('id', $offerId)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 0,
                'message' => 'Offer not found',
            ], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Offer letter fetched successfully',
            'data' => $offer,
        ], 200);
    }

    /**
     * GET talent-templates
     *
     * NOT present anywhere in hp_erp: `Route::get('talent-templates',
     * [TalentOfferController::class, 'getTemplates'])` is bound in hp_erp's
     * routes/api.php, but `TalentOfferController` has no `getTemplates`
     * method - a dead route in the source itself, same defect as
     * `getOfferLetter` above. This is a reconstruction: it lists this
     * tenant's `talent_offer_templates` rows, which is the table this exact
     * route name implies. Flagged in the porting report as not ported 1:1.
     */
    public function getTemplates(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $templates = TalentOfferTemplate::where('sub_institute_id', $subInstituteId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => 1,
            'message' => 'Templates fetched successfully',
            'data' => $templates,
        ], 200);
    }
}
