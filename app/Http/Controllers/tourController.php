<?php

namespace App\Http\Controllers;

use App\Http\Controllers\school_setup\std_divController;
use App\Models\implementation\implementation_MasterModel;
use App\Models\school_setup\SchoolModel;
use App\Models\tourModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\tblmenumasterModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class tourController extends Controller
{

    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $module = $request->input('module');

        $inTour[$module] = 1;

        tourModel::where(['user_id' => $user_id, 'sub_institute_id' => $sub_institute_id])->update($inTour);

        $inTour = array();

        $request->session()->forget('erpTour');

        $checkUserTour = tourModel::where(['user_id'          => $user_id, 'sub_institute_id' => $sub_institute_id,
        ])->get()->toArray();
        $inTour = $checkUserTour[0];

        $request->session()->put('erpTour', $inTour);
    }

    public function implementation(Request $request)
    {
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

    public function implementation_1(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $sessionData = $request->session()->get('data');

        if (! isset($sessionData['isImplementation'])) {
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

    public function implementation_2(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "implementation_2", $res, "view");
    }

    public function skipImplementation(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "dashboard", $res);
    }

    public function Onboarding(Request $request)
    {
        $type = "";
        // return session();exit;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $rightsQuery = DB::table('tbluser as u')
        ->leftJoin('tblindividual_rights as i', function ($join) {
            $join->on('u.id', '=', 'i.user_id')
                ->whereColumn('u.sub_institute_id', '=', 'i.sub_institute_id');
        })
        ->leftJoin('tblgroupwise_rights as g', function ($join) {
            $join->on('u.user_profile_id', '=', 'g.profile_id')
                ->whereColumn('u.sub_institute_id', '=', 'g.sub_institute_id');
        })
        ->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
            $join->on(function ($query) use ($sub_institute_id) {
                $query->whereColumn('i.menu_id', '=', 'm.id')
                    ->orWhereColumn('g.menu_id', '=', 'm.id');
            })->whereIn('m.sub_institute_id', explode(',', $sub_institute_id));
        })
        ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
        ->where('u.sub_institute_id', $sub_institute_id)
        ->where('u.id', $user_id)
        ->get()
        ->toArray();    

        $rightsQuery = array_map(function ($value) {
            return (array)$value;
        }, $rightsQuery);

        $rightsMenusIds = 0;

        if (isset($rightsQuery['0']['MID'])) {
            $rightsMenusIds = $rightsQuery['0']['MID'];
        }
        $rightsMenusIds = rtrim($rightsMenusIds, ',');//RAJESH

        $data = tblmenumasterModel::whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)
            ->orderBy('sort_order')->groupBy('menu_title')->get()->toArray();

        $databaseTables = tblmenumasterModel::select('database_table')
            ->whereRaw("find_in_set('$sub_institute_id', sub_institute_id)")->where('status',1)
            ->pluck('database_table')
            ->toArray();

       // Check if the specified sub_institute exists in the tables
        $subInstituteExists = [];

        foreach ($databaseTables as $tableName) {
            if (Schema::hasTable($tableName)) {
            // Check if the table has the sub_institute_id column
                if (Schema::hasColumn($tableName, 'sub_institute_id')) {
                    $exists = DB::table($tableName)
                        ->where('sub_institute_id', $sub_institute_id)
                        ->exists();
                } else {
                // If the sub_institute_id column doesn't exist, consider it as not found
                    $exists = false;
                }
            } else {
                $exists = false;
            }

            $subInstituteExists[$tableName] = $exists;
        }
        // return $subInstituteExists;exit;
        $master = tblmenumasterModel::whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1) ->where('menu_type','=','MASTER')
            ->orderBy('sort_order')->get()->toArray();
        $i = 0;

        foreach ($master as $key => $value) {
                // print_r($value);
            $mastermenu[$value['menu_title']][$i] = $master[$key];
            $i++;
        }
        $entry = tblmenumasterModel::where('parent_menu_id', '!=', 0)
            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)->where("menu_type","=","ENTRY")
            ->orderBy('sort_order')->get()->toArray();

        $i = 0;
        foreach ($entry as $key => $value) {
            $finalSubMenu[$value['menu_title']][$i] = $entry[$key];
            $i++;
        }

        $report = tblmenumasterModel::where('parent_menu_id', '!=', 0)
            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)->where("menu_type","=","REPORT")
            ->orderBy('sort_order')->get()->toArray();

        $i = 0;
        foreach ($report as $key => $value) {
            $finalSubChildMenu[$value['menu_title']][$i] = $report[$key];
            $i++;
        }
        $database_table = tblmenumasterModel::select('database_table')->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['head'] = $data;
        $res['table_name'] = $subInstituteExists;
        $res['groupwisemenuMaster'] = $mastermenu;
        $res['groupwisesubmenuMaster'] = $finalSubMenu ?? [];
        $res['groupwiseSubsubmenuMaster'] = $finalSubChildMenu ?? [];
        $rr = [];

        return is_mobile($type, "setup_institute_details", $res, 'view');
    }

}
