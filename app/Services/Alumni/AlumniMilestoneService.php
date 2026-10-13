<?php

namespace App\Services\Alumni;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Automatic milestone messages (spec section 38): birthday wishes, work anniversaries, graduation anniversaries.
 *
 * Each message goes through AlumniMessaging::notify as a NON-transactional message, so it respects consent per channel and
 * never reaches an alumnus who is not eligible. The dedupe key carries the year ("birthday:2026"), which together with the
 * unique key in alumni_message_log makes the daily command safe to run twice, or to re-run after a crash: nobody gets two
 * birthday wishes in one year.
 *
 * DATES are matched on month and day in the application's timezone (config app.timezone) - there is one clock per
 * installation, not one per institute. A 29 February birthday is celebrated on 28 February in a non-leap year.
 */
class AlumniMilestoneService
{
    public function __construct(private AlumniMessaging $messaging)
    {
    }

    /**
     * @return array<string, int> alumni attempted per type, and how each attempt ended
     */
    public function run(int $tenant, CarbonInterface $date): array
    {
        $out = ['birthday_wishes' => 0, 'work_anniversary' => 0, 'graduation_anniversary' => 0, 'sent' => 0, 'skipped' => 0, 'not_contacted' => 0];
        $year = (int) $date->format('Y');

        $birthdays = DB::table('alumni')->where('sub_institute_id', $tenant)->whereNotNull('dob');
        $this->monthDay($birthdays, 'alumni.dob', $date);
        foreach ($birthdays->get(['id']) as $a) {
            $out['birthday_wishes']++;
            $this->tally($out, $this->send($tenant, (int) $a->id, 'birthday_wishes', "birthday:{$year}", []));
        }

        $work = DB::table('alumni_career as c')->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'c.alumni_id')->on('a.sub_institute_id', '=', 'c.sub_institute_id');
        })->where('c.sub_institute_id', $tenant)->where('c.is_current', 1)->whereNotNull('c.start_date');
        $this->monthDay($work, 'c.start_date', $date);
        foreach ($work->get(['a.id', 'c.start_date', 'c.company_name']) as $r) {
            $years = $year - (int) substr((string) $r->start_date, 0, 4);
            if ($years < 1) {
                continue;
            }
            $out['work_anniversary']++;
            $this->tally($out, $this->send($tenant, (int) $r->id, 'work_anniversary', "work_anniversary:{$year}", ['years' => $years, 'company' => $r->company_name]));
        }

        $grad = DB::table('alumni')->where('sub_institute_id', $tenant)->whereNotNull('leaving_date');
        $this->monthDay($grad, 'alumni.leaving_date', $date);
        foreach ($grad->get(['id', 'leaving_date']) as $a) {
            $years = $year - (int) substr((string) $a->leaving_date, 0, 4);
            if ($years < 1) {
                continue;
            }
            $out['graduation_anniversary']++;
            $this->tally($out, $this->send($tenant, (int) $a->id, 'graduation_anniversary', "graduation_anniversary:{$year}", ['years' => $years]));
        }

        return $out;
    }

    /** @return array<string, string> */
    private function send(int $tenant, int $alumniId, string $type, string $dedupe, array $extra): array
    {
        [$subject, $body] = $this->messaging->templateFor($tenant, $type);

        return $this->messaging->notify($tenant, $alumniId, $type, $subject, $body, $dedupe, false, $extra);
    }

    /** @param array<string, int> $out @param array<string, string> $result */
    private function tally(array &$out, array $result): void
    {
        $sent = array_filter($result, fn ($s) => $s === 'sent');
        $attempted = array_filter($result, fn ($s) => in_array($s, ['sent', 'skipped', 'failed'], true));
        if ($sent !== []) {
            $out['sent']++;
        } elseif ($attempted !== []) {
            $out['skipped']++;
        } else {
            $out['not_contacted']++;   // no consent, no address, blocked, or already messaged this year
        }
    }

    /** Restrict a query to rows whose $column falls on this month and day, in either database. */
    private function monthDay($query, string $column, CarbonInterface $date): void
    {
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $days = [[$month, $day]];
        if ($month === 2 && $day === 28 && ! $date->isLeapYear()) {
            $days[] = [2, 29];   // a 29 February birthday is celebrated a day early in a common year
        }
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $query->where(function ($w) use ($days, $column, $sqlite) {
            foreach ($days as [$m, $d]) {
                $sqlite
                    ? $w->orWhereRaw("CAST(strftime('%m', {$column}) AS INTEGER) = ? AND CAST(strftime('%d', {$column}) AS INTEGER) = ?", [$m, $d])
                    : $w->orWhereRaw("MONTH({$column}) = ? AND DAY({$column}) = ?", [$m, $d]);
            }
        });
    }
}
