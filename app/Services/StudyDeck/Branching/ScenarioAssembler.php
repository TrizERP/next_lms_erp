<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\lms\H5P\HtmlLite;

/**
 * Skeleton + written text + bank questions -> the payload the editor saves.
 *
 * The output is EXACTLY the shape H5PBranchingScenarioController::store()
 * validates, so a generated scenario is saved by the ordinary save path with no
 * translation layer to get wrong.
 *
 * WHERE EACH WORD COMES FROM:
 *
 *   - a scene, an ending, and the question and choices of a free decision: the
 *     writer;
 *   - a knowledge check: the BANK, verbatim. Its question, its options, which
 *     option is right, and the explanation shown afterwards are the bank row's.
 *     The writer contributes a title and an optional connecting sentence in
 *     front of the question, nothing else. The assembler never rewrites a
 *     question, because rewriting would quietly turn a reviewed question into
 *     an unreviewed one.
 *   - structure (ids, links, which choice is best): the skeleton.
 *
 * ENDINGS ARE SCORED BY OUTCOME, not by the model: a scenario whose best ending
 * were worth whatever the model felt like could not be compared with another.
 */
final class ScenarioAssembler
{
    /** What reaching an ending is worth, out of 100. The pass mark is 60. */
    public const END_SCORES = ['best' => 100, 'good' => 80, 'partial' => 55, 'revisit' => 30];

    private const OUTCOME_RANK = ['best' => 0, 'good' => 1, 'partial' => 2, 'revisit' => 3];

    /**
     * @param array<string,mixed> $blueprint an accepted skeleton
     * @param array<string,array<string,mixed>> $written node id => text
     * @param array<string,mixed> $meta model, focus
     * @return array{payload:array<string,mixed>,suggestions:list<array<string,string>>}
     */
    public function assemble(SourcePack $pack, array $blueprint, array $written, array $meta = []): array
    {
        $byId = [];
        foreach ($blueprint['nodes'] as $n) {
            $byId[$n['id']] = $n;
        }
        $banks = $pack->questionsById();

        $nodes = [];
        $suggestions = [];
        $usedQuestions = [];

        foreach ($this->authoringOrder($blueprint) as $id) {
            $n = $byId[$id];
            $w = $written[$id] ?? [];
            $base = [
                'ref' => $id,
                'purpose' => $n['purpose'] ?: null,
                'title' => (string) ($w['title'] ?? ''),
                'show_title' => true,
                'body' => '',
                'media_path' => '',
                'media_alt' => '',
                'proceed_button_text' => '',
                'next_ref' => null,
                'score' => 0,
                'concept_id' => $n['concept_id'] ?? null,
                'source_question_id' => null,
                'options' => [],
            ];

            if ($n['kind'] === 'scene') {
                $nodes[] = [
                    'node_type' => 'text',
                    'body' => HtmlLite::clean((string) ($w['body'] ?? '')),
                    'proceed_button_text' => 'Continue',
                    'next_ref' => $n['next'],
                ] + $base;

                if (! empty($w['visual'])) {
                    $suggestions[] = ['node' => $id, 'title' => (string) ($w['title'] ?? ''), 'idea' => (string) $w['visual']];
                }
            } elseif ($n['kind'] === 'ending') {
                $nodes[] = [
                    'node_type' => 'ending',
                    'body' => HtmlLite::clean((string) ($w['body'] ?? '')),
                    'score' => self::END_SCORES[$n['outcome']] ?? 0,
                    'purpose' => 'summary',
                ] + $base;

                if (! empty($w['visual'])) {
                    $suggestions[] = ['node' => $id, 'title' => (string) ($w['title'] ?? ''), 'idea' => (string) $w['visual']];
                }
            } elseif (isset($n['question_id'])) {
                $q = $banks[$n['question_id']];
                $usedQuestions[] = (int) $q['id'];
                $nodes[] = $this->check($base, $n, $q, (string) ($w['lead_in'] ?? ''));
            } else {
                $options = [];
                foreach ($n['choices'] as $k => $choice) {
                    $text = $w['choices'][$k] ?? ['text' => '', 'feedback_title' => '', 'feedback_text' => ''];
                    $options[] = [
                        'text' => (string) $text['text'],
                        'next_ref' => $choice['next'],
                        'quality' => $choice['quality'],
                        'feedback_title' => (string) $text['feedback_title'],
                        'feedback_text' => HtmlLite::clean((string) $text['feedback_text']),
                        'feedback_image' => '',
                        'feedback_image_alt' => '',
                    ];
                }
                $nodes[] = [
                    'node_type' => 'branching_question',
                    'body' => HtmlLite::clean((string) ($w['question'] ?? '')),
                    'options' => $options,
                ] + $base;
            }
        }

        $topicIds = array_values(array_unique(array_filter(array_map(fn ($c) => $c['topic_id'], $pack->concepts))));

        return [
            'payload' => [
                'title' => $blueprint['title'],
                'description' => $blueprint['premise'],
                'start_title' => $blueprint['title'],
                'start_subtitle' => HtmlLite::clean('<p>' . htmlspecialchars($blueprint['premise'], ENT_QUOTES, 'UTF-8') . '</p>'),
                'start_image' => '',
                'start_image_alt' => '',
                'learning_objectives' => $blueprint['objectives'],
                'scoring_option' => 'static-end-score',
                'pass_percentage' => 60,
                'enable_backwards_navigation' => true,
                'randomize_alternatives' => false,
                'generation' => [
                    'generator' => 'ai',
                    'model' => $meta['model'] ?? null,
                    'generated_at' => gmdate('c'),
                    'topic_ids' => $topicIds,
                    'concept_ids' => $pack->conceptIds(),
                    'question_ids' => $usedQuestions,
                    'focus' => $meta['focus'] ?? null,
                ],
                'nodes' => $nodes,
            ],
            'suggestions' => $suggestions,
        ];
    }

