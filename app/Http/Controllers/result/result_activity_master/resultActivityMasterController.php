<?php

namespace App\Http\Controllers\result\result_activity_master;

use App\Http\Controllers\Controller;
use App\Models\student\studentVaccinationModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class resultActivityMasterController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $get_result_activity_masters = DB::table('result_activity_master as ram')
            ->join('result_skillset as rs', 'rs.id', '=', 'ram.skill_id')
            ->join('standard as s','s.id','=','ram.standard')
            ->leftJoin('result_sub_activity as rsa','rsa.sub_skill_id','=','ram.id')
            ->selectRaw('ram.*, rs.main_title as result_main_title, rs.title as result_title,s.name as standard,GROUP_CONCAT(rsa.title order by rsa.sort_order SEPARATOR "|||") as sub_activity')
            ->where('ram.sub_institute_id', $sub_institute_id)
            ->groupBy('ram.id')
            ->get()->toArray();
        // echo "<pre>";print_r($get_result_activity_masters);exit;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_activity_masters'] = $get_result_activity_masters;

        return is_mobile($type, "result/result_activity_master/show_result_activity_master", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();
       
        $standardLists = DB::table('standard')->where('sub_institute_id', $sub_institute_id)->orderBy('sort_order')->get()->toArray();

        $res['standardLists'] = $standardLists;
        $res['levelLayers'] = [3,4];
        $res['result_skillsets'] = $get_result_skillsets;

        return is_mobile($type, "result/result_activity_master/add_result_activity_master", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $type = $request->input('type');
        $title = $request->get('title');
        $skill_id = $request->get('skill_id');
        $standard = $request->get('standard');
        $levels = $request->get('levels');
        $sub_skill_id = $request->get('sub_skill_id');
        
        // for level 4
        $insert = 0;
        if(isset($request->sub_skill_id) && $request->levels==4){
            $maxSortOrder = DB::table('result_sub_activity')->where(['sub_institute_id'=>$sub_institute_id])->max('sort_order');
            $sort_order = $request->get('sort_order') ?? ($maxSortOrder+1);
            $finalArray = [
                'title' => $title,
                'skill_id' => $skill_id,
                'sub_institute_id' => $sub_institute_id,
                'created_by' => $user_id,
                'sub_skill_id' =>$sub_skill_id,
                'sort_order' =>$sort_order,
                'created_at' => now(),
            ];

            $insert=DB::table('result_sub_activity')->insert($finalArray);
        }else{
            
            $maxSortOrder = DB::table('result_activity_master')->where(['sub_institute_id'=>$sub_institute_id,'standard'=>$standard])->max('sort_order');
            $sort_order = $request->get('sort_order') ?? ($maxSortOrder+1);
            
            $finalArray = [
                'title' => $title,
                'skill_id' => $skill_id,
                'standard' => $standard,
                'sub_institute_id' => $sub_institute_id,
                'created_by' => $user_id,
                'sort_order' => $sort_order,
                'created_at' => now(),
            ];
            
            $insert=DB::table('result_activity_master')->insert($finalArray);
        }
        if($insert!=0){
            $res['status_code'] = 1;
            $res['message'] = "Result activity master added successfully.";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to add data";
        }

        return is_mobile($type, "result_activity_master.create", $res);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $get_result_activity_masters = DB::table('result_activity_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $id)
            ->first();

        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $get_result_sub_activity_masters = DB::table('result_sub_activity')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('sub_skill_id', $id)
            ->get()->toArray();

        $res['result_activity_masters'] = $get_result_activity_masters;
        $res['result_skillsets'] = $get_result_skillsets;
        $res['result_sub_activity_masters'] = $get_result_sub_activity_masters;
        $type = $request->input('type');

        return is_mobile($type, "result/result_activity_master/edit_result_activity_master", $res, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');
        $finalArray = $request->except('_method', '_token', 'submit','subData','hasSubActivity');

        DB::table('result_activity_master')->where(['id' => $id])->update($finalArray);
        // update sub data
        if(isset($request->subData['id'])){
            foreach ($request->subData['id'] as $key => $value) {
               $updateArr = [
                'title'=>$request->subData['title'][$key],
                'sort_order'=>$request->subData['sort_order'][$key],
                'updated_at'=>now()
               ];
                DB::table('result_sub_activity')->where(['id' => $value])->update($updateArr);
            }
        }
        // echo "<pre>";print_r($request->subData);exit;

        $res['status_code'] = 1;
        $res['message'] = "Result activity master updated successfully.";

        return is_mobile($type, "result_activity_master.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
      
        DB::table('result_activity_master')->where(["id" => $id])->delete();

        $res['status_code'] = "1";
        $res['message'] = "Result activity master deleted successfully";

        return is_mobile($type, "result_activity_master.index", $res);
    }
    
    public function result_sub_activity_destroy(Request $request)
    {
        $type = $request->input('type');
        DB::table('result_sub_activity')->where(["id" => $request->sub_id])->delete();
        
        $res['status_code'] = "1";
        $res['message'] = "Result activity master deleted successfully";

        return is_mobile($type, "result_activity_master.index", $res);
    }

    public function getActivityLists(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $skill_id = $request->skill_id;
        $standard = $request->standard;
        if(isset($standard)){
            $res = DB::table('result_activity_master')->where('sub_institute_id',$sub_institute_id)->where('standard',$standard)->where('skill_id',$skill_id)->get()->toArray();
        }else{
            $res = DB::table('result_sub_activity')->where('sub_institute_id',$sub_institute_id)->where('sub_skill_id',$skill_id)->get()->toArray();
        }
        return $res;
    }
}
