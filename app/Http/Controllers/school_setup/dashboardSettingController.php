<?php

namespace App\Http\Controllers\school_setup;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use App\Models\user\tbluserModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;

class dashboardSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public $dynamic_boxes = array(
                "Student Attendance"=>"95",
                "Recent fees collection"=>"33",
                "Recent Parent Communication"=>"99",
                "Events"=>"102",
                "Student Leaves"=>"140",
                "Student Fees Chart"=>"7"
                );

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_profile_name = $request->session()->get("user_profile_name");
        $user_profile_id = $request->session()->get("user_profile_id");
        $user_id = $request->session()->get("user_id");

        //START Dynamic Dashboard
        if ($user_profile_name == 'Admin' || $user_profile_name == 'ADMIN' || $user_profile_name == 'admin' || $user_profile_name == 'school admin' 
            || $user_profile_name == 'SCHOOL ADMIN' || $user_profile_name == 'School Admin' || $user_profile_name == 'Teacher') {
            
            $rightsQuery = "SELECT m.id,m.name,m.dashboard_menu
                FROM tbluser u 
                LEFT JOIN tblindividual_rights i ON u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id 
                LEFT JOIN tblgroupwise_rights g ON u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id 
                INNER JOIN tblmenumaster m ON (i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $sub_institute_id . ", m.sub_institute_id) AND m.dashboard_menu != '' 
                WHERE u.sub_institute_id IN ('" . $sub_institute_id . "') AND u.id = '" . $user_id . "'";
        }
        else{
            $rightsQuery = "SELECT m.id,m.name,m.dashboard_menu
                FROM tblstudent u 
                LEFT JOIN tblindividual_rights i ON u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id 
                LEFT JOIN tblgroupwise_rights g ON u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id 
                INNER JOIN tblmenumaster m ON (i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $sub_institute_id . ", m.sub_institute_id) AND m.dashboard_menu != '' 
                WHERE u.sub_institute_id IN ('" . $sub_institute_id . "') AND u.id = '" . $user_id . "'";
        }        

        $rightsQuery = DB::select($rightsQuery);
        $rightsQuery = array_map(function ($value) {
            return (array) $value;
        }, $rightsQuery);        
        
        $final_dynamic_dashboard = $final_userMenu = array();    

        if (count($rightsQuery) > 0) 
        {
            foreach($rightsQuery as $key =>$val)
            {                        
                $final_dynamic_dashboard[$val['id']] = $val['dashboard_menu'];                    
            }    
        }                           

        $userMenu = "SELECT *
            FROM dynamic_dashboard              
            WHERE sub_institute_id = '" . $sub_institute_id . "' AND user_id = '" . $user_id . "'
            ANd user_profile_id = '" . $user_profile_id . "'";
        $userMenu = DB::select($userMenu);
        $userMenu = array_map(function ($value) {
            return (array) $value;
        }, $userMenu);          
             
        if (isset($userMenu)) {             
            foreach($userMenu as $key => $val)
            {
                $final_userMenu[] = $val['menu_id'];    
                $final_userMenuTitle[$val['menu_title']] = $val['menu_id']; 
            }               
        }                               
        //END Dynamic Dashboard 
        
        $res['final_userMenu'] = $final_userMenu;
        $res['final_dynamic_dashboard'] = $final_dynamic_dashboard;
        // dd($res);
        return is_mobile($type,'/dashboard_setting',$res ,'view');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        dd($request);
        $type = $request->input('type');
        $password = $request->input('password');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $finalArray['password'] = $password;
        $data = tbluserModel::where(['id'=>$user_id])->update($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Password Change Successfully";

        return is_mobile($type, "change_password.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function ajax_SaveDynamicDashboardMenu(Request $request)
    {        
        $sub_institute_id = $request->session()->get('sub_institute_id');       
        $user_profile_id = $request->session()->get("user_profile_id");
        $user_id = $request->session()->get("user_id");

        $menu_id = $request->input('menu_id');
        $menu_title = $request->input('title');
        $checked = $request->input('checked');
        
        if($checked == "true")
        {
            $checkquery = "SELECT * FROM dynamic_dashboard where user_id = '".$user_id."' AND user_profile_id = '".$user_profile_id."'
            AND sub_institute_id = '".$sub_institute_id."' AND menu_id = '".$menu_id."'";
            $result = DB::select($checkquery);
            
            if(count($result) == 0)
            {
                $query = "INSERT INTO dynamic_dashboard(user_id,user_profile_id,sub_institute_id,menu_id,menu_title) 
                values('".$user_id."','".$user_profile_id."','".$sub_institute_id."','".$menu_id."','".$menu_title."')";
                $data = DB::select($query);
            }
        }
        else
        {
            $query = "DELETE FROM dynamic_dashboard where user_id = '".$user_id."' AND user_profile_id = '".$user_profile_id."'
            AND sub_institute_id = '".$sub_institute_id."' AND menu_id = '".$menu_id."'";
            $data = DB::select($query);
        }

        $totquery = "SELECT (COUNT(*) + 2) as total_usermenu FROM dynamic_dashboard where user_id = '".$user_id."' AND user_profile_id = '".$user_profile_id."'
        AND sub_institute_id = '".$sub_institute_id."'";
        $result = DB::select($totquery);          
        return $result[0]->total_usermenu;
    }

   
}
