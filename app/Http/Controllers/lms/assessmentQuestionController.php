<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questiontypeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class assessmentQuestionController extends Controller
{
    /**
     * Store questions from assessment preview
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse|Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        
        // Get the generated questions from the request
        $generatedQuestionsJson = $request->get('generated_questions', '[]');
        
        // Decode JSON string if it's a string
        if (is_string($generatedQuestionsJson)) {
            $generatedQuestions = json_decode($generatedQuestionsJson, true);
        } else {
            $generatedQuestions = $generatedQuestionsJson;
        }
        
        if (empty($generatedQuestions)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'No questions to save'
            ]);
        }
        
        $savedQuestions = [];
        
        foreach ($generatedQuestions as $questionData) {
            // Create the question
            $question = array(
                'question_type_id'     => $questionData['question_type_id'] ?? 1,
                'grade_id'             => $request->get('grade_id'),
                'standard_id'          => $request->get('standard_id'),
                'subject_id'           => $request->get('subject_id'),
                'chapter_id'           => $request->get('chapter_id'),
                'topic_id'             => $request->get('topic_id'),
                'question_title'      => htmlspecialchars($questionData['question_title']),
                'description'          => $questionData['description'] ?? '',
                'multiple_answer'      => $questionData['multiple_answer'] ?? 0,
                'points'               => $questionData['points'] ?? 1,
                'status'               => 1,
                'created_by'           => $user_id,
                'sub_institute_id'     => $sub_institute_id,
                'hint_text'            => $questionData['hint_text'] ?? '',
                'learning_outcome'     => $questionData['learning_outcome'] ?? '',
            );
            
            $question_id = lmsQuestionMasterModel::insertGetId($question);
            
            // Save mapping data
            // Save mapping data (FIXED)
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
            'mapping_type_id'   => $mapping['mapping_type'],   // FIXED
            'mapping_value_id'  => $mapping['mapping_value'],  // FIXED
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
        
        // Call the chat endpoint to generate questions
        $path = route('chat');
        $data = array(
            'standard' => $standard,
            'subject_id' => $subject,
            'chapter_id' => $chapter,
            'topic_id' => $topic ?? '',
            'question_prompt' => $prompt,
            'search' => 'question'
        );
        
        // Make AJAX call to generate questions
        // This will be called from the frontend
        
        return response()->json([
            'status_code' => 1,
            'message' => 'Questions generated successfully',
            'prompt' => $prompt,
            'questions' => $generatedQuestions
        ]);
    }
    public function chat(Request $request)
{
    try {
        $standard = $request->get('standard');
        $subject_id = $request->get('subject_id');
        $chapter_id = $request->get('chapter_id');
        $topic_id = $request->get('topic_id');
        $question_prompt = $request->get('question_prompt');
        $search = $request->get('search');
        
        // Your AI integration logic here
        // This could be calling OpenAI API or your custom AI service
        
        // Example mock response for testing
        $questions = [
            "What is the capital of France?",
            "Explain the water cycle.",
            "Who wrote Romeo and Juliet?"
        ];
        
        return response()->json($questions);
        
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}
