<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questiontypeModel;
use App\Services\OpenAIService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getTableFieldFromId;

class assessmentQuestionController extends Controller
{
    protected $openAIService;
    
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    
    /**
     * Store questions from assessment preview
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse|Response
     */
    public function store(Request $request)
    {
        // return $request;
        // Log the incoming request for debugging
        \Log::info('Assessment Question Store Request:', $request->all());
        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        
        // Validate required fields
        if (!$request->has('generated_questions') || empty($request->get('generated_questions'))) {
            return response()->json([
                'status_code' => 0,
                'message' => 'No questions generated. Please generate questions first.'
            ]);
        }
        
        if (!$request->has('chapter_id') || empty($request->get('chapter_id'))) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Chapter ID is required.'
            ]);
        }
        
        // Get question type mapping from database
        $questionTypesList = DB::table('question_type_master')
            ->where(function($q) use ($sub_institute_id) {
                $q->where('sub_institute_id', $sub_institute_id)
                  ->orWhere('sub_institute_id', 0);
            })
            ->get();
        
        $questionTypeMap = [];
        foreach ($questionTypesList as $qt) {
            $questionTypeMap[strtolower($qt->question_type)] = $qt->id;
        }
        
        // Get the generated questions from the request
        $generatedQuestionsJson = $request->get('generated_questions', '[]');
        
        // Decode JSON string if it's a string
        if (is_string($generatedQuestionsJson)) {
            $generatedQuestions = json_decode($generatedQuestionsJson, true);
        } else {
            $generatedQuestions = $generatedQuestionsJson;
        }
        // return $generatedQuestions; 
        
        if (empty($generatedQuestions)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'No questions to save'
            ]);
        }
        
        $savedQuestions = [];
        
        // Get question type from form selection (user's selection)
        $userQuestionTypeId = $request->get('question_type_id');
        $userMultipleAnswer = $request->get('multiple_answer', 0);
        
        // Debug log
        \Log::info('User Question Type ID: ' . $userQuestionTypeId);
        \Log::info('User Multiple Answer: ' . $userMultipleAnswer);
        \Log::info('Question Type Map:', $questionTypeMap);
        
        foreach ($generatedQuestions as $questionData) {
            // Map question_type string to ID (AI returns strings like 'MCQ', 'ShortAnswer')
            // Use user's selected question type if available, otherwise use AI's response
            if (!empty($userQuestionTypeId)) {
                $questionTypeId = $userQuestionTypeId;
            } else {
                $questionTypeId = 1; // Default to MCQ (id=1)
                $questionTypeStr = isset($questionData['question_type']) ? $questionData['question_type'] : 'MCQ';
                
                // Try to find the question type ID (case-insensitive)
                if (isset($questionTypeMap[strtolower($questionTypeStr)])) {
                    $questionTypeId = $questionTypeMap[strtolower($questionTypeStr)];
                }
            }
            
            // Use user's multiple_answer selection if available
            $multipleAnswerValue = !empty($userMultipleAnswer) ? $userMultipleAnswer : ($questionData['multiple_answer'] ?? 0);
            
            // If question type is Multiple Choice, set multiple_answer to 1
            // Find the Multiple Choice question type ID dynamically
            $mcqTypeId = isset($questionTypeMap['mcq']) ? $questionTypeMap['mcq'] : (isset($questionTypeMap['multiple choice']) ? $questionTypeMap['multiple choice'] : null);
            if ($mcqTypeId && $questionTypeId == $mcqTypeId) {
                $multipleAnswerValue = 1;
            }
            
            // Create the question
            $question = array(
                'question_type_id'     => $questionTypeId,
                'grade_id'             => $request->get('grade_id'),
                'standard_id'          => $request->get('standard_id'),
                'subject_id'           => $request->get('subject_id'),
                'chapter_id'           => $request->get('chapter_id'),
                'topic_id'             => $request->get('topic_id'),
                'question_title'      => htmlspecialchars($questionData['question_title']),
                'description'          => $questionData['description'] ?? '',
                // 'multiple_answer'      => $multipleAnswerValue,
                'points'               => $questionData['points'] ?? 1,
                'status'               => 1,
                'created_by'           => $user_id,
                'sub_institute_id'     => $sub_institute_id,
                'hint_text'            => $questionData['hint_text'] ?? '',
                'learning_outcome'     => $questionData['learning_outcome'] ?? '',
            );
            
            if($questionTypeId==1){
                $question['multiple_answer'] = 1;
            }else{
                $question['multiple_answer'] = 0;
            }
            
            $question_id = lmsQuestionMasterModel::insertGetId($question);
            
            // Save mapping data
            if (!empty($questionData['mappings'])) {
                foreach ($questionData['mappings'] as $mapping) {
                    // Safety check
                    if (
                        empty($mapping['mapping_type']) ||
                        empty($mapping['mapping_value'])
                    ) {
                        continue;
                    }

                    lmsQuestionMappingModel::insert([
                        'questionmaster_id' => $question_id,
                        'mapping_type_id'   => $mapping['mapping_type'],
                        'mapping_value_id'  => $mapping['mapping_value'],
                        'reasons'           => $mapping['reason'] ?? '',
                    ]);
                }
            }

            // Save answer options if multiple choice
            if (isset($questionData['options']) && !empty($questionData['options'])) {
                foreach ($questionData['options'] as $key => $option) {
                    $answer = array(
                        'question_id'      => $question_id,
                        'answer'           => $option['text'],
                        'feedback'         => $option['feedback'] ?? '',
                        'correct_answer'   => $option['correct'] ?? 0,
                        'created_by'       => $user_id,
                        'sub_institute_id' => $sub_institute_id,
                    );
                    answermasterModel::insert($answer);
                }
            }
            
            $savedQuestions[] = $question_id;
        }
        
        return response()->json([
            'status_code' => 1,
            'message' => 'Questions saved successfully',
            'saved_questions' => $savedQuestions
        ]);
        
    }
    
    /**
     * Generate questions based on mapping settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateQuestions(Request $request)
    {
        $mappingTypes = $request->get('mapping_type', []);
        $mappingValues = $request->get('mapping_value', []);
        $reasons = $request->get('reasons', []);
        $questionsCount = $request->get('questions', []);
        
        $standard = $request->get('standard_id');
        $subject = $request->get('subject_id');
        $chapter = $request->get('chapter_id');
        $topic = $request->get('topic_id');
        
        $generatedQuestions = [];
        
        // Build the prompt based on mapping selections
        $prompt = "Generate " . ($questionsCount[0] ?? 1) . " questions for standard '" . $standard . "' and subject '" . $subject . "' and chapter '" . $chapter . "'";
        
        if ($topic) {
            $prompt .= " and for topic '" . $topic . "'";
        }
        
        // Add mapping information to prompt
        if (!empty($mappingTypes) && !empty($mappingValues)) {
            $prompt .= " with ";
            foreach ($mappingTypes as $index => $typeId) {
                if (isset($mappingValues[$index]) && isset($reasons[$index])) {
                    $prompt .= " mapping type '" . $typeId . "' value '" . $mappingValues[$index] . "' reason '" . $reasons[$index] . "'";
                }
            }
        }
        
        return response()->json([
            'status_code' => 1,
            'message' => 'Questions generated successfully',
            'prompt' => $prompt,
            'questions' => $generatedQuestions
        ]);
    }
    
    /**
     * AI Chat endpoint for question generation
     */
    public function chat(Request $request)
    {
        try {
            $standard = $request->get('standard');
            $subject_id = $request->get('subject_id');
            $chapter_id = $request->get('chapter_id');
            $topic_id = $request->get('topic_id');
            $question_prompt = $request->get('question_prompt');
            $search = $request->get('search');
            $mappings = $request->get('mappings', []);
            
            // Get question type ID to check if "Multiple" (MCQ only) is selected
            $questionTypeId = $request->get('question_type_id');
            $isMultipleType = !empty($questionTypeId) && $questionTypeId == 1;
            $standard_name = getTableFieldFromId('standard', 'name', $standard);
            $subject_name = getTableFieldFromId('sub_std_map','display_name',$subject_id,'subject_id');
            $chapter_name = getTableFieldFromId('chapter_master','chapter_name',$chapter_id);
            // return $subject_name;exit;

            // Generate a unique seed for variety
            $seed = rand(1, 10000);
            
            // Build the prompt - check if we need MCQ only
            if ($isMultipleType) {
                // If "Multiple" is selected, only generate MCQ questions
                $prompt = "Generate unique MCQ questions (Multiple Choice Questions with 4 options) for " .
                    "Standard: " . ($standard_name ?? 'General') .
                    ", Subject: " . ($subject_name ?? 'General') .
                    ", Chapter: " . ($chapter_name ?? 'General');
                
                if ($topic_id) {
                    $prompt .= ", Topic: " . $topic_id;
                }
                
                // Add mapping info to make questions more specific
                if (!empty($mappings)) {
                    foreach ($mappings as $mapping) {
                        if (!empty($mapping['reason'])) {
                            $prompt .= ". Focus on: " . $mapping['reason'];
                        }
                    }
                }
                
                // Add instruction for MCQ only
                $questionCount = !empty($mappings[0]['questions']) ? (int)$mappings[0]['questions'] : 5;
                $prompt .= ". Generate exactly " . $questionCount . " MCQ question(s) ONLY. ";
                $prompt .= "Make each question unique and different from each other. Use this seed for variety: " . $seed . ". ";
                $prompt .= "Return the response as a JSON array of question objects with fields: question, question_type (always 'MCQ'), difficulty (Easy/Medium/Hard), options (array of 4 objects with 'text' and 'correct' boolean fields), correct_answer, and explanation.";
            } else {
                // Original behavior - generate varied question types
                $prompt = "Generate unique, varied questions for " .
                    "Standard: " . ($standard_name ?? 'General') .
                    ", Subject: " . ($subject_name ?? 'General') .
                    ", Chapter: " . ($chapter_name ?? 'General');
                
                if ($topic_id) {
                    $prompt .= ", Topic: " . $topic_id;
                }
                
                // Add mapping info to make questions more specific
                if (!empty($mappings)) {
                    foreach ($mappings as $mapping) {
                        if (!empty($mapping['reason'])) {
                            $prompt .= ". Focus on: " . $mapping['reason'];
                        }
                    }
                }
                
                // Add variety instruction
                $questionCount = !empty($mappings[0]['questions']) ? (int)$mappings[0]['questions'] : 5;
                $prompt .= ". Generate exactly " . $questionCount . " different question(s) that vary in type (MCQ, short answer, long answer, fill in the blanks) and difficulty level. ";
                $prompt .= "Make each question unique and different from each other. Use this seed for variety: " . $seed . ". ";
                $prompt .= "Return the response as a JSON array of question objects with fields: question, question_type (MCQ/ShortAnswer/LongAnswer/FillInBlanks), difficulty (Easy/Medium/Hard), options (array of 4 for MCQ), correct_answer, and explanation. with no extra text want only json array";
            }
            if($request->get('question_type_id')==1){
                $prompt .= " and it must have different types of 4 options which differentiate between them. give me corre t_answer also below the options array.";
            }
            // return $prompt;exit;
            // Call the AI service
            $generatedQuestions = $response = $this->openAIService->generateContent($prompt);
            
            // Parse the AI response
            $questions = $this->parseAIResponse($response);
            
            // If AI service fails, generate fallback questions
            if (empty($questions)) {
                $questions = $this->generateFallbackQuestions($questionCount, $standard, $subject_id, $chapter_id, $topic_id, $seed, $isMultipleType);
            }
            // Decode the JSON data
                $aiData = [];
                if (!empty($generatedQuestions)) {
                $cleanJson = preg_replace('/^```json\s*|\s*```$/m', '', $generatedQuestions);
                    $aiData = json_decode($cleanJson, true);
                }

            return response()->json(['main'=>$response,'ai_response'=>$aiData,'questions'=>$questions]);
            
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Question generation error: ' . $e->getMessage());
            
            // Return fallback questions on error
            $questionsCount = $request->get('question_prompt', '');
            preg_match('/(\d+)/', $questionsCount, $matches);
            $count = isset($matches[0]) ? (int)$matches[0] : 3;
            
            return response()->json($this->generateFallbackQuestions(
                $count,
                $request->get('standard'),
                $request->get('subject_id'),
                $request->get('chapter_id'),
                $request->get('topic_id'),
                rand(1, 10000),
                $isMultipleType
            ));
        }
    }
    
    /**
     * Parse AI response to extract questions
     */
    private function parseAIResponse($response)
    {
        $questions = [];
        
        // Try to extract JSON from the response
        if (is_string($response)) {
            // Find JSON array in response
            if (preg_match('/\[\s*\{/', $response, $match, PREG_OFFSET_CAPTURE)) {
                $jsonStr = substr($response, $match[0][1]);
                // Find the closing bracket
                $depth = 0;
                $endPos = strlen($jsonStr) - 1;
                for ($i = 0; $i < strlen($jsonStr); $i++) {
                    if ($jsonStr[$i] === '{') $depth++;
                    if ($jsonStr[$i] === '}') $depth--;
                    if ($depth === 0) {
                        $endPos = $i;
                        break;
                    }
                }
                $jsonStr = substr($jsonStr, 0, $endPos + 1);
                
                try {
                    $parsed = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                        $questions = $parsed;
                    }
                } catch (\Exception $e) {
                    // Failed to parse
                }
            }
        }
        
        return $questions;
    }
    
    /**
     * Generate fallback questions when AI fails
     */
    private function generateFallbackQuestions($count, $standard, $subject, $chapter, $topic, $seed, $isMultipleType = false)
    {
        // If Multiple type is selected, only use MCQ
        if ($isMultipleType) {
            $questionTypes = ['MCQ'];
        } else {
            $questionTypes = ['MCQ', 'ShortAnswer', 'LongAnswer', 'FillInBlanks'];
        }
        $difficulties = ['Easy', 'Medium', 'Hard'];
        
        // Use seed for randomness
        srand($seed);
        
        $questions = [];
        $topics = [
            'definition and explanation of key concepts',
            'difference between important terms',
            'list the main characteristics',
            'explain with examples',
            'compare and contrast',
            'describe the process',
            'state the significance',
            'analyze the given scenario',
            'evaluate the statement',
            'solve the problem'
        ];
        
        for ($i = 0; $i < $count; $i++) {
            $type = $questionTypes[array_rand($questionTypes)];
            $difficulty = $difficulties[array_rand($difficulties)];
            $topicPrompt = $topics[array_rand($topics)];
            
            $questionText = ucfirst($topicPrompt) . " related to " . ($chapter ?? 'the chapter');
            if ($topic) {
                $questionText .= " in " . $topic;
            }
            
            $question = [
                'question' => ($i + 1) . ". " . $questionText . "?",
                'question_type' => $type,
                'difficulty' => $difficulty,
                'options' => [],
                'correct_answer' => '',
                'explanation' => 'This question tests understanding of key concepts.'
            ];
            
            if ($type === 'MCQ') {
                $question['options'] = [
                    ['text' => 'Option A - Correct answer for: ' . substr($questionText, 0, 30), 'correct' => true],
                    ['text' => 'Option B - ' . substr($questionText, 0, 30), 'correct' => false],
                    ['text' => 'Option C - ' . substr($questionText, 0, 30), 'correct' => false],
                    ['text' => 'Option D - ' . substr($questionText, 0, 30), 'correct' => false]
                ];
                $question['correct_answer'] = 'Option A';
            } else {
                $question['correct_answer'] = 'Sample answer for: ' . $questionText;
            }
            
            $questions[] = $question;
        }
        
        // Reset random seed
        srand();
        
        return $questions;
    }
}
