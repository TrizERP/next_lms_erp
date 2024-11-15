<?php

namespace App\Http\Controllers;

use App\Models\IncomingMessage;
use App\Models\PayrollType;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;
use App\Models\WhatappUserDetail;
use App\Models\WhatsappSentMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        // DB::enableQueryLog();
        $data = WhatsappSentMessage::with('student')->with('standard')->with('division')->with('messages')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear]);

        if ($request->standard) {
            $data->where('standard_id', $request->standard);
        }

        if ($request->division) {
            $data->where('division_id', $request->division);
        }

        if ($request->from_date && $request->to_date) {
            $from_date = \Carbon\Carbon::parse($request->from_date)->format('Y-m-d');
            $to_date = \Carbon\Carbon::parse($request->to_date)->format('Y-m-d');
            $data->whereBetween('sent_date', [$from_date, $to_date]);
            // $data->whereRaw('created_at between "'.$from_date.'" and "'.$to_date.'"');
        }
        $data = $data->orderBy('id', 'DESC')->get();
        // dd(DB::getQueryLog($data));
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
        if ($type == "API") {
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }
        $update = $this->updateMessageStatus($sub_institute_id,$syear);
        // echo "<pre>";print_r($update);exit;

        $data['data'] = WhatsappSentMessage::with('student')->with('standard')->with('division')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->orderBy('id', 'DESC')->limit(2500)->get()->toArray();
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
            $imageUrl = $matches[1];
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
                $path = ltrim($parsedUrl['path'], '/');

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

        return [$textSections, $hrefLinks];
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
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
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
        $attachment = '';
        $token = WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->first();
        $searchStudent = SearchStudent($request->grade, $request->standard, $request->division, session()->get('sub_institute_id'));
        //$searchStudent = SearchStudent();


        list($textArray, $hrefArray) = $this->mediaFound($request->message);

        // Initialize prepareMessageBody array
        $prepareMessageBody = [];
        // check image
        preg_match('/<img[^>]+src="([^"]+)"/i', $request->message, $matches);
        // check image url
        preg_match('/(^|<\/a>)(.*?)(<a href="|$)/', $request->message, $match);

        // Check if an image source was found
        if (isset($matches[1]) || isset($match[1])) {
            sleep(40);
        }

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
            // $prepareMessageBody['contentSid'] = "HXe0114bc20670d1b3f92c854106ec4a81";
            $prepareMessageBody['contentSid'] = "HXc03a5ef4627d8843b071363756007a1b";
        }

        // echo "<pre>";print_r($prepareMessageBody);exit;
        $i = 0;
        if (isset($textArray[0])) {
            foreach ($request->sendNotification as $studentId => $on) {
                $student = tblstudentModel::where([['id', $studentId], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                if (!empty($token) && !empty($student) && $student['mobile'] != null && strlen($student['mobile']) == 10 && !in_array(substr($student['mobile'], 0, 1), [0, 1, 2, 3, 4, 5])) {
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
                    $errorStatus = $twilioResponse->uri;
                    // Check if there was an error
                    if ($twilioResponse->errorCode) {
                        $errorStatus = $twilioResponse->errorMessage;
                    }
                    $messagesid = $twilioResponse->sid;

                    if (isset($twilioResponse->errorCode) && $twilioResponse->errorCode!=null) {
                        $messageStatus .= ' (' . $twilioResponse->errorCode . ')';
                    }
                    $saveMesasge = new WhatsappSentMessage();
                    $saveMesasge->sub_institute_id = session()->get('sub_institute_id');
                    $saveMesasge->syear = session()->get('syear');
                    $saveMesasge->standard_id = $request->standard;
                    $saveMesasge->division_id = $request->division;
                    $saveMesasge->student_id = $student['id'];
                    $saveMesasge->message = $request->message;
                    $saveMesasge->whatsapp_number = "+91" . $student['mobile'];
                    $saveMesasge->attachment = $attachment;
                    $saveMesasge->sent_date = Carbon::today();
                    $saveMesasge->message_status = $messageStatus;
                    $saveMesasge->message_error = $errorStatus;
                    $saveMesasge->uri = $messagesid; // intstead of uri store message sid
                    $saveMesasge->created_by = session()->get('user_profile_id');
                    $saveMesasge->created_by_name = session()->get('name');
                    $saveMesasge->save();
                }
            }

        }

        if ($i != 0) {
            $res['status_code'] = 1;
            $res['message'] = "Message Sent to All Users";
        } else {
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


    public function whatsappSendReplyMessageStore(Request $request)
    {
        //return $request->all();
        $type = $request->type ?? '';
        $request->validate([
            'message' => 'required',
            'wid' => 'required'
        ]);
        $attachment = '';
        $token = WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->first();


        list($textArray, $hrefArray) = $this->mediaFound($request->message);

        // Initialize prepareMessageBody array
        $prepareMessageBody = [];
        // check image
        preg_match('/<img[^>]+src="([^"]+)"/i', $request->message, $matches);
        // check image url
        preg_match('/(^|<\/a>)(.*?)(<a href="|$)/', $request->message, $match);

        // Check if an image source was found
        /*  if (isset($matches[1]) || isset($match[1])) {
              sleep(40);
          }*/

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

        // echo "<pre>";print_r($prepareMessageBody);exit;
        $i = 0;
        if (isset($textArray[0])) {
            //foreach ($request->sendNotification as $studentId => $on) {
            //$student = tblstudentModel::where([['id',$studentId],['sub_institute_id',session()->get('sub_institute_id')]])->first();
            //if (!empty($token) && !empty($student) && $student['mobile'] != null && strlen($student['mobile'])==10 && !in_array(substr($student['mobile'],0,1),[0,1,2,3,4,5])) {

            $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
            $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
            $twilioResponse = $client->messages->create(
                'whatsapp:' . $request->wid,
                [
                    "contentSid" => $prepareMessageBody['contentSid'],
                    "messagingServiceSid" => $messagingServiceSid,
                    "from" => "whatsapp:+91" . $token['user_whatsapp_no'],
                    "contentVariables" => $prepareMessageBody['contentVariables'],
                ]
            );
            // Check message status
            $messageStatus = $twilioResponse->status;
            $errorStatus = $twilioResponse->uri;
            // Check if there was an error
            if ($twilioResponse->errorCode) {
                $errorStatus = $twilioResponse->errorMessage;
            }
            $messagesid = $twilioResponse->sid;

            $incommigMessage = new IncomingMessage();
            $incommigMessage->message_sid = $errorStatus;
            $incommigMessage->whatsapp_number = "+" . $request->wid;
            $incommigMessage->account_sid = $errorStatus;
            $incommigMessage->type = "outgoing";
            $incommigMessage->message = $request->message;
            $incommigMessage->message_date = Carbon::now();
            $incommigMessage->save();
        }
        //}

        //}

        if ($i != 0) {
            $res['status_code'] = 1;
            $res['message'] = "Message Sent to All Users";
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Oops ! something went wrong";
        }

        IncomingMessage::where('whatsapp_number', "+" . $request->wid)->update([
            'is_seen' => 1
        ]);


        return is_mobile($type, ['route' => 'whatsapp_show_reply', 'id' => $request->wid], [], "redirect", [], 1);
    }

    public function whatsappUserDetailsDestroy(Request $request, $id)
    {
        if ($id > 0) {
            WhatappUserDetail::where('id', $id)->delete();
        }
        return redirect('whatsapp-user-details');
    }

    public function updateMessageStatus($sub_institute_id, $syear)
    {
        $updateStatus = WhatsappSentMessage::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->whereRaw('message_status in ("accepted","delivered","sent","queued","sending")')->orderBy('id','DESC')->limit(500)->get()->toArray();
        foreach ($updateStatus as $key => $value) {
            if ($value['uri'] != null) {
                $messageSid = $value['uri']; // sid
                $token = WhatappUserDetail::where('sub_institute_id', $sub_institute_id)->orderBy('id', 'DESC')->first();
                $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
                $message = $client->messages($messageSid)->fetch();
                // echo "<pre>";print_r($message);exit;
                // Check the message status
                $messageStatus = $message->status;
                // Check if error_code exists and append it to the status if present
                if (isset($message->errorCode) && $message->errorCode!=null) {
                    $messageStatus .= ' (' . $message->errorCode . ')';
                }
                // $messageStatus = $message->status.' ('.$message->error_code.')';
                $update = WhatsappSentMessage::where('id', $value['id'])->update([
                    'message_status' => $messageStatus,
                ]);

            }
        }
    }

    public function updateDeliveryStatus(Request $request)
    {
        WhatsappSentMessage::where('uri', $request->MessageSid)->update([
            'message_status' => $request->MessageStatus,
        ]);
        return true;
    }

    public function incomingMessage(Request $request)
    {
        $incommigMessage = new IncomingMessage();
        $incommigMessage->message_sid = $request->SmsMessageSid;
        $incommigMessage->whatsapp_number = "+" . $request->WaId;
        $incommigMessage->account_sid = $request->AccountSid;
        $incommigMessage->type = "incoming";
        $incommigMessage->message = $request->Body;
        $incommigMessage->message_date = Carbon::now();
        $incommigMessage->save();
        return true;
    }

    public function whatsappShowReply(Request $request, $wid)
    {
        $type = $request->input('type');
        $whatsappChats['chats'] = IncomingMessage::where('whatsapp_number', $wid)->get();
        $whatsappChats['wid'] = $wid;
        IncomingMessage::where('whatsapp_number', $wid)->update([
            'is_seen' => 1
        ]);
        return is_mobile($type, "whatsapp/whatsapp_reply/index", $whatsappChats, "view");
    }

    public function whatsappCRM(Request $request)
    {
        // echo "<pre>";print_r($re);exit;
        $numbers = $request->get('number');  
        $message = $request->get('message');
        $file_url = $request->get('file_url');
        // check has file and which template should be use
        if (empty($file_url)) {
            // Ensure textArray is not empty before accessing it
            $prepareMessageBody['contentVariables'] = json_encode([
                "1" => isset($message) ? $message: null,
            ]);
            $prepareMessageBody['contentSid'] = "HX3a292a1ee72924adb532e807a2ed9b36";
        } else {
             // save file in erp
            $fileContents = file_get_contents($file_url);
            $fileName = basename($file_url); // e.g., '13_11_24_whatsapptest.jpeg'
            $filePath = public_path('whatsapp/wp_sent_files/' . $fileName);
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            // Save the file content to the specified path
            $isSaved = file_put_contents($filePath, $fileContents);
            // save file in erp end
            // Ensure hrefArray and textArray have elements before accessing them
            $fileMess = '<img alt="" src="'.$file_url.'" style="width: 1600px; height: 1600px;" />'.$message;
            list($textArray, $hrefArray) = $this->mediaFound($fileMess);
            $attachment = isset($hrefArray[0]) ? $hrefArray[0] : null;
            $prepareMessageBody['contentVariables'] = json_encode([
                "1" => isset($attachment) ? $attachment : null,
                "2" => isset($message) ? $message : null,
            ]);
            $prepareMessageBody['contentSid'] = "HXc03a5ef4627d8843b071363756007a1b";
        }
        // echo "<pre>";print_r($prepareMessageBody);exit;
        // send message to all users
        $response = [];
        $numArr = !empty($numbers) ? explode(',',$numbers) : [];
        if(!empty($numArr)){
            $token = WhatappUserDetail::where('sub_institute_id',1)->first();
            foreach ($numArr as $key => $value) {
                $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
                $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
                $twilioResponse = $client->messages->create(
                    'whatsapp:+91' . $value,
                    [
                        "contentSid" => $prepareMessageBody['contentSid'],
                        "messagingServiceSid" => $messagingServiceSid,
                        "from" => "whatsapp:+91" . $token['user_whatsapp_no'],
                        "contentVariables" => $prepareMessageBody['contentVariables'],
                    ]
                );
                // Check message status
                $messageStatus = $twilioResponse->status;
                $messageUri = $twilioResponse->uri;
                // Check if there was an error
                $errorStatus='';
                if ($twilioResponse->errorCode) {
                    $errorStatus = $twilioResponse->errorMessage;
                }
                $messagesid = $twilioResponse->sid;

                $response[$value]=[
                    "status"=>$messageStatus,
                    "message_uri"=>$messageUri,
                    "message_error"=>$errorStatus,
                    "message_id"=>$messagesid,
                ];
            }
        }
        // You can now use $number and $message as needed
        return response()->json($response);
    }

    public function updateCRMWhatsappStatus(Request $request){
        $response = [];
        foreach ($request->messageIds as $id => $messageId) {
            $token = WhatappUserDetail::where('sub_institute_id', 1)->orderBy('id', 'DESC')->first();
            $client = new Client($token['user_whatsapp_sid'], $token['user_whatsapp_token']);
            $message = $client->messages($messageId)->fetch();
            // echo "<pre>";print_r($message);exit;
            // Check the message status
            $response[$id]['status'] = $message->status;
            $response[$id]['error'] ='';
            // Check if error_code exists and append it to the status if present
            if (isset($message->errorCode) && $message->errorCode!=null) {
                $response[$id]['error'] = $message->errorCode;
            }

        return response()->json($response);

        }
           
    }
}