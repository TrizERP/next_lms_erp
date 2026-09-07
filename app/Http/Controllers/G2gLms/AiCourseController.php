<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Models\G2gLms\AiCourseOutline;
use App\Services\GammaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * "Build with AI" — Course Builder's outline generator + Gamma deck renderer.
 * G2G LMS migration (Package 3).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\AiCourseController`. Two
 * adaptations from the source, both driven by what this repo already has:
 *
 *   - Gamma: reuses this repo's OWN `App\Services\GammaService` (already used
 *     elsewhere in this codebase) rather than porting hp_erp's. Its API
 *     shape differs — `generatePresentation(array $params)` returns the raw
 *     Gamma JSON directly (not a normalised `{generationId}` array), and it
 *     has no non-blocking "read one status" call (`pollStatus()` loops with
 *     `sleep()` for up to 150s, unsuitable for an endpoint the frontend
 *     itself polls every 4s) — so `generationStatus()` below calls the Gamma
 *     API directly for a single status read, the same way `GammaService`
 *     does internally.
 *   - DeepSeek: no `DeepSeekService` class exists in this repo (only
 *     `config/deepseek.php`, used by the question-generation feature), so
 *     `chatJson()` below is a deliberately SIMPLIFIED inline port — the
 *     chat-completions call and JSON extraction only. hp_erp's balance-floor
 *     guard, blank-completion retry-with-perturbation loop and per-call usage
 *     accounting are NOT reproduced; a straightforward failure raises once
 *     rather than retrying. Flagged as a simplification in the task report.
 *
 * Course authoring (generate/generatePresentation/publish) is gated the same
 * way Course Builder's audience assignment is — `isLmsStaffAdmin()` — since
 * this repo's role model has no `guardLmsProfile(['admin','hr'])` equivalent
 * of its own for this controller to reuse cleanly without pulling in the
 * whole hp_erp-shaped identity surface Package 1 added to
 * `ResolvesLmsIdentity` for a different controller family.
 */
class AiCourseController extends Controller
{
    use ResolvesLmsIdentity;

    public function __construct(private readonly GammaService $gamma)
    {
    }

    private function guardAuthoring(array $context)
    {
        return $this->isLmsStaffAdmin($context)
            ? null
            : $this->lmsError('Your profile is not permitted to author courses.', 403);
    }

    /** GET /api/g2g-lms/course-builder/ai/status */
    public function status(Request $request)
    {
        return $this->lmsOk([
            'deepseek_configured' => (bool) config('deepseek.api_key'),
            'deepseek_model' => (string) config('deepseek.model'),
            'gamma_configured' => (bool) env('GAMMA_API_KEY'),
        ]);
    }

    /** POST /api/g2g-lms/course-builder/ai/outline */
    public function generateOutline(Request $request)
    {
        $context = $this->lmsContext($request);
        if ($denied = $this->guardAuthoring($context)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'industry' => 'nullable|string|max:191',
            'department' => 'nullable|string|max:191',
            'job_role' => 'nullable|string|max:191',
            'critical_work_function' => 'nullable|string|max:500',
            'tasks' => 'nullable|array',
            'tasks.*' => 'string|max:500',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:191',
            'proficiency' => 'nullable|string|max:100',
            'modality' => 'nullable|array',
            'course_title' => 'nullable|string|max:191',
            'slide_count' => 'nullable|integer|min:3|max:40',
            'model' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $slideCount = (int) ($request->input('slide_count') ?: 10);

        try {
            $outline = $this->chatJson(
                [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert instructional designer and L&D specialist. '
                            . 'You design competency-based corporate training. '
                            . 'Reply with a single valid JSON object and nothing else.',
                    ],
                    ['role' => 'user', 'content' => $this->buildOutlinePrompt($request, $slideCount)],
                ],
                $request->input('model') ?: null
            );

            $normalised = $this->normaliseOutline($outline, $slideCount);

            return $this->lmsOk([
                'outline' => $normalised,
                'plain_text' => $this->outlineToPlainText($normalised),
                'model' => $request->input('model') ?: config('deepseek.model'),
                'slide_count' => count($normalised['slides']),
            ], 'Course outline generated successfully');
        } catch (RuntimeException $e) {
            return $this->lmsError($e->getMessage(), 502);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to generate the course outline', 'error' => $e->getMessage()], 500);
        }
    }

    private function buildOutlinePrompt(Request $request, int $slideCount): string
    {
        $modality = $request->input('modality', []);
        $modalityLabels = [];
        if (! empty($modality['selfPaced'])) {
            $modalityLabels[] = 'Self-paced';
        }
        if (! empty($modality['instructorLed'])) {
            $modalityLabels[] = 'Instructor-led';
        }
        $modalityText = implode(', ', $modalityLabels) ?: '-';

        $tasks = array_filter((array) $request->input('tasks', []));
        $skills = array_filter((array) $request->input('skills', []));

        $prompt = [
            'instruction' => "You are an expert instructional designer and L&D specialist. Create a structured {$slideCount}-slide course based on the provided context.",
            'input_variables' => [
                'industry' => $request->input('industry') ?: '-',
                'department' => $request->input('department') ?: '-',
                'job_role' => $request->input('job_role') ?: '-',
                'critical_work_function' => $request->input('critical_work_function') ?: '-',
                'key_tasks' => ! empty($tasks) ? array_values($tasks) : ['-'],
                'target_skills' => ! empty($skills) ? array_values($skills) : ['-'],
                'proficiency' => $request->input('proficiency') ?: '-',
                'modality' => $modalityText,
                'preferred_title' => $request->input('course_title') ?: null,
            ],
            'output_format' => [
                'total_slides' => $slideCount,
                'bullet_points_per_slide' => '3-5 (under 40 words each)',
                'style' => 'Formal, structured, competency-based',
                'tone' => str_contains($modalityText, 'Self-paced') ? 'Direct, learner-led tone' : 'Facilitator-focused guidance',
            ],
            'response_schema' => [
                'title' => 'string - the course title',
                'summary' => 'string - 2 sentence overview',
                'learning_objectives' => 'array of 3-5 strings',
                'slides' => 'array of exactly ' . $slideCount . ' objects, each {slide_number:int, title:string, bullets:array of 3-5 strings, speaker_notes:string}',
            ],
        ];

        return json_encode($prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $outline */
    private function normaliseOutline(array $outline, int $slideCount): array
    {
        $slides = $outline['slides'] ?? $outline['Slides'] ?? [];
        if (! is_array($slides)) {
            $slides = [];
        }

        $normalisedSlides = [];
        foreach (array_values($slides) as $index => $slide) {
            if (! is_array($slide)) {
                continue;
            }

            $bullets = $slide['bullets'] ?? $slide['bullet_points'] ?? $slide['points'] ?? [];
            if (is_string($bullets)) {
                $bullets = array_filter(array_map('trim', explode("\n", $bullets)));
            }

            $normalisedSlides[] = [
                'slide_number' => (int) ($slide['slide_number'] ?? $index + 1),
                'title' => (string) ($slide['title'] ?? 'Slide ' . ($index + 1)),
                'bullets' => array_values(array_map('strval', (array) $bullets)),
                'speaker_notes' => (string) ($slide['speaker_notes'] ?? $slide['notes'] ?? ''),
            ];
        }

        $objectives = $outline['learning_objectives'] ?? $outline['objectives'] ?? [];

        return [
            'title' => (string) ($outline['title'] ?? 'Untitled course'),
            'summary' => (string) ($outline['summary'] ?? ''),
            'learning_objectives' => array_values(array_map('strval', (array) $objectives)),
            'slides' => $normalisedSlides,
            'requested_slide_count' => $slideCount,
        ];
    }

    private function outlineToPlainText(array $outline): string
    {
        $lines = [$outline['title']];

        if (! empty($outline['summary'])) {
            $lines[] = '';
            $lines[] = $outline['summary'];
        }

        if (! empty($outline['learning_objectives'])) {
            $lines[] = '';
            $lines[] = 'Learning objectives:';
            foreach ($outline['learning_objectives'] as $objective) {
                $lines[] = '- ' . $objective;
            }
        }

        foreach ($outline['slides'] as $slide) {
            $lines[] = '';
            $lines[] = 'Slide ' . $slide['slide_number'] . ': ' . $slide['title'];
            foreach ($slide['bullets'] as $bullet) {
                $lines[] = '- ' . $bullet;
            }
        }

        return implode("\n", $lines);
    }

    /** POST /api/g2g-lms/course-builder/ai/presentation */
    public function generatePresentation(Request $request)
    {
        $context = $this->lmsContext($request);
        if ($denied = $this->guardAuthoring($context)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'outline' => 'required|array',
            'input_fields' => 'nullable|array',
            'configure_fields' => 'nullable|array',
            'course_type' => 'nullable|string|max:255',
            'slide_count' => 'nullable|integer|min:3|max:40',
            'ai_model' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $outline = $this->normaliseOutline($request->input('outline'), (int) ($request->input('slide_count') ?: 10));
        $slideCount = count($outline['slides']) ?: (int) ($request->input('slide_count') ?: 10);

        try {
            $generation = $this->gamma->generatePresentation([
                'inputText' => $this->outlineToPlainText($outline),
                'numCards' => $slideCount,
            ]);

            $generationId = $generation['generationId'] ?? $generation['id'] ?? null;
            if (! $generationId) {
                throw new RuntimeException('Gamma did not return a generation id.');
            }

            $outlineId = DB::table('ai_course_outlines')->insertGetId([
                'course_type' => $request->input('course_type') ?: 'ai-generated',
                'input_fields' => json_encode($request->input('input_fields', [])),
                'configure_fields' => json_encode($request->input('configure_fields', [])),
                'outline' => json_encode($outline),
                'sub_institute_id' => $context['sub_institute_id'],
                'created_by' => $context['user_id'],
                'created_at' => now(),
                'updated_at' => now(),
                'presentation_platform' => 'gamma',
                'ai_model' => $request->input('ai_model') ?: config('deepseek.model'),
                'slide_count' => $slideCount,
                'generation_id' => $generationId,
                'status' => 'pending',
            ]);

            return $this->lmsOk([
                'outline_id' => $outlineId,
                'generation_id' => $generationId,
                'status' => 'pending',
            ], 'Presentation generation started', 202);
        } catch (\Exception $e) {
            Log::error('[g2g-lms/ai] Gamma generation failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'Failed to start presentation generation', 'error' => $e->getMessage()], 502);
        }
    }

    /** GET /api/g2g-lms/course-builder/ai/presentation/{generationId} */
    public function generationStatus(Request $request, string $generationId)
    {
        $context = $this->lmsContext($request);

        try {
            $apiKey = env('GAMMA_API_KEY');
            $baseUrl = env('GAMMA_BASE_URL', 'https://public-api.gamma.app/v1.0/');

            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->timeout(20)
                ->get(rtrim($baseUrl, '/') . '/generations/' . $generationId);

            if (! $response->successful()) {
                throw new RuntimeException('Gamma status check failed: ' . $response->body());
            }

            $result = $this->gamma->extractResult($response->json() ?? []);
            $status = match ($result['status']) {
                'completed', 'succeeded', 'done' => 'completed',
                'failed', 'error' => 'failed',
                default => 'pending',
            };

            $record = DB::table('ai_course_outlines')
                ->where('generation_id', $generationId)
                ->where('sub_institute_id', $context['sub_institute_id'])
                ->first();

            if ($record && $record->status !== $status) {
                DB::table('ai_course_outlines')->where('id', $record->id)->update([
                    'status' => $status,
                    'gamma_url' => $result['url'] ?? $record->gamma_url,
                    'export_url' => $result['pdf_url'] ?? $record->export_url,
                    'updated_by' => $context['user_id'],
                    'updated_at' => now(),
                ]);
            }

            return $this->lmsOk([
                'outline_id' => $record->id ?? null,
                'generation_id' => $generationId,
                'generation_status' => $status,
                'gamma_url' => $result['url'] ?? ($record->gamma_url ?? null),
                'export_url' => $result['pdf_url'] ?? ($record->export_url ?? null),
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to read the generation status', 'error' => $e->getMessage()], 502);
        }
    }

    /** GET /api/g2g-lms/course-builder/ai/outlines */
    public function outlines(Request $request)
    {
        $context = $this->lmsContext($request);
        $limit = min(max((int) $request->input('limit', 25), 1), 100);

        $outlines = DB::table('ai_course_outlines')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($outline) {
                $outline->outline = json_decode($outline->outline, true);
                $outline->input_fields = json_decode($outline->input_fields, true);
                $outline->configure_fields = json_decode($outline->configure_fields, true);

                return $outline;
            });

        return $this->lmsOk($outlines);
    }

    /** POST /api/g2g-lms/course-builder/ai/outlines/{id}/publish */
    public function publish(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        if ($denied = $this->guardAuthoring($context)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'required|string|max:191',
            'standard_id' => 'required|integer',
            'subject_category' => 'nullable|string|max:191',
            'subject_type' => 'nullable|string|max:100',
            'jobrole' => 'nullable|string|max:191',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $outline = DB::table('ai_course_outlines')
            ->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->whereNull('deleted_at')->first();

        if (! $outline) {
            return $this->lmsError('Outline not found', 404);
        }

        $departmentExists = DB::table('hrms_departments')
            ->where('id', $request->standard_id)->where('sub_institute_id', $context['sub_institute_id'])->whereNull('deleted_at')->exists();

        if (! $departmentExists) {
            return $this->lmsError('Invalid Department ID', 422);
        }

        $duplicate = DB::table('sub_std_map')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('display_name', $request->display_name)
            ->where('standard_id', $request->standard_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return $this->lmsError('A course with this name already exists in that department', 422);
        }

        try {
            $courseId = DB::table('sub_std_map')->insertGetId([
                'display_name' => $request->display_name,
                'standard_id' => $request->standard_id,
                'subject_category' => $request->subject_category,
                'subject_type' => $request->input('subject_type', 'E-learning Module'),
                'jobrole' => $request->jobrole,
                'sort_order' => 1,
                'status' => $request->input('status', 1),
                'sub_institute_id' => $context['sub_institute_id'],
                'allow_grades' => 'Yes',
                'allow_content' => 'Yes',
                'elective_subject' => 'No',
                'add_content' => 'chapterwise',
                'created_by' => $context['user_id'],
                'created_at' => now(),
            ]);

            DB::table('ai_course_outlines')->where('id', $id)->update([
                'course_id' => $courseId,
                'updated_by' => $context['user_id'],
                'updated_at' => now(),
            ]);

            return $this->lmsOk([
                'course_id' => $courseId,
                'outline_id' => (int) $id,
                'gamma_url' => $outline->gamma_url,
                'export_url' => $outline->export_url,
            ], 'Course published to the catalog', 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to publish the course', 'error' => $e->getMessage()], 500);
        }
    }

    /* ================================================================== *
     * DeepSeek — simplified inline client. See class doc-comment.
     * ================================================================== */

    /** @param array<int, array{role:string, content:string}> $messages */
    private function chatJson(array $messages, ?string $model = null): array
    {
        if (! config('deepseek.api_key')) {
            throw new RuntimeException('DeepSeek is not configured. Set DEEPSEEK_API_KEY in the environment.');
        }

        $response = Http::withToken(config('deepseek.api_key'))
            ->timeout((int) config('deepseek.timeout_seconds', 60))
            ->acceptJson()
            ->asJson()
            ->post(rtrim(config('deepseek.base_url'), '/') . '/chat/completions', [
                'model' => $model ?: config('deepseek.model'),
                'messages' => $messages,
                'temperature' => config('deepseek.temperature_narrative', 0.6),
                'response_format' => ['type' => 'json_object'],
                'stream' => false,
            ]);

        if ($response->failed()) {
            Log::error('[g2g-lms/ai] DeepSeek request failed', ['status' => $response->status(), 'body' => $response->body()]);
            $message = $response->json('error.message');
            throw new RuntimeException($message ? "DeepSeek error: {$message}" : "DeepSeek request failed with status {$response->status()}.");
        }

        $content = trim((string) $response->json('choices.0.message.content'));
        if ($content === '') {
            throw new RuntimeException('DeepSeek returned an empty completion.');
        }

        if (str_starts_with($content, '```')) {
            $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content) ?? $content);
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            Log::warning('[g2g-lms/ai] DeepSeek returned unparsable JSON', ['raw' => $content]);
            throw new RuntimeException('DeepSeek returned a response that could not be parsed as JSON.');
        }

        return $decoded;
    }
}
