<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\StudyDeckPdfRenderer;
use PHPUnit\Framework\TestCase;

/**
 * The classroom PDF must say everything the interactive player reveals. A PDF cannot click, so every interaction is
 * written out in full. NO DATABASE, NO object store, NO Dompdf: this is the HTML the PDF is drawn from.
 */
class StudyDeckPdfRendererTest extends TestCase
{
    private const BASE = 'https://s3-triz.fra1.digitaloceanspaces.com/';

    private function render(array $deck): string
    {
        return (new StudyDeckPdfRenderer(self::BASE))->html($deck, '');
    }

    /** Text as a reader sees it: no tags, entities decoded, one space between words. */
    private function text(string $html): string
    {
        $html = (string) preg_replace('#<style.*?</style>#is', '', $html);
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</td>', '</th>', '</p>', '</li>', '<br>'], ' ', $html)), ENT_QUOTES, 'UTF-8')));
    }

    private function deck(array $slide, array $concepts = []): array
    {
        $slide += [
            'n' => 5, 'slide_type' => 'concept_visual', 'title' => 'A title', 'taught_concept_ids' => [7], 'concept_ids' => [7],
            'content' => [], 'interaction' => null, 'image' => null,
        ];
        $slide['content'] += ['body' => '', 'explanations' => [], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null, 'key_idea' => null, 'discussion' => null];

        return [
            'version' => 3,
            'chapter' => ['id' => 1, 'name' => 'Chapter one', 'standard_name' => '9', 'subject_name' => 'Science'],
            'concepts' => $concepts ?: ['7' => ['id' => 7, 'name' => 'Symbols', 'definition' => null]],
            'slides' => [
                ['n' => 1, 'slide_type' => 'cover', 'title' => 'Chapter one', 'content' => ['body' => 'Welcome.'], 'taught_concept_ids' => [], 'concept_ids' => [], 'interaction' => null, 'image' => null],
                $slide,
            ],
        ];
    }

    public function test_every_hotspot_label_and_description_is_written_out(): void
    {
        $html = $this->render($this->deck(['interaction' => [
            'kind' => 'hotspots', 'intro' => 'Select each quantity to see how its symbol works.', 'wrapup' => 'Symbols are a language.',
            'spots' => [
                ['id' => 'h1', 'label' => 'm: mass', 'text' => 'The symbol m stands for mass.'],
                ['id' => 'h2', 'label' => 'F: force', 'text' => 'The symbol F stands for force.'],
            ],
        ]]));
        $t = $this->text($html);

        foreach (['m: mass', 'The symbol m stands for mass.', 'F: force', 'The symbol F stands for force.', 'Symbols are a language.', 'Explore the diagram'] as $needle) {
            $this->assertStringContainsString($needle, $t);
        }
        $this->assertStringNotContainsString('Select each quantity', $t, 'a click instruction means nothing on paper');
    }

    public function test_cards_steps_and_events_keep_their_titles_and_explanations(): void
    {
        $items = [
            ['id' => 'a', 'label' => 'First card', 'text' => 'First explanation.', 'when' => '1905'],
            ['id' => 'b', 'label' => 'Second card', 'text' => 'Second explanation.', 'when' => '1915'],
        ];
        foreach (['reveal', 'steps', 'timeline'] as $kind) {
            $t = $this->text($this->render($this->deck(['interaction' => ['kind' => $kind, 'intro' => '', 'wrapup' => 'Wrap.', 'items' => $items]])));
            foreach (['First card', 'First explanation.', 'Second card', 'Second explanation.', 'Wrap.'] as $needle) {
                $this->assertStringContainsString($needle, $t, $kind);
            }
        }
        $steps = $this->render($this->deck(['interaction' => ['kind' => 'steps', 'items' => $items, 'wrapup' => '']]));
        $this->assertLessThan(strpos($steps, 'Second card'), strpos($steps, 'First card'), 'steps stay in order');
        $this->assertStringContainsString('Step 1', $this->text($steps));
        $this->assertStringContainsString('1905 First card', $this->text($this->render($this->deck(['interaction' => ['kind' => 'timeline', 'items' => $items, 'wrapup' => '']]))), 'an event keeps its date');
    }

