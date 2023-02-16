<?php

namespace App\Http\Controllers\school_setup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\chapterModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\sub_std_mapModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use function App\Helpers\ValidateInsertData;

class chapterController extends Controller
{
    public function index(Request $request){
        $data = $this->getData($request); 		
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;        
        return is_mobile($type,'school_setup/show_chapter',$res,"view");  
    }

    public function getData($request){
        $sub_institute_id = $request->session()->get('sub_institute_id');
		$data = chapterModel::select('chapter_master.*','standard.name as standard_name'
		,'academic_section.title as grade_name','subject_name')
        ->join('standard', 'standard.id', '=', 'chapter_master.standard_id')
        ->join('academic_section', 'academic_section.id', '=', 'chapter_master.grade_id')        
        ->join('subject', 'subject.id', '=', 'chapter_master.subject_id')        
        ->where(['chapter_master.sub_institute_id'=>$sub_institute_id])
        ->orderBy('chapter_master.standard_id', 'asc')
        ->get();       
        
        return $data;
    }

    public function create(Request $request){
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$res['subjects'] = array();

        return is_mobile($type,'school_setup/add_chapter',$res,"view");
    }
    public function store(Request $request){
        $sub_institute_id = $request->session()->get('sub_institute_id'); 		
        $syear = $request->session()->get('syear'); 		
        $user_id = $request->session()->get('user_id');
 		
		$ch = new chapterModel([
			'grade_id' => $request->get('grade'),
			'standard_id' => $request->get('standard'),
			'subject_id' => $request->get('subject'),
			'chapter_name' => $request->get('chapter_name'),
			'chapter_code' => $request->get('chapter_code'),
			'chapter_desc' => $request->get('chapter_desc'),
			'created_by' => $user_id,
			'sub_institute_id' => $sub_institute_id,
			'status' => "1",            
			'syear' => $syear
		]);
			 
		$ch->save();
		$res = array(
			"status_code" => 1,
			"message" => "Chapter Added Successfully",
		);
        $type = $request->input('type');
        return is_mobile($type, "chapter_master.index", $res, "redirect");
    }
		
    public function edit(Request $request,$id){
        $type = $request->input('type');
        $std_id = $request->input('std_id');
		$sub_institute_id = $request->session()->get('sub_institute_id'); 		
		
		$stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $std_id])
        ->orderBy('display_name')->get()->toArray();		
        
		$data['subjects'] = $stdData;
		
		$data['chapter_data'] = chapterModel::find($id)->toArray();
        return is_mobile($type, "school_setup/add_chapter", $data, "view");
    }
	
    public function update(Request $request,$id){
        //ValidateInsertData('subject','update');        
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
		$syear = $request->session()->get('syear'); 		
        $user_id = $request->session()->get('user_id');
		
		$data = array(
			'grade_id' => $request->get('grade'),
			'standard_id' => $request->get('standard'),
			'subject_id' => $request->get('subject'),
			'chapter_name' => $request->get('chapter_name'),
			'chapter_code' => $request->get('chapter_code'),
			'chapter_desc' => $request->get('chapter_desc'),			
			'created_by' => $user_id,
			'sub_institute_id' => $sub_institute_id,
			'status' => "1",            
			'syear' => $syear
		);
		chapterModel::where(["id" => $id])->update($data);
		$res = array(
			"status_code" => 1,
			"message" => "Chapter Updated Successfully",
		);
        $type = $request->input('type');
        return is_mobile($type, "chapter_master.index", $res, "redirect");
    }
    public function destroy(Request $request,$id){
        $type = $request->input('type');
        chapterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Chapter Deleted Successfully";
        return is_mobile($type, "chapter_master.index", $res);
    }
	
	public function StandardwiseSubject(Request $request){        
        $std_id = $request->input("std_id");        
        $sub_institute_id = $request->session()->get("sub_institute_id");
		
        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $std_id])
        ->orderBy('display_name')->get()->toArray();
        return $stdData;    
    }
	
}
