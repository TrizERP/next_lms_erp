<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Http\Resources\PAL\Gamification\BadgeResource;
use App\Http\Resources\PAL\Gamification\CareerQuestResource;
use App\Http\Resources\PAL\Gamification\PersonalBestResource;
use App\Http\Resources\PAL\Gamification\TeamChallengeResource;
use App\Models\PAL\Gamification\TeamChallenge;
use App\Services\PAL\Gamification\BadgeService;
use App\Services\PAL\Gamification\CareerQuestService;
use App\Services\PAL\Gamification\ChallengeModeService;
use App\Services\PAL\Gamification\GamificationService;
use App\Services\PAL\Gamification\GamificationVisibility;
use App\Services\PAL\Gamification\LearnerActivitySource;
use App\Services\PAL\Gamification\PersonalBestService;
use App\Services\PAL\Gamification\SessionSummaryService;
use App\Services\PAL\Gamification\StreakService;
use App\Services\PAL\Gamification\TeamChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * New PAL → Gamification API.
 *
 * Mounted at /api/pal/new/gamification/* under the same `pal.auth` middleware
 * as the rest of PAL, so authentication, tenant scoping and per-learner
 * ownership are already enforced before any method here runs. In particular,
 * passing `?learner_id=` makes PalApiAuth verify the caller may read that
 * learner at all — a student can only ever resolve to themselves.
 *
 * Every response is the standard PAL `{ success, message, data }` envelope.
 *
 * The audience (student / teacher / parent / admin) is resolved once per
 * request by GamificationVisibility and threaded through every service, because
 * §9's visibility matrix is enforced on the way OUT of the server rather than
 * by the client choosing what to render.
 */
class NewPalGamificationController extends Controller
{
    public function __construct(
        private readonly GamificationService $gamification,
        private readonly PersonalBestService $personalBests,
        private readonly StreakService $streaks,
        private readonly BadgeService $badges,
        private readonly TeamChallengeService $teamChallenges,
        private readonly CareerQuestService $careerQuest,
        private readonly ChallengeModeService $challengeMode,
        private readonly SessionSummaryService $sessions,
        private readonly LearnerActivitySource $activity,
        private readonly GamificationVisibility $visibility,
    ) {
    }

    // =====================================================================
    // Overview + specification
    // =====================================================================

    /** GET /api/pal/new/gamification/overview */
    public function overview(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        return $this->ok($this->gamification->overview($learnerId, $this->audience($request)));
    }

    /** GET /api/pal/new/gamification/specification */
    public function specification(Request $request): JsonResponse
    {
        return $this->ok($this->gamification->specification($this->audience($request)));
    }

    // =====================================================================
    // Personal best
    // =====================================================================

    /** GET /api/pal/new/gamification/personal-best */
    public function personalBest(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        if (! $this->visibility->allows('own_personal_bests', $this->audience($request))) {
            return $this->fail('Personal bests are not visible to this audience.', 403);
        }

        return $this->ok((new PersonalBestResource($this->personalBests->board($learnerId)))->toArray($request));
    }

    /** GET /api/pal/new/gamification/personal-best/history */
    public function personalBestHistory(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $this->personalBests->refresh($learnerId);

        return $this->ok($this->personalBests->history($learnerId, (int) $request->input('limit', 50)));
    }

    // =====================================================================
    // Badges
    // =====================================================================

    /** GET /api/pal/new/gamification/badges */
    public function badges(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $audience = $this->audience($request);
        if (! $this->visibility->allows('own_badges', $audience)) {
            return $this->fail('Badges are not visible to this audience.', 403);
        }

        $collection = $this->badges->collection($learnerId);
        $collection['earned'] = array_map(fn ($b) => (new BadgeResource($b))->toArray($request), $collection['earned']);
        $collection['available'] = array_map(fn ($b) => (new BadgeResource($b))->toArray($request), $collection['available']);

        return $this->ok($collection);
    }

