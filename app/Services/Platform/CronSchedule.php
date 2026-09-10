<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The five-field cron the Scheduler screen edits, parsed once and used three
 * ways: to refuse an unusable expression at save time, to say in words what an
 * expression means, and to work out when a task will next run.
 *
 * WHY NOT A PACKAGE
 * A cron package would earn its place if this had to FIRE jobs — timezones,
 * drift, missed ticks, all of it. It does not: the dispatcher fires, and this
 * class only has to agree with the operator about what they typed. Parsing five
 * fields of `*`, `n`, `a,b`, `a-b` and `a-b/n` is a small, fully testable amount
 * of code, and the same logic exists in TypeScript for the live preview as the
 * operator types — this side is the one that decides, and it validates every
 * write regardless of what the screen did.
 *
 * ERRORS NAME THEIR FIELD. "Minute: 61 is outside 0–59" tells an operator where
 * to look; "invalid cron expression" does not.
 *
 * THE DAY RULE IS STANDARD CRON, INCLUDING ITS ODDITY: when day-of-month and
 * day-of-week are BOTH restricted, a day matching EITHER runs. `0 9 1 * 1` means
 * the 1st of the month and every Monday, not "Mondays that fall on the 1st". It
 * surprises people, so describe() says it out loud.
 */
class CronSchedule
{
    /** field => [label, min, max], in the order the screen shows them. */
    public const FIELDS = [
        'minute' => ['Minute', 0, 59],
        'hour' => ['Hour', 0, 23],
        'day' => ['Day of month', 1, 31],
        'month' => ['Month', 1, 12],
        'day_of_week' => ['Day of week', 0, 6],
    ];

    private const MONTH_NAMES = [
        1 => 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    private const DAY_NAMES = [
        0 => 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
    ];

    /** @var array<string,list<int>> expanded values per field */
    private array $values = [];

    private bool $dayRestricted;

    private bool $dowRestricted;

    /** @param array<string,string> $schedule */
    public function __construct(private array $schedule)
    {
        foreach (self::FIELDS as $field => [$label, $min, $max]) {
            $this->values[$field] = self::expand((string) ($schedule[$field] ?? ''), $label, $min, $max);
        }

        $this->dayRestricted = trim((string) ($schedule['day'] ?? '*')) !== '*';
        $this->dowRestricted = trim((string) ($schedule['day_of_week'] ?? '*')) !== '*';
    }

    /**
     * Normalise an incoming schedule to the five fields, filling from a fallback.
     *
     * A partial body is a partial change: a request that names only `hour` keeps
     * the other four as they were. Anything else would mean an operator who
     * changed one field silently reset the rest.
     *
     * @param  array<string,mixed>  $input
     * @param  array<string,string>  $fallback
     * @return array<string,string>
     */
    public static function normalise(array $input, array $fallback): array
    {
        $result = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $value = $input[$field] ?? null;
            $result[$field] = is_string($value) || is_int($value)
                ? trim((string) $value)
                : (string) ($fallback[$field] ?? '*');

            if ($result[$field] === '') {
                $result[$field] = (string) ($fallback[$field] ?? '*');
            }
        }

        return $result;
    }

