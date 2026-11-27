<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\DocumentWriter;
use PHPUnit\Framework\TestCase;

/** An item that breaks its rules is rewritten more than once, and a plan with too many activities is folded down, before a document fails. */
class RepairRoundsTest extends TestCase
{
    public function test_an_item_still_over_its_limit_is_rewritten_again_until_it_fits(): void
    {
        $replies = [
            json_encode(['one' => str_repeat('word ', 35)]),
            json_encode(['one' => str_repeat('word ', 34)]),
            json_encode(['one' => str_repeat('word ', 20)]),
        ];
        $completer = new class($replies) implements Completer {
            public int $calls = 0;

            public function __construct(private array $replies)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                return $this->replies[$this->calls++] ?? '';
            }
        };

        $writer = new class($completer) extends DocumentWriter {
            public function kind(): DocumentKind
            {
                return DocumentKind::RevisionNotes;
            }

            public function run(): array
            {
                return $this->inChunks(
                    ['one' => ['name' => 'One']],
                    'system',
                    1000,
                    'rows',
                    fn (array $chunk) => 'write',
                    fn (array $json, array $chunk) => array_map(fn ($t) => ['text' => trim($t)], array_intersect_key($json, $chunk)),
                    fn (array $item, array $work) => str_word_count($item['text']) > 32 ? ['Over 32 words.'] : []
                );
            }
        };

        $out = $writer->run();

