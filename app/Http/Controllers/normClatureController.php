<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\normClature;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class normClatureController extends Controller
{
    //
    public function index(Request $request){
        $type=$request->type;
        
        $res['menu_title'] = normClature::whereRaw('sub_institute_id = 0 and status = 0')->groupBy('menu')->get();
        return is_mobile($type,'norm_cluter/show',$res,'view');
    }

    public function create(Request $request){
        $type=$request->type;
        if($request->has('menu_id')){
            $menu_id = $request->menu_id;     
        }else{
            $menu_id = $request->menu_title;
        }
       
        $sub_institute_id = session()->get('sub_institute_id');
        
        $check = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id ='.$sub_institute_id)->get();
        // return $check;exit;
        if(count($check)>0){
            $res['table_data'] = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id ='.$sub_institute_id)->get();
        }else{
            $res['table_data'] = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id =0')->get();            
        }
        $res['menu_title'] = normClature::whereRaw('sub_institute_id = 0')->groupBy('menu')->get();
        
        $res['menu_id'] =$menu_id;       
        return is_mobile($type,'norm_cluter/show',$res,'view');
    }

    public function store(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id=session()->get('user_id');
        $menu_id = $request->menu_id;
        $type=$request->type;
        $res['get_data'] = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id =0')->get(); 
        foreach($res['get_data'] as $key => $value){
            $data =[ 
                "menu_id"=> $value->menu_id,
                "menu"=>$value->menu,
                "string"=>$value->string,
                "value"=>$request->value[$value->id] ?? $value->value,
                "status"=>isset($request->value[$value->id]) ? 1 : 0,
                "sub_institute_id"=>$sub_institute_id,
                "created_by"=>$user_id,
                "created_at"=>now(),
                "updated_at"=>now(),                
            ];
            // print_r($data);
        $check = normClature::whereRaw('sub_institute_id='.$sub_institute_id.' and menu_id ='.$menu_id.' and string ="'.$value->string.'" ')->first();
        if(empty($check)){     
            $insert = normClature::insert($data);
        }
    }
        // exit;
        $res['table_data'] = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id ='.$sub_institute_id)->get(); 
        $res['menu_title'] = normClature::whereRaw('sub_institute_id =0')->groupBy('menu')->get();  
        $res['menu_id'] =$menu_id;     
        $res['status_code']=1;
        $res['message']="stored";  
            
        return is_mobile($type,'norm_cluter/show',$res,'view');
    }

    public function Update(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        
        $menu_id = $request->menu_id;
        $res['get_data'] = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id ='.$sub_institute_id)->get();         
        foreach($res['get_data'] as $key => $value){
            if($request->value[$value->id]!==null){
                $status=1;
            }else{
                $status=0;
            }
            $data =[ 
                "menu_id"=> $value->menu_id,
                "menu"=> $value->menu,
                "string"=>$value->string,
                "value"=>$request->value[$value->id],
                "status"=>$status,
                "updated_at"=>now(),
            ];
            // print_r($data);
            $update = normClature::whereRaw('sub_institute_id='.$sub_institute_id.' and string = "'.$value->string.'"')->update($data);
        }
        $sub_institute_id = session()->get('sub_institute_id');
        $res['table_data'] = normClature::whereRaw('menu_id='.$menu_id.' and sub_institute_id ='.$sub_institute_id)->get();         
        $res['menu_title'] = normClature::whereRaw('sub_institute_id =0')->groupBy('menu')->get();  
        $res['menu_id'] =$menu_id;     
        $res['status_code']=1;
        $res['message']="Update";      
        return is_mobile($type,'norm_cluter/show',$res,'view');
    }
}
