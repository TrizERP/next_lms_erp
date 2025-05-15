<?php

namespace App\Http\Controllers\lms\teacher_resource;

use App\Http\Controllers\Controller;
use App\Models\lms\teacherResourceModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use DB;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Storage;

class lms_teacherResourceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request); 		
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";                  
        $res['data'] = $data;    
        // 27-02-2025 starts     
        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($request) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $request->get('chapter_id'));
            })->where(function ($q) use ($request) {
                $q->where('topic_id', '=', 0)
                    ->orWhere('topic_id', $request->get('topic_id'));
            })->get()->toArray();

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $res['lms_mapping_type'] = $lms_mapping_type;
        // echo "<pre>";print_r($data['mapVal']);exit;
        // 27-02-2025 end     

        return is_mobile($type,'lms/teacher_resource/show_teacher_resource',$res,"view");  
    }

    public function getData($request){
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
            $year = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            $syear =$year[0]->syear;
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
        }
        
        $standard_id = $request->get("standard_id");
        $chapter_id = $request->get("chapter_id");
        $subject_id = $request->get("subject_id");
        $topic_id = '';
        if($request->has('topic_id'))
        {
            $topic_id = $request->get("topic_id");
        }

        $data['TR_data'] = teacherResourceModel::select("lms_teacher_resource.*","c.chapter_name","t.name as topic_name")
                    ->join('chapter_master as c','lms_teacher_resource.chapter_id','c.id')
                    ->leftjoin('topic_master as t','lms_teacher_resource.topic_id','t.id')
                    ->where(['lms_teacher_resource.sub_institute_id'=>$sub_institute_id,
                        'lms_teacher_resource.chapter_id'=>$chapter_id,
                        'lms_teacher_resource.standard_id'=>$standard_id])
                        //'lms_teacher_resource.syear'=>$syear])
                        ->when(isset($request->mappedValues) && $request->mappedValues != '', function ($q) use ($request) {
                            $explodeData = explode(',', $request->mappedValues);
                            
                            // Ensure we exclude NULL values
                            $q->whereNotNull('mapping_value');
                        
                            if (count($explodeData) > 1) {
                                $q->where(function ($subQuery) use ($explodeData) {
                                    foreach ($explodeData as $value) {
                                        $subQuery->orWhere('mapping_value', 'LIKE', '%"'.$value.'"%' );
                                    }
                                });
                            } else {
                                foreach ($explodeData as $value) {
                                    $q->where('mapping_value', 'LIKE', '%"'.$value.'"%' );
                                }
                            }
                        })
                    ->get()->toArray(); 

        $data['chapter_id'] = $chapter_id;
        $data['standard_id'] = $standard_id;
        $data['subject_id'] = $subject_id;
        $data['topic_id'] = $topic_id;

        //START Columns from field setting
        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "lms_teacher_resource"])
                            ->whereRaw('(sub_institute_id = ' . $sub_institute_id . ' OR common_to_all = 1)  and user_type="" ')
                            ->get();

        $data['custom_fields'] = $dataCustomFields; 
        //END Columns from field setting

        //START Columns from field setting for combo checkbox
        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = [];
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        if (count($finalfieldsData) > 0) {
            $data['data_fields'] = $finalfieldsData;
        }                        
        //END Columns from field setting for combo checkbox         
        // get mapped parent Values 28-02-2025
        $mapParents = DB::table('lms_mapping_type')->where(['parent_id'=>0,'globally'=>1,'status'=>1])->get()->toArray();
        $mapVal=$mapType=[];
        foreach ($mapParents as $key => $value) {
            $mapType[$value->id] =$value->name;
            $mappedVals = DB::table('lms_mapping_type')->where(['parent_id'=>$value->id,'globally'=>1,'status'=>1])->get()->toArray();
            foreach ($mappedVals as $key2 => $value2) {
                $mapVal[$value->id][$value2->id] = $value2->name;
            }
        }
        $data['mapType'] = $mapType;
        $data['mapVal'] = $mapVal;
        // get mapped parent Values 28-02-2025 end

        return $data;
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {      
              
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
      
        $file_folder = $ext = $size = $newfilename = "";
        if($request->hasFile('teacher_file'))
        {           
            $img = $request->file('teacher_file');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $file_folder = '/lms_teacher_resource';
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;                        
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            // $img->storeAs('public'.$file_folder.'/',$newfilename); 20-05-24
            Storage::disk('digitalocean')->putFileAs('public/lms_teacher_resource/', $img, $newfilename, 'public');
        }         

        $TR_data = array(            
            // 'standard_id' =>  $request->get('hid_standard_id'),
            // 'subject_id' =>  $request->get('hid_subject_id'),
            // 'chapter_id' => $request->get('hid_chapter_id'),
            // 'topic_id' => $request->get('hid_topic_id'),
            // 'title' => $request->get('title'),
            'file_folder' => $file_folder,
            'file_name' => $newfilename,
            'file_type' => $ext,
            'file_size' => $size,
            'created_by' => $user_id,
            'status' => '1',
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear
        );

        $newRequest = $request->post();
        $mappingType = $mappingVal = [];
        foreach($newRequest as $key =>$value)
        {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'mapping_type' && $key != 'mapping_value' ) {
                if (strpos($key, 'hid') !== false) {
                    $key = str_replace('hid_','',$key);
                }
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $TR_data[$key] = $value;
            }
            if($key == 'mapping_type'){
               $mappingType = $value;
            }
            if($key == 'mapping_value'){
                $mappingVal = $value;
             }
        }
        $jsonArr = [];
        if(!empty($mappingType) && !empty($mappingVal)){
            foreach ($mappingType as $key => $value) {
               if(isset($mappingVal[$key])){
                $jsonArr[$value] = $mappingVal[$key];
               }
            }
        }
        $jsonDecodes = (!empty($jsonArr)) ? json_encode($jsonArr) : null;
        if($jsonDecodes!=null){
            $TR_data['mapping_value'] =  $jsonDecodes;
        }
        // echo "<pre>";print_r($TR_data);
        // exit;
        teacherResourceModel::insert($TR_data);

        $res = array(
            "status_code" => 1,
            "message"     => "Teacher Resource Added Successfully",
        );
        $type = $request->input('type');

        return redirect()->route('lms_teacherResource.index', [
            'standard_id' => $request->get('hid_standard_id'),
            'subject_id'  => $request->get('hid_subject_id'),
            'chapter_id'  => $request->get('hid_chapter_id'),
        ]);
    }
    public function edit(Request $request,$id)
    {
        $type= $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $editData = teacherResourceModel::where('id',$id)->first();

        // get mapped parent Values 28-02-2025
        $mapParents = DB::table('lms_mapping_type')->where(['parent_id'=>0,'globally'=>1,'status'=>1])->get()->toArray();
        $mapVal=$mapType=[];

        foreach ($mapParents as $key => $value) {
            $mapType[$value->id] =$value->name;
            $mappedVals = DB::table('lms_mapping_type')->where(['parent_id'=>$value->id,'globally'=>1,'status'=>1])->get()->toArray();
            foreach ($mappedVals as $key2 => $value2) {
                $mapVal[$value->id][$value2->id] = $value2->name;
            }
        }

        $res['mapType'] = $mapType;
        $res['mapVal'] = $mapVal;
        // get mapped parent Values 28-02-2025 end
        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($editData) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $editData->chapter_id);
            })->where(function ($q) use ($editData) {
                $q->where('topic_id', '=', 0)
                    ->orWhere('topic_id', $editData->topic_id);
            })->get()->toArray();
        // echo "<pre>";print_r($lms_mapping_type);exit;

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $res['lms_mapping_type'] = $lms_mapping_type;
        $res['editData'] = $editData;
        // echo "<pre>";print_r($editData->title);exit;

        return is_mobile($type,'lms/teacher_resource/edit',$res,"view");
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function update(Request $request,$id)
    {
        //
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');

        $TR_data = array( 
            'created_by' => $user_id,
            'status' => '1',
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear
        );
        // echo"<pre>";print_r($request->all());exit;
        $file_folder = $ext = $size = $newfilename = "";
        if($request->hasFile('teacher_file'))
        {           
            $img = $request->file('teacher_file');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $file_folder = '/lms_teacher_resource';
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;                        
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            // $img->storeAs('public'.$file_folder.'/',$newfilename); 20-05-24
            Storage::disk('digitalocean')->putFileAs('public/lms_teacher_resource/', $img, $newfilename, 'public');
            $TR_data['file_name'] = $newfilename;
            $TR_data['file_folder'] = $file_folder;
            $TR_data['file_type'] = $ext;
            $TR_data['file_size'] = $size;
        } 

        $newRequest = $request->post();
        $mappingType = $mappingVal = [];
        foreach($newRequest as $key =>$value)
        {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'mapping_type' && $key != 'mapping_value' ) {
                if (strpos($key, 'hid') !== false) {
                    $key = str_replace('hid_','',$key);
                }
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $TR_data[$key] = $value;
            }
            if($key == 'mapping_type'){
               $mappingType = $value;
            }
            if($key == 'mapping_value'){
                $mappingVal = $value;
             }
        }
        $jsonArr = [];
        if(!empty($mappingType) && !empty($mappingVal)){
            foreach ($mappingType as $key => $value) {
               if(isset($mappingVal[$key])){
                $jsonArr[$value] = $mappingVal[$key];
               }
            }
        }
        $jsonDecodes = (!empty($jsonArr)) ? json_encode($jsonArr) : null;
        if($jsonDecodes!=null){
            $TR_data['mapping_value'] =  $jsonDecodes;
        }
        // echo "<pre>";print_r($TR_data);
        // exit;
        teacherResourceModel::where('id',$id)->update($TR_data);

        $res = array(
            "status_code" => 1,
            "message"     => "Teacher Resource Added Successfully",
        );
        return redirect()->back()->with($res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');
        $TRdata = teacherResourceModel::where(["id" => $id])->get()->toArray();
        if (count($TRdata) > 0) {
            $standard_id = $TRdata[0]['standard_id'];
            $subject_id = $TRdata[0]['subject_id'];
            $chapter_id = $TRdata[0]['chapter_id'];
            $file_folder = $TRdata[0]['file_folder'];
            $file_name = $TRdata[0]['file_name'];
            if(file_exists(public_path('storage'.$file_folder.'/'.$file_name))){
                unlink(public_path('storage'.$file_folder.'/'.$file_name));
            }
        }

        teacherResourceModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Teacher Resource Deleted Successfully";


        return redirect()->route('lms_teacherResource.index', [
            'standard_id' => $standard_id,
            'subject_id'  => $subject_id,
            'chapter_id'  => $chapter_id,
        ]);
    }
}