        $this->assertSame(3, $completer->calls, 'first write plus two rewrites');
        $this->assertSame(20, str_word_count($out['one']['text']));
    }

    public function test_an_item_the_group_rounds_could_not_fix_is_rewritten_alone(): void
    {
        $replies = array_map(fn ($n) => json_encode(['one' => str_repeat('word ', $n)]), [35, 34, 33, 33, 20]);
        $completer = new class($replies) implements Completer {
            public int $calls = 0;

            public function __construct(private array $replies)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                return $this->replies[$this->calls++] ?? '';
            }
        };
        $writer = new class($completer) extends DocumentWriter {
            public function kind(): DocumentKind
            {
                return DocumentKind::RevisionNotes;
            }

            public function run(): array
            {
                return $this->inChunks(
                    ['one' => ['name' => 'One']],
                    'system',
                    1000,
                    'rows',
                    fn (array $chunk) => 'write',
                    fn (array $json, array $chunk) => array_map(fn ($t) => ['text' => trim($t)], array_intersect_key($json, $chunk)),
                    fn (array $item, array $work) => str_word_count($item['text']) > 32 ? ['Over 32 words.'] : []
                );
            }
        };

        $out = $writer->run();

        $this->assertSame(5, $completer->calls, 'first write, three group rewrites, one rewrite on its own');
        $this->assertSame(20, str_word_count($out['one']['text']));
    }

    public function test_surplus_activities_are_folded_into_others_without_losing_a_concept(): void
    {
        $plan = [];
        foreach ([[1, 2], [3], [4, 5], [6], [7, 8]] as $i => $ids) {
            $plan[] = ['id' => 'a' . ($i + 1), 'title' => 't' . $i, 'format' => 'x', 'grouping' => 'pair', 'minutes' => 20, 'concept_ids' => $ids, 'focus' => 'f'];
        }

        $fitted = ActivityWriter::fitToMax($plan, 3);

        $this->assertCount(3, $fitted);
        $this->assertSame(['a1', 'a2', 'a3'], array_column($fitted, 'id'));
        $covered = array_unique(array_merge(...array_column($fitted, 'concept_ids')));
        sort($covered);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $covered);
        foreach ($fitted as $a) {
            $this->assertLessThanOrEqual(ActivityWriter::MAX_CONCEPTS, count($a['concept_ids']));
        }
    }

    public function test_a_plan_missing_a_required_kind_of_activity_gets_it_from_an_activity_that_can_spare_it(): void
    {
        $mk = fn (string $format, string $grouping, int $i) => ['id' => 'a' . $i, 'title' => 't' . $i, 'format' => $format, 'grouping' => $grouping, 'minutes' => 20, 'concept_ids' => [$i], 'focus' => 'f'];
        $plan = [
            $mk('inquiry', 'individual', 1), $mk('inquiry', 'pair', 2), $mk('problem_solving', 'group', 3),
            $mk('discussion', 'group', 4), $mk('discussion', 'pair', 5), $mk('reflection', 'individual', 6),
        ];

        $fixed = ActivityWriter::ensureVariety($plan);

        $formats = array_column($fixed, 'format');
        $this->assertNotEmpty(array_intersect($formats, ['quiz', 'matching']), 'a quiz is added');
        $this->assertNotEmpty(array_intersect($formats, ['inquiry', 'problem_solving', 'role_play']));
        $this->assertNotEmpty(array_intersect($formats, ['discussion', 'reflection']));
        $this->assertSame(array_column($plan, 'concept_ids'), array_column($fixed, 'concept_ids'), 'nothing else changes');
        $this->assertCount(1, array_filter(array_map(fn ($a, $b) => $a['format'] !== $b['format'], $plan, $fixed)), 'one activity changed');
    }

    public function test_concepts_a_plan_leaves_out_are_added_to_activities_with_room_preferring_the_same_topic(): void
    {
        $mk = fn (int $i, array $ids) => ['id' => 'a' . $i, 'title' => 't' . $i, 'format' => 'inquiry', 'grouping' => 'pair', 'minutes' => 20, 'concept_ids' => $ids, 'focus' => 'f'];
        $plan = [$mk(1, [1, 2]), $mk(2, [3, 4])];
        $map = ['concepts' => [1 => ['topic_id' => 10], 2 => ['topic_id' => 10], 3 => ['topic_id' => 20], 4 => ['topic_id' => 20], 5 => ['topic_id' => 20], 6 => ['topic_id' => 10]]];

        $fixed = ActivityWriter::coverMissing($plan, [1, 2, 3, 4, 5, 6], $map);

        $this->assertContains(5, $fixed[1]['concept_ids'], 'a topic-20 concept joins the topic-20 activity');
        $this->assertContains(6, $fixed[0]['concept_ids'], 'a topic-10 concept joins the topic-10 activity');
        $covered = array_unique(array_merge(...array_column($fixed, 'concept_ids')));
        sort($covered);
        $this->assertSame([1, 2, 3, 4, 5, 6], $covered);
        $this->assertCount(2, $fixed);
    }

    public function test_no_activity_takes_more_concepts_than_the_limit_when_covering_the_missing(): void
    {
        $plan = [['id' => 'a1', 'title' => 't', 'format' => 'inquiry', 'grouping' => 'pair', 'minutes' => 20, 'concept_ids' => [1, 2, 3, 4], 'focus' => 'f']];

        $fixed = ActivityWriter::coverMissing($plan, [1, 2, 3, 4, 5], ['concepts' => []]);

        $this->assertSame([1, 2, 3, 4], $fixed[0]['concept_ids'], 'no room: left for the rules to report');
    }

    public function test_a_short_plan_is_left_alone_by_the_variety_fix(): void
    {
        $plan = [['id' => 'a1', 'title' => 't', 'format' => 'inquiry', 'grouping' => 'pair', 'minutes' => 20, 'concept_ids' => [1], 'focus' => 'f']];
        $this->assertSame($plan, ActivityWriter::ensureVariety($plan));
    }

    public function test_a_plan_that_already_fits_is_unchanged(): void
    {
        $plan = [['id' => 'a1', 'title' => 't', 'format' => 'x', 'grouping' => 'pair', 'minutes' => 20, 'concept_ids' => [1], 'focus' => 'f']];

        $this->assertSame($plan, ActivityWriter::fitToMax($plan, 3));
    }
}
