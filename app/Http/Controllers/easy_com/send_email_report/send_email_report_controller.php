<?php

namespace App\Http\Controllers\easy_com\send_email_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use App\Models\user\tbluserModel;
use DB;
use PHPMailer\PHPMailer;

class send_email_report_controller extends Controller
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
        return \App\Helpers\is_mobile($type, "easy_comm/send_email_report/show", $data, "view");
    }

 
    public function store(Request $request)
    {
        // echo "<pre>";
        // print_r($_REQUEST);
        // exit;

        $from = $_REQUEST['from_date'];
        $to = $_REQUEST['to_date'];

        $where = array();
        if (isset($_REQUEST['user']) && $_REQUEST['user'] != '') {
            $where["USER_ID"] = $_REQUEST['user'];
        }

        $users =  DB::table('email_sent_parents')
                ->where('sub_institute_id', session()->get('sub_institute_id'))
                ->where($where)
                ->whereBetween('CREATED_ON', ["$from", "$to"])
                ->get();
        // echo ('<pre>');print_r($users);exit;

        $responce["all_data"] = $users;
           
    
        $type = "WEB";
        return \App\Helpers\is_mobile($type, "easy_comm/send_email_report/add", $responce, "view");
        // return \App\Helpers\is_mobile($type, "send_mail", $responce, "redirect");
    }

   
  
    public function saveParentLog($email, $msg, $subject, $attachment, $ip)
    {
        DB::table('email_sent_parents')->insert(
            array(
                            'SYEAR' => session()->get('syear'),
                            'EMAIL' => $email,
                            'SUBJECT' => $subject,
                            'EMAIL_TEXT' => $msg,
                            'ATTECHMENT' => $attachment,
                            'USER_ID' => session()->get('user_id'),
                            'IP' => $ip,
                            'sub_institute_id' => session()->get('sub_institute_id')
                        )
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
