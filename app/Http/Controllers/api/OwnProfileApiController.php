<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only mobile endpoint; legacy controllers remain unchanged. */
class OwnProfileApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('user_id');
        $instituteId = (int) $request->session()->get('sub_institute_id');
        $isStudent = $request->session()->get('user_profile_name') === 'Student';

        $profile = $isStudent
            ? DB::table('tblstudent')->selectRaw('id, first_name, middle_name, last_name, email, mobile, dob as birthdate, admission_year as join_year, address, image, user_profile_id')->where('id', $userId)->where('sub_institute_id', $instituteId)->where('status', 1)->first()
            : DB::table('tbluser')->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'mobile', 'birthdate', 'join_year', 'address', 'image', 'user_profile_id')->where('id', $userId)->where('sub_institute_id', $instituteId)->where('status', 1)->first();

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.', 'data' => null], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile fetched successfully.',
            'data' => [
                'id' => $profile->id,
                'full_name' => trim(implode(' ', array_filter([$profile->first_name, $profile->middle_name, $profile->last_name]))),
                'email' => $profile->email,
                'mobile' => $profile->mobile,
                'birthdate' => $profile->birthdate,
                'join_year' => $profile->join_year,
                'address' => $profile->address,
                'image' => $profile->image,
                'user_profile' => DB::table('tbluserprofilemaster')->where('id', $profile->user_profile_id)->value('name'),
                'school_name' => DB::table('school_setup')->where('id', $instituteId)->value('SchoolName'),
            ],
        ]);
    }
}
