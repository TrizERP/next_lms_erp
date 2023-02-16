<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\tblmenumasterModel;
use App\Models\user\tblgroupwise_rightsModel;
use App\Models\user\tblindividual_rightsModel;
use App\Models\user\tbluserModel;
use App\Models\user\tbluserprofilemasterModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;

class tblindividual_rightsController extends Controller {
	public function index(Request $request) {
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$user_data = tblindividual_rightsModel::select('tblindividual_rights.*', 'tbluserprofilemaster.name as profile_name', 'tblmenumaster.name as menu_name', 'tbluser.user_name as user_name')
			->join('tbluser', 'tblindividual_rights.user_id', '=', 'tbluser.id')
			->join('tbluserprofilemaster', 'tblindividual_rights.profile_id', '=', 'tbluserprofilemaster.id')
			->join('tblmenumaster', 'tblindividual_rights.menu_id', '=', 'tblmenumaster.id')
			->where(['tblindividual_rights.sub_institute_id' => $sub_institute_id])
			->get();

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $user_data;
		$type = $request->input('type');
		return is_mobile($type, "user/show_individual_rights", $res, "view");
	}

	public function create(Request $request) {
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$user_profiles = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id])->orderBy('sort_order')->get()->toArray();

		$data = tblmenumasterModel::where(['LEVEL' => "1",'status' => "1"])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->orderBy('sort_order')->get()->toArray();

		$subMenuData = tblmenumasterModel::where(['LEVEL'=> "2",'status' => "1"])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->orderBy('sort_order')->get()->toArray();

		$SubsubMenuData = tblmenumasterModel::where(['LEVEL'=> "3",'status' => "1"])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->orderBy('sort_order')->get()->toArray();

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

		view()->share('individualmenuMaster', $data);

		if (isset($finalSubMenu)) {
			view()->share('individualsubmenuMaster', $finalSubMenu);
		}

		if (isset($finalSubSubMenu)) {
			view()->share('individualSubsubmenuMaster', $finalSubSubMenu);
		}

		return view('user/add_individual_rights', ['user_profiles' => $user_profiles]);
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

		tblindividual_rightsModel::where(["profile_id" => $request->input('profile_id')])->delete();
		foreach ($arrayKeys as $key => $value) {
			$finalArray = array(
				'menu_id' => $key,
				'profile_id' => $request->input('profile_id'),
				'user_id' => $request->input('user_id'),
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
			tblindividual_rightsModel::insert($finalArray);
		}
		$res['status_code'] = "1";
		$res['message'] = "Individual Rights Added successfully";
		$type = $request->input('type');
		return is_mobile($type, "add_individual_rights.index", $res);
	}

	public function profileWiseUsers(Request $request) {
		$profile_id = $request->input("profile_id");
		$sub_institute_id = $request->session()->get("sub_institute_id");
		$usersData = tbluserModel::where(['sub_institute_id' => $sub_institute_id, 'status' => '1', 'user_profile_id' => $profile_id])->get(['user_name', 'id'])->toArray();

		return $usersData;
	}

	public function displayIndividualRights(Request $request) {
		$profile_id = $request->input("profile_id");
		$user_id = $request->input("user_id");
		$grouprightsData = tblgroupwise_rightsModel::where(['profile_id' => $profile_id])->get()->toArray();
		$rights = array();
		if (count($grouprightsData) > 0) {
			foreach ($grouprightsData as $key => $value) {
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
		$rightsData = tblindividual_rightsModel::where(['profile_id' => $profile_id, 'user_id' => $user_id])->get()->toArray();
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
		return $rights;
	}
}
