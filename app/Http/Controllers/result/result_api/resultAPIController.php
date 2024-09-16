<?php

namespace App\Http\Controllers\result\result_api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use App\Http\Controllers\lms\questionmasterController;
use App\Http\Controllers\lms\counselling\lmsCounsellingController;

class resultAPIController extends Controller
{
    //

    public function resultPersonalize(Request $request){
        $type = $request->type;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;
        $res['status_code']=0;
        $res['message']="No data Found"; 
        // previous data start
        $allData = DB::table('result_personalize_marks')->selectRaw('student_name,enrollment_no,standard,subject,sum(total) as total,sum(obtain) as obtain,syear')->where('sub_institute_id',$sub_institute_id)
        ->when($request->enrollment_no, function ($q) use($request) {
            $q->where('enrollment_no',$request->enrollment_no);
        })->when($request->standard, function ($q) use($request) {
            $q->where('standard',$request->standard);
        })->groupBy(['subject','standard'])->get()->toArray();  
        $previous_marks=[];

        $previous_marks['previousdata']['overallresult'] = [];
        $standardData = [];
        
        foreach ($allData as $key => $value) {
            $previous_marks['previousdata']['overallresult'][$value->standard][] = [
                "subjectname" => $value->subject,
                "totalmarks" => $value->total,
                "totalobtain" => $value->obtain,
            ];
        
            $getSingledata = DB::table('result_personalize_marks')
                ->selectRaw('group_concat(exam) as title, sum(total) as total, sum(obtain) as obtain, standard, subject')
                ->where([
                    'standard' => $value->standard,
                    'subject' => $value->subject,
                    'sub_institute_id' => $sub_institute_id,
                ])
                ->where('enrollment_no',$value->enrollment_no)
                ->groupBy('standard', 'exam')
                ->get()
                ->toArray();
        
            foreach ($getSingledata as $index => $data) {
                if (!isset($standardData[$value->standard])) {
                    $standardData[$value->standard] = [
                        'standardname' => $value->standard,
                        'totalmarks' => 0,
                        'totalobtain' => 0,
                        'subjectdata' => [],
                    ];
                }
        
                $standardData[$value->standard]['totalmarks'] += $data->total;
                $standardData[$value->standard]['totalobtain'] += $data->obtain;
        
                // Check if the subject already exists in the subjectdata array
                $subjectIndex = array_search($value->subject, array_column($standardData[$value->standard]['subjectdata'], 'title'));
        
                if ($subjectIndex !== false) {
                    // If the subject exists, add the exam data to the subject's array
                    $standardData[$value->standard]['subjectdata'][$subjectIndex]['examdata'][] = [
                        "title" => $data->title,
                        "marks" => $data->total,
                        "obtain" => $data->obtain,
                    ];
                } else {
                    // If the subject doesn't exist, create a new entry in the subjectdata array
                    $standardData[$value->standard]['subjectdata'][] = [
                        "title" => $value->subject,
                        "examdata" => [
                            [
                                "title" => $data->title,
                                "marks" => $data->total,
                                "obtain" => $data->obtain,
                            ],
                        ],
                    ];
                }
            }
        }
        
        $previous_marks['previousdata']['standarddata'] = array_values($standardData);
        
        // previous data end
        
        return $previous_marks;
    }


