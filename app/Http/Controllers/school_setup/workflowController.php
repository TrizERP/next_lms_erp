<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class workflowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['status_code'] = 1;
        $data['message'] = "Success";

        $new_data = $this->getAllData($request);
        $data['wk_data'] = $new_data['wk_data'];
        $data['execute_arr'] = $new_data['execute_arr'];

        return is_mobile($type, '/show_workflow', $data, 'view');
    }

    public function getAllData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $wk_data = DB::table('wk_main')->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $data['wk_data'] = $wk_data;
        $data['execute_arr'] = [
            "1" => "Only on the first save",
            "2" => "Until the first time the condition is true",
            "3" => "Every time the record is saved ",
            "4" => "Every time a record is modified ",
            "5" => "Schedule",
        ];

        return $data;
    }

    public function getData($request)
    {
        $module_data = DB::table('wk_module')->groupBy('modulename')->get()->toArray();

        $data['module_data'] = $module_data;

        return $data;
    }

    public function wk_modulewise_fields(Request $request)
    {
        $module_name = $request->input("module_name");

        return DB::table('wk_module')->where('modulename', $module_name)->get()->toArray();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['status_code'] = 1;
        $data['message'] = "Success";

        $new_data = $this->getData($request);
        $data['module_data'] = $new_data['module_data'];

        return is_mobile($type, '/workflow', $data, 'view');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $created_by = $request->session()->get('user_id');

        $modulename = $request->input("wkname");
        $description = $request->input("wkdescription");
        $execute_id = $request->input("wkexecute");

        //START INSERT INTO wk_main table
        $wk_main_values = [
            'modulename'       => $modulename,
            'description'      => $description,
            'execute_id'       => $execute_id,
            'status'           => 1,
            'created_by'       => $created_by,
            'sub_institute_id' => $sub_institute_id,
        ];

        DB::table('wk_main')->insertGetId($wk_main_values);
        $main_id = DB::table('wk_main')->selectRaw('max(id) as last_id')->get()->toArray();

        $main_id = $main_id[0]->last_id;
        //END INSERT INTO wk_main table

        //START INSERT INTO wk_execute_schedule table		
        if ($execute_id == 5)//Execute is Schedule
        {
            $schedule_run = $request->input("schedule_run");
            $at_time = $at_date = $week_days = $month_days = "";

            if ($request->input("at_time") != "") {
                $at_time = date('h:i:s', strtotime($request->input("at_time")));
            }

            if ($request->input("specific_date") != "") {
                $at_date = date('Y-m-d', strtotime($request->input("specific_date")));
            }

            if ($request->input("week_days") != "") {
                $week_days = implode(",", $request->input("week_days"));
            }

            if ($request->input("month_days") != "") {
                $month_days = implode(",", $request->input("month_days"));
            }

            $wk_execute_values = [
                'main_id'          => $main_id,
                'run_workflow'     => $schedule_run,
                'at_time'          => $at_time,
                'at_date'          => $at_date,
                'week_days'        => $week_days,
                'month_days'       => $month_days,
                'status'           => 1,
                'created_by'       => $created_by,
                'sub_institute_id' => $sub_institute_id,
            ];
            DB::table('wk_execute_schedule')->insert($wk_execute_values);
        }
        //END INSERT INTO wk_execute_schedule table	

        //START INSERT INTO wk_condition table
        $fieldname = $request->input("fieldname");
        $fieldcondition = $request->input("fieldcondition");
        $fieldvalue = $request->input("fieldvalue");
        $conditiontype = $request->input("conditiontype");

        if (count($fieldname) > 1) {
            array_pop($fieldname); // Deleting last null element 
            foreach ($fieldname as $key => $val) {
                $module_id = $this->getmodule_id($modulename, $val);
                $wk_condition = [
                    'main_id'        => $main_id,
                    'module_id'      => $module_id,
                    'condition'      => $fieldcondition[$key],
                    'compare_value'  => $fieldvalue[$key],
                    'condition_type' => $conditiontype[$key],
                ];
                DB::table('wk_condition')->insert($wk_condition);
            }
        }

        //END INSERT INTO wk_condition table			
        return $main_id;
    }

    public function getmodule_id($modulename, $fieldname)
    {
        $module = DB::table('wk_module')->select('id')->where('modulename', $modulename)
            ->where('fieldname', $fieldname)->get()->toArray();

        return $module[0]->id;
    }

    public function wk_savemail(Request $request)
    {
        $main_id = $request->input("hiddenmain_id");
        $send_from = $request->input("send_from");
        $send_to = $request->input("send_to");
        $subject = $request->input("subject");
        $content = $request->input("content");

        $wk_mail = [
            'main_id'   => $main_id,
            'send_from' => $send_from,
            'send_to'   => $send_to,
            'subject'   => $subject,
            'content'   => $content,
            'status'    => 1,
        ];
        DB::table('wk_mail')->insert($wk_mail);

        return $this->getTaskList($main_id);
    }


    public function wk_saveupdatequery(Request $request)
    {
        $main_id = $request->input("upd_hiddenmain_id");
        $upd_fieldname = $request->input("upd_fieldname");
        $upd_fieldvalue = $request->input("upd_fieldvalue");

        if (count($upd_fieldname) > 1) {
            $module = DB::table('wk_module')->select('modulename')->where('id', $main_id)
                ->get()->toArray();

            $modulename = $module[0]->modulename;
            array_pop($upd_fieldname); // Deleting last null element 
            foreach ($upd_fieldname as $key => $val) {
                $module_id = $this->getmodule_id($modulename, $val);
                $wk_updatequery = array(
                    'main_id'    => $main_id,
                    'module_id'  => $module_id,
                    'fieldvalue' => $upd_fieldvalue[$key],
                );
                DB::table('wk_updatequery')->insert($wk_updatequery);
            }
        }

        return $this->getTaskList($main_id);
    }

    public function wk_savesms(Request $request)
    {
        $main_id = $request->input("sms_hiddenmain_id");
        $recepients = $request->input("recepients");
        $smstext = $request->input("smstext");

        $wk_sms = array(
            'main_id'    => $main_id,
            'recepients' => $recepients,
            'smstext'    => $smstext,
        );
        DB::table('wk_sms')->insert($wk_sms);

        return $this->getTaskList($main_id);
    }

    function getTaskList($main_id)
    {
        $mail_data = DB::table('wk_mail')->where('main_id', $main_id)->get()->toArray();
        $upd_data = DB::table('wk_updatequery')->selectRaw('count(*) as totalcount')->where('main_id', $main_id)
            ->groupBy('main_id')->get()->toArray();
        $sms_data = DB::table('wk_sms')->where('main_id', $main_id)->get()->toArray();

        $html = '';
        $html .= '<table id="mail_list" class="table table-striped table-bordered table-responsive" style="width:100%">
			<thead>
				<tr>
					<th>Task Type</th>                               
					<th>Task Title</th>
				</tr>
			</thead>
			<tbody>';
        foreach ($mail_data as $key => $val) {
            $html .= '<tr>    
                           <td>Send Mail</td>			
                           <td>'.$val->subject.'</td>			
                           </tr>';
        }

        foreach ($upd_data as $key1 => $val1) {
            $html .= '<tr>    
                           <td>Update Query</td>			
                           <td>No of Fields to update ->'.$val1->totalcount.'</td>			
                           </tr>';
        }

        foreach ($sms_data as $key2 => $val2) {
            $html .= '<tr>    
                           <td>Send SMS</td>			
                           <td>'.$val2->smstext.'</td>			
                           </tr>';
        }

        $html .= '</tbody></table>';

        return $html;
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
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $wk_data = DB::table('wk_main as w')
            ->leftJoin('wk_execute_schedule as e', function ($join) {
                $join->whereRaw('e.main_id = w.id');
            })->selectRaw("w.*,e.run_workflow,e.at_time,e.at_date,date_format(at_time,'%h:%s') as at_times")
            ->where('w.sub_institute_id', $sub_institute_id)
            ->where('w.id', $id)->get()->toArray();

        $wk_condition = DB::table('wk_condition as c')
            ->leftJoin('wk_module as m', function ($join) {
                $join->whereRaw('c.module_id = m.id');
            })
            ->where('main_id', '=', 2)
            ->get()->toArray();

        $type = $request->input('type');
        $data['wk_main'] = $wk_data[0];
        $data['wk_condition'] = $wk_condition;
        $data['wk_task'] = $this->getTaskList($id);
        $new_data = $this->getData($request);
        $data['module_data'] = $new_data['module_data'];

        return is_mobile($type, '/edit_workflow', $data, "view");
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
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('wk_main')->where('id', '=', $id)->delete();
        DB::table('wk_execute_schedule')->where('main_id', '=', $id)->delete();
        DB::table('wk_condition')->where('main_id', '=', $id)->delete();
        DB::table('wk_mail')->where('main_id', '=', $id)->delete();
        DB::table('wk_sms')->where('main_id', '=', $id)->delete();
        DB::table('wk_updatequery')->where('main_id', '=', $id)->delete();

        $res['status_code'] = "1";
        $res['message'] = "Workflow Deleted Successfully";

        return is_mobile($type, "workflow.index", $res);
    }

    public function device_check(request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, '/device_check', $res, 'view');
    }
}
