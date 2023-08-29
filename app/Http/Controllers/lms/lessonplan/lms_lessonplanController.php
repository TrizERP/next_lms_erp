<?php

namespace App\Http\Controllers\lms\lessonplan;

use App\Http\Controllers\Controller;
use App\Models\FormSubmitData;
use App\Models\FormTable;
use App\Models\lms\chapterModel;
use App\Models\lms\contentModel;
use App\Models\lms\LmsLessonPlan;
use App\Models\lms\LmsLessonPlanDayWise;
use App\Models\lms\questionpaperModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\lessonplanningModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\timetableModel;
use Exception;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class lms_lessonplanController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $type = $request->input('type');
        $id = $request->id;
        if($request->has('preload_lms')){
            $res['preload_lms'] = "preload_lms";
        }
        $formData = $this->getFormData($request);

        $lessonData = LmsLessonPlan::when($id, function ($q) use ($id) {
            $q->whereId($id);
        })
            ->when(is_null($id), function ($q) use ($request) {
                $q->where('standard_id', $request->standard_id);
                $q->where('subject_id', $request->subject_id);
                $q->where('chapter_id', $request->chapter_id);
            })
            ->with(['standard', 'subject', 'chapter', 'topic', 'lessonDays'])
            ->first() ?? new LmsLessonPlan();
        $lessonData->standard_id = $lessonData->standard_id ?? $request->standard_id;
        $lessonData->subject_id = $lessonData->subject_id ?? $request->subject_id;
        $lessonData->chapter_id = $lessonData->chapter_id ?? $request->chapter_id;
        $standards = standardModel::select('id', 'name')
            ->where('sub_institute_id', $sub_institute_id)
            ->get();
        $subjects = subjectModel::select('id', 'subject_name')
            ->where('sub_institute_id', $sub_institute_id)
            ->get();
        $chapters = chapterModel::select('id', 'chapter_name')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('standard_id', $lessonData->standard_id)
            ->where('subject_id', $lessonData->subject_id)
            ->get();
        $topics = topicModel::select('id', 'name')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('chapter_id', $lessonData->chapter_id)
            ->get();
        // dd($lessonData);
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['lessonplan_data'] = $lessonData;
        // echo "<pre>";print_r($lessonData['lessonDays'][0]['selfstudyactivity']);exit;
        $res['form_data'] = $formData;
        $res['topics'] = $topics;
        $res['chapters'] = $chapters;
        $res['subjects'] = $subjects;
        $res['standards'] = $standards;
        return is_mobile($type, 'lms/lessonplan/add_lessonplan', $res, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $type = $request->input('type');
        $id = $request->id;

        $formData = $this->getFormData($request);

        $lessonData = LmsLessonPlan::when($id, function ($q) use ($id) {
            $q->whereId($id);
        })
            ->when(is_null($id), function ($q) use ($request) {
                $q->where('standard_id', $request->standard_id);
                $q->where('subject_id', $request->subject_id);
                $q->where('chapter_id', $request->chapter_id);
            })
            ->withCount('lessonDays')
            ->first() ?? new LmsLessonPlan();
        $lessonData->standard_id = $lessonData->standard_id ?? $request->standard_id;
        $lessonData->subject_id = $lessonData->subject_id ?? $request->subject_id;
        $lessonData->chapter_id = $lessonData->chapter_id ?? $request->chapter_id;
        $standards = standardModel::select('id as value', 'name as label')
            ->where('sub_institute_id', $sub_institute_id)
            ->get();
        $subjects = subjectModel::select('id as value', 'subject_name as label')
            ->where('sub_institute_id', $sub_institute_id)
            ->get();
        $chapters = chapterModel::select('id as value', 'chapter_name as label')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('standard_id', $lessonData->standard_id)
            ->where('subject_id', $lessonData->subject_id)
            ->get();
        $topics = topicModel::select('id as value', 'name as label')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('chapter_id', $lessonData->chapter_id)
            ->get();
        // dd($lessonData);
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['lessonplan_data'] = $lessonData;
        $res['form_data'] = $formData;
        $res['topics'] = $topics;
        $res['chapters'] = $chapters;
        $res['subjects'] = $subjects;
        $res['standards'] = $standards;
        return is_mobile($type, 'lms/lessonplan/create', $res, "view");
    }

    public function ajax_DayWiseData(Request $request)
    {
        $day = $request->day;
        $id = $request->id;
        $standard_id = $request->standard_id;
        $chapter_id = $request->chapter_id;
        $subject_id = $request->subject_id;
        $topic_id = $request->topic_id;
        $content_master = contentModel::select('id', 'title')
            ->when($request->standard_id, function ($q) use ($standard_id) {
                $q->where('standard_id', $standard_id);
            })
            ->when($request->chapter_id, function ($q) use ($chapter_id) {
                $q->where('chapter_id', $chapter_id);
            })
            ->when($request->subject_id, function ($q) use ($subject_id) {
                $q->where('subject_id', $subject_id);
            })
            ->when($request->topic_id, function ($q) use ($topic_id) {
                $q->where('topic_id', $topic_id);
            })
            ->get();
        $question_master = questionpaperModel::select('id', 'paper_name as title')
            ->when($request->standard_id, function ($q) use ($standard_id) {
                $q->where('standard_id', $standard_id);
            })
            ->when($request->subject_id, function ($q) use ($subject_id) {
                $q->where('subject_id', $subject_id);
            })
            ->get();
        $objDayWise = LmsLessonPlanDayWise::where('lpid', $id)->get();
        $data = View::make('lms.lessonplan.day_wise_lesson_plan', compact('day', 'objDayWise', 'content_master', 'question_master'));
        return $data;
    }

    public function ajax_contentMasterData(Request $request)
    {
        $standard_id = $request->standard_id;
        $chapter_id = $request->chapter_id;
        $subject_id = $request->subject_id;
        $topic_id = $request->topic_id;
        $content_master = contentModel::select('id', 'title')
            ->when($request->standard_id, function ($q) use ($standard_id) {
                $q->where('standard_id', $standard_id);
            })
            ->when($request->chapter_id, function ($q) use ($chapter_id) {
                $q->where('chapter_id', $chapter_id);
            })
            ->when($request->subject_id, function ($q) use ($subject_id) {
                $q->where('subject_id', $subject_id);
            })
            ->when($request->topic_id, function ($q) use ($topic_id) {
                $q->where('topic_id', $topic_id);
            })
            ->get();
        return response($content_master, 200);
    }

    public function ajax_questionPaperData(Request $request)
    {
        $standard_id = $request->standard_id;
        $subject_id = $request->subject_id;
        $content_master = questionpaperModel::select('id', 'paper_name as title')
            ->when($request->standard_id, function ($q) use ($standard_id) {
                $q->where('standard_id', $standard_id);
            })
            ->when($request->subject_id, function ($q) use ($subject_id) {
                $q->where('subject_id', $subject_id);
            })
            ->get();
        return response($content_master, 200);
    }

    /**
     * FormBuilder
     * Get Form Data
     */
    public function getFormData($request)
    {
        // $form_id = 1;
        $user_id = $request->session()->get('user_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_id = $request->standard_id;
        $subject_id = $request->subject_id;
        $chapter_id = $request->chapter_id;

        // get form submitted Data
        // DB::enableQueryLog();
        $get_form_data = FormSubmitData::where('user_id', $user_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('standard', $standard_id)
            ->where('subject', $subject_id)
            ->where('chapter', $chapter_id)
            ->get()
            ->first();

        if ($get_form_data) {
            // Get Form
            $get_from_fields_json = FormTable::find($get_form_data->form_id);

            // echo "<pre>"; print_r($get_form_data); exit;
            $form_fields_object = json_decode($get_from_fields_json->form_json);
            if (!empty($form_fields_object) && !empty($get_form_data)) {
                $form_data = (array) json_decode($get_form_data->form_data);
                $fieldObject = [
                    'form_id' => $get_form_data['form_id'],
                    'chapter_id' => $chapter_id,
                ];

                foreach ($form_fields_object as $formField) {

                    if ($formField->type == 'header') {
                        $fieldObject['header'] = $formField->label;
                        // continue;
                    }

                    if ($formField->type == 'date' || $formField->type == 'text' || $formField->type == 'textarea' || $formField->type == 'number' || $formField->type == 'date') {
                        if (isset($form_data[$formField->name])) {
                            $formField->value = $form_data[$formField->name];
                            $fieldObject[$formField->label] = $form_data[$formField->name];
                        }
                    }

                    if ($formField->type == 'select') {

                        if (isset($form_data[$formField->name])) {
                            $formField->values = $form_data[$formField->name];
                            $fieldObject[$formField->label] = $form_data[$formField->name];
                        }

                        if ($formField->label == 'Standard') {
                            if (isset($form_data[$formField->name])) {

                                $get_standard = DB::table('standard')
                                    ->select('name')
                                    ->where('id', $form_data[$formField->name])
                                    ->where('sub_institute_id', $sub_institute_id)
                                    ->first();

                                $formField->values = $form_data[$formField->name];
                                // dd($get_standard->name);
                                $fieldObject[$formField->label] = $get_standard->name;
                            }
                        } else if ($formField->label == 'Subject') {

                            // dd($form_data[$formField->name]);
                            if (isset($form_data[$formField->name])) {
                                // DB::enableQueryLog();
                                $get_subject = DB::table('subject')
                                    ->select('subject_name')
                                    ->where('id', $form_data[$formField->name])
                                    ->where('sub_institute_id', $sub_institute_id)
                                    ->first();
                                // dd(DB::getQueryLog());

                                // dd($get_subject);

                                $formField->values = $form_data[$formField->name];
                                $fieldObject[$formField->label] = $get_subject->subject_name;
                            }
                        } else if ($formField->label == 'Chapters') {

                            // dd($form_data[$formField->name]);
                            if (isset($form_data[$formField->name])) {
                                // DB::enableQueryLog();
                                $get_chapter = DB::table('chapter_master')
                                    ->select('chapter_name')
                                    ->where('id', $form_data[$formField->name])
                                    ->where('sub_institute_id', $sub_institute_id)
                                    ->first();
                                // dd(DB::getQueryLog());

                                // dd($get_chapter);

                                $formField->values = $form_data[$formField->name];
                                $fieldObject[$formField->label] = $get_chapter->chapter_name;
                            }
                        }

                    }
                }
                return $fieldObject;
            }
        }
        return false;
    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $title = $request->get('title');

        $std_data = standardModel::select('*')
            ->where(["sub_institute_id" => $sub_institute_id, "id" => $standard_id])
            ->get()->toArray();

        $sub_data = subjectModel::select('*')
            ->where(["sub_institute_id" => $sub_institute_id, "id" => $subject_id])
            ->get()->toArray();

        $div_data = std_div_mappingModel::select('d.id', 'd.name as division_name')
            ->join('division as d', 'std_div_map.division_id', 'd.id')
            ->where(["std_div_map.sub_institute_id" => $sub_institute_id, "std_div_map.standard_id" => $standard_id])
            ->get()->toArray();

        $lessonplan_data['standard_name'] = $std_data[0]['name'];
        $lessonplan_data['standard_id'] = $standard_id;
        $lessonplan_data['grade_id'] = $std_data[0]['grade_id'];
        if ($title != null) {
            $lessonplan_data['subject_name'] = $sub_data[0]['subject_name'] . ' - ' . $title;
        } else {
            $lessonplan_data['subject_name'] = $sub_data[0]['subject_name'];
        }

        $lessonplan_data['subject_id'] = $subject_id;
        $lessonplan_data['division_data'] = $div_data;

        return $lessonplan_data;
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'focauspoint' => 'required',
            'pedagogicalprocess' => 'required',
            'resource' => 'required',
            'classroompresentation' => 'required',
            'classroomdiversity' => 'required',
            'id' => 'nullable|exists:lms_lesson_plan,id',
        ]);
        try {
            $objLessonPlan = LmsLessonPlan::find($request->id) ?? new LmsLessonPlan();
            $objLessonPlan->sub_institute_id = session()->get('sub_institute_id');
            $objLessonPlan->syear = session()->get('syear');
            $objLessonPlan->standard_id = $request->standard_id;
            $objLessonPlan->subject_id = $request->subject_id;
            $objLessonPlan->chapter_id = $request->chapter_id;
            $objLessonPlan->topic_id = $request->topic;
            $objLessonPlan->standard_id = $request->standard;
            $objLessonPlan->numberofperiod = $request->numberofperiod;
            $objLessonPlan->teachingtime = $request->teachingtime;
            $objLessonPlan->assessmenttime = $request->assessmenttime;
            $objLessonPlan->learningtime = $request->learningtime;
            $objLessonPlan->assessmentqualifying = $request->assessmentqualifying;
            $objLessonPlan->focauspoint = $request->focauspoint;
            $objLessonPlan->pedagogicalprocess = $request->pedagogicalprocess;
            $objLessonPlan->resource = $request->resource;
            $objLessonPlan->classroompresentation = $request->classroompresentation;            
            $objLessonPlan->classroomactivity = implode(',', $request->classroomactivity ?? []);
            $objLessonPlan->classroomdiversity = $request->classroomdiversity;
            $objLessonPlan->prerequisite = $request->prerequisite;
            $objLessonPlan->learningobjective = $request->learningobjective;
            $objLessonPlan->learningknowledge = $request->learningknowledge;
            $objLessonPlan->learningskill = $request->learningskill;
            $objLessonPlan->selfstudyhomework = $request->selfstudyhomework;
            $objLessonPlan->selfstudyactivity = implode(',', $request->selfstudyactivity ?? []);
            $objLessonPlan->assessment = $request->assessment;
            $objLessonPlan->assessmentactivity = implode(',', $request->assessmentactivity ?? []);
            $objLessonPlan->hardword = $request->hardword;
            $objLessonPlan->tagmetatag = $request->tagmetatag;
            $objLessonPlan->valueintegration = $request->valueintegration;
            $objLessonPlan->globalconnection = $request->globalconnection;
            $objLessonPlan->sel = $request->sel;
            $objLessonPlan->stem = $request->stem;
            $objLessonPlan->vocationaltraining = $request->vocationaltraining;
            $objLessonPlan->simulation = $request->simulation;
            $objLessonPlan->games = $request->games;
            $objLessonPlan->activities = $request->activities;
            $objLessonPlan->reallifeapplication = $request->reallifeapplication;
            if ($objLessonPlan->save()) {
                $dayWiseData = LmsLessonPlanDayWise::where('lpid', $objLessonPlan->id)->pluck('days')->toArray();
                $inputDays = $request->days ?? [];
                $deleteData = array_diff($dayWiseData, $inputDays);
                LmsLessonPlanDayWise::whereIn('days', $deleteData)->where('lpid', $objLessonPlan->id)->delete();

                foreach ($inputDays as $i => $value) {
                    $objDayWise = LmsLessonPlanDayWise::where('days', $value)->where('lpid', $objLessonPlan->id)->first() ?? new LmsLessonPlanDayWise();
                    $objDayWise->lpid = $objLessonPlan->id;
                    $objDayWise->days = $value;
                    $objDayWise->topicname = $request->topicname[$value] ?? '';
                    $objDayWise->classtime = $request->classtime[$value] ?? '';
                    $objDayWise->duringcontent = $request->duringcontent[$value] ?? '';
                    $objDayWise->assessmentqualifying = $request->assessmentqualifyingday[$value] ?? '';
                    $objDayWise->learningobjective = $request->learningobjectiveday[$value] ?? '';
                    $objDayWise->learningoutcome = $request->learningoutcome[$value] ?? '';
                    $objDayWise->pedagogicalprocess = $request->pedagogicalprocessday[$value] ?? '';
                    $objDayWise->resource = $request->resourceday[$value] ?? '';
                    $objDayWise->closure = $request->closure[$value] ?? '';
                    $objDayWise->selfstudyhomework = $request->selfstudyhomeworkday[$value] ?? '';
                    $objDayWise->selfstudyactivity = implode(',', $request->selfstudyactivityday[$value] ?? []);
                    $objDayWise->assessment = $request->assessmentday[$value] ?? '';
                    $objDayWise->assessmentactivity = implode(',', $request->assessmentactivityday[$value] ?? []);
                    // dd($objDayWise);
                    $objDayWise->save();
                }
                $res = array(
                    "status_code" => 1,
                    "url" => route('lms_lessonplan.index', ['standard_id' => $objLessonPlan->standard_id, 'subject_id' => $objLessonPlan->subject_id, 'chapter_id' => $objLessonPlan->chapter_id]),
                    "message" => "Lesson Plan Added Successfully",
                );
                return response()->json($res);
            }
        } catch (Exception $e) {
            dump($e);
            $e->getMessage();
        }
    }

    public function ajax_getTeacher(Request $request)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $division_id = $request->input("division_id");
        $standard_id = $request->input("standard_id");
        $subject_id = $request->input("subject_id");

        $teacherData = DB::select("SELECT DISTINCT(teacher_id), CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name,u.user_profile_id
            FROM timetable t
            INNER JOIN tbluser u ON u.id = t.teacher_id AND u.sub_institute_id = t.sub_institute_id
            WHERE t.sub_institute_id = '" . $sub_institute_id . "' AND t.standard_id = '" . $standard_id . "'
            AND t.division_id = '" . $division_id . "' AND t.subject_id = '" . $subject_id . "' AND t.syear = '" . $syear . "'
            ORDER BY first_name asc
        ");
        $teacherData = json_decode(json_encode($teacherData), true);

        return $teacherData;
    }

    public function ajax_Timetable(Request $request)
    {
        $from_date = $request->input("from_date");
        $to_date = $request->input("to_date");
        $division_id = $request->input("division_id");
        $standard_id = $request->input("standard_id");
        $subject_id = $request->input("subject_id");
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        //START Get weekday and date between from-date & to-date
        $days_arr = $this->getcountdays($from_date, $to_date);
        //END Get weekday and date between from-date & to-date

        //START Get Timetable data
        $timetableData = timetableModel::select('*')
            ->join('period as p', 'p.id', 'timetable.period_id')
            ->where(
                [
                    'timetable.sub_institute_id' => $sub_institute_id,
                    'timetable.standard_id' => $standard_id,
                    'timetable.division_id' => $division_id,
                    'timetable.subject_id' => $subject_id,
                    'timetable.teacher_id' => $teacher_id,
                    'timetable.syear' => $syear,
                ]
            )
            ->get()->toArray();

        $period = array();
        if (count($timetableData) > 0) {
            foreach ($timetableData as $key => $tdata) {
                $period[$tdata['week_day']][] = $tdata['title'];
            }
        }
        //END Get Timetable data

        //START Get Already lesson planning data
        $lessonplanData = lessonplanningModel::select('*')
            ->where(
                [
                    'sub_institute_id' => $sub_institute_id,
                    'standard_id' => $standard_id,
                    'division_id' => $division_id,
                    'subject_id' => $subject_id,
                    'teacher_id' => $teacher_id,
                    'syear' => $syear,
                ]
            )
            ->groupBy('school_date', 'standard_id', 'division_id', 'subject_id')
            ->get()
            ->toArray();

        $lpData = array();
        if (count($lessonplanData) > 0) {
            foreach ($lessonplanData as $lkey => $lval) {
                $lpData[] = $lval['school_date'];
            }
        }
        //END Get Already lesson planning data

        $from_date1 = $from_date;
        $days = array('1' => 'M', '2' => 'T', '3' => 'W', '4' => 'H', '5' => 'F', '6' => 'S');
        $final_timetable_data = array();
        while (strtotime($from_date1) <= strtotime($to_date)) {
            $week_no = date("N", strtotime($from_date1));
            if ($week_no != 7) {
                $week_day = $days[$week_no];
                if (array_key_exists($week_day, $period)) {
                    foreach ($days_arr[$week_day] as $dkey => $dval) {
                        foreach ($period[$week_day] as $wkey => $wval) {
                            if (!in_array($dval, $lpData)) //If lesson planning exist that dont add that date again
                            {
                                $final_timetable_data[$dval . '####' . $wval] = $dval . ' / ' . $wval;
                            }
                        }
                    }
                }
            }
            $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
        }

        return $final_timetable_data;
    }

    public function getcountdays($from_date, $to_date)
    {
        //5 for count Friday, 6 for Saturday , 7 for Sunday
        $days = array('M' => '1', 'T' => '2', 'W' => '3', 'H' => '4', 'F' => '5', 'S' => '6');
        foreach ($days as $key => $day) {
            $i = 0;
            $from_date1 = $from_date;
            while (strtotime($from_date1) <= strtotime($to_date)) {
                if (date("N", strtotime($from_date1)) == $day) {
                    $i++;
                    $counter[$key][] = $from_date1;
                }
                $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
            }
        }
        return $counter;
    }
}
