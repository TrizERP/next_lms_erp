<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\loginModel;
use App\Models\student\tblstudentModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\SchoolModel;
use App\Models\tourModel;
use App\Models\user\tbluserprofilemasterModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class signupController extends Controller {

	public function index(Request $request) {

		$type = $request->input('type');
		$res['status_code'] = 1;
		return is_mobile($type, "signup", $res,"view");
	}

	public function get_trizStandard(Request $request)
	{
		$data = DB::select("SELECT * FROM standard s 
            INNER JOIN academic_section a ON s.sub_institute_id = a.sub_institute_id AND s.grade_id = a.id
            WHERE s.sub_institute_id = '1' AND a.title != 'OTHERS'
            ");
		$data = json_decode(json_encode($data), true);

        return $data;
	}
}
