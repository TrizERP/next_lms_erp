<?php

namespace App\Services\CoursePresentation;

use App\Http\Controllers\api\lms\CoursePresentationController;
use App\Jobs\GenerateCoursePresentationJob;
use Illuminate\Support\Facades\DB;

/**
 * Starts a Course Presentation deck (interactive slides or a branching-scenario story lesson) from AI Stack (Teach/Learn
 * automations).
 *
 * It is the Create button of Classroom Resource, called from the server: the same fit check, the same GenerationRun and the
 * same background job, so the deck has the same layout, player and PPTX as one made from Classroom Resource. There is no review
 * step here (decided 2026-11-25): the run carries `auto_store`, so the job stores it through the same approve() checks as soon
 * as it passes validation. A deck of this chapter, teaching method and format that is already stored is reused, never
 * duplicated.
 */
class AutomationDraftStarter
{
    public const FORMAT = 'branching_scenario'; // default format; slides pass 'interactive_slides'

    public function __construct(private SourceLoader $loader, private PedagogicalWorkflow $workflows)
    {
    }

    /** The pipeline's method key for an AI Stack "Teaching approach" label (they are the same ten labels), or null. */
    public function methodKey(string $approach): ?string
    {
        $key = array_search(trim($approach), (array) config('course_presentation_pilot.pedagogies', []), true);

        return is_string($key) ? $key : null;
    }

    /** The story lesson already stored for this chapter AND method, if any: [id, title]. */
    public function existing(int $chapterId, int $tenant, string $approach, string $format = self::FORMAT): ?array
    {
        $method = $this->methodKey($approach);
        $row = $method === null ? null : app(GenerationRun::class)->storedPresentation($chapterId, $tenant, $format, $method);
        if ($row === null) {
            return null;
        }

        return ['id' => (int) $row['id'], 'title' => (string) (DB::table('content_master')->where('id', $row['id'])->value('title') ?? '')];
    }

    /**
     * @return array{success:bool,status?:string,run?:string,method?:string,chapter_id?:int,error?:string,message?:string,content_id?:int,title?:string}
     */
    public function start(int $chapterId, int $tenant, ?int $userId, string $approach, string $format = self::FORMAT, ?string $profile = null): array
    {
        $method = $this->methodKey($approach);
        if ($method === null) {
            return ['success' => false, 'error' => "'{$approach}' is not a teaching approach the presentation pipeline knows."];
        }
        $chapter = $this->loader->chapter($chapterId, $tenant);
        if ($chapter === null) {
            return ['success' => false, 'error' => "Chapter #{$chapterId} was not found for this school."];
        }

        $runs = app(GenerationRun::class);
        // What the teacher picks on the Create screen to find this draft.
        $label = (string) config('course_presentation_pilot.formats.' . $format . '.label', $format);
        if ($stored = $this->existing($chapterId, $tenant, $approach, $format)) {
            return ['success' => true, 'status' => 'verified_existing', 'is_new' => false, 'content_id' => $stored['id'], 'title' => $stored['title'],
                'message' => "{$label} deck #{$stored['id']} already exists for this chapter and teaching approach; reused."];
        }
        // A draft of this very choice is already queued, running or waiting for review: point at it, never start a second.
        if ($active = $runs->active($chapterId, $tenant, $method, $format)) {
            return ['success' => true, 'status' => 'draft_pending', 'run' => (string) $active['run'], 'method' => $method, 'format' => $format, 'format_label' => $label, 'chapter_id' => $chapterId,
                'message' => "A {$label} for this chapter and approach is already being generated."];
        }
        // The background job is one per chapter and school, so a second choice started now would be dropped and wait for ever.
        if (($busy = $runs->inFlight($chapterId, $tenant)) !== null && !GenerationRun::matches($busy, $method, $format)) {
            return ['success' => false, 'error' => 'Another presentation draft is being made for this chapter. Try again when it has finished.'];
        }

        if ($this->workflows->appliesTo($chapter) && ($reason = $this->workflows->forChapter($chapter)->refusal($method, $format)) !== null) {
            return ['success' => false, 'error' => $reason];
        }
        // The same check the Create button makes (sources present, a model reachable, nothing stored or in progress).
        [, , $fit] = app(CoursePresentationController::class)->assess($chapter, $method, $format);
        if (!$fit['continue_allowed'] || !$fit['generation_supported']) {
            return ['success' => false, 'error' => "A {$label} presentation cannot be generated for this chapter. " . implode(' ', $fit['generation_blockers'] ?: [$fit['reason']])];
        }

        $status = $runs->request($chapterId, $tenant, $userId, $method, $format, ['auto_store' => true, 'profile' => $profile ?: 'Teacher']);
        $job = new GenerateCoursePresentationJob($chapterId, $tenant, $status['run']);
        // A queue worker runs it when there is one; with the synchronous driver it runs right after the response, as for Create.
        config('queue.default') === 'sync' ? dispatch($job)->afterResponse() : dispatch($job);

        return ['success' => true, 'status' => 'draft_started', 'run' => (string) $status['run'], 'method' => $method, 'format' => $format, 'format_label' => $label, 'chapter_id' => $chapterId,
            'message' => "{$label} is being generated. It is stored automatically once it passes validation."];
    }
}
