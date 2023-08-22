<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class dashboardSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */

    public $dynamic_boxes = [
        "Student Attendance"          => "95",
        "Recent fees collection"      => "33",
        "Recent Parent Communication" => "99",
        "Events"                      => "102",
        "Student Leaves"              => "140",
        "Student Fees Chart"          => "7",
    ];

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_profile_name = $request->session()->get("user_profile_name");
        $profile_parent_id = $request->session()->get("profile_parent_id");
        $user_profile_id = $request->session()->get("user_profile_id");
        $user_id = $request->session()->get("user_id");

        //START Dynamic Dashboard
        if ($profile_parent_id == '1' || $user_profile_name == 'Teacher') {

            $rightsQuery = DB::table('tbluser as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id');
                })->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
                })->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(".$sub_institute_id.", m.sub_institute_id)
                        AND m.dashboard_menu != '' ");
                })->selectRaw("m.id,m.name,m.dashboard_menu")
                ->whereRaw("u.sub_institute_id IN ('".$sub_institute_id."') AND u.id = '".$user_id."'")->get()->toArray();
        } else {
            $rightsQuery = DB::table('tblstudent as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id');
                })->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
                })->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(".$sub_institute_id.", m.sub_institute_id)
                        AND m.dashboard_menu != '' ");
                })->selectRaw("m.id,m.name,m.dashboard_menu")
                ->whereRaw("u.sub_institute_id IN ('".$sub_institute_id."') AND u.id = '".$user_id."'")->get()->toArray();
        }

        $rightsQuery = array_map(function ($value) {
            return (array) $value;
        }, $rightsQuery);

        $final_dynamic_dashboard = $final_userMenu = array();

        if (count($rightsQuery) > 0) {
            foreach ($rightsQuery as $key => $val) {
                $final_dynamic_dashboard[$val['id']] = $val['dashboard_menu'];
            }
        }

        $userMenu = DB::table('dynamic_dashboard')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('user_id', $user_id)
            ->where('user_profile_id', $user_profile_id)->get()->toArray();
        $userMenu = array_map(function ($value) {
            return (array) $value;
        }, $userMenu);

        if (isset($userMenu)) {
            foreach ($userMenu as $key => $val) {
                $final_userMenu[] = $val['menu_id'];
                $final_userMenuTitle[$val['menu_title']] = $val['menu_id'];
            }
        }
        //END Dynamic Dashboard 

        $res['final_userMenu'] = $final_userMenu;
        $res['final_dynamic_dashboard'] = $final_dynamic_dashboard;

        // dd($res);
        return is_mobile($type, '/dashboard_setting', $res, 'view');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $password = $request->input('password');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $finalArray['password'] = $password;
        $data = tbluserModel::where(['id' => $user_id])->update($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Password Change Successfully";

        return is_mobile($type, "change_password.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
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

        if ($checked == "true") {
            $result = DB::table('dynamic_dashboard')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('user_id', $user_id)
                ->where('menu_id', $menu_id)
                ->where('user_profile_id', $user_profile_id)->get()->toArray();

            if (count($result) == 0) {
                DB::table('dynamic_dashboard')
                    ->insert([
                        'user_id'          => $user_id,
                        'user_profile_id'  => $user_profile_id,
                        'sub_institute_id' => $sub_institute_id,
                        'menu_id'          => $menu_id,
                        'menu_title'       => $menu_title,
                    ]);
            }
        } else {
            DB::table('dynamic_dashboard')
                ->where('user_id', $user_id)
                ->where('user_profile_id', $user_profile_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('menu_id', $menu_id)
                ->delete();
        }

        $result = DB::table('dynamic_dashboard')
            ->selectRaw("(COUNT(*) + 2) as total_usermenu")
            ->where('user_id', $user_id)
            ->where('user_profile_id', $user_profile_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        return $result[0]->total_usermenu;
    }


}
