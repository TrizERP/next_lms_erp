<?php

namespace App\Http\Middleware;
use App\Models\tblmenumasterModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterSetupMenuMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param Request $request
	 * @param  Closure  $next
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next)
    {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$user_id = $request->session()->get('user_id');
		$user_profile_id = $request->session()->get('user_profile_id');
		$user_profile_name = $request->session()->get('user_profile_name');
		// echo $type;

		$rightsQuery = "SELECT GROUP_CONCAT(distinct m.id) AS MID
FROM tbluser u LEFT JOIN tblindividual_rights i ON u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id LEFT JOIN tblgroupwise_rights g ON u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id INNER JOIN tblmenumaster m ON (i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $sub_institute_id . ", m.sub_institute_id) WHERE u.sub_institute_id = '" . $sub_institute_id . "' AND u.id = '" . $user_id . "'";

		$rightsQuery = DB::select($rightsQuery);

		$rightsQuery = array_map(function ($value) {
			return (array) $value;
		}, $rightsQuery);

		$rightsMenusIds = 0;

		if (isset($rightsQuery['0']['MID'])) {
			$rightsMenusIds = $rightsQuery['0']['MID'];
		}

		if ($user_profile_name == 'admin' || $user_profile_name == 'Admin' || $user_profile_name == 'ADMIN') {
			$rightsMenusIds = $rightsMenusIds . ",37,41,42";
		} else if ($request->session()->has('multiSchool') && $request->session()->get('multiSchool') == 1) {
			if ($user_profile_name == 'SCHOOL ADMIN' || $user_profile_name == 'School Admin' || $user_profile_name == 'school admin') {
				$rightsMenusIds = $rightsMenusIds . ",37,41,42";
			}
		}

		if ($type != "API") {
			$sub_institute_id = $request->session()->get('sub_institute_id');
			//        $data = tblmenumasterModel::where(['parent_menu_id'=>"0"])->whereIn('sub_institute_id', [$user_id])->get()->toArray();
			$data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and menu_type = 'MASTER' and id in (" . $rightsMenusIds . ") and status = 1")->orderBy('sort_order')->get()->toArray();
			//        $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=' , 0)->whereIn('sub_institute_id', [$user_id])->get()->toArray();
			$subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 2 and id in (" . $rightsMenusIds . ") and status = 1")->orderBy('sort_order')->get()->toArray();
			//         dd($subMenuData);
			$i = 0;
			foreach ($subMenuData as $subMenuKey => $subMenuValue) {
				$finalSubMenu[$subMenuValue['parent_menu_id']][$i] = $subMenuValue;
				$i++;
			}

			$subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and id in (" . $rightsMenusIds . ") AND level = 3 and status = 1")->orderBy('sort_order')->get()->toArray();
			// dd($subChildMenuData);
			$i = 0;
			foreach ($subChildMenuData as $subChildMenuKey => $subChildMenuValue) {
				$finalSubChildMenu[$subChildMenuValue['parent_menu_id']][$i] = $subChildMenuValue;
				$i++;
			}
			// dd($finalSubMenu);
			// exit;
			view()->share('menuMaster', $data);
			if (isset($finalSubMenu)) {
				view()->share('submenuMaster', $finalSubMenu);
			}
			if (isset($finalSubChildMenu)) {
				view()->share('subChildmenuMaster', $finalSubChildMenu);
			}
		}

		return $next($request);
	}
}
