<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\HrmsJobTitle;
use App\Models\school_setup\subjectModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\user\tbluserModel;
use App\Models\user\tbluserprofilemasterModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;

class tbluserController extends Controller
{

    use GetsJwtToken;

    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $user_data = tbluserModel::select('tbluser.*', 'tbluserprofilemaster.name as profile_name',
            DB::raw('if(tbluser.status = 1,"Active","Inactive") as status'))
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id]) //, 'tbluser.status' => "1"
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $user_data;

        $type = $request->input('type');

        return is_mobile($type, "user/show_user", $res, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id, 'status' => '1'])->get()->toArray();
        $dataCustomFields = tblcustomfieldsModel::where([
            'sub_institute_id' => $sub_institute_id, 'status' => "1", 'table_name' => "tbluser",
        ])
            ->get();

        $subject_data = subjectModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $employees = tbluserModel::where('sub_institute_id',$sub_institute_id)->where('status',1)->get();
        $job_titles = HrmsJobTitle::where('sub_institute_id',$sub_institute_id)->get();
        $departments = DB::table('hrms_departments')->where('status',1)->get()->toArray();
        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = [];
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        if (count($finalfieldsData) > 0) {
            view()->share('data_fields', $finalfieldsData);
        }
        view()->share('custom_fields', $dataCustomFields);
        view()->share('subject_data', $subject_data);
        view()->share('user_profiles', $data);
        view()->share('job_titles', $job_titles);
        view()->share('employees', $employees);
        view()->share('departments', $departments);

        return view('user/add_user');
    }

    public function store(Request $request)
    {
        //return $request->all();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');

        $file_name = "";
        if ($request->hasFile('user_image')) {
            $file = $request->file('user_image');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('user_name').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/user/', $file_name);
        }

        $request->request->add(['image' => $file_name]); //add request
        $data = $this->saveData($request);

        $data = tbluserModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $res['status_code'] = "1";
        $res['message'] = "User created successfully";
        $res['data'] = $data;

        return is_mobile($type, "add_user.index", $res);
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['status'] = 1;
        unset($newRequest['user_image']);
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }
        tbluserModel::insert($finalArray);
        $id = DB::getPdo()->lastInsertId();

        $client_data = DB::table("school_setup as s")
            ->join('tblclient as c', function ($join) {
                $join->whereRaw("c.id = s.client_id");
            })
            ->selectRaw('*,if(db_hrms is null,0,1) as rights')
            ->where("s.Id", "=", $sub_institute_id)
            ->get()->toArray();

        $hrms_db_host = $client_data[0]->db_host;
        $hrms_db_user = $client_data[0]->db_user;
        $hrms_db_password = $client_data[0]->db_password;
        $hrms_db_hrms = $client_data[0]->db_hrms;
        $hrms_rights = $client_data[0]->rights;

        if ($hrms_rights == 1 && $id != "") {
            $fields = [
                'db_host'     => $hrms_db_host,
                'db_user'     => $hrms_db_user,
                'db_password' => $hrms_db_password,
                'db_hrms'     => $hrms_db_hrms,
            ];
            $fields = array_merge($fields, $finalArray);

            //url-ify the data for the POST
            $fields_string = "";
            foreach ($fields as $key => $value) {
                $fields_string .= $key.'='.$value.'&';
            }
            rtrim($fields_string, '&');
            //open connection
            $ch = curl_init();

            $url = "http://".$_SERVER['HTTP_HOST']."/add_user_hrms.php";

            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

            //execute post
            $result = curl_exec($ch);

            //close connection
            curl_close($ch);
        }

        return $id;
    }

    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $user_id = $newRequest['id'];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['status'] = 1;
        unset($newRequest['user_image']);
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        return tbluserModel::where(['id' => $user_id])->update($finalArray);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $subject_data_selected_arr = array();

        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $editData = tbluserModel::find($id)->toArray();
        $data = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $subject_data = subjectModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $subject_data_selected = $editData['subject_ids'];
        if (isset($subject_data_selected)) {
            $subject_data_selected_arr = explode(",", $subject_data_selected);
        }

        $dataCustomFields = tblcustomfieldsModel::where([
            'sub_institute_id' => $sub_institute_id, 'status' => "1", 'table_name' => "tbluser",
        ])
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }
        $res['departments'] = DB::table('hrms_departments')->where('status',1)->get()->toArray();
        $res['employees'] = tbluserModel::where('sub_institute_id',$sub_institute_id)->get();
        $res['job_titles'] = HrmsJobTitle::where('sub_institute_id',$sub_institute_id)->get();
        $res['custom_fields'] = $dataCustomFields;
        $res['subject_data'] = $subject_data;
        $res['subject_data_selected_arr'] = $subject_data_selected_arr;
        $res['user_profiles'] = $data;
        $res['data'] = $editData;
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "user/edit_user", $res, "view");
    }

    public function update(Request $request, $id)
    {
        if(!$request->monday) {
            $request->request->add(['monday'=> 0]);
        }  if (!$request->tuesday) {
            $request->request->add(['tuesday' => 0]);
        }  if (!$request->wednesday) {
            $request->request->add(['wednesday' => 0]);
        }  if (!$request->thursday) {
            $request->request->add(['thursday' => 0]);
        }  if (!$request->friday) {
            $request->request->add(['friday' => 0]);
        }  if (!$request->saturday) {
            $request->request->add(['saturday' => 0]);
        }  if (!$request->sunday) {
            $request->request->add(['sunday' => 0]);
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');

        $file_name = "";
        if ($request->hasFile('user_image')) {
            $file = $request->file('user_image');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('user_name').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/user/', $file_name);
        }
        if ($file_name != "") {
            $request->request->add(['image' => $file_name]); //add request
            $request->session()->put('image', $file_name);
        }

        $request->request->add(['id' => $id]); //add request
        $user_id = $id;

        $data = $this->updateData($request);

        $res['status_code'] = "1";
        $res['message'] = "User updated successfully";
        $res['data'] = $data;

        return is_mobile($type, "add_user.index", $res);
    }

    public function destroy(Request $request, $id)
    {
        $user = [
            'status' => "0",
        ];
        $type = $request->input('type');
        tbluserModel::where(["id" => $id])->update($user);

        $res['status_code'] = "1";
        $res['message'] = "User deleted successfully";

        return is_mobile($type, "add_user.index", $res);
    }

    public function deactiveUser(Request $request, $id)
    {
        $user = [
            'status' => "0",
        ];
        $type = $request->input('type');
        tbluserModel::where(["id" => $id])->update($user);
        $res['status_code'] = "1";
        $res['message'] = "User deleted successfully";

        return is_mobile($type, "add_user.index", $res);
    }


    public function teacherListAPI(Request $request)
    {

        // try {
        //           if (!$this->jwtToken()->validate()) {
        //               $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
        //               return response()->json($response, 401);
        //           }
        //       } catch (\Exception $e) {
        //           $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
        //           return response()->json($response, 401);
        //       }

        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");


        if ($sub_institute_id != "") {
            $data = DB::table("tbluser as u")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = u.user_profile_id AND up.name = 'Teacher'");
                })
                ->selectRaw("u.id,concat_ws(' ',u.first_name,u.middle_name,u.last_name) as teacher_name,
					    u.email,u.mobile,u.user_profile_id,up.name as user_group")
                ->where("u.sub_institute_id", "=", $sub_institute_id)
                ->orderBy('u.id')
                ->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

}
