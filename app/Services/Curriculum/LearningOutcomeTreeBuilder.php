<?php

namespace App\Services\Curriculum;

/**
 * Shared helpers for reading `lms_learning_outcomes`.
 *
 * Extracted out of CurriculumPlanningApiController so a second caller
 * (CurriculumOutcomesApiController) does not re-derive the same tree-nesting
 * and value-normalising rules from scratch - this estate has already
 * drifted twice from exactly that kind of duplication (two curriculum
 * screens disagreeing on total_marks; three parallel lesson-delivery
 * tracking tables), so a second copy of "how outcomes nest" is worth
 * avoiding on principle, not just for now.
 */
class LearningOutcomeTreeBuilder
{
    /**
     * Nest the flat outcome rows into goals with their competencies beneath.
     *
     * Parenthood is read from `parent_id`, never from `type`: on estates
     * where the type enum is blank for goal rows, `type = 'goal'` would
     * match nothing and silently flatten the tree.
     */
    public function outcomeTree($rows)
    {
        $rows = collect($rows);

        $children = $rows->filter(fn ($row) => !empty($row->parent_id))->groupBy('parent_id');

        $goals = $rows
            ->filter(fn ($row) => empty($row->parent_id))
            ->map(fn ($goal) => [
                'id'           => (int) $goal->id,
                'code'         => $goal->code,
                'type'         => $this->blankToNull($goal->type ?? null) ?? 'goal',
                'description'  => $goal->description,
                'competencies' => ($children->get($goal->id) ?? collect())
                    ->map(fn ($child) => [
                        'id'          => (int) $child->id,
                        'code'        => $child->code,
                        'type'        => $this->blankToNull($child->type ?? null) ?? 'competency',
                        'description' => $child->description,
                    ])
                    ->values(),
            ])
            ->values();

        // A competency whose goal is missing would otherwise vanish. Keep it
        // visible under a null-coded parent rather than lose curriculum data.
        $orphans = $children
            ->reject(fn ($group, $parentId) => $rows->contains(fn ($row) => (int) $row->id === (int) $parentId))
            ->flatten(1);

        if ($orphans->isNotEmpty()) {
            $goals->push([
                'id'           => null,
                'code'         => null,
                'type'         => 'goal',
                'description'  => 'Competencies with no parent goal recorded',
                'competencies' => $orphans->map(fn ($child) => [
                    'id'          => (int) $child->id,
                    'code'        => $child->code,
                    'type'        => $this->blankToNull($child->type ?? null) ?? 'competency',
                    'description' => $child->description,
                ])->values(),
            ]);
        }

        return $goals;
    }

    /**
     * Decode a JSON array column, tolerating null, '' and malformed content.
     * Always an array, so callers can count() it without guarding.
     */
    public function decodeJsonArray($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Empty and whitespace-only strings become null.
     *
     * Every authoring column on lms_curriculum holds '' rather than NULL, and
     * the difference between "never written" and "written blank" is the
     * whole point of showing these fields, so the UI is given one
     * unambiguous value.
     */
    public function blankToNull($value)
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value) === '' ? null : $value;
    }
}
