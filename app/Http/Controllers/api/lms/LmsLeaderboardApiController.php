<?php

namespace App\Http\Controllers\api\lms;

use App\Services\lms\LmsLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * LMS Engagement -> Leader Board REST API (K12 frontend).
 *
 *   GET /api/lms/leaderboard             the caller's own summary
 *   GET /api/lms/leaderboard/filters     data-backed filter options
 *   GET /api/lms/leaderboard/rankings    full class ranking, paginated
 *   GET /api/lms/leaderboard/{userId}    one learner's summary
 *
 * Business logic lives in LmsLeaderboardService; the legacy web controller
 * (lms\lmsLeaderboardController) is neither called nor modified.
 *
 * Authorization
 *   - a student may only ever read their OWN summary, and only rankings for
 *     their own class;
 *   - staff and admins are confined to their own institute (guaranteed by the
 *     token-derived sub_institute_id) and, for {userId}, to learners actually
 *     enrolled in that institute.
 */
class LmsLeaderboardApiController extends BaseLmsEngagementApiController
{
    public function __construct(private readonly LmsLeaderboardService $leaderboard)
    {
    }

    /** GET /api/lms/leaderboard */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $ctx = $this->context($request);

            $topLimit = (int) $request->input('top_limit', 5);
            $topLimit = $topLimit > 0 ? min($topLimit, 25) : 5;

            $summary = $this->leaderboard->summary($ctx, $topLimit);

            return $this->success($summary, 'Leader board fetched successfully.');
        });
    }

    /** GET /api/lms/leaderboard/filters */
    public function filters(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            return $this->success(
                $this->leaderboard->filterOptions($this->context($request)),
                'Leader board filters fetched successfully.'
            );
        });
    }

    /** GET /api/lms/leaderboard/rankings */
    public function rankings(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $ctx = $this->context($request);

            $validator = Validator::make($request->all(), [
                'standard_id' => 'nullable|integer|min:1',
                'section_id'  => 'nullable|integer|min:1',
                'module_name' => 'nullable|string|max:250',
                'from'        => 'nullable|date',
                'to'          => 'nullable|date|after_or_equal:from',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $filters = $validator->validated();

            // A student is pinned to their own class regardless of what they ask for.
            if ($ctx['is_student']) {
                $filters['standard_id'] = null;
                $filters['section_id']  = null;
            }

            [$page, $perPage] = $this->pagination($request, 20);

            $result = $this->leaderboard->rankings($ctx, $filters, $page, $perPage);

            return $this->success($result['items'], 'Class ranking fetched successfully.', $result['meta']);
        });
    }

    /** GET /api/lms/leaderboard/{userId} */
    public function show(Request $request, $userId): JsonResponse
    {
        return $this->run(function () use ($request, $userId) {
            $ctx    = $this->context($request);
            $userId = (int) $userId;

            if ($userId <= 0) {
                return $this->error('A valid learner id is required.', 422);
            }

            if ($ctx['is_student'] && $userId !== $ctx['user_id']) {
                return $this->error('You can only view your own leader board.', 403);
            }

            if ($userId !== $ctx['user_id'] && ! $this->learnerInInstitute($userId, $ctx['sub_institute_id'])) {
                return $this->error('This learner is not part of your institute.', 404);
            }

            $topLimit = (int) $request->input('top_limit', 5);
            $topLimit = $topLimit > 0 ? min($topLimit, 25) : 5;

            // user_profile_id is dropped: a staff member reading a learner has
            // no way to know which profile the ledger rows were written under,
            // and the learner id + institute already pin the rows uniquely.
            $summary = $this->leaderboard->summary(
                array_merge($ctx, ['user_id' => $userId, 'user_profile_id' => null]),
                $topLimit
            );

            return $this->success($summary, 'Leader board fetched successfully.');
        });
    }

    private function learnerInInstitute(int $userId, int $subInstituteId): bool
    {
        return DB::table('tblstudent')
            ->where('id', $userId)
            ->where('sub_institute_id', $subInstituteId)
            ->exists();
    }
}
