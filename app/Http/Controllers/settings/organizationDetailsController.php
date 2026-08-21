<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\settings\organizationDetails;
use App\Models\settings\organizationSisterDetails;
use App\Models\school_setupModel;
use Illuminate\Support\Facades\Storage;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Institute / Organization Profile - ported as-is from hp_erp
 * (app/Http/Controllers/settings/organizationDetailsController.php) to back
 * the same "org_details" / "org_sister_details" tables in this app. Logic,
 * validation and response shape mirror hp_erp exactly; only the storage disk
 * stays whatever this app already uses for uploads ('digitalocean', same as
 * hp_erp and the rest of this codebase - see config/filesystems.php).
 *
 * Token check uses GetsJwtToken, NOT Laravel Sanctum's PersonalAccessToken
 * (hp_erp's original) - this app's real session tokens are JWTs issued by
 * App\Http\Controllers\api\ApiLoginController, which Sanctum's token lookup
 * never recognizes. Same fix already applied to the ported HRIT Attendance/
 * Leave API (see App\Http\Controllers\api\Attendance\Concerns\
 * ResolvesAttendanceContext for the established precedent).
 */
class organizationDetailsController extends Controller
{
    use GetsJwtToken;

    /** True when $token validates as this app's own JWT. */
    private function isValidJwt(Request $request): bool
    {
        try {
            return (bool) $this->jwtToken($request)->validate();
        } catch (\Exception $e) {
            return false;
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $token = $request->input('token');  // get token from input field 'token'

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        // Find the token in the database
        $accessToken = ($this->isValidJwt($request) ? $token : null);

        if (!$accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
        else{
            $type = $request->input('type');

            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required|numeric',
            ]);

            $sub_institute_id = $request->get('sub_institute_id');

            if ($validator->fails()) {
                $response['status'] = '0';
                $response['message'] = $validator->messages()->first();
                return response()->json($response);
            }

            $res['org_data'] = organizationDetails::with('sistersOrg')->where('sub_institute_id',$request->sub_institute_id)->get();

            return is_mobile($type, "settings/add_institute_detail", $res, "view");
        }

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

public function store(Request $request)
{
    // return $request;exit;
    $token = $request->input('token');

    if (!$token) {
        return response()->json(['message' => 'Token not provided'], 401);
    }

    $accessToken = ($this->isValidJwt($request) ? $token : null);

    if (!$accessToken) {
        return response()->json(['message' => 'Invalid token'], 401);
    }

    $type = $request->input('type');

    $validator = Validator::make($request->all(), [
        'sub_institute_id' => 'required|numeric',
        'formType'         => 'required',
        'email'            => 'nullable|email',
        'website'          => 'nullable|url'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => 0,
            'message' => $validator->messages()->first()->first()
        ]);
    }

    $orgDetail = organizationDetails::updateOrCreate(
        ['sub_institute_id' => $request->sub_institute_id],
        [
            'legal_name'         => $request->legal_name,
            'cin'                => $request->cin,
            'gstin'              => $request->gstin,
            'pan'                => $request->pan,
            'registered_address' => $request->registered_address,
            'industry'           => $request->industry,
            'employee_count'     => $request->employee_count,
            'work_week'          => $request->work_week,
            'mobile_no'          => $request->mobile_no,
            'country_code'       => $request->country_code,
            'email'              => $request->email,
            'website'            => $request->website,
        ]
    );

    // Set created_by / updated_by
    if ($orgDetail->wasRecentlyCreated) {
        $orgDetail->created_by = $request->user_id;
    } else {
        $orgDetail->updated_by = $request->user_id;
    }

    // Handle org logo upload
    if ($request->hasFile('logo')) {
        $file = $request->file('logo');
        $file_name = time() . '_' . $file->getClientOriginalName();

        Storage::disk('digitalocean')->putFileAs(
            'public/hp_logo/',
            $file,
            $file_name,
            'public'
        );
        $fileUrl = Storage::disk('digitalocean')->url('public/hp_logo/' . $file_name);

        // Save to DB
        $orgDetail->logo = $file_name;
        // Update school_setup table
        school_setupModel::where('Id', $request->sub_institute_id)->update(['Logo' => $file_name]);
        // $orgDetail->logo = $file_name; // assuming you have a logo column
    }

    $orgDetail->save();

    // Handle sister companies
    if ($request->has('sister_companies') && isset($orgDetail->id)) {
        organizationSisterDetails::where('org_id', $orgDetail->id)
            ->where('sub_institute_id', $request->sub_institute_id)
            ->delete();

        foreach ($request->sister_companies as $index => $sisterCompany) {
            $sister = new organizationSisterDetails([
                'org_id'            => $orgDetail->id,
                'sub_institute_id'  => $request->sub_institute_id,
                'legal_name'        => $sisterCompany['legal_name'],
                'cin'               => $sisterCompany['cin'],
                'gstin'             => $sisterCompany['gstin'] ?? null,
                'pan'               => $sisterCompany['pan'],
                'registered_address'=> $sisterCompany['registered_address'],
                'industry'          => $sisterCompany['industry'],
                'employee_count'    => $sisterCompany['employee_count'],
                'work_week'         => $sisterCompany['work_week'],
                'mobile_no'         => $sisterCompany['mobile_no'] ?? null,
                'country_code'      => $sisterCompany['country_code'] ?? '+91',
                'email'             => $sisterCompany['email'] ?? null,
                'website'           => $sisterCompany['website'] ?? null,
            ]);

            // Sister company logo upload
            if (
                isset($sisterCompany['logo']) &&
                $request->file("sister_companies.$index.logo")
            ) {
                $file = $request->file("sister_companies.$index.logo");
                $file_name = time() . '_' . $file->getClientOriginalName();

                Storage::disk('digitalocean')->putFileAs(
                    'public/hp_logo/',
                    $file,
                    $file_name,
                    'public'
                );

                $fileUrl = Storage::disk('digitalocean')->url('public/hp_logo/' . $file_name);

                // Save to DB
                $sister->logo = $file_name;// assuming you have a logo column
            }

            $sister->save();
        }
    }

    $res = [
        'status_code' => $orgDetail ? 1 : 0,
        'message'     => $orgDetail ? "Data added successfully" : "Failed to add Data"
    ];

    return is_mobile($type, "settings/add_institute_detail", $res, "view");
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
