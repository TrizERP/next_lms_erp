<?php

namespace App\Http\Controllers\lms;

use App\Events\Lms\ExamSubmitted;
use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questionpaperModel;
use App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use function App\Helpers\is_mobile;

class onlineExamController extends Controller
{

    public function index(Request $request)
    {

        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['answer_arr'] = $data['answer_arr'];
        $res['questionpaper_data'] = $data['questionpaper_data'];
        $res['question_arr'] = $data['question_arr'];

        return is_mobile($type, 'lms/online_exam', $res, "view");
    }

    public function getData($request)
    {

        if (Session::has('session_quiz')) {
            //Nothing to do     
        } else {
            Session::put('session_quiz', date('Y-m-d H:i:s'));
            Session::save();
        }

        $type = $request->input('type');
        // Request-with-session fallback so token/API clients (no Laravel session)
        // can load a paper to attempt.
        $sub_institute_id = $request->get('sub_institute_id') ?: $request->session()->get('sub_institute_id');
        $questionpaper_id = $request->get('questionpaper_id');
        $data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

        //Get all questions subject wise        
        $question_ids = explode(",", $data['questionpaper_data']['question_ids']);
        $data['question_arr'] = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();
        $answer = [];
        foreach ($data['question_arr'] as $key => $val) {
            $answer_arr = answermasterModel::where([
                "question_id"      => $val['id'],
                "sub_institute_id" => $sub_institute_id,
            ])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }
        }
        $data['answer_arr'] = $answer;

