<?php

namespace App\Http\Controllers;

use App\Models\inward_outward\inwardModel;
use App\Models\inward_outward\outwardModel;
use App\Models\student\tblstudentModel;
use App\Models\visitor_management\visitor_masterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;

class used_storage_graphController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $syear = session()->get('syear');
        $term_id = session()->get('term_id');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');
        $user_name = session()->get('name');

        $chart_data = "[{
            id: '0.0',
            parent: '',
            name: 'Storage Data'
        },";

        $modules = array();
        if ($user_profile_name == 'Admin') {
            $modules = array(
                'photo_video_gallary' => 'Photo Video Gallary',
                'leave_applications'  => 'Leave Applications',
                'exam_schedule'       => 'Exam Schedule',
                'inward'              => 'Inward',
                'outward'             => 'Outward',
                'petty_cash'          => 'Petty Cash',
                'homework'            => 'Homework',
                'homework_submission' => 'Homework Submission',
                'visitor_master'      => 'Visitor Master',
                'front_desk'          => 'Front Desk',
                'task'                => 'Task',
                'complaint'           => 'Complaint',
                'student'             => 'Student',
                'student_health'      => 'Student Health',
            );
        }

        if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
            $modules = array(
                'petty_cash'          => 'Petty Cash',
                'homework'            => 'Homework',
                'homework_submission' => 'Homework Submission',
                'front_desk'          => 'Front Desk',
                'task'                => 'Task',
                'complaint'           => 'Complaint',
                'student_health'      => 'Student Health',
            );
        }

        if ($user_profile_name == 'Student') {
            $modules = array(
                'photo_video_gallary' => 'Photo Video Gallary',
                'leave_applications'  => 'Leave Applications',
                'exam_schedule'       => 'Exam Schedule',
                'homework'            => 'Homework',
                'homework_submission' => 'Homework Submission',
                'student'             => 'Student',
            );
        }

        $tables = array();
        $i = $j = 1;
        foreach ($modules as $table_name => $module_title) {
            $tables[] = $table_name;
            $chart_data .= "{";
            $chart_data .= "id: "."'1.".$i."',";
            $chart_data .= "parent: '0.0',";
            $chart_data .= "name: "."'".$module_title."'";
            $chart_data .= "},";

            if ($table_name == 'inward') {
                $get_inward_data = inwardModel::selectRaw("SUM(IFNULL(attachment_size, 0)) AS total_size")
                    ->where('sub_institute_id', $sub_institute_id)->get();
            
                if ($get_inward_data->count() > 0) {
                    $inward_used_space_in_MB = $this->formatBytes($get_inward_data[0]->total_size);
            
                    $chart_data .= "{";
                    $chart_data .= "id: " . "'2." . $j . "',";
                    $chart_data .= "parent: " . "'1." . $i . "',";
                    $chart_data .= "name: " . "'" . $user_name . "',";
                    $chart_data .= "value: " . $inward_used_space_in_MB;
                    $chart_data .= "},";
                } else {
                    // Handle the case where no data was found for 'inward'
                    $chart_data .= "{";
                    $chart_data .= "id: " . "'2." . $j . "',";
                    $chart_data .= "parent: " . "'1." . $i . "',";
                    $chart_data .= "name: " . "'" . $user_name . "',";
                    $chart_data .= "value: 0"; // Set a default value or handle as needed
                    $chart_data .= "},";
                }
            }
            

            if ($table_name == 'outward') {
                $get_outward_data = outwardModel::selectRaw("SUM(IFNULL(attachment_size, 0)) AS total_size")
                    ->where('sub_institute_id', $sub_institute_id)->first(); // Use first() to get a single result
            
                if ($get_outward_data) {
                    $outward_used_space_in_MB = $this->formatBytes($get_outward_data->total_size);
            
                    $chart_data .= "{";
                    $chart_data .= "id: " . "'2." . $j . "',";
                    $chart_data .= "parent: " . "'1." . $i . "',";
                    $chart_data .= "name: " . "'" . $user_name . "',";
                    $chart_data .= "value: " . $outward_used_space_in_MB;
                    $chart_data .= "},";
                } else {
                    // Handle the case where no data was found for 'outward'
                    $chart_data .= "{";
                    $chart_data .= "id: " . "'2." . $j . "',";
                    $chart_data .= "parent: " . "'1." . $i . "',";
                    $chart_data .= "name: " . "'" . $user_name . "',";
                    $chart_data .= "value: 0"; // Set a default value or handle as needed
                    $chart_data .= "},";
                }
            }
            

            if ($table_name == 'photo_video_gallary') {
                if ($user_profile_name == 'Student') {
                    $get_photo_data = DB::table('photo_video_gallary as p')
                        ->join('tblstudent_enrollment as se', function ($join) {
                            $join->whereRaw("se.standard_id = p.standard_id AND se.section_id = p.division_id AND se.sub_institute_id = p.sub_institute_id");
                        })->join('tblstudent as s', function ($join) {
                            $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                        })
                        ->selectRaw('SUM(IFNULL(p.file_size,0)) AS total_size')
                        ->where('p.sub_institute_id', $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
                    $photo_used_space_in_MB = $this->formatBytes($get_photo_data[0]->total_size);
                } else {
                    $get_photo_data = DB::table('photo_video_gallary')->selectRaw('SUM(IFNULL(file_size,0)) AS total_size')
                        ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
                    $photo_used_space_in_MB = $this->formatBytes($get_photo_data[0]->total_size);
                }

                $chart_data .= "{";
                $chart_data .= "id: "."'2.".$j."',";
                $chart_data .= "parent: "."'1.".$i."',";
                $chart_data .= "name: "."'".$user_name."',";
                $chart_data .= "value: ".$photo_used_space_in_MB;
                $chart_data .= "},";
            }

            if ($table_name == 'leave_applications') {
                if ($user_profile_name == 'Student') {
                    $get_leave_app_data = DB::table('leave_applications as l')
                        ->join('tblstudent as s', function ($join) {
                            $join->whereRaw("s.id = l.student_id AND s.sub_institute_id = l.sub_institute_id");
                        })
                        ->selectRaw('SUM(IFNULL(l.file_size,0)) AS total_size')
                        ->where('l.sub_institute_id', $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
                    $leave_used_space_in_MB = $this->formatBytes($get_leave_app_data[0]->total_size);
                } else {
                    $get_leave_app_data = DB::table('leave_applications')->selectRaw('SUM(IFNULL(file_size,0)) AS total_size')
                        ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
                    $leave_used_space_in_MB = $this->formatBytes($get_leave_app_data[0]->total_size);
                }

                $chart_data .= "{";
                $chart_data .= "id: "."'2.".$j."',";
                $chart_data .= "parent: "."'1.".$i."',";
                $chart_data .= "name: "."'".$user_name."',";
                $chart_data .= "value: ".$leave_used_space_in_MB;
                $chart_data .= "},";
            }

            if ($table_name == 'exam_schedule') {
                if ($user_profile_name == 'Student') {
                    $get_exam_schedule_data = DB::table('exam_schedule as e')
                        ->join('tblstudent_enrollment as se', function ($join) {
                            $join->whereRaw("se.standard_id = e.standard_id AND se.section_id = e.division_id AND se.sub_institute_id = e.sub_institute_id");
                        })->join('tblstudent as s', function ($join) {
                            $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                        })
                        ->selectRaw('SUM(IFNULL(e.file_size,0)) AS total_size')
                        ->where('e.sub_institute_id', $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
                    $exam_schedule_used_space_in_MB = $this->formatBytes($get_exam_schedule_data[0]->total_size);
                } else {
                    $get_exam_schedule_data = DB::table('exam_schedule')->selectRaw('SUM(IFNULL(file_size,0)) AS total_size')
                        ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
                    $exam_schedule_used_space_in_MB = $this->formatBytes($get_exam_schedule_data[0]->total_size);
                }

                $chart_data .= "{";
                $chart_data .= "id: "."'2.".$j."',";
                $chart_data .= "parent: "."'1.".$i."',";
                $chart_data .= "name: "."'".$user_name."',";
                $chart_data .= "value: ".$exam_schedule_used_space_in_MB;
                $chart_data .= "},";
            }

            if ($table_name == 'petty_cash') {
                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND p.user_id = '".$user_id."' ";
                }
                
                $get_petty_cash_data = DB::table('petty_cash as p')
                    ->join('tbluser as u', function ($join) {
                        $join->whereRaw("u.id = p.user_id AND p.sub_institute_id = u.sub_institute_id");
                    })
                    ->selectRaw("SUM(IFNULL(p.file_size,0)) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name")
                    ->whereRaw("p.sub_institute_id = '".$sub_institute_id."' $extra")
                    ->groupBy('p.user_id')->get()->toArray();
                $get_petty_cash_data = json_decode(json_encode($get_petty_cash_data), true);

                $total_petty_cash_used_space_in_MB = 0;
                foreach ($get_petty_cash_data as $key => $val) {
                    $petty_cash_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$petty_cash_used_space_in_MB;
                    $chart_data .= "},";

                    $total_petty_cash_used_space_in_MB += $petty_cash_used_space_in_MB;
                }
            }

            if ($table_name == 'homework') {

                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND p.created_by = '".$user_id."' ";
                }

                if ($user_profile_name == 'Student') {
                    $get_homework_data = DB::table('homework as p')
                        ->join('tblstudent as u', function ($join) {
                            $join->whereRaw("s.id = p.student_id AND s.sub_institute_id = p.sub_institute_id");
                        })
                        ->selectRaw("IFNULL(SUM(DISTINCT p.image_size),0) AS total_size, CONCAT_WS(' ',s.first_name,s.last_name) AS user_name, p.image_size")
                        ->where("p.sub_institute_id", $sub_institute_id)
                        ->where("s.id", $user_id)
                        ->get()->toArray();
                    $get_homework_data = json_decode(json_encode($get_homework_data), true);
                } else {
                    $get_homework_data = DB::table('homework as p')
                        ->join('tbluser as u', function ($join) {
                            $join->whereRaw("u.id = p.created_by AND p.sub_institute_id = u.sub_institute_id");
                        })
                        ->selectRaw("IFNULL(SUM(DISTINCT p.image_size),0) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name, p.image_size")
                        ->where("p.sub_institute_id", $sub_institute_id)
                        ->groupBy("p.created_by")->having('total_size', '>', 0)
                        ->get()->toArray();
                    $get_homework_data = json_decode(json_encode($get_homework_data), true);
                }

                $total_homework_used_space_in_MB = 0;
                foreach ($get_homework_data as $key => $val) {
                    $homework_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$homework_used_space_in_MB;
                    $chart_data .= "},";

                    $total_homework_used_space_in_MB += $homework_used_space_in_MB;

                }
            }

            if ($table_name == 'homework_submission') {
                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND p.created_by = '".$user_id."' ";
                }
                if ($user_profile_name == 'Student') {
                    $get_homework_submission_data = DB::table('homework as p')
                        ->join('tblstudent as u', function ($join) {
                            $join->whereRaw("s.id = p.student_id AND s.sub_institute_id = p.sub_institute_id");
                        })
                        ->selectRaw("IFNULL(SUM(DISTINCT p.submission_image_size),0) AS total_size, CONCAT_WS(' ',s.first_name,s.last_name) AS user_name, p.submission_image_size")
                        ->where("p.sub_institute_id", $sub_institute_id)
                        ->where("s.id", $user_id)
                        ->get()->toArray();
                    $get_homework_submission_data = json_decode(json_encode($get_homework_submission_data), true);
                } else {
                    $get_homework_submission_data = DB::table('homework as p')
                        ->join('tbluser as u', function ($join) {
                            $join->whereRaw("u.id = p.created_by AND p.sub_institute_id = u.sub_institute_id");
                        })
                        ->selectRaw("IFNULL(SUM(DISTINCT p.submission_image_size),0) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name, p.submission_image_size")
                        ->where("p.sub_institute_id", $sub_institute_id)
                        ->groupBy("p.created_by")->having('total_size', '>', 0)
                        ->get()->toArray();
                    $get_homework_submission_data = json_decode(json_encode($get_homework_submission_data), true);
                }

                $total_homework_submission_used_space_in_MB = 0;
                foreach ($get_homework_submission_data as $key => $val) {
                    $homework_submission_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$homework_submission_used_space_in_MB;
                    $chart_data .= "},";

                    $total_homework_submission_used_space_in_MB += $homework_submission_used_space_in_MB;                    
                }
            }

            if ($table_name == 'visitor_master') {
                $get_visitor_data = visitor_masterModel::selectRaw('SUM(IFNULL(file_size, 0)) AS total_size')
                    ->where('sub_institute_id', $sub_institute_id)->first(); // Use first() to get a single result
            
                if ($get_visitor_data) {
                    $visitor_used_space_in_MB = $this->formatBytes($get_visitor_data->total_size);
            
                    $chart_data .= "{";
                    $chart_data .= "id: " . "'2." . $j . "',";
                    $chart_data .= "parent: " . "'1." . $i . "',";
                    $chart_data .= "name: " . "'" . $user_name . "',";
                    $chart_data .= "value: " . $visitor_used_space_in_MB;
                    $chart_data .= "},";
                } else {
                    // Handle the case where no data was found for 'visitor_master'
                    $chart_data .= "{";
                    $chart_data .= "id: " . "'2." . $j . "',";
                    $chart_data .= "parent: " . "'1." . $i . "',";
                    $chart_data .= "name: " . "'" . $user_name . "',";
                    $chart_data .= "value: 0"; // Set a default value or handle as needed
                    $chart_data .= "},";
                }
            }
            

            if ($table_name == 'front_desk') {
                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND f.CREATED_BY = '".$user_id."' ";
                }
                
                $get_frontdesk_data = DB::table('front_desk as f')
                    ->join('tbluser as u', function ($join) {
                        $join->whereRaw('u.id = f.CREATED_BY AND f.SUB_INSTITUTE_ID = u.sub_institute_id');
                    })
                    ->selectRaw("SUM(IFNULL(f.FILE_SIZE,0)) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name")
                    ->whereRaw("f.SUB_INSTITUTE_ID = '".$sub_institute_id."' $extra")->groupBy("f.CREATED_BY")->get()->toArray();
                $get_frontdesk_data = json_decode(json_encode($get_frontdesk_data), true);

                $total_frontdesk_used_space_in_MB = 0;
                foreach ($get_frontdesk_data as $key => $val) {
                    $frontdesk_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$frontdesk_used_space_in_MB;
                    $chart_data .= "},";

                    $total_frontdesk_used_space_in_MB += $frontdesk_used_space_in_MB;
                }
            }

            if ($table_name == 'task') {
                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND f.CREATED_BY = '".$user_id."' ";
                }
                
                $get_task_data = DB::table('task as f')
                    ->join('tbluser as u', function ($join) {
                        $join->whereRaw('u.id = f.CREATED_BY AND f.SUB_INSTITUTE_ID = u.sub_institute_id');
                    })
                    ->selectRaw("SUM(IFNULL(f.FILE_SIZE,0)) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name")
                    ->whereRaw("f.SUB_INSTITUTE_ID = '".$sub_institute_id."' $extra")->groupBy("f.CREATED_BY")->get()->toArray();
                $get_task_data = json_decode(json_encode($get_task_data), true);

                $total_task_used_space_in_MB = 0;
                foreach ($get_task_data as $key => $val) {
                    $task_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$task_used_space_in_MB;
                    $chart_data .= "},";

                    $total_task_used_space_in_MB += $task_used_space_in_MB;
                }
            }

            if ($table_name == 'complaint') {
                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND f.COMPLAINT_BY = '".$user_id."' ";
                }
                
                $get_complaint_data = DB::table('complaint as f')
                    ->join('tbluser as u', function ($join) {
                        $join->whereRaw('u.id = f.COMPLAINT_BY AND f.SUB_INSTITUTE_ID = u.sub_institute_id');
                    })
                    ->selectRaw("SUM(IFNULL(f.FILE_SIZE,0)) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name")
                    ->whereRaw("f.SUB_INSTITUTE_ID = '".$sub_institute_id."' $extra")->groupBy("f.COMPLAINT_BY")->get()->toArray();
                $get_complaint_data = json_decode(json_encode($get_complaint_data), true);

                $total_complaint_used_space_in_MB = 0;
                foreach ($get_complaint_data as $key => $val) {
                    $complaint_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$complaint_used_space_in_MB;
                    $chart_data .= "},";

                    $total_complaint_used_space_in_MB += $complaint_used_space_in_MB;
                }
            }

            if ($table_name == 'student') {
                if ($user_profile_name == 'Student') {
                    $get_student_data = tblstudentModel::selectRaw("IFNULL(SUM(file_size), 0) AS total_size")
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('id', $user_id)
                        ->first(); // Use first() to get a single result
            
                    if ($get_student_data) {
                        $student_used_space_in_MB = $this->formatBytes($get_student_data->total_size);
                    } else {
                        // Handle the case where no data was found for the specific student
                        $student_used_space_in_MB = 0; // Set a default value or handle as needed
                    }
                } else {
                    $get_student_data = tblstudentModel::selectRaw("IFNULL(SUM(file_size), 0) AS total_size")
                        ->where('sub_institute_id', $sub_institute_id)
                        ->first(); // Use first() to get a single result
            
                    if ($get_student_data) {
                        $student_used_space_in_MB = $this->formatBytes($get_student_data->total_size);
                    } else {
                        // Handle the case where no data was found for all students
                        $student_used_space_in_MB = 0; // Set a default value or handle as needed
                    }
                }
            
                $chart_data .= "{";
                $chart_data .= "id: " . "'2." . $j . "',";
                $chart_data .= "parent: " . "'1." . $i . "',";
                $chart_data .= "name: " . "'" . $user_name . "',";
                $chart_data .= "value: " . $student_used_space_in_MB;
                $chart_data .= "},";
            }
            

            if ($table_name == 'student_health') {
                $extra = "";
                if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
                    $extra .= " AND f.created_by = '".$user_id."' ";
                }

                $get_student_health_data = DB::table('student_health as f')
                    ->join('tbluser as u', function ($join) {
                        $join->whereRaw('u.id = f.created_by AND f.sub_institute_id = u.sub_institute_id');
                    })
                    ->selectRaw("SUM(IFNULL(f.file_size,0)) AS total_size, CONCAT_WS(' ',u.first_name,u.last_name) AS user_name")
                    ->whereRaw("f.sub_institute_id = '".$sub_institute_id."' $extra")->groupBy('f.created_by')
                    ->get()->toArray();
                $get_student_health_data = json_decode(json_encode($get_student_health_data), true);

                $total_student_health_used_space_in_MB = 0;
                foreach ($get_student_health_data as $key => $val) {
                    $student_health_used_space_in_MB = $this->formatBytes($val['total_size']);
                    $chart_data .= "{";
                    $chart_data .= "id: "."'2.".$j."',";
                    $chart_data .= "parent: "."'1.".$i."',";
                    $chart_data .= "name: "."'".$val['user_name']."',";
                    $chart_data .= "value: ".$student_health_used_space_in_MB;
                    $chart_data .= "},";

                    $total_student_health_used_space_in_MB += $student_health_used_space_in_MB;
                }
            }

            $i++;
            $j++;
        }

        if ($user_profile_name == 'Admin') {
            $total_used_space_array = array(
                'Inward'              => $inward_used_space_in_MB,
                'Outward'             => $outward_used_space_in_MB,
                'Photo Video Gallary' => $photo_used_space_in_MB,
                'Leave Applications'  => $leave_used_space_in_MB,
                'Exam Schedule'       => $exam_schedule_used_space_in_MB,
                'Petty Cash'          => $total_petty_cash_used_space_in_MB,
                'Homework'            => $total_homework_used_space_in_MB,
                'Homework Submission' => $total_homework_submission_used_space_in_MB,
                'Visitor'             => $visitor_used_space_in_MB,
                'Front Desk'          => $total_frontdesk_used_space_in_MB,
                'Task'                => $total_task_used_space_in_MB,
                'Complaint'           => $total_complaint_used_space_in_MB,
                'Student'             => $student_used_space_in_MB,
                'Student Health'      => $total_student_health_used_space_in_MB,
            );
        }

        if ($user_profile_name != 'Admin' && $user_profile_name != 'Student') {
            $total_used_space_array = array(
                'Petty Cash'          => $total_petty_cash_used_space_in_MB,
                'Homework'            => $total_homework_used_space_in_MB,
                'Homework Submission' => $total_homework_submission_used_space_in_MB,
                'Front Desk'          => $total_frontdesk_used_space_in_MB,
                'Task'                => $total_task_used_space_in_MB,
                'Complaint'           => $total_complaint_used_space_in_MB,
                'Student Health'      => $total_student_health_used_space_in_MB,
            );
        }

        if ($user_profile_name == 'Student') {
            $total_used_space_array = array(
                'Photo Video Gallary' => $photo_used_space_in_MB,
                'Leave Applications'  => $leave_used_space_in_MB,
                'Exam Schedule'       => $exam_schedule_used_space_in_MB,
                'Homework'            => $total_homework_used_space_in_MB,
                'Homework Submission' => $total_homework_submission_used_space_in_MB,
                'Student'             => $student_used_space_in_MB,
            );
        }
        
        $school_setup_data = DB::table('school_setup')->selectRaw("*,SUM(IFNULL(given_space_mb,0)) as given_space_mb")
            ->where('Id', $sub_institute_id)->get()->toArray();
        $school_setup_data = json_decode(json_encode($school_setup_data[0]), true);
        $occupied_space_in_MB = $school_setup_data['given_space_mb'];

        $chart_data = rtrim($chart_data, ",");
        $chart_data .= "];";
        $res['chartData'] = $chart_data;
        $res['total_used_space_array'] = $total_used_space_array;
        $res['occupied_space_in_MB'] = $occupied_space_in_MB;

        return is_mobile($type, "used_storage_table_view", $res, "view");
    }

    
    public function formatBytes($bytes, $precision = 2) { 
        $units = array('B', 'KB');  //, 'MB', 'GB', 'TB'

        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow)); 

        return round($bytes, $precision); 
    }
}
