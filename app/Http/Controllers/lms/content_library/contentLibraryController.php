<?php

namespace App\Http\Controllers\lms\content_library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Storage;
use App\Models\lms\contentLibraryModel;
use App\Models\lms\userActivityModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use DB;

class contentLibraryController extends Controller
{
    // index Controller
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $user_id = $request->get('user_id');            
        }
        // get Searched Values
        if($request->has('search_type') && isset($request->search_type)){
            // DB::enableQueryLog();
            $getData = contentLibraryModel::leftJoin('user_activities as ua',function($join) use($user_id){
                $join->on('ua.content_id','=','contents.id')->where('ua.user_id','=',$user_id)->whereNull('ua.deleted_at');
            })
            ->selectRaw('contents.*,ua.*,group_concat(DISTINCT ua.action) as all_actions,contents.id as id,contents.sub_institute_id as sub_institute_id')
            ->when($request->has('title'),function($q) use ($request){
                $q->whereRaw('contents.title LIKE "%'.$request->title.'%"');
            })
            ->whereNull('contents.deleted_at')
            ->where('contents.status','approved');
            foreach ($request->keywords as $key => $value) {
                if(isset($value)){
                    $searchWord = '"'.$key.'":"'.$value.'"';
                    $getData->WhereRaw("contents.keywords LIKE '%".$searchWord."%'");
                    $res['keywordSearch'][$key] = $value; 
                }
            }
            $getData = $getData->groupBy('contents.id')
            ->get()->toArray();
            // dd(DB::getQueryLog($getData));
            $res['searchedContent'] = $getData;
            $res['searchedTitle'] = $request->title;
        }

        $starredContent = userActivityModel::Join('contents as c','c.id','=','user_activities.content_id')
        ->selectRaw('c.*,user_activities.*,c.id as id,group_concat(DISTINCT user_activities.action) as all_actions')
        ->where('user_activities.action','starred')
        ->where('user_activities.sub_institute_id',$sub_institute_id)
        ->where('user_activities.user_id',$user_id)
        ->whereNull('user_activities.deleted_at')
        ->whereNull('c.deleted_at')
        ->groupBy('c.id')
        ->get()->toArray();

        $downloadContent = userActivityModel::Join('contents as c','c.id','=','user_activities.content_id')
        ->selectRaw('c.*,user_activities.*,c.id as id,group_concat(DISTINCT user_activities.action) as all_actions,count(user_activities.id) as downloaded')
        ->where('user_activities.action','download')
        ->where('user_activities.sub_institute_id',$sub_institute_id)
        ->where('user_activities.user_id',$user_id)
        ->whereNull('user_activities.deleted_at')
        ->whereNull('c.deleted_at')
        ->groupBy('c.id')
        ->get()->toArray();

        $sharedContent = userActivityModel::Join('contents as c','c.id','=','user_activities.content_id')
        ->selectRaw('c.*,user_activities.*,c.id as id,group_concat(DISTINCT user_activities.action) as all_actions,count(user_activities.id) as shared')
        ->where('user_activities.action','shared')
        ->where('user_activities.sub_institute_id',$sub_institute_id)
        ->where('user_activities.user_id',$user_id)
        ->whereNull('user_activities.deleted_at')
        ->whereNull('c.deleted_at')
        ->groupBy('c.id')
        ->get()->toArray();

        $ownContent = contentLibraryModel::leftJoin('user_activities as ua',function($join) use($user_id){
            $join->on('ua.content_id','=','contents.id')->where('ua.user_id','=',$user_id)->whereNull('ua.deleted_at');
        })
        ->selectRaw('contents.*,ua.*,group_concat(DISTINCT ua.action) as all_actions,contents.id as id')
        ->where('contents.sub_institute_id',$sub_institute_id)
        ->where('contents.created_by',$user_id)
        ->whereNull('contents.deleted_at')
        ->groupBy('contents.id')
        ->get()->toArray();

        $copyContent = userActivityModel::Join('contents as c','c.id','=','user_activities.content_id')
        ->selectRaw('c.*,user_activities.*,c.id as id,group_concat(DISTINCT user_activities.action) as all_actions,count(user_activities.id) as shared')
        ->where('user_activities.action','copy')
        ->where('user_activities.sub_institute_id',$sub_institute_id)
        ->where('user_activities.user_id',$user_id)
        ->whereNull('user_activities.deleted_at')
        ->whereNull('c.deleted_at')
        ->groupBy('c.id')
        ->get()->toArray();

        $res['starredContent'] = $starredContent;
        $res['downloadContent'] = $downloadContent;
        $res['sharedContent'] = $sharedContent;
        $res['ownContent'] = $ownContent;
        $res['copyContent'] = $copyContent;

        // get mapping types and mapping values
        $getMapVal = $this->getMapVals();
        $res['mapType'] = $getMapVal['mapType'];
        $res['mapValue'] = $getMapVal['mapValue'];
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, 'lms/content_library/index', $res, 'view');
    }

    // create Controller
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        // get mapping types and mapping values
        $getMapVal = $this->getMapVals();
        // echo "<pre>";print_r($mappedVals);exit;
        $res['mapType'] = $getMapVal['mapType'];
        $res['mapValue'] = $getMapVal['mapValue'];
        return is_mobile($type, 'lms/content_library/add', $res, 'view');
    }

    // store all type of data
    public function store(Request $request){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
       
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $user_id = $request->get('user_id');
        }

        $res['status_code'] = 0;
        $res['message'] = "Failed to Insert";

        if($request->insert_type=="content_insert"){
            $jsonVal = json_encode($request->keywords);
            $filename = null;

            if($request->hasFile('attachment')){
                $file = $request->file('attachment');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = Storage::disk('digitalocean')->putFileAs('public/content_library/', $file, $filename, 'public');
            }
           
            $insertData = [
                'title'=>$request->title,
                'description' => $request->description,
                'keywords'=>$jsonVal,
                'attachment'=>$filename,
                'sub_institute_id'=>$sub_institute_id,
                'created_by'=>$user_id,
                'created_at'=>now()
            ];
            
            $insert = DB::table('contents')->insert($insertData);
            if($insert){
                $res['status_code'] = 1;
                $res['message'] = "Content Added Successfully !";
            }
            // return $jsonVal;
            return is_mobile($type, 'content_library.create', $res);
        }else{
            if($request->insert_type=='activity'){
                $content_id = $request->content_id;
                $action = $request->action;

                $checkData = userActivityModel::where(['sub_institute_id'=>$sub_institute_id,'content_id'=>$content_id,'action'=>$action
                ,'user_id'=>$user_id])->whereNull('deleted_at')->get()->first();

                if(in_array($action,["starred","copy"]) && !empty($checkData)){
                    if($action=="starred"){
                        $starred=userActivityModel::where('id',$checkData->id)->update(['deleted_at'=>now()]);
                        $res['status_code'] = 2;
                        $res['message'] = "Removed Contents !";
                    }else{
                        $res['status_code'] = 2;
                        $res['message'] = "Content Already Copied !";
                    }
                }else{
                    $insert= userActivityModel::insert([
                        'user_id'=>$user_id,
                        'content_id'=>$content_id,
                        'action'=>$action,
                        'sub_institute_id'=>$sub_institute_id,
                        'created_at'=>now(),
                    ]);
                        if($action=="copy"){
                            $contentData=contentLibraryModel::find($content_id);
                            if(empty($contentData) && $contentData->sub_institute_id!=$sub_institute_id){
                                $addData = [
                                    'title'=>$contentData->title,
                                    'description'=>$contentData->description,
                                    'keywords'=>$contentData->keywords,
                                    'attachment'=>$contentData->attachment,
                                    'status'=>"copied",
                                    'sub_institute_id'=>$sub_institute_id,
                                    'parent_id'=>$content_id,
                                    'created_by'=>$user_id,
                                    'created_at'=>now(),
                                ];
                                $insert2 = contentLibraryModel::insert($addData);
                            }
                        }
                    $res['status_code'] = 1;
                    $res['message'] = "Content ".$action." Successfully !";
                }
            }
        }
        return $res;
    }
    
    // edit Controller
    public function edit(Request $request,$id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        // get mapping types and mapping values
        $getMapVal = $this->getMapVals();
        // echo "<pre>";print_r($mappedVals);exit;
        $res['mapType'] = $getMapVal['mapType'];
        $res['mapValue'] = $getMapVal['mapValue'];
        $res['editData'] = contentLibraryModel::find($id);
        $res['contentData'] = DB::table('content_master')->where(['sub_institute_id'=>$sub_institute_id,'content_library_id'=>$id])->first();

        return is_mobile($type, 'lms/content_library/edit', $res, 'view');
    }

    public function update(Request $request,$id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $syear = session()->get('syear');

        // echo "<pre>";print_r($request->all());exit;
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $user_id = $request->get('user_id');         
            $syear = $request->get('syear');            
        }
        $res['status_code'] = 0;
        $res['message'] = "Failed to Update";

        $jsonVal = json_encode($request->keywords);
        $filename = isset($request->attached_file) ? $request->attached_file : null;

        if($request->hasFile('attachment')){
            $file = $request->file('attachment');
            $filename = time().'_'.$file->getClientOriginalName();
            $path = Storage::disk('digitalocean')->putFileAs('public/content_library/', $file, $filename, 'public');
        }
       
        $updateDate = [
            'title'=>$request->title,
            'description' => $request->description,
            'keywords'=>$jsonVal,
            'attachment'=>$filename,
            'sub_institute_id'=>$sub_institute_id,
            'created_by'=>$user_id,
            'updated_at'=>now()
        ];
        
        $update = DB::table('contents')->where('id',$id)->update($updateDate);

        if($update){
            $res['status_code'] = 1;
            $res['message'] = "Content Updated Successfully !";
        }

        if($request->has('grade') && $request->has('standard') && $request->has('subject') && $request->has('chapter_id')){
            $checkMaster = DB::table('content_master')->where(['sub_institute_id'=>$sub_institute_id,'content_library_id'=>$id])->first();
            $masterData = [
                'grade_id'=>$request->grade,
                'standard_id'=>$request->standard,
                'subject_id'=>$request->subject,
                'chapter_id'=>$request->chapter_id,
                'topic_id'=> isset($request->topic_id) ? $request->topic_id : null,
                'title'=>$request->title,
                'description'=>$request->description,
                'filename'=>"https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/".$filename,
                'file_type'=>'link',
                'url'=>"https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/".$filename,
                'show_hide'=> isset($request->show_hide) ? 1 : 0,
                'content_category'=>'Content Library',
                'syear'=>$syear,
                'sub_institute_id'=>$sub_institute_id,
                'content_library_id'=>$id
            ];

            if(empty($checkMaster)){
                $masterData['created_at']=now();
                $masterData['created_by']=$user_id;
                $actionStatus = DB::table('content_master')->insert($masterData);
            }else{
                $actionStatus = DB::table('content_master')->where(['sub_institute_id'=>$sub_institute_id,'content_library_id'=>$id])->update($masterData);
            }

            if(!$actionStatus){
                $res['status_code'] = 0;
                $res['message'] = "Content Master Failed to Add!";
            }
        }

        return is_mobile($type, 'content_library.index', $res);
    }

    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $user_id = $request->get('user_id');            
        }
        $res['status_code'] = 0;
        $res['message'] = "Failed to Delete";

        $contentData = contentLibraryModel::find($id);

        if($contentData){
           $userActivity = DB::table('user_activities')->where(['sub_institute_id'=>$sub_institute_id,'user_id'=>$user_id,'action'=>'copy'])->where('content_id',$contentData->parent_id)->update(['deleted_at'=>now()]);
        }
        
        $delete = DB::table('contents')->where('id',$id)->update(['deleted_at'=>now()]);
        
        if($delete){
            $res['status_code'] = 1;
            $res['message'] = "Content Deleted Succefully";
        }
        return is_mobile($type, 'content_library.index', $res);
    }

    public function show(Request $request,$id){
        $type= $request->type;
        $getMapVal = $this->getMapVals();
        $res['mapType'] = $getMapVal['mapType'];
        $res['mapValue'] = $getMapVal['mapValue'];
        $res['editData'] = contentLibraryModel::find($id);
        return is_mobile($type, 'lms/content_library/show', $res,'view');
    }

    function getMapVals(){
        $getMapType = DB::table('lms_mapping_type')->where(['parent_id'=>0,'status'=>1,'type'=>"content_library"])->get()->toArray();
        $mappedVals = [];
        foreach ($getMapType as $key => $value) {
            $mappedVals[$value->name] = DB::table('lms_mapping_type')->where(['parent_id'=>$value->id,'status'=>1])->get()->toArray();
        }
        // echo "<pre>";print_r($mappedVals);exit;
        $res['mapType'] = $getMapType;
        $res['mapValue'] = $mappedVals;
        return $res;
    }

    public function downloadFile(Request $request){
        // try {
            $getContent = contentLibraryModel::find($request->ContentId);
            if($getContent){
                $fileName = $getContent->attachment;
                $url = "https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/".$fileName;
                // Fetch the file content from the URL
                $response = Http::get($url);
                    
                if ($response->successful()) {
                    // Extract the filename from the URL
                    $filename = basename($url);

                    // Get the content type for the file
                    $contentType = $response->header('Content-Type') ?? 'application/octet-stream';

                    // Return the file as a download response
                    return Response::make($response->body(), 200, [
                        'Content-Type' => $contentType,
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ]);
                } 
                // else {
                //     return response()->json(['message' => 'File could not be downloaded.'], 400);
                // }
            }
        // else{
        //         return response()->json(["message"=>"Failed to Find Content"]);
        //     }
            
        // } catch (\Exception $e) {
        //     return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        // }
        // return $request;
    }
}
