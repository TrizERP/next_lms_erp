<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\LmsLessonPlan;
use App\Models\lms\LmsLessonPlanDayWise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Write side of the Lesson Plan document (create + edit), backing the new ERP's
 * lesson-plan editor. Ports `lms_lessonplanController::store()` (the old Blade
 * screen's only write path) as a JSON API: same upsert-by-id, same five required
 * fields, same day-wise diff-delete, same CSV-of-ids storage for the three activity
 * fields - but reading standard/subject/chapter/sub_institute_id from the validated
 * request body instead of a web session, the way the sibling read controller
 * (LessonPlanDocumentApiController::show()) already does.
 *
 * Deliberately a separate file from the read controller so that one's "deliberately
 * read-only" docblock stays true.
 */
class LessonPlanDocumentWriteApiController extends Controller
{
    /**
     * POST /api/lesson-plan-doc/save
     */
    public function save(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'                                 => 'nullable|integer|exists:lms_lesson_plan,id',
            'sub_institute_id'                   => 'required|integer',
            'syear'                               => 'required|integer',
            'standard_id'                         => 'required|integer',
            'subject_id'                          => 'required|integer',
            'chapter_id'                          => 'required|integer',
            'topic_id'                            => 'nullable|integer',

            'summary.number_of_periods'           => 'nullable|integer',
            'summary.teaching_time'               => 'nullable|string|max:50',
            'summary.assessment_time'             => 'nullable|string|max:50',
            'summary.learning_time'               => 'nullable|string|max:50',
            'summary.assessment_qualifying'       => 'nullable|string',
            'summary.mapping'                     => 'nullable|array',
            'summary.mapping.*.type_id'           => 'required_with:summary.mapping|integer',
            'summary.mapping.*.value_id'          => 'required_with:summary.mapping|integer',

            'teaching.focus_point'                => 'required|string',
            'teaching.pedagogical_process'        => 'required|string',
            'teaching.resource'                   => 'required|string',
            'teaching.classroom_presentation'     => 'required|string',
            'teaching.classroom_diversity'        => 'required|string',
            'teaching.classroom_activity_ids'     => 'nullable|array',
            'teaching.classroom_activity_ids.*'   => 'integer',

            'learning.learning_objective'         => 'nullable|string',
            'learning.learning_knowledge'         => 'nullable|string',
            'learning.learning_skill'             => 'nullable|string',
            'learning.prerequisite'               => 'nullable|string',
            'learning.self_study_homework'        => 'nullable|string',
            'learning.self_study_activity_ids'    => 'nullable|array',
            'learning.self_study_activity_ids.*'  => 'integer',
            'learning.assessment'                 => 'nullable|string',
            'learning.assessment_activity_ids'    => 'nullable|array',
            'learning.assessment_activity_ids.*'  => 'integer',

            'map_and_alignment.hard_word'             => 'nullable|string',
            'map_and_alignment.tag_metatag'           => 'nullable|string',
            'map_and_alignment.value_integration'     => 'nullable|string',
            'map_and_alignment.global_connection'     => 'nullable|string',
            'map_and_alignment.sel'                   => 'nullable|string',
            'map_and_alignment.stem'                  => 'nullable|string',
            'map_and_alignment.vocational_training'   => 'nullable|string',
            'map_and_alignment.simulation'            => 'nullable|string',
            'map_and_alignment.games'                 => 'nullable|string',
            'map_and_alignment.activities'            => 'nullable|string',
            'map_and_alignment.real_life_application' => 'nullable|string',

            'days'                                 => 'nullable|array',
            'days.*.day'                           => 'required|integer|min:1',
            'days.*.topic_name'                    => 'nullable|string|max:150',
            'days.*.class_time'                    => 'nullable|string|max:50',
            'days.*.during_content'                => 'nullable|string',
            'days.*.assessment_qualifying'         => 'nullable|string',
            'days.*.learning_objective'            => 'nullable|string',
            'days.*.learning_outcome'              => 'nullable|string',
            'days.*.pedagogical_process'           => 'nullable|string',
            'days.*.resource'                      => 'nullable|string',
            'days.*.closure'                       => 'nullable|string',
            'days.*.self_study_homework'           => 'nullable|string',
            'days.*.self_study_activity_ids'       => 'nullable|array',
            'days.*.self_study_activity_ids.*'     => 'integer',
            'days.*.assessment'                    => 'nullable|string',
            'days.*.assessment_activity_ids'       => 'nullable|array',
            'days.*.assessment_activity_ids.*'     => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $planId = DB::transaction(function () use ($validated) {
                $plan = LmsLessonPlan::find($validated['id'] ?? null) ?? new LmsLessonPlan();

                $plan->sub_institute_id = $validated['sub_institute_id'];
                $plan->syear = $validated['syear'];
                $plan->standard_id = $validated['standard_id'];
                $plan->subject_id = $validated['subject_id'];
                $plan->chapter_id = $validated['chapter_id'];
                $plan->topic_id = $validated['topic_id'] ?? null;

                $plan->numberofperiod = data_get($validated, 'summary.number_of_periods');
                $plan->teachingtime = data_get($validated, 'summary.teaching_time');
                $plan->assessmenttime = data_get($validated, 'summary.assessment_time');
                $plan->learningtime = data_get($validated, 'summary.learning_time');
                $plan->assessmentqualifying = data_get($validated, 'summary.assessment_qualifying');

                $plan->focauspoint = data_get($validated, 'teaching.focus_point');
                $plan->pedagogicalprocess = data_get($validated, 'teaching.pedagogical_process');
                $plan->resource = data_get($validated, 'teaching.resource');
                $plan->classroompresentation = data_get($validated, 'teaching.classroom_presentation');
                $plan->classroomactivity = implode(',', data_get($validated, 'teaching.classroom_activity_ids', []));
                $plan->classroomdiversity = data_get($validated, 'teaching.classroom_diversity');

                $plan->prerequisite = data_get($validated, 'learning.prerequisite');
                $plan->learningobjective = data_get($validated, 'learning.learning_objective');
                $plan->learningknowledge = data_get($validated, 'learning.learning_knowledge');
                $plan->learningskill = data_get($validated, 'learning.learning_skill');
                $plan->selfstudyhomework = data_get($validated, 'learning.self_study_homework');
                $plan->selfstudyactivity = implode(',', data_get($validated, 'learning.self_study_activity_ids', []));
                $plan->assessment = data_get($validated, 'learning.assessment');
                $plan->assessmentactivity = implode(',', data_get($validated, 'learning.assessment_activity_ids', []));

                $plan->hardword = data_get($validated, 'map_and_alignment.hard_word');
                $plan->tagmetatag = data_get($validated, 'map_and_alignment.tag_metatag');
                $plan->valueintegration = data_get($validated, 'map_and_alignment.value_integration');
                $plan->globalconnection = data_get($validated, 'map_and_alignment.global_connection');
                $plan->sel = data_get($validated, 'map_and_alignment.sel');
                $plan->stem = data_get($validated, 'map_and_alignment.stem');
                $plan->vocationaltraining = data_get($validated, 'map_and_alignment.vocational_training');
                $plan->simulation = data_get($validated, 'map_and_alignment.simulation');
                $plan->games = data_get($validated, 'map_and_alignment.games');
                $plan->activities = data_get($validated, 'map_and_alignment.activities');
                $plan->reallifeapplication = data_get($validated, 'map_and_alignment.real_life_application');

                // mapping_value has no migration in this repo even though the old controller
                // reads/writes it (live columns here are known to drift from migrations) -
                // check it exists before touching it, same guard the read side uses.
                if (Schema::hasColumn('lms_lesson_plan', 'mapping_value')) {
                    $mapping = [];
                    foreach (data_get($validated, 'summary.mapping', []) as $entry) {
                        $mapping[$entry['type_id']] = $entry['value_id'];
                    }
                    $plan->mapping_value = $mapping ? json_encode($mapping) : null;
                }

                $plan->save();

                $existingDays = LmsLessonPlanDayWise::where('lpid', $plan->id)
                    ->pluck('days')
                    ->map(fn ($day) => (int) $day)
                    ->all();
                $inputDays = collect($validated['days'] ?? [])
                    ->pluck('day')
                    ->map(fn ($day) => (int) $day)
                    ->all();
                $daysToDelete = array_diff($existingDays, $inputDays);
                if ($daysToDelete) {
                    LmsLessonPlanDayWise::whereIn('days', $daysToDelete)->where('lpid', $plan->id)->delete();
                }

                foreach ($validated['days'] ?? [] as $dayInput) {
                    $row = LmsLessonPlanDayWise::where('days', $dayInput['day'])
                        ->where('lpid', $plan->id)
                        ->first() ?? new LmsLessonPlanDayWise();

                    $row->sub_institute_id = $validated['sub_institute_id'];
                    $row->lpid = $plan->id;
                    $row->days = $dayInput['day'];
                    $row->topicname = $dayInput['topic_name'] ?? null;
                    $row->classtime = $dayInput['class_time'] ?? null;
                    $row->duringcontent = $dayInput['during_content'] ?? null;
                    $row->assessmentqualifying = $dayInput['assessment_qualifying'] ?? null;
                    $row->learningobjective = $dayInput['learning_objective'] ?? null;
                    $row->learningoutcome = $dayInput['learning_outcome'] ?? null;
                    $row->pedagogicalprocess = $dayInput['pedagogical_process'] ?? null;
                    $row->resource = $dayInput['resource'] ?? null;
                    $row->closure = $dayInput['closure'] ?? null;
                    $row->selfstudyhomework = $dayInput['self_study_homework'] ?? null;
                    $row->selfstudyactivity = implode(',', $dayInput['self_study_activity_ids'] ?? []);
                    $row->assessment = $dayInput['assessment'] ?? null;
                    $row->assessmentactivity = implode(',', $dayInput['assessment_activity_ids'] ?? []);
                    $row->save();
                }

                return $plan->id;
            });

            return response()->json([
                'status'  => true,
                'message' => 'Lesson plan saved successfully',
                'data'    => ['id' => $planId],
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanDocument save failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while saving the lesson plan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
