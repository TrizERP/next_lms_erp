<?php

namespace App\Http\Controllers;

use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GraphControllerNew extends Controller
{
    protected $neo4jService;

    public function __construct(Neo4jService $neo4jService)
    {
        $this->neo4jService = $neo4jService;
    }
    public function getStudents(): JsonResponse
    {
        $query = 'MATCH (student:Student {id: 5}) RETURN student';
        $result = $this->neo4jService->getClient()->run($query);

        $nodes = [];
        foreach ($result as $record) {
            $student = $record->get('student');
            $studentProperties = $student->getProperties();

            $studentId = (string) $studentProperties['id'];
            $studentName = $studentProperties['name'] ?? 'Unknown Student';

            $nodes[] = [
                'id' => $studentId,
                'label' => $studentName,
                'color' => '#FFD700'
            ];
        }
        Log::info($nodes);
        return response()->json(['nodes' => $nodes]);
    }

    public function getRelatedData($nodeId): JsonResponse
    {
        $query = '
            MATCH (student:Student {id: $nodeId})-[:ENROLLED_IN]->(standard:Standard)-[:OFFERS]->(subject:Subject)-[:OFFERS]->(chapter:Chapter)
            RETURN student, standard, subject, chapter
        ';

        $result = $this->neo4jService->getClient()->run($query, ['nodeId' => (int) $nodeId]);
        Log::info($result->toArray());

        $nodes = [];
        $edges = [];
        $nodeIds = [];
        $edgeIds = [];
        $chapterIds = [];

        foreach ($result as $record) {
            $student = $record->get('student');
            $standard = $record->get('standard');
            $subject = $record->get('subject');
            $chapter = $record->get('chapter');

            $studentId = (int) $student->getId();
            $standardId = (int) $standard->getId();
            $subjectId = (int) $subject->getId();
            $chapterId = (int) $chapter->getId();
            Log::info($chapterId);

            $studentName = $student->getProperties()['name'] ?? 'Unknown Student';
            $standardName = $standard->getProperties()['standard'] ?? 'Unknown Standard';
            $subjectName = $subject->getProperties()['subject'] ?? 'Unknown Subject';
            $chapterName = $chapter->getProperties()['chapter'] ?? 'Unknown Chapter';

            if (!in_array($studentId, $nodeIds)) {
                $nodes[] = [
                    'id' => $studentId,
                    'label' => $studentName,
                    'color' => '#FFD700'
                ];
                $nodeIds[] = $studentId;
            }

            if (!in_array($standardId, $nodeIds)) {
                $nodes[] = [
                    'id' => $standardId,
                    'label' => $standardName,
                    'color' => '#FFE333'
                ];
                $nodeIds[] = $standardId;
            }

            if (!in_array($subjectId, $nodeIds)) {
                $nodes[] = [
                    'id' => $subjectId,
                    'label' => $subjectName,
                    'color' => '#33FF57'
                ];
                $nodeIds[] = $subjectId;
            }

            if (!in_array($chapterId, $nodeIds)) {
                $nodes[] = [
                    'id' => $chapterId,
                    'label' => $chapterName,
                    'color' => '#FF5733'
                ];
                $chapterIds[] = $chapterId;

            }

            if (!in_array("$studentId-$standardId", $edgeIds)) {
                $edges[] = [
                    'from' => $studentId,
                    'to' => $standardId,
                    'label' => 'ENROLLED_IN',
                    'arrows' => 'to'
                ];
                $edgeIds[] = "$studentId-$standardId";
            }

            if (!in_array("$standardId-$subjectId", $edgeIds)) {
                $edges[] = [
                    'from' => $standardId,
                    'to' => $subjectId,
                    'label' => 'OFFERS',
                    'arrows' => 'to'
                ];
                $edgeIds[] = "$standardId-$subjectId";
            }

            if (!in_array("$subjectId-$chapterId", $edgeIds)) {
                $edges[] = [
                    'from' => $subjectId,
                    'to' => $chapterId,
                    'label' => 'OFFERS',
                    'arrows' => 'to'
                ];
                $edgeIds[] = "$subjectId-$chapterId";
            }
        }

        return response()->json(['nodes' => $nodes, 'edges' => $edges, 'chapterIds' => $chapterIds]);
    }

    public function getChaptersForSubject($subjectId): JsonResponse
    {
        $query = '
            MATCH (subject:Subject {id: $subjectId})-[:OFFERS]->(chapter:Chapter)
            RETURN subject, chapter
        ';

        $result = $this->neo4jService->getClient()->run($query, ['subjectId' => (int) $subjectId]);

        $nodes = [];
        $edges = [];

        foreach ($result as $record) {
            $subject = $record->get('subject');
            $chapter = $record->get('chapter');

            $subjectId = (string) $subject->getId();
            $chapterId = (string) $chapter->getId();

            $chapterName = $chapter->getProperties()['chapter'] ?? 'Unknown Chapter';

            $nodes[] = [
                'id' => $chapterId,
                'label' => $chapterName,
                'color' => '#FF5733'
            ];

            $edges[] = [
                'from' => $subjectId,
                'to' => $chapterId,
                'label' => 'OFFERS',
                'arrows' => 'to'
            ];
        }

        return response()->json(['nodes' => $nodes, 'edges' => $edges]);
    }

    public function getQuestionsForChapter($chapterId): JsonResponse
{
    $questions = DB::table('lms_question_master as lqm')
        ->leftJoin('standard', 'standard.id', '=', 'lqm.standard_id')
        ->leftJoin('academic_section', 'academic_section.id', '=', 'lqm.grade_id')
        ->leftJoin('subject', 'subject.id', '=', 'lqm.subject_id')
        ->leftJoin('chapter_master', 'chapter_master.id', '=', 'lqm.chapter_id')
        ->leftJoin('question_type_master', 'question_type_master.id', '=', 'lqm.question_type_id')
        ->leftJoin('lms_question_mapping as lm', 'lm.questionmaster_id', '=', 'lqm.id')
        ->leftJoin('lms_mapping_type as t', 't.id', '=', 'lm.mapping_type_id')
        ->leftJoin('lms_mapping_type as t1', 't1.id', '=', 'lm.mapping_value_id')
        ->leftJoin('lms_online_exam_answer as loea', 'loea.question_id', '=', 'lqm.id')
        ->select(
            'lqm.id',
            DB::raw('MAX(lqm.question_title) as question_title'),
            DB::raw('MAX(standard.name) as standard_name'),
            DB::raw('MAX(academic_section.title) as grade_name'),
            DB::raw('MAX(subject.subject_name) as subject_name'),
            DB::raw('MAX(chapter_master.chapter_name) as chapter_name'),
            DB::raw('MAX(question_type_master.question_type) as question_type'),
            DB::raw('GROUP_CONCAT(t1.name) as type_name'),
            DB::raw('GROUP_CONCAT(t.name) as value_name'),
            DB::raw('IFNULL(MAX(loea.question_id), "0") as attempt_question')
        )
        ->where('lqm.chapter_id', '=', $chapterId)
        ->groupBy('lqm.id')
        ->get();
    Log::info($questions);
    $nodes = [];
    $edges = [];

    foreach ($questions as $question) {
        $questionId = 'q-' . $question->id;
        $questionLabel = $question->question_title ?? 'Unknown Question';

        $nodes[] = [
            'id' => $questionId,
            'label' => $questionLabel,
            'color' => '#87CEEB'
        ];

        $edges[] = [
            'from' => $chapterId,
            'to' => $questionId,
            'label' => 'HAS_QUESTION',
            'arrows' => 'to'
        ];
    }

    return response()->json(['nodes' => $nodes, 'edges' => $edges]);
}
public function getPersonalizedLearningPath($studentId): JsonResponse
{
    $relatedData = $this->getRelatedData($studentId);
    $chapterIds = $relatedData->getData()->chapterIds; 
    $recommendations = [
        'chapters' => [],
        'questions' => []
    ];

    foreach ($chapterIds as $chapterId) {
        $questions = $this->getQuestionsForChapter($chapterId);
        $recommendations['chapters'][] = [
            'chapterId' => $chapterId,
            'questions' => $questions->getData()->nodes 
        ];
    }

    return response()->json(['recommendations' => $recommendations]);
}
}