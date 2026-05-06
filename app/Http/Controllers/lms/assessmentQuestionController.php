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
use function App\Helpers\neo4jCreateNode;
use function App\Helpers\neo4jCreateRelationship;

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
        'Hard' => 3,
        'Mixed' => 1
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
            
            $baseMappings = $this->getAvailableMappings($standardId, $subjectId, $chapterId, $topicId);
            $selectedMappings = $this->getSelectedMappingsFromRequest($request);
            $mappings = array_merge($baseMappings, $selectedMappings);

            $distribution = $this->buildDistributionWithSelectedMappings(
                $totalQuestions,
                $baseMappings,
                $selectedMappings
            );
            $distribution = $this->appendPedagogyToDistribution($distribution);
            // Additional mapping rows are attached to the same generated questions, so
            // they should not increase the requested question count or total marks.
            $primaryDistribution = array_values(array_filter($distribution, fn($item) => empty($item['additional_mapping'])));
            $totalDistributedQuestions = !empty($selectedMappings)
                ? $totalQuestions
                : array_sum(array_column($primaryDistribution, 'questions'));
            $totalMarks = !empty($selectedMappings)
                ? array_sum(array_column($primaryDistribution, 'total_marks'))
                : array_sum(array_column($primaryDistribution, 'total_marks'));
            
            // Build response
            $response = [
                'status_code' => 1,
                'distribution' => $distribution,
                'total_questions' => $totalDistributedQuestions,
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
                $names = $this->getName($standardId, $subjectId, $chapterId, $topicId);

                // Generate the prompt
                $prompt = $this->generateEnhancedPrompt(
                    $totalDistributedQuestions,
                    $distribution,
                    $questionTypeName,
                    $names['standard'],
                    $names['subject'],
                    $names['chapter'],
                    $names['topic']
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
     * Build mapping distribution data from selected mapping rows.
     */
    private function getSelectedMappingsFromRequest(Request $request)
    {
        if (!$request->boolean('advanced_mapping')) {
            return [];
        }

        $mappingTypes = (array) $request->get('mapping_type', []);
        $mappingValues = (array) $request->get('mapping_value', []);
        $reasons = (array) $request->get('reasons', []);
        $selectedMappings = [];

        foreach ($mappingTypes as $index => $typeId) {
            $valueId = $mappingValues[$index] ?? null;
            if (empty($typeId) || empty($valueId)) {
                continue;
            }

            $mappingType = DB::table('lms_mapping_type')
                ->where('id', $typeId)
                ->where('status', 1)
                ->first();
            $mappingValue = DB::table('lms_mapping_type')
                ->where('id', $valueId)
                ->where('parent_id', $typeId)
                ->where('status', 1)
                ->first();

            if (!$mappingType || !$mappingValue) {
                continue;
            }

            if (!isset($selectedMappings[$typeId])) {
                $selectedMappings[$typeId] = [
                    'type_id' => $mappingType->id,
                    'type_name' => $mappingType->name,
                    'values' => []
                ];
            }

            $selectedMappings[$typeId]['values'][] = [
                'value_id' => $mappingValue->id,
                'value_name' => $mappingValue->name,
                'difficulty' => $this->getDifficultyFromMappingValue($mappingValue->name),
                'reason' => $reasons[$index] ?? '',
                'selected' => true
            ];
        }

        return array_values($selectedMappings);
    }

    /**
     * Build the question distribution. Selected mappings replace the auto distribution.
     */
    private function buildDistributionWithSelectedMappings($totalQuestions, $baseMappings, $selectedMappings)
    {
        if (!empty($selectedMappings)) {
            return $this->calculateMarksForDistribution(
                $this->distributeQuestionsByDifficulty($totalQuestions, $selectedMappings, true)
            );
        }

        return $this->calculateMarksForDistribution(
            $this->distributeQuestionsByDifficulty($totalQuestions, $baseMappings)
        );
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
     * @param bool $isSelectedMappings - When true, attach all selected mappings to every question
     */
    private function distributeQuestionsByDifficulty($totalQuestions, $mappings, $isSelectedMappings = false)
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
                    'difficulty' => $value['difficulty'],
                    'reason' => $value['reason'] ?? '',
                    'selected' => $value['selected'] ?? false
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

        // For selected mappings, create a single distribution item with all selected mappings attached to every question
        if (collect($allValues)->contains(fn($value) => !empty($value['selected'])) || $isSelectedMappings) {
            // Get all selected mappings (or all mappings if isSelectedMappings is true)
            $selectedValues = $isSelectedMappings ? $allValues : array_filter($allValues, fn($value) => !empty($value['selected']));
            $selectedValues = array_values($selectedValues);
            
            if (empty($selectedValues)) {
                return $this->distributeSelectedValues($allValues, $totalQuestions);
            }

            // Create one distribution item that includes all selected mappings,
            // and this will be attached to all questions
            $distribution[] = [
                'mapping_type_id' => null, // Multiple types
                'mapping_type_name' => 'Selected Mappings',
                'mapping_value_id' => null,
                'mapping_value_name' => null,
                'difficulty' => 'Mixed',
                'questions' => $totalQuestions,
                'start_num' => 1,
                'end_num' => $totalQuestions,
                'reason' => '',
                'selected' => true,
                'all_selected_mappings' => $selectedValues // Store all selected mappings to attach to each question
            ];
            
            return $distribution;
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
     * Distribute all questions across the selected mapping values.
     */
    private function distributeSelectedValues($values, $totalQuestions)
    {
        $result = [];
        $values = array_values($values);

        if (empty($values)) {
            return $result;
        }

        $countPerValue = (int) floor($totalQuestions / count($values));
        $remainder = $totalQuestions % count($values);
        $questionNum = 1;

        foreach ($values as $index => $value) {
            $questionsForThis = max(1, $countPerValue + ($index < $remainder ? 1 : 0));

            $result[] = [
                'mapping_type_id' => $value['type_id'],
                'mapping_type_name' => $value['type_name'],
                'mapping_value_id' => $value['value_id'],
                'mapping_value_name' => $value['value_name'],
                'difficulty' => $value['difficulty'],
                'questions' => $questionsForThis,
                'start_num' => (($index % $totalQuestions) + 1),
                'end_num' => (($index % $totalQuestions) + 1),
                'reason' => $value['reason'] ?? '',
                'selected' => true
            ];
            $questionNum += $questionsForThis;
        }

        return $result;
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
                    'end_num' => $questionNum + $questionsForThis - 1,
                    'reason' => $value['reason'] ?? ''
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
            $item['marks'] = self::MARKS_BY_DIFFICULTY[$item['difficulty'] ?? 'Easy'] ?? 1;
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
     * Add Pedagogy mapping rows into the distribution preview/prompt when absent.
     */
    private function appendPedagogyToDistribution($distribution)
    {
        $pedagogyTypeId = 3105;

        if ($this->distributionHasMappingType($distribution, $pedagogyTypeId)) {
            return $distribution;
        }

        $pedagogyTitle = DB::table('lms_mapping_type')
            ->where('id', $pedagogyTypeId)
            ->where('status', 1)
            ->value('name');

        $pedagogyValue = DB::table('lms_mapping_type')
            ->where('parent_id', $pedagogyTypeId)
            ->where('status', 1)
            ->where('globally', 1)
            ->orderBy('id')
            ->first(['id', 'name']);

        if (empty($pedagogyTitle) || empty($pedagogyValue)) {
            return $distribution;
        }

        $distribution[] = [
            'mapping_type_id' => $pedagogyTypeId,
            'mapping_type_name' => $pedagogyTitle,
            'mapping_value_id' => $pedagogyValue->id,
            'mapping_value_name' => $pedagogyValue->name,
            'difficulty' => 'Mixed',
            'questions' => 0,
            'start_num' => null,
            'end_num' => null,
            'marks' => 0,
            'total_marks' => 0,
            'reason' => $pedagogyValue->name,
            'additional_mapping' => true,
            'attach_to_all' => true
        ];

        return $distribution;
    }

    private function distributionHasMappingType($distribution, $mappingTypeId)
    {
        foreach ($distribution ?? [] as $item) {
            if (($item['mapping_type_id'] ?? null) == $mappingTypeId) {
                return true;
            }

            if (!empty($item['all_selected_mappings']) && is_array($item['all_selected_mappings'])) {
                foreach ($item['all_selected_mappings'] as $selectedMapping) {
                    if (($selectedMapping['type_id'] ?? null) == $mappingTypeId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
    
    /**
     * Generate enhanced AI prompt with distribution details
     */
    private function generateEnhancedPrompt($totalQuestions, $distribution, $questionType, $standard, $subject, $chapter, $topic = null)
    {
        $prompt = "Generate {$totalQuestions} {$questionType} CBSE questions for:\n";
        $prompt .= "- Standard: {$standard}\n";
        $prompt .= "- Subject: {$subject}\n";
        $prompt .= "- Chapter: {$chapter}\n";
        if ($topic) {
            $prompt .= "- Topic: {$topic}\n";
        }
        
        $primaryDistribution = array_values(array_filter($distribution, fn($item) => empty($item['additional_mapping'])));
        $additionalMappings = array_values(array_filter($distribution, fn($item) => !empty($item['additional_mapping'])));
        $hasSelectedDistribution = collect($primaryDistribution)->contains(fn($item) => !empty($item['selected']));

        $prompt .= "\nDistribution Requirements:\n";
        
        foreach ($primaryDistribution as $item) {
            if (!empty($item['all_selected_mappings']) && is_array($item['all_selected_mappings'])) {
                foreach ($item['all_selected_mappings'] as $selectedMapping) {
                    $focusArea = !empty($selectedMapping['reason'])
                        ? $selectedMapping['reason']
                        : $this->getFocusAreaForLevel($selectedMapping['value_name'] ?? null);
                    $prompt .= "- {$selectedMapping['type_name']} - {$selectedMapping['value_name']} - Focus: {$focusArea}\n";
                }
                continue;
            }

            $focusArea = $this->getFocusAreaForLevel($item['mapping_value_name'] ?? null);
            $prompt .= "- {$item['mapping_type_name']} - {$item['mapping_value_name']} - Focus: {$focusArea}\n";
        }

        if ($hasSelectedDistribution) {
            $prompt .= "Use the selected mapping rows as tags on the same generated set; do not generate extra items for additional mapping rows.\n";
        }

        if (!empty($additionalMappings)) {
            foreach ($additionalMappings as $item) {
                $focusArea = !empty($item['reason']) ? $item['reason'] : $this->getFocusAreaForLevel($item['mapping_value_name']);
                $attachInstruction = !empty($item['attach_to_all'])
                    ? 'Apply to every question.'
                    : 'Do not generate extra items for this mapping.';
                $prompt .= "- {$item['mapping_type_name']} - {$item['mapping_value_name']} - Focus: {$focusArea}. {$attachInstruction}\n";
            }
        }

        $prompt .= "\nReturn the response as a JSON array of question objects with fields: ";
        $prompt .= "question, question_type (always '{$questionType}'), correct_answer, and explanation.";
        
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
     * Get Text from pass IDs
     */
    public function getName($standard_id = null, $subject_id = null, $chapter_id = null, $topic_id = null)
    {
        return [
            'standard' => DB::table('standard')->where('id', $standard_id)->value('name'),
            'subject'  => DB::table('subject')->where('id', $subject_id)->value('subject_name'),
            'chapter'  => DB::table('chapter_master')->where('id', $chapter_id)->value('chapter_name'),
            'topic'    => DB::table('topic_master')->where('id', $topic_id)->value('name'),
        ];
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
                'question_title'      => $questionData['question_title'],
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

            try {


    neo4jCreateNode(
        'Question',
        ['qId' => (int)$question_id],
        [
            'question_title' => $questionData['question_title'],
            'chapter_id' => (int)$request->get('chapter_id'),
            'displayLabel' => 'Question:' . $questionData['question_title'],
            'standard_id' => (int)$request->get('standard_id'),
            'sub_institute_id' => (int)$sub_institute_id,
            'question_type_id' => (int)$questionTypeId,
            'points' => 1
        ]
    );

    \Log::info('Node Created');

    neo4jCreateRelationship(
        'Question',
        ['qId' => (int)$question_id],
        'BELONGS_TO',
        'Chapter',
        ['chId' => (int)$request->get('chapter_id')]
    );

    \Log::info('Relationship Created');

} catch (\Throwable $e) {

    \Log::error('❌ Neo4j Error', [
        'message' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}
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
                
                $baseMappings = $this->getAvailableMappings(
                    $request->get('standard_id'),
                    $request->get('subject_id'),
                    $request->get('chapter_id'),
                    $topic_id
                );
                $selectedMappings = $this->getSelectedMappingsFromRequest($request);

                $distribution = $this->buildDistributionWithSelectedMappings(
                    $totalQuestions,
                    $baseMappings,
                    $selectedMappings
                );
                $distribution = $this->appendPedagogyToDistribution($distribution);
                $primaryDistribution = array_values(array_filter($distribution, fn($item) => empty($item['additional_mapping'])));
                $distributedQuestionCount = !empty($selectedMappings)
                    ? $totalQuestions
                    : array_sum(array_column($primaryDistribution, 'questions'));
                $totalMarks = !empty($selectedMappings)
                    ? array_sum(array_column($primaryDistribution, 'total_marks'))
                    : array_sum(array_column($primaryDistribution, 'total_marks'));
                
                $names = $this->getName($standard, $subject_id, $chapter_id, $topic_id);
                
                // Use custom prompt if provided, otherwise generate one
                if (!empty($customPrompt)) {
                    $prompt = $customPrompt;
                } else {
                    // Generate enhanced prompt
                    $prompt = $this->generateEnhancedPrompt(
                        $distributedQuestionCount,
                        $distribution,
                        $questionTypeName,
                        $names['standard'],
                        $names['subject'],
                        $names['chapter'],
                        $names['topic']
                    );
                }
                
                // Add seed for variety if not already in prompt
                if (empty($customPrompt)) {
                    $prompt .= " Use this seed for variety: " . $seed . ".";
                }
                // added by uma fo 28-04-2026 for mcq 
                    if($questionTypeId == 1){
                        $prompt .= "and it must have different types of 4 options which differentiate between them. give me corret_answer also below the options array. Each option must have 'text' (string) and 'correct' (boolean: true/false). Make options truly distinct from each other.";
                    }
                $questionCount = $distributedQuestionCount;
                
            } else {
                // Original workflow - use mappings from frontend
                $isMultipleType = !empty($questionTypeId) && $questionTypeId == 1;
                $names = $this->getName($standard, $subject_id, $chapter_id, $topic_id);
                // Build the prompt - check if we need MCQ only
                if ($isMultipleType) {
                    $prompt = "Generate unique MCQ questions (Multiple Choice Questions with 4 options) for " .
                        "Standard: " . $names['standard'] .
                        ", Subject: " . $names['subject'] .
                        ", Chapter: " . $names['chapter'];
                    
                    if ($topic_id) {
                        $prompt .= ", Topic: " . $names['topic'];
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
                    $prompt .= "Return the response as a JSON array of question objects with fields: question, question_type (always 'MCQ'), options (array of 4 objects with 'text' and 'correct' boolean fields), correct_answer, and explanation.";
                    // added by uma fo 28-04-2026 for mcq 
                     if($questionTypeId == 1){
                        $prompt .= "and it must have different types of 4 options which differentiate between them. give me corret_answer also below the options array. Each option must have 'text' (string) and 'correct' (boolean: true/false). Make options truly distinct from each other.";
                    }
                    
                } else {
                    // Original behavior - generate varied question types
                    $prompt = "Generate unique, varied questions for " .
                        "Standard: " . $names['standard'] .
                        ", Subject: " . $names['subject'] .
                        ", Chapter: " . $names['chapter'];
                    
                    if ($topic_id) {
                        $prompt .= ", Topic: " . $names['topic'];
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
                    $prompt .= ". Generate exactly " . $questionCount . " different question(s) that vary in type (MCQ, short answer, long answer, fill in the blanks). ";
                    $prompt .= "Make each question unique and different from each other. Use this seed for variety: " . $seed . ". ";
                    $prompt .= "Return the response as a JSON array of question objects with fields: question, question_type (MCQ/ShortAnswer/LongAnswer/FillInBlanks), options (array of 4 for MCQ), correct_answer, and explanation. with no extra text want only json array";
                }
                // below if condition is for MCQ only
                if($questionTypeId == 1){
                        $prompt .= "and it must have different types of 4 options which differentiate between them. give me corret_answer also below the options array. Each option must have 'text' (string) and 'correct' (boolean: true/false). Make options truly distinct from each other.";
                    }
                $distribution = null;
            }
            // return $prompt;
            $prompt.=". Return ONLY the JSON array starting with `[` - no explanations, no prefixes, no markdown formatting, just the raw JSON array.";
            // if(empty($getPadagogy))
            //     {
            //         $getPadagogyTitle = \App\Helpers\getTableFieldFromId('lms_mapping_type', 'name', 3105);
            //         $getPadadgogyVals = DB::table('lms_mapping_type')
            //             ->where('parent_id', 3105)
            //             ->where(['status'=>1,'globally'=>1])
            //             ->pluck('name')->toArray();    
                            
            //         $prompt .= "Also in every Questions i want mappings with type=".$getPadagogyTitle." and values=".json_encode($getPadadgogyVals);
            //     }
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
                'status'=>1,
                'message'=>'Success',
                'main'=>$response,
                'ai_response'=>$aiData,
                'questions'=>$questions,
                'distribution' => $distribution ?? null,
                'total_questions' => $questionCount ?? null,
                'total_marks' => $isSimplifiedMode
                    ? ($totalMarks ?? array_sum(array_column(array_filter($distribution, fn($item) => empty($item['additional_mapping'])), 'total_marks')))
                    : null
            ]);
            
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Question generation error: ' . $e->getMessage());
            
            // // Return fallback questions on error
            // $questionsCount = $request->get('question_prompt', '');
            // preg_match('/(\d+)/', $questionsCount, $matches);
            // $count = isset($matches[0]) ? (int)$matches[0] : 3;
            
            // return response()->json($this->generateFallbackQuestions(
            //     $count,
            //     $request->get('standard'),
            //     $request->get('subject_id'),
            //     $request->get('chapter_id'),
            //     $request->get('topic_id'),
            //     rand(1, 10000),
            //     ($request->get('question_type_id') ?? 1) == 1
            // ));

            return response()->json(['status'=>0,'message'=>$e->getMessage()]);
        }
    }
    
    /**
     * Attach distribution information to generated questions
     */
    private function attachDistributionToQuestions($questions, $distribution)
    {
        $result = $questions;
        $questionIndex = 0;
        $primaryDistribution = array_values(array_filter($distribution, fn($item) => empty($item['additional_mapping'])));
        $additionalDistribution = array_values(array_filter($distribution, fn($item) => !empty($item['additional_mapping'])));
        $hasSelectedDistribution = collect($primaryDistribution)->contains(fn($item) => !empty($item['selected']));

        if ($hasSelectedDistribution) {
            $questionCount = count($result);
            foreach ($primaryDistribution as $index => $distItem) {
                if ($questionCount === 0) {
                    break;
                }

                $targetIndex = $index % $questionCount;
                if (!isset($result[$targetIndex]['mappings']) || !is_array($result[$targetIndex]['mappings'])) {
                    $result[$targetIndex]['mappings'] = [];
                }

                if (!empty($distItem['all_selected_mappings']) && is_array($distItem['all_selected_mappings'])) {
                    foreach ($result as &$question) {
                        if (!isset($question['mappings']) || !is_array($question['mappings'])) {
                            $question['mappings'] = [];
                        }

                        foreach ($distItem['all_selected_mappings'] as $selectedMapping) {
                            if (empty($question['mapping_type_id'])) {
                                $question['mapping_type_id'] = $selectedMapping['type_id'];
                                $question['mapping_value_id'] = $selectedMapping['value_id'];
                                $question['mapping_type_name'] = $selectedMapping['type_name'];
                                $question['mapping_value_name'] = $selectedMapping['value_name'];
                                $question['difficulty'] = $selectedMapping['difficulty'];
                                $question['marks'] = self::MARKS_BY_DIFFICULTY[$selectedMapping['difficulty'] ?? 'Easy'] ?? 1;
                                $question['points'] = $question['marks'];
                            }

                            $question['mappings'][] = [
                                'mapping_type' => $selectedMapping['type_id'],
                                'mapping_value' => $selectedMapping['value_id'],
                                'mapping_type_name' => $selectedMapping['type_name'],
                                'mapping_value_name' => $selectedMapping['value_name'],
                                'reason' => ($selectedMapping['reason'] ?? '') ?: $selectedMapping['value_name'] . ' - ' . $selectedMapping['difficulty']
                            ];
                        }
                    }
                    unset($question);

                    return $result;
                }

                if (empty($result[$targetIndex]['mapping_type_id'])) {
                    $result[$targetIndex]['mapping_type_id'] = $distItem['mapping_type_id'];
                    $result[$targetIndex]['mapping_value_id'] = $distItem['mapping_value_id'];
                    $result[$targetIndex]['mapping_type_name'] = $distItem['mapping_type_name'];
                    $result[$targetIndex]['mapping_value_name'] = $distItem['mapping_value_name'];
                    $result[$targetIndex]['difficulty'] = $distItem['difficulty'];
                    $result[$targetIndex]['marks'] = $distItem['marks'];
                    $result[$targetIndex]['points'] = $distItem['marks'];
                }

                $result[$targetIndex]['mappings'][] = [
                    'mapping_type' => $distItem['mapping_type_id'],
                    'mapping_value' => $distItem['mapping_value_id'],
                    'mapping_type_name' => $distItem['mapping_type_name'],
                    'mapping_value_name' => $distItem['mapping_value_name'],
                    'reason' => ($distItem['reason'] ?? '') ?: $distItem['mapping_value_name'] . ' - ' . $distItem['difficulty']
                ];
            }

            return $result;
        }
        
        foreach ($primaryDistribution as $distItem) {
            for ($i = 0; $i < $distItem['questions']; $i++) {
                if (isset($result[$questionIndex])) {
                    $result[$questionIndex]['mapping_type_id'] = $distItem['mapping_type_id'];
                    $result[$questionIndex]['mapping_value_id'] = $distItem['mapping_value_id'];
                    $result[$questionIndex]['mapping_type_name'] = $distItem['mapping_type_name'];
                    $result[$questionIndex]['mapping_value_name'] = $distItem['mapping_value_name'];
                    $result[$questionIndex]['difficulty'] = $distItem['difficulty'];
                    $result[$questionIndex]['marks'] = $distItem['marks'];
                    $result[$questionIndex]['points'] = $distItem['marks'];
                    
                    $result[$questionIndex]['mappings'] = [[
                        'mapping_type' => $distItem['mapping_type_id'],
                        'mapping_value' => $distItem['mapping_value_id'],
                        'mapping_type_name' => $distItem['mapping_type_name'],
                        'mapping_value_name' => $distItem['mapping_value_name'],
                        'reason' => ($distItem['reason'] ?? '') ?: $distItem['mapping_value_name'] . ' - ' . $distItem['difficulty']
                    ]];
                    
                    $questionIndex++;
                }
            }
        }

        $questionIndex = 0;
        foreach ($additionalDistribution as $distItem) {
            if (!empty($distItem['attach_to_all'])) {
                foreach ($result as &$question) {
                    if (!isset($question['mappings']) || !is_array($question['mappings'])) {
                        $question['mappings'] = [];
                    }

                    $question['mappings'][] = [
                        'mapping_type' => $distItem['mapping_type_id'],
                        'mapping_value' => $distItem['mapping_value_id'],
                        'mapping_type_name' => $distItem['mapping_type_name'],
                        'mapping_value_name' => $distItem['mapping_value_name'],
                        'reason' => ($distItem['reason'] ?? '') ?: $distItem['mapping_value_name'] . ' - ' . $distItem['difficulty']
                    ];
                }
                unset($question);
                continue;
            }

            for ($i = 0; $i < $distItem['questions']; $i++) {
                if (!isset($result[$questionIndex])) {
                    break;
                }

                if (!isset($result[$questionIndex]['mappings']) || !is_array($result[$questionIndex]['mappings'])) {
                    $result[$questionIndex]['mappings'] = [];
                }

                $result[$questionIndex]['mappings'][] = [
                    'mapping_type' => $distItem['mapping_type_id'],
                    'mapping_value' => $distItem['mapping_value_id'],
                    'mapping_type_name' => $distItem['mapping_type_name'],
                    'mapping_value_name' => $distItem['mapping_value_name'],
                    'reason' => ($distItem['reason'] ?? '') ?: $distItem['mapping_value_name'] . ' - ' . $distItem['difficulty']
                ];

                $questionIndex++;
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
            $cleanResponse = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $response));
            $parsed = json_decode($cleanResponse, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }

            // Find and decode the first complete JSON array in the response.
            $startPos = strpos($cleanResponse, '[');
            if ($startPos !== false) {
                $jsonStr = substr($cleanResponse, $startPos);
                $depth = 0;
                $inString = false;
                $escaped = false;
                $endPos = null;

                for ($i = 0; $i < strlen($jsonStr); $i++) {
                    $char = $jsonStr[$i];

                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }

                    if ($char === '\\' && $inString) {
                        $escaped = true;
                        continue;
                    }

                    if ($char === '"') {
                        $inString = !$inString;
                        continue;
                    }

                    if ($inString) {
                        continue;
                    }

                    if ($char === '[') {
                        $depth++;
                    } elseif ($char === ']') {
                        $depth--;
                        if ($depth === 0) {
                            $endPos = $i;
                            break;
                        }
                    }
                }

                if ($endPos !== null) {
                    $parsed = json_decode(substr($jsonStr, 0, $endPos + 1), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                        $questions = $parsed;
                    }
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
