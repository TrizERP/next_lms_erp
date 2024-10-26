<?php

namespace App\Http\Controllers\front_desk\syllabus;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use PDF;

class syllabusController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();

        $type = $request->input('type');

        return is_mobile($type, "front_desk/syllabus/show", $school_data, "view");
    }

    function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_profile_id = session()->get('user_profile_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');
        $marking_period_id = session()->get('term_id');

        if (strtoupper($user_profile_name) == 'TEACHER') {
            $result = DB::table("syllabus as c")
                ->join('standard as s', function ($join) use ($marking_period_id) {
                    $join->on("s.id", "=", "c.standard_id");
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->on("su.subject_id", "=", "c.subject_id")->whereRaw("su.standard_id = c.standard_id");
                })
                ->join('timetable as t', function ($join) {
                    $join->on("t.standard_id", "=", "s.id")->whereRaw("t.subject_id = su.subject_id AND t.sub_institute_id = su.sub_institute_id");
                })
                ->join('tbluser as u',function($q){
                    $q->on('u.id','=','c.created_by');
                })
                ->leftJoin('lms_curriculum as lc','lc.id','c.curriculum_id')
                ->selectRaw('c.*,s.name std_name ,su.display_name,,CONCAT_WS(" ",COALESCE(u.first_name,"-"),COALESCE(u.last_name,"-")) as createdBy,lc.curriculum_name')
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->where("t.teacher_id", "=", $user_id)
                ->orderBy('c.id','DESC')
                ->get()->toArray();
        } else {
            $result = DB::table("syllabus as c")
                ->join('standard as s', function ($join) use ($marking_period_id) {
                    $join->on("s.id", "=", "c.standard_id");
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->on("su.subject_id", "=", "c.subject_id")->whereRaw("su.standard_id = c.standard_id");
                })
                ->join('tbluser as u',function($q){
                    $q->on('u.id','=','c.created_by');
                })
                ->leftJoin('lms_curriculum as lc','lc.id','c.curriculum_id')
                ->selectRaw('c.*,s.name std_name ,su.display_name,CONCAT_WS(" ",COALESCE(u.first_name,"-"),COALESCE(u.last_name,"-")) as createdBy,lc.curriculum_name')
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->orderBy('c.id','DESC')
                ->get()->toArray();
        }

        return $result;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {

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
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        if($type=="API"){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
        }
        $file_name = "";
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('attachment').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "attachment_".$name.'.'.$ext;
            // $path = $file->storeAs('public/syllabus/', $file_name);
            // store in digital ocean 
            // Storage::disk('digitalocean')->put('public/syllabus/' . $file_name, $file, 'public');
            Storage::disk('digitalocean')->putFileAs('public/syllabus/', $file, $file_name, 'public');
        }
       else if($request->has('aiOutput') && !empty($request->aiOutput)) {
            $html = '<!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=erpice-width, initial-scale=1.0">
                        </head>
                    <body>
                        ' . $request->aiOutput . '
                    </body>
                    </html>';
            // Load the HTML content into the PDF
            $pdf = PDF::loadHTML($html);

            // Generate a unique name for the PDF file
            $name = date('YmdHis');
            $file_name = "attachment_" . $name . '.pdf';
        
            // Store the PDF file to the specified storage disk
            Storage::disk('digitalocean')->put('public/syllabus/' . $file_name, $pdf->output(), 'public');
        }
        $grade = $request->grade;
        $standard = $request->standard;
        $subject = $request->subject;
        $date = $request->date ?? null;
        $types = $request->types;
        $month = ($types=="Monthly") ? $request->month : null;
        $title = $request->title;
        $message = $request->message;
        $no_of_periods = $request->no_of_periods  ?? null;
        $no_of_days = $request->no_of_days  ?? null;
        $assement_tool = $request->assement_tool  ?? null;
        // 25-10-2024
        $curriculum_id = $request->curriculum_id;
        $syllabus_objectives = $request->syllabus_objectives;
        $learning_outcomes = $request->learning_outcomes;
        $suggested_materials = $request->suggested_materials;
        $progress_tracking = $request->progress_tracking;
        // echo "<pre>";print_r($request->all());exit;

        $values = [
            'syear'            => $syear,
            'standard_id'      => $standard,
            'subject_id'       => $subject,
            'no_of_days'       => $no_of_days,
            'no_of_periods'    => $no_of_periods,
            'types'            => $types,
            'months'           => $month,
            'title'            => $title,
            'message'          => $message,
            'assesment_tool'   => $assement_tool,
            'file_name'        => $file_name,
            'date_'            => $date,
            'curriculum_id'=>$curriculum_id,
            'objectives'=>$syllabus_objectives,
            'learning_outcomes'=>$learning_outcomes,
            'suggested_materials'=>$suggested_materials,
            'progress_tracking'=>$progress_tracking,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
        DB::table('syllabus')->insert($values);

        $res = [
            "status_code" => 1,
            "message"     => "Done",
        ];

        $type = $request->input('type');

        return is_mobile($type, "syllabus.index", $res, "redirect");
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


    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $data = DB::table('syllabus')->where('id',$id)->first();
        $filename=$data->file_name;
        if(isset($filename)){
            $file_path = 'public/syllabus/' . $filename;
            if (Storage::disk('digitalocean')->exists($file_path)) {
                Storage::disk('digitalocean')->delete($file_path);
                if (!Storage::disk('digitalocean')->exists($file_path)) {
                    $filename=null;
                }   
            } 
        }
        
        DB::table('syllabus')->where(["Id" => $id])->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "syllabus.index", $res, "redirect");
    }

    public function GenrateAISyllabus(Request $request){
        // echo "<pre>";print_r($request->all());
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=="API"){
            $sub_institute_id= $request->sub_institute_id;
            $syear= $request->syear;
        }
        $standard_id = $request->standard_id;
        $standard = $request->standard;
        $subject_id = $request->subject_id;
        $subject = $request->subject;
        $date = $request->date;
        $types = $request->types;
        $month = $request->month;
        $title = $request->title;
        $message = $request->message;
        $no_of_days = $request->no_of_days;
        $no_of_periods = $request->no_of_periods;
        $assement_tool = $request->assement_tool;

        // create prompt 
        $prompt = 'Generate {$types} CBSE syllabus ';
        if($types=="Yearly"){
            $prompt .= "month wise, starting month june current year and ending march month next year, standard = {$standard} and subject = {$subject}. And {$message}";
        }else if($types=="Monthly"){
            $prompt .= "week wise, standard = {$standard} and subject = {$subject} as per day {$no_of_days} and period {$no_of_periods}. And {$assement_tool} and {$message}";
        }else{
            $prompt .= "day wise, standard = {$standard} and subject = {$subject} AND {$message}";  
            if(isset($date)){
                $prompt .= " AND date {$date}";
            }
        }
        // echo "<pre>";print_r($prompt);exit;
        $apiKey ='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'; //'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';
        $endpoint = "https://api.openai.com/v1/chat/completions";

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ]
            ],
            "temperature" => 0.7,
            "max_tokens" => 2000,
            "top_p" => 1,
            "frequency_penalty" => 0,
            "presence_penalty" => 0,
            "stop" => ["11."]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post($endpoint, $data)->json();

        if (isset($response['choices'][0]['message']['content'])) {
          
            $output = $response['choices'][0]['message']['content'];
        }else{
            $output = $response;
        }

        // echo "<pre>";print_r($output);exit;
        
        $returnReaponse = [
            "AI_response" =>$output,
        ];
        return $returnReaponse;
    }
}
