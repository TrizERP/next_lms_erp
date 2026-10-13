<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\StudyDeckGuidePdfRenderer;
use App\Services\StudyDeck\StudyDeckPdfRenderer;

/**
 * The study guide PDF is the classroom PDF made to be read and navigated: a picture cover, a contents page that links to every
 * slide, a colour for each topic, and a button on each slide that opens it in the Study Deck. It must still say everything the
 * plain renderer says (so every one of those checks runs against it too), and it must change nothing for the study documents,
 * which use the plain renderer. NO DATABASE, NO object store, NO Dompdf.
 */
class StudyDeckGuidePdfRendererTest extends StudyDeckPdfRendererTest
{
    protected function make(?callable $imageBytes = null, ?string $markerFont = null): StudyDeckPdfRenderer
    {
        return new StudyDeckGuidePdfRenderer($imageBytes, $markerFont);
    }

    /** The cover and the contents each end a page (the plain cover's rule is still in the shared stylesheet). */
    protected function forcedBreaks(): int
    {
        return 3;
    }

    /** A photograph sits in a figure card under the heading, 300px tall at most. */
    protected function photoHeightCap(): int
    {
        return 300;
    }

    protected function readingKey(): string
    {
        return 'Colour key';
    }

    /** A chapter of two topics, three slides, so the contents, the colours and the bookmarks have something to say. */
    private function chapter(): array
    {
        $slide = fn (int $n, string $type, string $title, array $taught) => [
            'n' => $n, 'slide_type' => $type, 'title' => $title, 'taught_concept_ids' => $taught, 'concept_ids' => $taught,
            'content' => ['body' => '', 'explanations' => [['label' => 'Idea', 'text' => 'Cells are small.']], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null, 'key_idea' => null, 'discussion' => null],
            'interaction' => null, 'image' => null,
        ];

        return [
            'version' => 3,
            'chapter' => ['id' => 1, 'name' => 'The cell', 'number' => 2, 'standard_name' => '9', 'subject_name' => 'Science'],
            'concepts' => [
                '7' => ['id' => 7, 'name' => 'Microscopes', 'definition' => null, 'topic_id' => 100],
                '8' => ['id' => 8, 'name' => 'Cell membrane', 'definition' => null, 'topic_id' => 200],
            ],
            'outline' => [
                ['topic_id' => 100, 'name' => 'How we study cells', 'concept_ids' => [7]],
                ['topic_id' => 200, 'name' => 'Membrane and wall', 'concept_ids' => [8]],
            ],
            'taught_by' => ['7' => [2], '8' => [3, 4]],
            'slides' => [
                ['n' => 1, 'slide_type' => 'cover', 'title' => 'The cell', 'content' => ['body' => 'The building block of life.'], 'taught_concept_ids' => [], 'concept_ids' => [], 'interaction' => null, 'image' => null],
                $slide(2, 'concept_intro', 'Seeing small things', [7]),
                $slide(3, 'concept_intro', 'A membrane that chooses', [8]),
                $slide(4, 'worked_example', 'Water across the membrane', [8]),
            ],
        ];
    }

    private function guideHtml(array $options = []): string
    {
        return $this->make()->html($this->chapter(), '', $options);
    }

    public function test_the_cover_names_the_chapter_and_counts_what_is_inside(): void
    {
        $html = $this->guideHtml();

        $this->assertStringContainsString('id="cover"', $html);
        $t = $this->text($html);
        foreach (['Chapter 2', 'The cell', 'STUDY GUIDE', 'The building block of life.', 'CLASS 9 · SCIENCE'] as $needle) {
            $this->assertStringContainsString($needle, $t);
        }
    }

    public function test_the_practice_copy_is_marked_on_the_cover(): void
    {
        $this->assertStringContainsString('PRACTICE COPY', $this->text($this->guideHtml(['variant' => StudyDeckPdfRenderer::PRACTICE])));
        $this->assertStringNotContainsString('PRACTICE COPY', $this->text($this->guideHtml()));
    }