    public function currentResult(Request $request){
        $type = $request->type;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;
        $res['status_code']=0;
        $res['message']="No data Found"; 
        // (system table) result_exam_master & result_create_exam & result_marks & standard & subject		
        // db::enableQueryLog();
        // standard data			
        $allData =DB::table('result_exam_master as rem')
        ->join('result_create_exam as rce',function($join){
            $join->on('rem.Id','=','rce.exam_id')->on('rce.standard_id','=','rem.standard_id');
        })
        ->join('result_marks as rm','rm.exam_id','=','rce.id')
        ->join('tblstudent as s','rm.student_id','=','s.id')
        ->join('tblstudent_enrollment as se',function ($q) use($syear) {
            $q->on('se.student_id','=','s.id')->where('se.syear',$syear)->whereNull('se.end_date');
        })
        ->join('standard as std','std.id','=','se.standard_id')
        ->join('subject as sub','sub.id','=','rce.subject_id')  
        ->selectRaw('std.id as standard,rem.Id as exam_id,rem.ExamTitle,rem.standard_id,rce.id as create_id,rce.subject_id,rce.title as exam_title,sub.subject_name as subject,sum(rm.points) as obtain,sum(rce.points) as total,group_concat(DISTINCT rce.title) as title')
        ->where('rem.SubInstituteId',$sub_institute_id)
        ->where('rce.syear',$syear)
        ->when($request->standard,function($q) use($request) {
            $q->where('rem.standard_id',$request->standard);
        })->when($request->student_id,function($q) use($request) {
            $q->where('rm.student_id',$request->student_id)->groupBy('rm.student_id');
        })
        ->groupBy(['standard','subject','create_id'])      
        ->get()->toArray();
        // dd(db::getQueryLog($allData));
        $newData =$subjectdata=[];
        $stdObtMark = $stdTotMark=0;
        foreach ($allData as $value) {
            $examData = [
                'title' => $value->exam_title,
                'total' => $value->total,
                'obtain' => $value->obtain,
            ];
            $stdTotMark += $value->total;
            $stdObtMark += $value->obtain;
            
            $getSTDname=DB::table('standard')->where('id',$value->standard)->where('sub_institute_id',$sub_institute_id)->first();
        
            if (isset($newData[$value->standard])) {
                $subjectData = &$newData[$value->standard]['subjectdata'];
        
                $subjectIndex = array_search($value->subject, array_column($subjectData, 'title'));
        
                if ($subjectIndex !== false) {
                    $newData[$value->standard]['subjectdata'][$subjectIndex]['examdata'][] = $examData;
                } else {
                    $newData[$value->standard]['subjectdata'][] = [
                        'subject_id'=>$value->subject_id,
                        'title' => $value->subject,
                        'examdata' => [$examData],
                    ];
                }
                 // Update 'totalmarks' and 'totalobtain'
                $newData[$value->standard]['totalmarks'] += $stdTotMark;
                $newData[$value->standard]['totalobtain'] += $stdObtMark;
            } else {
                $newData[$value->standard] = [
                    'standardname' => $getSTDname->name,
                    'totalmarks' => $stdTotMark,
                    'totalobtain' => $stdObtMark,                    
                    'subjectdata' => [
                        [
                            'subject_id'=>$value->subject_id,
                            'title' => $value->subject,
                            'examdata' => [$examData],
                        ],
                    ],
                ];

            }
         
        }
        
        // Flatten the structure
        $flattenedData = [];
        foreach ($newData as $standard) {
            $flattenedData['standarddata']['standardname']=$standard['standardname'];
            $flattenedData['standarddata']['totalmarks']=$standard['totalmarks'];
            $flattenedData['standarddata']['totalobtain']=$standard['totalobtain'];            
            $flattenedData['standarddata']['subjectdata'] = $standard['subjectdata'];
        }
        
        $current_marks['currentdata'] = $flattenedData;
        // subject and chapter data
        $subjectDatas =DB::table('result_exam_master as rem')
        ->join('result_create_exam as rce',function($join){
            $join->on('rem.Id','=','rce.exam_id')->on('rce.standard_id','=','rem.standard_id');
        })
        ->join('result_marks as rm','rm.exam_id','=','rce.id')
        ->join('tblstudent as s','rm.student_id','=','s.id')
        ->join('tblstudent_enrollment as se',function ($q) use($syear) {
            $q->on('se.student_id','=','s.id')->where('se.syear',$syear)->whereNull('se.end_date');
        })
        ->join('standard as std','std.id','=','se.standard_id')
        ->join('subject as sub','sub.id','=','rce.subject_id')  
        ->selectRaw('std.id as standard,rem.Id as exam_id,rem.ExamTitle,rem.standard_id,rce.id as create_id,rce.subject_id,rce.title as exam_title,sub.subject_name as subject,sum(rm.points) as obtain,sum(rce.points) as total,group_concat(DISTINCT rce.title) as title')
        ->where('rem.SubInstituteId',$sub_institute_id)
        ->where('rce.syear',$syear)
        ->when($request->standard,function($q) use($request) {
            $q->where('rem.standard_id',$request->standard);
        })->when($request->subject_id,function($q) use($request) {
            $q->where('rce.subject_id',$request->subject_id);
        })->when($request->student_id,function($q) use($request) {
            $q->where('rm.student_id',$request->student_id)->groupBy('rm.student_id');
        })
        ->groupBy(['standard','subject','create_id'])      
        ->get()->toArray();
        $subTotMark = $subObtMark = 0;
        $allchapterdata=[];
        
        set_time_limit(300);

        // db::enableQueryLog();
        $chapterData = DB::table('question_paper as qp')
            ->select('qp.standard_id','qp.subject_id','qp.id AS question_paper_id','qp.paper_name','le.student_id','le.question_paper_id',DB::raw('SUM(qp.total_marks) as total_marks'),DB::raw('SUM(IFNULL(le.total_right, 0)) AS obtain_marks'),'qp.question_ids',DB::raw('GROUP_CONCAT(qm.question_title) as question_titles'),DB::raw('GROUP_CONCAT(qp.question_ids) as question_str'),'ch.chapter_name',DB::raw('ch.id as chapter_id'),)
            ->leftjoin('lms_online_exam as le', 'le.question_paper_id', '=', 'qp.id')
            ->join('lms_question_master as qm', function ($join) {
                $join->on('qm.sub_institute_id','=','qp.sub_institute_id')->whereRaw('qm.id IN (qp.question_ids)');
            })
            ->join('chapter_master as ch', 'ch.id', '=', 'qm.chapter_id')
            ->where('qp.sub_institute_id', $sub_institute_id)
            ->where('qp.standard_id', $request->standard)
            ->when($request->subject_id,function($q) use($request){
                $q->where('qp.subject_id', $request->subject_id);
            })
            ->where('qp.syear',$syear)
            ->where('le.student_id',$request->student_id) // for student wise chapter data
            ->groupBy('ch.chapter_name')
            ->get()->toArray();
            // dd($chapterData);exit;
            // dd(db::getQueryLog($chapterData));    exit;
            // echo "<pre>";print_r($chapterData);exit;
        foreach ($subjectDatas as $value) {
            $subTotMark +=$value->total;
            $stdObtMark +=$value->obtain;
           
            $allchapterdata[$value->subject_id]['subjectdata'] = $value->subject;             
            $allchapterdata[$value->subject_id]['totalmarks'] = $subTotMark;
            $allchapterdata[$value->subject_id]['totalobtain'] = $stdObtMark;

        // Access the results
        $chapters=$all_mapped_data=[];
        foreach ($chapterData as $row) {
            // get mapping details
            // DB::enableQueryLog();
            $mappedQuestion = DB::table('lms_question_mapping as lqm')->join('lms_mapping_type as lmt', 'lmt.id', '=' ,'lqm.mapping_type_id')->join('lms_mapping_type as lmv', 'lmv.id','=','lqm.mapping_value_id')
            ->selectRaw('lqm.id,lqm.questionmaster_id,lmt.name as type_name,lmv.name as value_name,count(lqm.questionmaster_id) as total_question')
            ->whereRaw('lqm.questionmaster_id IN ('.$row->question_ids.')')
            ->groupBy(['type_name','value_name'])->get()->toArray();
            // dd($mappedQuestion);
            $transformedData = [];

            foreach ($mappedQuestion as $item) {
                $type = $item->type_name;
                $title = $item->value_name;
                $progress = $item->total_question;

                // Initialize the chapter key if not already set
                if (!isset($transformedData[$row->chapter_name])) {
                    $transformedData[$row->chapter_name] = [];
                }
            
                if (!isset($transformedData[$row->chapter_name][$type])) {
                    $transformedData[$row->chapter_name][$type] = [
                        'type' => $type,
                        'data' => []
                    ];
                }
            
                $transformedData[$row->chapter_name][$type]['data'][] = [
                    'title' => $title,
                    'progress' => $progress,
                ];
            }
            
            // Convert the associative array to indexed array if key exists
            if (isset($transformedData[$row->chapter_name])) {
                $transformedData = array_values($transformedData[$row->chapter_name]);
            }

            // db::enableQueryLog();
            $getCurrentStudentExam = DB::table('lms_online_exam')->selectRaw('group_concat(DISTINCT question_paper_id) as question_paper_id')->whereRaw('student_id = '.$request->student_id.'')->groupBy('student_id')->first();
            // dd(db::getQueryLog($getCurrentStudentExam));
            $explodedQuestionPaper = isset($getCurrentStudentExam->question_paper_id) ? explode(',',$getCurrentStudentExam->question_paper_id) : [];
            // echo "<pre>";print_r($explodedQuestionPaper);exit;
            //chapter progress
            $chpaterProgress=DB::table('question_paper as qp')
            ->leftjoin('lms_online_exam as loe','loe.question_paper_id','=','qp.id')
            ->join('lms_question_master as lqm',function($join){
                $join->on('qp.sub_institute_id','=','lqm.sub_institute_id')->whereRaw('lqm.id IN (qp.question_ids)');
            })
            ->join('tblstudent as s','s.id','=','loe.student_id')
            ->join('tblstudent_enrollment as se','se.student_id','=','s.id')
            ->selectRaw("qp.*,lqm.*,loe.*,s.*,sum(qp.total_marks) as total_marks, sum(IFNULL(loe.total_right, '0')) AS obtain_marks,GROUP_CONCAT(qp.question_ids) as question_ids")
            ->whereRaw('qp.standard_id='.$request->standard.'  and qp.sub_institute_id='.$sub_institute_id.' and qp.syear='.$syear.' and lqm.chapter_id='.$row->chapter_id.' ')->groupBy('qp.subject_id')->get()->toArray();
            // echo "<pre>";print_r($getCurrentStudentExam->question_paper_id);exitand loe.question_paper_id NOT IN ('.$getCurrentStudentExam->question_paper_id.') ;
            $progressData = $all_students=[];
           
            foreach ($chpaterProgress as $item) {
                $student = $item->student_id;
                $paper_name  = $item->paper_name.'-'.$item->paper_desc; 
            
                if (!isset($progressData[$row->chapter_id])) {
                    $progressData[$row->chapter_id] = [
                        'quiz' => $paper_name,
                        'students' => [],
                    ];
                }
                // $all_students[$row->chapter_id][$row->question_paper_id] =$student;
                if(!in_array($row->question_paper_id,$explodedQuestionPaper)){
                    $progressData[$row->chapter_id]['students'][] = [
                        'id' => $student,
                        'name' => $item->first_name.' '.$item->middle_name.' '.$item->last_name,
                        'photo' => 'https://erp.triz.co.in/storage/student/'.$item->image,
                        'progress' => $item->obtain_marks,
                        'total' =>$item->total_marks,
                    ];
                 }

            }
           
            $progressData = array_values($progressData);
                if($value->subject_id == $row->subject_id){
                    $studentPer = ($row->total_marks>0) ? ($row->obtain_marks*100) /$row->total_marks : 0;
                    $chper = ($row->total_marks>0) ? ($studentPer) /10 : 0;
                    $controller = new questionmasterController;
                    $Realistic = $Investigative = $Artistic = $Social = $Enterprising = $Conventional = 0;
                    // get question map values
                    if(isset($row->question_ids)){
                        $question_ids = explode(',',$row->question_ids);
                        foreach ($question_ids as $qk => $qv) {
                            $getQuestionData = DB::table('lms_question_master')->where('id',$qv)->first();
                            if(!empty($getQuestionData) && $getQuestionData->subject_id==$row->subject_id && $row->chapter_id == $getQuestionData->chapter_id){
                                $request2 = new Request(['question_id'=>$qv]);
                                $mapValues = $controller->getMappedValue($request2);
                               foreach ($mapValues['MappedData'] as $it=>$item) { 
                                    if (isset($item->name) && $item->name == "Interests") {
                                       foreach ($item->mappedValue as $k => $v) {
                                        $Realistic +=($v->name== "Realistic") ? 1 :0;
                                        $Investigative +=($v->name== "Investigative") ? 1 :0;
                                        $Artistic +=($v->name== "Artistic") ? 1 :0;
                                        $Social += ($v->name== "Social") ? 1 :0;
                                        $Enterprising +=($v->name== "Enterprising") ? 1 :0;
                                        $Conventional += ($v->name== "Conventional") ? 1 :0;
                                       }
                                    }
                                }
                            }
                        }
                    }
                    $conRealistic = ( $Realistic > 0) ? round(($Realistic*100)/40) : 0;
                    $conInvestigative = ( $Investigative > 0) ? round(($Investigative*100)/40) : 0;
                    $conArtistic = ( $Artistic > 0) ? round(($Artistic*100)/40) : 0;
                    $conSocial =  ( $Social > 0) ? round(($Social*100)/40) : 0;
                    $conEnterprising = ( $Enterprising > 0) ? round(($Enterprising*100)/40) : 0;
                    $conConventional =  ( $Conventional > 0) ? round(($Conventional*100)/40) : 0;
                    // echo $conRealistic.'_'.$conInvestigative.'_'.$conArtistic.'_'.$conSocial.'_'.$conEnterprising.'_'.$conConventional.'<br>';
                    // get occupation
                    $lmsCounsellingController = new  lmsCounsellingController;

                    $request3 = new Request(["Realistic" => $conRealistic, "Investigative" => $conInvestigative, "Artistic" => $conArtistic, "Social" => $conSocial, "Enterprising" => $conEnterprising, "Conventional" => $conConventional]);

                    $intrestOccupation = $lmsCounsellingController->intrestEnterScore($request3);
                    $dataArray = $intrestOccupation->getData(true);
                    // echo "<pre>";print_r($dataArray);exit;
                    $occupation = [];
                    if(isset($dataArray['career']) && !empty($dataArray['career'])){
                        // echo $conRealistic.'_'.$conInvestigative.'_'.$conArtistic.'_'.$conSocial.'_'.$conEnterprising.'_'.$conConventional.'<br>';
                        // echo "<pre>";print_r($dataArray['career']);
                        foreach ($dataArray['career'] as $ck => $cv) {
                           $occupation[] = array(
                            "type"=>"video",
                            "title"=>$cv['title'],
                            "link"=>"https://youtu.be/wDchsz8nmbo?si=O8Md_owjD1ipg383",
                           );
                        }
                    }
                    // echo "<pre>";print_r($occupation);exit;
                    $chapters[$value->subject_id][]=array(
                        "title"=>$row->chapter_name,
                        "totalmarks"=>$row->total_marks,
                        "totalobtain"=>$row->obtain_marks,
                        "chapterrank"=>round($chper,2),
                        "recommendation"=>$occupation,
                        "chapteroutcome"=> $transformedData,
                        "chapterprogress"=> $progressData,
                    );
                }
            }
            if(isset($item->subject_id)){
                $allchapterdata[$value->subject_id]['chapterdata'] = $chapters;
            }
        }

        $flattenedData2 = [];
        
        foreach ($newData as $standard) {
            foreach($subjectDatas as $k =>$subjectVal){
                    $flattenedData2[$subjectVal->subject_id]['subjectdata']=!empty($allchapterdata[$subjectVal->subject_id]['subjectdata']) ? $allchapterdata[$subjectVal->subject_id]['subjectdata'] : [];
                    $flattenedData2[$subjectVal->subject_id]['totalmarks']=!empty($allchapterdata[$subjectVal->subject_id]['totalmarks']) ? $allchapterdata[$subjectVal->subject_id]['totalmarks'] : [];
                    $flattenedData2[$subjectVal->subject_id]['totalobtain']=!empty($allchapterdata[$subjectVal->subject_id]['totalobtain']) ? $allchapterdata[$subjectVal->subject_id]['totalobtain'] : [];  
                    $flattenedData2[$subjectVal->subject_id]['chapterdata']=!empty($allchapterdata[$subjectVal->subject_id]['chapterdata']) ? $allchapterdata[$subjectVal->subject_id]['chapterdata'] : [];                     
             }             
        }
        $current_marks['currentdata']['subjectdata'] = $flattenedData2; 
        

        // get current students details
        $studentDetail = DB::table('tblstudent as s')
        ->select('s.id as student_id', 's.image as photo', 's.dob as birthdate', 's.address', 's.admission_date', 'hm.house_name', 'b.title as batch_name',
                  DB::raw('group_concat(ssm.display_name) as optional_subject'))
        ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
        ->leftJoin('house_master as hm', 'hm.id', '=', 's.house')
        ->leftJoin('student_optional_subject as sos', 'sos.student_id', '=', 's.id')
        ->leftJoin('sub_std_map as ssm', 'ssm.subject_id', '=', 'sos.subject_id')
        ->leftJoin('batch as b', 'b.id', '=', 's.studentbatch')
        ->where('se.syear', '=', $request->syear)
        ->where('s.sub_institute_id', '=', $request->sub_institute_id)
        ->where('s.id', '=', $request->student_id)
        ->groupBy('sos.student_id')
        ->first();
        // exit;
        $current_marks['currentdata']['student_id'] = isset($studentDetail->student_id) ? $studentDetail->student_id : null; 
        $current_marks['currentdata']['syear'] = isset($request->syear) ? $request->syear : null;
        $current_marks['currentdata']['sub_institute_id'] = isset($request->sub_institute_id) ? $request->sub_institute_id : null;                
        $current_marks['currentdata']['photo'] = isset($studentDetail->photo) ? $studentDetail->photo : null;
        $current_marks['currentdata']['birthdate'] = isset($studentDetail->birthdate) ? $studentDetail->birthdate : null;                
        $current_marks['currentdata']['address'] =isset($studentDetail->address) ? $studentDetail->address : null;                
        $current_marks['currentdata']['admission_date'] = isset($studentDetail->admission_date) ? $studentDetail->admission_date : null;                
        $current_marks['currentdata']['house'] = isset($studentDetail->house_name) ? $studentDetail->house_name : null;                
        $current_marks['currentdata']['batch'] =isset($studentDetail->batch_name) ?  $studentDetail->batch_name : null;                
        $current_marks['currentdata']['optional_subjects'] =isset($studentDetail->optional_subject) ?  $studentDetail->optional_subject : null;                                       
        return $current_marks;
    }
}
