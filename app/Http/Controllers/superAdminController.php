<?php

namespace App\Http\Controllers;

use App\Models\loginModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\SchoolModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;
use App\Models\user\tblindividual_rightsModel;
use App\Models\tourModel;
use App\Models\user\tbluserprofilemasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

class superAdminController extends Controller
{
    public function index(Request $request) 
    {
        $type = $request->input('type');

        if ($type == 'API') 
        {
            $sub_institute_id = $request->input('sub_institute_id');
        } 
        else 
        {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $get_clients = DB::table('tblclient')->selectRaw("id as client_id,client_name")->get()->toArray();

        $get_institutes = DB::table('school_setup')->get()->toArray();
        
        $res['get_clients'] = $get_clients;
        $res['get_institutes'] = $get_institutes;

        return is_mobile($type, "super_admin_page", $res, "view");
    }
    public function store(Request $request) 
    {
        $type = $request->input('type');

        $user_name = $request->input("user_name");
        $firstName = $request->input("first_name");
        $middleName = $request->input("middle_name");
        $lastName = $request->input("last_name");
        $email = $request->input("email");
        $password = $request->input("password");
        $mobile = $request->input("mobile");
        $client_id = $request->input("client_id");
        $institute_ids = $request->input("institute_id");

        foreach($institute_ids as $institute_id)
        {
            // update into school_setup table
            SchoolModel::where("Id", $institute_id)->update(["client_id" => $client_id]);
        }
        
        // Insert into tbluserprofilemaster table
        $user_profile = new tbluserprofilemasterModel();
        $user_profile->parent_id = 1;
        $user_profile->name = "Super Admin";
        $user_profile->description = "Super Admin";
        $user_profile->sort_order = 1;
        $user_profile->status = 1;
        $user_profile->client_id = $client_id;
        $user_profile->sub_institute_id = 0;
        $user_profile->save();

        // Insert into tbluser table
        $user = new loginModel();
        $user->user_name = $user_name;
        $user->email = $email;
        $user->first_name = $firstName;
        $user->middle_name = $middleName;
        $user->last_name = $lastName;
        $user->password = $password;
        $user->mobile = $mobile;
        $user->user_profile_id = $user_profile->id;
        $user->client_id = $client_id;
        $user->sub_institute_id = 0;
        $user->is_admin = 1;
        $user->status = 1;
        $user->save();

        $get_tbl_menumasters = DB::table('tblmenumaster')->get()->toArray();

        foreach($get_tbl_menumasters as $key => $get_tbl_menumaster)
        {
             // Insert into tblindividual_rights table
            $individual_right = new tblindividual_rightsModel();
            $individual_right->user_id = $user->id;
            $individual_right->menu_id = $get_tbl_menumaster->id;
            $individual_right->profile_id = $user_profile->id;
            $individual_right->can_view = 1;
            $individual_right->can_add = 1;
            $individual_right->can_edit = 1;
            $individual_right->can_delete = 1;
            $individual_right->sub_institute_id = 0;
            $individual_right->client_id = $client_id;
            $individual_right->save();
        }

        return is_mobile($type, "superAdmin", null, "redirect");
    }
}
