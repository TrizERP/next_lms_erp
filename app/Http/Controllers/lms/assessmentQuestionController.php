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

class assessmentQuestionController extends Controller
{
    protected $openAIService;
    
    // Difficulty weights for distribution
    const DIFFICULTY_WEIGHTS = [
        'Easy' => 0.30,
        'Medium' => 0.40,
        'Hard' => 0.30
    ];
    
    // Marks based on difficulty
    const MARKS_BY_DIFFICULTY = [
        'Easy' => 1,
        'Medium' => 2,
        'Hard' => 3
    ];
    
    // Mapping value to difficulty mapping
    const BLOOM_TAXONOMY_LEVELS = [
        'remember' => 'Easy',
        'understand' => 'Easy',
        'apply' => 'Medium',
        'analyze' => 'Medium',
        'evaluate' => 'Hard',
        'create' => 'Hard'
    ];
    
    const QUESTION_DEPTH_LEVELS = [
        'simple' => 'Easy',
        'basic' => 'Easy',
        'intermediate' => 'Medium',
        'moderate' => 'Medium',
        'complex' => 'Hard',
        'advanced' => 'Hard'
    ];
    
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    
    /**
     * Preview distribution of questions based on total count
     * Also returns generated prompt if requested
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function previewDistribution(Request $request)
    {
        try {
            $totalQuestions = (int) $request->get('total_questions', 10);
            $questionTypeId = $request->get('question_type_id');
            $standardId = $request->get('standard_id');
            $subjectId = $request->get('subject_id');
            $chapterId = $request->get('chapter_id');
            $topicId = $request->get('topic_id');
            $generatePrompt = $request->get('generate_prompt', false);
            
            // Validate minimum questions
            if ($totalQuestions < 1 || $totalQuestions > 100) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Total questions must be between 1 and 100'
                ]);
            }
            
            // Get available mappings from database
            $mappings = $this->getAvailableMappings($standardId, $subjectId, $chapterId, $topicId);
            
            // Calculate distribution
            $distribution = $this->distributeQuestionsByDifficulty($totalQuestions, $mappings);
            
            // Calculate marks for each distribution
            $distribution = $this->calculateMarksForDistribution($distribution);
            
            // Calculate total marks
            $totalMarks = array_sum(array_column($distribution, 'total_marks'));
            
            // Build response
            $response = [
                'status_code' => 1,
                'distribution' => $distribution,
                'total_questions' => $totalQuestions,
                'total_marks' => $totalMarks,
                'mappings_found' => count($mappings)
            ];
            
            // If prompt generation is requested
            if ($generatePrompt) {
                // Get question type name
                $questionTypeName = 'MCQ';
                if (!empty($questionTypeId)) {
                    $questionTypeData = DB::table('question_type_master')->where('id', $questionTypeId)->first();
                    if ($questionTypeData) {
                        $questionTypeName = $questionTypeData->question_type;
                    }
                }
                
                // Generate the prompt
                $prompt = $this->generateEnhancedPrompt(
                    $totalQuestions,
                    $distribution,
                    $questionTypeName,
                    $standardId ?? 'General',
                    $subjectId ?? 'General',
                    $chapterId ?? 'General',
                    $topicId
                );
                
                $response['prompt'] = $prompt;
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            \Log::error('Preview distribution error: ' . $e->getMessage());
            return response()->json([
                'status_code' => 0,
                'message' => 'Error calculating distribution: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Auto-detect available mappings from database
     */
    private function getAvailableMappings($standardId = null, $subjectId = null, $chapterId = null, $topicId = null)
    {
        $parentMappings = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($chapterId, $topicId) {
                $q->where('globally', '=', 1)
                  ->orWhere('chapter_id', $chapterId ?? 0);
            })
            ->orderBy('id')
            ->get();
        
        $availableMappings = [];
        
