<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class userReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $tblcustom_fields = $this->customFields($request);

        $tblProfiles = DB::table("tbluserprofilemaster")
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->orderBy('sort_order', 'asc')
            ->pluck("name", "id");

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $tblcustom_fields;
        $res['profiles'] = $tblProfiles;

        return is_mobile($type, "user/show_user_report", $res, "view");
    }

    public function customFields(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $tblcustoms = DB::table("tblcustom_fields")
        ->whereRaw("status=1 AND (common_to_all= 1 or sub_institute_id=$sub_institute_id) AND is_deleted != 'Y'")
        ->where('user_type','staff')
        ->orderByRaw('tab_sort_order,sort_order')
        ->get()->toArray();    
        
        $headerType =[];
        foreach ($tblcustoms as $key => $value) {
            $headerType[$value->column_header][]=$value;
        }
        return $headerType;

    }

    public function searchUser(Request $request)
    {
        $profile = $request->input("profile");
        $status = $request->input("status");
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $tblProfiles = DB::table("tbluserprofilemaster")
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->orderBy('sort_order', 'asc')
            ->pluck("name", "id");

        $header = $array =[];
        $searchArr = ['_'];
        $replaceArr = [' '];
        if ($request->input('dynamicFields') == '') {
            $res['status_code'] = 0;
            $res['message'] = "Please select one checkbox atlease to view report";

            return is_mobile($type, "user_report.index", $res);
        }
        foreach ($request->input('dynamicFields') as $key => $fieldValue) {
            $seprateVal = explode('/',$fieldValue);
            $value = $seprateVal[0];
            $fieldId = $seprateVal[1];
            $value1 = str_replace($searchArr, $replaceArr, $value);
            if($value=="user_name"){
                $array[] = 'CONCAT_WS(" ", tbluser.first_name, tbluser.middle_name, tbluser.last_name) AS user_name';
                $header[$value] = ucfirst($value1);
            }else{
                $customDetails = DB::table("tblcustom_fields")
                ->whereRaw("status=1 AND (common_to_all= 1 or sub_institute_id=$sub_institute_id) AND is_deleted != 'Y'")
                ->where('id',$fieldId)
                ->where('user_type','staff')
                ->first();
                if(!empty($customDetails) && !in_array($value,["user_name"])){
                    $array[] = $customDetails->table_name.".".$value." as ".str_ireplace(" ","_",$customDetails->field_label);
                    $makeKey = strtolower(str_replace(" ","_",$customDetails->field_label));
                    $header[$makeKey] = ucfirst(str_replace(['_'], [' '], str_replace($searchArr, $replaceArr, $customDetails->field_label)));
                }else{
                    $header[$value] = ucfirst($value1);
                }
            }
        }
        $extraSearchArray = [];
        $extraSearchArray['tbluser.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tbluser.status'] = $status;
        $extraSearchArray['tbluser.user_profile_id'] = $profile;

        $user_data = tbluserModel::select(DB::raw(strtolower(implode(',', $array))))
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->leftJoin('hrms_departments','hrms_departments.id','=','tbluser.department_id')
            ->leftJoin('tbluser_past_education','tbluser_past_education.user_id','=','tbluser.id')
            ->where($extraSearchArray)
            ->get();
            // echo "<pre>";print_r($header);exit;
        
        $res['status_code'] = 1;
        $res['message'] = "Student List";
        $res['user_data'] = $user_data;
        $res['data'] = $this->customFields($request);
        $res['headers'] = $header;
        $res['profiles'] = $tblProfiles;
        $res['profile'] = $profile;
        $res['status'] = $status;
        $res['dynamicFields']= $request->input('dynamicFields');

        return is_mobile($type, "user/show_user_report", $res, "view");

    }
}