    /**
     * The problem with a schedule as a sentence, or null when it parses.
     *
     * @param  array<string,string>  $schedule
     */
    public static function problem(array $schedule): ?string
    {
        try {
            new self($schedule);

            return null;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Expand one field to the values it matches.
     *
     * @return list<int>
     */
    private static function expand(string $expression, string $label, int $min, int $max): array
    {
        $raw = trim($expression);
        if ($raw === '') {
            throw new InvalidArgumentException("{$label} cannot be empty. Use * for every value.");
        }

        $values = [];

        foreach (explode(',', $raw) as $part) {
            $piece = trim($part);
            if ($piece === '') {
                throw new InvalidArgumentException("{$label} has an empty entry in its list.");
            }

            $segments = explode('/', $piece);
            if (count($segments) > 2) {
                throw new InvalidArgumentException("{$label}: \"{$piece}\" has more than one step.");
            }

            $step = 1;
            if (isset($segments[1])) {
                if (! preg_match('/^\d+$/', $segments[1]) || (int) $segments[1] < 1) {
                    throw new InvalidArgumentException("{$label}: the step in \"{$piece}\" must be a whole number of 1 or more.");
                }
                $step = (int) $segments[1];
            }

            $range = trim($segments[0]);

            if ($range === '*') {
                $start = $min;
                $end = $max;
            } elseif (str_contains($range, '-')) {
                [$from, $to] = array_pad(explode('-', $range, 2), 2, '');
                if (! preg_match('/^\d+$/', trim($from)) || ! preg_match('/^\d+$/', trim($to))) {
                    throw new InvalidArgumentException("{$label}: \"{$range}\" is not a range of whole numbers.");
                }
                $start = (int) $from;
                $end = (int) $to;
                if ($start > $end) {
                    throw new InvalidArgumentException("{$label}: \"{$range}\" runs backwards.");
                }
            } else {
                if (! preg_match('/^\d+$/', $range)) {
                    throw new InvalidArgumentException("{$label}: \"{$range}\" is not a whole number.");
                }
                $start = (int) $range;
                $end = $start;
            }

            if ($start < $min || $end > $max) {
                throw new InvalidArgumentException("{$label}: \"{$range}\" is outside {$min}–{$max}.");
            }

            for ($value = $start; $value <= $end; $value += $step) {
                $values[$value] = true;
            }
        }

        $expanded = array_keys($values);
        sort($expanded);

        return $expanded;
    }

    private function matchesDay(CarbonImmutable $date): bool
    {
        if (! in_array((int) $date->month, $this->values['month'], true)) {
            return false;
        }

        $dayHit = in_array((int) $date->day, $this->values['day'], true);
        $dowHit = in_array((int) $date->dayOfWeek, $this->values['day_of_week'], true);

        if ($this->dayRestricted && $this->dowRestricted) {
            return $dayHit || $dowHit;
        }
        if ($this->dayRestricted) {
            return $dayHit;
        }
        if ($this->dowRestricted) {
            return $dowHit;
        }

        return true;
    }

    /**
     * The next moment this schedule fires, at or after `$from`.
     *
     * Walks forward a day at a time and only looks at hours and minutes on days
     * that match — a minute-by-minute walk would be 525,600 steps for a yearly
     * task. Gives up after four years, which only a schedule that can never fire
     * reaches (29 February in a month with no 29th, say); null is the honest
     * answer there, and the screen says so rather than showing a date that will
     * never happen.
     */
    public function nextRunAt(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        // Start at the next whole minute: a task due at 09:00 is not "due now"
        // at 09:00:30.
        $cursor = ($from ?? CarbonImmutable::now())->startOfMinute()->addMinute();
        $searchDay = $cursor->startOfDay();

        for ($offset = 0; $offset < 366 * 4; $offset++) {
            $day = $searchDay->addDays($offset);
            if (! $this->matchesDay($day)) {
                continue;
            }

            $isFirstDay = $offset === 0;

            foreach ($this->values['hour'] as $hour) {
                if ($isFirstDay && $hour < (int) $cursor->hour) {
                    continue;
                }
                foreach ($this->values['minute'] as $minute) {
                    if ($isFirstDay && $hour === (int) $cursor->hour && $minute < (int) $cursor->minute) {
                        continue;
                    }

                    return $day->setTime($hour, $minute, 0);
                }
            }
        }

        return null;
    }

    /** @param list<string> $items */
    private static function joinWords(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    private function timesOfDay(): string
    {
        $everyMinute = count($this->values['minute']) === 60;
        $everyHour = count($this->values['hour']) === 24;

        $hourList = self::joinWords(array_map(
            static fn (int $hour) => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
            $this->values['hour']
        ));

        if ($everyMinute && $everyHour) {
            return 'every minute';
        }
        if ($everyMinute) {
            return "every minute during {$hourList}";
        }

        if (preg_match('/^\*\/(\d+)$/', trim((string) ($this->schedule['minute'] ?? '')), $matches)) {
            return $everyHour
                ? "every {$matches[1]} minutes"
                : "every {$matches[1]} minutes during {$hourList}";
        }

        if ($everyHour) {
            $minuteList = self::joinWords(array_map(
                static fn (int $minute) => ':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT),
                $this->values['minute']
            ));

            return "every hour at {$minuteList}";
        }

        $times = [];
        foreach ($this->values['hour'] as $hour) {
            foreach ($this->values['minute'] as $minute) {
                $times[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
                if (count($times) >= 6) {
                    return 'at '.self::joinWords($times).' and other times';
                }
            }
        }

        return 'at '.self::joinWords($times);
    }

    /** The schedule as a sentence, for the row beneath the five inputs. */
    public function describe(): string
    {
        $when = $this->timesOfDay();

        $days = [];
        if ($this->dowRestricted) {
            $days[] = 'on '.self::joinWords(array_map(
                static fn (int $day) => self::DAY_NAMES[$day],
                $this->values['day_of_week']
            ));
        }
        if ($this->dayRestricted) {
            $days[] = 'on day '.self::joinWords(array_map('strval', $this->values['day'])).' of the month';
        }

        $months = count($this->values['month']) === 12
            ? ''
            : ' in '.self::joinWords(array_map(
                static fn (int $month) => self::MONTH_NAMES[$month],
                $this->values['month']
            ));

        if ($days === []) {
            return "Runs {$when}, every day{$months}.";
        }

        // Say the OR out loud — this is the rule operators get wrong.
        $dayPart = count($days) === 2 ? $days[0].' or '.$days[1] : $days[0];

        return "Runs {$when} {$dayPart}{$months}.";
    }

    /**
     * `0 9 * * 1-5`, for the mono column that shows the raw expression.
     *
     * The empty check is `=== ''` and not `?:` on purpose: PHP reads the string
     * "0" as falsy, so `trim($field) ?: '*'` turns a minute of 0 — the single most
     * common value in this table — into a star, and every midnight task would
     * render as running every minute.
     */
    public function toString(): string
    {
        return implode(' ', array_map(
            function (string $field): string {
                $value = trim((string) ($this->schedule[$field] ?? '*'));

                return $value === '' ? '*' : $value;
            },
            array_keys(self::FIELDS)
        ));
    }

    /** @param array<string,string> $a, @param array<string,string> $b */
    public static function equal(array $a, array $b): bool
    {
        foreach (array_keys(self::FIELDS) as $field) {
            if (trim((string) ($a[$field] ?? '*')) !== trim((string) ($b[$field] ?? '*'))) {
                return false;
            }
        }

        return true;
    }
}