        foreach ($parentMappings as $parent) {
            $childValues = DB::table('lms_mapping_type')
                ->where('status', '=', 1)
                ->where('parent_id', '=', $parent->id)
                ->orderBy('id')
                ->get();
            
            if ($childValues->isNotEmpty()) {
                $availableMappings[] = [
                    'type_id' => $parent->id,
                    'type_name' => $parent->name,
                    'values' => $childValues->map(function($child) {
                        return [
                            'value_id' => $child->id,
                            'value_name' => $child->name,
                            'difficulty' => $this->getDifficultyFromMappingValue($child->name)
                        ];
                    })->toArray()
                ];
            }
        }
        
        if (empty($availableMappings)) {
            $availableMappings = $this->getDefaultMappings();
        }
        
        return $availableMappings;
    }
    
    /**
     * Get default mappings when none found
     */
    private function getDefaultMappings()
    {
        return [
            [
                'type_id' => 1,
                'type_name' => 'Bloom\'s Taxonomy',
                'values' => [
                    ['value_id' => 1, 'value_name' => 'Remember', 'difficulty' => 'Easy'],
                    ['value_id' => 2, 'value_name' => 'Understand', 'difficulty' => 'Easy'],
                    ['value_id' => 3, 'value_name' => 'Apply', 'difficulty' => 'Medium'],
                    ['value_id' => 4, 'value_name' => 'Analyze', 'difficulty' => 'Medium'],
                    ['value_id' => 5, 'value_name' => 'Evaluate', 'difficulty' => 'Hard'],
                    ['value_id' => 6, 'value_name' => 'Create', 'difficulty' => 'Hard']
                ]
            ]
        ];
    }
    
    /**
     * Determine difficulty from mapping value name
     */
    private function getDifficultyFromMappingValue($valueName)
    {
        $lowerName = strtolower(trim($valueName));
        
        if (isset(self::BLOOM_TAXONOMY_LEVELS[$lowerName])) {
            return self::BLOOM_TAXONOMY_LEVELS[$lowerName];
        }
        
        if (isset(self::QUESTION_DEPTH_LEVELS[$lowerName])) {
            return self::QUESTION_DEPTH_LEVELS[$lowerName];
        }
        
        return 'Easy';
    }
    
    /**
     * Distribute questions based on difficulty weights
     */
    private function distributeQuestionsByDifficulty($totalQuestions, $mappings)
    {
        $distribution = [];
        
        // Calculate base distribution by difficulty
        $easyCount = (int) ceil($totalQuestions * self::DIFFICULTY_WEIGHTS['Easy']);
        $mediumCount = (int) ceil($totalQuestions * self::DIFFICULTY_WEIGHTS['Medium']);
        $hardCount = $totalQuestions - $easyCount - $mediumCount;
        
        if ($hardCount < 0) {
            $hardCount = 0;
            $mediumCount = $totalQuestions - $easyCount;
        }
        
        // Collect all mapping values with their difficulties
        $allValues = [];
        foreach ($mappings as $mapping) {
            foreach ($mapping['values'] as $value) {
                $allValues[] = [
                    'type_id' => $mapping['type_id'],
                    'type_name' => $mapping['type_name'],
                    'value_id' => $value['value_id'],
                    'value_name' => $value['value_name'],
                    'difficulty' => $value['difficulty']
                ];
            }
        }
        
        if (empty($allValues)) {
            $allValues = [
                ['type_id' => 1, 'type_name' => 'Bloom\'s Taxonomy', 'value_id' => 1, 'value_name' => 'Remember', 'difficulty' => 'Easy'],
                ['type_id' => 1, 'type_name' => 'Bloom\'s Taxonomy', 'value_id' => 2, 'value_name' => 'Understand', 'difficulty' => 'Easy'],
                ['type_id' => 1, 'type_name' => 'Bloom\'s Taxonomy', 'value_id' => 3, 'value_name' => 'Apply', 'difficulty' => 'Medium'],
                ['type_id' => 1, 'type_name' => 'Bloom\'s Taxonomy', 'value_id' => 4, 'value_name' => 'Analyze', 'difficulty' => 'Medium'],
                ['type_id' => 1, 'type_name' => 'Bloom\'s Taxonomy', 'value_id' => 5, 'value_name' => 'Evaluate', 'difficulty' => 'Hard'],
                ['type_id' => 1, 'type_name' => 'Bloom\'s Taxonomy', 'value_id' => 6, 'value_name' => 'Create', 'difficulty' => 'Hard']
            ];
        }
        
        $easyValues = array_filter($allValues, fn($v) => $v['difficulty'] === 'Easy');
        $mediumValues = array_filter($allValues, fn($v) => $v['difficulty'] === 'Medium');
        $hardValues = array_filter($allValues, fn($v) => $v['difficulty'] === 'Hard');
        
        $easyValues = array_values($easyValues);
        $mediumValues = array_values($mediumValues);
        $hardValues = array_values($hardValues);
        
        $distribution = array_merge($distribution, $this->distributeToValues($easyValues, $easyCount, 'Easy'));
        $distribution = array_merge($distribution, $this->distributeToValues($mediumValues, $mediumCount, 'Medium'));
        $distribution = array_merge($distribution, $this->distributeToValues($hardValues, $hardCount, 'Hard'));
        
        return $distribution;
    }
    
    /**
     * Distribute questions among mapping values
     */
    private function distributeToValues($values, $count, $difficulty)
    {
        $result = [];
        
        if (empty($values) || $count === 0) {
            return $result;
        }
        
        $countPerValue = (int) floor($count / count($values));
        $remainder = $count % count($values);
        
        $questionNum = 1;
        foreach ($values as $index => $value) {
            $questionsForThis = $countPerValue;
            if ($index < $remainder) {
                $questionsForThis++;
            }
            
            if ($questionsForThis > 0) {
                $result[] = [
                    'mapping_type_id' => $value['type_id'],
                    'mapping_type_name' => $value['type_name'],
                    'mapping_value_id' => $value['value_id'],
                    'mapping_value_name' => $value['value_name'],
                    'difficulty' => $difficulty,
                    'questions' => $questionsForThis,
                    'start_num' => $questionNum,
                    'end_num' => $questionNum + $questionsForThis - 1
                ];
                $questionNum += $questionsForThis;
            }
        }
        
        return $result;
    }
    
    /**
     * Calculate marks for distribution
     */
    private function calculateMarksForDistribution($distribution)
    {
        foreach ($distribution as &$item) {
            $item['marks'] = self::MARKS_BY_DIFFICULTY[$item['difficulty']];
            $item['total_marks'] = $item['questions'] * $item['marks'];
        }
        return $distribution;
    }
    
    /**
     * Calculate marks for a single mapping value
     */
    private function calculateMarks($mappingType, $mappingValue)
    {
        $difficulty = $this->getDifficultyFromMappingValue($mappingValue);
        return self::MARKS_BY_DIFFICULTY[$difficulty] ?? 1;
    }
    
    /**
     * Generate enhanced AI prompt with distribution details
     */
    private function generateEnhancedPrompt($totalQuestions, $distribution, $questionType, $standard, $subject, $chapter, $topic = null)
    {
        $prompt = "Generate {$totalQuestions} {$questionType} questions for:\n";
        $prompt .= "- Standard: {$standard}\n";
        $prompt .= "- Subject: {$subject}\n";
        $prompt .= "- Chapter: {$chapter}\n";
        if ($topic) {
            $prompt .= "- Topic: {$topic}\n";
        }
        
        $prompt .= "\nDistribution Requirements:\n";
        
        foreach ($distribution as $item) {
            $focusArea = $this->getFocusAreaForLevel($item['mapping_value_name']);
            $prompt .= "- {$item['mapping_type_name']} - {$item['mapping_value_name']}: " .
                      "{$item['questions']} question(s), " .
                      self::MARKS_BY_DIFFICULTY[$item['difficulty']] . " mark(s) each - Focus: {$focusArea}\n";
        }
        
        $prompt .= "\nTotal: {$totalQuestions} questions\n";
        
        $prompt .= "\nReturn the response as a JSON array of question objects with fields: ";
        $prompt .= "question, question_type (always '{$questionType}'), difficulty (Easy/Medium/Hard), ";
        $prompt .= "mapping_type_id, mapping_value_id, marks, correct_answer, and explanation.";
        
        if (strtolower($questionType) === 'mcq' || strtolower($questionType) === 'multiple choice') {
            $prompt .= " For MCQ, include options array with 4 objects having 'text' and 'correct' boolean fields.";
        }
        
        return $prompt;
    }
    
    /**
     * Get focus area description for each mapping level
     */
    private function getFocusAreaForLevel($levelName)
    {
        $focusAreas = [
            'Remember' => 'Recall definitions, facts, and basic concepts',
            'Understand' => 'Explain and summarize key ideas',
            'Apply' => 'Use knowledge in new situations',
            'Analyze' => 'Compare, contrast, and examine relationships',
            'Evaluate' => 'Justify and critique decisions',
            'Create' => 'Design and develop original solutions',
            'Simple' => 'Basic recall and identification',
            'Basic' => 'Simple understanding of concepts',
            'Intermediate' => 'Application of procedures',
            'Moderate' => 'Analysis of complex situations',
            'Complex' => 'Evaluation and synthesis',
            'Advanced' => 'Creative problem solving'
        ];
        
        return $focusAreas[$levelName] ?? 'General understanding';
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
     * Updated to support simplified workflow: user only selects question type + total questions
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
            
            // Get question type ID
            $questionTypeId = $request->get('question_type_id');
            
            // Get question type name
            $questionTypeName = 'MCQ';
            if (!empty($questionTypeId)) {
                $questionTypeData = DB::table('question_type_master')->where('id', $questionTypeId)->first();
                if ($questionTypeData) {
                    $questionTypeName = $questionTypeData->question_type;
                }
            }
            
            // Check if simplified mode (total_questions provided)
            $totalQuestions = $request->get('total_questions');
            $isSimplifiedMode = !empty($totalQuestions) && is_numeric($totalQuestions);
            
            // Check for custom prompt (edited by user)
            $customPrompt = $request->get('custom_prompt');
            
            // Generate a unique seed for variety
            $seed = rand(1, 10000);
            
            // Use simplified workflow if total_questions is provided
            if ($isSimplifiedMode) {
                $totalQuestions = (int) $totalQuestions;
                
                // Validate
                if ($totalQuestions < 1 || $totalQuestions > 100) {
                    return response()->json([
                        'status_code' => 0,
                        'message' => 'Total questions must be between 1 and 100'
                    ]);
                }
                
                // Auto-detect available mappings
                $availableMappings = $this->getAvailableMappings(
                    $request->get('standard_id'),
                    $request->get('subject_id'),
                    $request->get('chapter_id'),
                    $topic_id
                );
                
                // Calculate distribution
                $distribution = $this->distributeQuestionsByDifficulty($totalQuestions, $availableMappings);
                $distribution = $this->calculateMarksForDistribution($distribution);
                
                // Use custom prompt if provided, otherwise generate one
                if (!empty($customPrompt)) {
                    $prompt = $customPrompt;
                } else {
                    // Generate enhanced prompt
                    $prompt = $this->generateEnhancedPrompt(
                        $totalQuestions,
                        $distribution,
                        $questionTypeName,
                        $standard ?? 'General',
                        $subject_id ?? 'General',
                        $chapter_id ?? 'General',
                        $topic_id
                    );
                }
                
                // Add seed for variety if not already in prompt
                if (empty($customPrompt)) {
                    $prompt .= " Use this seed for variety: " . $seed . ".";
                }
                
                $questionCount = $totalQuestions;
                
            } else {
                // Original workflow - use mappings from frontend
                $isMultipleType = !empty($questionTypeId) && $questionTypeId == 1;
                
                // Build the prompt - check if we need MCQ only
                if ($isMultipleType) {
                    $prompt = "Generate unique MCQ questions (Multiple Choice Questions with 4 options) for " .
                        "Standard: " . ($standard ?? 'General') .
                        ", Subject: " . ($subject_id ?? 'General') .
                        ", Chapter: " . ($chapter_id ?? 'General');
                    
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
                        "Standard: " . ($standard ?? 'General') .
                        ", Subject: " . ($subject_id ?? 'General') .
                        ", Chapter: " . ($chapter_id ?? 'General');
                    
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
                    $prompt .= "Return the response as a JSON array of question objects with fields: question, question_type (MCQ/ShortAnswer/LongAnswer/FillInBlanks), difficulty (Easy/Medium/Hard), options (array of 4 for MCQ), correct_answer, and explanation.";
                }
                if($questionTypeId == 1){
                    $prompt .= " and it must have different types of 4 options which differentiate between them. give me corre t_answer also below the options array.";
                }
                
                $distribution = null;
            }
            
            // Call the AI service
            $generatedQuestions = $response = $this->openAIService->generateContent($prompt);
            
            // Parse the AI response
            $questions = $this->parseAIResponse($response);
            
            // If AI service fails, generate fallback questions
            if (empty($questions)) {
                $questions = $this->generateFallbackQuestions(
                    $questionCount ?? 5, 
                    $standard, 
                    $subject_id, 
                    $chapter_id, 
                    $topic_id, 
                    $seed, 
                    ($questionTypeId ?? 1) == 1
                );
            }
            
            // If in simplified mode, add distribution info to each question
            if ($isSimplifiedMode && !empty($distribution)) {
                $questions = $this->attachDistributionToQuestions($questions, $distribution);
            }
            
            // Decode the JSON data
            $aiData = [];
            if (!empty($generatedQuestions)) {
                $cleanJson = preg_replace('/^```json\s*|\s*```$/m', '', $generatedQuestions);
                $aiData = json_decode($cleanJson, true);
            }

            return response()->json([
                'main'=>$response,
                'ai_response'=>$aiData,
                'questions'=>$questions,
                'distribution' => $distribution ?? null,
                'total_questions' => $questionCount ?? null,
                'total_marks' => $isSimplifiedMode ? array_sum(array_column($distribution, 'total_marks')) : null
            ]);
            
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
                ($request->get('question_type_id') ?? 1) == 1
            ));
        }
    }
    
    /**
     * Attach distribution information to generated questions
     */
    private function attachDistributionToQuestions($questions, $distribution)
    {
        $result = [];
        $questionIndex = 0;
        
        foreach ($distribution as $distItem) {
            for ($i = 0; $i < $distItem['questions']; $i++) {
                if (isset($questions[$questionIndex])) {
                    $questions[$questionIndex]['mapping_type_id'] = $distItem['mapping_type_id'];
                    $questions[$questionIndex]['mapping_value_id'] = $distItem['mapping_value_id'];
                    $questions[$questionIndex]['mapping_type_name'] = $distItem['mapping_type_name'];
                    $questions[$questionIndex]['mapping_value_name'] = $distItem['mapping_value_name'];
                    $questions[$questionIndex]['difficulty'] = $distItem['difficulty'];
                    $questions[$questionIndex]['marks'] = $distItem['marks'];
                    $questions[$questionIndex]['points'] = $distItem['marks'];
                    
                    // Create mappings array for store method
                    $questions[$questionIndex]['mappings'] = [[
                        'mapping_type' => $distItem['mapping_type_id'],
                        'mapping_value' => $distItem['mapping_value_id'],
                        'reason' => $distItem['mapping_value_name'] . ' - ' . $distItem['difficulty']
                    ]];
                    
                    $result[] = $questions[$questionIndex];
                    $questionIndex++;
                }
            }
        }
        
        return $result;
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
