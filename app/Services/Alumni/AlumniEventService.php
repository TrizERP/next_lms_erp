<?php

namespace App\Services\Alumni;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alumni events: registration, capacity and waitlist, attendance, completion, cancellation and reports (spec sections 17-20).
 *
 * THE RULES LIVE HERE, NOT IN THE SCREEN. Whatever calls this - the staff API today, an alumni portal or the mobile app
 * later - gets the same answers:
 *   - only a PUBLISHED event takes registrations, only if registration is required, before its deadline and its date;
 *   - only an eligible alumnus who matches the event's audience may register;
 *   - one registration per alumnus per event (a cancelled one is reused); a second attempt is refused, not duplicated;
 *   - CAPACITY counts participants (the alumnus plus guests) on registered, confirmed and attended rows and is checked under
 *     a row lock on the event, so two simultaneous registrations cannot both take the last seat; a full event waitlists;
 *   - a cancellation frees seats and promotes waitlisted people first-come-first-served, skipping a party too large to fit;
 *   - a paid event cannot be CONFIRMED until the fee is marked paid or waived (online payment is not enabled yet);
 *   - attendance is idempotent: scanning the same ticket twice says "already checked in" and credits engagement once.
 *
 * Alumni are told about registration, waitlisting, promotion and cancellation as TRANSACTIONAL messages (email + in-app).
 */
class AlumniEventService
{
    public const TYPES = [
        'alumni_meet' => 'Alumni meet', 'batch_reunion' => 'Batch reunion', 'annual_function' => 'Annual alumni function', 'sports_event' => 'Sports event',
        'career_talk' => 'Career talk', 'guest_lecture' => 'Guest lecture', 'mentorship_session' => 'Mentorship session', 'networking_event' => 'Networking event',
        'fundraising_event' => 'Fundraising event', 'webinar' => 'Webinar', 'workshop' => 'Workshop', 'convocation' => 'Convocation', 'social_service' => 'Social service activity',
    ];

    public const METHODS = ['manual', 'qr', 'mobile', 'registration_no', 'alumni_id'];

    private const SEAT_STATUSES = ['registered', 'confirmed', 'attended'];

    public function __construct(
        private AlumniMessaging $messaging,
        private AlumniEngagementService $engagement,
    ) {
    }

    public function find(int $t, int $id): ?object
    {
        return DB::table('alumni_events')->where('sub_institute_id', $t)->where('id', $id)->first();
    }

    public function usedSeats(int $t, int $eventId): int
    {
        return (int) DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->whereIn('status', self::SEAT_STATUSES)->sum('participants');
    }

    // ------------------------------------------------------------- registration

    /**
     * @return array{0: object, 1: string} the registration and what happened: registered | confirmed | waitlisted
     *
     * @throws AlumniRuleException
     */
    public function register(int $t, int $eventId, int $alumniId, int $participants, ?string $guests, ?string $payment, ?int $userId): array
    {
        [$reg, $event] = DB::transaction(function () use ($t, $eventId, $alumniId, $participants, $guests, $payment, $userId) {
            $event = DB::table('alumni_events')->where('sub_institute_id', $t)->where('id', $eventId)->lockForUpdate()->first();
            if (! $event) {
                throw new AlumniRuleException('Event not found.', 404);
            }
            $this->assertOpen($event);

            if (! $this->mayAttend($t, $event, $alumniId)) {
                throw new AlumniRuleException($this->isEligible($t, $alumniId)
                    ? 'This event is open to a specific group of alumni and this alumnus is not in it.'
                    : 'Only registered, verified or active alumni can register for events.', 422, 'alumni_id');
            }
            if ($participants < 1 || $participants > 20) {
                throw new AlumniRuleException('Participants must be between 1 and 20.', 422, 'participants');
            }

            $existing = DB::table('alumni_event_registrations')->where('event_id', $eventId)->where('alumni_id', $alumniId)->first();
            if ($existing && $existing->status !== 'cancelled') {
                throw new AlumniRuleException('This alumnus is already registered for the event (' . $existing->status . ').', 409, 'alumni_id');
            }

            $fee = (float) $event->fee_amount;
            $payment = $fee > 0 ? ($payment && in_array($payment, ['paid', 'waived'], true) ? $payment : 'pending') : 'none';
            $full = $event->capacity !== null && ($this->usedSeats($t, $eventId) + $participants) > (int) $event->capacity;
            $status = $full ? 'waitlisted' : $this->openingStatus($event, $payment);
            $now = now();

            $data = ['participants' => $participants, 'guest_details' => $guests, 'payment_status' => $payment, 'status' => $status,
                'qr_token' => bin2hex(random_bytes(16)), 'registered_at' => $now,
                'confirmed_at' => $status === 'confirmed' ? $now : null, 'cancelled_at' => null, 'attended_at' => null, 'attendance_method' => null,
                'checked_in_by' => null, 'updated_at' => $now];

            if ($existing) {
                DB::table('alumni_event_registrations')->where('id', $existing->id)->update($data);
                $id = (int) $existing->id;
            } else {
                $count = (int) DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->count();
                $id = (int) DB::table('alumni_event_registrations')->insertGetId($data + [
                    'sub_institute_id' => $t, 'event_id' => $eventId, 'alumni_id' => $alumniId,
                    'registration_no' => sprintf('EV%05d-%04d', $eventId, $count + 1), 'created_by' => $userId, 'created_at' => $now,
                ]);
            }

            return [DB::table('alumni_event_registrations')->where('id', $id)->first(), $event];
        });

        $this->tell($t, $event, $reg, $reg->status === 'waitlisted' ? 'waitlisted' : $reg->status);

        return [$reg, $reg->status];
    }

