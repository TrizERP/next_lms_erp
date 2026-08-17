<?php

namespace App\Http\Resources\PAL\Gamification;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * New PAL → Gamification: the Career Quest (§5).
 *
 * The `ready` / `reason` pair on the RIASEC block is the important part of this
 * shape. A career profile assembled from too little evidence would be a guess
 * dressed as insight, so the API says which gate is still closed and the UI
 * repeats it — rather than rendering a plausible-looking profile nobody earned.
 */
class CareerQuestResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $quest = (array) $this->resource;

        if (! ($quest['available'] ?? false)) {
            return [
                'available' => false,
                'reason' => $quest['reason'] ?? 'unavailable',
            ];
        }

        return [
            'available' => true,
            'learner' => $quest['learner'],
            'stage' => [
                'key' => $quest['stage']['key'] ?? null,
                'label' => $quest['stage']['label'] ?? null,
                'grade_min' => $quest['stage']['grade_min'] ?? null,
                'grade_max' => $quest['stage']['grade_max'] ?? null,
                'grade_known' => (bool) ($quest['stage']['grade_known'] ?? false),
                'shows_riasec' => (bool) ($quest['stage']['shows_riasec'] ?? false),
                'shows_pathways' => (bool) ($quest['stage']['shows_pathways'] ?? false),
                'generates_pathway_report' => (bool) ($quest['stage']['generates_pathway_report'] ?? false),
            ],
            'quest_message' => $quest['quest_message'],
            'quest_level' => $quest['quest_level'],
            'islands' => $quest['islands'],
            'riasec' => [
                'ready' => (bool) ($quest['riasec']['ready'] ?? false),
                'reason' => $quest['riasec']['reason'] ?? null,
                'signals_total' => $quest['riasec']['signals_total'] ?? 0,
                'signals_required' => $quest['riasec']['signals_required'] ?? 0,
                'distinct_types' => $quest['riasec']['distinct_types'] ?? 0,
                'distinct_required' => $quest['riasec']['distinct_required'] ?? 0,
                'min_grade' => $quest['riasec']['min_grade'] ?? null,
                'evidence_sources' => $quest['riasec']['evidence_sources'] ?? [],
                'types' => $quest['riasec']['types'] ?? [],
                'top' => $quest['riasec']['top'] ?? null,
            ],
            'pathways' => $quest['pathways'],
            'primary_pathway' => $quest['primary_pathway'],
            'skill_progress' => $quest['skill_progress'],
            'career_exposure' => $quest['career_exposure'],
            'interest_declaration' => $quest['interest_declaration'],
            'report' => $quest['report'],
        ];
    }
}
