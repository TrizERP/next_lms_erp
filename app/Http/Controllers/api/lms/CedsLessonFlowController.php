<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\Ceds\CedsFitService;
use App\Services\Ceds\CedsScope;
use App\Services\Ceds\LessonFlowBuilder;
use App\Services\Ceds\ResourceResolver;
use App\Services\Ceds\ResourceRowLoader;
use App\Services\Ceds\TopicResourceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Teaching method -> lesson flow for one topic of a chapter: READ ONLY.
 *
 *   GET ?chapter_id=                           the topics that have something stored, and the methods
 *   GET ?chapter_id=&pedagogy=&topic_id=       the method's phases with timings and the stored resources
 *                                              that verifiably support each (optionally &period= and &minutes[]=)
 *
 * The school comes from the verified session (api.session), the caller must pass the staff gate, the chapter
 * must be one this school may see, and the only rows and files looked at are those the existing
 * ResourceResolver already found visible. Nothing is stored, generated or changed, and no resource is
 * shortened, reordered or rewritten to fit a phase.
 */
class CedsLessonFlowController extends Controller
{
    public function __construct(
        private CedsFitService $fit,
        private ResourceRowLoader $loader,
        private ResourceResolver $resolver,
        private CedsScope $scope,
        private TopicResourceReader $reader,
        private LessonFlowBuilder $builder,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $minPeriod = (int) config('ceds_fit_matrix.lesson_flow.min_period', 10);
        $maxPeriod = (int) config('ceds_fit_matrix.lesson_flow.max_period', 120);

        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'pedagogy' => ['nullable', 'string', Rule::in(array_keys($this->fit->pedagogies()))],
            'topic_id' => 'nullable|integer|min:1',
            'period' => "nullable|integer|min:$minPeriod|max:$maxPeriod",
            'minutes' => 'nullable|array|max:12',
            'minutes.*' => "integer|min:0|max:$maxPeriod",
        ]);

        $tenant = (int) $request->session()->get('sub_institute_id');
        if ($tenant <= 0) {
            return response()->json(['status_code' => 0, 'message' => 'No school in this session.'], 403);
        }
        $tenants = $this->loader->visibleTenants($tenant);
        $chapter = $this->loader->visibleChapter((int) $data['chapter_id'], $tenants);
        if ($chapter === null) {
            return response()->json(['status_code' => 0, 'message' => 'Chapter not found.'], 404);
        }
        // Switched on for Std 9 Science, Chapter 1 only (config ceds_fit_matrix.scope). Checked after visibility so
        // a chapter this school cannot see still reads as "not found".
        if (!$this->scope->allows($chapter)) {
            return response()->json(['status_code' => 0, 'message' => $this->scope->message()], 403);
        }


        $rows = $this->loader->load((int) $chapter->id, $tenants);
        $resolved = $this->resolver->resolve((int) $chapter->id, $rows['content'], $rows['labs'], $tenants, $this->fit->formats());
        $material = $this->reader->read($resolved, (int) $chapter->id, $tenants);

        $topics = $this->builder->topics($material);
        $names = $this->reader->topicNames(array_column($topics, 'id'));
        $topics = array_map(fn (array $t) => ['name' => $names[$t['id']] ?? ('Topic ' . $t['id'])] + $t, $topics);

        $base = [
            'status_code' => 1,
            'chapter' => ['id' => (int) $chapter->id, 'name' => $chapter->chapter_name],
            'pedagogies' => $this->fit->pedagogies(),
            'default_period_minutes' => $this->fit->defaultPeriodMinutes(),
            'topics' => $topics,
            'deck_content_id' => $material['deck_id'],
            'warnings' => array_map(fn ($w) => ['code' => 'unreadable', 'message' => "The stored $w could not be read."], $material['unreadable']),
            'generation' => ['enabled' => false, 'adapt_enabled' => false],
        ];

        $topicId = isset($data['topic_id']) ? (int) $data['topic_id'] : null;
        if ($topicId === null) {
            return response()->json($base + ['flow' => null]);
        }
        if (empty($data['pedagogy'])) {
            return response()->json(['status_code' => 0, 'message' => 'Choose a teaching method.', 'errors' => ['pedagogy' => ['Choose a teaching method.']]], 422);
        }
        $topic = collect($topics)->firstWhere('id', $topicId);
        if ($topic === null) {
            return response()->json(['status_code' => 0, 'message' => 'Topic not found for this chapter.'], 404);
        }

        $period = (int) ($data['period'] ?? $this->fit->defaultPeriodMinutes());
        $minutes = isset($data['minutes']) ? array_map('intval', array_values($data['minutes'])) : null;
        if ($minutes !== null && count($minutes) !== count($this->fit->phases($data['pedagogy']))) {
            return response()->json(['status_code' => 0, 'message' => 'One value of minutes is needed for every phase.', 'errors' => ['minutes' => ['One value is needed for every phase.']]], 422);
        }

        $flow = $this->builder->build($data['pedagogy'], $topicId, $period, $material, $minutes);

        return response()->json($base + ['topic' => $topic, 'flow' => $flow]);
    }
}
