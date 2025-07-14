<?php

namespace App\Http\Controllers\front_desk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Facades\Storage;
use App\Models\front_desk\classWorkModel;
use DB;

class classworkAttachmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if(in_array($type,['API','JSON'])){
             try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];

                return response()->json($response, 401);
            }
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required',
                'syear' => 'required',
                'user_id' => 'required',
            ]);

            if($validator->fails()){
                $response = ['status' => '0', 'message' => $validator->errors()->first()];

                return response()->json($response, 401);
            }

            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
        }

        $res['sentData'] = classWorkModel::with('student')->with('createdBy')
                ->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
                ->whereRaw('created_at LIKE "'.date('Y-m-d').'%"')
                ->whereNull('deleted_at')
                ->get()->toArray();
        // echo "<pre>";print_r($res['sentData']);exit;
        return is_mobile($type, "front_desk/attachment/index", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if(in_array($type,['API','JSON'])){
             try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];

                return response()->json($response, 401);
            }
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required',
                'syear' => 'required',
                'user_id' => 'required',
            ]);

            if($validator->fails()){
                $response = ['status' => '0', 'message' => $validator->errors()->first()];

                return response()->json($response, 401);
            }

            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
        }
        $res['from_date'] = $from_date = $request->has('from_date') ? $request->from_date : date('Y-m-d 00:00:00'); // Default: last 30 days

        $res['to_date'] = $to_date = $request->has('to_date') ? $request->to_date . ' 23:59:59' : date('Y-m-d 23:59:59'); // Default: today until end of day

        $res['sentData'] = classWorkModel::with(['student', 'createdBy', 'updatedBy'])
            ->where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear
            ])
            ->whereBetween('created_at', [$from_date, $to_date])
            ->whereNull('deleted_at')
            ->get()
            ->toArray();
        // echo "<pre>";print_r($res['sentData']);exit;
        return is_mobile($type, "front_desk/attachment/report", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if(in_array($type,['API','JSON'])){
             try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];

                return response()->json($response, 401);
            }
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required',
                'syear' => 'required',
                'user_id' => 'required',
                'files' => 'required',
                'title' => 'required',
            ]);

            if($validator->fails()){
                $response = ['status' => '0', 'message' => $validator->errors()->first()];

                return response()->json($response, 401);
            }

            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
        }

        $folderFiles = $request->file('files');
        $fileTitle = $request->title;
        $i=0;

        foreach ($folderFiles as $key => $file) {
            $originalNameOfFile = $file->getClientOriginalName();
            $enrollmentNo = pathinfo($originalNameOfFile, PATHINFO_FILENAME);
            $fileExtension = $file->getClientOriginalExtension();
            $fileName = date('YmdHis')."_".$originalNameOfFile;
            // get studentData 
            $studentData = SearchStudent("", "", "", $sub_institute_id, $syear,"", "", "","", $enrollmentNo,"", "","");
            // echo $key."<pre>";print_r([$enrollmentNo,$originalNameOfFile,$fileExtension]);
            // echo $key."<pre>";print_r($studentData);
            // if student exits then send notifiction and store data into database and files into digital ocean classwork_attachment folder
            if(isset($studentData[0])){
                $studentVal = $studentData[0];
                // check already exists
                $check =classWorkModel::where(['title'=>$fileTitle,'student_id'=>$studentVal['id'],'sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
                ->whereNull('deleted_at')
                ->whereRaw('created_at LIKE "'.date('Y-m-d').'%"')
                ->first();

                // Storage::disk('digitalocean')->put('public/classwork_attachment/' . $fileName,$file,'public');
                Storage::disk('digitalocean')->putFileAs('public/classwork_attachment/', $file, $fileName, 'public');
                $filePath = Storage::disk('digitalocean')->url('public/classwork_attachment/' . $fileName);

                if(isset($check->id)){
                    $update =classWorkModel::where(['id'=>$check->id,'student_id'=>$studentVal['id'],'sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
                            ->update([
                                'file_path'=>$filePath,
                                'updated_by'=>$user_id,
                                'updated_at'=>now(),
                            ]);
                    if($update){
                        $i++;
                    }
                }
                else{
                    $insert = classWorkModel::insert([
                        'title' => $fileTitle,
                        'student_id' => $studentVal['id'],
                        'file_path' => $filePath,
                        'sub_institute_id' => $sub_institute_id,
                        'syear' => $syear,
                        'created_by' => $user_id,
                        'created_at' => now(),
                    ]);
                    if($insert){
                        $i++;
                    }
                }

                // send notification start
                 $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
                                $schoolName = $schoolData[0]['SchoolName'];
                                $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo']; 
                 $pushMessage = $fileTitle." has been added in Classwork for date : ".date('d-m-Y',
                                                strtotime(now()));

                                        $app_notification_content = [
                                            'NOTIFICATION_TYPE'        => 'Classwork',
                                            'NOTIFICATION_DATE'        => now(),
                                            'STUDENT_ID'               => $studentVal->id,
                                            'NOTIFICATION_DESCRIPTION' => $pushMessage,
                                            'STATUS'                   => 0,
                                            'SUB_INSTITUTE_ID'         => $sub_institute_id,
                                            'SYEAR'                    => $syear,
                                            'SCREEN_NAME'              => 'Classwork',
                                            'CREATED_BY'               => $user_id,
                                            'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
                                        ];

                                        $gcm_data = DB::table("gcm_users")
                                            ->where("mobile_no", "=", $studentVal->mobile)
                                            ->where("sub_institute_id", "=", $sub_institute_id)
                                            ->groupBy("gcm_regid")
                                            ->get()->toArray();

                                        $gcmRegIds = [];
                                        if (count($gcm_data) > 0) {
                                            foreach ($gcm_data as $key1 => $val1) {
                                                array_push($gcmRegIds, $val1->gcm_regid);
                                            }
                                        }
                                        sendNotification($app_notification_content);

                                        $bunch_arr = array_chunk($gcmRegIds, 1000);
                                        if (! empty($bunch_arr)) {
                                            foreach ($bunch_arr as $val) {
                                                if (isset($val, $pushMessage)) {
                                                    $noti_type = 'Classwork';
                                                    $message = [
                                                        'body'    => $pushMessage, 'TYPE' => $noti_type,
                                                        'USER_ID' => $student_id, 'title' => $schoolName.' - '.$noti_type,
                                                        'image'   => $schoolLogo,
                                                    ];
                                                    /*
                                                    Rajesh: stop push notification for photo-video gallery
                                                    $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                                                    */
                                                    // sendNotification($app_notification_content);
                                                }
                                            }
                                        }
                // send notification end 
            }
            
        }
        // exit;
        $res['status']=0;
        $res['message'] = "Failed To Attachment(s) Sent.";

        if($i > 0){
            $res['status'] = 1;
            $res['message'] = "Attachment(s) Sent successfully.";
        }
        return is_mobile($type, "sendAttachment.index", $res);
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
    public function destroy(Request $request,$id)
    {
         $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if(in_array($type,['API','JSON'])){
             try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];

                return response()->json($response, 401);
            }
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required',
                'syear' => 'required',
                'user_id' => 'required',
            ]);

            if($validator->fails()){
                $response = ['status' => '0', 'message' => $validator->errors()->first()];

                return response()->json($response, 401);
            }

            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
        }

        $delete = classWorkModel::where('id', $id)->update(['deleted_at' => now(), 'deleted_by' => $user_id]);

        $res['status']=0;
        $res['message'] = "Failed To Attachment(s) Delete.";

        if($delete > 0){
            $res['status'] = 1;
            $res['message'] = "Attachment(s) Deleted successfully.";
        }
        return is_mobile($type, "sendAttachment.index", $res);
    }
}
