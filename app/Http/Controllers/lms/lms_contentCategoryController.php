<?php

namespace App\Http\Controllers\lms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\lms\lmsContentCategoryModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lms_contentCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){        
        $data = $this->getData($request);       
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['cc_data'];  
                                         
        return is_mobile($type,'lms/show_contentCategory',$res,"view");  
    }

    public function getData($request){        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['cc_data'] = array();        

        $data['cc_data'] = lmsContentCategoryModel::select('*')
                                    
        ->get()->toArray();        

        return $data;
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request){         
        $type = $request->input('type');        
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
        $syear = $request->session()->get('syear');                     

        $data = array();    
        return is_mobile($type,'lms/add_contentCategory',$data,"view");
    }  

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {               
        $sub_institute_id = $request->session()->get('sub_institute_id');       

        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('category_name'),$sub_institute_id);       
        if($exist == 0)
        {
            $content = array(            
                'category_name' => $request->get('category_name'),                                   
                'status' => '1',                                   
                'sub_institute_id' => $sub_institute_id
            );                         

            lmsContentCategoryModel::insert($content);
            
            $res = array(
                "status_code" => 1,
                "message" => "Content Category Added Successfully",
            );
        }
        else
        {
            $res = array(
                "status_code" => 0,
                "message" => "Content Category Already Exist",
            );
        }
        
        $type = $request->input('type');
        return is_mobile($type, "lms_content_category.index", $res, "redirect");        
    }

    public function check_exist($category_name,$sub_institute_id,$id = null)
    {    
        $extra = "";
        if($id != "")
        {
            $extra = "AND id != '".$id."' ";
        }
        $data = DB::select("SELECT count(*) as tot 
            FROM lms_content_category 
            WHERE (sub_institute_id = '".$sub_institute_id."' or sub_institute_id = '0')  AND category_name = '".$category_name."' 
            ".$extra);
        $total_count = $data[0]->tot;
        return $total_count;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$id)
    {
        $type = $request->input('type');
        
        $sub_institute_id = $request->session()->get('sub_institute_id');       
                
        $data['cc_data'] = lmsContentCategoryModel::find($id)->toArray();        
        return is_mobile($type, "lms/add_contentCategory", $data, "view");
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
        $sub_institute_id = $request->session()->get('sub_institute_id');               

        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('category_name'),$sub_institute_id,$id);    
        if($exist == 0)
        {
            $content = array(  
                'category_name' => $request->get('category_name'),                                   
                'status' => '1',                                   
                'sub_institute_id' => $sub_institute_id
            );  
            
            lmsContentCategoryModel::where(["id" => $id])->update($content);
            
            $res = array(
                "status_code" => 1,
                "message" => "Content Category Updated Successfully",
            );
        }
        else
        {
            $res = array(
                "status_code" => 0,
                "message" => "Content Category Already Exist",
            );
        }
        $type = $request->input('type');
        return is_mobile($type, "lms_content_category.index", $res, "redirect");        
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
        lmsContentCategoryModel::where(["id" => $id])->delete();    

        $res['status_code'] = "1";
        $res['message'] = "Content Category Deleted Successfully";
                
        return is_mobile($type, "lms_content_category.index", $res);
    }        
        
}
