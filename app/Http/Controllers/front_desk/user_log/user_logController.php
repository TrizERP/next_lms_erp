<?php

namespace App\Http\Controllers\front_desk\user_log;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use File;
use DB;

class user_logController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = session()->get('sub_institute_id');
        $users = tbluserModel::select(
            DB::raw("CONCAT(first_name,' ',last_name) AS name"),
            'id'
        )
                ->where('sub_institute_id', $sub_institute_id)->get()
                ->pluck("name", "id");

        // echo ('<pre>');print_r($users);exit;
        $data['data'] = array();
        $data['data']['user'] = $users;

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/user_log/show", $data, "view");
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {       
        $from = $_REQUEST['from_date'];
        $to = $_REQUEST['to_date'];

        $where = array();
        if (isset($_REQUEST['user']) && $_REQUEST['user'] != '') {
            $where["USER_ID"] = $_REQUEST['user'];
        }

        $users =  DB::table('access_log_route')->select('access_log_route.*',DB::raw("CONCAT_WS(' ',u.first_name,u.last_name) AS user_name"))
				->join('tbluser as u','access_log_route.user_id','u.id')		
                ->where('access_log_route.SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
                ->where($where)
                ->whereBetween('created_at', ["$from", "$to"])
                ->get();       
        $response_arr= array();
        $i=0;
        foreach ($users as $id=>$arr) {
            // $response_arr[$i]['ID'] = $arr->ID;
            // $response_arr[$i]['SYEAR'] = $arr->SYEAR;
            // $response_arr[$i]['CURRUNT_URL'] = $arr->CURRUNT_URL;
            // $response_arr[$i]['CURRUNT_ROUTE'] = $arr->CURRUNT_ROUTE;
			
			$response_arr[$i]['id'] = $arr->id;
            $response_arr[$i]['url'] = $arr->url;
            $response_arr[$i]['module'] = $arr->module;
            $response_arr[$i]['action'] = $arr->action;
            $response_arr[$i]['created_at'] = $arr->created_at;
            $response_arr[$i]['user_name'] = $arr->user_name;

            // $route_arr = explode('.', $arr->CURRUNT_ROUTE);
            // $MENU = strtoupper($route_arr[0]);
            // $METHOD = strtoupper($route_arr[1]);
            // if ($METHOD == "STORE") {
                // $METHOD = "Insert";
            // } elseif ($METHOD == "DESTROY") {
                // $METHOD = "Delete";
            // } else {
                // $METHOD = "Update";
            // }

            // $response_arr[$i]['MENU'] = $MENU;
            // $response_arr[$i]['METHOD'] = $METHOD;
            // $response_arr[$i]['IP'] = $arr->IP;
            // $response_arr[$i]['CREATED_ON'] = $arr->CREATED_ON;
            $i++;
        }
        // echo('<pre>');
        // print_r($response_arr);
        // exit;

        $responce["all_data"] = $response_arr;
           
    
        $type = "WEB";
        return \App\Helpers\is_mobile($type, "front_desk/user_log/add", $responce, "view");

        // $res = array(
        //     "status_code" => 1,
        //     "message" => "Done",
        // );
        // $type = $request->input('type');
        // return \App\Helpers\is_mobile($type, "user_log.index", $res, "redirect");
    }


}
