<?php

namespace App\Services\Mcp;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * "Yesterday", "last 7 days", "13 October 2026" → an exact, inclusive date range.
 *
 * WHY THIS IS CODE AND NOT THE MODEL'S JOB
 *
 * A model does not reliably know what day it is, so asking it to turn "yesterday" into a date makes the
 * answer depend on a guess that is wrong without any sign of it. A tool that is handed the words and
 * resolves them against the server's own clock cannot be wrong that way, and the range it used is
 * reported back, so the reader can see which dates the figures are for.
 *
 * WHAT IS RECOGNISED
 *
 *   today, yesterday, the day before yesterday
 *   last/past N days        N days ending TODAY, inclusive ("last 7 days" = today and the six before)
 *   this week / last week   Monday to Sunday; "this week" ends today
 *   this month / last month
 *   an explicit date        2026-10-13, 13/10/2026, 13-10-2026, 13 October 2026, October 13 2026, 13 Oct
 *   two explicit dates      "from X to Y", "between X and Y" — a range
 *
 * Day-first, because that is how dates are written here: 03/04/2026 is 3 April.
 *
 * WHAT IT NEVER DOES
 *
 * A date it cannot read is reported as unreadable. It is never replaced by today or by "all time",
 * because a quiet substitution is how a question about one day returns totals for another.
 */
