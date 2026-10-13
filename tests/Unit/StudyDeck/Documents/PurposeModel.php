<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\Contracts\Completer;

/**
 * A scripted stand-in for the model that writes the purpose-based documents. It reads the data each prompt carries (the work
 * items and the chapter text) and answers with replies that obey the writers' own rules, cut from the chapter's words, so the
 * pipeline from prompt to document can be tested without a model, a database or a network.
 *
 * It answers each KIND of prompt it recognises by a phrase in it. `$calls` counts them, `$asked` keeps the phrase of each.
 */
class PurposeModel implements Completer
{
    /** @var array<int,string> */
    public array $asked = [];

    /** @var array<string,mixed> changes to apply to a reply before it is returned: phrase => callable(array):array */
    public array $tweak = [];

    public function complete(string $system, string $prompt, int $maxTokens = 16000): string
    {
        foreach (['overview' => 'Write the opening of an exam revision pack', 'sheets' => 'Write the REVISION SHEET', 'lessons' => 'Write a remedial LESSON',
            'objectives' => 'learning objective of a remedial class', 'clinic' => 'IDEAS TO CORRECT', 'teacher' => 'brief guidance for the teacher'] as $what => $phrase) {
            if (str_contains($prompt, $phrase)) {
                $this->asked[] = $what;
                $reply = $this->{$what}($prompt, $this->words($prompt));
                if (isset($this->tweak[$what])) {
                    $reply = ($this->tweak[$what])($reply);
                }

                return json_encode($reply);
            }
        }

        return '{}';
    }

    /** The chapter text of the prompt as a list of words, to cut replies from. @return array<int,string> */
    private function words(string $prompt): array
    {
        $text = substr($prompt, (int) strrpos($prompt, "CHAPTER TEXT (the only source of facts)\n") + 40);

        return array_values(array_filter(preg_split('/\s+/', preg_replace('/[^A-Za-z\s]/', ' ', $text)), fn ($w) => $w !== ''));
    }

    /** @var int how many texts have been cut so far: each starts somewhere else, so no two replies repeat each other word for word */
    private int $cut = 0;

    /** `$n` words of the chapter, from a starting point, as a sentence. */
    private function text(array $words, int $n, int $from = 0): string
    {
        $from += 13 * $this->cut++;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = strtolower($words[($from + $i) % count($words)]);
        }

