<?php

namespace App\Http\Resources\PAL\Gamification;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * New PAL → Gamification: the Personal Best board (§2).
 *
 * The resource layer is where §2.4 is made structural: the shape below has no
 * slot for a rank, a peer count or a class average, so no future change to a
 * service can leak one into a student-facing response by accident. Everything
 * emitted is the learner measured against their own earlier self.
 */
class PersonalBestResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $board = (array) $this->resource;

        return [
            'total_records' => (int) ($board['total_records'] ?? 0),
            'headline' => (string) ($board['headline'] ?? ''),
            'groups' => array_map(fn (array $group) => [
                'group' => (string) ($group['group'] ?? ''),
                'label' => (string) ($group['label'] ?? ''),
                'records' => array_map(fn (array $record) => [
                    'metric_key' => $record['metric_key'],
                    'label' => $record['label'],
                    'format' => $record['format'],
                    'direction' => $record['direction'],
                    'scope_type' => $record['scope_type'],
                    'scope_ref' => $record['scope_ref'],
                    'scope_label' => $record['scope_label'],
                    'best_value' => $record['best_value'],
                    'best_achieved_at' => $record['best_achieved_at'],
                    // The only comparison the system makes.
                    'previous_value' => $record['previous_value'],
                    'previous_achieved_at' => $record['previous_achieved_at'],
                    'improvement_pct' => $record['improvement_pct'],
                    'context' => $record['context'] ?? [],
                ], (array) ($group['records'] ?? [])),
            ], (array) ($board['groups'] ?? [])),
            'recent' => (array) ($board['recent'] ?? []),
        ];
    }
}
