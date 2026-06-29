<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\loginModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\SchoolModel;
use App\Models\school_setup\subjectModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;
use App\Models\tourModel;
use App\Models\user\tbluserprofilemasterModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class ApiLoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
            'type'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => 'Parameter Missing',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $email    = $request->input('email');
        $password = $request->input('password');
        $type     = $request->input('type', 'API');

       $staffQuery = loginModel::select(
    DB::raw('id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(last_name,"-")) as user_name,password,name_suffix,first_name,middle_name,last_name,email,mobile,gender,
        birthdate,address,city,state,pincode,user_profile_id,join_year,image,plain_password,
        sub_institute_id,client_id,is_admin,status,created_on as last_login,expire_date')
)->where(['email' => $email, 'status' => '1']);

$staffData = $staffQuery->first();
$isStaff = $isStudent= false;
if ($staffData) {
    $isStaff = true;
    if ($staffData->password != $password) {
        return response()->json([
            'status'  => 0,
            'message' => 'Invalid User Id And Password',
        ], 401);
    }

    $user = $staffData->toArray();

} else {

    $studentQuery = tblstudentModel::select(
        DB::raw('id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(last_name,"-")) as user_name,password,"" as name_suffix,first_name,middle_name,last_name,email,
            mobile,gender,dob as birthdate,address,city,state,pincode,user_profile_id,
            admission_year as join_year,image,"student" as plain_password,
            sub_institute_id,"" as client_id,"" as is_admin,status,created_on as last_login,expire_date')
    )->where(['email' => $email, 'status' => '1']);   
    

    $studentData = $studentQuery->first();

    if (! $studentData) {
        return response()->json([
            'status'  => 0,
            'message' => 'Invalid User Id And Password',
        ], 401);
    }

    if ($studentData->password != md5($password)) {
        return response()->json([
            'status'  => 0,
            'message' => 'Invalid User Id And Password',
        ], 401);
    }
    $isStudent = true;

    $user = $studentData->toArray();
}
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
        $tokenUser = tbluserModel::find($user['id']) ?? tblstudentModel::find($user['id']);
        $userToken = $tokenUser->createToken('api-token')->plainTextToken;

        $checkMultiLogin = DB::table('general_data')
            ->where(['fieldname' => 'multi_login', 'sub_institute_id' => $user['sub_institute_id']])
            ->value('fieldvalue');

        if ($checkMultiLogin === 'No') {
            DB::table('tbluser')
                ->where(['sub_institute_id' => $user['sub_institute_id'], 'id' => $user['id']])
                ->update(['login_ip' => $userToken]);
        }

        $existingToken = PersonalAccessToken::where('tokenable_type', loginModel::class)
            ->where('tokenable_id', $user['id'])
            ->where('name', 'api-login')
            ->first();

        if (! $existingToken && ($isStaff || ($user['plain_password'] != 'student'))) {
            $tokenModel = loginModel::find($user['id']);
            $tokenUser = tbluserModel::find($user['id']) ?? tblstudentModel::find($user['id']);
            $userToken = $tokenUser->createToken('api-token')->plainTextToken;
            if ($tokenModel) {
                $tokenModel->tokens()->create([
                    'name'      => 'api-login',
                    'token'     => hash('sha256', $userToken),
                    'abilities' => ['*'],
                ]);
            }
        }

        if (! $existingToken && $isStudent) {
            $tokenModel = tblstudentModel::find($user['id']);
            $tokenUser = tbluserModel::find($user['id']) ?? tblstudentModel::find($user['id']);
            $userToken = $tokenUser->createToken('api-token')->plainTextToken;
            if ($tokenModel) {
                $tokenModel->tokens()->create([
                    'name'      => 'api-login',
                    'token'     =>$userToken,
                    'abilities' => ['*'],
                ]);
            }
        }

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

    public function credentials(Request $request)
    {
        $user = $request->user();

        if (! $user || (int) ($user->is_admin ?? 0) < 1) {
            return response()->json([
                'status'  => 0,
                'message' => 'Unauthorized. Admin access only.',
            ], 403);
        }

        $staff = loginModel::select('id', 'user_name', 'email', 'mobile', 'user_profile_id',
            'sub_institute_id', 'client_id', 'is_admin', 'status', 'created_on', 'join_year')
            ->where('status', '1')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_name' => $item->user_name,
                    'email' => $item->email,
                    'mobile' => $item->mobile,
                    'type' => 'Staff',
                    'user_profile_id' => $item->user_profile_id,
                    'sub_institute_id' => $item->sub_institute_id,
                    'client_id' => $item->client_id,
                    'is_admin' => $item->is_admin,
                    'status' => $item->status,
                    'created_on' => $item->created_on,
                    'join_year' => $item->join_year,
                ];
            });

        $students = tblstudentModel::select('id', 'username as user_name', 'email', 'mobile',
            'user_profile_id', 'sub_institute_id', 'status', 'created_on', 'admission_year as join_year')
            ->where('status', '1')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_name' => $item->user_name,
                    'email' => $item->email,
                    'mobile' => $item->mobile,
                    'type' => 'Student',
                    'user_profile_id' => $item->user_profile_id,
                    'sub_institute_id' => $item->sub_institute_id,
                    'client_id' => null,
                    'is_admin' => null,
                    'status' => $item->status,
                    'created_on' => $item->created_on,
                    'join_year' => $item->join_year,
                ];
            });

        $all = $staff->merge($students)->sortBy('type')->values();

        $profileIds = $all->pluck('user_profile_id')->filter()->unique()->values()->all();
        $instituteIds = $all->pluck('sub_institute_id')->filter()->unique()->values()->all();

        $profiles = [];
        if (! empty($profileIds)) {
            $profiles = tbluserprofilemasterModel::whereIn('id', $profileIds)
                ->get(['id', 'name', 'parent_id'])
                ->mapWithKeys(function ($item) {
                    return [$item->id => ['name' => $item->name, 'parent_id' => $item->parent_id]];
                })
                ->toArray();
        }

        $institutes = [];
        if (! empty($instituteIds)) {
            $institutes = DB::table('school_setup')
                ->whereIn('id', $instituteIds)
                ->get(['id', 'SchoolName', 'ShortCode', 'client_id'])
                ->mapWithKeys(function ($item) {
                    return [$item->id => ['name' => $item->SchoolName, 'short_code' => $item->ShortCode, 'client_id' => $item->client_id]];
                })
                ->toArray();
        }

        $clients = [];
        $clientIds = array_filter(array_unique(array_merge(
            array_column($institutes, 'client_id'),
            $all->where('type', 'Staff')->pluck('client_id')->filter()->unique()->values()->all()
        )));
        if (! empty($clientIds)) {
            $clients = DB::table('tblclient')
                ->whereIn('id', $clientIds)
                ->get(['id', 'client_name', 'short_code'])
                ->mapWithKeys(function ($item) {
                    return [$item->id => ['name' => $item->client_name, 'short_code' => $item->short_code]];
                })
                ->toArray();
        }

        $data = $all->map(function ($item) use ($profiles, $institutes, $clients) {
            $profile = $profiles[$item['user_profile_id']] ?? [];
            $institute = $institutes[$item['sub_institute_id']] ?? [];
            $client = ! empty($item['client_id']) ? ($clients[$item['client_id']] ?? []) : [];

            return [
                'id' => $item['id'],
                'user_name' => $item['user_name'],
                'email' => $item['email'],
                'mobile' => $item['mobile'],
                'type' => $item['type'],
                'profile_name' => $profile['name'] ?? null,
                'profile_parent_id' => $profile['parent_id'] ?? null,
                'institute_name' => $institute['name'] ?? null,
                'institute_short_code' => $institute['short_code'] ?? null,
                'client_name' => $client['name'] ?? null,
                'client_short_code' => $client['short_code'] ?? null,
                'is_admin' => $item['is_admin'],
                'status' => $item['status'],
                'created_on' => $item['created_on'],
                'join_year' => $item['join_year'],
            ];
        })->values();

        return response()->json([
            'status'  => 1,
            'message' => 'Credentials fetched successfully',
            'total'   => $data->count(),
            'data'    => $data,
        ], 200);
    }
}
