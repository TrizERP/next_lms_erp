<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\school_setup\std_divController;
use App\Models\implementation\implementation_MasterModel;
use App\Models\school_setup\SchoolModel;
use App\Models\tourModel;
use App\Models\user\tbluserModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;

class tourController extends Controller {

	public function index(Request $request) {
		$user_id = $request->session()->get('user_id');

		$sub_institute_id = $request->session()->get('sub_institute_id');

		$module = $request->input('module');

		$inTour[$module] = 1;

		tourModel::where(['user_id' => $user_id, 'sub_institute_id' => $sub_institute_id])->update($inTour);

		$inTour = array();

		$request->session()->forget('erpTour');

		$checkUserTour = tourModel::where(['user_id' => $user_id, 'sub_institute_id' => $sub_institute_id])->get()->toArray();
		$inTour = $checkUserTour[0];
		
		$request->session()->put('erpTour', $inTour);


	}

	public function implementation(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');

		$getSchoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();

		$getUserData = tbluserModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		if (isset($getSchoolData)) {
			$res['schooldata'] = $getSchoolData[0];
		}
		if (isset($getUserData)) {
			$res['userdata'] = $getUserData[0];
		}

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "implementation", $res, "view");
	}

	public function implementation_1(Request $request) {

		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$sessionData = $request->session()->get('data');

		if (!isset($sessionData['isImplementation'])) {
			$std_divController = new std_divController();
			$data = $std_divController->getData($request);

			$map = count($data['std_div_map_data']);
			if ($map == 0) {
				$res['status_code'] = 1;
				$res['message'] = "SUCCESS";
				$res['data'] = $data;
				$res['isImplementation'] = "1";

				return is_mobile($type, 'school_setup/std_div_map', $res, "view");
			}

			$implementationData = implementation_MasterModel::where(['SUB_INSTITUTE_ID' => $sub_institute_id])->get()->toArray();

			if (count($implementationData) == 0) {
				$res['status_code'] = 1;
				$res['message'] = "SUCCESS";
				$res['isImplementation'] = "1";
				return is_mobile($type, "add_implementation.index", $res);
			}

			$res['status_code'] = 1;
			$res['message'] = "Success";
			$res['moduleId'] = "1";

			return is_mobile($type, "implementation_1", $res, "view");

		} else {

			$res['status_code'] = 1;
			$res['message'] = "Success";

			return is_mobile($type, "implementation_1", $res, "view");
		}
	}

	public function implementation_2(Request $request) {
		$type = $request->input('type');

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "implementation_2", $res, "view");
	}

	public function skipImplementation(Request $request) {
		$type = $request->input('type');

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "dashboard", $res);
	}

}
