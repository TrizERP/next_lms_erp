<?php

namespace Tests\Unit;

use App\Services\lms\H5P\GenerationFormat;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class H5PGenerationFormatTest extends TestCase
{
    public function test_mapping_uses_scored_players_and_skips_presentation_only_forms(): void
    {
        $format = new GenerationFormat();
        $this->assertSame('h5p_blanks', $format->playerFor('numerical'));
        $this->assertSame('h5p_single_choice_set', $format->playerFor('assertion_reason'));
        $this->assertSame('h5p_memory_game', $format->playerFor('match_following'));
        $this->assertNull($format->playerFor('proof'));
        $this->assertNull($format->playerFor('unknown'));
    }

    public function test_matching_pairs_are_serialized_for_the_existing_player(): void
    {
        $row = (new GenerationFormat())->normalize(['question_title' => 'Match the capitals.', 'answer' => ['pairs' => [
            ['left' => 'France', 'right' => 'Paris'], ['left' => 'India', 'right' => 'Delhi'], ['left' => 'Japan', 'right' => 'Tokyo'],
        ]]], 'match_following', 'h5p_memory_game');
        $this->assertSame('France -> Paris; India -> Delhi; Japan -> Tokyo', $row['answer']['model_answer']);
        $this->assertSame('match_following', $row['answer']['item_form']);
    }

    public function test_rejects_options_with_multiple_correct_answers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GenerationFormat())->normalize(['question_title' => 'Choose one.', 'answer' => ['options' => array_map(
            fn ($text) => ['text' => $text, 'is_correct' => true], ['One', 'Two', 'Three', 'Four'])]], 'mcq', 'h5p_single_choice_set');
    }

    public function test_rejects_missing_blank_answers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GenerationFormat())->normalize(['question_title' => '___ and ___', 'answer' => ['model_answer' => 'one']], 'fill_blank', 'h5p_blanks');
    }

    public function test_numerical_answers_preserve_sign_and_decimal(): void
    {
        $row = (new GenerationFormat())->normalize(['question_title' => 'The temperature is ___ degrees.', 'answer' => ['model_answer' => '-2.5']], 'numerical', 'h5p_blanks');
        $this->assertSame('-2.5', $row['answer']['model_answer']);
    }

    public function test_false_is_a_valid_answer(): void
    {
        $row = (new GenerationFormat())->normalize(['question_title' => 'Every number is even.', 'answer' => ['model_answer' => 'False']], 'true_false', 'h5p_true_false');
        $this->assertSame('False', $row['answer']['model_answer']);
    }
}
