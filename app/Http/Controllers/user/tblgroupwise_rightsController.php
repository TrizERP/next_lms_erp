<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\tblmenumasterModel;
use App\Models\user\tblgroupwise_rightsModel;
use App\Models\user\tbluserprofilemasterModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Response;

class tblgroupwise_rightsController extends Controller {
    public function index(Request $request) {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_data = tblgroupwise_rightsModel::select('tblgroupwise_rights.*', 'tbluserprofilemaster.name as profile_name', 'tblmenumaster.name as menu_name')
            ->join('tbluserprofilemaster', 'tblgroupwise_rights.profile_id', '=', 'tbluserprofilemaster.id')
            ->join('tblmenumaster', 'tblgroupwise_rights.menu_id', '=', 'tblmenumaster.id')
            ->where(['tblgroupwise_rights.sub_institute_id' => $sub_institute_id])
            ->get();
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $user_data;
        $type = $request->input('type');
        return is_mobile($type, "user/show_groupwise_rights", $res, "view");
    }

    public function create(Request $request) {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profiles = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id, 'status' => '1'])->orderBy('sort_order')->get()->toArray();

        // $data = tblmenumasterModel::where(['LEVEL' => 1,'status' => 1])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->orderBy('sort_order')->get()->toArray();

        // $subMenuData = tblmenumasterModel::where(['LEVEL' => 2,'status' => 1])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->orderBy('sort_order')->get()->toArray();

        // $SubsubMenuData = tblmenumasterModel::where(['LEVEL' => 3,'status' => 1])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->orderBy('sort_order')->get()->toArray();

        // $i = 0;
        // foreach ($subMenuData as $key => $value) {
        //  $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
        //  $i++;
        // }

        // $i = 0;
        // foreach ($SubsubMenuData as $key => $value) {
        //  $finalSubSubMenu[$value['parent_menu_id']][$i] = $SubsubMenuData[$key];
        //  $i++;
        // }

        // view()->share('groupwisemenuMaster', $data);
        // if (isset($finalSubMenu)) {
        //  view()->share('groupwisesubmenuMaster', $finalSubMenu);
        // }

        // if (isset($finalSubSubMenu)) {
        //  view()->share('groupwiseSubsubmenuMaster', $finalSubSubMenu);
        // }

        return view('user/add_groupwise_rights', ['user_profiles' => $user_profiles]);
    }

    public function store(Request $request) {
        $addRights = $request->input('add');
        $editRights = $request->input('edit');
        $deleteRights = $request->input('delete');
        $viewRights = $request->input('view');
        if (!isset($addRights)) {
            $addRights = array();
        }
        if (!isset($editRights)) {
            $editRights = array();
        }
        if (!isset($deleteRights)) {
            $deleteRights = array();
        }
        if (!isset($viewRights)) {
            $viewRights = array();
        }

        $arrayKeys = array_replace($addRights, $editRights, $deleteRights, $viewRights);
        $sub_institute_id = $request->session()->get('sub_institute_id');
                
        tblgroupwise_rightsModel::where(["profile_id" => $request->input('profile_id')])->delete();
        foreach ($arrayKeys as $key => $value) {
            $finalArray = array(
                'menu_id' => $key,
                'profile_id' => $request->input('profile_id'),
                'sub_institute_id' => $sub_institute_id,
            );

            if (isset($viewRights[$key])) {
                $finalArray['can_view'] = 1;
            }
            if (isset($addRights[$key])) {
                $finalArray['can_add'] = 1;
            }
            if (isset($editRights[$key])) {
                $finalArray['can_edit'] = 1;
            }
            if (isset($deleteRights[$key])) {
                $finalArray['can_delete'] = 1;
            }
            tblgroupwise_rightsModel::insert($finalArray);
        }
        $res['status_code'] = "1";
        $res['message'] = "Groupwise Rights Added successfully";
        $type = $request->input('type');
        return is_mobile($type, "add_groupwise_rights.index", $res);
    }

    public function displayGroupwiseRights(Request $request) {

        $profile_id = $request->input("profile_id");
        
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = tblmenumasterModel::join('tblprofilewise_menu','tblmenumaster.id','=','tblprofilewise_menu.menu_id')->where(['LEVEL' => 1,'status' => 1,'tblprofilewise_menu.user_profile_id' => $profile_id,'tblprofilewise_menu.sub_institute_id'=>$sub_institute_id])->groupBy('tblmenumaster.id')->orderBy('tblmenumaster.sort_order','ASC')->get()->toArray();

        $subMenuData = tblmenumasterModel::join('tblprofilewise_menu','tblmenumaster.id','=','tblprofilewise_menu.menu_id')->where(['LEVEL' => 2,'status' => 1,'tblprofilewise_menu.user_profile_id' => $profile_id,'tblprofilewise_menu.sub_institute_id'=>$sub_institute_id])->orderBy('tblmenumaster.sort_order','ASC')->get()->toArray();

        $SubsubMenuData = tblmenumasterModel::join('tblprofilewise_menu','tblmenumaster.id','=','tblprofilewise_menu.menu_id')->where(['LEVEL' => 3,'status' => 1,'tblprofilewise_menu.user_profile_id' => $profile_id,'tblprofilewise_menu.sub_institute_id'=>$sub_institute_id])->orderBy('tblmenumaster.sort_order','ASC')->get()->toArray();

        $i = 0;
        foreach ($subMenuData as $key => $value) {
            $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
            $i++;
        }

        $i = 0;
        foreach ($SubsubMenuData as $key => $value) {
            $finalSubSubMenu[$value['parent_menu_id']][$i] = $SubsubMenuData[$key];
            $i++;
        }

        view()->share('groupwisemenuMaster', $data);
        if (isset($finalSubMenu)) {
            view()->share('groupwisesubmenuMaster', $finalSubMenu);
        }else{
            $finalSubMenu = array();
        }

        if (isset($finalSubSubMenu)) {
            view()->share('groupwiseSubsubmenuMaster', $finalSubSubMenu);
        }

        $rightsData = tblgroupwise_rightsModel::join('tblmenumaster','tblgroupwise_rights.menu_id','=','tblmenumaster.id')->where(['tblgroupwise_rights.profile_id' => $profile_id])->get()->toArray();
        $rights = array();
        if (count($rightsData) > 0) {
            foreach ($rightsData as $key => $value) {
                if ($value['can_view'] == 1) {
                    $rights['view'][] = $value['menu_id'] . "_" . $value['can_view'];
                }
                if ($value['can_add'] == 1) {
                    $rights['add'][] = $value['menu_id'] . "_" . $value['can_add'];
                }
                if ($value['can_edit'] == 1) {
                    $rights['edit'][] = $value['menu_id'] . "_" . $value['can_edit'];
                }
                if ($value['can_delete'] == 1) {
                    $rights['delete'][] = $value['menu_id'] . "_" . $value['can_delete'];
                }
            }
        }

        $response = array(
            $data,
            $finalSubMenu ?? [],
            $finalSubSubMenu ?? [],
            $rights
        );
        return $response;
        
        // return view('user.add_groupwise_rights');

        // return view('user/add_groupwise_rights', ['user_profiles' => $user_profiles]);
        // return back()->with('success','added');
    }
}
