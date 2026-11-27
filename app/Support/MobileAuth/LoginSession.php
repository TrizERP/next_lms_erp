<?php

namespace App\Support\MobileAuth;

use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\SchoolModel;
use App\Models\school_setup\subjectModel;
use App\Models\user\tbluserprofilemasterModel;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in session for a profile: the same response api/api-login returns, built for an
 * already-authenticated profile (the mobile + mPIN flow proves the person another way).
 *
 * The body is a verbatim copy of ApiLoginController::login() from the profile lookup onward, so the web
 * login is left alone. If login() changes (rights, subjects, token payload), copy the change here too.
 */
class LoginSession
{
    /**
     * @param  array<string,mixed>  $user  the columns api-login selects for a staff or student row
     */
    public static function build(array $user, bool $isStudent, bool $isStaff, JwtToken $jwt): JsonResponse
    {
        $profileData  = tbluserprofilemasterModel::where('id', $user['user_profile_id'])->get()->toArray();
        $userProfile  = $profileData[0]['name'] ?? '';
        $profileParentId = $profileData[0]['parent_id'] ?? null;
        $rightsMenusIds = 0;

        if ($user['plain_password'] == 'student' || $user['plain_password'] == 'Student' || $user['plain_password'] == 'STUDENT') {
            $rightsMenusIds = DB::table('tblstudent as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->on('u.id', '=', 'i.user_id')->on('u.sub_institute_id', '=', 'i.sub_institute_id');
                })
                ->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->on('u.user_profile_id', '=', 'g.profile_id')->on('u.sub_institute_id', '=', 'g.sub_institute_id');
                })
                ->join('tblmenumaster as m', function ($join) use ($user) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $user['sub_institute_id'] . ", m.sub_institute_id)");
                })
                ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                ->whereIn('u.sub_institute_id', explode(',', $user['sub_institute_id']))
                ->where('u.id', $user['id'])
                ->value('MID');
        } elseif ((int) $user['id'] === 1002) {
            $rightsMenusIds = DB::table('tbluser as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->on('u.id', '=', 'i.user_id')->on('u.sub_institute_id', '=', 'i.sub_institute_id');
                })
                ->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->on('u.user_profile_id', '=', 'g.profile_id')->on('u.sub_institute_id', '=', 'g.sub_institute_id');
                })
                ->join('tblmenumaster as m', function ($join) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id)");
                })
                ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                ->where('u.status', 1)
                ->where('u.id', $user['id'])
                ->value('MID');
        } elseif ((int) $user['sub_institute_id'] === 0 && ! empty($user['client_id']) && (int) $user['is_admin'] === 1) {
            $rightsMenusIds = DB::table('tbluser as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->on('u.id', '=', 'i.user_id')->on('u.sub_institute_id', '=', 'i.sub_institute_id');
                })
                ->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->on('u.user_profile_id', '=', 'g.profile_id')->on('u.sub_institute_id', '=', 'g.sub_institute_id');
                })
                ->join('tblmenumaster as m', function ($join) use ($user) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $user['client_id'] . ", m.client_id)");
                })
                ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                ->whereIn('u.sub_institute_id', explode(',', $user['sub_institute_id']))
                ->where('u.status', 1)
                ->where('u.id', $user['id'])
                ->value('MID');
        } else {
            $rightsMenusIds = DB::table('tbluser as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->on('u.id', '=', 'i.user_id')->on('u.sub_institute_id', '=', 'i.sub_institute_id');
                })
                ->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->on('u.user_profile_id', '=', 'g.profile_id')->on('u.sub_institute_id', '=', 'g.sub_institute_id');
                })
                ->join('tblmenumaster as m', function ($join) use ($user) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $user['sub_institute_id'] . ", m.sub_institute_id)");
                })
                ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
                ->whereIn('u.sub_institute_id', explode(',', $user['sub_institute_id']))
                ->where('u.status', 1)
                ->where('u.id', $user['id'])
                ->value('MID');
        }

        if ((int) $rightsMenusIds === 0) {
            return response()->json([
                'status'  => 0,
                'message' => 'Please Contact Administrator For ERP Rights',
            ], 403);
        }

        $shortCode      = '';
        $schoolName     = '';
        $logo           = '';
        $instituteType  = '';
        $multiSchool    = null;
        $givenHrmsRights = 0;
        $getAcademicTerms = [];
        $getAcademicYear  = [];

        if ((int) $user['is_admin'] === 1 || (int) $user['is_admin'] === 2) {
            if ((int) $user['is_admin'] === 2) {
                $schoolData = DB::table('tblclient')->get()->toArray();
            } else {
                $schoolData = DB::table('tblclient')->where('id', $user['client_id'])->get()->toArray();
            }

            $schoolData = json_decode(json_encode($schoolData), true);
            $shortCode  = $schoolData[0]['short_code'] ?? '';
            $schoolName = $schoolData[0]['client_name'] ?? '';
            $logo       = $schoolData[0]['logo'] ?? '';

            if ((int) $user['is_admin'] === 2) {
                $getMultiInst = DB::table('tblclient')->get()->toArray();
            } else {
                $getMultiInst = DB::table('tblclient')->where('id', $user['client_id'])->get()->toArray();
            }

            $multiSchool = $getMultiInst[0]->multischool ?? null;

            if ((int) $user['is_admin'] === 2) {
                $schools = SchoolModel::whereIn('client_id', [2, 11, 20, 34, 81])->get()->toArray();
            } else {
                $schools = SchoolModel::where('client_id', $user['client_id'])->get()->toArray();
            }

            $clientSubInstituteId = $schools[0]['Id'] ?? null;
            $syear = $schools[0]['syear'] ?? null;

            if ((int) $user['is_admin'] === 2) {
                $getTermId = academic_yearModel::whereIn('sub_institute_id', [254, 195, 47, 72, 1])
                    ->where('syear', $syear)
                    ->get()
                    ->toArray();
            } else {
                $getTermId = academic_yearModel::where('sub_institute_id', $clientSubInstituteId)
                    ->whereRaw('"'.date('Y-m-d').'" BETWEEN start_date AND end_date')
                    ->get()
                    ->toArray();

                if (empty($getTermId)) {
                    return response()->json([
                        'status'  => 0,
                        'message' => 'Academic Term Date Expired',
                    ], 422);
                }

                $syear = $getTermId[0]['syear'];
            }

            if ((int) $user['is_admin'] === 2) {
                $getInstitutes = DB::table('school_setup as ss')
                    ->whereIn('id', [254, 195, 47, 72, 1])
                    ->get()
                    ->toArray();
            } else {
                $getInstitutes = DB::table('school_setup')
                    ->where('client_id', $user['client_id'])
                    ->get()
                    ->toArray();
            }

            if (! empty($getTermId)) {
                $termId = $getTermId[0]['term_id'];

                $getAcademicTerms = DB::table('academic_year')
                    ->where('sub_institute_id', $clientSubInstituteId)
                    ->where('syear', $syear)
                    ->orderBy('sort_order')
                    ->get()
                    ->toArray();

                $getAcademicYear = DB::table('academic_year')
                    ->where('sub_institute_id', $clientSubInstituteId)
                    ->groupBy('syear')
                    ->get()
                    ->toArray();
            }
        } else {
            $schoolData = SchoolModel::where('id', $user['sub_institute_id'])->get()->toArray();
            $shortCode  = $schoolData[0]['ShortCode'] ?? '';
            $schoolName = $schoolData[0]['SchoolName'] ?? '';
            $logo       = $schoolData[0]['Logo'] ?? '';
            $instituteType = $schoolData[0]['institute_type'] ?? '';

            if (! empty($schoolData[0]['client_id'])) {
                $getMultiInst = DB::table('tblclient')->where('id', $schoolData[0]['client_id'])->get()->toArray();
                $multiSchool  = $getMultiInst[0]->multischool ?? null;
            }

            $getTermId = academic_yearModel::where('sub_institute_id', $user['sub_institute_id'])
                ->whereRaw('"'.date('Y-m-d').'" BETWEEN start_date AND end_date')
                ->get()
                ->toArray();

            if (empty($getTermId)) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'Academic Term Date Expired',
                ], 422);
            }

            $userGroupId = DB::table('tbluserprofilemaster')
                ->where('NAME', 'Teacher')
                ->where('sub_institute_id', $user['sub_institute_id'])
                ->value('id');

            if ((int) $userGroupId === (int) $user['user_profile_id']) {
                $classTeacher = DB::table('class_teacher')
                    ->where('teacher_id', $user['id'])
                    ->where('sub_institute_id', $user['sub_institute_id'])
                    ->where('syear', $getTermId[0]['syear'])
                    ->get()
                    ->toArray();
            }

            $hrmsRights = DB::table('school_setup as s')
                ->join('tblclient as c', 'c.id', '=', 's.client_id')
                ->selectRaw('IF(db_hrms IS NULL, 0, 1) as rights')
                ->where('s.Id', $user['sub_institute_id'])
                ->value('rights');

            $givenHrmsRights = (int) ($hrmsRights ?? 0);

            $getAcademicTerms = DB::table('academic_year')
                ->where('sub_institute_id', $user['sub_institute_id'])
                ->where('syear', $getTermId[0]['syear'])
                ->orderBy('sort_order')
                ->get()
                ->toArray();

            $getAcademicYear = DB::table('academic_year')
                ->where('sub_institute_id', $user['sub_institute_id'])
                ->groupBy('syear')
                ->get()
                ->toArray();
        }

        $subjects = [];
        $studentAcademic = [];
        $schoolSyear = DB::table('school_setup')
            ->where('Id', $user['sub_institute_id'])
            ->value('syear');

        $studentSubjectIds = DB::table('student_optional_subject')
            ->where('student_id', $user['id'])
            ->where('sub_institute_id', $user['sub_institute_id'])
            ->pluck('subject_id')
            ->toArray();

        if ($user['plain_password'] == 'student' || $user['plain_password'] == 'Student' || $user['plain_password'] == 'STUDENT') {
            $currentSyear = null;

            if (! empty($getTermId) && isset($getTermId[0]['syear'])) {
                $currentSyear = $getTermId[0]['syear'];
            }

            $enrollmentQuery = DB::table('tblstudent_enrollment as se')
                ->join('standard as s', function ($join) {
                    $join->on('s.id', '=', 'se.standard_id')
                        ->on('s.sub_institute_id', '=', 'se.sub_institute_id');
                })
                ->join('division as d', function ($join) {
                    $join->on('d.id', '=', 'se.section_id')
                        ->on('d.sub_institute_id', '=', 'se.sub_institute_id');
                })
                ->join('academic_section as g', 'g.id', '=', 'se.grade_id')
                ->where('se.student_id', $user['id'])
                ->where('se.sub_institute_id', $user['sub_institute_id'])
                ->whereNull('se.end_date');

            if ($currentSyear) {
                $enrollmentQuery->where('se.syear', $currentSyear);
            }

            $enrollmentQuery->select(
                'se.syear',
                'se.standard_id',
                's.name as standard_name',
                's.short_name as standard_short_name',
                'se.section_id',
                'd.name as section_name',
                'se.grade_id',
                'g.title as grade_title',
                'g.short_name as grade_short_name'
            );

            $studentAcademic = $enrollmentQuery->get()->toArray();

            if (! empty($studentSubjectIds)) {
                $subjects = subjectModel::select('id', 'subject_code', 'subject_name', 'short_name', 'subject_type', 'sub_institute_id', 'status')
                    ->whereIn('id', $studentSubjectIds)
                    ->where('sub_institute_id', $user['sub_institute_id'])
                    ->where('status', '1')
                    ->get()
                    ->groupBy('sub_institute_id')
                    ->map(function ($group) {
                        return $group->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'subject_code' => $item->subject_code,
                                'subject_name' => $item->subject_name,
                                'short_name' => $item->short_name,
                                'subject_type' => $item->subject_type,
                                'status' => $item->status,
                            ];
                        })->values();
                    })
                    ->toArray();
            }
        } else {
            $subjectQuery = subjectModel::select('id', 'subject_code', 'subject_name', 'short_name', 'subject_type', 'sub_institute_id', 'status')
                ->where('status', '1');

            if ((int) $user['is_admin'] === 2) {
                $subjectQuery->whereIn('sub_institute_id', [254, 195, 47, 72, 1]);
            } elseif ((int) $user['is_admin'] === 1 && ! empty($user['client_id'])) {
                $instituteIds = SchoolModel::where('client_id', $user['client_id'])->pluck('id')->toArray();
                $subjectQuery->whereIn('sub_institute_id', $instituteIds);
            } else {
                $subjectQuery->where('sub_institute_id', $user['sub_institute_id']);
            }

            $subjects = $subjectQuery->get()
                ->groupBy('sub_institute_id')
                ->map(function ($group) {
                    return $group->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'subject_code' => $item->subject_code,
                            'subject_name' => $item->subject_name,
                            'short_name' => $item->short_name,
                            'subject_type' => $item->subject_type,
                            'status' => $item->status,
                        ];
                    })->values();
                })
