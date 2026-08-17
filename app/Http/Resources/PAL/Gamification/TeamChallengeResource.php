<?php

namespace App\Http\Resources\PAL\Gamification;

use App\Services\PAL\Gamification\GamificationVisibility;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * New PAL → Gamification: a team challenge as one audience may see it (§4.3).
 *
 * The per-learner breakdown is a TEACHER field. It is included only when the
 * service already resolved the audience as staff — this resource re-checks
 * rather than trusting the caller, so the "student never sees who contributed"
 * rule holds even if a controller passes the wrong payload.
 */
class TeamChallengeResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, private readonly string $audience = GamificationVisibility::STUDENT)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        $challenge = (array) $this->resource;

        $payload = [
            'id' => $challenge['id'],
            'type' => $challenge['type'],
            'type_label' => $challenge['type_label'],
            'type_summary' => $challenge['type_summary'],
            'inclusive' => (bool) ($challenge['inclusive'] ?? false),
            'title' => $challenge['title'],
            'description' => $challenge['description'],
            'concept_label' => $challenge['concept_label'],
            'target_value' => $challenge['target_value'],
            'target_tier' => $challenge['target_tier'],
            'deadline' => $challenge['deadline'],
            'days_remaining' => $challenge['days_remaining'],
            'status' => $challenge['status'],
            'reward' => $challenge['reward'],
            'class_progress' => $challenge['class_progress'],
        ];

        if (array_key_exists('own_contribution', $challenge)) {
            $payload['own_contribution'] = $challenge['own_contribution'];
        }

        $isStaff = in_array($this->audience, [GamificationVisibility::TEACHER, GamificationVisibility::ADMIN], true);
        if ($isStaff && array_key_exists('per_learner', $challenge)) {
            $payload['per_learner'] = $challenge['per_learner'];
            $payload['teacher_id'] = $challenge['teacher_id'] ?? null;
            $payload['standard_id'] = $challenge['standard_id'] ?? null;
            $payload['division_id'] = $challenge['division_id'] ?? null;
            $payload['baseline_value'] = $challenge['baseline_value'] ?? null;
            $payload['ended_reason'] = $challenge['ended_reason'] ?? null;
        }

        return $payload;
    }
}
