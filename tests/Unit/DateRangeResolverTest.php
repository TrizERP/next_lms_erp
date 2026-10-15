<?php

namespace Tests\Unit;

use App\Services\Mcp\DateRangeResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "Yesterday" and friends become exact dates, against a clock the test fixes.
 *
 * 14 October 2026 is a Wednesday.
 */
class DateRangeResolverTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-14 17:00:00', 'Asia/Kolkata');
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function phrases(): array
    {
        return [
            'today' => ['today', '2026-10-14', '2026-10-14'],
            "today's" => ["what was collected today's", '2026-10-14', '2026-10-14'],
            'yesterday in a sentence' => ['Show me the fees collected yesterday', '2026-10-13', '2026-10-13'],
            'day before yesterday' => ['the day before yesterday', '2026-10-12', '2026-10-12'],
            'last 7 days includes today' => ['last 7 days', '2026-10-08', '2026-10-14'],
            'past 30 days' => ['past 30 days', '2026-09-15', '2026-10-14'],
            'this week starts monday' => ['this week', '2026-10-12', '2026-10-14'],
            'last week is monday to sunday' => ['last week', '2026-10-05', '2026-10-11'],
            'this month' => ['this month', '2026-10-01', '2026-10-14'],
            'last month' => ['last month', '2026-09-01', '2026-09-30'],
            'iso' => ['2026-10-05', '2026-10-05', '2026-10-05'],
            'day first slashes' => ['03/04/2026', '2026-04-03', '2026-04-03'],
            'day month year' => ['13 October 2026', '2026-10-13', '2026-10-13'],
            'month day year' => ['October 13, 2026', '2026-10-13', '2026-10-13'],
            'ordinal' => ['the 5th of October 2026', '2026-10-05', '2026-10-05'],
            'no year is this year' => ['13 oct', '2026-10-13', '2026-10-13'],
            'a future day without a year is last year' => ['31 dec', '2025-12-31', '2025-12-31'],
            'from to' => ['from 1 October to 7 October 2026', '2026-10-01', '2026-10-07'],
            'between and' => ['between 2026-10-01 and 2026-10-07', '2026-10-01', '2026-10-07'],
            'reversed range is put right' => ['from 7 October to 1 October 2026', '2026-10-01', '2026-10-07'],
        ];
    }

    #[DataProvider('phrases')]
    public function test_a_phrase_resolves_to_the_exact_inclusive_range(string $text, string $from, string $to): void
    {
        $range = DateRangeResolver::fromText($text, $this->now);

        $this->assertNotNull($range, $text);
        $this->assertSame($from, $range['from'], $text);
        $this->assertSame($to, $range['to'], $text);
    }

    public function test_the_label_names_the_day_and_says_yesterday(): void
    {
        $this->assertSame('13 October 2026 (yesterday)', DateRangeResolver::fromText('yesterday', $this->now)['label']);
        $this->assertSame('14 October 2026 (today)', DateRangeResolver::fromText('today', $this->now)['label']);
        $this->assertSame('5 October 2026', DateRangeResolver::fromText('5 october 2026', $this->now)['label']);
        $this->assertSame('8 October to 14 October 2026', DateRangeResolver::fromText('last 7 days', $this->now)['label']);
    }

    public function test_a_sentence_with_no_date_resolves_to_nothing(): void
    {
        foreach (['which students owe the most', 'show me the timetable', 'nonsense', ''] as $text) {
            $this->assertNull(DateRangeResolver::fromText($text, $this->now), $text);
        }
    }

    public function test_an_impossible_date_is_unreadable_not_replaced(): void
    {
        foreach (['31 February 2026', '29 Feb 2026', '2026-13-45'] as $text) {
            $this->assertNull(DateRangeResolver::fromText($text, $this->now), $text);
            $this->assertTrue(DateRangeResolver::mentionsDate($text, $this->now), "{$text} is still a question about a date");
            $this->assertFalse(DateRangeResolver::fromArguments(['period' => $text], $this->now), $text);
        }
    }

    public function test_mentions_date_is_false_for_a_sentence_without_one(): void
    {
        $this->assertFalse(DateRangeResolver::mentionsDate('Which students have pending fees?', $this->now));
        $this->assertTrue(DateRangeResolver::mentionsDate('fees collected yesterday', $this->now));
    }

    public function test_arguments_take_explicit_dates_first_then_date_then_period(): void
    {
        $explicit = DateRangeResolver::fromArguments(
            ['from_date' => '2026-10-01', 'to_date' => '2026-10-07', 'period' => 'yesterday'],
            $this->now
        );

        $this->assertSame(['2026-10-01', '2026-10-07'], [$explicit['from'], $explicit['to']]);

        $date = DateRangeResolver::fromArguments(['date' => 'yesterday', 'period' => 'last 7 days'], $this->now);

        $this->assertSame('2026-10-13', $date['from']);
        $this->assertNull(DateRangeResolver::fromArguments([], $this->now), 'no date given is null, not today');
    }
}
