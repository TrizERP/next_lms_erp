<?php

namespace App\Services\PAL\Integration;

use App\Services\PAL\Pedagogy\PedagogyOrchestrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PedagogySuggestedContentService
{
    private ?PedagogyOrchestrationService $pedagogy;

    public function __construct(?PedagogyOrchestrationService $pedagogy = null)
    {
        $this->pedagogy = $pedagogy;
    }

    public function getSuggestions(array $context = []): array
    {
        $learnerId = $this->resolveLearnerId($context);

        if ($learnerId <= 0) {
            return [
                'status_code' => 0,
                'status' => 0,
                'message' => 'Learner ID is required',
                'content_data' => $this->emptyContentData(),
                'pedagogy' => [],
                'student_profile' => [
                    'learning_style' => 'visual',
                    'learning_pace' => 'medium',
                    'preferred_content_types' => [],
                    'motivation_level' => 'medium',
                    'attention_span' => 0,
                    'strengths' => [],
                    'weaknesses' => [],
                    'interests' => [],
                ],
                'mastery_summary' => [
                    'total_concepts' => 0,
                    'mastered' => 0,
                    'developing' => 0,
                    'needs_practice' => 0,
                    'due_for_review' => 0,
                ],
                'teacher_insights' => [],
                'student_level' => 'esay',
            ];
        }

        $standardId = (int) ($context['standard_id'] ?? 0);
        $subjectId = (int) ($context['subject_id'] ?? 0);
        $chapterId = (int) ($context['chapter_id'] ?? 0);
        $gradeId = (int) ($context['grade_id'] ?? 0);
        $subInstituteId = (int) ($context['sub_institute_id'] ?? session()->get('sub_institute_id', 0));
        $syear = $context['syear'] ?? session()->get('syear');
        $deviceType = (string) ($context['device_type'] ?? 'desktop');
        $studentLevelHint = strtolower((string) ($context['student_level'] ?? ''));

        $profile = $this->getOrCreateStudentProfile($learnerId);
        $preferredContentTypes = $this->decodeJsonArray($profile->preferred_content_types ?? []);

        $masteryData = $this->getStudentConceptMastery(
            $learnerId,
            $standardId,
            $subjectId,
            $chapterId,
            $subInstituteId,
            $syear
        );

        $forgettingData = $this->getForgettingCurveForStudent($learnerId, $chapterId, $syear);

        $weakConceptRows = array_values(array_filter($masteryData, function (array $item) {
            return ($item['mastery_level'] ?? 0) < 40;
        }));
        $weakConceptIds = array_values(array_filter(array_map(function (array $item) {
            return $item['concept_id'] ?? null;
        }, $weakConceptRows)));
        $weakConceptNames = array_values(array_filter(array_map(function (array $item) {
            return $item['concept_name'] ?? null;
        }, $weakConceptRows)));

        $dueConceptIds = [];
        foreach ($forgettingData as $row) {
            if (!empty($row->next_review_at) && Carbon::parse($row->next_review_at)->lte(now())) {
                $dueConceptIds[] = $row->concept_id;
            }
        }
        $dueConceptIds = array_values(array_filter(array_unique($dueConceptIds)));

        $contentRows = $this->getCandidateContent(
            $standardId,
            $subjectId,
            $chapterId,
            $gradeId,
            $subInstituteId,
            $syear,
            $preferredContentTypes,
            $studentLevelHint,
            $profile,
            $masteryData
        );

        $storedSuggestedRows = $this->getSuggestedContentRows(
            $learnerId,
            $standardId,
            $subjectId,
            $chapterId,
            $subInstituteId,
            $syear
        );

        $contentRows = $this->attachContentMappings($contentRows);
        $storedSuggestedRows = $this->attachContentMappings($storedSuggestedRows);

        $contentData = $this->categorizeContentWithPedagogy(
            $contentRows,
            $storedSuggestedRows,
            $masteryData,
            $forgettingData,
            $profile,
            $weakConceptIds,
            $weakConceptNames,
            $dueConceptIds,
            $preferredContentTypes,
            $deviceType,
            $learnerId
        );

        $studentLevel = $this->getStudentLevel($masteryData);

        $pedagogy = $this->generatePedagogyRecommendations(
            $profile,
            $masteryData,
            $weakConceptIds,
            $weakConceptNames,
            $dueConceptIds,
            $deviceType,
            $learnerId
        );

        $teacherInsights = $this->generateTeacherInsights(
            $contentRows,
            $masteryData,
            $weakConceptNames,
            $dueConceptIds
        );

        $message = !empty($contentRows)
            ? 'Pedagogy-Enhanced Content Loaded'
            : 'Pedagogy profile loaded, but no mapped content was found for this chapter';

        return [
            'status_code' => 1,
            'status' => 1,
            'message' => $message,
            'content_data' => $contentData,
            'pedagogy' => $pedagogy,
            'student_profile' => [
                'learning_style' => $profile->learning_style ?? 'visual',
                'learning_pace' => $profile->learning_pace ?? 'medium',
                'preferred_content_types' => $preferredContentTypes,
                'motivation_level' => $profile->motivation_level ?? 'medium',
                'attention_span' => (float) ($profile->attention_span ?? 0),
                'strengths' => $this->decodeJsonArray($profile->strengths ?? []),
                'weaknesses' => $this->decodeJsonArray($profile->weaknesses ?? []),
                'interests' => $this->decodeJsonArray($profile->interests ?? []),
            ],
            'mastery_summary' => [
                'total_concepts' => count($masteryData),
                'mastered' => collect($masteryData)->filter(function (array $item) {
                    return ($item['mastery_level'] ?? 0) >= 70;
                })->count(),
                'developing' => collect($masteryData)->filter(function (array $item) {
                    $mastery = (float) ($item['mastery_level'] ?? 0);
                    return $mastery >= 40 && $mastery < 70;
                })->count(),
                'needs_practice' => count($weakConceptRows),
                'due_for_review' => count($dueConceptIds),
            ],
            'teacher_insights' => $teacherInsights,
            'student_level' => $studentLevel,
            'grade' => $gradeId ?: null,
            'standard' => $standardId ?: null,
            'subject' => $subjectId ?: null,
            'chapter' => $chapterId ?: null,
        ];
    }

    private function resolveLearnerId(array $context): int
    {
        return (int) (
            $context['learner_id']
            ?? $context['student_id']
            ?? $context['user_id']
            ?? session()->get('user_id', 0)
        );
    }

    private function getOrCreateStudentProfile(int $learnerId)
    {
        $defaults = [
            'student_id' => $learnerId,
            'learning_style' => 'visual',
            'learning_pace' => 'medium',
            'motivation_level' => 'medium',
            'attention_span' => 50,
            'preferred_content_types' => json_encode(['video', 'interactive', 'text']),
            'strengths' => json_encode([]),
            'weaknesses' => json_encode([]),
            'interests' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!Schema::hasTable('lms_student_profile')) {
            return (object) $defaults;
        }

        $profile = DB::table('lms_student_profile')
            ->where('student_id', $learnerId)
            ->first();

        if (!$profile) {
            $profileId = DB::table('lms_student_profile')->insertGetId($defaults);

            $profile = DB::table('lms_student_profile')
                ->where('id', $profileId)
                ->first();
        }

        return $profile ?: (object) $defaults;
    }

    private function getStudentConceptMastery(
        int $learnerId,
        int $standardId,
        int $subjectId,
        int $chapterId = 0,
        int $subInstituteId = 0,
        $syear = null
    ): array {
        if (
            !Schema::hasTable('lms_concept')
            || !Schema::hasTable('lms_question_master')
            || !Schema::hasTable('lms_online_exam_answer')
            || !Schema::hasColumn('lms_question_master', 'concept_id')
        ) {
            return [];
        }

        $query = DB::table('lms_concept as c')
            ->leftJoin('lms_question_master as q', 'q.concept_id', '=', 'c.id')
            ->leftJoin('lms_online_exam_answer as a', function ($join) use ($learnerId) {
                $join->on('a.question_id', '=', 'q.id')
                    ->where('a.student_id', '=', $learnerId);
            })
            ->select(
                'c.id as concept_id',
                'c.name as concept_name',
                DB::raw('COUNT(DISTINCT a.id) as attempts'),
                DB::raw('SUM(CASE WHEN a.ans_status = 1 THEN 1 ELSE 0 END) as correct'),
                DB::raw('MAX(a.created_at) as last_practiced')
            )
            ->where('c.standard_id', $standardId)
            ->where('c.subject_id', $subjectId);

        if ($chapterId > 0) {
            $query->where('c.chapter_id', $chapterId);
        }

        if ($subInstituteId > 0 && Schema::hasColumn('lms_concept', 'sub_institute_id')) {
            $query->where('c.sub_institute_id', $subInstituteId);
        }

        if ($syear !== null && Schema::hasColumn('lms_concept', 'syear')) {
            $query->where('c.syear', $syear);
        }

        if (Schema::hasColumn('lms_concept', 'mastery_threshold')) {
            $query->addSelect('c.mastery_threshold');
        } else {
            $query->addSelect(DB::raw('70 as mastery_threshold'));
        }

        $groupByColumns = ['c.id', 'c.name'];
        if (Schema::hasColumn('lms_concept', 'mastery_threshold')) {
            $groupByColumns[] = 'c.mastery_threshold';
        }

        $results = $query
            ->groupBy($groupByColumns)
            ->get();

        $masteryData = [];

        foreach ($results as $concept) {
            $attempts = (int) ($concept->attempts ?? 0);
            $correct = (int) ($concept->correct ?? 0);
            $mastery = $attempts > 0 ? round(($correct / $attempts) * 100, 1) : 0;
            $threshold = (float) ($concept->mastery_threshold ?? 70);

            $status = $attempts === 0
                ? 'not_started'
                : ($mastery >= $threshold
                    ? 'mastered'
                    : ($mastery >= ($threshold * 0.6) ? 'developing' : 'needs_practice'));

            $masteryData[] = [
                'concept_id' => (int) $concept->concept_id,
                'concept_name' => $concept->concept_name,
                'mastery_level' => $mastery,
                'attempts' => $attempts,
                'correct' => $correct,
                'status' => $status,
                'last_practiced' => $concept->last_practiced,
            ];
        }

        return $masteryData;
    }

    private function getForgettingCurveForStudent(int $learnerId, int $chapterId = 0, $syear = null)
    {
        if (!Schema::hasTable('lms_forgetting_curve') || !Schema::hasTable('lms_concept')) {
            return collect();
        }

        $query = DB::table('lms_forgetting_curve as fc')
            ->join('lms_concept as c', 'c.id', '=', 'fc.concept_id')
            ->where('fc.student_id', $learnerId);

        if ($chapterId > 0) {
            $query->where('c.chapter_id', $chapterId);
        }

        if ($syear !== null && Schema::hasColumn('lms_concept', 'syear')) {
            $query->where('c.syear', $syear);
        }

        return $query
            ->select('fc.*', 'c.name as concept_name')
            ->get();
    }

    private function getCandidateContent(
        int $standardId,
        int $subjectId,
        int $chapterId,
        int $gradeId,
        int $subInstituteId,
        $syear,
        array $preferredContentTypes,
        string $studentLevelHint,
        $profile,
        array $masteryData
    ): array {
        if (!Schema::hasTable('content_master')) {
            return [];
        }

        $query = DB::table('content_master as cm')->select('cm.*');

        if ($standardId > 0) {
            $query->where('cm.standard_id', $standardId);
        }

        if ($subjectId > 0) {
            $query->where('cm.subject_id', $subjectId);
        }

        if ($chapterId > 0) {
            $query->where('cm.chapter_id', $chapterId);
        }

        if ($gradeId > 0 && Schema::hasColumn('content_master', 'grade_id')) {
            $query->where('cm.grade_id', $gradeId);
        }

        if ($syear !== null && Schema::hasColumn('content_master', 'syear')) {
            $query->where('cm.syear', $syear);
        }

        if (Schema::hasColumn('content_master', 'sub_institute_id') && $subInstituteId > 0) {
            $query->where(function ($subQuery) use ($subInstituteId) {
                $subQuery->whereNull('cm.sub_institute_id')
                    ->orWhere('cm.sub_institute_id', $subInstituteId)
                    ->orWhere('cm.sub_institute_id', 1);
            });
        }

        if (Schema::hasColumn('content_master', 'show_hide')) {
            $query->where(function ($visibilityQuery) {
                $visibilityQuery->whereNull('cm.show_hide')
                    ->orWhere('cm.show_hide', 1);
            });
        }

        if (Schema::hasColumn('content_master', 'topic_id')) {
            $query->where(function ($topicQuery) {
                $topicQuery->whereNull('cm.topic_id')
                    ->orWhere('cm.topic_id', 0)
                    ->orWhere('cm.topic_id', '0');
            });
        }

        if (Schema::hasColumn('content_master', 'user_profile_name')) {
            $query->where(function ($profileQuery) {
                $profileQuery->whereNull('cm.user_profile_name')
                    ->orWhereRaw("LOWER(cm.user_profile_name) != 'student'");
            });
        }

        $rows = $query
            ->orderByRaw('COALESCE(cm.sort_order, 999999) ASC')
            ->orderByDesc('cm.id')
            ->limit(25)
            ->get()
            ->map(function ($row) {
                $content = (array) $row;
                $content['id'] = (int) ($content['id'] ?? 0);
                $content['mapping'] = [];
                $content['content_type'] = 'pedagogy_content';
                $content['teacher_content_category'] = $content['content_category'] ?? 'Content';
                return $content;
            })
            ->all();

        $masteryAverage = collect($masteryData)->avg('mastery_level') ?? 0;

        foreach ($rows as &$content) {
            $content['recommendation_score'] = $this->calculateContentScore(
                $content,
                (float) $masteryAverage,
                $preferredContentTypes,
                $profile,
                $studentLevelHint
            );
        }
        unset($content);

        usort($rows, function (array $left, array $right) {
            if (($left['recommendation_score'] ?? 0) === ($right['recommendation_score'] ?? 0)) {
                return ($right['id'] ?? 0) <=> ($left['id'] ?? 0);
            }

            return ($right['recommendation_score'] ?? 0) <=> ($left['recommendation_score'] ?? 0);
        });

        return $rows;
    }

    private function attachContentMappings(array $contents): array
    {
        if (empty($contents)) {
            return $contents;
        }

        if (!Schema::hasTable('content_mapping_type') || !Schema::hasTable('lms_mapping_type')) {
            return $contents;
        }

        $contentIds = array_values(array_filter(array_unique(array_map(function (array $content) {
            return $content['id'] ?? null;
        }, $contents))));

        if (empty($contentIds)) {
            return $contents;
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

        foreach ($contents as $index => $content) {
            $contentId = $content['id'] ?? null;
            $mapping = [];

            if ($contentId !== null && isset($mappingRows[$contentId])) {
                $mapping = $mappingRows[$contentId]->values()->map(function ($row) {
                    return [
                        'content_id' => $row->content_id ?? null,
                        'type_name' => $row->type_name ?? null,
                        'type_id' => $row->type_id ?? null,
                        'value_name' => $row->value_name ?? null,
                        'value_id' => $row->value_id ?? null,
                    ];
                })->all();
            }

            $contents[$index]['mapping'] = $mapping;
        }

        return $contents;
    }

    private function getSuggestedContentRows(
        int $learnerId,
        int $standardId,
        int $subjectId,
        int $chapterId,
        int $subInstituteId,
        $syear
    ): array {
        if (!Schema::hasTable('suggested_content') || !Schema::hasTable('content_master')) {
            return [];
        }

        $hasContentUserProfile = Schema::hasColumn('content_master', 'user_profile_name');
        $query = DB::table('suggested_content as sc')
            ->join('content_master as cm', 'cm.id', '=', 'sc.type_id')
            ->where('sc.type', 'pal_content')
            ->where('sc.student_id', $learnerId);

        if ($standardId > 0) {
            $query->where('sc.standard_id', $standardId);
        }

        if ($subjectId > 0) {
            $query->where('sc.subject_id', $subjectId);
        }

        if ($subInstituteId > 0) {
            $query->where('sc.sub_institute_id', $subInstituteId);
        }

        if ($syear !== null) {
            $query->where('sc.syear', $syear);
        }

        if ($chapterId > 0) {
            $query->where('cm.chapter_id', $chapterId);
        }

        if ($hasContentUserProfile) {
            $query->where(function ($profileQuery) {
                $profileQuery->whereNull('cm.user_profile_name')
                    ->orWhereRaw("LOWER(cm.user_profile_name) != 'student'");
            });
        }

        $rows = $query
            ->select('cm.*', 'sc.student_level as suggested_student_level')
            ->orderByDesc('sc.id')
            ->limit(25)
            ->get()
            ->map(function ($row) {
                $content = (array) $row;
                $content['id'] = (int) ($content['id'] ?? 0);
                $content['mapping'] = [];
                $content['content_type'] = 'pedagogy_content';
                $content['teacher_content_category'] = $content['content_category'] ?? 'Content';
                return $content;
            })
            ->all();

        return $rows;
    }

    private function categorizeContentWithPedagogy(
        array $contents,
        array $storedRemediationContents,
        array $masteryData,
        $forgettingData,
        $profile,
        array $weakConceptIds,
        array $weakConceptNames,
        array $dueConceptIds,
        array $preferredContentTypes,
        string $deviceType,
        int $learnerId
    ): array {
        $categories = $this->emptyContentData();
        $masteryAverage = collect($masteryData)->avg('mastery_level') ?? 0;
        $preferredTypes = array_map('strtolower', $preferredContentTypes);

        foreach ($contents as $content) {
            $pedagogyType = $this->inferPedagogyType(
                $content,
                (float) $masteryAverage,
                $weakConceptIds,
                $dueConceptIds
            );

            if ($pedagogyType === 'remediation') {
                continue;
            }

            $enriched = $this->enrichContentWithPedagogy(
                $content,
                $pedagogyType,
                (float) $masteryAverage,
                $profile,
                $preferredTypes
            );

            $categories[$this->mapPedagogyTypeToCategory($pedagogyType)][] = $enriched;
        }

        foreach ($storedRemediationContents as $content) {
            $categories['remediation_content'][] = $this->enrichContentWithPedagogy(
                $content,
                'remediation',
                (float) $masteryAverage,
                $profile,
                $preferredTypes
            );
        }

        if (!empty($weakConceptNames)) {
            $categories['misconception_content'] = array_values(array_merge(
                $categories['misconception_content'],
                $this->generateMisconceptionContent($weakConceptNames)
            ));
        }

        foreach ($categories as $category => $items) {
            usort($items, function (array $left, array $right) {
                if (($left['recommendation_score'] ?? 0) === ($right['recommendation_score'] ?? 0)) {
                    return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
                }

                return ($right['recommendation_score'] ?? 0) <=> ($left['recommendation_score'] ?? 0);
            });

            $categories[$category] = array_values($items);
        }

        return $categories;
    }

    private function enrichContentWithPedagogy(
        array $content,
        string $pedagogyType,
        float $masteryAverage,
        $profile,
        array $preferredContentTypes
    ): array {
        $content['pedagogy_type'] = $pedagogyType;
        $content['recommended_pedagogy'] = $this->getPedagogyForType($pedagogyType);
        $content['student_level'] = $this->getLevelFromMastery($masteryAverage);
        $content['learning_tips'] = $this->getLearningTips($pedagogyType);
        $content['content_type'] = $pedagogyType === 'misconception'
            ? 'misconception_content'
            : 'pedagogy_content';
        $content['recommendation_score'] = $content['recommendation_score'] ?? $this->calculateContentScore(
            $content,
            $masteryAverage,
            $preferredContentTypes,
            $profile,
            ''
        );

        return $content;
    }

    private function inferPedagogyType(array $content, float $masteryAverage, array $weakConceptIds, array $dueConceptIds): string
    {
        $title = strtolower((string) ($content['title'] ?? ''));
        $category = strtolower((string) ($content['content_category'] ?? ''));
        $pedagogyTag = strtolower((string) ($content['pedagogy_tag'] ?? ''));
        $fileType = strtolower((string) ($content['file_type'] ?? ''));
        $difficulty = (int) ($content['difficulty_level'] ?? 0);

        if (
            str_contains($title, 'misconception')
            || str_contains($category, 'misconception')
            || str_contains($pedagogyTag, 'misconception')
        ) {
            return 'misconception';
        }

        if (
            $masteryAverage < 40
            || str_contains($category, 'remediation')
            || str_contains($pedagogyTag, 'remediation')
            || str_contains($fileType, 'worksheet')
        ) {
            return 'remediation';
        }

        if (
            !empty($dueConceptIds)
            || str_contains($category, 'review')
            || str_contains($pedagogyTag, 'review')
            || str_contains($fileType, 'quiz')
        ) {
            return 'review';
        }

        if (
            $masteryAverage >= 70
            || str_contains($category, 'enrichment')
            || str_contains($pedagogyTag, 'enrichment')
            || $difficulty >= 4
        ) {
            return 'enrichment';
        }

        return 'practice';
    }

    private function mapPedagogyTypeToCategory(string $pedagogyType): string
    {
        return match ($pedagogyType) {
            'remediation' => 'remediation_content',
            'enrichment' => 'enrichment_content',
            'misconception' => 'misconception_content',
            'review', 'concept-based', 'practice' => 'practice_content',
            default => 'practice_content',
        };
    }

    private function generateMisconceptionContent(array $conceptNames): array
    {
        $content = [];

        foreach ($conceptNames as $conceptName) {
            $content[] = [
                'id' => 'misconception_' . uniqid(),
                'title' => 'Common Misconceptions: ' . $conceptName,
                'description' => 'Many students struggle with ' . $conceptName . '. Here are the common mistakes to avoid.',
                'content_type' => 'misconception_content',
                'pedagogy_type' => 'misconception',
                'student_level' => 'esay',
                'recommended_pedagogy' => $this->getPedagogyForType('misconception'),
                'mapping' => [
                    [
                        'type_name' => 'Pedagogy',
                        'type_id' => null,
                        'value_name' => 'Misconception Clarification',
                        'value_id' => null,
                    ],
                ],
                'learning_tips' => $this->getLearningTips('misconception'),
                'recommendation_score' => 95,
            ];
        }

        return $content;
    }

    private function generatePedagogyRecommendations(
        $profile,
        array $masteryData,
        array $weakConceptIds,
        array $weakConceptNames,
        array $dueConceptIds,
        string $deviceType,
        int $learnerId
    ): array {
        $recommendations = [];
        $learningStyle = strtolower((string) ($profile->learning_style ?? 'visual'));
        $styleTips = [
            'visual' => 'Use diagrams, charts, and short videos',
            'auditory' => 'Use audio explanations and discussion',
            'kinesthetic' => 'Use hands-on activities and simulations',
            'reading' => 'Use text-based materials and written exercises',
        ];

        $recommendations[] = [
            'type' => 'learning_style',
            'label' => 'Learning Style: ' . $this->formatLevelLabel($learningStyle),
            'reason' => 'You learn best through ' . $learningStyle . ' content',
            'cognitive_load_display' => 'Low',
            'tip' => $styleTips[$learningStyle] ?? 'Use a mix of content types',
        ];

        if (!empty($weakConceptNames)) {
            $recommendations[] = [
                'type' => 'focus_area',
                'label' => 'Focus Areas: ' . implode(', ', $weakConceptNames),
                'reason' => 'These concepts need immediate attention (mastery below 40%)',
                'cognitive_load_display' => 'High',
                'tip' => 'Start with the easiest concept first',
            ];
        }

        if (!empty($dueConceptIds)) {
            $dueConceptNames = $this->getConceptNamesByIds($dueConceptIds);

            $recommendations[] = [
                'type' => 'review',
                'label' => 'Review Due: ' . implode(', ', $dueConceptNames),
                'reason' => 'These concepts are due for review based on the forgetting curve',
                'cognitive_load_display' => 'Medium',
                'tip' => 'Review these before moving to new concepts',
            ];
        }

        $avgMastery = collect($masteryData)->avg('mastery_level') ?? 0;
        if ($avgMastery >= 70) {
            $recommendations[] = [
                'type' => 'status',
                'label' => 'You are on track',
                'reason' => 'Overall mastery is ' . round($avgMastery) . '%',
                'cognitive_load_display' => 'Low',
                'tip' => 'Keep up the good work and try advanced content.',
            ];
        } elseif ($avgMastery >= 40) {
            $recommendations[] = [
                'type' => 'status',
                'label' => 'Keep practicing',
                'reason' => 'Overall mastery is ' . round($avgMastery) . '%',
                'cognitive_load_display' => 'Medium',
                'tip' => 'Focus on the concepts that need the most improvement.',
            ];
        } else {
            $recommendations[] = [
                'type' => 'status',
                'label' => 'Build your foundation',
                'reason' => 'Overall mastery is ' . round($avgMastery) . '%',
                'cognitive_load_display' => 'High',
                'tip' => 'Start with the basics and move up gradually.',
            ];
        }

        if ($this->pedagogy && !empty($weakConceptIds)) {
            $engineRecommendation = $this->pedagogy->getRecommendation(
                $learnerId,
                (int) $weakConceptIds[0],
                ['device_type' => $deviceType]
            );

            $recommendedType = $engineRecommendation['recommended_pedagogy']['type'] ?? 'concept-based';

            $recommendations[] = [
                'type' => $recommendedType,
                'label' => 'PAL Engine Suggestion: ' . $this->formatLevelLabel($recommendedType),
                'reason' => $engineRecommendation['selection_reason'] ?? 'Selected by the pedagogy engine',
                'cognitive_load_display' => $engineRecommendation['cognitive_load_display'] ?? 'Not enough data',
                'tip' => 'Sequence: ' . implode(' -> ', array_map(function ($item) {
                    return $item['type'] ?? 'fallback';
                }, $engineRecommendation['sequence'] ?? [])),
            ];
        }

        return $recommendations;
    }

    private function generateTeacherInsights(
        array $contentRows,
        array $masteryData,
        array $weakConceptNames,
        array $dueConceptIds
    ): array {
        $insights = [];
        $avgMastery = collect($masteryData)->avg('mastery_level') ?? 0;

        if (empty($contentRows)) {
            $insights[] = [
                'type' => 'info',
                'title' => 'No mapped content found',
                'description' => 'There is no content_master record for this chapter, standard, and subject combination yet.',
                'priority' => 'medium',
            ];
        }

        if ($avgMastery < 40) {
            $insights[] = [
                'type' => 'alert',
                'title' => 'Strengthen Foundations',
                'description' => 'Overall mastery is ' . round($avgMastery) . '%. Start with remediation and concept building.',
                'priority' => 'high',
            ];
        } elseif ($avgMastery >= 70) {
            $insights[] = [
                'type' => 'status',
                'title' => 'Ready for Enrichment',
                'description' => 'Overall mastery is ' . round($avgMastery) . '%. Introduce advanced applications and deeper practice.',
                'priority' => 'low',
            ];
        }

        if (!empty($weakConceptNames)) {
            $insights[] = [
                'type' => 'remediation',
                'title' => 'Focus Areas',
                'description' => 'Needs practice on: ' . implode(', ', $weakConceptNames),
                'priority' => 'high',
            ];
        }

        if (!empty($dueConceptIds)) {
            $dueConceptNames = $this->getConceptNamesByIds($dueConceptIds);
            $insights[] = [
                'type' => 'review',
                'title' => 'Review Queue',
                'description' => 'Due concepts: ' . implode(', ', $dueConceptNames),
                'priority' => 'medium',
            ];
        }

        return $insights;
    }

    private function getConceptNamesByIds(array $conceptIds): array
    {
        if (empty($conceptIds) || !Schema::hasTable('lms_concept')) {
            return array_map('strval', $conceptIds);
        }

        return DB::table('lms_concept')
            ->whereIn('id', $conceptIds)
            ->pluck('name')
            ->map(function ($name) {
                return (string) $name;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function getPedagogyForType(string $type): array
    {
        $pedagogyMap = [
            'remediation' => [
                'label' => 'Concept Building',
                'method' => 'Scaffolded Learning',
                'approach' => 'Break down complex concepts into smaller parts',
            ],
            'practice' => [
                'label' => 'Active Practice',
                'method' => 'Spaced Repetition',
                'approach' => 'Practice with increasing difficulty',
            ],
            'review' => [
                'label' => 'Review & Reinforcement',
                'method' => 'Retrieval Practice',
                'approach' => 'Active recall of previously learned concepts',
            ],
            'enrichment' => [
                'label' => 'Deep Dive',
                'method' => 'Inquiry-Based Learning',
                'approach' => 'Explore advanced applications and connections',
            ],
            'misconception' => [
                'label' => 'Misconception Clarification',
                'method' => 'Diagnostic Feedback',
                'approach' => 'Surface and correct common errors',
            ],
            'concept-based' => [
                'label' => 'Concept Building',
                'method' => 'Scaffolded Learning',
                'approach' => 'Break down complex concepts into smaller parts',
            ],
        ];

        return $pedagogyMap[$type] ?? $pedagogyMap['practice'];
    }

    private function getLearningTips(string $type): array
    {
        $tips = [
            'remediation' => [
                'Start with the basics',
                'Take notes while reading',
                'Practice each step before moving on',
            ],
            'practice' => [
                'Set a timer for focused practice',
                'Track your progress',
                'Focus on one concept at a time',
            ],
            'review' => [
                'Test yourself without looking at notes',
                'Write down what you remember first',
                'Review multiple times with breaks',
            ],
            'enrichment' => [
                'Connect ideas to real-world examples',
                'Ask what-if questions',
                'Explore additional resources',
            ],
            'misconception' => [
                'Compare the wrong idea with the correct one',
                'Look for the assumption behind the mistake',
                'Practice a few corrected examples',
            ],
            'concept-based' => [
                'Start with the core definition',
                'Use one example and one non-example',
                'Build up one step at a time',
            ],
        ];

        return $tips[$type] ?? $tips['practice'];
    }

    private function getLevelFromMastery(float $mastery): string
    {
        if ($mastery >= 70) {
            return 'advanced';
        }

        if ($mastery >= 40) {
            return 'intermediate';
        }

        return 'esay';
    }

    private function formatLevelLabel(string $level): string
    {
        $normalized = strtolower(trim($level));

        return match ($normalized) {
            'advanced' => 'Advanced',
            'intermediate' => 'Intermediate',
            'esay' => 'esay',
            'visual' => 'Visual',
            'auditory' => 'Auditory',
            'kinesthetic' => 'Kinesthetic',
            'reading' => 'Reading',
            'concept-based' => 'Concept Based',
            default => ucfirst(str_replace(['_', '-'], ' ', $normalized)),
        };
    }

    private function getStudentLevel(array $masteryData): string
    {
        $avgMastery = collect($masteryData)->avg('mastery_level') ?? 0;

        return $this->getLevelFromMastery((float) $avgMastery);
    }

    private function calculateContentScore(
        array $content,
        float $masteryAverage,
        array $preferredContentTypes,
        $profile,
        string $studentLevelHint
    ): int {
        $score = 50;
        $fileType = strtolower((string) ($content['file_type'] ?? ''));
        $category = strtolower((string) ($content['content_category'] ?? ''));
        $pedagogyTag = strtolower((string) ($content['pedagogy_tag'] ?? ''));
        $title = strtolower((string) ($content['title'] ?? ''));
        $learningStyle = strtolower((string) ($profile->learning_style ?? ''));
        $normalizedPreferredTypes = array_map('strtolower', $preferredContentTypes);

        foreach ($normalizedPreferredTypes as $preferredType) {
            if ($preferredType === '') {
                continue;
            }

            if (
                str_contains($fileType, $preferredType)
                || str_contains($category, $preferredType)
                || str_contains($pedagogyTag, $preferredType)
            ) {
                $score += 20;
                break;
            }
        }

        $styleBoosts = [
            'visual' => ['video', 'image', 'pdf', 'slides'],
            'auditory' => ['audio', 'podcast', 'video'],
            'kinesthetic' => ['interactive', 'lab', 'simulation'],
            'reading' => ['text', 'pdf', 'doc', 'document'],
        ];

        foreach ($styleBoosts[$learningStyle] ?? [] as $needle) {
            if ($needle !== '' && str_contains($fileType, $needle)) {
                $score += 10;
                break;
            }
        }

        if ($masteryAverage < 40) {
            if (str_contains($category, 'remediation') || str_contains($pedagogyTag, 'remediation')) {
                $score += 20;
            }

            if ((int) ($content['difficulty_level'] ?? 0) <= 2) {
                $score += 10;
            }
        } elseif ($masteryAverage >= 70) {
            if (str_contains($category, 'enrichment') || str_contains($pedagogyTag, 'enrichment')) {
                $score += 20;
            }

            if ((int) ($content['difficulty_level'] ?? 0) >= 3) {
                $score += 10;
            }
        } else {
            if (str_contains($category, 'practice') || str_contains($category, 'review')) {
                $score += 15;
            }
        }

        if ($studentLevelHint !== '' && str_contains($title, $studentLevelHint)) {
            $score += 5;
        }

        return max(0, $score);
    }

    private function decodeJsonArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, function ($item) {
                return $item !== null && $item !== '';
            }));
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, function ($item) {
                    return $item !== null && $item !== '';
                }));
            }

            $trimmed = trim($value, "[]");
            if (str_contains($trimmed, ',')) {
                return array_values(array_filter(array_map('trim', explode(',', $trimmed))));
            }

            return [$value];
        }

        return [];
    }

    private function emptyContentData(): array
    {
        return [
            'remediation_content' => [],
            'practice_content' => [],
            'enrichment_content' => [],
            'misconception_content' => [],
        ];
    }
}
