<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\Story\StoryHotspots;
use PHPUnit\Framework\TestCase;

/** The parts of a story picture: what the picture check returns, made into hotspots-slide parts, and the rules that refuse a bad one. No framework, no network. */
class StoryHotspotsTest extends TestCase
{
    private function part(string $label, float $x, float $y, float $w = 20, float $h = 20, ?string $text = null): array
    {
        return ['label' => $label, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'text' => $text ?? "The $label shows how the idea in this scene looks in practice."];
    }

    public function test_parts_become_hotspot_parts_placed_by_their_centre(): void
    {
        $spots = StoryHotspots::spots([$this->part('Wheel', 10, 20, 30, 40), $this->part('Chain', 60, 50, 20, 20)]);

        $this->assertCount(2, $spots);
        $this->assertSame(['id' => 'h1', 'label' => 'Wheel', 'x' => 25.0, 'y' => 40.0, 'w' => 30.0, 'h' => 40.0], array_diff_key($spots[0], ['text' => 1]));
        $this->assertSame(70.0, $spots[1]['x']);
        $this->assertSame(60.0, $spots[1]['y']);
        $this->assertStringContainsString('Chain', $spots[1]['text']);
    }

    public function test_fewer_than_two_usable_parts_give_none_and_at_most_four_are_kept(): void
    {
        $this->assertSame([], StoryHotspots::spots([$this->part('Wheel', 10, 10)]));
        $this->assertSame([], StoryHotspots::spots([]));
        $many = [$this->part('A one', 0, 0), $this->part('B two', 25, 0), $this->part('C three', 50, 0), $this->part('D four', 75, 0), $this->part('E five', 0, 50)];
        $this->assertCount(4, StoryHotspots::spots($many));
    }

    public function test_unusable_parts_are_dropped(): void
    {
        $good = [$this->part('Wheel', 10, 10), $this->part('Chain', 60, 60)];
        $bad = [
            $this->part('Off the edge', 90, 90, 30, 30),                 // outside the picture
            $this->part('Tiny', 10, 10, 3, 3),                          // too small to be a part
            ['label' => '', 'x' => 40, 'y' => 40, 'w' => 10, 'h' => 10, 'text' => 'A long enough explanation of a thing.'],
            $this->part('With digit 5', 40, 5),                          // a figure in the label
            $this->part('Numbered', 40, 70, 10, 10, 'It spins at 300 turns a minute in the scene.'),   // a figure in the words
            $this->part('Short', 5, 70, 10, 10, 'Too short.'),
            $this->part('Whole', 0, 0, 100, 100),                       // the whole picture points at nothing
            'not an array',
        ];

        $spots = StoryHotspots::spots(array_merge([$bad[0]], $good, array_slice($bad, 1)));

        $this->assertSame(['Wheel', 'Chain'], array_column($spots, 'label'));
    }

    public function test_the_same_part_twice_is_kept_once(): void
    {
        $spots = StoryHotspots::spots([$this->part('Wheel', 10, 10, 30, 30), $this->part('Rim', 12, 12, 26, 26), $this->part('Chain', 60, 60), $this->part('wheel', 60, 10)]);

        $this->assertSame(['Wheel', 'Chain'], array_column($spots, 'label'), 'an overlapping box and a repeated name are the same part');
    }

    public function test_the_story_rule_accepts_good_hotspots_and_names_what_is_wrong_with_bad_ones(): void
    {
        $good = ['hotspots' => StoryHotspots::spots([$this->part('Wheel', 10, 10), $this->part('Chain', 60, 60)])];
        $this->assertSame([], StoryHotspots::errors($good));
        $this->assertSame([], StoryHotspots::errors([]), 'no hotspots is fine');
        $this->assertSame([], StoryHotspots::errors(['hotspots' => null]));

        $this->assertNotSame([], StoryHotspots::errors(['hotspots' => [['id' => 'h1', 'label' => 'A', 'text' => 'x', 'x' => 10, 'y' => 10, 'w' => 10, 'h' => 10]]]), 'one part');
        $outside = $good;
        $outside['hotspots'][0]['x'] = 99;
        $this->assertStringContainsString('outside its picture', implode(' ', StoryHotspots::errors($outside)));
        $noWords = $good;
        $noWords['hotspots'][1]['text'] = '';
        $this->assertStringContainsString('label and an explanation', implode(' ', StoryHotspots::errors($noWords)));
        $sameId = $good;
        $sameId['hotspots'][1]['id'] = 'h1';
        $this->assertStringContainsString('its own id', implode(' ', StoryHotspots::errors($sameId)));
    }
}
