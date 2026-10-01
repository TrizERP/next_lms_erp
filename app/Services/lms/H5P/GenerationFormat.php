<?php

namespace App\Services\lms\H5P;

use InvalidArgumentException;

/** Generation contracts belong to playable interactions, not catalogue IDs. */
class GenerationFormat
{
    public const CONTRACTS = [
        'h5p_single_choice_set' => 'options: exactly four objects {label:A|B|C|D,text:string,is_correct:boolean}; exactly one correct. For assertion/reason include both statements in question_title.',
        'h5p_true_false' => 'model_answer: exactly "True" or "False". question_title must be a single unambiguous statement.',
        'h5p_blanks' => 'question_title: a sentence with one ___ placeholder. model_answer: the missing word/phrase (at most six words and 60 characters), or a numeric literal for numerical questions. Put units in the question, not the answer.',
        'h5p_drag_text' => 'question_title: a sentence with one ___ placeholder and at least four other words. model_answer: the missing word/phrase, at most six words and 60 characters.',
        'h5p_mark_the_words' => 'question_title: a sentence with one ___ placeholder and at least four other words. model_answer: the word to mark, at most six words and 60 characters.',
        'h5p_memory_game' => 'pairs: 3 to 5 objects {left:string,right:string}, unique on each side. Use plain text without punctuation separators in either side. question_title must instruct the learner to match the items, without revealing the pairs.',
    ];

    public function playerFor(string $code): ?string
    {
        foreach (self::CONTRACTS as $player => $contract) {
            if (in_array($code, QuestionBankSource::codesFor($player), true)) {
                return $player;
            }
        }
        return null;
    }

    public function normalize(array $row, string $code, string $player): array
    {
        $title = $row['question_title'] ?? null;
        $answer = $row['answer'] ?? null;
        if (!is_string($title) || trim(strip_tags($title)) === '' || !is_array($answer)) {
            throw new InvalidArgumentException('Missing question or answer object.');
        }
        $answer['item_form'] = $code;
        $answer['h5p_player'] = $player;
        unset($answer['options']);
        if ($player === 'h5p_single_choice_set') {
            $options = $row['answer']['options'] ?? [];
            if (!is_array($options) || count($options) !== 4) {
                throw new InvalidArgumentException('Four options required.');
            }
            $texts = [];
            $correct = [];
            foreach (array_values($options) as $i => $option) {
                if (!is_array($option) || !is_string($option['text'] ?? null) || trim($option['text']) === ''
                    || mb_strlen($option['text']) > 250 || !is_bool($option['is_correct'] ?? null)) {
                    throw new InvalidArgumentException('Invalid option.');
                }
                $option['label'] = chr(65 + $i);
                $texts[] = mb_strtolower(trim($option['text']));
                $answer['options'][] = $option;
                if ($option['is_correct']) {
                    $correct[] = $option['label'];
                }
            }
            if (count($correct) !== 1 || count(array_unique($texts)) !== 4) {
                throw new InvalidArgumentException('Options must be distinct with one correct answer.');
            }
            $answer['correct_option'] = $correct[0];
            if ($code === 'assertion_reason' && (!str_contains(strtolower($title), 'assertion') || !str_contains(strtolower($title), 'reason'))) {
                throw new InvalidArgumentException('Assertion and reason must both be in the stem.');
            }
        } elseif ($player === 'h5p_memory_game') {
            $pairs = $answer['pairs'] ?? [];
            if (!is_array($pairs) || count($pairs) < 3 || count($pairs) > 5) {
                throw new InvalidArgumentException('Three to five matching pairs required.');
            }
            $left = $right = $encoded = [];
            foreach ($pairs as $pair) {
                foreach (['left', 'right'] as $side) {
                    if (!is_string($pair[$side] ?? null) || trim($pair[$side]) === '' || preg_match('/[,;\n:\-=<>*\/]/u', $pair[$side])) {
                        throw new InvalidArgumentException('Invalid matching pair.');
                    }
                }
                $left[] = mb_strtolower(trim($pair['left']));
                $right[] = mb_strtolower(trim($pair['right']));
                if (end($left) === end($right)) {
                    throw new InvalidArgumentException('Matching sides must differ.');
                }
                $encoded[] = trim($pair['left']).' -> '.trim($pair['right']);
            }
            if (count(array_unique($left)) !== count($pairs) || count(array_unique($right)) !== count($pairs)) {
                throw new InvalidArgumentException('Matching sides must be unique.');
            }
            $answer['model_answer'] = implode('; ', $encoded);
        } else {
            $model = $answer['model_answer'] ?? null;
            if (!is_string($model) || trim($model) === '') {
                throw new InvalidArgumentException('Model answer required.');
            }
            if ($player === 'h5p_true_false') {
                if (!in_array($model, ['True', 'False'], true)) {
                    throw new InvalidArgumentException('Boolean answer required.');
                }
            } else {
                if (preg_match_all('/_{3,}|\.{3,}|-{3,}/', $title) !== 1 || mb_strlen($model) > 60
                    || count(preg_split('/\s+/u', trim($model))) > 6 || preg_match('/[;|*\/<>]/', $model)) {
                    throw new InvalidArgumentException('One blank and one short literal solution required.');
                }
                if ($code === 'numerical' && !is_numeric($model)) {
                    throw new InvalidArgumentException('Numeric literal required.');
                }
                if ($player === 'h5p_mark_the_words' && str_word_count(str_replace('___', '', $title)) < 4) {
                    throw new InvalidArgumentException('Marking requires surrounding words.');
                }
            }
        }
        $row['answer'] = $answer;
        return $row;
    }
}
