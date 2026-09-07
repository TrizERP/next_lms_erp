<?php

namespace App\Http\Controllers\api\lms;

use App\Services\lms\LmsSocialCollaborativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * LMS Engagement -> Social & Collaborative REST API (K12 frontend).
 *
 *   GET  /api/lms/social-collaborative                    the doubt feed
 *   GET  /api/lms/social-collaborative/lookups/subjects   compose-form options
 *   GET  /api/lms/social-collaborative/lookups/chapters
 *   GET  /api/lms/social-collaborative/lookups/topics
 *   GET  /api/lms/social-collaborative/{id}               one doubt + conversation
 *   POST /api/lms/social-collaborative                    raise a doubt
 *   POST /api/lms/social-collaborative/{id}/comments      reply to a doubt
 *
 * Business logic lives in LmsSocialCollaborativeService; the legacy web
 * controllers (lmsSocialCollabrotive / lmsDoubt / lmsDoubtConversation) are
 * neither called nor modified.
 *
 * There is deliberately no PUT or DELETE: the legacy module has no edit or
 * delete path for a doubt or a comment (those resource methods are empty
 * stubs), and inventing one would introduce ownership and moderation rules the
 * ERP has never had.
 */
class LmsSocialCollaborativeApiController extends BaseLmsEngagementApiController
{
    public function __construct(private readonly LmsSocialCollaborativeService $social)
    {
    }

    /** GET /api/lms/social-collaborative */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'search'     => 'nullable|string|max:250',
                'subject_id' => 'nullable|integer|min:1',
                'chapter_id' => 'nullable|integer|min:1',
                'visibility' => 'nullable|in:public,private',
                'mine'       => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            [$page, $perPage] = $this->pagination($request, 10, 50);

            $result = $this->social->feed($this->context($request), $validator->validated(), $page, $perPage);

            return $this->success($result['items'], 'Discussion feed fetched successfully.', $result['meta']);
        });
    }

    /** GET /api/lms/social-collaborative/{id} */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $doubt = $this->social->show($this->context($request), (int) $id);

            if (! $doubt) {
                return $this->error('This discussion is not available.', 404);
            }

            return $this->success($doubt, 'Discussion fetched successfully.');
        });
    }

    /** POST /api/lms/social-collaborative */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'subject_id'  => 'nullable|integer|min:1',
                'chapter_id'  => 'nullable|integer|min:1',
                'topic_id'    => 'nullable|integer|min:1',
                'title'       => 'required|string|max:250',
                'description' => 'nullable|string|max:65535',
                'visibility'  => 'required|in:public,private',
                'file'        => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,ppt,pptx,xls,xlsx,txt',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $doubt = $this->social->createDoubt(
                $this->context($request),
                $validator->validated(),
                $request->file('file')
            );

            return $this->success($doubt, 'Discussion posted successfully.', [], 201);
        });
    }

    /** POST /api/lms/social-collaborative/{id}/comments */
    public function storeComment(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:65535',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $ctx     = $this->context($request);
            $doubtId = (int) $id;

            if (! $this->social->canAccess($ctx, $doubtId)) {
                return $this->error('This discussion is not available.', 404);
            }

            $comment = $this->social->addComment($ctx, $doubtId, $validator->validated()['message']);

            return $this->success($comment, 'Reply posted successfully.', [], 201);
        });
    }

    /** GET /api/lms/social-collaborative/lookups/subjects */
    public function subjects(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $standardId = $request->input('standard_id');

            return $this->success(
                $this->social->subjects($this->context($request), $standardId ? (int) $standardId : null),
                'Subjects fetched successfully.'
            );
        });
    }

    /** GET /api/lms/social-collaborative/lookups/chapters */
    public function chapters(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'subject_id'  => 'required|integer|min:1',
                'standard_id' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $input = $validator->validated();

            return $this->success(
                $this->social->chapters(
                    $this->context($request),
                    (int) $input['subject_id'],
                    isset($input['standard_id']) ? (int) $input['standard_id'] : null
                ),
                'Chapters fetched successfully.'
            );
        });
    }

    /** GET /api/lms/social-collaborative/lookups/topics */
    public function topics(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'chapter_id' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            return $this->success(
                $this->social->topics($this->context($request), (int) $validator->validated()['chapter_id']),
                'Topics fetched successfully.'
            );
        });
    }
}
