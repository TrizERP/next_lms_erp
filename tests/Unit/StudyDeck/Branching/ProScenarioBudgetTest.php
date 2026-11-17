<?php

namespace Tests\Unit\StudyDeck\Branching;

use App\Services\StudyDeck\Branching\Pro\ProScenarioBudget;
use App\Services\StudyDeck\Story\StoryActs;
use PHPUnit\Framework\TestCase;

/** How a chapter is cut into parts so a Branching Scenario never runs over its slide limit. Pure. */
class ProScenarioBudgetTest extends TestCase
{
    /** @return array<int,array<string,mixed>> n topic acts of $each concepts, then the mission */
    private function acts(int $topics, int $each = 2): array
    {
        $acts = [];
        $id = 100;
        for ($t = 1; $t <= $topics; $t++) {
            $ids = [];
            for ($c = 0; $c < $each; $c++) {
                $ids[] = $id++;
            }
            $acts[] = ['key' => 'a' . $t, 'kind' => StoryActs::TOPIC, 'title' => "Topic $t", 'topic_id' => $t, 'concept_ids' => $ids, 'required_ids' => $ids, 'description' => "About topic $t."];
        }
        $acts[] = ['key' => 'a' . ($topics + 1), 'kind' => StoryActs::MISSION, 'title' => 'The final mission', 'topic_id' => null, 'concept_ids' => range(100, $id - 1), 'required_ids' => [100, 102], 'description' => 'Apply it.'];

        return $acts;
    }

    public function test_fifty_slides_allow_seven_parts_of_six_slides_with_a_cover_an_objectives_slide_and_a_summary(): void
    {
        $this->assertSame(7, ProScenarioBudget::maxParts(50));
        $this->assertSame(45, ProScenarioBudget::slides(7));
        $this->assertLessThanOrEqual(50, ProScenarioBudget::slides(ProScenarioBudget::maxParts(50)));
        $this->assertSame(2, ProScenarioBudget::maxParts(10), 'never fewer than a part and the mission');
    }

    public function test_a_chapter_that_fits_is_left_as_it_is(): void
    {
        $acts = $this->acts(5);

        $this->assertSame($acts, ProScenarioBudget::group($acts, 50));
        $this->assertSame($acts, ProScenarioBudget::group($acts, null), 'no limit, no grouping');
    }

    public function test_a_long_chapter_is_grouped_in_order_into_the_parts_that_fit_and_keeps_every_concept(): void
    {
        foreach ([8, 12, 20] as $topics) {
            $acts = $this->acts($topics);
            $grouped = ProScenarioBudget::group($acts, 50);

            $this->assertLessThanOrEqual(7, count($grouped), "$topics topics");
            $this->assertLessThanOrEqual(50, ProScenarioBudget::slides(count($grouped)));
            $this->assertSame(StoryActs::MISSION, end($grouped)['kind'], 'the mission stays last');

            $topicParts = array_slice($grouped, 0, -1);
            $this->assertSame(
                array_merge(...array_map(fn ($a) => $a['concept_ids'], array_slice($acts, 0, -1))),
                array_merge(...array_map(fn ($a) => $a['concept_ids'], $topicParts)),
                'every concept, in the chapter\'s order'
            );
            $this->assertSame(array_map(fn ($i) => 'a' . ($i + 1), array_keys($grouped)), array_column($grouped, 'key'), 'keys run on');
            foreach ($topicParts as $a) {
                $this->assertLessThanOrEqual(60, mb_strlen($a['title']));
            }
        }
    }

    public function test_the_groups_are_about_the_same_size(): void
    {
        $sizes = array_map(fn ($a) => count($a['concept_ids']), array_slice(ProScenarioBudget::group($this->acts(8), 50), 0, -1));

        $this->assertLessThanOrEqual(2 * min($sizes), max($sizes), 'no part holds twice as much as another');
    }

    public function test_a_merged_part_names_its_topics(): void
    {
        $grouped = ProScenarioBudget::group($this->acts(8), 50);
        $merged = array_values(array_filter($grouped, fn ($a) => isset($a['topics'])));

        $this->assertNotEmpty($merged);
        $this->assertStringContainsString(' and ', $merged[0]['title']);
        $this->assertGreaterThanOrEqual(2, count($merged[0]['topics']));
    }
}