    public function test_compare_match_and_order_are_complete(): void
    {
        $compare = $this->text($this->render($this->deck(['interaction' => ['kind' => 'compare', 'intro' => '', 'wrapup' => 'They differ in scale.', 'items' => [
            ['id' => 'a', 'label' => 'Law', 'text' => 'Describes a pattern.'], ['id' => 'b', 'label' => 'Theory', 'text' => 'Explains why.'],
        ]]])));
        foreach (['Law', 'Describes a pattern.', 'Theory', 'Explains why.', 'They differ in scale.', 'How they compare'] as $n) {
            $this->assertStringContainsStringIgnoringCase($n, $compare);
        }

        $match = $this->text($this->render($this->deck(['interaction' => ['kind' => 'match', 'intro' => 'Match each word.', 'wrapup' => '', 'pairs' => [
            ['id' => 'p1', 'term' => 'Mass', 'meaning' => 'Amount of matter.'], ['id' => 'p2', 'term' => 'Force', 'meaning' => 'A push or a pull.'],
        ]]])));
        foreach (['Mass', 'Amount of matter.', 'Force', 'A push or a pull.'] as $n) {
            $this->assertStringContainsString($n, $match);
        }

        $order = $this->render($this->deck(['interaction' => ['kind' => 'order', 'intro' => '', 'wrapup' => '', 'items' => [['id' => 'a', 'text' => 'Observe'], ['id' => 'b', 'text' => 'Measure'], ['id' => 'c', 'text' => 'Test']]]]));
        $this->assertStringContainsString('The correct order', $this->text($order));
        $this->assertTrue(strpos($order, 'Observe') < strpos($order, 'Measure') && strpos($order, 'Measure') < strpos($order, 'Test'));
    }

    public function test_a_scenario_shows_the_situation_every_choice_what_happens_why_and_the_conclusion(): void
    {
        $t = $this->text($this->render($this->deck(['interaction' => ['kind' => 'scenario', 'situation' => 'A prediction fails.', 'start' => 'n1', 'conclusion' => 'Evidence decides.', 'nodes' => [[
            'id' => 'n1', 'prompt' => 'What do you do?', 'choices' => [
                ['id' => 'c1', 'text' => 'Ignore it', 'outcome' => 'Nothing is learned.', 'why' => 'Ignoring evidence is not science.', 'sound' => false, 'next' => null],
                ['id' => 'c2', 'text' => 'Re-examine', 'outcome' => 'You check the model.', 'why' => 'Openness is a strength.', 'sound' => true, 'next' => null],
            ],
        ]]]])));

        foreach (['A prediction fails.', 'What do you do?', 'Ignore it', 'Nothing is learned.', 'Ignoring evidence is not science.', 'Re-examine', 'You check the model.', 'Openness is a strength.', 'Evidence decides.'] as $needle) {
            $this->assertStringContainsString($needle, $t);
        }
        $this->assertSame(1, substr_count($t, 'Sound choice'), 'the sound choice is marked in words, not by colour alone');
    }

    public function test_explanation_definition_example_mistake_key_idea_and_discussion_with_its_answer(): void
    {
        $html = $this->render($this->deck(['content' => [
            'explanations' => [['concept_id' => 7, 'text' => 'The explanation.']],
            'example' => 'The worked example.', 'misconception' => ['wrong_idea' => 'The wrong idea.', 'correction' => 'The correction.'],
            'relationship_note' => 'The connection.', 'key_idea' => 'The key idea.',
            'discussion' => ['prompt' => 'The question?', 'answer' => 'The possible answer.'],
            'bullets' => ['A point.'],
        ]], ['7' => ['id' => 7, 'name' => 'Symbols', 'definition' => 'The concept definition.']]));
        $t = $this->text($html);

        foreach (['The explanation.', 'The concept definition.', 'The worked example.', 'The wrong idea.', 'The correction.', 'The connection.', 'The key idea.', 'The question?', 'Think.', 'Pair.', 'Share.', 'The possible answer.', 'A point.'] as $needle) {
            $this->assertStringContainsString($needle, $t);
        }
    }

    public function test_a_definition_that_only_repeats_the_explanation_is_not_printed_twice_and_a_missing_one_is_not_invented(): void
    {
        $same = $this->text($this->render($this->deck(['content' => ['explanations' => [['concept_id' => 7, 'text' => 'Same words.']]]], ['7' => ['id' => 7, 'name' => 'S', 'definition' => 'same  words.']])));
        $this->assertSame(1, substr_count(strtolower($same), 'same words.'));

        $none = $this->text($this->render($this->deck(['content' => ['explanations' => [['concept_id' => 7, 'text' => 'Only this.']]]])));
        $this->assertStringNotContainsString('DEFINITION', strtoupper($none));
    }

    public function test_a_slide_that_has_a_body_but_no_explanation_prints_its_body(): void
    {
        $t = $this->text($this->render($this->deck(['slide_type' => 'hook', 'taught_concept_ids' => [], 'content' => ['body' => 'Look closely at the frame.']])));
        $this->assertStringContainsString('Look closely at the frame.', $t);
    }

    public function test_only_pictures_on_the_shared_store_are_drawn_with_their_caption_and_alt(): void
    {
        $img = fn (string $url) => ['type' => 'diagram', 'url' => $url, 'alt' => 'Alt text', 'caption' => 'A caption', 'width' => 1280, 'height' => 720];

        $ok = $this->render($this->deck(['image' => $img(self::BASE . 'public/lms_content_file/studydeck/a.png')]));
        $this->assertStringContainsString('<img src="' . self::BASE . 'public/lms_content_file/studydeck/a.png"', $ok);
        $this->assertStringContainsString('A caption', $this->text($ok));

        foreach (['http://localhost:3000/x.png', 'images/x.png', 'https://example.org/x.png', 'C:\\x.png'] as $bad) {
            $this->assertStringNotContainsString('<img', $this->render($this->deck(['image' => $img($bad)])), $bad);
        }
    }

