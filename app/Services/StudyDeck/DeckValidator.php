<?php

namespace App\Services\StudyDeck;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Checks a finished study deck before it is allowed to be stored.
 *
 * Reuses the thresholds of `lms:validate-content` (60 body words, 7 bullets, a check
 * on every slide, labelled callouts, alt text, no emoji, 3 Bloom levels) and adds what
 * a study deck needs on top:
 *   - slide count, and every concept TAUGHT (a real explanation), not merely named
 *   - concept -> slide mapping, and the concept -> question mapping of whatever optional practice is placed
 *   - practice questions: few, at most one per slide, none on framing slides, each with a stored explanation
 *   - interactions (hotspots, scenarios, reveals): well-formed, on a slide that can carry them, and grounded
 *   - questions that stand on their own (context-dependence re-checked on the exact text)
 *   - activity specs that name a target the runtime can play, and branching only where a
 *     real decision exists
 *   - image provenance, licence, and alt text that came from the chosen picture
 *   - no name-only stubs, no example that merely repeats the explanation
 *   - a lexical grounding check against the chapter text
 *
 * The grounding check is deliberately blunt: it finds numbers and capitalised names in
 * the slide text that appear nowhere in the chapter text or the attached questions. It
 * cannot judge truth - only that a claim was not lifted from the book - so what it
 * reports is for a human to review, not proof.
 *
 * Returns a report; it never throws and never stores anything.
 */