->toArray();
        }

        $payload = [
            'id'              => $user['id'],
            'sub_institute_id' => $user['sub_institute_id'],
            'is_admin'        => $user['is_admin'],
            'client_id'       => $user['client_id'],
            'user_profile_id' => $user['user_profile_id'],
            'is_student'      => $isStudent,
        ];

        // Tokens carried no expiry, so a leaked one worked forever. Set JWT_TTL_MINUTES
        // to change the lifetime (default 30 days); 0 disables expiry and is not recommended.
        $ttlMinutes = (int) env('JWT_TTL_MINUTES', 43200);
        if ($ttlMinutes > 0) {
            $payload['iat'] = time();
            $payload['exp'] = time() + ($ttlMinutes * 60);
        }

        $userToken = $jwt->createToken($payload);

        $apiLoginUrl = url('/api/api-login');
        return response()->json([
            'status'         => 1,
            'message'        => 'User Successfully Login',
            'api_login_url'  => $apiLoginUrl,
            'data'           => [
                'id'               => $user['id'],
                'user_name'        => $user['user_name'],
                'first_name'       => $user['first_name'],
                'middle_name'      => $user['middle_name'],
                'last_name'        => $user['last_name'],
                'email'            => $user['email'],
                'mobile'           => $user['mobile'],
                'gender'           => $user['gender'],
                'birthdate'        => $user['birthdate'],
                'address'          => $user['address'],
                'user_profile'     => $userProfile,
                'user_profile_id'  => $user['user_profile_id'],
                'profile_parent_id'=> $profileParentId,
                'sub_institute_id' => $user['sub_institute_id'],
                'mobile_syear'            => $schoolSyear,
                'client_id'        => $user['client_id'],
                'is_admin'         => $user['is_admin'],
                'join_year'        => $user['join_year'],
                'image'            => $user['image'],
                'last_login'       => $user['last_login'],
                'short_code'       => $shortCode,
                'school_name'      => $schoolName,
                'logo'             => $logo,
                'institute_type'   => $instituteType,
                'multi_school'     => $multiSchool,
                'hrms_rights'      => $givenHrmsRights,
                'user_token'       => $userToken,
                'host_name'        => env('APP_URL'),
                'erp_rights'       => $rightsMenusIds,
            ],
            'academicTerms'  => $getAcademicTerms,
            'academicYears'  => $getAcademicYear,
            'student_academic' => $studentAcademic,
            'subjects'       => $subjects,
        ], 200);
    }
}