    public function test_a_photo_credit_is_kept(): void
    {
        $html = $this->render($this->deck(['image' => ['type' => 'photo', 'url' => self::BASE . 'p.jpg', 'alt' => 'a', 'caption' => 'Cap', 'creator' => 'Jo', 'licence' => 'BY 2.0', 'attribution' => 'Jo, BY 2.0', 'source_url' => 'https://x', 'width' => 768, 'height' => 1024]]));
        $t = $this->text($html);
        $this->assertStringContainsString('Jo, BY 2.0', $t);
        $this->assertStringContainsString('Image credits', $t);
    }

    public function test_text_is_escaped(): void
    {
        $html = $this->render($this->deck(['title' => '<script>alert(1)</script>', 'content' => ['explanations' => [['concept_id' => 7, 'text' => 'a < b & "c"']]]]));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('a &lt; b &amp; &quot;c&quot;', $html);
    }

    public function test_each_slide_starts_on_its_own_page_after_the_cover(): void
    {
        $html = $this->render($this->deck([]));
        $this->assertStringContainsString('class="sd-slide"', $html, 'the slide after the cover starts a new page');
        $noCover = $this->deck([]);
        array_shift($noCover['slides']);
        $this->assertStringContainsString('class="sd-slide sd-first"', $this->render($noCover), 'a deck with no cover has no blank first page');
    }

    /**
     * Chapter 8592 as generated: every string a learner can reveal is in the PDF's HTML. The bundle lives in
     * storage/app, which is not part of every checkout, so the test only runs where it exists.
     */
    public function test_chapter_8592_loses_nothing(): void
    {
        $file = dirname(__DIR__, 3) . '/storage/app/study-deck/chapter-8592/deck.json';
        if (!is_file($file)) {
            $this->markTestSkipped('The 8592 review bundle is not on this machine.');
        }
        $deck = json_decode((string) file_get_contents($file), true);
        // The stored deck has its pictures on the shared store; the bundle has them beside it. Only text is checked here.
        $text = $this->text($this->render($deck));
        $norm = fn (string $s) => $this->text('<p>' . htmlspecialchars($s) . '</p>');
        $missing = [];
        $count = 0;
        $need = function (string $what, ?string $value) use (&$missing, &$count, $text, $norm): void {
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            $count++;
            if (!str_contains($text, $norm($value))) {
                $missing[] = "$what: " . mb_substr($value, 0, 60);
            }
        };

        foreach ($deck['concepts'] as $c) {
            // A definition is printed on the slide that teaches the concept (unless the slide already says it).
            if (!empty($deck['taught_by'][(string) $c['id']])) {
                $need('definition of ' . $c['name'], $c['definition'] ?? '');
            }
        }
        foreach ($deck['slides'] as $s) {
            $n = $s['n'];
            $k = $s['content'];
            foreach ($k['explanations'] as $e) {
                $need("slide $n explanation", $e['text']);
            }
            foreach ($k['bullets'] as $b) {
                $need("slide $n bullet", $b);
            }
            $need("slide $n example", $k['example']);
            $need("slide $n mistake", $k['misconception']['wrong_idea'] ?? '');
            $need("slide $n correction", $k['misconception']['correction'] ?? '');
            $need("slide $n key idea", $k['key_idea'] ?? '');
            $need("slide $n connection", $k['relationship_note'] ?? '');
            $need("slide $n discussion", $k['discussion']['prompt'] ?? '');
            $need("slide $n possible answer", $k['discussion']['answer'] ?? '');
            $i = $s['interaction'] ?? null;
            if (!$i) {
                continue;
            }
            $need("slide $n wrapup", $i['wrapup'] ?? '');
            foreach (['spots', 'items'] as $list) {
                foreach ($i[$list] ?? [] as $x) {
                    $need("slide $n {$i['kind']} label", $x['label'] ?? '');
                    $need("slide $n {$i['kind']} text", $x['text'] ?? '');
                }
            }
            foreach ($i['pairs'] ?? [] as $p) {
                $need("slide $n term", $p['term']);
                $need("slide $n meaning", $p['meaning']);
            }
            if (($i['kind'] ?? '') === 'scenario') {
                $need("slide $n situation", $i['situation']);
                $need("slide $n conclusion", $i['conclusion']);
                foreach ($i['nodes'] as $node) {
                    $need("slide $n decision", $node['prompt']);
                    foreach ($node['choices'] as $ch) {
                        foreach (['text', 'outcome', 'why'] as $f) {
                            $need("slide $n choice $f", $ch[$f]);
                        }
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'content the player reveals but the PDF lacks');
        $this->assertGreaterThan(400, $count, 'the sweep really covered the deck');
    }
}
