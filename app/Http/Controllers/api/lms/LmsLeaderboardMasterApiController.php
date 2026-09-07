<?php

namespace App\Http\Controllers\api\lms;

use App\Services\lms\LmsLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * LMS Engagement -> Leader Board Master REST API (K12 frontend).
 *
 * The admin configuration behind the leader board: how many points each module
 * is worth for a given (grade, class), its display icon and whether it is
 * active.
 *
 *   GET    /api/lms/leaderboard-master           list (search/filter/paginate)
 *   GET    /api/lms/leaderboard-master/{id}      one row
 *   POST   /api/lms/leaderboard-master           create
 *   PUT    /api/lms/leaderboard-master/{id}      update
 *   DELETE /api/lms/leaderboard-master/{id}      delete
 *
 * Business logic lives in LmsLeaderboardService; the legacy web controller
 * (lms\leaderboard\lbMasterController) is neither called nor modified.
 *
 * Authorization: `api.session` + `staff.only`, so students and parents cannot
 * reach the configuration screen at all. The institute is taken from the
 * verified token, and every read and write is filtered by it, so a row from
 * another institute can be neither listed, fetched, edited nor deleted.
 */
class LmsLeaderboardMasterApiController extends BaseLmsEngagementApiController
{
    /** Modules the leader board supports (mirrors the legacy master form). */
    private const MODULES = ['login', 'exampass', 'examfail', 'homework'];

    /** FontAwesome unicode points the legacy icon picker offered. */
    private const ICONS = ['xf091', 'xf005', 'xf089', 'xf118', 'xf165', 'xf164', 'xf11a'];

    public function __construct(private readonly LmsLeaderboardService $leaderboard)
    {
    }

    /** GET /api/lms/leaderboard-master */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'search'      => 'nullable|string|max:250',
                'module_name' => 'nullable|string|max:250',
                'standard_id' => 'nullable|integer|min:1',
                'status'      => 'nullable|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            [$page, $perPage] = $this->pagination($request, 25);

            $result = $this->leaderboard->masterList($this->context($request), $validator->validated(), $page, $perPage);

            return $this->success($result['items'], 'Leader board master fetched successfully.', $result['meta']);
        });
    }

    /** GET /api/lms/leaderboard-master/{id} */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $row = $this->leaderboard->masterFind($this->context($request), (int) $id);

            if (! $row) {
                return $this->error('This leader board configuration was not found.', 404);
            }

            return $this->success($row, 'Leader board master fetched successfully.');
        });
    }

    /** POST /api/lms/leaderboard-master */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $validator = Validator::make($request->all(), $this->rules());

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $ctx   = $this->context($request);
            $input = $validator->validated();

            if ($this->leaderboard->masterExists($ctx, $input)) {
                return $this->duplicate();
            }

            return $this->success(
                $this->leaderboard->masterCreate($ctx, $input),
                'Leader board master added successfully.',
                [],
                201
            );
        });
    }

    /** PUT /api/lms/leaderboard-master/{id} */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $validator = Validator::make($request->all(), $this->rules());

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $ctx = $this->context($request);
            $id  = (int) $id;

            if (! $this->leaderboard->masterFind($ctx, $id)) {
                return $this->error('This leader board configuration was not found.', 404);
            }

            $input = $validator->validated();

            // A duplicate on update means ANOTHER row already holds this
            // (grade, class, module) - the row being edited is excluded.
            if ($this->leaderboard->masterExists($ctx, $input, $id)) {
                return $this->duplicate();
            }

            return $this->success(
                $this->leaderboard->masterUpdate($ctx, $id, $input),
                'Leader board master updated successfully.'
            );
        });
    }

    /** DELETE /api/lms/leaderboard-master/{id} */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $deleted = $this->leaderboard->masterDelete($this->context($request), (int) $id);

            if (! $deleted) {
                return $this->error('This leader board configuration was not found.', 404);
            }

            return $this->success(null, 'Leader board master deleted successfully.');
        });
    }

    /**
     * Shared create/update rules.
     *
     * `per_value` is the pass/fail percentage threshold; the service stores 0
     * for every module other than exampass/examfail, so it is optional here.
     * `points` may be negative - examfail is worth -10 by default.
     */
    private function rules(): array
    {
        return [
            'grade_id'    => 'required|integer|min:1',
            'standard_id' => 'required|integer|min:1',
            'module_name' => 'required|in:' . implode(',', self::MODULES),
            'per_value'   => 'nullable|numeric|min:0|max:100',
            'points'      => 'required|integer|between:-1000,1000',
            'icon'        => 'required|in:' . implode(',', self::ICONS),
            'description' => 'nullable|string|max:250',
            'status'      => 'nullable|boolean',
        ];
    }

    private function duplicate(): JsonResponse
    {
        return $this->validationError(
            ['module_name' => ['This module is already configured for the selected class.']],
            'This module is already configured for the selected class.'
        );
    }
}
