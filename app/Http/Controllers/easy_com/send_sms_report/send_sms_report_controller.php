<?php

namespace App\Http\Controllers\easy_com\send_sms_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class send_sms_report_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) {
            // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "easy_comm/send_sms_report/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $join_tbl = "";
        $join = "";
        $responce_arr = [];
        if ($_REQUEST['tbl'] == 'staff') {
            $tbl = "sms_sent_staff as s";
            $join_tbl = "tbluser as u";
            $join = [
                's.staff_id'         => 'u.id',
                's.sub_institute_id' => 'u.sub_institute_id',
            ];
        } else {
            $tbl = "sms_sent_parents as s";
            $join_tbl = "tblstudent as u";
            $join = [
                's.student_id'       => 'u.id',
                's.sub_institute_id' => 'u.sub_institute_id',
            ];
        }

        $type = $request->input('type');

        $alldata = DB::table($tbl)
            ->join($join_tbl, $join)
            ->where([
                's.sub_institute_id' => session()->get('sub_institute_id'),
            ])
            ->where('s.created_on', '<=', $_REQUEST['to_date'])
            ->where('s.created_on', '>=', $_REQUEST['from_date'])
            ->get();

        $data = [];
        foreach ($alldata as $object) {
            $data[] = (array) $object;
        }

        foreach ($data as $id => $arr) {
            $responce_arr[$id]['sr.no'] = $id + 1;
            $responce_arr[$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr[$id]['syear'] = $arr['syear'];
            $responce_arr[$id]['sms_no'] = $arr['sms_no'];
            $responce_arr[$id]['sms_text'] = $arr['sms_text'];
            $responce_arr[$id]['module_name'] = $arr['module_name'];
        }

        return is_mobile($type, "easy_comm/send_sms_report/add", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
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

}