class DeckValidator
{
    public const BLOOM = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];

    public function __construct(
        private readonly QuestionSelector $questions = new QuestionSelector(),
        private readonly H5pPatternSelector $patterns = new H5pPatternSelector(),
        private readonly ImagePlanner|null $images = null,
        private readonly int $maxSlideWords = 60,
        private readonly int $maxBullets = 7,
        private readonly int $minBloom = 3,
        private readonly float $maxMissingVisualShare = 0.4,
    ) {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,array<int,array<string,mixed>>> $eligible
     * @param array{html:string,deck:array<string,mixed>} $rendered SlideHtmlRenderer::render()
     * @param array<int,array{id:int,flags:array<int,string>}> $flagged wording-flagged questions
     * @return array{ok:bool, errors:array<int,string>, warnings:array<int,string>, stats:array<string,mixed>}
     */
    public function validate(array $context, array $map, array $eligible, array $rendered, array $flagged = []): array
    {
        $errors = [];
        $warnings = [];
        $deck = $rendered['deck'];
        $min = $map['target_slides']['min'];
        $max = $map['target_slides']['max'];

        if ($deck['slide_count'] < $min || $deck['slide_count'] > $max) {
            $errors[] = sprintf('Deck has %d slides; the target is %d-%d.', $deck['slide_count'], $min, $max);
        }

        $this->teaching($map, $deck, $errors);
        $this->questionRules($deck, $eligible, $flagged, $errors, $warnings);
        $this->activityRules($deck, $eligible, $errors);
        $this->patternRules($deck, $errors);
        $this->interactionRules($deck, $errors, $warnings);
        $this->imageRules($deck, $errors, $warnings);
        $this->duplicateRules($deck, $errors);
        $this->markup($rendered['html'], $map, $deck, $errors);
        $this->grounding($rendered['html'], $deck, $context, $eligible, $errors, $warnings);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => [
                'slides' => $deck['slide_count'],
                'concepts' => count($map['concepts']),
                'questions_placed' => array_sum(array_map('count', $deck['concept_questions'])),
                'activities' => array_sum(array_map(fn ($s) => count($s['activities']), $deck['slides'])),
                'images' => count(array_filter($deck['slides'], fn ($s) => $s['image'] !== null)),
                'visuals_planned' => count(array_filter($deck['slides'], fn ($s) => $s['visual'] !== null)),
                'wording_flagged' => count($flagged),
                'interactions' => array_count_values(array_filter(array_map(fn ($s) => $s['interaction']['kind'] ?? null, $deck['slides']))),
                'interactive_slides' => count(array_filter($deck['slides'], fn ($s) => ($s['interaction'] ?? null) !== null)),
                'learning_slides' => count(array_filter($deck['slides'], fn ($s) => $s['slide_type'] !== 'cover')),
                'discussion_prompts' => count(array_filter($deck['slides'], fn ($s) => !empty($s['content']['discussion']))),
            ],
        ];
    }

    /** Every concept is TAUGHT: some slide lists it and gives it an explanation of its own. */
    private function teaching(array $map, array $deck, array &$errors): void
    {
        $explanations = [];
        foreach ($deck['slides'] as $s) {
            foreach ($s['content']['explanations'] as $e) {
                $explanations[$e['concept_id']][] = str_word_count($e['text']);
            }
        }

        $untaught = [];
        $thin = [];
        foreach ($map['concepts'] as $id => $c) {
            if (empty($deck['taught_by'][$id]) || empty($explanations[$id])) {
                $untaught[] = $c['name'];
            } elseif (max($explanations[$id]) < SlideContentGenerator::MIN_EXPLANATION_WORDS) {
                $thin[] = $c['name'];
            }
        }
        if ($untaught) {
            $errors[] = 'Concepts with no explanation of their own (named or summarised only): ' . implode('; ', $untaught);
        }
        if ($thin) {
            $errors[] = 'Concepts whose explanation is too thin to teach (under ' . SlideContentGenerator::MIN_EXPLANATION_WORDS . ' words): ' . implode('; ', $thin);
        }
        foreach ($deck['slides'] as $s) {
            if (count($s['taught_concept_ids']) > SlidePlanner::MAX_TAUGHT_PER_SLIDE) {
                $errors[] = "Slide {$s['n']} teaches too many concepts to explain each properly.";
            }
        }
    }

    private function questionRules(array $deck, array $eligible, array $flagged, array &$errors, array &$warnings): void
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }
        $flags = array_column($flagged, 'flags', 'id');

        $placed = array_sum(array_map(fn ($s) => count($s['question_ids']), $deck['slides']));
        if ($placed > SlidePlanner::MAX_PRACTICE) {
            $errors[] = "The deck places $placed practice questions; practice is optional, so the limit is " . SlidePlanner::MAX_PRACTICE . '.';
        }

        $seen = [];
        foreach ($deck['slides'] as $s) {
            if (count($s['question_ids']) > SlidePlanner::MAX_QUESTIONS_PER_SLIDE) {
                $errors[] = "Slide {$s['n']} has " . count($s['question_ids']) . ' bank questions; the limit is ' . SlidePlanner::MAX_QUESTIONS_PER_SLIDE . '.';
            }
            if ($s['question_ids'] && in_array($s['slide_type'], SlidePlanner::FRAMING, true)) {
                $errors[] = "Slide {$s['n']} is a {$s['slide_type']} slide and must carry no bank question.";
            }
            foreach ($s['question_ids'] as $qid) {
                if (!isset($byId[$qid])) {
                    $errors[] = "Slide {$s['n']}: question $qid is not an eligible bank question.";
                    continue;
                }
                $q = $byId[$qid];
                if (isset($seen[$qid])) {
                    $errors[] = "Question $qid appears on more than one slide.";
                }
                $seen[$qid] = true;

                if ($reason = $this->questions->dependsOnMissingContext($q['stem'])) {
                    $errors[] = "Slide {$s['n']}: question $qid is not self-contained ($reason).";
                }
                foreach ($q['options'] as $o) {
                    if ($reason = $this->questions->dependsOnMissingContext($o['text'])) {
                        $errors[] = "Slide {$s['n']}: an option of question $qid is not self-contained ($reason).";
                    }
                }
                if (trim($q['explanation']) === '' && trim($q['answer_text']) === '') {
                    $errors[] = "Slide {$s['n']}: question $qid has no stored explanation or model answer to show after the answer.";
                }
                if (!empty($q['flags']) || isset($flags[$qid])) {
                    $warnings[] = "Slide {$s['n']}: question $qid needs a human wording review (" . implode('; ', $q['flags'] ?: $flags[$qid]) . ').';
                }
            }
        }
    }

    private function activityRules(array $deck, array $eligible, array &$errors): void
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }
        foreach ($deck['slides'] as $s) {
            $placed = array_column(array_filter($s['activities'], fn ($a) => ($a['source'] ?? '') === 'bank'), 'question_id');
            if (array_diff($s['question_ids'], $placed) || array_diff($placed, $s['question_ids'])) {
                $errors[] = "Slide {$s['n']}: the activities do not match the placed questions.";
            }
            foreach ($s['activities'] as $a) {
                if (!in_array($a['as'] ?? '', ActivityPlanner::TARGETS, true)) {
                    $errors[] = "Slide {$s['n']}: activity asks for \"" . ($a['as'] ?? '') . '", which the player cannot render.';
                }
                if (!in_array($a['label'] ?? '', ActivityPlanner::LABELS, true)) {
                    $errors[] = "Slide {$s['n']}: activity label \"" . ($a['label'] ?? '') . '" is not one of ' . implode(', ', ActivityPlanner::LABELS) . '.';
                }
                if (($a['source'] ?? '') === 'authored') {
                    $q = $a['question'] ?? [];
                    if (trim((string) ($q['question'] ?? '')) === '' || trim((string) ($q['model_answer'] ?? '')) === '') {
                        $errors[] = "Slide {$s['n']}: an authored check needs a question and an answer.";
                    }
                }
                if (($a['source'] ?? '') === 'bank' && !isset($byId[$a['question_id']])) {
                    $errors[] = "Slide {$s['n']}: activity names question {$a['question_id']}, which is not eligible.";
                }
            }
            if (($s['h5p_pattern']['type'] ?? null) === 'branching' && !array_filter($s['activities'], fn ($a) => !empty($a['decision']))) {
                $errors[] = "Slide {$s['n']}: a branching pattern was chosen but no decision question realises it; branching must be justified by a real decision.";
            }
            foreach ($s['activities'] as $a) {
                if (!empty($a['decision']) && ($s['h5p_pattern']['type'] ?? null) !== 'branching') {
                    $errors[] = "Slide {$s['n']}: a decision activity on a slide that did not choose branching.";
                }
            }
        }
    }

    /**
     * Interactions are lesson content, so they are held to the lesson's rules: the right slide,
     * a well-formed shape, labels that really are on the drawn picture, a scenario graph that
     * ends, and a stated reason. The grounding check reads their wording too.
     */
    private function interactionRules(array $deck, array &$errors, array &$warnings): void
    {
        $count = [];

        foreach ($deck['slides'] as $s) {
            $i = $s['interaction'] ?? null;
            if ($i === null) {
                continue;
            }
            $n = $s['n'];
            $kind = $i['kind'] ?? '';
            if (!in_array($kind, InteractionPlanner::KINDS, true)) {
                $errors[] = "Slide $n: interaction kind \"$kind\" is not one of " . implode(', ', InteractionPlanner::KINDS) . '.';
                continue;
            }
            if (trim((string) ($i['reason'] ?? '')) === '') {
                $errors[] = "Slide $n: the $kind interaction does not say why it is there.";
            }
            if ($s['slide_type'] === 'cover') {
                $errors[] = "Slide $n: the title slide carries no interaction.";
            }
            $count[$kind] = ($count[$kind] ?? 0) + 1;

            if ($kind === 'hotspots') {
                $labels = array_map('mb_strtolower', (array) ($s['image']['texts'] ?? []));
                if (($s['image']['type'] ?? '') !== 'diagram') {
                    $errors[] = "Slide $n: hotspots need a drawn diagram to sit on.";
                }
                if (count($i['spots'] ?? []) < 2) {
                    $errors[] = "Slide $n: hotspots need at least two spots.";
                }
                foreach ((array) ($i['spots'] ?? []) as $sp) {
                    if (!in_array(mb_strtolower((string) $sp['label']), $labels, true)) {
                        $errors[] = "Slide $n: hotspot \"{$sp['label']}\" is not a label on the diagram.";
                    }
                    if ($sp['x'] < 0 || $sp['x'] > 100 || $sp['y'] < 0 || $sp['y'] > 100) {
                        $errors[] = "Slide $n: hotspot \"{$sp['label']}\" is outside the picture.";
                    }
                }
            } elseif ($kind === 'scenario') {
                if (!in_array($s['slide_type'], InteractionPlanner::SCENARIO_SLIDES, true)) {
                    $errors[] = "Slide $n: a scenario needs a slide that is a real situation; this is a {$s['slide_type']} slide.";
                }
                $ids = array_column((array) ($i['nodes'] ?? []), 'id');
                if (!in_array($i['start'] ?? null, $ids, true)) {
                    $errors[] = "Slide $n: the scenario has no starting decision.";
                }
                foreach ((array) ($i['nodes'] ?? []) as $node) {
                    if (count($node['choices'] ?? []) < 2) {
                        $errors[] = "Slide $n: a scenario decision needs at least two choices.";
                    }
                    foreach ((array) ($node['choices'] ?? []) as $ch) {
                        if ($ch['next'] !== null && !in_array($ch['next'], $ids, true)) {
                            $errors[] = "Slide $n: a scenario choice leads to a decision that does not exist.";
                        }
                    }
                }
            } elseif (in_array($kind, InteractionPlanner::ITEM_KINDS, true)) {
                $items = (array) ($i['items'] ?? []);
                $min = match ($kind) {
                    'steps', 'timeline' => 3,
                    default => 2,
                };
                if (count($items) < $min) {
                    $errors[] = "Slide $n: a $kind interaction needs at least $min items.";
                }
                foreach ($items as $item) {
                    if (trim((string) ($item['label'] ?? '')) === '' || trim((string) ($item['text'] ?? '')) === '') {
                        $errors[] = "Slide $n: a $kind item has no label or no explanation.";
                    }
                    if ($kind === 'timeline' && trim((string) ($item['when'] ?? '')) === '') {
                        $errors[] = "Slide $n: a timeline event has no date.";
                    }
                }
                if ($kind === 'compare' && trim((string) ($i['wrapup'] ?? '')) === '') {
                    $errors[] = "Slide $n: a compare has no line saying how the items compare.";
                }
            } elseif ($kind === 'match') {
                if (count($i['pairs'] ?? []) < 3) {
                    $errors[] = "Slide $n: a match needs at least three pairs.";
                }
            } elseif ($kind === 'order' && count($i['items'] ?? []) < 3) {
                $errors[] = "Slide $n: an order needs at least three items.";
            }
        }

        foreach (InteractionPlanner::CAPS as $kind => $limit) {
            if (($count[$kind] ?? 0) > $limit) {
                $errors[] = "The deck has {$count[$kind]} $kind interactions; the limit is $limit.";
            }
        }

        $bare = [];
        foreach ($deck['slides'] as $x) {
            if ($x['slide_type'] !== 'cover' && ($x['interaction'] ?? null) === null) {
                $bare[] = $x['n'];
            }
        }
        if ($bare) {
            $warnings[] = 'Slides with no interaction (' . count($bare) . '): ' . implode(', ', $bare) . '. Every learning slide is meant to have one that teaches; check these were left plain for a reason.';
        }
    }
    private function patternRules(array $deck, array &$errors): void
    {
        foreach ($deck['slides'] as $s) {
            $p = $s['h5p_pattern'];
            if ($p !== null && (!$this->patterns->isValid($p['type'] ?? null) || trim((string) ($p['reason'] ?? '')) === '')) {
                $errors[] = "Slide {$s['n']}: invalid h5p_pattern metadata.";
            }
        }
    }

    private function imageRules(array $deck, array &$errors, array &$warnings): void
    {
        $planned = 0;
        $missing = 0;
        foreach ($deck['slides'] as $s) {
            if ($s['visual'] === null) {
                continue;
            }
            $planned++;
            $img = $s['image'];
            if ($img === null) {
                $missing++;
                $warnings[] = "Slide {$s['n']}: planned visual not found ({$s['image_missing']}); the slide teaches without it.";
                continue;
            }
            if (trim((string) ($img['alt'] ?? '')) === '' || trim((string) $img['alt']) === trim((string) ($s['visual']['purpose'] ?? ''))) {
                $errors[] = "Slide {$s['n']}: the image's alt text is missing or is the plan's hope rather than a description of the chosen picture.";
            }

            if (($img['type'] ?? 'photo') === 'diagram') {
                if (empty($img['texts']) || empty($img['sha1'])) {
                    $errors[] = "Slide {$s['n']}: a drawn diagram has no recorded labels.";
                }
                continue;
            }

            foreach (['source_url' => 'source', 'licence' => 'licence', 'sha1' => 'stored copy', 'title' => 'title'] as $key => $label) {
                if (empty($img[$key])) {
                    $errors[] = "Slide {$s['n']}: image has no $label recorded.";
                }
            }
            if (empty($img['review']['relevant'])) {
                $warnings[] = "Slide {$s['n']}: image was not relevance-reviewed; confirm it at {$img['source_url']} before publishing.";
            }
            if ($this->images && !$this->images->licenceAccepted((string) ($img['licence'] ?? ''))) {
                $errors[] = "Slide {$s['n']}: image licence \"" . ($img['licence'] ?? '') . '" is not acceptable.';
            }
            if (!empty($img['attribution_required']) && trim((string) ($img['attribution'] ?? '')) === '') {
                $errors[] = "Slide {$s['n']}: image requires attribution but has none.";
            }
            if (($img['match']['shared'] ?? 0) < 1) {
                $errors[] = "Slide {$s['n']}: image has no keyword evidence of matching its query.";
            }
        }

        if ($planned > SlidePlanner::MAX_VISUALS) {
            $errors[] = "The deck plans $planned visuals; the limit is " . SlidePlanner::MAX_VISUALS . '.';
        }
        if ($planned > 0 && $missing / $planned > $this->maxMissingVisualShare) {
            $errors[] = sprintf('%d of %d planned visuals could not be sourced - too many for a visual lesson.', $missing, $planned);
        }
    }

    private function duplicateRules(array $deck, array &$errors): void
    {
        $titles = [];
        foreach ($deck['slides'] as $s) {
            $t = mb_strtolower(trim($s['title']));
            if (isset($titles[$t])) {
                $errors[] = "Slides {$titles[$t]} and {$s['n']} have the same title.";
            }
            $titles[$t] = $s['n'];

            // A slide that is neither a framing slide nor tied to a concept teaches nothing in particular.
            if ($s['concept_ids'] === [] && in_array($s['slide_type'], SlidePlanner::NEEDS_CONCEPT, true)) {
                $errors[] = "Slide {$s['n']} ({$s['slide_type']}) is tied to no concept.";
            }

            // An example earns its place by adding something the explanation did not say.
            $c = $s['content'];
            if ($c['example'] !== null) {
                $other = SlideContentGenerator::explanationText($c) . ' ' . $c['body'] . ' ' . implode(' ', $c['bullets']);
                if (SlideContentGenerator::overlap($c['example'], $other) >= 0.7) {
                    $errors[] = "Slide {$s['n']}: the worked example only repeats the explanation.";
                }
            }
        }
    }

    private function markup(string $html, array $map, array $deck, array &$errors): void
    {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $x = new DOMXPath($doc);
        $has = fn ($cls) => 'contains(concat(" ", normalize-space(@class), " "), " ' . $cls . ' ")';

        $names = array_map(fn ($c) => $c['name'], $map['concepts']);
        $used = [];
        $levels = [];
        foreach ($x->query('//*[@data-block]') as $n) {
            /** @var DOMElement $n */
            if ($c = trim($n->getAttribute('data-concept'))) {
                $used[$c] = true;
            }
            $b = $n->getAttribute('data-bloom');
            if ($b !== '') {
                if (!in_array($b, self::BLOOM, true)) {
                    $errors[] = "Bloom level \"$b\" is not one of the six.";
                } else {
                    $levels[$b] = true;
                }
            }
        }
        if ($invented = array_diff(array_keys($used), $names)) {
            $errors[] = 'data-concept names not in this chapter: ' . implode(', ', $invented);
        }
        if ($notNamed = array_diff($names, array_keys($used))) {
            $errors[] = 'Concepts never named by a data-concept: ' . implode('; ', $notNamed);
        }
        if (count($levels) < $this->minBloom) {
            $errors[] = sprintf('Bloom range is flat: %d level(s), need %d.', count($levels), $this->minBloom);
        }

        // A name-only stub is a concept "covered" without being taught.
        foreach ($x->query('//*[' . $has('callout-label') . ']') as $label) {
            if (trim($label->textContent) === 'Also covered') {
                $errors[] = 'A name-only "Also covered" stub is present; every concept must be explained.';
            }
        }

        $i = 0;
        foreach ($x->query('//*[' . $has('slide') . ']') as $slide) {
            $i++;
            if ($x->query('.//*[@data-block="check"]', $slide)->length === 0) {
                $errors[] = "Slide section $i has no check block.";
            }
            // One explain block per concept the slide teaches, and no more.
            $taught = max(1, count($deck['slides'][$i]['taught_concept_ids'] ?? []));
            if ($x->query('.//*[@data-block="explain"]', $slide)->length > $taught) {
                $errors[] = "Slide section $i has more explain blocks than concepts it teaches.";
            }
            $clone = $slide->cloneNode(true);
            $inner = new DOMXPath($slide->ownerDocument);
            foreach (iterator_to_array($inner->query('.//*[' . $has('callout') . ']', $clone)) as $callout) {
                $callout->parentNode?->removeChild($callout);
            }
            $words = str_word_count(trim(preg_replace('/\s+/', ' ', (string) $clone->textContent)));
            if ($words > $this->maxSlideWords) {
                $errors[] = "Slide section $i has $words words of body text (limit {$this->maxSlideWords}).";
            }
        }

        foreach ($x->query('//ul|//ol') as $list) {
            if ($x->query('./li', $list)->length > $this->maxBullets) {
                $errors[] = 'A list exceeds ' . $this->maxBullets . ' bullets.';
            }
        }
        foreach ($x->query('//*[' . $has('callout') . ']') as $callout) {
            if ($x->query('.//*[' . $has('callout-label') . ']', $callout)->length === 0) {
                $errors[] = 'A callout has no .callout-label.';
            }
        }
        foreach ($x->query('//img') as $img) {
            if (trim($img->getAttribute('alt')) === '') {
                $errors[] = 'An image has no alt text.';
            }
        }
        if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html)) {
            $errors[] = 'Contains emoji.';
        }
    }

    /**
     * Numbers and capitalised names in slide wording that neither the chapter text nor
     * an attached question mentions, plus the labels of any drawn diagram.
     */
    private function grounding(string $html, array $deck, array $context, array $eligible, array &$errors, array &$warnings): void
    {
        $allowed = mb_strtolower($context['ground_truth']);
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                // Tags become spaces, not nothing: stripping "<td>40</td><td>10</td>" to "4010" would hide both numbers.
                $allowed .= ' ' . mb_strtolower(preg_replace('/<[^>]+>/', ' ', $q['stem'] . ' ' . implode(' ', array_column($q['options'], 'text')) . ' ' . $q['explanation'] . ' ' . $q['answer_text']));
            }
        }
        foreach ($context['concepts'] as $c) {
            $allowed .= ' ' . mb_strtolower($c['name']);
        }
        $allowed .= ' ' . mb_strtolower($context['chapter']['chapter_name'] . ' ' . $context['chapter']['subject_name'] . ' ' . $context['chapter']['standard_name']);

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $x = new DOMXPath($doc);
        // Slide numbers, image credits (licence versions such as "4.0") and the credits
        // callout are furniture, not claims about the subject.
        foreach (iterator_to_array($x->query(
            '//*[contains(@class,"slide-num")]|//figure'
            . '|//section[contains(@class,"callout") and ./span[contains(@class,"callout-label") and normalize-space()="Image credits"]]'
            . '|//*[contains(@class,"cover")]'
        )) as $furniture) {
            $furniture->parentNode?->removeChild($furniture);
        }

        // Node by node with a space between: paragraph textContent runs "...| 10" and "85 |..." together as "1085".
        $text = '';
        foreach ($x->query('//text()') as $node) {
            $text .= ' ' . $node->nodeValue;
        }
        foreach ($deck['slides'] as $s) {
            if (($s['image']['type'] ?? '') === 'diagram') {
                $text .= ' ' . implode(' ', $s['image']['texts']);
            }
            // Discussion prompts and interactions are slide wording too.
            $text .= ' ' . implode(' ', array_map(fn ($v) => rtrim((string) $v, '.') . '.', array_merge($this->wording($s['content']['discussion'] ?? null), $this->wording($s['interaction'] ?? null), $this->wording($s['content']['key_idea'] ?? null))));
        }

        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $nums);
        $badNumbers = [];
        foreach (array_unique($nums[0]) as $num) {
            if (!preg_match('/(?<![\d.])' . preg_quote($num, '/') . '(?![\d])/u', $allowed)) {
                $badNumbers[] = $num;
            }
        }
        if ($badNumbers) {
            $errors[] = 'Numbers in slide text that are not in the chapter text: ' . implode(', ', array_slice($badNumbers, 0, 12));
        }

        // Capitalised words that are not sentence starts.
        $badNames = [];
        foreach (preg_split('/(?<=[.!?:])\s+|\n+/u', $text) ?: [] as $sentence) {
            $words = preg_split('/\s+/u', trim($sentence)) ?: [];
            array_shift($words);
            foreach ($words as $w) {
                $w = trim($w, ".,;:()'\"“”‘’?!");
                if (preg_match('/^\p{Lu}\p{Ll}{2,}$/u', $w) && !str_contains($allowed, mb_strtolower($w))) {
                    $badNames[$w] = true;
                }
            }
        }
        if ($badNames) {
            $warnings[] = 'Capitalised names in slide text that are not in the chapter text (review): ' . implode(', ', array_slice(array_keys($badNames), 0, 12));
        }
    }

    /** Every string in a nested structure, in order, skipping machine fields (ids, kinds, flags, coordinates). @return array<int,string> */
    private function wording(mixed $node): array
    {
        $skip = ['id', 'kind', 'start', 'next', 'sound', 'x', 'y', 'w', 'h', 'reason', 'style'];
        $out = [];
        $walk = function ($v, $key = null) use (&$walk, &$out, $skip) {
            if (is_array($v)) {
                foreach ($v as $k => $x) {
                    $walk($x, is_string($k) ? $k : $key);
                }
            } elseif (is_string($v) && $v !== '' && !in_array($key, $skip, true)) {
                $out[] = $v;
            }
        };
        $walk($node);

        return $out;
    }
}
