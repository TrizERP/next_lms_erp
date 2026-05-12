<?php
 
namespace App\Http\Controllers\lms\pal;
 
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use App\Http\Controllers\AJAXController;
use App\Http\Controllers\lms\onlineExamController;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\lmsOnlineExamAnswerStudent;
// // use App\Models\lms\lmsOnlineExamStudent;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\questionpaperModel;
use App\Models\lms\lmsmappingtypeModel;
use DB;
use App\Models\lms\chapterModel;
use App\Models\lms\contentModel;
use App\Models\lms\contentmappingtypeModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use function App\Helpers\neo4jCreateNode;
use function App\Helpers\neo4jCreateRelationship;

class palController extends Controller
{
    //
    private function suggestedContentQuestionIdColumn()
    {
        return 'type_id';
    }

    private function misconceptionQuestionIdColumn()
    {
        return Schema::hasColumn('suggested_content', 'type_id') ? 'type_id' : 'content_id';
    }

    private function misconceptionContentType()
    {
        return 'misconception';
    }

    private function misconceptionContentTypes()
    {
        return [$this->misconceptionContentType(), 'misconcept', 'misconception'];
    }

    private function suggestedContentTextColumn()
    {
        foreach (['generated_content', 'content', 'suggested_content', 'description'] as $column) {
            if (Schema::hasColumn('suggested_content', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function onlyExistingSuggestedContentColumns(array $data)
    {
        foreach (array_keys($data) as $column) {
            if (!Schema::hasColumn('suggested_content', $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    private function suggestedContentTypeAllows($type)
    {
        if (!Schema::hasColumn('suggested_content', 'type') || DB::getDriverName() !== 'mysql') {
            return true;
        }

        $typeColumn = collect(DB::select("SHOW COLUMNS FROM suggested_content WHERE Field = 'type'"))->first();
        $typeDefinition = $typeColumn->Type ?? '';

        return stripos($typeDefinition, 'enum') === false || stripos($typeDefinition, "'" . $type . "'") !== false;
    }

    private function buildMisconceptionSummary($question, $chapterName)
    {
        $questionText = strip_tags($question->question ?? '');
        $correctAnswer = strip_tags($question->correct_answer ?? '');
        $wrongCount = (int) ($question->wrong_count ?? 0);

        return '<p><strong>Chapter:</strong> ' . e($chapterName) . '</p>'
            . '<p><strong>Question:</strong> ' . e($questionText) . '</p>'
            . '<p><strong>Correct Answer:</strong> ' . e($correctAnswer) . '</p>'
            . '<p>This question was answered incorrectly ' . $wrongCount . ' time' . ($wrongCount === 1 ? '' : 's') . '.</p>';
    }

    private function buildMisconceptionAIPrompt($question, $chapterName, $subjectName)
    {
        $questionText = strip_tags($question->question ?? '');
        $correctAnswer = strip_tags($question->correct_answer ?? '');
        $wrongCount = (int) ($question->wrong_count ?? 0);

        return "Create remedial learning content for a student's misconception.\n"
            . "Subject: {$subjectName}\n"
            . "Chapter: {$chapterName}\n"
            . "Question: {$questionText}\n"
            . "Correct answer: {$correctAnswer}\n"
            . "Wrong attempts: {$wrongCount}\n\n"
            . "Write student-friendly content that explains the misconception, the correct concept, a worked example, and 3 short practice questions with answers. "
            . "Return clean HTML only using headings, paragraphs, ordered/unordered lists, and bold text. Do not include apologies or conversational filler.";
    }

    private function generateMisconceptionAIContent($question, $chapterName, $subjectName)
    {
        try {
            $openAIService = new OpenAIService();
            $generatedContent = trim((string) $openAIService->generateContent(
                $this->buildMisconceptionAIPrompt($question, $chapterName, $subjectName)
            ));

            if (!empty($generatedContent)) {
                return $generatedContent;
            }
        } catch (\Throwable $e) {
            \Log::error('Misconception AI provider failed: ' . $e->getMessage(), [
                'question_id' => $question->question_id ?? null,
                'chapter' => $chapterName,
            ]);
        }

        return $this->buildMisconceptionSummary($question, $chapterName);
    }

    private function saveMisconceptionContentHtml($htmlContent, $questionId)
    {
        $directory = public_path('lms_content_file/misconception');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = 'misconception_' . $questionId . '_' . time() . '.html';
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Misconception Content</title>'
            . '<style>body{font-family:Arial,sans-serif;line-height:1.6;padding:24px;color:#1f2937;max-width:960px;margin:auto;}'
            . 'h1,h2,h3{color:#111827;} .content{background:#fff;}</style></head><body><div class="content">'
            . $htmlContent
            . '</div></body></html>';

        file_put_contents($directory . DIRECTORY_SEPARATOR . $fileName, $html);

        return url('lms_content_file/misconception/' . $fileName);
    }

    private function syncMisconceptionContentMappings($contentId, $question)
    {
        if (empty($contentId)) {
            return;
        }

        DB::table('content_mapping_type')->where('content_id', $contentId)->delete();

        if (empty($question->mapping)) {
            return;
        }

        foreach ($question->mapping as $mapping) {
            $mappingTypeId = is_array($mapping) ? ($mapping['type_id'] ?? null) : ($mapping->type_id ?? null);
            $mappingValueId = is_array($mapping) ? ($mapping['value_id'] ?? null) : ($mapping->value_id ?? null);

            if (empty($mappingTypeId) || empty($mappingValueId)) {
                continue;
            }

            contentmappingtypeModel::insert([
                'content_id' => $contentId,
                'mapping_type_id' => $mappingTypeId,
                'mapping_value_id' => $mappingValueId,
            ]);
        }
    }

    private function createMisconceptionContentMaster($question, $chapterId, $chapterName, $subjectName, $studentLevel, $studentId, $subInstituteId, $syear)
    {
        $generatedContent = $this->generateMisconceptionAIContent($question, $chapterName, $subjectName);
        $contentUrl = $this->saveMisconceptionContentHtml($generatedContent, $question->question_id);
        $title = 'Misconception Content';

        $existingContent = contentModel::where('title', $title)
            ->where('chapter_id', $chapterId)
            ->where('topic_id', $question->question_id)
            ->where('sub_institute_id', $subInstituteId)
            ->where('created_by', $studentId)
            ->first();

        $content = [
            'grade_id' => 0,
            'standard_id' => $question->standard_id,
            'subject_id' => $question->subject_id,
            'chapter_id' => $chapterId,
            'topic_id' => $question->question_id,
            'title' => $title,
            'description' => strip_tags($question->question ?? ''),
            'file_folder' => '/lms_content_file',
            'filename' => $contentUrl,
            'url' => $contentUrl,
            'file_type' => 'link',
            'file_size' => 0,
            'show_hide' => 1,
            'sort_order' => 0,
            'meta_tags' => 'misconception',
            'content_category' => 'Misconception Content',
            'created_by' => $studentId,
            'sub_institute_id' => $subInstituteId,
            'basic_advance' => 0,
            'syear' => $syear,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('content_master', 'user_profile_name')) {
            $content['user_profile_name'] = 'student';  
        }

        if ($existingContent) {
            $existingContent->update($content);
            $this->syncMisconceptionContentMappings($existingContent->id, $question);
            return $existingContent->id;
        }

        contentModel::insert($content);
        $contentId = DB::getPDO()->lastInsertId();
        $this->syncMisconceptionContentMappings($contentId, $question);

        return $contentId;
    }

    public function index(Request $request){
        $type=$request->type;
        $res['message'] = "no data";
        if($type=='API'){
            $student_id =$request->user_id;
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
               
        }else{
            $student_id =session()->get('user_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $syear=session()->get('syear');        
        }
        
        $studentData = getStudents([$student_id],$sub_institute_id, $syear);
        $ajaxController = new AJAXController;
        $newData=$getSubjectList=$getchapterList=[];

        if(!empty($studentData)){
            $newData = $studentData[$student_id];
            $currentStandard = $studentData[$student_id]['standard_id'];
			$request->merge(['standard_id' => $currentStandard]);
            $getSubjectList=$ajaxController->getSubjectList($request)->original;
            // get chapters list 
            if(!empty($getSubjectList)){
                foreach ($getSubjectList as $subject_id => $subject_name) {
                    # code...
                    $request->merge(['standard_id' => $currentStandard,'subject_id'=>$subject_id]);
                    $getchapterList[$subject_id]=$ajaxController->getChapterList($request)->original;   
                }
            }
        }
        $res['studentDetails'] = $newData;
        $res['subjectList'] =$getSubjectList;
        $res['chapterList'] =$getchapterList;  
        // $res['attemptExams'] = questionpaperModel::join('lms_online_exam_student as loes','loes.question_paper_id','=','question_paper.id')
         $res['attemptExams'] = questionpaperModel::join('lms_online_exam as loes','loes.question_paper_id','=','question_paper.id')
        ->where('question_paper.created_by',$student_id)->where(['question_paper.sub_institute_id'=>$sub_institute_id])->where('question_paper.exam_type','PAL')->get()->toArray();
        $perChapterQuiz = [];
        foreach($res['attemptExams'] as $exam){
            $i=0;
            if(!isset($perChapterQuiz[$exam['paper_desc']])){
                $perChapterQuiz[$exam['paper_desc']]=0;
            }
            $perChapterQuiz[$exam['paper_desc']]++;
            $i++;
        }
        $res['perChapterQuiz'] = $perChapterQuiz;
        // echo "<pre>";print_r($res['perChapterQuiz']);exit;
        return is_mobile($type, 'lms/pal/show', $res, "view");        
    }
    
    /**
     * Get Student Result API - Returns student level based on their exam performance
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

  private function getMisconceptionQuestions($student_id, $chapter_id)
{
    $data = DB::select("
        SELECT 
            q.id as question_id,
            q.question_title as question,
            q.standard_id,
            q.subject_id,
            q.chapter_id,
            GROUP_CONCAT(DISTINCT am.answer) as correct_answer,
            COUNT(*) as wrong_count
        FROM lms_online_exam_answer_student lea
        JOIN lms_question_master q ON q.id = lea.question_id
        LEFT JOIN answer_master am 
            ON am.question_id = q.id AND am.correct_answer = 1
        JOIN question_paper qp ON qp.id = lea.question_paper_id
        WHERE lea.student_id = ?
        AND lea.ans_status = 'wrong'
        AND qp.paper_desc = ?
        GROUP BY q.id, q.question_title, q.standard_id, q.subject_id, q.chapter_id
    ", [$student_id, $chapter_id]);

    // Get mapping type and mapping value for each question only
    foreach ($data as &$question) {
        $mappingData = lmsQuestionMappingModel::select(
            'lms_question_mapping.questionmaster_id',
            't.name as type_name', 
            't.id as type_id',
            't1.name as value_name', 
            't1.id as value_id'
        )
        ->join('lms_mapping_type as t', 't.id', '=', 'lms_question_mapping.mapping_type_id')
        ->join('lms_mapping_type as t1', 't1.id', '=', 'lms_question_mapping.mapping_value_id')
        ->where(['questionmaster_id' => $question->question_id])
        ->get()
        ->toArray();
        
        $question->mapping = $mappingData;
    }

    return $data;
}

  public function misconception(Request $request)
{
    $chapter_id = $request->chapter_id;
    $student_id = session()->get('user_id');
    $sub_institute_id = session()->get('sub_institute_id');
    $syear = session()->get('syear');

    $data = $this->getMisconceptionQuestions($student_id, $chapter_id);
    $questionIds = array_map(function ($question) {
        return $question->question_id;
    }, $data);
    $generatedQuestionIds = [];
    $contentIdColumn = $this->suggestedContentQuestionIdColumn();

    if (!empty($questionIds)) {
        $generatedQuestionIds = DB::table('suggested_content')
            ->join('content_master as cm', 'cm.id', '=', 'suggested_content.' . $contentIdColumn)
            ->where('suggested_content.type', $this->misconceptionContentType())
            ->where('student_id', $student_id)
            ->where('suggested_content.sub_institute_id', $sub_institute_id)
            ->where('suggested_content.syear', $syear)
            ->whereIn('cm.title', array_map(function ($questionId) {
                return 'Misconception Content';
            }, $questionIds))
            ->pluck('cm.title')
            ->map(function ($title) {
                return (int) str_replace('Misconception Content ', '', $title);
            })
            ->toArray();
    }

    foreach ($data as &$question) {
        $question->content_generated = in_array((int) $question->question_id, $generatedQuestionIds);
    }

    return response()->json([
        'status' => 1,
        'data' => $data
    ]);
}

public function generateMisconceptionContent(Request $request)
{
    $chapter_id = $request->input('chapter_id');
    $student_id = session()->get('user_id');
    $sub_institute_id = session()->get('sub_institute_id');
    $syear = session()->get('syear');

    if (empty($chapter_id)) {
        return response()->json(['status' => 0, 'message' => 'Chapter is required']);
    }

    $questions = $this->getMisconceptionQuestions($student_id, $chapter_id);

    if (empty($questions)) {
        return response()->json(['status' => 0, 'message' => 'No wrong questions found for this chapter']);
    }

    if (!$this->suggestedContentTypeAllows($this->misconceptionContentType())) {
        return response()->json([
            'status' => 0,
            'message' => "Run php artisan migrate first. suggested_content.type enum does not allow '" . $this->misconceptionContentType() . "'.",
        ]);
    }

    $contentIdColumn = $this->suggestedContentQuestionIdColumn();
    $studentLevel = $this->getCurrentLevelForChapter($student_id, $sub_institute_id, $chapter_id);

    $generatedCount = 0;
    $skippedCount = 0;
    $failed = [];

    $chapterNameColumn = Schema::hasColumn('chapter_master', 'chapter_name') 
        ? 'chapter_name' 
        : (Schema::hasColumn('chapter_master', 'name') ? 'name' : 'id');
    
    $chapterName = DB::table('chapter_master')
        ->where('id', $chapter_id)
        ->value($chapterNameColumn) ?? '';

    $subjectName = DB::table('sub_std_map')
        ->where('standard_id', $questions[0]->standard_id ?? null)
        ->where('subject_id', $questions[0]->subject_id ?? null)
        ->where('sub_institute_id', $sub_institute_id)
        ->value('display_name') ?? '';

    foreach ($questions as $question) {
        try {
            $contentId = $this->createMisconceptionContentMaster(
                $question,
                $chapter_id,
                $chapterName,
                $subjectName,
                $studentLevel,
                $student_id,
                $sub_institute_id,
                $syear
            );

            $updatedRows = DB::table('suggested_content')
                ->whereIn('type', $this->misconceptionContentTypes())
                ->where($contentIdColumn, $question->question_id)
                ->where('student_id', $student_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->update($this->onlyExistingSuggestedContentColumns([
                    $contentIdColumn => $contentId,
                    'type' => $this->misconceptionContentType(),
                    'student_level' => $studentLevel,
                    'content_visited' => 0,
                    'standard_id' => $question->standard_id,
                    'subject_id' => $question->subject_id,
                    'chapter_id' => $chapter_id,
                    'updated_at' => now(),
                ]));

            if ($updatedRows > 0) {
                $generatedCount++;
                continue;
            }

            $exists = DB::table('suggested_content')
                ->where('type', $this->misconceptionContentType())
                ->where($contentIdColumn, $contentId)
                ->where('student_id', $student_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            $insertData = [
                'type' => $this->misconceptionContentType(),
                $contentIdColumn => $contentId,
                'student_level' => $studentLevel,
                'content_visited' => 0,
                'standard_id' => $question->standard_id,
                'subject_id' => $question->subject_id,
                'chapter_id' => $chapter_id,
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
                'student_id' => $student_id,
                'created_by' => $student_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $insertData = $this->onlyExistingSuggestedContentColumns($insertData);

            \Log::info('Inserting into suggested_content', [
                'question_id' => $question->question_id,
                'content_id_column' => $contentIdColumn,
                'content_id' => $contentId,
            ]);

            DB::table('suggested_content')->insert($insertData);
            $generatedCount++;
            
        } catch (\Throwable $e) {
            \Log::error('Misconception AI content generation failed: ' . $e->getMessage(), [
                'question_id' => $question->question_id,
                'chapter_id' => $chapter_id,
                'error_type' => get_class($e)
            ]);
            $failed[] = $question->question_id;
        }
    }

    $status = empty($failed) ? 1 : (($generatedCount > 0) ? 1 : 0);
    $message = "Generated: $generatedCount, Skipped: $skippedCount";
    
    if (!empty($failed)) {
        $message .= ", Failed: " . count($failed) . " questions";
    } else {
        $message .= " - All successful!";
    }

    return response()->json([
        'status' => $status,
        'message' => $message,
        'generated' => $generatedCount,
        'skipped' => $skippedCount,
        'failed' => $failed,
    ]);
}
    public function getStudentResult(Request $request)
    {
        $type = $request->input('type');
        
        if ($type == 'API') {
            $student_id = $request->user_id;
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        } else {
            $student_id = session()->get('user_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        // Get the latest PAL exam result for the student
        $latestExam = DB::table('lms_online_exam_student as loes')
            ->join('question_paper as qp', 'qp.id', '=', 'loes.question_paper_id')
            ->where('loes.student_id', $student_id)
            ->where('qp.exam_type', 'PAL')
            ->where('qp.sub_institute_id', $sub_institute_id)
            ->orderBy('loes.created_at', 'DESC')
            ->first();

        $studentLevel = null;
        $studentLevelId = null;

        if ($latestExam) {
            // Calculate performance percentage
            $totalQuestions = $latestExam->total_right + $latestExam->total_wrong;
            $percentage = $totalQuestions > 0 ? ($latestExam->total_right / $totalQuestions) * 100 : 0;

            // Determine student level based on performance
            // Easy: < 40%, Medium: 40-70%, Hard: > 70%
            if ($percentage < 40) {
                $studentLevel = 'easy';
            } elseif ($percentage < 70) {
                $studentLevel = 'medium';
            } else {
                $studentLevel = 'hard';
            }

            // Get the level id from lms_mapping_type table
            $levelMapping = lmsmappingtypeModel::where('name', $studentLevel)
                // ->where('sub_institute_id', $sub_institute_id)
                ->first();

            if ($levelMapping && $levelMapping->id) {
                $studentLevelId = $levelMapping->id;
            } else {
                // If level not found, get default "easy" level id
                $easyLevel = lmsmappingtypeModel::where('name', 'easy')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->first();
                $studentLevelId = $easyLevel ? $easyLevel->id : null;
            }
        } else {
            // No previous exam, default to easy level
            $studentLevel = 'easy';
            $easyLevel = lmsmappingtypeModel::where('name', 'easy')
                // ->where('sub_institute_id', $sub_institute_id)
                ->first();
            $studentLevelId = $easyLevel ? $easyLevel->id : null;
        }

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'student_level' => $studentLevel,
            'student_level_id' => $studentLevelId,
            'latest_exam' => $latestExam
        ]);
    }

    /**
     * Get the next level based on current student level
     * 
     * @param string $currentLevel
     * @return string
     */
    private function getNextLevel($currentLevel)
    {
        $levelMap = [
            'easy' => 'medium',
            'medium' => 'hard',
            'hard' => 'hard'
        ];

        return $levelMap[$currentLevel] ?? 'medium';
    }

    private function formatLevelLabel($level)
    {
        return ucfirst(strtolower((string) $level));
    }

    private function deduplicateContentData(array $contentData)
    {
        $seenContentIds = [];
        $deduplicated = [];

        foreach ($contentData as $chapterId => $categories) {
            foreach ($categories as $category => $contents) {
                foreach ($contents as $content) {
                    $contentType = is_array($content)
                        ? ($content['content_type'] ?? $content['type'] ?? $category)
                        : ($content->content_type ?? $content->type ?? $category);

                    if ($contentType === 'misconception_content') {
                        $contentId = is_array($content)
                            ? ($content['question_id'] ?? $content['generated_content'] ?? $content['id'] ?? null)
                            : ($content->question_id ?? $content->generated_content ?? $content->id ?? null);
                    } else {
                        $contentId = is_array($content)
                            ? ($content['id'] ?? $content['content_id'] ?? $content['filename'] ?? $content['title'] ?? $content['content_title'] ?? null)
                            : ($content->id ?? $content->content_id ?? $content->filename ?? $content->title ?? $content->content_title ?? null);
                    }

                    $dedupeKey = $contentType . ':' . $contentId;

                    if (empty($contentId) || isset($seenContentIds[$dedupeKey])) {
                        continue;
                    }

                    $seenContentIds[$dedupeKey] = true;
                    $deduplicated[$chapterId][$category][] = $content;
                }
            }
        }

        return $deduplicated;
    }

    /**
     * Get level ID from lms_mapping_type by level name
     * 
     * @param string $levelName
     * @param int $subInstituteId
     * @return int|null
     */
  private function getLevelId($levelName, $subInstituteId)
    {
        $level = lmsmappingtypeModel::where('name', $levelName)
            // ->where('sub_institute_id', $subInstituteId)
 ->where('parent_id', 9)
            ->first();

        return $level ? $level->id : null;
    }

    private function getCurrentLevelForChapter($studentId, $subInstituteId, $chapterId)
    {
        $latestExam = DB::table('lms_online_exam as loes')
            ->join('question_paper as qp', 'qp.id', '=', 'loes.question_paper_id')
            ->where('loes.student_id', $studentId)
            ->where('qp.exam_type', 'PAL')
            ->where('qp.sub_institute_id', $subInstituteId)
            ->where('qp.paper_desc', $chapterId)
            ->orderBy('loes.created_at', 'DESC')
            ->first();

        if (!$latestExam) {
            return 'easy';
        }

        $totalQuestions = $latestExam->total_right + $latestExam->total_wrong;
        $percentage = $totalQuestions > 0 ? ($latestExam->total_right / $totalQuestions) * 100 : 0;

        if ($percentage < 40) {
            return 'easy';
        }

        if ($percentage < 70) {
            return 'medium';
        }

        return 'hard';
    }

    private function getRandomPalQuestions($subInstituteId, $standardId, $subjectId, $chapterId, $limit = 10, array $levelIds = [], array $excludeQuestionIds = [])
    {
        $query = DB::table('lms_question_master as lqm')
            ->join('answer_master as am', 'lqm.id', '=', 'am.question_id')
            ->select('lqm.*')
            ->distinct()
            ->where('lqm.sub_institute_id', $subInstituteId)
            ->where('lqm.standard_id', $standardId)
            ->where('lqm.subject_id', $subjectId)
            ->where('lqm.chapter_id', $chapterId)
            ->where('lqm.question_type_id', 1);

        if (!empty($levelIds)) {
            $query->join('lms_question_mapping as lm', 'lqm.id', '=', 'lm.questionmaster_id')
                ->whereIn('lm.mapping_value_id', $levelIds);
        }

        if (!empty($excludeQuestionIds)) {
            $query->whereNotIn('lqm.id', $excludeQuestionIds);
        }

        return $query->inRandomOrder()
            ->take($limit)
            ->get()
            ->toArray();
    }

    private function formatPalQuestionList(array $questions)
    {
        $questionList = [];

        foreach ($questions as $k => $v) {
            $questionList[$k]['question_id'] = $v->id;
            $questionList[$k]['question_text'] = $v->question_title;
        }

        return $questionList;
    }

    private function getMixedLevelQuestionList($subInstituteId, $standardId, $subjectId, $chapterId)
    {
        $levelIds = [
            'easy' => $this->getLevelId('easy', $subInstituteId),
            'medium' => $this->getLevelId('medium', $subInstituteId),
            'hard' => $this->getLevelId('hard', $subInstituteId),
        ];

        $distribution = [
            'easy' => 4,
            'medium' => 3,
            'hard' => 3,
        ];

        $questions = [];
        $selectedQuestionIds = [];

        foreach ($distribution as $levelName => $limit) {
            if (empty($levelIds[$levelName])) {
                continue;
            }

            $levelQuestions = $this->getRandomPalQuestions(
                $subInstituteId,
                $standardId,
                $subjectId,
                $chapterId,
                $limit,
                [$levelIds[$levelName]],
                $selectedQuestionIds
            );

            foreach ($levelQuestions as $question) {
                $questions[] = $question;
                $selectedQuestionIds[] = $question->id;
            }
        }

        $remaining = 10 - count($questions);
        if ($remaining > 0) {
            $mappedLevelIds = array_values(array_filter($levelIds));
            $fillQuestions = $this->getRandomPalQuestions(
                $subInstituteId,
                $standardId,
                $subjectId,
                $chapterId,
                $remaining,
                $mappedLevelIds,
                $selectedQuestionIds
            );

            foreach ($fillQuestions as $question) {
                $questions[] = $question;
                $selectedQuestionIds[] = $question->id;
            }
        }

        $remaining = 10 - count($questions);
        if ($remaining > 0) {
            $fillQuestions = $this->getRandomPalQuestions(
                $subInstituteId,
                $standardId,
                $subjectId,
                $chapterId,
                $remaining,
                [],
                $selectedQuestionIds
            );

            foreach ($fillQuestions as $question) {
                $questions[] = $question;
            }
        }

        shuffle($questions);

        return $this->formatPalQuestionList($questions);
    }


    public function create(Request $request){
        $type=$request->type;
        $grade_id = $res['grade_id'] = $request->grade_id;        
        $standard_id = $res['standard_id'] = $request->standard_id;
        $subject_id = $res['subject_id']= $request->subject_id;
        $chapter_id = $res['chapter_id']= $request->chapter_id;
        $enrollment_no = $res['enrollment_no'] = $request->enrollment_no;
        $res['message'] = "no data";

        if($type=='API'){
            $student_id =$request->user_id;   
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;         
        }else{
            $student_id =session()->get('user_id');
            $sub_institute_id =session()->get('sub_institute_id');
            $syear = session()->get('syear');   
        }

        $attemptedChapterQuizCount = questionpaperModel::where('created_by', $student_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('exam_type', 'PAL')
            ->where('paper_desc', $chapter_id)
            ->count();

        if ($attemptedChapterQuizCount == 0) {
            $questionList = $this->getMixedLevelQuestionList($sub_institute_id, $standard_id, $subject_id, $chapter_id);
        } else {
            $currentLevel = $this->getCurrentLevelForChapter($student_id, $sub_institute_id, $chapter_id);
            $nextLevel = $this->getNextLevel($currentLevel);
            $selectedLevelId = $this->getLevelId($nextLevel, $sub_institute_id);
            $randomQuestions = $this->getRandomPalQuestions(
                $sub_institute_id,
                $standard_id,
                $subject_id,
                $chapter_id,
                10,
                $selectedLevelId ? [$selectedLevelId] : []
            );

            $questionList = $this->formatPalQuestionList($randomQuestions);
        }

        // $questionList = lmsQuestionMasterModel::where(['sub_institute_id'=>$sub_institute_id,'standard_id'=>$standard_id,'subject_id'=>$subject_id,'chapter_id'=>$chapter_id])->take(10)->orderBy('id','DESC')->get()->toArray();
        $answer=[];
        $existQusetion = [];
        if(empty($questionList)){
             // Use level-based question fetching with the provided query
            $fallbackLevelId = $attemptedChapterQuizCount == 0 ? null : ($selectedLevelId ?? null);
            $randomQuestions = $this->getRandomPalQuestions(
                $sub_institute_id,
                $request->standard_id,
                $request->subject_id,
                $request->chapter_id,
                10,
                $fallbackLevelId ? [$fallbackLevelId] : []
            );

            // If no questions found with the selected level, fallback to any questions
            if (empty($randomQuestions)) {
                $randomQuestions = $this->getRandomPalQuestions(
                    $sub_institute_id,
                    $request->standard_id,
                    $request->subject_id,
                    $request->chapter_id
                );
            }

            $questionList = $this->formatPalQuestionList($randomQuestions);
        }
        // echo "<pre>";print_r($questionList);exit;

        if(!empty($questionList)){
            $filteredQuestions = [];
        foreach ($questionList as $key => $val) {
            if(!in_array($val['question_id'],$existQusetion)){
                $answer_arr = answermasterModel::where([
                    "question_id"      => $val['question_id'],
                    "sub_institute_id" => $sub_institute_id,
                ])->get()->toArray();
                if (count($answer_arr) > 0) {
                    foreach ($answer_arr as $anskey => $ansval) {
                        $answer[$val['question_id']][] = $ansval;
                    }
                    $filteredQuestions[] = $val;

                }
                $existQusetion[]=$val['question_id'];
            }
        }
         $questionList = $filteredQuestions;
        
        // Check if we have any questions with answers after filtering
        if(empty($questionList)){
            $res['status_code'] = 0;
            $res['message'] = 'Questions Not Found';
            return is_mobile($type, 'pal.index', $res, "redirect");exit;
        }
        // echo "<pre>";print_r($answer);exit;
    }else{
        $res['status_code'] = 0;
        $res['message'] = 'Questions Not Found';
        return is_mobile($type, 'pal.index', $res, "redirect");exit;      
    }
    
        // echo "<pre>";print_r($questionList);exit;
        
        $res['question_arr'] = $questionList;
        $res['answer_arr'] = $answer;        
        $res['questionpaper_data']['total_marks'] = 10;
        $res['questionpaper_data']['time_allowed'] = 20;
        $res['questionpaper_data']['paper_name'] = "PAL Test";        
        // send request to python file 
        // echo "<pre>";print_r($res['question_arr']);exit;
        return is_mobile($type, 'lms/pal/exam', $res, "view");                
    }

/**
 * Increment content_visited count when student views a content
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function incrementContentVisit(Request $request)
{
    $type = $request->input('type');

    if ($type == 'API') {
        $student_id = $request->user_id;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;
    } else {
        $student_id = session()->get('user_id');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    }

    $content_id = $request->input('content_id');
    $standard_id = $request->input('standard_id');
    $subject_id = $request->input('subject_id');
    $chapter_id = $request->input('chapter_id');
    $content_type = $request->input('content_type', 'pal_content');

    $contentIdColumn = $content_type === $this->misconceptionContentType()
        ? $this->suggestedContentQuestionIdColumn()
        : 'type_id';

    try {
        $updated = DB::table('suggested_content')
            ->where('type', $content_type)
            ->where($contentIdColumn, $content_id)
            ->where('student_id', $student_id)
            ->where('standard_id', $standard_id)
            ->where('subject_id', $subject_id)
            ->where('chapter_id', $chapter_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->increment('content_visited');

        if ($updated) {
            // Get updated count
            $newCount = DB::table('suggested_content')
                ->where('type', $content_type)
                ->where($contentIdColumn, $content_id)
                ->where('student_id', $student_id)
                ->value('content_visited');

            return response()->json([
                'status' => 1,
                'message' => 'Content visit recorded',
                'content_visited' => $newCount
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Content record not found'
        ]);

    } catch (\Exception $e) {
        Log::error('Error incrementing content_visited: ' . $e->getMessage());
        return response()->json([
            'status' => 0,
            'message' => 'Error recording visit'
        ]);
    }
}
    public function store(Request $request){
        $type = $request->type;
        if($type != 'API'){
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');            
            $user_id = $request->session()->get('user_id');
        }else{
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
        }
        $grade_id = $request->grade_id;
        $standard_id = $request->standard_id;
        $subject_id= $request->subject_id;
        $paper_name= $request->paper_name; 
        $chapter_name = $request->chapter_name;
        $date = date('Y-m-d H:i:s');     
        $allowed_time = $request->questionpaper_time;  
        $total_marks = $request->total_marks;
        $submittedQuestionIds = $request->get('question_ids', []);
        $question_ids = implode(',', $submittedQuestionIds);
        $total_question = $request->total_question;
        // echo "<pre>";print_r($request->all());exit;
        $res['message']='failed to submit';
        // first add question paper
        $getChaptername = DB::table('chapter_master')->where('id',$request->chapter_id)->where('sub_institute_id',$sub_institute_id)->first();
        
        $questionPaperDetails = [
            'grade_id'=>$grade_id,
            'standard_id'=>$standard_id,
            'subject_id'=>$subject_id,
            'paper_name'=>$paper_name,
            'paper_desc'=>$request->chapter_id, // to count quiz it is used as chapter_id only in PAL
            'timelimit_enable'=>1,
            'time_allowed' =>$allowed_time,
            'total_marks' =>$total_marks,
            'total_ques'=>$total_question,
            'question_ids' =>$question_ids,
            'shuffle_question' =>1,
            'attempt_allowed' =>0,
            'show_feedback'=>1,
            'show_hide' =>1,
            'result_show_ans' =>1,
             'created_by'=>$user_id,
            'sub_institute_id'=>$sub_institute_id,
            'exam_type'=>'PAL',
        ];
        // $check_exists = DB::table('question_paper')->where($questionPaperDetails)->first();
        // if(empty($check_exists)){
            $questionPaperDetails['open_date'] = now();
            $questionPaperDetails['created_on'] = now();
            $questionPaperDetails['close_date'] = now();
            $questionPaperId = DB::table('question_paper')->insertGetId($questionPaperDetails);
        // echo "<pre>";print_r($questionPaperId);exit;
        
        $controller = new onlineExamController;
        $result = $controller->get_calculate_marks($request);
        // echo "<pre>";print_r($result);exit;
        $answer_single = $request->get('answer_single');
        $answer_multiple = $request->get('answer_multiple');
        $answer_narrative = $request->get('answer_narrative');
        $attemptTimes = $request->get('attempt_time', []);
        $submittedQuestionIds = array_values(array_unique(array_filter($submittedQuestionIds)));
        $totalQuestions = (int) ($total_question ?: count($submittedQuestionIds));
        $answeredQuestionIds = [];

        if (is_array($answer_single)) {
            $answeredQuestionIds = array_merge($answeredQuestionIds, array_keys(array_filter($answer_single)));
        }

        if (is_array($answer_multiple)) {
            foreach ($answer_multiple as $questionId => $answerIds) {
                if (!empty($answerIds)) {
                    $answeredQuestionIds[] = $questionId;
                }
            }
        }

        if (is_array($answer_narrative)) {
            $answeredQuestionIds = array_merge($answeredQuestionIds, array_keys(array_filter($answer_narrative)));
        }

        $answeredQuestionIds = array_values(array_unique(array_map('strval', $answeredQuestionIds)));
        $attemptedQuestions = count($answeredQuestionIds);
        $skippedQuestions = max(0, $totalQuestions - $attemptedQuestions);
        $totalTimeSpent = 0;

        if (is_array($attemptTimes)) {
            foreach ($attemptTimes as $attemptTime) {
                $totalTimeSpent += max(0, (int) $attemptTime);
            }
        }

        $accuracyRate = $attemptedQuestions > 0
            ? round(((int) $result['total_right_ans'] / $attemptedQuestions) * 100, 2)
            : 0;
        $skipRate = $totalQuestions > 0
            ? round(($skippedQuestions / $totalQuestions) * 100, 2)
            : 0;
        $avgTime = $attemptedQuestions > 0
            ? round($totalTimeSpent / $attemptedQuestions, 2)
            : 0;
        $idealTime = 30;
        $maxTime = 120;
        $timePenalty = max(0, ($avgTime - $idealTime) / ($maxTime - $idealTime));
        $timePenalty = min($timePenalty, 1.0);
        $struggleScore = round((
            ((1 - ($accuracyRate / 100)) * 0.45)
            + (($skipRate / 100) * 0.35)
            + ($timePenalty * 0.20)
        ) * 100, 2);

        $hasOnlineExamMetrics = Schema::hasColumn('lms_online_exam', 'accuracy_rate')
            && Schema::hasColumn('lms_online_exam', 'skip_rate')
            && Schema::hasColumn('lms_online_exam', 'avg_time');
        $hasOnlineExamStruggleScore = Schema::hasColumn('lms_online_exam', 'struggle_score');

        //START Insert into lms_online_exam table
        $online_exam = [
            'student_id'        => $user_id,
            'question_paper_id' => $questionPaperId,
            'total_right'       => $result['total_right_ans'],
            'total_wrong'       => $result['total_wrong_ans'],
            'obtain_marks'      => $result['obtain_marks'],
            'start_time'        => $request->get('hid_session_quiz') ?? now(),
            'created_at'        => now(),
        ];

        if ($hasOnlineExamMetrics) {
            $online_exam['accuracy_rate'] = $accuracyRate;
            $online_exam['skip_rate'] = $skipRate;
            $online_exam['avg_time'] = $avgTime;
        }

        if ($hasOnlineExamStruggleScore) {
            $online_exam['struggle_score'] = $struggleScore;
        }

        lmsOnlineExamModel::insert($online_exam);
        $online_exam_id = DB::getPDO()->lastInsertId();
        //END Insert into lms_online_exam table

        //START Insert into lms_online_exam_answer table
        $answeredStudentQuestionIds = [];
        $getAttemptTime = function ($questionId) use ($attemptTimes) {
            $attemptTime = is_array($attemptTimes) && isset($attemptTimes[$questionId]) ? (int) $attemptTimes[$questionId] : 0;
            return max(0, $attemptTime);
        };
        $hasAttemptTimeColumn = Schema::hasColumn('lms_online_exam_answer_student', 'attempt_time');
        $insertStudentAnswer = function (array $answerData) use ($hasAttemptTimeColumn) {
            if (!$hasAttemptTimeColumn) {
                unset($answerData['attempt_time']);
            }

            lmsOnlineExamAnswerStudent::create($answerData);
        };
        $rightInterest=[];
        // echo "<pre>";print_r($answer_single);exit;
        if (is_array($answer_single)) {
            foreach ($answer_single as $single_question_id => $single_answer_ids) {
                $ans_status = "wrong";
                $single_ans_arr = explode("##", $single_answer_ids);
                $interset = $request->interestValue[$single_question_id];
                if(!isset($rightInterest[$interset])){
                    $rightInterest[$interset]=0;
                }
                if ($single_ans_arr[1] == 1) {
                    $ans_status = "right";
                    // interset mapped type
                    $rightInterest[$interset] += 1;
                }
                $single = [
                    'question_paper_id' => $questionPaperId,
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $single_question_id,
                    'answer_id'         => $single_ans_arr[0],
                    'ans_status'        => $ans_status,
                    'created_at'        => now(),                    
                ];
                lmsOnlineExamModel::insert($single);
                $insertStudentAnswer([
                    'question_paper_id' => $questionPaperId,
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $single_question_id,
                    'answer_id'         => $single_ans_arr[0],
                    'ans_status'        => $ans_status,
                    'attempt_time'      => $getAttemptTime($single_question_id),
                ]);
                $answeredStudentQuestionIds[] = $single_question_id;
            }
        }

        if (is_array($answer_multiple)) {
            foreach ($answer_multiple as $multiple_question_id => $multiple_answer_ids) {
                if (is_array($multiple_answer_ids))//Insert MCQ Answers
                {
                    foreach ($multiple_answer_ids as $key => $val) {
                        $ans_status = "wrong";
                        $multiple_ans_arr = explode("##", $val);
                        $interset = $request->interestValue[$multiple_question_id];
                     
                        if(!isset($rightInterest[$interset])){
                            $rightInterest[$interset]=0;
                        }
                        if ($multiple_ans_arr[1] == 1) {
                            $ans_status = "right";
                            // interset mapped type
                            $rightInterest[$interset] += 1;
                        }
                        $multiple = [
                            'question_paper_id' => $questionPaperId,
                            'online_exam_id'    => $online_exam_id,
                            'student_id'        => $user_id,
                            'question_id'       => $multiple_question_id,
                            'answer_id'         => $multiple_ans_arr[0],
                            'ans_status'        => $ans_status,
                            'created_at'        => now(),                                                
                        ];
                        lmsOnlineExamAnswerModel::insert($multiple);
                        $insertStudentAnswer([
                            'question_paper_id' => $questionPaperId,
                            'online_exam_id'    => $online_exam_id,
                            'student_id'        => $user_id,
                            'question_id'       => $multiple_question_id,
                            'answer_id'         => $multiple_ans_arr[0],
                            'ans_status'        => $ans_status,
                            'attempt_time'      => $getAttemptTime($multiple_question_id),
                        ]);
                        $answeredStudentQuestionIds[] = $multiple_question_id;
                    }
                }
            }
        }

        if (is_array($answer_narrative)) {
            foreach ($answer_narrative as $narrative_question_id => $narrative_answer_ids) {
                $ans_status = "right";
                if(!isset($rightInterest[$interset])){
                    $rightInterest[$interset]=0;
                }
                $rightInterest[$interset] += 1;
                $narrative = [
                    'question_paper_id' => $questionPaperId,
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $narrative_question_id,
                    'narrative_answer'  => $narrative_answer_ids,
                    'ans_status'        => $ans_status,
                    'created_at'        => now(),                                                                    
                ];
                lmsOnlineExamModel::insert($narrative);
                $insertStudentAnswer([
                    'question_paper_id' => $questionPaperId,
                    'online_exam_id'    => $online_exam_id,
                    'student_id'        => $user_id,
                    'question_id'       => $narrative_question_id,
                    'narrative_answer'  => $narrative_answer_ids,
                    'ans_status'        => $ans_status,
                    'attempt_time'      => $getAttemptTime($narrative_question_id),
                ]);
                $answeredStudentQuestionIds[] = $narrative_question_id;
            }
        }

        foreach (array_diff($submittedQuestionIds, array_unique($answeredStudentQuestionIds)) as $unansweredQuestionId) {
            $insertStudentAnswer([
                'question_paper_id' => $questionPaperId,
                'online_exam_id'    => $online_exam_id,
                'student_id'        => $user_id,
                'question_id'       => $unansweredQuestionId,
                'ans_status'        => 'wrong',
                'attempt_time'      => $getAttemptTime($unansweredQuestionId),
            ]);
        }

        // Neo4j Assessment Node
         neo4jCreateNode(
            'Assessment',
            [
                'assId' => (int)$questionPaperId,
                'sub_institute_id' => (int)$sub_institute_id
            ],
            [
                'displayLabel' => 'Assessment:' . $paper_name,
                'exam_type' => 'pal',
                'paper_name' => $paper_name,
                'total_marks' => (float)$total_marks,
                'standard_id' => (int)$standard_id,
                'subject_id' => (int)$subject_id,
                'grade_id' => (int)$grade_id,
                'question_ids' => $question_ids,
                'total_ques' => (int)$total_question
            ]
        );

    // ================= RESULT NODE =================
    // Result Node (using original online_exam_id from line 384)
    neo4jCreateNode(
        'Result',
        ['resultId' => (int)$online_exam_id, 'student_id' => (int)$user_id],
        [
            'question_paper_id' => (int)$questionPaperId,
            'total_right' => (int)$result['total_right_ans'],
            'total_wrong' => (int)$result['total_wrong_ans'],
            'obtain_marks' => (int)$result['obtain_marks'],
            'displayLabel' => 'Result:' . $result['obtain_marks']
        ]
    );

    // ================= RELATIONSHIPS =================
    neo4jCreateRelationship(
        'Result',
        ['resultId' => (int)$online_exam_id],
        'FOR_ASSESSMENT',
        'Assessment',
        ['assId' => (int)$questionPaperId]
    );

    neo4jCreateRelationship(
        'Student',
        ['student_id' => (int)$user_id],
        'HAS_RESULT',
        'Result',
        ['resultId' => (int)$online_exam_id]
    );

    neo4jCreateRelationship(
        'Assessment',
        ['assId' => (int)$questionPaperId],
        'ASSESSES_CHAPTER',
        'Chapter',
        ['chId' => (int)$request->chapter_id]
    );

    // ================= ✅ MASTERS RELATION =================
    try {

        $records = DB::select("
            SELECT 
                r.obtain_marks,
                a.total_marks,
                a.paper_desc as chapter_id
            FROM lms_online_exam r
            JOIN question_paper a ON a.id = r.question_paper_id
            WHERE r.student_id = ? AND a.total_marks > 0
        ", [$user_id]);

        $chapterWise = [];

        foreach ($records as $row) {

            $chId = $row->chapter_id;
            if (!$chId) continue;

            $ratio = $row->obtain_marks / $row->total_marks;

            if (!isset($chapterWise[$chId])) {
                $chapterWise[$chId] = ['total' => 0, 'count' => 0];
            }

            $chapterWise[$chId]['total'] += $ratio;
            $chapterWise[$chId]['count']++;
        }

        foreach ($chapterWise as $chId => $data) {

            $avg = $data['total'] / $data['count'];
            $score = round($avg * 100);

            neo4jCreateRelationship(
                'Student',
                ['student_id' => (int)$user_id],
                'MASTERS',
                'Chapter',
                ['chId' => (int)$chId],
                ['proficiency_score' => (int)$score]
            );
        }

        \Log::info('✅ MASTERS created', ['student_id' => $user_id]);

    } catch (\Exception $e) {
        \Log::error('❌ MASTERS ERROR', ['message' => $e->getMessage()]);
    }

        $res['message'] = "Exam submitted";
        return redirect()->route('pal.show',[$questionPaperId,"online_exam_id"=> $online_exam_id,"rightInterest"=>$rightInterest]);
    
    }

    public function suggestedContent(Request $request)
{
    $requestedLevel = $request->input('student_level');

     // ================= GET SESSION DATA =================
     $type = $request->input('type');
     $isAjax = $request->ajax() || $request->input('type') === 'AJAX';
 
     if ($type == 'API' || $isAjax) {
         $student_id = $request->input('user_id', session()->get('user_id'));
         $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
         $syear = $request->input('syear', session()->get('syear'));
     } else {
         $student_id = session()->get('user_id');
         $sub_institute_id = session()->get('sub_institute_id');
         $syear = session()->get('syear');
     }
 
     // Fallback to session if values not found
     if(empty($sub_institute_id)) $sub_institute_id = session()->get('sub_institute_id');
     if(empty($student_id)) $student_id = session()->get('user_id');
     if(empty($syear)) $syear = session()->get('syear');
    if (!$requestedLevel) {

        $latestExam = DB::table('lms_online_exam as loes')
            ->join('question_paper as qp', 'qp.id', '=', 'loes.question_paper_id')
            ->where('loes.student_id', $student_id)
            ->where('qp.exam_type', 'PAL')
            ->where('qp.sub_institute_id', $sub_institute_id)
            ->orderBy('loes.created_at', 'DESC')
            ->first();

        if ($latestExam) {
            $totalQuestions = $latestExam->total_right + $latestExam->total_wrong;
            $percentage = $totalQuestions > 0 ? ($latestExam->total_right / $totalQuestions) * 100 : 0;

            if ($percentage < 40) {
                $studentLevel = 'easy';
            } elseif ($percentage < 70) {
                $studentLevel = 'medium';
            } else {
                $studentLevel = 'hard';
            }
        } else {
            $studentLevel = 'easy';
        }

    } else {
        $studentLevel = $requestedLevel;
    }

    // ================= SPECIAL RULE =================
    // easy → medium
    if ($studentLevel == 'easy') {
        $studentLevel = 'medium';
    }

    // ================= GET LEVEL ID =================
    $levelMapping = lmsmappingtypeModel::where('name', $studentLevel)->first();
    $studentLevelId = $levelMapping ? $levelMapping->id : null;

    // ================= MERGE REQUEST =================
    $request->merge([
        'student_level' => $studentLevel,
        'student_level_id' => $studentLevelId
    ]);

    // ================= GET DATA =================
    $data = $this->getData($request);

    $res['sub_institute_id'] = $sub_institute_id;

    // ================= MAPPING =================
    $lms_mapping_type = DB::table('lms_mapping_type')
        ->where('status', '=', 1)
        ->where('parent_id', '=', 0)
        ->where(function ($q) use ($request) {
            $q->where('globally', '=', 1)
              ->orWhere('chapter_id', $request->get('chapter_id'));
        })
        ->where(function ($q) use ($request) {
            $q->where('topic_id', '=', 0)
              ->orWhere('topic_id', $request->get('topic_id'));
        })
        ->where('element_id','content_library')
        ->get()->toArray();

    $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);

    $lms_mapping_Values = [];
    foreach ($lms_mapping_type as $key => $value) {
        $lms_mapping_Values[$value['name']] = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', $value['id'])
            ->get()->toArray();
    }

    // ================= FINAL RESPONSE =================
    $res['status_code'] = 1;
    $res['message'] = "SUCCESS";
    $res['data'] = $data['chapter_data'];
    $res['content_data'] = $this->deduplicateContentData($data['content_data']);
    $res['grade'] = $data['basic_ids']['grade_id'];
    $res['standard'] = $data['basic_ids']['standard_id'];
    $res['subject'] = $data['basic_ids']['subject_id'];
    $res['subject_name'] = $data['basic_ids']['subject_name'];
    $res['show_content'] = $data['basic_ids']['add_content'];
    $res['lms_mapping_type'] = $lms_mapping_type;
    $res['lms_mapping_Values'] = $lms_mapping_Values;
    $res['mapped_type'] = $request->mapping_type;
    $res['mapped_value'] = $request->mapped_value;

    // ✅ IMPORTANT
    $res['student_level'] = $this->formatLevelLabel($studentLevel);
    $res['student_level_id'] = $studentLevelId;

    if ($isAjax) {
        return response()->json($res);
    }

     return is_mobile($type, 'lms/pal/suggested_content', $res, "view");
 }    

     /**
      * Get suggested content from the suggested_content table for a chapter
      * 
      * @param Request $request
      * @return \Illuminate\Http\JsonResponse
      */
     public function getSuggestedContent(Request $request)
     {
         $type = $request->input('type');
         $isAjax = $request->ajax() || $request->input('type') === 'AJAX';

         if ($type == 'API' || $isAjax) {
             $student_id = $request->input('user_id', session()->get('user_id'));
             $sub_institute_id = $request->input('sub_institute_id', session()->get('sub_institute_id'));
             $syear = $request->input('syear', session()->get('syear'));
         } else {
             $student_id = session()->get('user_id');
             $sub_institute_id = session()->get('sub_institute_id');
             $syear = session()->get('syear');
         }

         // Fallback to session if values not found
         if(empty($sub_institute_id)) $sub_institute_id = session()->get('sub_institute_id');
         if(empty($student_id)) $student_id = session()->get('user_id');
         if(empty($syear)) $syear = session()->get('syear');

         $standard_id = $request->input('standard_id');
         $subject_id = $request->input('subject_id');
         $chapter_id = $request->input('chapter_id');
         $grade_id = $request->input('grade_id');
         $hasContentUserProfile = Schema::hasColumn('content_master', 'user_profile_name');

         $selectColumns = ['cm.*', 'sc.student_level'];
         if (Schema::hasColumn('suggested_content', 'content_visited')) {
             $selectColumns[] = 'sc.content_visited';
         }

         // Get suggested content from the suggested_content table
         $suggestedContent = DB::table('suggested_content as sc')
        ->join('content_master as cm', 'cm.id', '=', 'sc.type_id')
        ->where('sc.type', 'pal_content')
        ->when($hasContentUserProfile, function ($query) {
            $query->where(function ($profileQuery) {
                $profileQuery->whereNull('cm.user_profile_name')
                    ->orWhereRaw("LOWER(cm.user_profile_name) != 'student'");
            });
        })
        ->where('sc.student_id', $student_id)
        ->where('sc.standard_id', $standard_id)
        ->where('sc.subject_id', $subject_id)
        ->when($chapter_id, function ($query) use ($chapter_id) {
            $query->where('cm.chapter_id', $chapter_id);
        })
        ->where('sc.sub_institute_id', $sub_institute_id)
        ->where('sc.syear', $syear)
        ->select($selectColumns)
        ->get()
        ->toArray();

         $this->attachContentMappings($suggestedContent);

         // Format the data similar to the suggestedContent method's content_data
         $content_data = [];
         if (!empty($suggestedContent)) {
             foreach ($suggestedContent as $content) {
                 $content->student_level = $this->formatLevelLabel($content->student_level);
                 $content->teacher_content_category = $content->content_category;
                 $content->content_type = 'teacher_content';
                 $content_data[$content->chapter_id]['Teacher Content'][] = $content;
             }
         }

         $contentIdColumn = $this->suggestedContentQuestionIdColumn();
         $misconceptionSelectColumns = ['cm.*', 'sc.student_level'];
         if (Schema::hasColumn('suggested_content', 'content_visited')) {
             $misconceptionSelectColumns[] = 'sc.content_visited';
         }

         $misconceptionContent = DB::table('suggested_content as sc')
             ->join('content_master as cm', 'cm.id', '=', 'sc.' . $contentIdColumn)
             ->where('sc.type', $this->misconceptionContentType())
             ->when($hasContentUserProfile, function ($query) {
                 $query->whereRaw("LOWER(cm.user_profile_name) = 'student'");
             })
             ->where('sc.student_id', $student_id)
             ->where('sc.standard_id', $standard_id)
             ->where('sc.subject_id', $subject_id)
             ->where('sc.sub_institute_id', $sub_institute_id)
             ->where('sc.syear', $syear)
             ->when($chapter_id, function ($query) use ($chapter_id) {
                 $query->where(function ($chapterQuery) use ($chapter_id) {
                     $chapterQuery->where('cm.chapter_id', $chapter_id)
                         ->orWhere('sc.chapter_id', $chapter_id);
                 });
             })
             ->select($misconceptionSelectColumns)
             ->orderBy('sc.id', 'desc')
             ->get()
             ->toArray();

         $this->attachContentMappings($misconceptionContent);

         if (!empty($misconceptionContent)) {
             foreach ($misconceptionContent as $item) {
                 $item->student_level = $this->formatLevelLabel($item->student_level);
                 $item->teacher_content_category = $item->content_category ?: 'Misconception Content';
                 $item->content_type = 'misconception_content';
                 $content_data[$item->chapter_id]['Misconception Content'][] = $item;
             }
         }

         // Get chapter data for the chapter (to get chapter name, etc.)
        //  $chapterData = chapterModel::where('id', $chapter_id)
        //      ->where('sub_institute_id', $sub_institute_id)
        //      ->first();

         $res['status_code'] = 1;
         $res['message'] = "SUCCESS";
         $res['content_data'] = $this->deduplicateContentData($content_data);
        //  $res['chapter_data'] = $chapterData ? [$chapterData->toArray()] : [];
         $res['grade'] = $grade_id;
         $res['standard'] = $standard_id;
         $res['subject'] = $subject_id;
         $res['subject_name'] = DB::table('sub_std_map')
             ->where('standard_id', $standard_id)
             ->where('subject_id', $subject_id)
             ->where('sub_institute_id', $sub_institute_id)
             ->value('display_name');

         if ($isAjax) {
             return response()->json($res);
         }

         return is_mobile($type, 'lms/pal/suggested_content', $res, "view");
     }

     private function attachContentMappings(&$contents)
     {
         if (empty($contents)) {
             return;
         }

         $contentIds = [];
         foreach ($contents as $content) {
             $contentIds[] = is_array($content) ? ($content['id'] ?? null) : ($content->id ?? null);
         }
         $contentIds = array_values(array_filter(array_unique($contentIds)));

         if (empty($contentIds)) {
             return;
         }

         $mappingRows = DB::table('content_mapping_type as cmt')
             ->join('lms_mapping_type as t', 't.id', '=', 'cmt.mapping_type_id')
             ->join('lms_mapping_type as t1', 't1.id', '=', 'cmt.mapping_value_id')
             ->whereIn('cmt.content_id', $contentIds)
             ->select(
                 'cmt.content_id',
                 't.name as type_name',
                 't.id as type_id',
                 't1.name as value_name',
                 't1.id as value_id'
             )
             ->get()
             ->groupBy('content_id');

         foreach ($contents as &$content) {
             $contentId = is_array($content) ? ($content['id'] ?? null) : ($content->id ?? null);
             $mapping = isset($mappingRows[$contentId]) ? $mappingRows[$contentId]->values()->toArray() : [];

             if (is_array($content)) {
                 $content['mapping'] = $mapping;
             } else {
                 $content->mapping = $mapping;
             }
         }
     }

     public function storeSuggestedContent(Request $request)
{
    $student_id = session()->get('user_id');
    $contentData = $request->content_data;

    if(empty($contentData)){
        return response()->json(['status' => 0, 'message' => 'No content found']);
    }

    $studentLevel = strtolower($request->student_level ?? 'medium');
    $sub_institute_id = $request->sub_institute_id ?? session()->get('sub_institute_id');
    $standard_id = $request->standard_id;
    $subject_id = $request->subject_id;
    $chapter_id = $request->chapter_id;
    $syear = $request->syear;

    foreach($contentData as $chapterId => $categories){
        foreach($categories as $category => $contents){
            foreach($contents as $content){

                if(empty($content)) continue;

                $contentId = $content['id'] ?? null;

                if (empty($contentId)) {
                    continue;
                }

                $exists = DB::table('suggested_content')
                    ->where('type', 'pal_content')
                    ->where('type_id', $contentId)
                    ->where('student_id', $student_id)
                    ->where('standard_id', $standard_id)
                    ->where('subject_id', $subject_id)
                    ->where('chapter_id', $chapter_id)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('syear', $syear)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('suggested_content')->insert([
                    'type' => 'pal_content',
                    'type_id' => $contentId,
                    'student_id' => $student_id,
                    'student_level' => $studentLevel,
                    'content_visited' => 0,  // ADD THIS LINE
                    'standard_id' => $standard_id,
                    'subject_id' => $subject_id,
                    'chapter_id' => $chapter_id,
                    'sub_institute_id' => $sub_institute_id,
                    'syear' => $syear,
                    'created_by' => $student_id,
                    'created_at' => now()
                ]);
            }
        }
    }

    return response()->json([
        'status' => 1,
        'message' => 'Content Stored Successfully'
    ]);
}

public function getData($request)
    {
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
            $year = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            $syear =$year[0]->syear;
            $user_profile_name = 1;
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
            $user_profile_name = $request->session()->get('user_profile_name');
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $extra_where = array();
        if ($user_profile_name == "Student") {
            $extra_where['chapter_master.show_hide'] = "1";
            $content_where['content_master.show_hide'] = '1';
        }

        $subject_id = $request->input('subject_id');
        $standard_id = $request->input('standard_id');
        $data['chapter_data'] = array();
        $hasContentUserProfile = Schema::hasColumn('content_master', 'user_profile_name');

        $data['chapter_data'] = chapterModel::select('chapter_master.*',
            DB::raw('COUNT(content_master.id) as total_content,sum(if(content_category = "Triz", 1, 0)) AS total_triz_content,
        sum(if(content_category = "OER", 1, 0)) AS total_OER_content'))
            ->leftjoin('content_master', function ($join) use ($hasContentUserProfile) {
                $join->on('content_master.chapter_id', '=', 'chapter_master.id');
                if ($hasContentUserProfile) {
                    $join->where(function ($query) {
                        $query->whereNull('content_master.user_profile_name')
                            ->orWhereRaw("LOWER(content_master.user_profile_name) != 'student'");
                    });
                }
            })
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('chapter_master.sub_institute_id', '1')
                        ->orWhere('chapter_master.sub_institute_id', $sub_institute_id);
                } else {
                    $query->Where('chapter_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('chapter_master.subject_id', $subject_id)
            ->where('chapter_master.standard_id', $standard_id)
            ->where($extra_where)
            ->groupBy('chapter_master.id')
            ->orderBy('chapter_master.sort_order')
            ->get();

        $data['basic_ids'] = sub_std_mapModel::select('standard.grade_id', 'sub_std_map.subject_id',
            'sub_std_map.standard_id',
            'sub_std_map.display_name as subject_name', 'sub_std_map.add_content')
            ->join('standard', 'standard.id', '=', 'sub_std_map.standard_id')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('sub_std_map.sub_institute_id', '1')
                        ->orWhere('sub_std_map.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('sub_std_map.subject_id', $subject_id)
            ->where('sub_std_map.standard_id', $standard_id)
            ->get()->toArray();

        $content_data = contentModel::select('content_master.*')
    ->where(function ($query) use ($getIsLms, $sub_institute_id) {
        if ($getIsLms == 'Y') {
            $query->where('content_master.sub_institute_id', '1')
                ->orWhere('content_master.sub_institute_id', $sub_institute_id);
        } else {
            $query->where('content_master.sub_institute_id', $sub_institute_id);
        }
    })
    ->where('content_master.subject_id', $subject_id)
    ->where('content_master.standard_id', $standard_id)
    ->where('content_master.chapter_id', $request->chapter_id) // ✅ ADD THIS
    ->when($hasContentUserProfile, function ($query) {
        $query->where(function ($profileQuery) {
            $profileQuery->whereNull('content_master.user_profile_name')
                ->orWhereRaw("LOWER(content_master.user_profile_name) != 'student'");
        });
    })
    ->where(function ($query) {
        $query->whereNull('content_master.topic_id')
            ->orWhere('content_master.topic_id', '0');
    })
     ->when($request->student_level_id, function ($query) use ($request) {
         $query->join('content_mapping_type as cmt', 'cmt.content_id', '=', 'content_master.id')
               ->where('cmt.mapping_value_id', $request->student_level_id);
     }) // ✅ ADD THIS (LEVEL FILTER)
     // Note: Using JOIN for level filter means only content mapped to this level is returned.
     // If no content is found, the level mapping in content_mapping_type table may need to be set up.
    ->get()
    ->toArray();

        $this->attachContentMappings($content_data);

        $content_data_array =[];
        $mappedVals = explode(',',$request->mapped_value);

        if (!empty($content_data)) {
            foreach ($content_data as $content) {
               if(isset($mappedVals[0]) && $mappedVals[0] != ''){

    $exists = DB::table('content_mapping_type')
        ->where('content_id', $content['id'])
        ->whereIn('mapping_value_id', $mappedVals)
        ->exists();

    if($exists){
        $content_data_array[$content['chapter_id']][$content['content_category']][] = $content;
    }

} else {
    $content_data_array[$content['chapter_id']][$content['content_category']][] = $content;
}
            }
            foreach ($content_data_array as $chapter_id => &$chapter_content) {
                
                if (!isset($chapter_content['Flash Cards'])) {
                    $chapter_content['Flash Cards'] =$flash =DB::table('lms_flashcard')
                    ->where(['chapter_id' => $chapter_id, 'sub_institute_id' => $sub_institute_id, 'status' => 1])
                    ->get()
                    ->toArray();
                }
                if (!isset($chapter_content['Mindmap'])) {
                    $chapter_content['Mindmap'] = array();
                }
                if (!isset($chapter_content['Virtual Lab'])) {
                    $chapter_content['Virtual Lab'] = array();
                }
            }
        }
        $data['content_data'] = $content_data_array;

        $data['basic_ids'] = $data['basic_ids'][0];

        return $data;
    }
    public function show(Request $request, $id)
    {
        $questionpaper_id = $id;

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $online_exam_id = $request->get('online_exam_id');
        $data['user_id'] = $online_exam_id;

        $data['questionpaper_data'] = questionpaperModel::find($questionpaper_id)->toArray();

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
                    if (!isset($lmsmapping[$val['id']][$lval['type_name']])) {
                        $lmsmapping[$val['id']][$lval['type_name']] = [];
                    }

                    if (!in_array($lval['value_name'], $lmsmapping[$val['id']][$lval['type_name']])) {
                        $lmsmapping[$val['id']][$lval['type_name']][] = $lval['value_name'];
                    }
                }
            }
        }

        $data['mapping_arr'] = $lmsmapping;
        $data['answer_arr'] = $answer;
        
        // $data['online_exam_data'] =DB::SELECT("SELECT * FROM lms_online_exam  where id ='$online_exam_id' and student_id=95634 AND question_paper_id = '$user_id'");

        $onlineExamData = lmsOnlineExamModel::where([
            'id'=>$online_exam_id,'student_id'=>$user_id
        ])->get()->toArray();
        $data['online_exam_data'] = $onlineExamData[0] ?? $onlineExamData;
        
        // Calculate student level based on performance
        $totalQuestions = $data['online_exam_data']['total_right'] + $data['online_exam_data']['total_wrong'];
        $percentage = $totalQuestions > 0 ? ($data['online_exam_data']['total_right'] / $totalQuestions) * 100 : 0;
        if ($percentage < 40) {
            $data['online_exam_data']['student_level'] = 'easy';
        } elseif ($percentage < 70) {
            $data['online_exam_data']['student_level'] = 'medium';
        } else {
            $data['online_exam_data']['student_level'] = 'hard';
        }

        // $online_answer_data = lmsOnlineExamAnswerModel::where(['online_exam_id'=>$online_exam_id,'student_id'=>$user_id])->get()->toArray();
        // foreach($online_answer_data as $key => $val)
        // {
        //     $data['online_answer_data'][$val['question_id']][] = $val; 
        // }

        $online_answer_data = DB::select("SELECT a.*, GROUP_CONCAT(am.id) AS actual_answer,q.question_type_id,q.multiple_answer,
                (
                CASE 
                WHEN question_type_id = 2 THEN IF(given_answer is null,'wrong','right') 
                WHEN question_type_id = 1 AND multiple_answer = 0 THEN IF(given_answer=GROUP_CONCAT(am.id),'right','wrong') 
                WHEN question_type_id = 1 AND multiple_answer = 1 THEN IF(given_answer=GROUP_CONCAT(am.id),'right','wrong') 
                END
                ) AS right_wrong 
                FROM (
                SELECT question_id,ans_status, IFNULL(narrative_answer, GROUP_CONCAT(answer_id)) AS given_answer
                FROM lms_online_exam_answer
                WHERE online_exam_id = '".$online_exam_id."' AND student_id = '".$user_id."'
                GROUP BY question_id) AS a
                INNER JOIN lms_question_master q ON q.id = a.question_id and q.status = 1
                LEFT JOIN answer_master am ON a.question_id = am.question_id AND correct_answer = 1
                GROUP BY am.question_id,a.question_id
            ");
        //dd($online_answer_data);
        foreach ($online_answer_data as $key => $val) {
            $data['online_answer_data'][$val->question_id]['RIGHT_WRONG'] = $val->right_wrong;
            $data['online_answer_data'][$val->question_id]['ACTUAL_ANSWER'] = $val->actual_answer;
            $data['online_answer_data'][$val->question_id]['GIVEN_ANSWER'] = $val->given_answer;
        }
        //dd($online_answer_data);
       
        $type = $request->input('type');
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        $data['rightInterest'] = $request->rightInterest;
        $data['exam_type'] = 'PAL';
        // echo "<pre>";print_r($data);exit;
        return is_mobile($type, 'lms/online_exam_result', $data, "view");
    }
    public function palreport(Request $request){
        $type= $request->type;
        if($type == 'API'){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
        }else{
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

            $data = DB::table('question_paper as q')
            // ->join('lms_online_exam_student as l', 'l.question_paper_id', '=', 'q.id')
            ->join('lms_online_exam as l', 'l.question_paper_id', '=', 'q.id')
            ->join('tblstudent as t', 't.id', '=', 'l.student_id')
            ->join('tblstudent_enrollment as te', function ($join) {
                $join->on('te.student_id', '=', 't.id')
                     ->on('te.syear', '=', 'q.syear');
            })
            ->join('standard as s', 's.id', '=', 'te.standard_id')
            ->join('division as d', 'd.id', '=', 'te.section_id')
            ->where('q.sub_institute_id', $sub_institute_id)
            ->where('q.syear', $syear)
            ->where('q.exam_type', 'PAL')
            ->select(
                't.first_name',
                't.middle_name',
                't.last_name',
                's.name as standard',
                'd.name as division',
                DB::raw("DATE_FORMAT(l.start_time, '%d-%m-%Y %h:%i:%s') as start_time"),
                DB::raw("CONCAT(l.obtain_marks,'/',(l.total_right+l.total_wrong)) as grade")
            )
            ->orderBy('l.start_time', 'desc')
            ->get()->toArray();
            // echo "<pre>";print_r($res);exit;
        //}

        if (empty($data)) {
            $res['status'] = 0;
            $res['message'] = "No Data Found";
        } else {
            $res['data'] = $data;
            $res['status'] = 1;
            $res['message'] = "Success";
        }
        return is_mobile($type, "lms/pal/palreport", $res, "view");
    }
}

