<?php

namespace App\Http\Controllers;

use App\Models\PayrollType;
use App\Models\user\tbluserModel;
use App\Models\WhatappUserDetail;
use App\Models\WhatsappSentMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Twilio\Rest\Client;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class WhatsappController extends Controller
{
    public function whatsapp_user_details(Request $request)
    {
        $type = $request->type ?? '';
        $data['data'] = WhatappUserDetail::all();
        $data['is_hidden'] = false;
        if (WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->count()) {
            $data['is_hidden'] = true;
        }
        return is_mobile($type, 'whatsapp.whatsapp_user_details.index', $data, "view");
    }

    public function whatsapp_send_messages(Request $request)
    {
        $type = $request->type ?? '';
        $data['data'] = WhatsappSentMessage::all();
        //return view('whatsapp.whatsapp_send_messages.index', ["data" => $data]);
        return is_mobile($type, 'whatsapp.whatsapp_send_messages.index', $data, "view");
    }

    public function whatsappUserDetailsCreate(Request $request, $id = 0)
    {
        $type = $request->type ?? '';

        if ($id) {
            $WhatsappUserDetail = WhatappUserDetail::find($id);
            //eturn view('whatsapp.whatsapp_user_details.create', compact('WhatsappUserDetail'));
            return is_mobile($type, 'whatsapp.whatsapp_user_details.create', $WhatsappUserDetail, "view");

        }
        $WhatsappUserDetail['user_whatsapp_no'] = '';
        $WhatsappUserDetail['user_whatsapp_sid'] = '';
        $WhatsappUserDetail['user_whatsapp_token'] = '';
        $WhatsappUserDetail['created_by'] = '';
        $WhatsappUserDetail['id'] = 0;
        //return view('whatsapp.whatsapp_user_details.create', compact('WhatsappUserDetail'));
        return is_mobile($type, 'whatsapp.whatsapp_user_details.create', $WhatsappUserDetail, "view");
    }

    public function whatsappSendMessageCreate(Request $request, $id = 0)
    {
        $type = $request->type ?? '';
        if ($id) {
            $WhatsappUserDetail = WhatsappSentMessage::find($id);
            //return view('whatsapp.whatsapp_user_details.create', compact('WhatsappUserDetail'));
            return is_mobile($type, 'whatsapp.whatsapp_user_details.create', $WhatsappUserDetail, "view");

        }
        //return view('whatsapp.whatsapp_send_messages.create');
        return is_mobile($type, 'whatsapp.whatsapp_send_messages.create', [], "view");

    }

    public function whatsappUserDetailsStore(Request $request)
    {
        $type = $request->type ?? '';
        $request->validate([
            'user_whatsapp_no' => 'required',
            'user_whatsapp_sid' => 'required',
            'user_whatsapp_token' => 'required',
        ]);
        if ($request->id > 0) {
            $payrollType = WhatappUserDetail::find($request->id);
        } else {
            $payrollType = new WhatappUserDetail();
        }
        $payrollType->user_whatsapp_no = $request->user_whatsapp_no;
        $payrollType->user_whatsapp_sid = $request->user_whatsapp_sid;
        $payrollType->user_whatsapp_token = $request->user_whatsapp_token;
        $payrollType->sub_institute_id = session()->get('sub_institute_id');
        $payrollType->created_by = session()->get('user_profile_id');
        $payrollType->created_by_name = session()->get('name');
        $payrollType->save();

        return is_mobile($type, 'whatsapp-user-details', [], "redirect");
    }


    public function whatsappSendMessageStore(Request $request)
    {
        $type = $request->type ?? '';
        $request->validate([
            'message' => 'required'
        ]);

        $token = WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->first();
        $searchStudent = SearchStudent($request->grade,$request->standard,$request->division,session()->get('sub_institute_id'));
        //$searchStudent = SearchStudent();

        foreach ($searchStudent as $student) {
            if (!empty($token)) {
                $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
                $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
                $client->messages->create(
                    'whatsapp:+91' . $student['mobile'],
                    [
                        "contentSid" => "HX02a86c824bbf747808744e76ac5795d3",
                        "messagingServiceSid" => $messagingServiceSid,
                        "from" => "whatsapp:".$token['user_whatsapp_no'],
                        "contentVariables" => json_encode([
                            "1" => $request->message
                        ])
                    ]
                );
                $saveMesasge = new WhatsappSentMessage();
                $saveMesasge->sub_institute_id = session()->get('sub_institute_id');
                $saveMesasge->syear = session()->get('syear');
                $saveMesasge->standard_id = $request->standard;
                $saveMesasge->division_id = $request->division;
                $saveMesasge->student_id = $student['id'];
                $saveMesasge->message = $request->message;
                $saveMesasge->sent_date = Carbon::today();
                $saveMesasge->created_by = session()->get('user_profile_id');
                $saveMesasge->created_by_name = session()->get('name');
                $saveMesasge->save();

            }
        }


        return is_mobile($type, 'whatsapp-send-messages', [], "redirect");

    }

    public function whatsappUserDetailsDestroy(Request $request, $id)
    {
        if ($id > 0) {
            WhatappUserDetail::where('id', $id)->delete();
        }
        return redirect('whatsapp-user-details');
    }
}
