<?php

namespace App\Http\Resources\PAL\Gamification;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * New PAL → Gamification: a badge and this learner's standing on it (§3).
 *
 * §3.1 requires every badge to carry its framework mapping, because a badge in
 * PAL V4 is HPC portfolio evidence rather than a sticker. The mapping therefore
 * travels in the payload, not just in the catalogue — the portfolio and the
 * parent digest read it straight from here.
 */
class BadgeResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $badge = (array) $this->resource;

        return [
            'badge_id' => $badge['badge_id'],
            'name' => $badge['name'],
            'category' => $badge['category'],
            'category_label' => $badge['category_label'] ?? null,
            'description' => $badge['description'],
            'rarity' => $badge['rarity'],
            'scope' => $badge['scope'],
            'challenge_mode_only' => (bool) ($badge['challenge_mode_only'] ?? false),

            // Evidence mapping (§3.1).
            'hpc_domain' => $badge['hpc_domain'],
            'casel_domain' => $badge['casel_domain'],
            'ncdg_goal' => $badge['ncdg_goal'],
            'hpc_evidence_weight' => (float) ($badge['hpc_evidence_weight'] ?? 0),

            'earned' => (bool) ($badge['earned'] ?? false),
            'times_earned' => (int) ($badge['times_earned'] ?? 0),
            'awards' => array_map(fn (array $award) => [
                'scope_key' => $award['scope_key'],
                'awarded_at' => $award['awarded_at'],
                'student_message' => $award['student_message'] ?? null,
                'context' => $award['context'] ?? [],
            ], (array) ($badge['awards'] ?? [])),
        ];
    }
}
