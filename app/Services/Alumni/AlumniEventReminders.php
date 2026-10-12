<?php

namespace App\Services\Alumni;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Event reminders (spec sections 38 and 41), run daily by `alumni:run event-reminders`.
 *
 *   - Everyone who is registered or confirmed for a PUBLISHED event starting within `event_reminder_days` (a tenant
 *     setting, default 2) gets a reminder. That is a notice about something they signed up for, so it is TRANSACTIONAL
 *     (email + in-app, no marketing consent needed).
 *   - A BATCH REUNION also reminds the alumni it is aimed at who have not registered yet ("alumni reunion reminder"). That
 *     one is marketing-like, so it respects consent on every channel.
 *
 * The dedupe key carries the event id, so running the command again the same day - or every day until the event - sends
 * each person one reminder per event.
 */
class AlumniEventReminders
{
    public function __construct(private AlumniMessaging $messaging, private AlumniService $alumni)
    {
    }

    /** @return array<string, int> */
    public function run(int $tenant, CarbonInterface $date): array
    {
        $out = ['events' => 0, 'alumni' => 0, 'sent' => 0, 'skipped' => 0, 'not_contacted' => 0];
        $days = (int) $this->alumni->setting($tenant, 'event_reminder_days', 2);
        $days = ($days >= 0 && $days <= 14) ? $days : 2;

        $events = DB::table('alumni_events')->where('sub_institute_id', $tenant)->where('status', 'published')
            ->whereBetween('event_date', [$date->toDateString(), $date->copy()->addDays($days)->toDateString()])->get();

        foreach ($events as $e) {
            $out['events']++;
            $extra = ['event_name' => $e->name, 'event_date' => date('j M Y', strtotime((string) $e->event_date)) . ($e->start_time ? ', ' . substr((string) $e->start_time, 0, 5) : ''),
                'event_venue' => $e->mode === 'online' ? 'online' : (string) $e->venue];

            $registered = DB::table('alumni_event_registrations')->where('sub_institute_id', $tenant)->where('event_id', $e->id)->whereIn('status', ['registered', 'confirmed'])->pluck('alumni_id');
            [$subject, $body] = $this->messaging->templateFor($tenant, 'event_reminder');
            foreach ($registered as $alumniId) {
                $out['alumni']++;
                $this->tally($out, $this->messaging->notify($tenant, (int) $alumniId, 'event_reminder', $subject, $body, "event_reminder:{$e->id}", true, $extra));
            }

            if ($e->event_type === 'batch_reunion') {
                $filters = $e->audience ? (array) json_decode((string) $e->audience, true) : [];
                [$rSubject, $rBody] = $this->messaging->templateFor($tenant, 'reunion');
                $others = $this->messaging->audience($tenant, $filters)->whereNotIn('id', $registered->all() ?: [0])->pluck('id');
                foreach ($others as $alumniId) {
                    $out['alumni']++;
                    $this->tally($out, $this->messaging->notify($tenant, (int) $alumniId, 'reunion', $rSubject, $rBody, "reunion_reminder:{$e->id}", false, $extra));
                }
            }
        }

        return $out;
    }

    /** @param array<string, int> $out @param array<string, string> $result */
    private function tally(array &$out, array $result): void
    {
        if (array_filter($result, fn ($s) => $s === 'sent') !== []) {
            $out['sent']++;
        } elseif (array_filter($result, fn ($s) => in_array($s, ['skipped', 'failed'], true)) !== []) {
            $out['skipped']++;
        } else {
            $out['not_contacted']++;
        }
    }
}
