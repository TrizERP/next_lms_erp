<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\contentModel;
use App\Models\lms\LmsLessonPlan;
use App\Models\lms\questionpaperModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Read-only JSON view of the static, manually-authored Lesson Plan (standard +
 * subject + chapter -> focus point, pedagogical process, learning outcomes,
 * day-wise planning, map & alignment, ...). Backed by lms_lesson_plan /
 * lms_lessonplan_dayswise, the same tables the old Blade screen
 * (lms_lessonplanController) reads and writes.
 *
 * Deliberately unrelated to the other `intelligence/lesson-plan-*` routes in
 * this directory, which belong to the separate AI "Lesson Intelligence"
 * calendar engine (lms_intelligence_lesson_plans and friends) - do not point
 * this at, or merge this with, those tables/controllers.
 */
class LessonPlanDocumentApiController extends Controller
{
    /**
     * GET|POST /api/lesson-plan-doc
     */
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer',
            'chapter_id'       => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        try {
            $plan = LmsLessonPlan::with(['standard', 'subject', 'chapter', 'topic', 'lessonDays'])
                ->where('sub_institute_id', $filters['sub_institute_id'])
                ->where('standard_id', $filters['standard_id'])
                ->where('subject_id', $filters['subject_id'])
                ->where('chapter_id', $filters['chapter_id'])
                ->first();

            if ($plan === null) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No lesson plan has been authored for this chapter yet',
                    'data'    => null,
                ], 404);
            }

            $activityIds = collect($this->splitIds($plan->classroomactivity))
                ->merge($this->splitIds($plan->selfstudyactivity))
                ->merge($plan->lessonDays->flatMap(fn ($day) => $this->splitIds($day->selfstudyactivity)))
                ->unique()
                ->values();
            $questionIds = collect($this->splitIds($plan->assessmentactivity))
                ->merge($plan->lessonDays->flatMap(fn ($day) => $this->splitIds($day->assessmentactivity)))
                ->unique()
                ->values();

            $contentTitles = $activityIds->isNotEmpty()
                ? contentModel::whereIn('id', $activityIds)->pluck('title', 'id')
                : collect();
            $questionTitles = $questionIds->isNotEmpty()
                ? questionpaperModel::whereIn('id', $questionIds)->pluck('paper_name', 'id')
                : collect();

            $resolveContent = fn (?string $csv) => $this->resolveActivities($csv, $contentTitles);
            $resolveQuestion = fn (?string $csv) => $this->resolveActivities($csv, $questionTitles);

            return response()->json([
                'status'  => true,
                'message' => 'Lesson plan found',
                'data'    => [
                    'id'                   => $plan->id,
                    'standard_id'          => $plan->standard_id,
                    'standard_name'        => $plan->standard?->name,
                    'subject_id'           => $plan->subject_id,
                    'subject_name'         => $plan->subject?->subject_name,
                    'chapter_id'           => $plan->chapter_id,
                    'chapter_name'         => $plan->chapter?->chapter_name,
                    'topic_id'             => $plan->topic_id,
                    'topic_name'           => $plan->topic?->name,
                    'summary'              => [
                        'number_of_periods'       => $plan->numberofperiod,
                        'teaching_time'           => $plan->teachingtime,
                        'assessment_time'         => $plan->assessmenttime,
                        'learning_time'           => $plan->learningtime,
                        'assessment_qualifying'   => $plan->assessmentqualifying,
                        'mapping'                 => $this->resolveMapping($plan),
                    ],
                    'teaching'             => [
                        'focus_point'              => $plan->focauspoint,
                        'pedagogical_process'      => $plan->pedagogicalprocess,
                        'resource'                 => $plan->resource,
                        'classroom_presentation'   => $plan->classroompresentation,
                        'classroom_diversity'      => $plan->classroomdiversity,
                        'classroom_activity'       => $resolveContent($plan->classroomactivity),
                    ],
                    'learning'             => [
                        'learning_objective'     => $plan->learningobjective,
                        'learning_knowledge'     => $plan->learningknowledge,
                        'learning_skill'         => $plan->learningskill,
                        'prerequisite'           => $plan->prerequisite,
                        'self_study_homework'    => $plan->selfstudyhomework,
                        'self_study_activity'    => $resolveContent($plan->selfstudyactivity),
                        'assessment'             => $plan->assessment,
                        'assessment_activity'    => $resolveQuestion($plan->assessmentactivity),
                    ],
                    'days'                 => $plan->lessonDays->map(fn ($day) => [
                        'day'                     => $day->days,
                        'topic_name'              => $day->topicname,
                        'class_time'              => $day->classtime,
                        'during_content'          => $day->duringcontent,
                        'assessment_qualifying'   => $day->assessmentqualifying,
                        'learning_objective'      => $day->learningobjective,
                        'learning_outcome'        => $day->learningoutcome,
                        'pedagogical_process'     => $day->pedagogicalprocess,
                        'resource'                => $day->resource,
                        'closure'                 => $day->closure,
                        'self_study_homework'     => $day->selfstudyhomework,
                        'self_study_activity'     => $resolveContent($day->selfstudyactivity),
                        'assessment'              => $day->assessment,
                        'assessment_activity'     => $resolveQuestion($day->assessmentactivity),
                    ])->values(),
                    'map_and_alignment'    => [
                        'hard_word'               => $plan->hardword,
                        'tag_metatag'             => $plan->tagmetatag,
                        'value_integration'       => $plan->valueintegration,
                        'global_connection'       => $plan->globalconnection,
                        // Never populated by the old authoring UI (no input control exists
                        // for it there) - always null today, kept so the field is not silently
                        // dropped from the response.
                        'cross_curriculum'        => $plan->crosscurriculum,
                        'sel'                     => $plan->sel,
                        'stem'                    => $plan->stem,
                        'vocational_training'     => $plan->vocationaltraining,
                        'simulation'              => $plan->simulation,
                        'games'                   => $plan->games,
                        'activities'              => $plan->activities,
                        'real_life_application'   => $plan->reallifeapplication,
                    ],
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanDocument fetch failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching the lesson plan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * mapping_value has no migration in this repo even though the old controller
     * reads/writes it (live columns here are known to drift from migrations) -
     * check it exists before touching it instead of assuming.
     */
    private function resolveMapping(LmsLessonPlan $plan): array
    {
        if (!Schema::hasColumn('lms_lesson_plan', 'mapping_value') || empty($plan->mapping_value)) {
            return [];
        }

        $decoded = json_decode($plan->mapping_value, true);
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        $typeIds = array_map('intval', array_keys($decoded));
        $valueIds = array_map('intval', array_values($decoded));

        $types = DB::table('lms_mapping_type')->whereIn('id', $typeIds)->pluck('name', 'id');
        $values = DB::table('lms_mapping_type')->whereIn('id', $valueIds)->pluck('name', 'id');

        $result = [];
        foreach ($decoded as $typeId => $valueId) {
            $result[] = [
                'type'     => $types->get((int) $typeId),
                'value'    => $values->get((int) $valueId),
                // Additive: the names above are all the read-only document view ever
                // needed, but the editor's mapping dropdowns need the ids back to
                // pre-select them - this does not change the existing shape, it only
                // adds fields an older consumer would simply ignore.
                'type_id'  => (int) $typeId,
                'value_id' => (int) $valueId,
            ];
        }

        return $result;
    }

    private function splitIds(?string $csv): array
    {
        if (empty($csv)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), fn ($id) => $id !== ''));
    }

    private function resolveActivities(?string $csv, $titles): array
    {
        $ids = $this->splitIds($csv);
        if (empty($ids)) {
            return [];
        }

        return array_values(array_map(fn ($id) => [
            'id'    => (int) $id,
            'title' => $titles->get((int) $id),
        ], $ids));
    }
}