    /**
     * A knowledge check, from a bank question.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $node the skeleton node
     * @param array<string,mixed> $q the bank question (QuestionSelector::normalise)
     * @return array<string,mixed>
     */
    private function check(array $base, array $node, array $q, string $leadIn): array
    {
        $options = [];
        foreach ($q['options'] as $o) {
            $right = (string) $o['label'] === (string) $q['correct_label'];
            $options[] = [
                'text' => (string) $o['text'],
                'next_ref' => $right ? $node['on_correct'] : $node['on_wrong'],
                'quality' => $right ? 'best' : 'misstep',
                'feedback_title' => $right ? 'That is right' : 'Not quite',
                // The bank's own explanation, which says why the right answer is
                // right. It is what a learner is shown after answering anywhere
                // else in the product, too.
                'feedback_text' => HtmlLite::clean((string) $q['explanation']),
                'feedback_image' => '',
                'feedback_image_alt' => '',
            ];
        }

        return [
            'node_type' => 'branching_question',
            'body' => HtmlLite::clean(trim($leadIn . ' ' . $q['stem'])),
            'options' => $options,
            'source_question_id' => (int) $q['id'],
            'concept_id' => (int) $q['concept_id'],
        ] + $base;
    }

    /**
     * The order the editor lists the nodes in: the start first, the rest as a
     * learner meets them, endings last and best first. The first node is where
     * the scenario starts, so it must stay first.
     *
     * @param array<string,mixed> $blueprint
     * @return list<string>
     */
    private function authoringOrder(array $blueprint): array
    {
        $byId = [];
        foreach ($blueprint['nodes'] as $n) {
            $byId[$n['id']] = $n;
        }

        $reading = ScenarioWriter::readingOrder($blueprint);
        $content = array_values(array_filter($reading, fn ($id) => $byId[$id]['kind'] !== 'ending'));
        $endings = array_values(array_filter($reading, fn ($id) => $byId[$id]['kind'] === 'ending'));
        usort($endings, fn ($a, $b) => (self::OUTCOME_RANK[$byId[$a]['outcome']] ?? 9) <=> (self::OUTCOME_RANK[$byId[$b]['outcome']] ?? 9));

        return array_merge($content, $endings);
    }
}