    /** @throws AlumniRuleException */
    public function confirm(int $t, int $eventId, int $regId): object
    {
        $reg = $this->registration($t, $eventId, $regId);
        $event = $this->find($t, $eventId);
        if ($reg->status !== 'registered') {
            throw new AlumniRuleException('Only a registration that is waiting for confirmation can be confirmed (this one is ' . $reg->status . ').');
        }
        if ((float) $event->fee_amount > 0 && ! in_array($reg->payment_status, ['paid', 'waived'], true)) {
            throw new AlumniRuleException('The fee has not been marked as paid or waived, so this registration cannot be confirmed yet.', 409, 'payment_status');
        }
        DB::table('alumni_event_registrations')->where('id', $reg->id)->update(['status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now()]);
        $fresh = $this->registration($t, $eventId, $regId);
        $this->tell($t, $event, $fresh, 'confirmed');

        return $fresh;
    }

    /** @throws AlumniRuleException */
    public function setPayment(int $t, int $eventId, int $regId, string $payment): object
    {
        $reg = $this->registration($t, $eventId, $regId);
        if (! in_array($payment, ['pending', 'paid', 'waived'], true)) {
            throw new AlumniRuleException('Payment status must be pending, paid or waived.', 422, 'payment_status');
        }
        DB::table('alumni_event_registrations')->where('id', $reg->id)->update(['payment_status' => $payment, 'updated_at' => now()]);

        return $this->registration($t, $eventId, $regId);
    }

    /** @return array<int, object> the registrations promoted off the waiting list by this cancellation */
    public function cancelRegistration(int $t, int $eventId, int $regId): array
    {
        $reg = $this->registration($t, $eventId, $regId);
        if ($reg->status === 'cancelled') {
            throw new AlumniRuleException('This registration is already cancelled.');
        }
        if (in_array($reg->status, ['attended', 'no_show'], true)) {
            throw new AlumniRuleException('A registration that has attended or been marked a no-show cannot be cancelled.');
        }
        DB::table('alumni_event_registrations')->where('id', $reg->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);

        return $this->promoteWaitlist($t, $eventId);
    }

    /**
     * Give freed seats to the waiting list, oldest first, skipping a party that does not fit.
     *
     * @return array<int, object>
     */
    public function promoteWaitlist(int $t, int $eventId): array
    {
        $promoted = [];
        $event = null;
        DB::transaction(function () use ($t, $eventId, &$promoted, &$event) {
            $event = DB::table('alumni_events')->where('sub_institute_id', $t)->where('id', $eventId)->lockForUpdate()->first();
            if (! $event || $event->status !== 'published' || $event->capacity === null) {
                return;
            }
            $free = (int) $event->capacity - $this->usedSeats($t, $eventId);
            foreach (DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->where('status', 'waitlisted')->orderBy('id')->get() as $w) {
                if ($free < 1) {
                    break;
                }
                if ((int) $w->participants > $free) {
                    continue;
                }
                $status = $this->openingStatus($event, (string) $w->payment_status);
                DB::table('alumni_event_registrations')->where('id', $w->id)->update(['status' => $status, 'confirmed_at' => $status === 'confirmed' ? now() : null, 'updated_at' => now()]);
                $free -= (int) $w->participants;
                $promoted[] = DB::table('alumni_event_registrations')->where('id', $w->id)->first();
            }
        });
        foreach ($promoted as $p) {
            $this->tell($t, $event, $p, 'promoted');
        }

        return $promoted;
    }

    // --------------------------------------------------------------- attendance

    /**
     * @return array{0: object, 1: bool} the registration and whether it had ALREADY been checked in
     *
     * @throws AlumniRuleException
     */
    public function checkIn(int $t, int $eventId, string $method, ?string $value, ?int $registrationId, ?int $userId): array
    {
        $event = $this->find($t, $eventId);
        if (! $event) {
            throw new AlumniRuleException('Event not found.', 404);
        }
        if (! in_array($event->status, ['published', 'completed'], true)) {
            throw new AlumniRuleException('Attendance can only be recorded for a published or completed event.');
        }
        if (Carbon::parse($event->event_date)->startOfDay()->gt(now()->addDay()->endOfDay())) {
            throw new AlumniRuleException('Check-in opens the day before the event.');
        }

        $reg = $this->resolve($t, $eventId, $method, $value, $registrationId);
        if (! $reg) {
            throw new AlumniRuleException('No registration matches that for this event.', 404, 'value');
        }
        if ($reg->status === 'attended') {
            return [$reg, true];
        }
        $allowed = $method === 'manual' ? ['registered', 'confirmed', 'no_show'] : ['registered', 'confirmed'];
        if (! in_array($reg->status, $allowed, true)) {
            throw new AlumniRuleException(match ($reg->status) {
                'waitlisted' => 'This alumnus is on the waiting list and has not been given a seat.',
                'cancelled' => 'This registration was cancelled.',
                'no_show' => 'This registration was marked a no-show; a coordinator can correct it by hand.',
                default => 'This registration cannot be checked in (' . $reg->status . ').',
            });
        }
        if ((float) $event->fee_amount > 0 && ! in_array($reg->payment_status, ['paid', 'waived'], true)) {
            throw new AlumniRuleException('The fee for this registration has not been marked as paid or waived.', 409, 'payment_status');
        }

        $upd = DB::table('alumni_event_registrations')->where('id', $reg->id)->whereIn('status', ['registered', 'confirmed', 'no_show'])
            ->update(['status' => 'attended', 'attended_at' => now(), 'attendance_method' => $method, 'checked_in_by' => $userId, 'updated_at' => now()]);
        // exactly once, however many times it is scanned
        $this->engagement->record($t, (int) $reg->alumni_id, 'event_participation', 'Attended ' . $event->name, $userId, (string) $event->event_date, null, 'event_registration', (int) $reg->id);

        return [$this->registration($t, $eventId, (int) $reg->id), $upd !== 1];
    }

    // --------------------------------------------------------------- life cycle

    /** @throws AlumniRuleException */
    public function publish(int $t, int $eventId): object
    {
        $e = $this->find($t, $eventId) ?? throw new AlumniRuleException('Event not found.', 404);
        if ($e->status !== 'draft') {
            throw new AlumniRuleException('Only a draft event can be published (this one is ' . $e->status . ').');
        }
        if ($e->mode !== 'online' && trim((string) $e->venue) === '') {
            throw new AlumniRuleException('Add a venue before publishing an event that is held in person.', 422, 'venue');
        }
        if ($e->mode !== 'offline' && trim((string) $e->online_link) === '') {
            throw new AlumniRuleException('Add the online link before publishing an online or hybrid event.', 422, 'online_link');
        }
        if (Carbon::parse($e->event_date)->endOfDay()->lt(now())) {
            throw new AlumniRuleException('The event date has already passed.', 422, 'event_date');
        }
        DB::table('alumni_events')->where('id', $e->id)->update(['status' => 'published', 'updated_at' => now()]);

        return $this->find($t, $eventId);
    }

    /** Close the event: whoever was registered or confirmed but never checked in becomes a no-show. @throws AlumniRuleException */
    public function complete(int $t, int $eventId): array
    {
        $e = $this->find($t, $eventId) ?? throw new AlumniRuleException('Event not found.', 404);
        if ($e->status !== 'published') {
            throw new AlumniRuleException('Only a published event can be completed (this one is ' . $e->status . ').');
        }
        $n = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->whereIn('status', ['registered', 'confirmed'])
            ->update(['status' => 'no_show', 'updated_at' => now()]);
        DB::table('alumni_events')->where('id', $e->id)->update(['status' => 'completed', 'updated_at' => now()]);

        return ['event' => $this->find($t, $eventId), 'no_shows' => $n];
    }

    /** Cancel the event and everyone's seat; everyone who was due to come is told. @throws AlumniRuleException */
    public function cancelEvent(int $t, int $eventId, ?string $reason): object
    {
        $e = $this->find($t, $eventId) ?? throw new AlumniRuleException('Event not found.', 404);
        if (in_array($e->status, ['cancelled', 'completed'], true)) {
            throw new AlumniRuleException('This event is already ' . $e->status . '.');
        }
        $affected = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->whereIn('status', ['registered', 'confirmed', 'waitlisted'])->get();
        DB::table('alumni_events')->where('id', $e->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        DB::table('alumni_event_registrations')->whereIn('id', $affected->pluck('id'))->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
        foreach ($affected as $r) {
            $this->tell($t, $e, $r, 'event_cancelled', $reason);
        }

        return $this->find($t, $eventId);
    }

    // ------------------------------------------------------------ feedback, report

    /** @throws AlumniRuleException */
    public function feedback(int $t, int $eventId, int $alumniId, int $rating, ?string $comments, ?int $userId): object
    {
        $reg = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->where('alumni_id', $alumniId)->first();
        if (! $reg || $reg->status !== 'attended') {
            throw new AlumniRuleException('Only an alumnus who attended can give feedback on the event.', 409, 'alumni_id');
        }
        if (DB::table('alumni_event_feedback')->where('event_id', $eventId)->where('alumni_id', $alumniId)->exists()) {
            throw new AlumniRuleException('Feedback has already been given for this event.');
        }
        $id = DB::table('alumni_event_feedback')->insertGetId(['sub_institute_id' => $t, 'event_id' => $eventId, 'alumni_id' => $alumniId, 'rating' => $rating,
            'comments' => $comments, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('alumni_event_feedback')->where('id', $id)->first();
    }

    /** @return array<string, mixed> */
    public function report(int $t, int $eventId): array
    {
        $event = $this->find($t, $eventId) ?? throw new AlumniRuleException('Event not found.', 404);
        $by = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->groupBy('status')
            ->selectRaw('status, COUNT(*) as people, SUM(participants) as participants')->get()->keyBy('status');
        $n = fn (string $s) => (int) ($by[$s]->people ?? 0);
        $registeredTotal = $n('registered') + $n('confirmed') + $n('attended') + $n('no_show');   // everyone who held a seat at some point
        $fb = DB::table('alumni_event_feedback')->where('sub_institute_id', $t)->where('event_id', $eventId);

        return [
            'event' => $event,
            'registered' => $registeredTotal, 'confirmed' => $n('confirmed') + $n('attended'), 'waitlisted' => $n('waitlisted'), 'cancelled' => $n('cancelled'),
            'attended' => $n('attended'), 'no_show' => $n('no_show'),
            // "absent" is only meaningful once the event is over: held a seat, never came
            'absent' => $event->status === 'completed' ? $n('no_show') : null,
            'participants_attended' => (int) ($by['attended']->participants ?? 0), 'seats_used' => $this->usedSeats($t, $eventId), 'capacity' => $event->capacity,
            'feedback_count' => (int) (clone $fb)->count(), 'feedback_average' => (clone $fb)->count() ? round((float) (clone $fb)->avg('rating'), 2) : null,
        ];
    }

    // ----------------------------------------------------------------- helpers

    /** @throws AlumniRuleException */
    public function registration(int $t, int $eventId, int $regId): object
    {
        return DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId)->where('id', $regId)->first()
            ?? throw new AlumniRuleException('Registration not found.', 404);
    }

    private function assertOpen(object $event): void
    {
        if ($event->status !== 'published') {
            throw new AlumniRuleException($event->status === 'cancelled' ? 'This event was cancelled.' : ($event->status === 'completed' ? 'This event is over.' : 'This event has not been published yet.'));
        }
        if (! $event->registration_required) {
            throw new AlumniRuleException('Registration is not required for this event.');
        }
        if ($event->registration_deadline && Carbon::parse($event->registration_deadline)->lt(now())) {
            throw new AlumniRuleException('The registration deadline has passed.');
        }
        if (Carbon::parse($event->event_date)->endOfDay()->lt(now())) {
            throw new AlumniRuleException('This event has already taken place.');
        }
    }

    private function openingStatus(object $event, string $payment): string
    {
        $free = (float) $event->fee_amount <= 0 || in_array($payment, ['paid', 'waived'], true);

        return ($event->auto_confirm && $free) ? 'confirmed' : 'registered';
    }

    private function isEligible(int $t, int $alumniId): bool
    {
        return $this->messaging->audience($t, [])->where('id', $alumniId)->exists();
    }

    /** Eligible, and inside the event's audience when it has one. */
    public function mayAttend(int $t, object $event, int $alumniId): bool
    {
        $filters = $event->audience ? (array) json_decode((string) $event->audience, true) : [];

        return $this->messaging->audience($t, $filters)->where('id', $alumniId)->exists();
    }

    private function resolve(int $t, int $eventId, string $method, ?string $value, ?int $registrationId): ?object
    {
        $q = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', $eventId);
        $value = $value === null ? null : trim($value);

        return match ($method) {
            'manual' => $registrationId ? $q->where('id', $registrationId)->first() : null,
            'qr', 'mobile' => $value ? $q->where('qr_token', $value)->first() : null,
            'registration_no' => $value ? $q->where('registration_no', $value)->first() : null,
            'alumni_id' => $value ? $q->whereIn('alumni_id', DB::table('alumni')->where('sub_institute_id', $t)->where('alumni_code', $value)->select('id'))->first() : null,
            default => null,
        };
    }

    /** Transactional notice about a registration. Never blocks or fails the action it describes. */
    private function tell(int $t, object $event, object $reg, string $what, ?string $reason = null): void
    {
        $when = Carbon::parse($event->event_date)->format('j M Y') . ($event->start_time ? ', ' . substr((string) $event->start_time, 0, 5) : '');
        $extra = ['event_name' => $event->name, 'event_date' => $when, 'event_venue' => $event->mode === 'online' ? 'online' : (string) $event->venue,
            'registration_no' => $reg->registration_no, 'participants' => $reg->participants, 'reason' => $reason ?? ''];
        [$type, $subject, $body] = match ($what) {
            'confirmed' => ['registration_confirmation', 'You are confirmed: {{event_name}}', "Dear {{name}},\n\nYour registration for {{event_name}} on {{event_date}} at {{event_venue}} is confirmed for {{participants}} participant(s).\nRegistration number: {{registration_no}}."],
            'registered' => ['registration_confirmation', 'Registration received: {{event_name}}', "Dear {{name}},\n\nWe have received your registration for {{event_name}} on {{event_date}}. It will be confirmed shortly.\nRegistration number: {{registration_no}}."],
            'waitlisted' => ['registration_confirmation', 'You are on the waiting list: {{event_name}}', "Dear {{name}},\n\n{{event_name}} is full. You are on the waiting list and we will tell you if a seat opens.\nRegistration number: {{registration_no}}."],
            'promoted' => ['waitlist_promotion', 'A seat opened: {{event_name}}', "Dear {{name}},\n\nGood news - a seat opened and your registration for {{event_name}} on {{event_date}} is now active.\nRegistration number: {{registration_no}}."],
            default => ['announcement', 'Cancelled: {{event_name}}', "Dear {{name}},\n\n{{event_name}} planned for {{event_date}} has been cancelled. {{reason}}\nWe are sorry for the change."],
        };
        try {
            // one notice per registration CYCLE: registering again after a cancellation is a new cycle (new registered_at)
            $cycle = strtotime((string) $reg->registered_at) ?: 0;
            $this->messaging->notify($t, (int) $reg->alumni_id, $type, $subject, $body, "ereg:{$reg->id}:{$what}:{$cycle}", true, $extra);
        } catch (\Throwable $e) {
            report($e);   // a notification problem must not undo a registration
        }
    }
}
