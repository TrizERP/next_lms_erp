<?php

namespace App\Services\StudyDeck\Story;

/**
 * The acts of a story lesson: one act per topic of the chapter, then a closing mission that applies the chapter.
 *
 * Deterministic and PURE: it only reads the learning map (LearningPlanBuilder::build()). An act is a self-contained story
 * graph that merges to its own ending, so the lesson's navigation stays linear between acts while the learner's choices decide the
 * path inside one. Every concept of a topic is required on every path of that topic's act; the mission requires one anchor concept
 * per topic (up to the template's `mission_concepts`) so it really does reach across the chapter.
 */
final class StoryActs
{
    public const TOPIC = 'topic';

    public const MISSION = 'mission';

    /**
     * @param array<string,mixed> $map LearningPlanBuilder::build(): topics (id, name, description, concept_ids) and concepts
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @return array<int,array{key:string,kind:string,title:string,topic_id:?int,concept_ids:array<int,int>,required_ids:array<int,int>,description:string}>
     */
    public static function plan(array $map, array $config): array
    {
        $known = array_map('intval', array_keys((array) $map['concepts']));
        $acts = [];
        $anchors = [];
        foreach ((array) $map['topics'] as $t) {
            $ids = array_values(array_filter(array_map('intval', (array) ($t['concept_ids'] ?? [])), fn ($id) => in_array($id, $known, true)));
            if ($ids === []) {
                continue;
            }
            $acts[] = [
                'key' => 'a' . (count($acts) + 1), 'kind' => self::TOPIC, 'title' => (string) $t['name'], 'topic_id' => (int) $t['id'],
                'concept_ids' => $ids, 'required_ids' => $ids, 'description' => trim((string) ($t['description'] ?? '')),
            ];
            $anchors[] = $ids[0];
        }
        if ($acts === []) {
            return [];
        }

        // One anchor per topic; when there are more topics than the mission may cover, spread them evenly across the chapter.
        $cap = max(1, (int) ($config['mission_concepts'] ?? 6));
        if (count($anchors) > $cap) {
            $picked = [];
            for ($i = 0; $i < $cap; $i++) {
                $picked[] = $anchors[(int) floor($i * count($anchors) / $cap)];
            }
            $anchors = $picked;
        }
        $acts[] = [
            'key' => 'a' . (count($acts) + 1), 'kind' => self::MISSION, 'title' => 'The final mission', 'topic_id' => null,
            'concept_ids' => $known, 'required_ids' => array_values(array_unique($anchors)), 'description' => 'Apply what the chapter taught to one real situation.',
        ];

        return $acts;
    }
}