        return ucfirst(implode(' ', $out)) . '.';
    }

    /** @return array<int,array<string,mixed>> the JSON array that follows a label in the prompt */
    private function block(string $prompt, string $label): array
    {
        return preg_match('/' . preg_quote($label, '/') . "\n(\[.*?\])\n\nCHAPTER TEXT/s", $prompt, $m) ? (json_decode($m[1], true) ?? []) : [];
    }

    private function overview(string $prompt, array $w): array
    {
        preg_match("/CHAPTER\n(\{.*?\})\n\nCHAPTER TEXT/s", $prompt, $m);
        $topics = json_decode($m[1], true)['topics'] ?? [];

        return [
            'lede' => 'Revise the whole chapter before your examination.',
            'summary' => $this->text($w, 70),
            'topics' => array_map(fn ($t) => ['topic_id' => $t['topic_id'], 'gist' => $this->text($w, 12, $t['topic_id'])], $topics),
        ];
    }

    private function sheets(string $prompt, array $w): array
    {
        $out = [];
        foreach ($this->block($prompt, 'TOPICS TO WRITE') as $i => $t) {
            $rows = [];
            $terms = [];
            $mix = [];
            foreach ($t['concepts'] as $j => $c) {
                $rows[] = ['concept_id' => $c['concept_id'], 'essential' => $this->text($w, 14, $c['concept_id'] * 3), 'terms' => [$this->term($w, $c['concept_id'])]];
                $terms[] = ['concept_id' => $c['concept_id'], 'term' => $this->term($w, $c['concept_id'] + 10), 'meaning' => $this->text($w, 10, $c['concept_id'] * 5)];
                if ($c['misconceptions'] && $mix === []) {
                    $mix[] = ['wrong_idea' => $c['misconceptions'][0]['wrong_idea'], 'correct' => $this->text($w, 12, 4)];
                }
            }
            $out[] = [
                'topic_id' => $t['topic_id'],
                'big_idea' => $this->text($w, 14, $t['topic_id']),
                'rows' => $rows,
                'compare' => $i === 0 ? ['title' => 'How the ideas differ', 'columns' => ['Idea', 'What to remember'], 'rows' => [[$this->term($w, 1), $this->text($w, 6, 2)], [$this->term($w, 2), $this->text($w, 6, 9)]]] : null,
                'mixups' => $mix,
                'recall' => [$this->text($w, 12, $t['topic_id'] + 40)],
                'checklist' => ['I can ' . strtolower(rtrim($this->text($w, 9, $t['topic_id'] + 3), '.')) . '.'],
                'terms' => array_slice($terms, 0, 2),
                'diagram' => null, 'diagram_notes' => [],
            ];
        }

        return ['topics' => $out];
    }

    /** One word of the chapter (so it is a term the chapter names) that is long enough to be one. */
    private function term(array $w, int $seed): string
    {
        $long = array_values(array_filter($w, fn ($x) => strlen($x) >= 6));

        return strtolower($long[($seed * 7) % count($long)]);
    }

    private function lessons(string $prompt, array $w): array
    {
        $units = [];
        foreach ($this->block($prompt, 'CONCEPTS TO WRITE') as $c) {
            $id = $c['concept_id'];
            $practice = [];
            foreach ($c['practice'] as $p) {
                $not = [];
                foreach ($p['options'] as $o) {
                    if ((string) $o['label'] !== (string) $p['correct_option']) {
                        $not[$o['label']] = $this->text($w, 8, $id);
                    }
                }
                $practice[] = ['question_id' => $p['question_id'], 'hint' => $this->text($w, 8, $id + 2), 'not_options' => $not];
            }
            $units[] = [
                'concept_id' => $id,
                'prerequisites' => array_map(fn ($r) => ['concept_id' => $r['concept_id'], 'refresher' => $this->text($w, 10, $r['concept_id'])], $c['prerequisites']),
                'simple_explanation' => $this->text($w, 30, $id * 2),
                'steps' => array_map(fn ($s) => ['label' => ['First step', 'Second step', 'Third step'][$s - 1], 'text' => $this->text($w, 10, $id + $s)], [1, 2, 3]),
                'real_life' => $this->text($w, 14, $id + 5),
                'worked_example' => ['problem' => $this->text($w, 12, $id + 1), 'steps' => array_map(fn ($s) => ['text' => $this->text($w, 8, $id + $s), 'why' => $this->text($w, 7, $id + $s + 3)], [1, 2, 3]), 'answer' => $this->text($w, 9, $id + 6)],
                'practice' => $practice,
                'win' => 'You can now explain this idea in your own words.',
                'diagram' => null, 'diagram_notes' => [],
                'bloom' => 'understand', 'dok' => 2, 'minutes' => 8,
            ];
        }

        return ['units' => $units];
    }

    private function objectives(string $prompt, array $w): array
    {
        return ['objectives' => array_map(fn ($t) => ['topic_id' => $t['topic_id'], 'text' => 'I can ' . strtolower(rtrim($this->text($w, 9, $t['topic_id']), '.')) . '.'], $this->block($prompt, 'TOPICS'))];
    }

    private function clinic(string $prompt, array $w): array
    {
        return ['items' => array_map(fn ($i) => [
            'concept_id' => $i['concept_id'],
            'why_it_seems_true' => $this->text($w, 10, $i['concept_id']),
            'correction' => $this->text($w, 12, $i['concept_id'] + 2),
            'check_it' => $this->text($w, 10, $i['concept_id'] + 4),
        ], $this->block($prompt, 'IDEAS TO CORRECT'))];
    }

    private function teacher(string $prompt, array $w): array
    {
        return ['interventions' => array_map(fn ($u) => [
            'concept_id' => $u['concept_id'],
            'look_for' => 'Some learners may find it hard to put this idea in their own words.',
            'try_this' => 'Ask the learner to talk through the worked example one step at a time before you show the next step.',
            'if_still_stuck' => 'Go back to the idea it builds on and put it another way using the chapter example.',
        ], $this->block($prompt, 'LESSONS'))];
    }
}
