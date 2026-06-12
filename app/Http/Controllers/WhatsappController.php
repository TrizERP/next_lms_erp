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
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
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
        }
        $data = $data->orderBy('id', 'DESC')->get();
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

        $data['data'] = WhatsappSentMessage::with('student')->with('standard')->with('division')->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->orderBy('id', 'DESC')->limit(2500)->get()->toArray();
        return is_mobile($type, 'whatsapp.whatsapp_send_messages.index', $data, "view");
    }

    public function whatsappUserDetailsCreate(Request $request, $id = 0)
    {
        $type = $request->type ?? '';

        if ($id) {
            $WhatsappUserDetail = WhatappUserDetail::find($id);
            return is_mobile($type, 'whatsapp.whatsapp_user_details.create', $WhatsappUserDetail, "view");
        }
        $WhatsappUserDetail['user_whatsapp_no'] = '';
        $WhatsappUserDetail['cloud_api_access_token'] = '';
        $WhatsappUserDetail['cloud_api_phone_number_id'] = '';
        $WhatsappUserDetail['created_by'] = '';
        $WhatsappUserDetail['id'] = 0;
        return is_mobile($type, 'whatsapp.whatsapp_user_details.create', $WhatsappUserDetail, "view");
    }

    public function whatsappSendMessageCreate(Request $request, $id = 0)
    {
        $type = $request->type ?? '';
        if ($id) {
            $WhatsappUserDetail = WhatsappSentMessage::find($id);
            return is_mobile($type, 'whatsapp.whatsapp_user_details.create', $WhatsappUserDetail, "view");
        }
        return is_mobile($type, 'whatsapp.whatsapp_send_messages.create', [], "view");
    }

    public function whatsappUserDetailsStore(Request $request)
    {
        $type = $request->type ?? '';
        $request->validate([
            'user_whatsapp_no' => 'required',
            'cloud_api_access_token' => 'required',
            'cloud_api_phone_number_id' => 'required',
        ]);

        if ($request->id > 0) {
            $payrollType = WhatappUserDetail::find($request->id);
        } else {
            $payrollType = new WhatappUserDetail();
        }
        $payrollType->user_whatsapp_no = $request->user_whatsapp_no;
        $payrollType->cloud_api_access_token = $request->cloud_api_access_token;
        $payrollType->cloud_api_phone_number_id = $request->cloud_api_phone_number_id;
        $payrollType->api_type = 'cloud_api';
        $payrollType->sub_institute_id = session()->get('sub_institute_id');
        $payrollType->created_by = session()->get('user_profile_id');
        $payrollType->created_by_name = session()->get('name');
        $payrollType->save();

        return is_mobile($type, 'whatsapp-user-details', [], "redirect");
    }

    public function mediaFound($message)
    {
        preg_match('/<img[^>]+src="([^"]+)"/i', $message, $matches);
        if (isset($matches[1])) {
            $imageUrl = $matches[1];
            $message = preg_replace('/<img[^>]*>/i', '<a href="' . $imageUrl . '">' . $imageUrl . '</a>', $message);
        }
        $textPattern = '/(^|<\/a>)(.*?)(<a href="|$)/us';
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
        foreach ($hrefMatches[1] as $href) {
            $parsedUrl = parse_url($href);
            if (isset($parsedUrl['path'])) {
                $path = ltrim($parsedUrl['path'], '/');
                if (isset($parsedUrl['query'])) {
                    $path .= '?' . $parsedUrl['query'];
                }
                if (isset($parsedUrl['fragment'])) {
                    $path .= '#' . $parsedUrl['fragment'];
                }
                $hrefLinks[] = $path;
            } else {
                $hrefLinks[] = $href;
            }
        }

        return [$textSections, $hrefLinks];
    }

    public function index(Request $request)
    {
        if (session()->has('data')) {
            $data_arr = session('data');
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
        $type = $request->type ?? '';
        $request->validate([
            'message' => 'required'
        ]);

        $token = WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->first();
        if (empty($token) || empty($token->cloud_api_access_token) || empty($token->cloud_api_phone_number_id)) {
            $res['status_code'] = 0;
            $res['message'] = "WhatsApp configuration missing";
            return is_mobile($type, 'whatsapp_send_messages.index', $res, "redirect");
        }

        list($textArray, $hrefArray) = $this->mediaFound($request->message);

        $i = 0;
        if (isset($textArray[0])) {
            foreach ($request->sendNotification as $studentId => $on) {
                $student = tblstudentModel::where([['id', $studentId], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                if (!empty($student) && $student['mobile'] != null && strlen($student['mobile']) == 10 && !in_array(substr($student['mobile'], 0, 1), [0, 1, 2, 3, 4, 5])) {
                    if ($i > 0) {
                        usleep(300000);
                    }
                    $i++;
                    $cloudResponse = $this->sendWhatsappCloudApi(
                        '91' . $student['mobile'],
                        $request->message,
                        $token->cloud_api_access_token,
                        $token->cloud_api_phone_number_id,
                        !empty($hrefArray) ? $hrefArray[0] : null
                    );
                    if (isset($cloudResponse['error']) || (isset($cloudResponse['messages'][0]['error']) && !empty($cloudResponse['messages'][0]['error']))) {
                        $errorStatus = json_encode($cloudResponse['error'] ?? $cloudResponse['messages'][0]['error']);
                        $messageStatus = 'failed';
                        $messagesid = null;
                    } else {
                        $messageStatus = isset($cloudResponse['messages'][0]['status']) ? $cloudResponse['messages'][0]['status'] : 'unknown';
                        $errorStatus = null;
                        $messagesid = isset($cloudResponse['messages'][0]['id']) ? $cloudResponse['messages'][0]['id'] : null;
                    }
                    $saveMesasge = new WhatsappSentMessage();
                    $saveMesasge->sub_institute_id = session()->get('sub_institute_id');
                    $saveMesasge->syear = session()->get('syear');
                    $saveMesasge->standard_id = $request->standard;
                    $saveMesasge->division_id = $request->division;
                    $saveMesasge->student_id = $student['id'];
                    $saveMesasge->message = $request->message;
                    $saveMesasge->whatsapp_number = "+91" . $student['mobile'];
                    $saveMesasge->attachment = !empty($hrefArray) ? $hrefArray[0] : null;
                    $saveMesasge->sent_date = Carbon::today();
                    $saveMesasge->message_status = $messageStatus;
                    $saveMesasge->message_error = $errorStatus;
                    $saveMesasge->uri = $messagesid;
                    $saveMesasge->api_type = 'cloud_api';
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
        return is_mobile($type, 'whatsapp_send_messages.index', $res, "redirect");
    }

    public function whatsappSendReplyMessageStore(Request $request)
    {
        $type = $request->type ?? '';
        $request->validate([
            'message' => 'required',
            'wid' => 'required'
        ]);

        $token = WhatappUserDetail::where('sub_institute_id', session()->get('sub_institute_id'))->first();
        if (empty($token) || empty($token->cloud_api_access_token) || empty($token->cloud_api_phone_number_id)) {
            $res['status_code'] = 0;
            $res['message'] = "WhatsApp configuration missing";
            return is_mobile($type, ['route' => 'whatsapp_show_reply', 'id' => $request->wid], [], "redirect", [], 1);
        }

        list($textArray, $hrefArray) = $this->mediaFound($request->message);

        $i = 0;
        if (isset($textArray[0])) {
            $i++;
            $cloudResponse = $this->sendWhatsappCloudApi(
                $request->wid,
                $request->message,
                $token->cloud_api_access_token,
                $token->cloud_api_phone_number_id,
                !empty($hrefArray) ? $hrefArray[0] : null
            );
            $messageStatus = isset($cloudResponse['messages'][0]['status']) ? $cloudResponse['messages'][0]['status'] : 'unknown';
            $errorStatus = isset($cloudResponse['messages'][0]['error']) ? json_encode($cloudResponse['messages'][0]['error']) : null;
            $messagesid = isset($cloudResponse['messages'][0]['id']) ? $cloudResponse['messages'][0]['id'] : null;

            $incommigMessage = new IncomingMessage();
            $incommigMessage->message_sid = $messagesid;
            $incommigMessage->whatsapp_number = "+" . $request->wid;
            $incommigMessage->account_sid = $messagesid;
            $incommigMessage->type = "outgoing";
            $incommigMessage->message = $request->message;
            $incommigMessage->message_date = Carbon::now();
            $incommigMessage->save();
        }

        if ($i != 0) {
            $res['status_code'] = 1;
            $res['message'] = "Message Sent";
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
    }

    public function updateDeliveryStatus(Request $request)
    {
        return true;
    }

    public function sendWhatsappCloudApi($to, $message, $accessToken, $phoneNumberId, $imageUrl = null)
    {
        $url = "https://graph.facebook.com/v25.0/{$phoneNumberId}/messages";
        $client = new HttpClient();

        $message = trim(preg_replace('/\s+/', ' ', strip_tags($message)));

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to
        ];

        if (!empty($imageUrl)) {
            $payload["type"] = "image";
            $payload["image"] = [
                "link" => $imageUrl,
                "caption" => $message
            ];
        } else {
            $payload["type"] = "template";
            $payload["template"] = [
                "name" => "parents_",
                "language" => ["code" => "en_US"],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => [
                            [
                                "type" => "text",
                                "text" => $message
                            ]
                        ]
                    ]
                ]
            ];
        }

        Log::info('WhatsApp Payload', $payload);

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload
            ]);

            return json_decode($response->getBody(), true);
        } catch (ClientException $exception) {
            $errorResponse = $exception->getResponse();
            $errorBody = $errorResponse ? json_decode($errorResponse->getBody()->getContents(), true) : null;

            Log::error('WhatsApp Cloud API error', [
                'payload' => $payload,
                'response' => $errorBody,
                'message' => $exception->getMessage(),
            ]);

            return $errorBody ?: [
                'error' => [
                    'message' => $exception->getMessage(),
                ]
            ];
        }
    }
    

    public function incomingMessage(Request $request)
    {
        $incommigMessage = new IncomingMessage();
        $incommigMessage->message_sid = $request->SmsMessageSid ?? $request->id ?? null;
        $incommigMessage->whatsapp_number = isset($request->WaId) ? "+" . $request->WaId : ($request->from ?? null);
        $incommigMessage->account_sid = $request->AccountSid ?? null;
        $incommigMessage->type = "incoming";
        $incommigMessage->message = $request->Body ?? $request->text ?? null;
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
        $numbers = $request->get('number');
        $message = $request->get('message');
        $file_url = $request->get('file_url');

        $token = WhatappUserDetail::where('sub_institute_id', 1)->first();
        if (empty($token) || empty($token->cloud_api_access_token) || empty($token->cloud_api_phone_number_id)) {
            return response()->json([]);
        }

        $imageUrl = null;
        if (!empty($file_url)) {
            $fileContents = file_get_contents($file_url);
            $fileName = basename($file_url);
            $filePath = public_path('whatsapp/wp_sent_files/' . $fileName);
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            file_put_contents($filePath, $fileContents);
            $imageUrl = $file_url;
        }

        $response = [];
        $numArr = !empty($numbers) ? explode(',', $numbers) : [];
        if (!empty($numArr)) {
            foreach ($numArr as $value) {
                $cloudResponse = $this->sendWhatsappCloudApi(
                    '91' . trim($value),
                    $message,
                    $token->cloud_api_access_token,
                    $token->cloud_api_phone_number_id,
                    $imageUrl
                );
                $response[$value] = [
                    "status" => isset($cloudResponse['messages'][0]['status']) ? $cloudResponse['messages'][0]['status'] : 'unknown',
                    "message_uri" => null,
                    "message_error" => isset($cloudResponse['messages'][0]['error']) ? json_encode($cloudResponse['messages'][0]['error']) : null,
                    "message_id" => isset($cloudResponse['messages'][0]['id']) ? $cloudResponse['messages'][0]['id'] : null,
                ];
            }
        }
        return response()->json($response);
    }

    public function updateCRMWhatsappStatus(Request $request)
    {
        $response = [];
        $token = WhatappUserDetail::where('sub_institute_id', 1)->orderBy('id', 'DESC')->first();
        if (empty($token) || empty($token->cloud_api_access_token) || empty($token->cloud_api_phone_number_id)) {
            return response()->json($response);
        }
        $client = new HttpClient();
        foreach ($request->messageIds as $id => $messageId) {
            $url = "https://graph.facebook.com/v25.0/{$messageId}?fields=status";
            $apiResponse = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->cloud_api_access_token,
                ]
            ]);
            $body = json_decode($apiResponse->getBody(), true);
            $response[$id]['status'] = $body['status'] ?? 'unknown';
            $response[$id]['error'] = '';
        }
        return response()->json($response);
    }
}