    public function test_the_contents_links_every_topic_and_every_concept_to_its_slide(): void
    {
        $html = $this->guideHtml();

        $this->assertStringContainsString('id="contents"', $html);
        foreach (['#topic-1', '#topic-2', '#slide-2', '#slide-3'] as $target) {
            $this->assertStringContainsString('href="' . $target . '"', $html, "the contents should link to $target");
        }
        // Each link has something to land on.
        foreach (['topic-1', 'topic-2', 'slide-2', 'slide-3', 'slide-4'] as $anchor) {
            $this->assertStringContainsString('id="' . $anchor . '"', $html, "$anchor should exist");
        }
        $t = $this->text($html);
        $this->assertStringContainsString('How we study cells', $t);
        $this->assertStringContainsString('Slides 3 to 4', $t, 'a topic says which slides it runs over');
    }

    public function test_the_contents_is_a_single_page_block_that_cannot_leave_its_heading_alone(): void
    {
        $html = $this->guideHtml();

        // The heading and the first topic are in one unbreakable block (see the blank page 2 this replaced).
        $this->assertSame(2, substr_count($html, 'class="keep gk"'));
        $this->assertStringContainsString('Colour key', $this->text($html));
    }

    public function test_a_topic_band_opens_each_topic_once_in_its_own_colour(): void
    {
        $html = $this->guideHtml();

        $this->assertSame(2, substr_count($html, 'class="gtb"'), 'one band per topic, not one per slide');
        $this->assertStringContainsString('TOPIC 1', $this->text($html));
        $this->assertStringContainsString('TOPIC 2', $this->text($html));
        $this->assertNotSame(
            preg_match('/class="sl (tp\d)"/', $html, $a) ? $a[1] : null,
            preg_match_all('/class="sl (tp\d)"/', $html, $all) ? $all[1][count($all[1]) - 1] : null,
            'the two topics get different colour families'
        );
    }

    public function test_each_slide_has_a_button_into_the_study_deck_only_when_there_is_somewhere_to_go(): void
    {
        $withLinks = $this->guideHtml(['link_base' => 'https://lms.example/student/study-deck/8593', 'content_id' => 61406]);
        $this->assertStringContainsString('href="https://lms.example/student/study-deck/8593?content=61406&amp;slide=3"', $withLinks);
        $this->assertSame(3, substr_count($withLinks, 'Open in Study Deck'), 'one button for each of the three slides');

        $this->assertStringNotContainsString('Study Deck &#9654;', $this->guideHtml(), 'no link base, no button that goes nowhere');
    }

    public function test_the_bookmarks_follow_the_chapter_cover_contents_then_each_topic_with_its_slides(): void
    {
        $tree = ($this->make())->outline($this->chapter());

        $this->assertSame(['Cover', 'Chapter at a glance', '1  How we study cells', '2  Membrane and wall'], array_column($tree, 'title'));
        $this->assertSame(['cover', 'contents', 'topic-1', 'topic-2'], array_column($tree, 'anchor'));
        $this->assertSame(['2  Seeing small things'], array_column($tree[2]['children'], 'title'));
        $this->assertSame(['slide-3', 'slide-4'], array_column($tree[3]['children'], 'anchor'));
    }

    public function test_a_slide_that_comes_before_any_topic_sits_under_getting_started(): void
    {
        $deck = $this->chapter();
        array_splice($deck['slides'], 1, 0, [[
            'n' => 9, 'slide_type' => 'hook', 'title' => 'Why cells', 'taught_concept_ids' => [], 'concept_ids' => [],
            'content' => ['body' => 'Look closer.'], 'interaction' => null, 'image' => null,
        ]]);

        $tree = ($this->make())->outline($deck);

        $this->assertSame('Getting started', $tree[2]['title']);
        $this->assertSame(['slide-9'], array_column($tree[2]['children'], 'anchor'));
    }

    public function test_the_plain_renderer_is_untouched_by_the_guide(): void
    {
        $plain = (new StudyDeckPdfRenderer())->html($this->chapter(), '');

        $this->assertStringNotContainsString('id="cover"', $plain);
        $this->assertStringNotContainsString('gtb', $plain);
        $this->assertStringNotContainsString('Colour key', $this->text($plain));
    }
}
