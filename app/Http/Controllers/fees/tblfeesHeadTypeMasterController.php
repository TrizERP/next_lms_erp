<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use App\Models\fees\tblfeesHeadTypeMasterModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class tblfeesHeadTypeMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $code = $request->input('code');
        $head_title = $request->input('head_title');


        if(!empty($code))
        {
            $whereArray['code'] = $code;
            $res['code'] = $code;
        }
        if(!empty($head_title))
        {
            $whereArray['head_title'] = $head_title;
            $res['head_title'] = $head_title;
        }
        
        $whereArray['syear'] = $syear;
        $whereArray['sub_institute_id'] = $sub_institute_id;
        $data = tblfeesHeadTypeMasterModel::where($whereArray)->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        
        return is_mobile($type, "fees/show_fees_head_type", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $newCode = tblfeesHeadTypeMasterModel::selectRaw("MAX(CAST(code AS UNSIGNED))+1 AS newcode")->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        if(!isset($newCode[0]['newcode']))
        {
            $newCode[0]['newcode'] = 1;
        }

        view()->share('newcode', $newCode[0]['newcode']);
        return view('fees/add_fees_head_type');
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
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $standard_ids = $request['standard_id'];
        
        $data = $this->saveData($request);     
        
       
        $res['status_code'] = "1";
        $res['message'] = "Fees Head Type Added successfully";

        return is_mobile($type, "fees_head_type_master.index", $res);
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
     * @return Application|Factory|View
     */
    public function edit(Request $request,$id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        
        $whereArray['syear'] = $syear;
        $whereArray['sub_institute_id'] = $sub_institute_id;
        $whereArray['id'] = $id;
        
        $editData = tblfeesHeadTypeMasterModel::find($id)->toArray();

        return view('fees/edit_fees_head_type',['data' => $editData]);
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
        $type = $request->input('type');
        $mandatory = $request->input('mandatory');
        if (empty($mandatory)) {
            $request->request->set('mandatory', "0");
        }
        $request->request->add(['id' => $id]); //add request

        $this->updateData($request);
        
        $res['status_code'] = "1";
        $res['message'] = "Fees Head Type Updated successfully";
        
        return is_mobile($type, "fees_head_type_master.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');
        tblfeesHeadTypeMasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Fees Late Start Date deleted successfully";
        return is_mobile($type, "fees_head_type_master.index", $res);
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;
        $finalArray['created_by'] = $user_id;

        foreach($newRequest as $key => $value){
            if($key != '_method' && $key != '_token' && $key != 'submit'){
                if(is_array($value)){
                    $value = implode(",",$value);
                }
                $finalArray[$key] = $value;
            }
        }
        
        tblfeesHeadTypeMasterModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();
        
        
    }

    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $id = $newRequest['id'];
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        return tblfeesHeadTypeMasterModel::where(['id' => $id])->update($finalArray);
    }
}
