<?php

namespace App\Http\Controllers\neo4J;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Neo4jService;

class addAssesmentController extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }

    /**
     * Store a single assessment in Neo4j
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'assId' => 'required|integer',
                'sub_institute_id' => 'required|integer',
                'paper_name' => 'required|string|max:255',
                'total_marks' => 'required|numeric',
                'syear' => 'required|integer',
                'exam_type' => 'nullable|string|max:50',
                'displayLabel' => 'nullable|string|max:255',
                'standard_id' => 'nullable|integer',
                'subject_id' => 'nullable|integer',
                'grade_id' => 'nullable|integer',
                'question_ids' => 'nullable|string',
                'total_ques' => 'nullable|integer',
                'paper_desc' => 'nullable|string',
                'time_allowed' => 'nullable|integer',
                'created_by' => 'nullable|integer',
                'created_on' => 'nullable|date'
            ]);

            $client = $this->neo4jService->getClient();

            // Prepare display label if not provided
            $displayLabel = $request->displayLabel ?? "Assessment:{$request->paper_name}";
            $exam_type = $request->exam_type ?? 'online';
            $question_ids = $request->question_ids ?? '';
            $total_ques = $request->total_ques ?? 0;

            // Cypher query to create or update Assessment node
            $query = '
            MERGE (ass:Assessment {
                assId: $assId,
                sub_institute_id: $sub_institute_id
            })
            SET ass.displayLabel = $displayLabel,
                ass.exam_type = $exam_type,
                ass.paper_name = $paper_name,
                ass.total_marks = $total_marks,
                ass.syear = $syear,
                ass.standard_id = $standard_id,
                ass.subject_id = $subject_id,
                ass.grade_id = $grade_id,
                ass.question_ids = $question_ids,
                ass.total_ques = $total_ques,
                ass.paper_desc = $paper_desc,
                ass.time_allowed = $time_allowed,
                ass.created_by = $created_by,
                ass.created_on = $created_on,
                ass.updated_at = datetime()
            
            RETURN ass
            ';

            $params = [
                'assId' => (int)$validated['assId'],
                'sub_institute_id' => (int)$validated['sub_institute_id'],
                'displayLabel' => $displayLabel,
                'exam_type' => $exam_type,
                'paper_name' => $validated['paper_name'],
                'total_marks' => (float)$validated['total_marks'],
                'syear' => (int)$validated['syear'],
                'standard_id' => $request->standard_id ? (int)$request->standard_id : null,
                'subject_id' => $request->subject_id ? (int)$request->subject_id : null,
                'grade_id' => $request->grade_id ? (int)$request->grade_id : null,
                'question_ids' => $question_ids,
                'total_ques' => (int)$total_ques,
                'paper_desc' => $request->paper_desc,
                'time_allowed' => $request->time_allowed ? (int)$request->time_allowed : null,
                'created_by' => $request->created_by ? (int)$request->created_by : null,
                'created_on' => $request->created_on ?? date('Y-m-d H:i:s')
            ];

            $result = $client->run($query, $params);

            return response()->json([
                'success' => true,
                'message' => 'Assessment stored successfully in Neo4j',
                'data' => [
                    'assId' => $validated['assId'],
                    'sub_institute_id' => $validated['sub_institute_id'],
                    'paper_name' => $validated['paper_name']
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Neo4j Assessment Insertion Error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to store assessment in Neo4j',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment by ID from Neo4j
     * 
     * @param int $assId
     * @param Request $request
     * @return JsonResponse
     */
    public function show($assId, Request $request): JsonResponse
    {
        try {
            $sub_institute_id = $request->get('sub_institute_id');
            
            if (!$sub_institute_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'sub_institute_id is required'
                ], 400);
            }

            $client = $this->neo4jService->getClient();
            
            $query = '
            MATCH (ass:Assessment {
                assId: $assId, 
                sub_institute_id: $sub_institute_id
            })
            RETURN ass
            ';
            
            $result = $client->run($query, [
                'assId' => (int)$assId,
                'sub_institute_id' => (int)$sub_institute_id
            ]);
            
            if ($result->count() === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Assessment not found in Neo4j'
                ], 404);
            }
            
            $record = $result->first();
            $assessment = $record->get('ass');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'assId' => $assessment->get('assId'),
                    'sub_institute_id' => $assessment->get('sub_institute_id'),
                    'displayLabel' => $assessment->get('displayLabel'),
                    'exam_type' => $assessment->get('exam_type'),
                    'paper_name' => $assessment->get('paper_name'),
                    'total_marks' => $assessment->get('total_marks'),
                    'syear' => $assessment->get('syear'),
                    'standard_id' => $assessment->get('standard_id'),
                    'subject_id' => $assessment->get('subject_id'),
                    'grade_id' => $assessment->get('grade_id'),
                    'question_ids' => $assessment->get('question_ids'),
                    'total_ques' => $assessment->get('total_ques'),
                    'paper_desc' => $assessment->get('paper_desc'),
                    'time_allowed' => $assessment->get('time_allowed'),
                    'created_by' => $assessment->get('created_by'),
                    'created_on' => $assessment->get('created_on'),
                    'updated_at' => $assessment->get('updated_at')
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Neo4j Assessment Fetch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch assessment from Neo4j'
            ], 500);
        }
    }

    /**
     * Update assessment in Neo4j
     * 
     * @param Request $request
     * @param int $assId
     * @return JsonResponse
     */
    public function update(Request $request, $assId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sub_institute_id' => 'required|integer',
                'paper_name' => 'sometimes|string|max:255',
                'total_marks' => 'sometimes|numeric',
                'syear' => 'sometimes|integer',
                'exam_type' => 'sometimes|string|max:50',
                'displayLabel' => 'sometimes|string|max:255',
                'standard_id' => 'sometimes|integer',
                'subject_id' => 'sometimes|integer',
                'grade_id' => 'sometimes|integer',
                'question_ids' => 'sometimes|string',
                'total_ques' => 'sometimes|integer'
            ]);

            $client = $this->neo4jService->getClient();
            
            // Build dynamic SET clause
            $setFields = [];
            $params = [
                'assId' => (int)$assId,
                'sub_institute_id' => (int)$validated['sub_institute_id']
            ];
            
            $updatableFields = [
                'paper_name', 'total_marks', 'syear', 'exam_type', 'displayLabel',
                'standard_id', 'subject_id', 'grade_id', 'question_ids', 'total_ques'
            ];
            
            foreach ($updatableFields as $field) {
                if ($request->has($field)) {
                    $setFields[] = "ass.$field = \$$field";
                    $params[$field] = $request->$field;
                }
            }
            
            if (empty($setFields)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No fields to update'
                ], 400);
            }
            
            $setFields[] = 'ass.updated_at = datetime()';
            $setClause = implode(', ', $setFields);
            
            $query = "
            MATCH (ass:Assessment {
                assId: \$assId, 
                sub_institute_id: \$sub_institute_id
            })
            SET $setClause
            RETURN ass
            ";
            
            $result = $client->run($query, $params);
            
            if ($result->count() === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Assessment not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Assessment updated successfully'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Neo4j Assessment Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to update assessment'
            ], 500);
        }
    }

    /**
     * Delete assessment from Neo4j
     * 
     * @param int $assId
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy($assId, Request $request): JsonResponse
    {
        try {
            $sub_institute_id = $request->get('sub_institute_id');
            
            if (!$sub_institute_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'sub_institute_id is required'
                ], 400);
            }
            
            $client = $this->neo4jService->getClient();
            
            // Delete assessment and its relationships
            $query = '
            MATCH (ass:Assessment {
                assId: $assId, 
                sub_institute_id: $sub_institute_id
            })
            OPTIONAL MATCH (ass)-[r]-()
            DELETE ass, r
            RETURN COUNT(ass) as deleted
            ';
            
            $result = $client->run($query, [
                'assId' => (int)$assId,
                'sub_institute_id' => (int)$sub_institute_id
            ]);
            
            $deleted = $result->first()->get('deleted');
            
            if ($deleted == 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Assessment not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Assessment deleted successfully'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Neo4j Assessment Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete assessment'
            ], 500);
        }
    }

    /**
     * Get all assessments for a sub institute
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $limit = $request->get('limit', 100);
            
            if (!$sub_institute_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'sub_institute_id is required'
                ], 400);
            }
            
            $client = $this->neo4jService->getClient();
            
            $query = '
            MATCH (ass:Assessment {sub_institute_id: $sub_institute_id})
            ' . ($syear ? 'WHERE ass.syear = $syear' : '') . '
            RETURN ass
            ORDER BY ass.created_on DESC
            LIMIT $limit
            ';
            
            $params = [
                'sub_institute_id' => (int)$sub_institute_id,
                'limit' => (int)$limit
            ];
            
            if ($syear) {
                $params['syear'] = (int)$syear;
            }
            
            $result = $client->run($query, $params);
            
            $assessments = [];
            foreach ($result as $record) {
                $ass = $record->get('ass');
                $assessments[] = [
                    'assId' => $ass->get('assId'),
                    'sub_institute_id' => $ass->get('sub_institute_id'),
                    'displayLabel' => $ass->get('displayLabel'),
                    'exam_type' => $ass->get('exam_type'),
                    'paper_name' => $ass->get('paper_name'),
                    'total_marks' => $ass->get('total_marks'),
                    'syear' => $ass->get('syear'),
                    'standard_id' => $ass->get('standard_id'),
                    'subject_id' => $ass->get('subject_id'),
                    'total_ques' => $ass->get('total_ques')
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $assessments,
                'count' => count($assessments)
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Neo4j Assessments Fetch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch assessments'
            ], 500);
        }
    }

    /**
     * Store assessment from question_paper data (bulk insert from your existing table)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function storeFromQuestionPaper(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'assId' => 'required|integer',
                'sub_institute_id' => 'required|integer',
                'paper_name' => 'required|string',
                'total_marks' => 'required|numeric',
                'syear' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'grade_id' => 'required|integer',
                'question_ids' => 'required|string',
                'total_ques' => 'required|integer'
            ]);

            $client = $this->neo4jService->getClient();
            
            $query = '
            MERGE (ass:Assessment {
                assId: $assId,
                sub_institute_id: $sub_institute_id
            })
            SET ass.displayLabel = "Assessment:" + $paper_name,
                ass.exam_type = "online",
                ass.paper_name = $paper_name,
                ass.total_marks = $total_marks,
                ass.syear = $syear,
                ass.standard_id = $standard_id,
                ass.subject_id = $subject_id,
                ass.grade_id = $grade_id,
                ass.question_ids = $question_ids,
                ass.total_ques = $total_ques,
                ass.created_at = datetime()
            RETURN ass
            ';
            
            $client->run($query, [
                'assId' => (int)$validated['assId'],
                'sub_institute_id' => (int)$validated['sub_institute_id'],
                'paper_name' => $validated['paper_name'],
                'total_marks' => (float)$validated['total_marks'],
                'syear' => (int)$validated['syear'],
                'standard_id' => (int)$validated['standard_id'],
                'subject_id' => (int)$validated['subject_id'],
                'grade_id' => (int)$validated['grade_id'],
                'question_ids' => $validated['question_ids'],
                'total_ques' => (int)$validated['total_ques']
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Assessment stored from question paper successfully'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Neo4j Assessment Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to store assessment'
            ], 500);
        }
    }
}
