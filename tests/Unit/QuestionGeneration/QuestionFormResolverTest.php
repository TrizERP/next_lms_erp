<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\QuestionEnvelope;
use App\Services\QuestionGeneration\QuestionFormResolver;
use Tests\TestCase;

/**
 * The shared "what form is this question?" ladder.
 *
 * NO DATABASE, no network. phpunit.xml points at the live shared vivek_erp.
 * Parity against real rows lives in QuestionFormResolverParityTest, which is
 * read-only and opt-in.
 */
class QuestionFormResolverTest extends TestCase
{
    public function test_php_ladder_prefers_sidecar_then_format_then_item_form_then_generated_then_legacy(): void
    {
        $env = ['item_form' => 'item'];

        $this->assertSame('side', QuestionFormResolver::resolve('side', 'fmt', $env, 'gen', 'mcq'));
        $this->assertSame('fmt', QuestionFormResolver::resolve(null, 'fmt', $env, 'gen', 'mcq'));
        $this->assertSame('item', QuestionFormResolver::resolve(null, null, $env, 'gen', 'mcq'));
        $this->assertSame('gen', QuestionFormResolver::resolve(null, null, [], 'gen', 'mcq'));
        $this->assertSame('mcq', QuestionFormResolver::resolve(null, null, [], null, 'mcq'));
        $this->assertSame('narrative', QuestionFormResolver::resolve(null, null, null, null, 'narrative'));
    }

    public function test_blank_and_literal_null_values_do_not_win_a_tier(): void
    {
        $this->assertSame('gen', QuestionFormResolver::resolve('', '  ', ['item_form' => 'null'], 'gen', 'mcq'));
        $this->assertSame('mcq', QuestionFormResolver::resolve(null, '', ['item_form' => ''], '', 'mcq'));
        $this->assertSame('mcq', QuestionFormResolver::resolve(null, null, ['item_form' => null], null, 'mcq'));
        $this->assertSame('mcq', QuestionFormResolver::resolve(null, null, ['item_form' => ['x']], null, 'mcq'));
    }

    public function test_resolve_returns_null_when_the_caller_has_no_legacy_tier(): void
    {
        $this->assertNull(QuestionFormResolver::resolve(null, null, [], null, null));
    }

    public function test_item_form_reads_a_raw_json_column_and_ignores_prose(): void
    {
        $this->assertSame('fill_blank', QuestionFormResolver::itemForm('{"item_form":"fill_blank"}'));
        $this->assertNull(QuestionFormResolver::itemForm('The product is negative.'));
        $this->assertNull(QuestionFormResolver::itemForm('{not json'));
        $this->assertNull(QuestionFormResolver::itemForm(''));
        $this->assertNull(QuestionFormResolver::itemForm(null));
    }

    public function test_sql_ladder_has_the_five_tiers_in_order(): void
    {
        $sql = QuestionFormResolver::sqlExpression('q', 'x.question_type_code');

        $positions = [
            strpos($sql, 'x.question_type_code'),
            strpos($sql, 'q.question_format_code'),
            strpos($sql, '$.item_form'),
            strpos($sql, 'q.g_qtype_code'),
            strpos($sql, 'q.question_type_id = 1'),
        ];

        foreach ($positions as $position) {
            $this->assertNotFalse($position, 'A tier is missing from the ladder: ' . $sql);
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Tiers are out of order: ' . $sql);
        $this->assertStringStartsWith('COALESCE(', $sql);
    }

    public function test_sql_ladder_honours_schema_guards_and_the_legacy_switch(): void
    {
        $old = QuestionFormResolver::sqlExpression('q', 'NULL', false, false);
        $this->assertStringNotContainsString('question_format_code', $old);
        $this->assertStringNotContainsString('g_qtype_code', $old);
        $this->assertStringContainsString('$.item_form', $old);

        $noLegacy = QuestionFormResolver::sqlExpression('lms_question_master', 'x.c', true, true, false);
        $this->assertStringNotContainsString('CASE WHEN', $noLegacy);
        $this->assertStringEndsWith('g_qtype_code)', $noLegacy);
    }

    public function test_sql_ladder_uses_the_callers_table_alias_throughout(): void
    {
        $sql = QuestionFormResolver::sqlExpression('lqm', 'NULL');

        $this->assertStringContainsString('lqm.question_format_code', $sql);
        $this->assertStringContainsString('lqm.answer', $sql);
        $this->assertStringContainsString('lqm.g_qtype_code', $sql);
        $this->assertStringContainsString('lqm.question_type_id', $sql);
        $this->assertStringNotContainsString('q.', str_replace('lqm.', '', $sql));
    }

    public function test_envelope_pairs_are_normalised_and_absent_means_null(): void
    {
        $this->assertNull(QuestionEnvelope::pairs(null));
        $this->assertNull(QuestionEnvelope::pairs([]));
        $this->assertNull(QuestionEnvelope::pairs(['pairs' => 'a-b']));
        $this->assertNull(QuestionEnvelope::pairs(['pairs' => [['left' => '', 'right' => 'x']]]));

        $this->assertSame(
            [['left' => 'Heart', 'right' => 'Pumps blood'], ['left' => 'Lung', 'right' => 'Gas exchange']],
            QuestionEnvelope::pairs(['pairs' => [
                ['left' => ' Heart ', 'right' => 'Pumps blood'],
                'junk',
                ['left' => 'Lung', 'right' => 'Gas exchange'],
                ['left' => 'Empty', 'right' => '  '],
            ]])
        );
    }
}
