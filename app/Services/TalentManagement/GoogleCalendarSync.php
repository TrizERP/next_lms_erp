<?php

namespace App\Services\TalentManagement;

use App\Http\Controllers\api\TalentManagement\Administration\AdminIntegrationController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Puts Talent Management interviews on the Google Calendar a school connected under
 * Administration -> Integrations -> Google Calendar.
 *
 * Scheduling an interview never fails because of the calendar: every public method catches its own
 * errors, records them on the integration card (and in the log) and returns quietly.
 */
class GoogleCalendarSync
{
    private const API = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    /**
     * Create or update the calendar event for an interview schedule row. With `$invite` false the event goes
     * on the connected calendar only: nobody else is added as an attendee or emailed. `$onlyAttendees` replaces the
     * usual interviewer + candidate list with exactly those emails.
     */
    public static function syncInterview(int $scheduleId, bool $invite = true, ?array $onlyAttendees = null): void
    {
        try {
            $row = DB::table('talent_interview_schedules')->where('id', $scheduleId)->first();
            if (! $row || ! $row->interview_date) {
                return;
            }
            $integration = self::connected((int) $row->sub_institute_id);
            if (! $integration) {
                return;
            }
            $token = self::accessToken($integration);
            if (! $token) {
                return;
            }

            $event = self::buildEvent($row);
            if (! $event) {
                return;
            }
            if ($onlyAttendees !== null) {
                $event['attendees'] = array_map(fn ($e) => ['email' => $e], $onlyAttendees);
                $invite = true;
            } elseif (! $invite) {
                unset($event['attendees']);
            }
            $updates = $invite ? 'all' : 'none';

            $existing = $row->google_event_id ?? null;
            $response = $existing
                ? Http::withToken($token)->patch(self::API . '/' . $existing . '?sendUpdates=' . $updates, $event)
                : Http::withToken($token)->post(self::API . '?sendUpdates=' . $updates, $event);

            // The event was deleted on Google's side: make a fresh one.
            if ($existing && in_array($response->status(), [404, 410], true)) {
                $response = Http::withToken($token)->post(self::API . '?sendUpdates=' . $updates, $event);
            }

            if (! $response->successful()) {
                self::fail($integration, $response->json('error.message') ?: 'Google did not accept the event.', $response->status());

                return;
            }

            DB::table('talent_interview_schedules')->where('id', $row->id)->update(['google_event_id' => $response->json('id')]);
            self::note($integration, 'Interview added to calendar: ' . $event['summary']);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Prove the connection works without leaving anything behind: create an event and delete it.
     *
     * @return array{ok: bool, message: string}
     */
    public static function test(int $tenant): array
    {
        try {
            $integration = self::connected($tenant);
            if (! $integration) {
                return ['ok' => false, 'message' => 'Google Calendar is not connected.'];
            }
            $token = self::accessToken($integration);
            if (! $token) {
                return ['ok' => false, 'message' => 'The saved Google sign-in is no longer valid. Disconnect and connect again.'];
            }

            $start = Carbon::now(config('app.timezone'))->addDay()->setTime(10, 0);
            $created = Http::withToken($token)->post(self::API, [
                'summary' => 'ERP connection test (safe to ignore)',
                'start' => ['dateTime' => $start->toRfc3339String()],
                'end' => ['dateTime' => $start->copy()->addMinutes(15)->toRfc3339String()],
            ]);
            if (! $created->successful()) {
                $message = $created->json('error.message') ?: 'Google did not accept the test event.';
                self::fail($integration, $message, $created->status());

                return ['ok' => false, 'message' => $message];
            }
            Http::withToken($token)->delete(self::API . '/' . $created->json('id'));
            self::note($integration, 'Connection test passed');

            return ['ok' => true, 'message' => 'Google Calendar works: a test event was created and removed.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Google Calendar.'];
        }
    }

    private static function buildEvent(object $row): ?array
    {
        $tz = config('app.timezone');
        try {
            $date = Carbon::parse($row->interview_date)->format('Y-m-d');
            $start = Carbon::parse($date . ' ' . ($row->time ?: '10:00'), $tz);
        } catch (\Throwable) {
            return null;
        }
        $end = $start->copy()->addMinutes(max(15, (int) ($row->duration ?: 60)));

        $candidate = DB::table('talent_job_applications')->where('id', $row->applicant_id)->first();
        $name = $candidate ? trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? '')) : '';
        $job = DB::table('talent_job_postings')->where('id', $row->job_id)->value('title');

        $ids = json_decode(is_string($row->interviewer_id) ? $row->interviewer_id : json_encode($row->interviewer_id), true) ?: [];
        $emails = DB::table('tbluser')->whereIn('id', array_map('intval', (array) $ids))->pluck('email')->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values();
        if ($candidate && ! empty($candidate->email) && filter_var($candidate->email, FILTER_VALIDATE_EMAIL)) {
            $emails->push($candidate->email);
        }

        $event = [
            'summary' => 'Interview: ' . ($name ?: 'Candidate') . ($job ? " for {$job}" : '') . ($row->round_no ? " (Round {$row->round_no})" : ''),
            'description' => trim(($row->additional_notes ?? '') . "\n\nScheduled from the ERP Talent Management module."),
            'start' => ['dateTime' => $start->toRfc3339String()],
            'end' => ['dateTime' => $end->toRfc3339String()],
            'attendees' => $emails->map(fn ($e) => ['email' => $e])->all(),
            'reminders' => ['useDefault' => true],
        ];
        if (! empty($row->location)) {
            $event['location'] = $row->location;
        }
        if (in_array($row->status, ['Rejected'], true)) {
            $event['status'] = 'cancelled';
        }

        return $event;
    }

