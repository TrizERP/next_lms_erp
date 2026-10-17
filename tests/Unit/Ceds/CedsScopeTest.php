<?php

namespace Tests\Unit\Ceds;

use App\Services\Ceds\CedsScope;
use PHPUnit\Framework\TestCase;

/** No application boot and no database: the scope rule over the real config file. */
class CedsScopeTest extends TestCase
{
    private CedsScope $scope;

    protected function setUp(): void
    {
        $config = require __DIR__ . '/../../../config/ceds_fit_matrix.php';
        $this->scope = new CedsScope($config['scope']);
    }

    public function test_the_configured_scope_is_exactly_one_chapter(): void
    {
        $config = require __DIR__ . '/../../../config/ceds_fit_matrix.php';
        $this->assertSame([['chapter_id' => 8592, 'standard_id' => 42, 'subject_id' => 3975]], $config['scope']['chapters']);
        $this->assertSame('Std 9 Science, Chapter 1', $config['scope']['label']);
    }

    public function test_std_9_science_chapter_1_is_allowed(): void
    {
        $this->assertTrue($this->scope->allows(['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975]));
        $this->assertTrue($this->scope->allows((object) ['id' => '8592', 'standard_id' => '42', 'subject_id' => '3975']), 'ids arrive as strings from the database');
    }

    public function test_any_other_chapter_standard_or_subject_is_not(): void
    {
        $cases = [
            'the next chapter of the same book' => ['id' => 8593, 'standard_id' => 42, 'subject_id' => 3975],
            'same chapter id, another subject' => ['id' => 8592, 'standard_id' => 42, 'subject_id' => 3976],
            'same chapter id, another standard' => ['id' => 8592, 'standard_id' => 43, 'subject_id' => 3975],
            'a Maths chapter' => ['id' => 9001, 'standard_id' => 42, 'subject_id' => 4001],
            'another standard entirely' => ['id' => 100, 'standard_id' => 1, 'subject_id' => 1],
            'missing fields' => ['id' => 8592],
            'nothing' => [],
        ];
        foreach ($cases as $name => $chapter) {
            $this->assertFalse($this->scope->allows($chapter), $name);
        }
    }

    public function test_an_empty_scope_allows_nothing(): void
    {
        $none = new CedsScope(['chapters' => []]);
        $this->assertFalse($none->allows(['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975]));
    }

    public function test_the_refusal_says_where_it_is_available(): void
    {
        $this->assertSame('The teaching-method planner is available only for Std 9 Science, Chapter 1.', $this->scope->message());
    }
}
