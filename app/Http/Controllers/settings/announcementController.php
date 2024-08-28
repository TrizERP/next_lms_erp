<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use GenTux\Jwt\GetsJwtToken;

class announcementController extends Controller
{
    //
    use GetsJwtToken;

    public function index(Request $request){
        $type=$request->type;
        $sub_institute_id =session()->get('sub_institute_id');
        $syear =session()->get('syear');
        $user_profile_name =session()->get('user_profile_name');
        $user_profile_id =session()->get('user_profile_id');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_profile_name = $request->user_profile_name;
            $user_profile_id = $request->user_profile_id;
        }

        $profile_id = '';
        if($user_profile_name != 'Admin' && $user_profile_name != 'Super Admin'){
            $profile_id = $user_profile_id;
        }

        $res['announcementDetails'] = DB::table('announcement as ann')->join('tbluserprofilemaster as tup','tup.id','=','ann.user_profile_id')->where('ann.sub_institute_id',$sub_institute_id)->where('ann.syear',$syear)
        ->selectRaw('ann.*,CONCAT("https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/announcements/",ann.attachment) as attachment_url,tup.id as profile_id,tup.name')
        ->when($profile_id!='',function($q) use($profile_id){
            $q->where('ann.user_profile_id',$profile_id);
        })
        ->orderBy('ann.id','DESC')->get()->toArray();

        return is_mobile($type, "settings/announcement/show", $res, "view");
    }

    public function create(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        $res['usersProfile'] = DB::table('tbluserprofilemaster')->where('sub_institute_id',$sub_institute_id)->get()->toArray();

        return is_mobile($type, "settings/announcement/add", $res, "view");
    }

    public function store(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $title = $request->title;
        $description = $request->description;
        $from_date = $request->from_date ?? 0;
        $to_date = $request->to_date ?? 0;
        $users = $request->user_profiles;

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

            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $users = explode(',',$request->user_profiles);

        }
        $filename = null;

        if($request->hasFile('attachment')){
            $file = $request->file('attachment');
            $filename = $sub_institute_id.'_'.time() . '.' . $file->getClientOriginalExtension();
            $file_path = 'public/announcements/' . $filename;
            // if (Storage::disk('digitalocean')->exists($file_path)) {
            //     Storage::disk('digitalocean')->delete($file_path);
            //     if (!Storage::disk('digitalocean')->exists($file_path)) {
            //         $filename=null;
            //     }   
            // } 

            Storage::disk('digitalocean')->putFileAs('public/announcements/', $file, $filename, 'public');
        }

        $insertData = [];
        foreach ($users as $key => $value) {
          $data = [
            'syear'=>$syear,
            'sub_institute_id'=>$sub_institute_id,
            'title'=>$title,
            'description'=>$description,
            'attachment'=>$filename,
            'from_date'=>$from_date,
            'to_date'=>$to_date,
            'user_profile_id'=>$value,
            'created_at'=>now(),
          ];
          $insert = DB::table('announcement')->insert($data);
          $insertData = $data;
        }

        if(!empty($insertData)){
            $res['status_code'] = 1;
            $res['message'] = "Added Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Add";
        }

        $res['usersProfile'] = DB::table('tbluserprofilemaster')->where('sub_institute_id',$sub_institute_id)->orderBy('id','DESC')->get()->toArray();

        return is_mobile($type, "announcements.index", $res, "redirect");
    }
    public function edit(Request $request,$id){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        $res['data'] = DB::table('announcement as ann')->join('tbluserprofilemaster as tup','tup.id','=','ann.user_profile_id')->where('ann.sub_institute_id',$sub_institute_id)->where('ann.syear',$syear)
        ->selectRaw('ann.*,CONCAT("https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/announcements/",ann.attachment) as attachment_url,tup.id as profile_id,tup.name')
        ->where('ann.id',$id)
        ->first();
        
        if(empty($res)){
            $res['status_code'] = 0;
            $res['message'] = "No Data Found";
        }
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "settings/announcement/edit", $res, "view");
    }


    public function update(Request $request,$id){
        $title = $request->title;
        $description = $request->description;
        $from_date = $request->from_date ?? 0;
        $to_date = $request->to_date ?? 0;
        
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
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
            
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }

        $filename = null;

        if($request->hasFile('attachment')){
            $file = $request->file('attachment');
            $filename = $sub_institute_id.'_'.time() . '.' . $file->getClientOriginalExtension();
            $file_path = 'public/announcements/' . $filename;
            Storage::disk('digitalocean')->putFileAs('public/announcements/', $file, $filename, 'public');
          
        }else if(isset($request->oldFile)){
            $filename = $request->oldFile;
        }

        $updateData = [];
          $data = [
            'syear'=>$syear,
            'sub_institute_id'=>$sub_institute_id,
            'title'=>$title,
            'description'=>$description,
            'attachment'=>$filename,
            'from_date'=>$from_date,
            'to_date'=>$to_date,
            'updated_at'=>now(),
          ];
          $update = DB::table('announcement')->where('id',$id)->update($data);
          $updateData = $data;
        
        // echo "<pre>";print_r($data);exit;    

        if(!empty($updateData)){
            $res['status_code'] = 1;
            $res['message'] = "Updated Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Add";
        }

        return is_mobile($type, "announcements.index", $res, "redirect");
    }

    public function destroy(Request $request,$id){
        $type=$request->type;
        // if(isset($request->oldFile)){
        //     $file_path = 'public/announcements/' . $request->oldFile;

        //     if (Storage::disk('digitalocean')->exists($file_path)) {
        //         Storage::disk('digitalocean')->delete($file_path);
        //         if (!Storage::disk('digitalocean')->exists($file_path)) {
        //             $filename=null;
        //         }   
        //     } 
        // }

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

        }

        $delete = DB::table('announcement')->where('id',$id)->delete();
        
        if($delete){
            $res['status_code'] = 1;
            $res['message'] = "Record Deleted Successfully";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Add";
        }
        return is_mobile($type, "announcements.index", $res, "redirect");

    }

    public function dashboardData(Request $request){
        $type=$request->type;
        $sub_institute_id =session()->get('sub_institute_id');
        $syear =session()->get('syear');
        $user_profile_name =session()->get('user_profile_name');
        $user_profile_id =session()->get('user_profile_id');

        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_profile_name = $request->user_profile_name;
            $user_profile_id = $request->user_profile_id;
        }

        $profile_id = '';
        if($user_profile_name != 'Admin' && $user_profile_name != 'Super Admin'){
            $profile_id = $user_profile_id;
        }

        $res['announcementDetails'] = DB::table('announcement as ann')
        ->join('tbluserprofilemaster as tup', 'tup.id', '=', 'ann.user_profile_id')
        ->where('ann.sub_institute_id', $sub_institute_id)
        ->where('ann.syear', $syear)
        ->when($profile_id!='',function($q) use($profile_id){
            $q->where('ann.user_profile_id',$profile_id);
        })
        ->where(function ($query) {
            $query->where('ann.from_date', '0000-00-00')
                ->orWhere('ann.to_date', '0000-00-00')
                ->orWhere('ann.from_date', '=', date('Y-m-d'))
                ->Where('ann.to_date', '>=',date('Y-m-d'));
        })
        ->selectRaw('ann.*, CONCAT("https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/announcements/", ann.attachment) AS attachment_url, tup.id AS profile_id, tup.name')
        ->orderBy('ann.id', 'DESC')
        ->get()
        ->toArray();

        return $res;
    }

}