    private static function connected(int $tenant): ?object
    {
        return DB::table('talent_admin_integrations')->where('sub_institute_id', $tenant)->where('provider', 'google')
            ->where('status', 'Connected')->whereNotNull('credentials')->first() ?: null;
    }

    /** A valid access token, refreshing it with the stored refresh token when it is about to expire. */
    private static function accessToken(object $integration): ?string
    {
        $creds = json_decode(Crypt::decryptString($integration->credentials), true) ?: [];
        $expires = $integration->token_expires_at ? Carbon::parse($integration->token_expires_at) : null;
        if (! empty($creds['access_token']) && (! $expires || $expires->gt(now()->addMinute()))) {
            return $creds['access_token'];
        }

        $cfg = AdminIntegrationController::oauth('google', (int) $integration->sub_institute_id);
        if (empty($creds['refresh_token']) || ! $cfg || ! $cfg['client_id']) {
            self::fail($integration, 'Google sign-in expired. Disconnect and connect again.', 401, true);

            return null;
        }

        $res = Http::asForm()->acceptJson()->post($cfg['token_url'], [
            'grant_type' => 'refresh_token', 'refresh_token' => $creds['refresh_token'],
            'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'],
        ]);
        if (! $res->successful() || ! $res->json('access_token')) {
            self::fail($integration, 'Google sign-in expired. Disconnect and connect again.', 401, true);

            return null;
        }

        $creds['access_token'] = $res->json('access_token');
        DB::table('talent_admin_integrations')->where('id', $integration->id)->update([
            'credentials' => Crypt::encryptString(json_encode($creds)),
            'token_expires_at' => now()->addSeconds((int) $res->json('expires_in', 3600)),
        ]);

        return $creds['access_token'];
    }

    private static function note(object $integration, string $text): void
    {
        DB::table('talent_admin_integrations')->where('id', $integration->id)->update(['last_sync_at' => now(), 'last_sync_note' => mb_substr($text, 0, 190), 'updated_at' => now()]);
    }

    private static function fail(object $integration, string $message, int $status, bool $needsReconnect = false): void
    {
        Log::warning("Google Calendar ({$status}): {$message}");
        $update = ['last_sync_note' => mb_substr('Calendar error: ' . $message, 0, 190), 'updated_at' => now()];
        if ($needsReconnect || $status === 401) {
            $update['status'] = 'Error';
        }
        DB::table('talent_admin_integrations')->where('id', $integration->id)->update($update);
    }
}