        return $data;
    }


    public function create(Request $request)
    {
    }

    /**
     * Feed a wrong MCQ answer into the misconception intelligence engine so the
     * standard exam path builds the same misconception signal that previously
     * only fired for H5P interactive content. Never allowed to break exam
     * submission — analysis failures are logged and swallowed.
     */
    private function recordMisconceptionOnWrongAnswer($studentId, $conceptId, $selectedAnswerId): void
    {
        if (!$studentId || !$conceptId) {
            return;
        }

        try {
            // This submit path doesn't track per-question timing, so we pass a
            // neutral mid-range value rather than 0 — 0 would misclassify every
            // wrong answer as an "attention slip" in the engine's clustering.
            app(MisconceptionIntelligenceEngine::class)->analyze((int) $studentId, (int) $conceptId, [
                'selected_option' => $selectedAnswerId,
                'time_seconds'    => 15,
                'hint_used'       => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Misconception analysis failed on exam submit: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        if ($request->session()->get('is_student') && ! $request->attributes->get('proctoring_authorized')) {
            return response()->json([
                'success' => false,
                'message' => 'Submit this examination through its proctoring session.',
            ], 409);
        }

        //Clear session for timer
        Session::forget('session_quiz');

        // Request-with-session fallback so token/API clients can submit.
        $sub_institute_id = $request->get('sub_institute_id') ?: $request->session()->get('sub_institute_id');
        $user_id = $request->get('user_id') ?: $request->session()->get('user_id');
        $syear = $request->get('syear') ?: $request->session()->get('syear');

        //$questionpaper_details = $this->get_questionpaper_details($request->get('questionpaper_id'));
        $result = $this->calculateOnlineExamMarks($request, (int) $sub_institute_id);

        //START Insert into lms_online_exam table
        $online_exam = [
            'student_id'        => $user_id,
            'question_paper_id' => $request->get('questionpaper_id'),
            'total_right'       => $result['total_right_ans'],
            'total_wrong'       => $result['total_wrong_ans'],
            'obtain_marks'      => $result['obtain_marks'],
            'start_time'        => $request->get('hid_session_quiz'),
        ];


        lmsOnlineExamModel::insert($online_exam);
        $online_exam_id = DB::getPDO()->lastInsertId();
        //END Insert into lms_online_exam table

        //START Insert into lms_online_exam_answer table
        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');

        // Concept lookup for misconception detection below — one query instead of
        // one per wrong answer.
        $answeredQuestionIds = array_keys($result['selected_answer_ids_by_question']);
        $conceptByQuestionId = empty($answeredQuestionIds)
            ? collect()
            : lmsQuestionMasterModel::whereIn('id', $answeredQuestionIds)->pluck('concept_id', 'id');

        foreach ($result['question_statuses'] as $questionId => $status) {
            if (! in_array($status, ['right', 'wrong'], true)) {
                continue;
            }
            $selectedIds = $result['selected_answer_ids_by_question'][$questionId] ?? [];
            foreach ($selectedIds ?: [null] as $answerId) {
                lmsOnlineExamAnswerModel::insert([
                    'question_paper_id' => $request->get('questionpaper_id'),
                    'online_exam_id' => $online_exam_id,
                    'student_id' => $user_id,
                    'question_id' => $questionId,
                    'answer_id' => $answerId,
                    'ans_status' => $status,
                ]);
            }

            if ($status === 'wrong') {
                $this->recordMisconceptionOnWrongAnswer(
                    $user_id,
                    $conceptByQuestionId->get($questionId),
                    $selectedIds[0] ?? null
                );
            }
        }

        if (is_array($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer_ids) {
                if (! is_string($narrative_answer_ids) || trim($narrative_answer_ids) === '') {
                    continue;
                }
                $narrative = [
                    'question_paper_id' => $request->get('questionpaper_id'),
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $narrative_question_id,
                    'narrative_answer'  => $narrative_answer_ids,
                    'ans_status'        => 'pending_review',
                ];
                lmsOnlineExamAnswerModel::insert($narrative);
            }
        }


        // if(is_array($answer_ids))//Insert MCQ Answers
        // {
        //     foreach($answer_ids as $key => $val)
        //     {
        //         $online_exam_answer['answer_id'] = $key; 
        //         lmsOnlineExamAnswerModel::insert($online_exam_answer);        
        //     }                
        // }
        // else //Insert Narrative Answers
        // { 
        //     $online_exam_answer['narrative_answer'] = $answer_ids; 
        //     lmsOnlineExamAnswerModel::insert($online_exam_answer);        
        // }
        //END Insert into lms_online_exam_answer table

        // Fire the real-time exam-to-evidence pipeline. Dispatched ONCE per
        // submission, not per question — AssessmentEvidenceAdapter re-derives
        // evidence across the student's whole answer history per call, so
        // firing this once here (not inside the per-question loops above,
        // unlike recordMisconceptionOnWrongAnswer) is deliberate. This is the
        // earliest point every required lms_online_exam / lms_online_exam_answer
        // row for the attempt has been committed, i.e. the attempt is complete
        // and valid to analyze. Queued (see GenerateAssessmentEvidenceListener),
        // so it can never delay or break the exam-submission response below.
        if ($user_id && $syear) {
            event(new ExamSubmitted((string) $user_id, (string) $syear, (int) $online_exam_id, (int) $request->get('questionpaper_id')));
        }

        // API/JSON clients get the ids back (no cross-origin redirect a browser
        // SPA can't follow); web keeps the redirect to the result page.
        $type = $request->input('type');
        if ($type == 'API' || $type == 'JSON') {
            return response()->json([
                'status_code'       => 1,
                'message'           => 'Exam submitted successfully',
                'questionpaper_id'  => $request->get('questionpaper_id'),
                'online_exam_id'    => $online_exam_id,
                'total_right'       => $result['total_right_ans'],
                'total_wrong'       => $result['total_wrong_ans'],
                'obtain_marks'      => $result['obtain_marks'],
            ]);
        }

        //return is_mobile($type,'lms/online_exam_result',$res,"view");
        return redirect()->route('online_exam.show',[$request->get('questionpaper_id'),"online_exam_id"=> $online_exam_id]);
    }

    private function calculateOnlineExamMarks(Request $request, int $subInstituteId): array
    {
        $paper = DB::table('question_paper')
            ->where('id', $request->input('questionpaper_id'))
            ->where('sub_institute_id', $subInstituteId)
            ->first(['question_ids']);
        if (! $paper) {
            throw new \RuntimeException('The examination paper could not be found.');
        }

        $questionIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $paper->question_ids)
        )));
        $questions = DB::table('lms_question_master')
            ->whereIn('id', $questionIds)
            ->get(['id', 'question_type_id', 'points'])
            ->keyBy('id');
        $questionIds = $questions->keys()->map(fn ($id) => (int) $id)->all();

        $optionsByQuestion = DB::table('answer_master')
            ->whereIn('question_id', $questionIds)
            ->where('sub_institute_id', $subInstituteId)
            ->get(['id', 'question_id', 'correct_answer'])
            ->groupBy('question_id');
        $correctAnswerIds = $optionsByQuestion->map(
            fn ($options) => $options->where('correct_answer', 1)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all()
        );

        $rawSelections = [];
        foreach (['answer_single', 'answer_multiple'] as $field) {
            $answers = $request->input($field, []);
            if (! is_array($answers)) {
                continue;
            }
            foreach ($answers as $questionId => $values) {
                $values = is_array($values) ? $values : [$values];
                foreach ($values as $value) {
                    if (! is_string($value) && ! is_numeric($value)) {
                        continue;
                    }
                    $answerId = trim(explode('##', (string) $value, 2)[0]);
                    if ($answerId !== '') {
                        $rawSelections[(int) $questionId][] = $answerId;
                    }
                }
            }
        }

        $narrativeAnswers = $request->input('answer_narrative', []);
        $questionStatuses = [];
        $selectedAnswerIdsByQuestion = [];
        $totalRight = 0;
        $totalWrong = 0;
        $totalUnanswered = 0;
        $totalPendingReview = 0;
        $obtainMarks = 0.0;
        $totalMarks = 0.0;

        foreach ($questions as $question) {
            $questionId = (int) $question->id;
            $totalMarks += (float) ($question->points ?? 0);

            if ((int) $question->question_type_id === 2) {
                $narrative = is_array($narrativeAnswers)
                    ? ($narrativeAnswers[$questionId] ?? '')
                    : '';
                if (is_string($narrative) && trim($narrative) !== '') {
                    $questionStatuses[$questionId] = 'pending_review';
                    $totalPendingReview++;
                } else {
                    $questionStatuses[$questionId] = 'unanswered';
                    $totalUnanswered++;
                }
                continue;
            }

            $submitted = array_values(array_unique($rawSelections[$questionId] ?? []));
            if ($submitted === []) {
                $questionStatuses[$questionId] = 'unanswered';
                $totalUnanswered++;
                continue;
            }

            $validIds = ($optionsByQuestion->get($questionId) ?? collect())
                ->whereIn('id', $submitted)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $selectedAnswerIdsByQuestion[$questionId] = $validIds;
            $expected = $correctAnswerIds->get($questionId, []);

            if ($validIds !== [] && count($validIds) === count($submitted) && $validIds === $expected) {
                $questionStatuses[$questionId] = 'right';
                $totalRight++;
                $obtainMarks += (float) ($question->points ?? 0);
            } else {
                $questionStatuses[$questionId] = 'wrong';
                $totalWrong++;
            }
        }

        return [
            'total_right_ans' => $totalRight,
            'total_wrong_ans' => $totalWrong,
            'total_unanswered' => $totalUnanswered,
            'total_pending_review' => $totalPendingReview,
            'obtain_marks' => $obtainMarks,
            'total_marks' => $totalMarks,
            'question_statuses' => $questionStatuses,
            'selected_answer_ids_by_question' => $selectedAnswerIdsByQuestion,
        ];
    }

    public function get_calculate_marks(Request $request)
    {
        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');

        $wrong_single_ans = $right_single_ans = $right_multiple_ans = $wrong_multiple_ans = $right_narrative_ans = $wrong_narrative_ans = 0;
        $right_question_ids_arr = $given_ans_arr = [];

        //START Check for single answer
        if (isset($answer_single)) {
            foreach ($answer_single as $single_question_id => $single_answer_ids) {
                $s_ans = explode("##", $single_answer_ids);

                if ($s_ans[1] == 1) {
                    $right_single_ans++;
                    $right_question_ids_arr[] = $single_question_id;
                } else {
                    if ($s_ans[1] == 0) {
                        $wrong_single_ans++;
                    }
                }
            }
        }
        //END Check for single answer

        //START Check for multiple answer
        if (isset($answer_multiple)) {
            foreach ($answer_multiple as $key => $multiple_answer_ids) {
                $original_ans = DB::table('answer_master')
                    ->selectRaw('GROUP_CONCAT(id) AS right_answers')
                    ->where('question_id', $key)
                    ->where('correct_answer', '=', 1)->get()->toArray();
                $original_ans = explode(",", $original_ans[0]->right_answers);

                foreach ($multiple_answer_ids as $multiple_question_id => $multiple_answer) {
                    $a_ans = explode("##", $multiple_answer);
                    $ans = $a_ans[1];
                    if ($ans == 1) {
                        $given_ans_arr[] = $a_ans[0];
                    }
                }

                $diff = array_diff($original_ans, $given_ans_arr);

                if (count($diff) == 0) {
                    $right_multiple_ans++;
                    $right_question_ids_arr[] = $key;
                } else {
                    $wrong_multiple_ans++;
                }
            }
        }

        //END Check for multiple answer

        //START Check for Narrative answer
        if (isset($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer) {
                if (isset($narrative_answer) && $narrative_answer != "") {
                    $right_narrative_ans++;
                    $right_question_ids_arr[] = $narrative_question_id;
                } else {
                    $wrong_narrative_ans++;
                }
            }
        }
        //END Check for Narrative answer

        $obtain_marks = $this->get_obtain_marks($right_question_ids_arr);

        // echo "right_single_ans->".$right_single_ans."<br>";
        // echo "wrong_single_ans->".$wrong_single_ans."<br><br>";
        // echo "right_multiple_ans->".$right_multiple_ans."<br>";
        // echo "wrong_multiple_ans->".$wrong_multiple_ans."<br><br>";
        // echo "right_narrative_ans->".$right_narrative_ans."<br>";
        // echo "wrong_narrative_ans->".$wrong_narrative_ans."<br><br>";
        // echo '<pre>';
        // print_r($right_question_ids_arr);
        // echo "<br>";
        // echo "obtain_marks->".$obtain_marks."<br>";

        $data['obtain_marks'] = $obtain_marks;
        $data['total_wrong_ans'] = $wrong_single_ans + $wrong_multiple_ans + $wrong_narrative_ans;
        $data['total_right_ans'] = $right_single_ans + $right_multiple_ans + $right_narrative_ans;

        return $data;

    }

    public function get_obtain_marks($arr)
    {
        if (empty($arr)) {
            $obt_marks = 0;
        } else {
            $obtain_marks = DB::table('lms_question_master')
                ->selectRaw('SUM(points) AS obtain_marks')
                ->whereIn('id', $arr)
                ->get()->toArray();

            $obt_marks = $obtain_marks[0]->obtain_marks;
        }

        return $obt_marks;
    }

    public function get_questionpaper_details($questionpaper_id)
    {
        $data = questionpaperModel::select('question_paper.*', 'sub_std_map.display_name as subject_name')
            ->join("sub_std_map", function ($join) {
                $join->on("sub_std_map.subject_id", "=", "question_paper.subject_id")
                    ->on("sub_std_map.standard_id", "=", "question_paper.standard_id");
            })
            ->where('question_paper.id', $questionpaper_id)->get()->toArray();

        return $data[0];
    }

    public function edit(Request $request, $id)
    {
        // dd($request);
        // $type = $request->input('type');
        // $sub_institute_id = $request->session()->get('sub_institute_id');
        // $questionpaper_id = $request->get('questionpaper_id');
        // $data['questionpaper_data'] = questionpaperModel::find($id)->toArray(); 

        // //Get all questions subject wise        
        // $question_ids = explode(",",$data['questionpaper_data']['question_ids']);
        // $data['question_arr'] = lmsQuestionMasterModel::whereIn("id",$question_ids)->get()->toArray(); 

        // foreach($data['question_arr'] as $key => $val)
        // {            
        //     $answer_arr = answermasterModel::where("question_id",$val['id'])->get()->toArray(); 
        //     if(count($answer_arr) > 0)
        //     {
        //         foreach($answer_arr as $anskey => $ansval)
        //         {
        //             $answer[$val['id']][] = $ansval;
        //         }                       
        //     }
        // }

        // $type = $request->input('type');
        // $res['status_code'] = 1;
        // $res['message'] = "SUCCESS";    
        // $res['answer_arr'] = $answer; 
        // $res['questionpaper_data'] = $data['questionpaper_data']; 
        // $res['question_arr'] = $data['question_arr']; 
        // return is_mobile($type,'lms/online_exam_div',$res,"view");  

    }

    public function update(Request $request, $id)
    {

    }

    public function destroy(Request $request, $id)
    {

    }

    public function show(Request $request, $id)
    {
        $questionpaper_id = $id;

        $type = $request->input('type');
        // Request-with-session fallback: pass the student's user_id to view a
        // specific learner's result headlessly (staff reviewing / SPA).
        $sub_institute_id = $request->get('sub_institute_id') ?: $request->session()->get('sub_institute_id');
        $user_id = $request->get('user_id') ?: $request->session()->get('user_id');
        $online_exam_id = $request->get('online_exam_id');
        $data['user_id'] = $online_exam_id;

        $data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

        // A result is only ever the review of an attempt that exists. Without one there is
        // nothing to review, and returning the paper here would hand over every question with
        // its correct options before the student has answered anything. With no attempt id the
        // student's latest attempt on this paper is used.
        $attempt = $online_exam_id
            ? lmsOnlineExamModel::where(['id' => $online_exam_id, 'student_id' => $user_id, 'question_paper_id' => $questionpaper_id])->first()
            : lmsOnlineExamModel::where(['student_id' => $user_id, 'question_paper_id' => $questionpaper_id])->orderByDesc('id')->first();

        if (! $attempt) {
            $noAttempt = [
                'status_code' => 1,
                'message' => 'NO_ATTEMPT',
                'no_attempt' => true,
                'user_id' => $online_exam_id,
                'questionpaper_data' => [
                    'id' => $data['questionpaper_data']['id'],
                    'paper_name' => $data['questionpaper_data']['paper_name'] ?? '',
                    'total_marks' => $data['questionpaper_data']['total_marks'] ?? 0,
                ],
                'question_arr' => [],
                'answer_arr' => [],
                'mapping_arr' => [],
                'online_exam_data' => [],
            ];

            return is_mobile($type, 'lms/online_exam_result', $noAttempt, "view");
        }

        // A student sees the review (answers, correct options) only once every allowed attempt
        // has been used; before that the exam can still be taken again and the answers would
        // give the paper away. Staff reviewing a learner's result are not held back.
        $isStudent = (bool) $request->session()->get('is_student')
            || strtolower(trim((string) $request->session()->get('user_profile_name'))) === 'student';
        $allowed = max(1, (int) ($data['questionpaper_data']['attempt_allowed'] ?? 1));
        $used = lmsOnlineExamModel::where(['student_id' => $user_id, 'question_paper_id' => $questionpaper_id])->count();

        if ($isStudent && $used < $allowed) {
            $locked = [
                'status_code' => 1,
                'message' => 'ATTEMPTS_REMAINING',
                'attempts_remaining' => $allowed - $used,
                'attempts_allowed' => $allowed,
                'attempts_used' => $used,
                'user_id' => $online_exam_id,
                'questionpaper_data' => [
                    'id' => $data['questionpaper_data']['id'],
                    'paper_name' => $data['questionpaper_data']['paper_name'] ?? '',
                    'total_marks' => $data['questionpaper_data']['total_marks'] ?? 0,
                ],
                'question_arr' => [],
                'answer_arr' => [],
                'mapping_arr' => [],
                'online_exam_data' => [],
            ];

            return is_mobile($type, 'lms/online_exam_result', $locked, "view");
        }

        $online_exam_id = $attempt->id;
        $data['user_id'] = $online_exam_id;

        //Get all questions subject wise        
        $question_ids = explode(",", $data['questionpaper_data']['question_ids']);
        $data['question_arr'] = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();

        $lmsmapping = array();
        $answer = array();
        foreach ($data['question_arr'] as $key => $val) {
            $answer_arr = answermasterModel::where([
                "question_id"      => $val['id'],
                "sub_institute_id" => $sub_institute_id,
            ])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }

            $lmsquestionmapping_arr = lmsQuestionMappingModel::select('lms_question_mapping.questionmaster_id',
                't.name as type_name', 't.id as type_id'
                , 't1.name as value_name', 't1.id as value_id')
                ->join('lms_mapping_type as t', 't.id', 'lms_question_mapping.mapping_type_id')
                ->join('lms_mapping_type as t1', 't1.id', 'lms_question_mapping.mapping_value_id')
                ->where(["questionmaster_id" => $val['id']])
                ->get()->toArray();
            if (count($lmsquestionmapping_arr) > 0) {
                foreach ($lmsquestionmapping_arr as $lkey => $lval) {
                    $lmsmapping[$val['id']][$lval['type_name']] = $lval['value_name'];
                }
            }
        }

        $data['mapping_arr'] = $lmsmapping;
        $data['answer_arr'] = $answer;
        
        $data['online_exam_data'] = lmsOnlineExamModel::where([
            'id'=>$online_exam_id,'student_id'=>$user_id
        ])->get()->toArray();
        $onlineExamSummary = $data['online_exam_data'][0] ?? [];
        $attemptAnswers = DB::table('lms_online_exam_answer')
            ->where('online_exam_id', $online_exam_id)
            ->where('student_id', $user_id)
            ->get(['question_id', 'answer_id', 'narrative_answer'])
            ->groupBy('question_id');

        $totalRight = 0;
        $totalWrong = 0;
        $totalUnanswered = 0;
        $totalPendingReview = 0;
        $totalMarks = 0.0;
        $obtainMarks = 0.0;
        foreach ($data['question_arr'] as $question) {
            $questionId = (int) $question['id'];
            $totalMarks += (float) ($question['points'] ?? 0);
            $answersForQuestion = $attemptAnswers->get($questionId, collect());
            $narrativeAnswer = trim((string) ($answersForQuestion->first()->narrative_answer ?? ''));
            $givenAnswerIds = $answersForQuestion->pluck('answer_id')
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $actualAnswerIds = collect($answer[$questionId] ?? [])
                ->filter(fn ($option) => (int) ($option['correct_answer'] ?? 0) === 1)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all();

            if ((int) ($question['question_type_id'] ?? 0) === 2) {
                if ($narrativeAnswer !== '') {
                    $rightWrong = 'pending_review';
                    $totalPendingReview++;
                } else {
                    $rightWrong = 'unanswered';
                    $totalUnanswered++;
                }
                $givenAnswer = $narrativeAnswer;
            } elseif ($givenAnswerIds === []) {
                $rightWrong = 'unanswered';
                $givenAnswer = '';
                $totalUnanswered++;
            } elseif ($givenAnswerIds !== [] && $givenAnswerIds === $actualAnswerIds) {
                $rightWrong = 'right';
                $givenAnswer = implode(',', $givenAnswerIds);
                $totalRight++;
                $obtainMarks += (float) ($question['points'] ?? 0);
            } else {
                $rightWrong = 'wrong';
                $givenAnswer = implode(',', $givenAnswerIds);
                $totalWrong++;
            }

            $data['online_answer_data'][$questionId] = [
                'RIGHT_WRONG' => $rightWrong,
                'ACTUAL_ANSWER' => implode(',', $actualAnswerIds),
                'GIVEN_ANSWER' => $givenAnswer,
            ];
        }

        $data['online_exam_data'] = array_merge($onlineExamSummary, [
            'total_right' => $totalRight,
            'total_wrong' => $totalWrong,
            'total_unanswered' => $totalUnanswered,
            'total_pending_review' => $totalPendingReview,
            'obtain_marks' => $obtainMarks,
        ]);
        if ($totalMarks > 0) {
            $data['questionpaper_data']['total_marks'] = $totalMarks;
        }

        $type = $request->input('type');
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        // echo "<pre>";print_r($data);exit;
        return is_mobile($type, 'lms/online_exam_result', $data, "view");
    }

    public function online_exam_attempt(Request $request)
    {
        $questionpaper_id = $request->get("questionpaper_id");
        $student_id = $request->get("student_id");

        $data['questionpaper_data'] = $this->get_questionpaper_details($questionpaper_id);

        $data['attempted_data'] = lmsOnlineExamModel::where([
            'student_id'        => $student_id,
            'question_paper_id' => $questionpaper_id,
        ])->orderby('start_time')->get()->toArray();
        $progressbar_data = array();
        foreach ($data['attempted_data'] as $key => $val) {
            $paperQuestionIds = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) ($data['questionpaper_data']['question_ids'] ?? ''))
            )));
            $pdata = DB::table('lms_question_mapping as l')
                ->join('lms_mapping_type as lt', 'lt.id', '=', 'l.mapping_value_id')
                ->join('lms_mapping_type as plt', 'plt.id', '=', 'lt.parent_id')
                ->leftJoin('lms_question_master as q', 'q.id', '=', 'l.questionmaster_id')
                ->leftJoin('lms_online_exam_answer as e', function ($join) use ($val) {
                    $join->on('e.question_id', '=', 'l.questionmaster_id')
                        ->where('e.question_paper_id', '=', $val['question_paper_id'])
                        ->where('e.student_id', '=', $val['student_id'])
                        ->where('e.online_exam_id', '=', $val['id']);
                })
                ->whereIn('l.questionmaster_id', $paperQuestionIds)
                ->select([
                    'lt.parent_id',
                    'plt.name as parent_name',
                    'lt.id',
                    'lt.name',
                ])
                ->selectRaw('GROUP_CONCAT(DISTINCT l.questionmaster_id) as ques_list')
                ->selectRaw('COUNT(DISTINCT l.questionmaster_id) as total_question')
                ->selectRaw(
                    "COUNT(DISTINCT CASE WHEN q.question_type_id <> 2 AND e.ans_status = 'right' "
                    . 'THEN l.questionmaster_id END) as right_answer'
                )
                ->selectRaw("'100' as total_percentage")
                ->selectRaw(
                    'ROUND((COUNT(DISTINCT CASE WHEN q.question_type_id <> 2 AND e.ans_status = '
                    . "'right' THEN l.questionmaster_id END) * 100) / "
                    . 'NULLIF(COUNT(DISTINCT l.questionmaster_id), 0), 2) as obtained_percentage'
                )
                ->groupBy('lt.parent_id', 'plt.name', 'lt.id', 'lt.name')
                ->orderBy('lt.id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
            $progressbar_data[$val['id']] = json_decode(json_encode($pdata), true);
        }
        $data['progressbar_data'] = $progressbar_data;
        //dd($progressbar_data);

        $final_progressbar_data = array();
        $parent_mapping_arr = array();

        foreach ($progressbar_data as $pkey => $pval) {
            $parent_mapping_arr[$pkey] = array();
            foreach ($pval as $okey => $oval) {
                if (! array_key_exists($oval['parent_name'], $parent_mapping_arr[$pkey])) {
                    $parent_mapping_arr[$pkey][$oval['parent_name']] = $oval['total_question'];
                } else {
                    $temp = $parent_mapping_arr[$pkey][$oval['parent_name']];
                    $temp = $temp + $oval['total_question'];
                    $parent_mapping_arr[$pkey][$oval['parent_name']] = $temp;
                }
                if (! array_key_exists($oval['parent_name'], $final_progressbar_data)) {
                    $final_progressbar_data[$pkey][$oval['parent_name']][] = $oval;
                }
            }
        }
        $data['parent_mapping_arr'] = $parent_mapping_arr;
        $data['final_progressbar_data'] = $final_progressbar_data;

        //dd($data);

        $type = $request->input('type');

        //return is_mobile($type,'lms/online_attempted_result',$data,"view");
        return is_mobile($type, 'lms/online_attempted_result', $data, "view");
    }

    public function ajax_getQuestionList(Request $request)
    {
        $ques_list = explode(",", $request->input("ques_list"));
        $online_exam_id = $request->input("online_exam_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return lmsQuestionMasterModel::select('lms_question_master.id', 'question_title', 'a.ans_status')
            ->join('lms_online_exam_answer as a', 'a.question_id', 'lms_question_master.id')
            ->where(['sub_institute_id' => $sub_institute_id, 'a.online_exam_id' => $online_exam_id])
            ->whereIn('lms_question_master.id', $ques_list)
            ->get()->toArray();
    }
}
