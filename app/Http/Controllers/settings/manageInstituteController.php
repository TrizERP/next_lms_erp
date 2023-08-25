<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\api\NewLMS_ApiController;
use App\Http\Controllers\Controller;
use App\Models\fees\map_year\map_year;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setupModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Storage;


class manageInstituteController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $inward_data['message'] = $data_arr['message'];
            }
        }

        $client_id = session()->get('client_id');
        $syear = session()->get('syear');
        $type = $request->input('type');

        $schools = school_setupModel::where(['client_id' => $client_id])->get()->toArray();

        $school_data['status_code'] = 1;
        $school_data['data'] = $schools;

        return is_mobile($type, "settings/show_manage_institute", $school_data, "view");

    }

    public function create(Request $request)
    {
        return view('settings/add_manage_institute');
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $syear = session()->get('syear');
        $created_by = session()->get('user_id');
        $client_id = session()->get('client_id');
        $created_at = date('Y-m-d H:i:s');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        $file_name = $file_size = $ext = "";
        if ($request->hasFile('Logo')) {
            $file = $request->file('Logo');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->move(public_path('/admin_dep/images'), $file_name);
            $imageData = file_get_contents($path);
            Storage::disk('public')->put('user/' . $file_name, $imageData);
        }
        $school_setup = new school_setupModel([
            'SchoolName'            => $request->get('SchoolName'),
            'ShortCode'             => $request->get('ShortCode'),
            'ContactPerson'         => $request->get('ContactPerson'),
            'Mobile'                => $request->get('Mobile'),
            'Email'                 => $request->get('Email'),
            'ReceiptHeader'         => $request->get('ReceiptHeader'),
            'ReceiptAddress'        => $request->get('ReceiptAddress'),
            'FeeEmail'              => $request->get('FeeEmail'),
            'ReceiptContact'        => $request->get('ReceiptContact'),
            'SortOrder'             => $request->get('SortOrder'),
            'Logo'                  => $file_name,
            'cheque_return_charges' => $request->get('cheque_return_charges'),
            'created_at'            => $created_at,
            'created_by'            => $created_by,
            'created_ip'            => $created_ip,
            'client_id'             => $client_id,
            'institute_type'        => $request->get('institute_type') ?? 'school',            
        ]);

        $data_array = [
            'user_type'  => 'Admin',
            'first_name' => $request->get('SchoolName'),
            'last_name'  => '',
            'email'      => $request->get('Email'),
            'mobile'     => $request->get('Mobile'),
            'gender'     => 'M',
            'birthdate'  => '',
        ];
        $data = (object) $data_array;

        $school_setup->save();
        $board = '';
        $section = '';
        $sub_institute_id = DB::getPdo()->lastInsertId();

        $functions_object = new NewLMS_ApiController();

        // INSERT INTO tbluserprofilemaster table
        $functions_object->INSERT_USERPROFILEMASTER($sub_institute_id);
        // INSERT INTO tbluserprofilemaster table                 

        // INSERT INTO tbluser table
        $functions_object->INSERT_USER($data, $sub_institute_id, $file_name);
        // INSERT INTO tbluser table  

        // INSERT INTO academic_year table
        $functions_object->INSERT_ACADEMIC_YEAR($sub_institute_id);
        // INSERT INTO academic_year table 

        // INSERT INTO fees_map_years table
        $this->INSERT_FEES_MAP_YEARS($sub_institute_id);
        // INSERT INTO fees_map_years table 

        // INSERT INTO academic_section table
        $functions_object->INSERT_ACADEMIC_SECTION($sub_institute_id,$section,$board);
        // INSERT INTO academic_section table 

        // INSERT INTO standard table
        $functions_object->INSERT_STANDARD($sub_institute_id,$section,$board);
        // INSERT INTO standard table 

        // INSERT INTO division table                
        $functions_object->INSERT_DIVISION($sub_institute_id,$board);
        // INSERT INTO division table 

        // INSERT INTO std_div_map table                
        $this->INSERT_STD_DIV_MAP($sub_institute_id,$board);
        // INSERT INTO std_div_map table

        // INSERT INTO subject table                
        $functions_object->INSERT_SUBJECT($sub_institute_id,$board);
        // INSERT INTO subject table                 

        // INSERT INTO student_quota table                
        $functions_object->INSERT_STUDENTQUOTA($sub_institute_id,$board);
        // INSERT INTO student_quota table                 

        // INSERT INTO tblmenumaster & rightside_menumaster           
        $functions_object->INSERT_MENUMASTER($sub_institute_id,$board);
        // INSERT INTO tblmenumaster & rightside_menumaster            

        // INSERT INTO tblgroupwiseright
        $functions_object->INSERT_RIGHTS($data, $sub_institute_id,$board);
        // INSERT INTO tblgroupwiseright   

        $res['status_code'] = "1";
        $res['message'] = "Institute Added Succesfully";

        return is_mobile($type, "manage_institute.index", $res, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = school_setupModel::find($id);
        return view('settings/add_manage_institute', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $updated_at = date('Y-m-d H:i:s');
        $data = [
            'SchoolName'            => $request->get('SchoolName'),
            'ShortCode'             => $request->get('ShortCode'),
            'ContactPerson'         => $request->get('ContactPerson'),
            'Mobile'                => $request->get('Mobile'),
            'Email'                 => $request->get('Email'),
            'ReceiptHeader'         => $request->get('ReceiptHeader'),
            'ReceiptAddress'        => $request->get('ReceiptAddress'),
            'FeeEmail'              => $request->get('FeeEmail'),
            'ReceiptContact'        => $request->get('ReceiptContact'),
            'SortOrder'             => $request->get('SortOrder'),
            'institute_type'        => $request->get('institute_type'),            
            'cheque_return_charges' => $request->get('cheque_return_charges'),
            'updated_at'            => $updated_at,
        ];

        $file_name = $file_size = $ext = "";

        if ($request->hasFile('Logo')) {
            $file = $request->file('Logo');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->move(public_path('/admin_dep/images'), $file_name);
            $imageData = file_get_contents($path);
            Storage::disk('public')->put('user/' . $file_name, $imageData);
        }

        if ($file_name != "") {
            $data['Logo'] = $file_name;
        }

        school_setupModel::where(["Id" => $id])->update($data);

        $res['status_code'] = "1";
        $res['message'] = "Institute Updated Successfully";
        $type = $request->input('type');

        return is_mobile($type, "manage_institute.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        school_setupModel::where(["Id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Institute Deleted successfully";

        return is_mobile($type, "manage_institute.index", $res, "redirect");
    }

    public function INSERT_STD_DIV_MAP($sub_institute_id)
    {
        $standard_data = standardModel::select('*')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $division_data = divisionModel::select('*')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $division_data = $division_data[0];

        foreach ($standard_data as $key => $value) {
            $data = [
                'standard_id'      => $value['id'],
                'division_id'      => $division_data['id'],
                'sub_institute_id' => $sub_institute_id,
                'created_at'       => now(),
            ];

            std_div_mappingModel::insert($data);
        }
    }

    public function INSERT_FEES_MAP_YEARS($sub_institute_id)
    {
        $academic_year_data = academic_yearModel::select(DB::raw('MONTH(start_date) as from_month,MONTH(end_date) as to_month,syear'))
            ->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $from_month = $academic_year_data[0]['from_month'];
        $to_month = $academic_year_data[0]['to_month'];
        $syear = $academic_year_data[0]['syear'];

        $data = [
            'from_month'       => $from_month,
            'to_month'         => $to_month,
            'syear'            => $syear,
            'sub_institute_id' => $sub_institute_id,
            'created_at'       => now(),
        ];
        
        map_year::insert($data);
    }
}
