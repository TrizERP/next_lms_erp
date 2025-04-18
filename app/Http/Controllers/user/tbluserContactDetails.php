<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class tbluserContactDetails extends Controller
{
    public function store(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = $request->empId;
        $insertData = [
            'country' => $request->input('country') ?? null,
            'street1' => $request->input('street1') ?? null,
            'street2' => $request->input('street2') ?? null,
            'city' => $request->input('city') ?? null,
            'state' => $request->input('state') ?? null,
            'zipCode' => $request->input('zipCode') ?? null,
            'homeTelephone' => $request->input('homeTelephone') ?? null,
            'mobile' => $request->input('mobile') ?? null,
            'workTelephone' => $request->input('workTelephone') ?? null,
            'workEmail' => $request->input('workEmail') ?? null,
            'otherEmail' => $request->input('otherEmail') ?? null,
            'other_status' => $request->input('other_status') ?? null,
            'country_other' => $request->input('country_other') ?? null,
            'street1_other' => $request->input('street1_other') ?? null,
            'street2_other' => $request->input('street2_other') ?? null,
            'city_other' => $request->input('city_other') ?? null,
            'state_other' => $request->input('state_other') ?? null,
            'zipCode_other' => $request->input('zipCode_other') ?? null,
        ];

        $existingRecord = DB::table('tbluser_contact_details')
            ->where('user_id', $user_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if ($existingRecord) {
            $insertData['updated_at'] = now();
            $insertData['user_id'] = $user_id;
            $insertData['sub_institute_id'] = $sub_institute_id;
            \DB::table('tbluser_contact_details')
            ->where('id', $existingRecord->id)
            ->update($insertData);
        } else {
            $insertData['created_at'] = now();
            $insertData['user_id'] = $user_id;
            $insertData['sub_institute_id'] = $sub_institute_id;
            \DB::table('tbluser_contact_details')->insert($insertData);
        }

        return redirect()->back()->with('success', 'Contact details saved successfully.');
    }
}