final class DateRangeResolver
{
    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
    ];

    /**
     * Resolve a tool call's date arguments.
     *
     * Precedence: an explicit `from_date`/`to_date`, then `date`, then `period`.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{from:string,to:string,label:string,single:bool}|false|null  The range; false when a
     *                                                                       date was given but could not be
     *                                                                       read; null when none was given.
     */
    public static function fromArguments(array $arguments, ?Carbon $now = null): array|false|null
    {
        $now ??= Carbon::now();

        $from = self::clean($arguments['from_date'] ?? null);
        $to = self::clean($arguments['to_date'] ?? null);

        if ($from !== null || $to !== null) {
            $start = self::parseText($from ?? $to, $now);
            $end = self::parseText($to ?? $from, $now);

            return $start === null || $end === null
                ? false
                : self::range($start['from'], $end['to'], $now);
        }

        foreach (['date', 'period'] as $key) {
            $text = self::clean($arguments[$key] ?? null);

            if ($text === null) {
                continue;
            }

            $resolved = self::fromText($text, $now);

            return $resolved ?? false;
        }

        return null;
    }

    /**
     * The range a sentence asks for, or null when it names none.
     *
     * @return array{from:string,to:string,label:string,single:bool}|null
     */
    public static function fromText(string $text, ?Carbon $now = null): ?array
    {
        $now ??= Carbon::now();
        $parsed = self::parseText($text, $now);

        return $parsed === null ? null : self::range($parsed['from'], $parsed['to'], $now);
    }

    /**
     * Whether a sentence names a day or a range at all — including one that is not a real date.
     *
     * "31 February" resolves to nothing, but it is still a question about a date, and answering it
     * with all-time figures is the one thing that must not happen. A caller that acts on dates uses
     * this to decide the question is dated, then lets the tool report that the date could not be read.
     */
    public static function mentionsDate(string $text, ?Carbon $now = null): bool
    {
        if (self::fromText($text, $now) !== null) {
            return true;
        }

        $text = mb_strtolower($text);
        $month = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';

        return (bool) preg_match(
            '/\b'.$month.'\s+\d{4}\b|\b\d{4}-\d{1,2}-\d{1,2}\b|\b\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4}\b|\b\d{1,2}(?:st|nd|rd|th)?\s+(?:of\s+)?'
            .$month.'\b|\b'.$month.'\s+\d{1,2}(?:st|nd|rd|th)?\b/',
            $text
        );
    }

    // ---------------------------------------------------------------- parsing

    /** @return array{from:Carbon,to:Carbon}|null */
    private static function parseText(?string $text, Carbon $now): ?array
    {
        if ($text === null) {
            return null;
        }

        $text = mb_strtolower(trim($text));
        $text = preg_replace("/[\\s,]+/u", ' ', $text) ?? $text;
        $today = $now->copy()->startOfDay();

        if (preg_match('/\bday before yesterday\b/', $text)) {
            return self::day($today->copy()->subDays(2));
        }

        if (preg_match('/\byesterday\b/', $text)) {
            return self::day($today->copy()->subDay());
        }

        // Before "today", so "last 7 days" is not read as the word "today" nowhere in it — and so a
        // sentence naming both ("today and the last 7 days") takes the wider, explicit count.
        if (preg_match('/\b(?:last|past|previous)\s+(\d{1,3})\s+days?\b/', $text, $m)) {
            $days = min(max((int) $m[1], 1), 366);

            return ['from' => $today->copy()->subDays($days - 1), 'to' => $today];
        }

        // Two explicit dates make a range, whatever words join them.
        $dates = self::explicitDates($text, $today);

        if (count($dates) >= 2) {
            [$a, $b] = [$dates[0], $dates[1]];

            return $a->lte($b) ? ['from' => $a, 'to' => $b] : ['from' => $b, 'to' => $a];
        }

        if (count($dates) === 1) {
            return self::day($dates[0]);
        }

        // A whole month named in words: "September 2026", "in september", "for the month of october".
        // "may" alone is a verb, so a bare "may" is not read as a month; with a year it is.
        $months = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

        // Not when a day number comes first ("31 february 2026"): that is a day, and if it is not a real
        // one it must stay unreadable instead of quietly widening to the whole month.
        if (preg_match('/(?<!\d\s)(?<!\d(?:st|nd|rd|th)\s)(?<!\d\sof\s)\b('.$months.')\s+(\d{4})\b/', $text, $m)
            || preg_match('/\b(?:in|during|for|of|month of)\s+(?:the\s+month\s+of\s+)?(?!may\b)('.$months.')\b(?!\s+\d)/', $text, $m)) {
            $number = self::MONTHS[$m[1]] ?? 0;
            $year = isset($m[2]) ? (int) $m[2] : (int) $today->format('Y');

            // A bare month later in the year than today means last year's.
            if (! isset($m[2]) && $number > (int) $today->format('n')) {
                $year--;
            }

            if ($number >= 1) {
                $start = Carbon::create($year, $number, 1, 0, 0, 0, $today->getTimezone());

                return ['from' => $start, 'to' => $start->copy()->endOfMonth()->startOfDay()];
            }
        }

        if (preg_match('/\blast week\b/', $text)) {
            $start = $today->copy()->startOfWeek()->subWeek();

            return ['from' => $start, 'to' => $start->copy()->endOfWeek()->startOfDay()];
        }

        if (preg_match('/\bthis week\b/', $text)) {
            return ['from' => $today->copy()->startOfWeek(), 'to' => $today];
        }

        if (preg_match('/\blast month\b/', $text)) {
            $start = $today->copy()->startOfMonth()->subMonthNoOverflow();

            return ['from' => $start, 'to' => $start->copy()->endOfMonth()->startOfDay()];
        }

        if (preg_match('/\bthis month\b/', $text)) {
            return ['from' => $today->copy()->startOfMonth(), 'to' => $today];
        }

        if (preg_match("/\\btoday(?:'s)?\\b/", $text)) {
            return self::day($today);
        }

        return null;
    }

    /**
     * Every explicit date in the text, in the order written.
     *
     * @return array<int, Carbon>
     */
    private static function explicitDates(string $text, Carbon $today): array
    {
        $found = [];
        $month = '(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';

        $patterns = [
            // 2026-10-13
            '/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/' => static fn (array $m) => [(int) $m[1], (int) $m[2], (int) $m[3]],
            // 13/10/2026, 13-10-2026, 13.10.2026 — day first
            '/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/' => static fn (array $m) => [(int) $m[3], (int) $m[2], (int) $m[1]],
            // 13 october 2026, 13th oct, 13 oct
            '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?'.$month.'(?:\s+(\d{4}))?\b/' => static fn (array $m) => [
                isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null, self::MONTHS[$m[2]] ?? 0, (int) $m[1],
            ],
            // october 13 2026, oct 13th
            '/\b'.$month.'\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s+(\d{4}))?\b/' => static fn (array $m) => [
                isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null, self::MONTHS[$m[1]] ?? 0, (int) $m[2],
            ],
        ];

        foreach ($patterns as $pattern => $extract) {
            if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches as $match) {
                $groups = array_map(static fn ($g) => is_array($g) ? $g[0] : $g, $match);
                [$year, $mon, $day] = $extract($groups);

                $date = self::make($year, $mon, $day, $today);

                if ($date !== null) {
                    $found[] = ['at' => $match[0][1], 'date' => $date];
                }
            }
        }

        usort($found, static fn (array $a, array $b) => $a['at'] <=> $b['at']);

        // The same text can satisfy two patterns ("13 october 2026" and "october 13"); keep one per place.
        $unique = [];

        foreach ($found as $entry) {
            $key = $entry['date']->toDateString();

            if (! isset($unique[$key])) {
                $unique[$key] = $entry['date'];
            }
        }

        return array_values($unique);
    }

    /** A real calendar date, or null. A missing year is this year, or last year if that would be in the future. */
    private static function make(?int $year, int $month, int $day, Carbon $today): ?Carbon
    {
        $assumedYear = $year === null;
        $year ??= (int) $today->format('Y');

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || ! checkdate($month, $day, $year)) {
            return null;
        }

        try {
            $date = Carbon::create($year, $month, $day, 0, 0, 0, $today->getTimezone());
        } catch (Throwable) {
            return null;
        }

        if ($assumedYear && $date->gt($today)) {
            $date = $date->subYear();
        }

        return $date;
    }

    // ---------------------------------------------------------------- results

    /** @return array{from:Carbon,to:Carbon} */
    private static function day(Carbon $date): array
    {
        return ['from' => $date->copy()->startOfDay(), 'to' => $date->copy()->startOfDay()];
    }

    /** @return array{from:string,to:string,label:string,single:bool} */
    private static function range(Carbon $from, Carbon $to, Carbon $now): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $single = $from->equalTo($to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'single' => $single,
            'label' => $single
                ? self::dayLabel($from, $today)
                : sprintf(
                    '%s to %s',
                    $from->format('Y') === $to->format('Y') ? $from->format('j F') : $from->format('j F Y'),
                    $to->format('j F Y')
                ),
        ];
    }

    /** "13 October 2026 (yesterday)". */
    private static function dayLabel(Carbon $day, Carbon $today): string
    {
        $label = $day->format('j F Y');
        $diff = (int) $day->diffInDays($today, false);

        return match (true) {
            $diff === 0 => $label.' (today)',
            $diff === 1 => $label.' (yesterday)',
            default => $label,
        };
    }

    private static function clean(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
