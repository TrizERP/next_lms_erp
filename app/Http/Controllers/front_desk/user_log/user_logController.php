<?php

namespace App\Http\Controllers\front_desk\user_log;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class user_logController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
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
        $users = tbluserModel::select(DB::raw("CONCAT(first_name,' ',last_name) AS name"), 'id')
            ->where('sub_institute_id', $sub_institute_id)->get()
            ->pluck("name", "id");

        $data['data'] = [];
        $data['data']['user'] = $users;

        $type = $request->input('type');

        return is_mobile($type, "front_desk/user_log/show", $data, "view");
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $from = $_REQUEST['from_date'];
        $to = $_REQUEST['to_date'];

        $where = [];
        if (isset($_REQUEST['user']) && $_REQUEST['user'] != '') {
            $where["USER_ID"] = $_REQUEST['user'];
        }

        $users = DB::table('access_log_route')->select('access_log_route.*',
            DB::raw("CONCAT_WS(' ',u.first_name,u.last_name) AS user_name"))
            ->join('tbluser as u', 'access_log_route.user_id', 'u.id')
            ->where('access_log_route.SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
            ->where($where)
            ->whereBetween('created_at', ["$from", "$to"])
            ->get();
        $response_arr = array();
        $i = 0;
        foreach ($users as $id => $arr) {

            $response_arr[$i]['id'] = $arr->id;
            $response_arr[$i]['url'] = $arr->url;
            $response_arr[$i]['module'] = $arr->module;
            $response_arr[$i]['action'] = $arr->action;
            $response_arr[$i]['created_at'] = $arr->created_at;
            $response_arr[$i]['user_name'] = $arr->user_name;

            $i++;
        }

        $responce["all_data"] = $response_arr;

        $type = "WEB";

        return is_mobile($type, "front_desk/user_log/add", $responce, "view");
    }
}
