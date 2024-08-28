<?php

namespace App\Http\Controllers;

use App\Models\PayrollType;
use App\Models\student\tblstudentModel;
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
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Storage;

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

    public function whatsappSentGenerateReport(Request $request)
    {
        $type = $request->type ?? '';
        $res = session()->get('data');
        return is_mobile($type, 'whatsapp.whatsapp_send_messages.generate_report', [], "view");
    }

    public function whatsappSentGenerateReportDetails(Request $request)
    {

        $type = $request->type ?? '';

        $data = WhatsappSentMessage::with('student');

        if ($request->standard) {
            $data->where('standard_id', $request->standard);
        }

        if ($request->division) {
            $data->where('division_id', $request->division);
        }

        if ($request->from_date && $request->to_date) {
            $data->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }
        $data = $data->get();
        $result['stu_data'] = $data;
        $result['grade_id'] = $request->grade;
        $result['standard_id'] = $request->standard;
        $result['division_id'] = $request->division;
        $result['from_date'] = $request->from_date;
        $result['to_date'] = $request->to_date;
        return is_mobile($type, 'whatsapp.whatsapp_send_messages.generate_report', $result, "view");
    }

    public function whatsapp_send_messages(Request $request)
    {
        $type = $request->type ?? '';
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=="API"){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }
        $this->updateMessageStatus($sub_institute_id,$syear);
        $data['data'] = WhatsappSentMessage::with('student')->with('standard')->with('division')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();
        // echo "<pre>";print_r($data);exit;
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

    public function mediaFound($message)
    {
        // 22-08-2024 
        preg_match('/<img[^>]+src="([^"]+)"/i', $message, $matches);

        // Check if an image source was found
        if (isset($matches[1])) {
            $imageUrl  = $matches[1];
            $message = preg_replace('/<img[^>]*>/i', '<a href="' . $imageUrl . '">' . $imageUrl . '</a>', $message);
        } 
        // end 22-08-2024
        // Extract text parts outside anchor tags and concatenate each section
        $textPattern = '/(^|<\/a>)(.*?)(<a href="|$)/';
        $textMatches = [];
        preg_match_all($textPattern, $message, $textMatches);

        $textSections = [];
        $currentSection = '';

        foreach ($textMatches[2] as $match) {
            if (!empty(trim($match))) {
                $currentSection .= $match;
            } else {
                if (!empty($currentSection)) {
                    $textSections[] = $currentSection;
                    $currentSection = '';
                }
            }
        }
        if (!empty($currentSection)) {
            $textSections[] = $currentSection;
        }
        $hrefPattern = '/<a href="(.*?)">/';
        $hrefMatches = $hrefLinks = [];
        preg_match_all($hrefPattern, $message, $hrefMatches);
        //$hrefLinks = $hrefMatches[1]; // $matches[1] contains all href links found
        foreach ($hrefMatches[1] as $href) {
            // Use parse_url to parse the URL
            $parsedUrl = parse_url($href);

            // We want to keep the path part after the domain, remove the domain part
            if (isset($parsedUrl['path'])) {
                $path =ltrim($parsedUrl['path'], '/');

                // Concatenate the query and fragment part if they exist
                if (isset($parsedUrl['query'])) {
                    $path .= '?' . $parsedUrl['query'];
                }
                if (isset($parsedUrl['fragment'])) {
                    $path .= '#' . $parsedUrl['fragment'];
                }

                // Add the modified path to the hrefLinks array
                $hrefLinks[] = $path;
            } else {
                // If there is no path, keep the full href
                $hrefLinks[] = $href;
            }
        }

        return [$textSections,$hrefLinks];
    }

    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = array();
        $type = $request->input('type');

        return is_mobile($type, "whatsapp/whatsapp_send_messages/create", $data, "view");
    }
    public function create(Request $request)
    {

        $type = $request->input('type');
        $student_data = SearchStudent($request->get('grade'), $request->get('standard'), $request->get('division'));
        $responce_arr['grade'] = $request->get('grade');
        $responce_arr['standard'] = $request->get('standard');
        $responce_arr['division'] = $request->get('division');

        foreach ($student_data as $id => $arr) {

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['enrollment_no'] = $arr['enrollment_no'];
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
        }

        return is_mobile($type, "whatsapp/whatsapp_send_messages/add", $responce_arr, "view");
    }


    public function whatsappSendMessageStore(Request $request)
    {
        // return $request->all();exit;
        //return $request->all();
        $type = $request->type ?? '';
        $request->validate([
            'message' => 'required'
        ]);
        $attachment ='';
        $token = WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->first();
        $searchStudent = SearchStudent($request->grade, $request->standard, $request->division, session()->get('sub_institute_id'));
        //$searchStudent = SearchStudent();


        list($textArray, $hrefArray) = $this->mediaFound($request->message);

        // Initialize prepareMessageBody array
        $prepareMessageBody = [];

        if (count($hrefArray) == 0) {
            // Ensure textArray is not empty before accessing it
            $prepareMessageBody['contentVariables'] = json_encode([
                "1" => isset($textArray[0]) ? $textArray[0] : null,
            ]);
            $prepareMessageBody['contentSid'] = "HX3a292a1ee72924adb532e807a2ed9b36";
        } else {
            // Ensure hrefArray and textArray have elements before accessing them
            $attachment = isset($hrefArray[0]) ? $hrefArray[0] : null;
            $prepareMessageBody['contentVariables'] = json_encode([
                "1" => isset($hrefArray[0]) ? $hrefArray[0] : null,
                "2" => isset($textArray[0]) ? $textArray[0] : null,
            ]);
            $prepareMessageBody['contentSid'] = "HXe0114bc20670d1b3f92c854106ec4a81";
        }
       
        // echo "<pre>";print_r($attachment);exit;
        $i=0;
        if(isset($textArray[0])){
            foreach ($request->sendNotification as $studentId => $on) {
                $student = tblstudentModel::where([['id',$studentId],['sub_institute_id',session()->get('sub_institute_id')]])->first();
                if (!empty($token) && !empty($student) && $student['mobile'] != null) {
                    $i++;
                    $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
                    $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
                    $twilioResponse = $client->messages->create(
                        'whatsapp:+91' . $student['mobile'],
                        [
                            "contentSid" => $prepareMessageBody['contentSid'],
                            "messagingServiceSid" => $messagingServiceSid,
                            "from" => "whatsapp:+91" . $token['user_whatsapp_no'],
                            "contentVariables" => $prepareMessageBody['contentVariables'],
                        ]
                    );
                    // Check message status
                    $messageStatus = $twilioResponse->status;
                    $errorStatus = null;
                    // Check if there was an error
                    if ($twilioResponse->errorCode) {
                        $errorStatus =  $twilioResponse->errorMessage;
                    }
                    $messagesid = $twilioResponse->sid;

                    $saveMesasge = new WhatsappSentMessage();
                    $saveMesasge->sub_institute_id = session()->get('sub_institute_id');
                    $saveMesasge->syear = session()->get('syear');
                    $saveMesasge->standard_id = $request->standard;
                    $saveMesasge->division_id = $request->division;
                    $saveMesasge->student_id = $student['id'];
                    $saveMesasge->message = $request->message;
                    $saveMesasge->attachment = $attachment;
                    $saveMesasge->sent_date = Carbon::today();
                    $saveMesasge->message_status = $messageStatus;
                    $saveMesasge->message_error =$errorStatus;
                    $saveMesasge->uri = $messagesid; // intstead of uri store message sid
                    $saveMesasge->created_by = session()->get('user_profile_id');
                    $saveMesasge->created_by_name = session()->get('name');
                    $saveMesasge->save();
                }
            }
            
        }

        if($i!=0){
            $res['status_code'] = 1;
            $res['message'] = "Message Sent to All Users";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Oops ! something went wrong";
        }

        // echo "i value : ".$i."<br>";
        // exit;
       /* foreach ($searchStudent as $student) {
            if (!empty($token)) {
                $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
                $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
                $client->messages->create(
                    'whatsapp:+91' . $student['mobile'],
                    [
                        "contentSid" => $prepareMessageBody['contentSid'],
                        "messagingServiceSid" => $messagingServiceSid,
                        "from" => "whatsapp:" . $token['user_whatsapp_no'],
                        "contentVariables" => $prepareMessageBody['contentVariables']
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
        }*/


        return is_mobile($type, 'whatsapp_send_messages.index', $res, "redirect");

    }
    public function whatsappUserDetailsDestroy(Request $request, $id)
    {
        if ($id > 0) {
            WhatappUserDetail::where('id', $id)->delete();
        }
        return redirect('whatsapp-user-details');
    }

    public function updateMessageStatus($sub_institute_id,$syear){
        $updateStatus = WhatsappSentMessage::where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->get()->toArray();
        foreach ($updateStatus as $key => $value) {
            if($value['uri']!=null && $value['message_status']!="read"){
                $messageSid = $value['uri']; // sid
                $token = WhatappUserDetail::where('sub_institute_id', $sub_institute_id)->first();
                $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
                $message = $client->messages($messageSid)->fetch();
                // Check the message status
                $messageStatus = $message->status;
                $update = WhatsappSentMessage::where('id',$value['id'])->update([
                    'message_status'=>$messageStatus,
                ]);
                
            }
        }
    }
}