    /** GET /api/pal/new/gamification/badges/earned */
    public function earnedBadges(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $collection = $this->badges->collection($learnerId);

        return $this->ok([
            'total_earned' => $collection['total_earned'],
            'total_available' => $collection['total_available'],
            'earned' => array_map(fn ($b) => (new BadgeResource($b))->toArray($request), $collection['earned']),
            'categories' => $collection['categories'],
        ]);
    }

    /** GET /api/pal/new/gamification/badges/{badgeId} */
    public function badge(Request $request, string $badgeId): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $badge = $this->badges->detail($learnerId, $badgeId);
        if ($badge === null) {
            return $this->fail('That badge is not in the catalogue.', 404);
        }

        return $this->ok($badge);
    }

    /**
     * POST /api/pal/new/gamification/badges/{badgeId}/revoke
     *
     * §10.3 teacher override. Staff only — a student cannot revoke their own
     * badge, and no automated path ever revokes one.
     */
    public function revokeBadge(Request $request, string $badgeId): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Only a teacher can nullify a badge award.', 403);
        }

        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return $this->fail('A reason is required — a revoked badge must be explainable to the student.');
        }

        $revoked = $this->badges->revoke(
            $learnerId,
            $badgeId,
            (string) $request->input('scope_key', ''),
            (int) $auth['user_id'],
            $reason
        );

        return $revoked
            ? $this->ok(['revoked' => true], 'Badge award nullified.')
            : $this->fail('No live award of that badge was found for this learner.', 404);
    }

    // =====================================================================
    // Streak
    // =====================================================================

    /** GET /api/pal/new/gamification/streak */
    public function streak(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        if (! $this->visibility->allows('own_streak', $this->audience($request))) {
            return $this->fail('Streaks are not visible to this audience.', 403);
        }

        return $this->ok($this->streaks->summary($learnerId));
    }

    /** GET /api/pal/new/gamification/streak/history */
    public function streakHistory(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        return $this->ok($this->streaks->history($learnerId, (int) $request->input('days', 120)));
    }

    // =====================================================================
    // Team challenges
    // =====================================================================

    /** GET /api/pal/new/gamification/team-challenges */
    public function teamChallenges(Request $request): JsonResponse
    {
        $auth = $this->auth($request);
        $audience = $this->audience($request);

        [$scope, $learnerId] = $this->classScope($request, $auth);
        if ($scope === null) {
            return $this->fail('A class is required. Students resolve it from their own enrolment; staff must pass standard_id.');
        }

        $challenges = $this->teamChallenges->forClass(
            $scope,
            $audience,
            $learnerId,
            (bool) $request->input('include_finished', true)
        );

        return $this->ok([
            'scope' => $scope,
            'types' => $this->teamChallenges->types(),
            'challenges' => array_map(
                fn ($c) => (new TeamChallengeResource($c, $audience))->toArray($request),
                $challenges
            ),
            'rules' => [
                'max_active_per_class_per_week' => (int) config('pal_gamification.team_challenges.max_active_per_class_per_week', 2),
                'teacher_initiated_only' => (bool) config('pal_gamification.team_challenges.teacher_initiated_only', true),
            ],
        ]);
    }

    /** GET /api/pal/new/gamification/team-challenges/{challengeId} */
    public function teamChallenge(Request $request, int $challengeId): JsonResponse
    {
        $challenge = TeamChallenge::find($challengeId);
        if ($challenge === null) {
            return $this->fail('Challenge not found.', 404);
        }

        $auth = $this->auth($request);
        $audience = $this->audience($request);
        $learnerId = ($auth['role'] ?? '') === 'student' ? (int) $auth['user_id'] : $this->optionalLearnerId($request);

        if (! $this->mayReadChallenge($challenge, $auth, $learnerId)) {
            return $this->fail('This challenge belongs to a different class.', 403);
        }

        return $this->ok(
            (new TeamChallengeResource($this->teamChallenges->present($challenge, $audience, $learnerId), $audience))
                ->toArray($request)
        );
    }

    /** POST /api/pal/new/gamification/team-challenges — teacher only (§4.3). */
    public function createTeamChallenge(Request $request): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Team challenges are always teacher-initiated.', 403);
        }

        $subInstituteId = (int) $this->firstInstitute($auth, (int) $request->input('sub_institute_id', 0));
        if ($subInstituteId <= 0) {
            return $this->fail('No institute is available for this account.', 403);
        }

        $result = $this->teamChallenges->create($request->all(), (int) $auth['user_id'], $subInstituteId);
        if (isset($result['error'])) {
            return $this->fail($result['error']);
        }

        $audience = $this->audience($request);

        return $this->ok(
            (new TeamChallengeResource($this->teamChallenges->present($result['challenge'], $audience), $audience))
                ->toArray($request),
            'Challenge created.'
        );
    }

    /** PUT /api/pal/new/gamification/team-challenges/{challengeId} */
    public function updateTeamChallenge(Request $request, int $challengeId): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Only a teacher can change a challenge.', 403);
        }

        $challenge = TeamChallenge::find($challengeId);
        if ($challenge === null) {
            return $this->fail('Challenge not found.', 404);
        }
        if (! $this->mayManageChallenge($challenge, $auth)) {
            return $this->fail('This challenge belongs to a different institute.', 403);
        }

        $result = $this->teamChallenges->update($challenge, $request->all());
        if (isset($result['error'])) {
            return $this->fail($result['error']);
        }

        $audience = $this->audience($request);

        return $this->ok(
            (new TeamChallengeResource($this->teamChallenges->present($result['challenge'], $audience), $audience))
                ->toArray($request),
            'Challenge updated.'
        );
    }

    /**
     * POST /api/pal/new/gamification/team-challenges/{challengeId}/end
     *
     * §4.3: a teacher who sees a challenge causing distress must be able to end
     * it immediately.
     */
    public function endTeamChallenge(Request $request, int $challengeId): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Only a teacher can end a challenge.', 403);
        }

        $challenge = TeamChallenge::find($challengeId);
        if ($challenge === null) {
            return $this->fail('Challenge not found.', 404);
        }
        if (! $this->mayManageChallenge($challenge, $auth)) {
            return $this->fail('This challenge belongs to a different institute.', 403);
        }

        $ended = $this->teamChallenges->end($challenge, (int) $auth['user_id'], (string) $request->input('reason', ''));
        $audience = $this->audience($request);

        return $this->ok(
            (new TeamChallengeResource($this->teamChallenges->present($ended, $audience), $audience))->toArray($request),
            'Challenge ended.'
        );
    }

    // =====================================================================
    // Career quest
    // =====================================================================

    /** GET /api/pal/new/gamification/career-quest */
    public function careerQuest(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        if (! $this->visibility->allows('own_career_quest', $this->audience($request))) {
            return $this->fail('The career quest is not visible to this audience.', 403);
        }

        return $this->ok((new CareerQuestResource($this->careerQuest->quest($learnerId)))->toArray($request));
    }

    /** GET /api/pal/new/gamification/career-quest/progress */
    public function careerQuestProgress(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        return $this->ok($this->careerQuest->progress($learnerId));
    }

    /** POST /api/pal/new/gamification/career-quest/interest */
    public function declareInterest(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $interests = $request->input('interests', []);
        if (! is_array($interests)) {
            return $this->fail('interests must be a list of pathway keys.');
        }

        return $this->ok($this->careerQuest->declareInterest($learnerId, $interests), 'Interest recorded.');
    }

    /** POST /api/pal/new/gamification/career-quest/pathway */
    public function choosePathway(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $result = $this->careerQuest->choosePathway($learnerId, (string) $request->input('pathway', ''));

        return isset($result['error']) ? $this->fail($result['error']) : $this->ok($result, 'Pathway chosen.');
    }

    /** POST /api/pal/new/gamification/career-quest/report */
    public function generateCareerReport(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $result = $this->careerQuest->generateReport($learnerId);

        return isset($result['error']) ? $this->fail($result['error']) : $this->ok($result, 'Career Pathway Report generated.');
    }

    // =====================================================================
    // Challenge Mode
    // =====================================================================

    /** GET /api/pal/new/gamification/challenge-mode */
    public function challengeMode(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $audience = $this->audience($request);
        if ($audience === GamificationVisibility::PARENT) {
            return $this->fail('Challenge Mode is not visible to parents.', 403);
        }

        return $this->ok($this->challengeMode->state($learnerId, $audience));
    }

    /** POST /api/pal/new/gamification/challenge-mode/opt-in */
    public function challengeModeOptIn(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $result = $this->challengeMode->setOptIn($learnerId, (bool) $request->input('opted_in', true));

        return isset($result['error']) ? $this->fail($result['error']) : $this->ok($result);
    }

    /** POST /api/pal/new/gamification/challenge-mode/submit */
    public function challengeModeSubmit(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $responses = $request->input('responses', []);
        if (! is_array($responses) || $responses === []) {
            return $this->fail('responses must be a non-empty list of answered items.');
        }

        $result = $this->challengeMode->submit($learnerId, $responses, [
            'concept_ref' => $request->input('concept_ref'),
            'concept_label' => $request->input('concept_label'),
            'subject_id' => $request->input('subject_id'),
        ]);

        return isset($result['error']) ? $this->fail($result['error']) : $this->ok($result);
    }

    /** GET /api/pal/new/gamification/challenge-mode/leaderboard */
    public function challengeModeLeaderboard(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $audience = $this->audience($request);
        if ($audience === GamificationVisibility::PARENT) {
            return $this->fail('Challenge Mode is not visible to parents.', 403);
        }

        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return $this->fail('Learner not found.', 404);
        }

        // A student who has not opted in is not shown the board at all.
        $state = $this->challengeMode->state($learnerId, $audience);
        if ($audience === GamificationVisibility::STUDENT && ! ($state['opted_in'] ?? false)) {
            return $this->ok([
                'visible' => false,
                'reason' => 'not_opted_in',
                'message' => 'Challenge Mode is optional. Opt in to see the weekly board.',
            ]);
        }

        return $this->ok($this->challengeMode->leaderboard($learner, $audience));
    }

    /** POST /api/pal/new/gamification/challenge-mode/class-availability — teacher switch (§6.1). */
    public function challengeModeAvailability(Request $request): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Only a teacher can change Challenge Mode availability for a class.', 403);
        }

        $subInstituteId = (int) $this->firstInstitute($auth, (int) $request->input('sub_institute_id', 0));
        if ($subInstituteId <= 0) {
            return $this->fail('No institute is available for this account.', 403);
        }

        $setting = $this->challengeMode->setClassAvailability(
            [
                'sub_institute_id' => $subInstituteId,
                'syear' => $request->input('syear'),
                'standard_id' => $request->input('standard_id'),
                'division_id' => $request->input('division_id'),
            ],
            (bool) $request->input('enabled', true),
            (int) $auth['user_id'],
            (string) $request->input('reason', '')
        );

        return $this->ok([
            'enabled' => (bool) $setting->enabled,
            'standard_id' => $setting->standard_id,
            'division_id' => $setting->division_id,
            'disabled_reason' => $setting->disabled_reason,
        ]);
    }

    // =====================================================================
    // Session summary + notifications
    // =====================================================================

    /** GET /api/pal/new/gamification/session-summary */
    public function sessionSummary(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        return $this->ok($this->sessions->summary($learnerId, $request->input('date')));
    }

    /** GET /api/pal/new/gamification/notifications */
    public function notifications(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        return $this->ok($this->sessions->notifications(
            $learnerId,
            (bool) $request->input('unread_only', false),
            (int) $request->input('limit', 20)
        ));
    }

    /** POST /api/pal/new/gamification/notifications/read */
    public function readNotifications(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $ids = $request->input('ids', []);
        $marked = $this->sessions->markNotificationsRead($learnerId, is_array($ids) ? array_map('intval', $ids) : []);

        return $this->ok(['marked_read' => $marked]);
    }

    // =====================================================================
    // Shared plumbing
    // =====================================================================

    /** @return array<string,mixed> */
    private function auth(Request $request): array
    {
        $auth = $request->attributes->get('pal_auth');

        return is_array($auth) ? $auth : [];
    }

    private function audience(Request $request): string
    {
        return $this->visibility->audience($this->auth($request), (string) $request->input('audience', ''));
    }

    /**
     * Resolve the learner this request is about.
     *
     * A student is always themselves. Staff must name a learner — and by the
     * time this runs, `pal.auth` has already rejected any learner_id outside
     * the caller's institute, so no extra ownership check is needed here.
     *
     * @return array{0:int,1:?JsonResponse}
     */
    private function learnerId(Request $request): array
    {
        $auth = $this->auth($request);

        if (($auth['role'] ?? '') === 'student') {
            return [(int) $auth['user_id'], null];
        }

        $learnerId = (int) $request->input('learner_id', 0);
        if ($learnerId <= 0) {
            return [0, $this->fail('learner_id is required — pick a student to view their gamification.', 422)];
        }

        return [$learnerId, null];
    }

    private function optionalLearnerId(Request $request): ?int
    {
        $learnerId = (int) $request->input('learner_id', 0);

        return $learnerId > 0 ? $learnerId : null;
    }

    /**
     * The class a request is about: a student's own enrolment, or the class a
     * staff member named.
     *
     * @return array{0:?array<string,mixed>,1:?int}
     */
    private function classScope(Request $request, array $auth): array
    {
        if (($auth['role'] ?? '') === 'student') {
            $learner = $this->activity->learner((int) $auth['user_id']);
            if ($learner === null || $learner['standard_id'] === null) {
                return [null, null];
            }

            return [[
                'sub_institute_id' => $learner['sub_institute_id'],
                'syear' => $learner['syear'],
                'standard_id' => $learner['standard_id'],
                'division_id' => $learner['division_id'],
            ], $learner['learner_id']];
        }

        $learnerId = $this->optionalLearnerId($request);
        $standardId = (int) $request->input('standard_id', 0);

        // Staff may pass a learner instead of a class and get that learner's.
        if ($standardId <= 0 && $learnerId !== null) {
            $learner = $this->activity->learner($learnerId);
            if ($learner !== null && $learner['standard_id'] !== null) {
                return [[
                    'sub_institute_id' => $learner['sub_institute_id'],
                    'syear' => $learner['syear'],
                    'standard_id' => $learner['standard_id'],
                    'division_id' => $learner['division_id'],
                ], $learnerId];
            }
        }

        if ($standardId <= 0) {
            return [null, null];
        }

        return [[
            'sub_institute_id' => (int) $this->firstInstitute($auth, (int) $request->input('sub_institute_id', 0)),
            'syear' => $request->input('syear'),
            'standard_id' => $standardId,
            'division_id' => $request->input('division_id'),
        ], $learnerId];
    }

    private function mayReadChallenge(TeamChallenge $challenge, array $auth, ?int $learnerId): bool
    {
        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return true;
        }

        if (($auth['role'] ?? '') === 'student') {
            $learner = $this->activity->learner((int) $auth['user_id']);

            return $learner !== null && (int) $learner['standard_id'] === (int) $challenge->standard_id;
        }

        return $this->mayManageChallenge($challenge, $auth);
    }

    private function mayManageChallenge(TeamChallenge $challenge, array $auth): bool
    {
        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return true;
        }

        $institutes = $this->institutes($auth);

        return $institutes === [] || in_array((string) $challenge->sub_institute_id, $institutes, true);
    }

    /** @return array<int,string> */
    private function institutes(array $auth): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) ($auth['sub_institute_id'] ?? ''))),
            'strlen'
        ));
    }

    private function firstInstitute(array $auth, int $fallback = 0): int
    {
        $institutes = $this->institutes($auth);

        return (int) ($institutes[0] ?? $fallback);
    }

    private function ok($data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
