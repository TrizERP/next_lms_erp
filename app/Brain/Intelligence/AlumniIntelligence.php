<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Alumni Management for one institute — all time.
 *
 * ── NOT AN ACADEMIC-YEAR FACT ───────────────────────────────────────────────
 *
 * An alumnus is a person who has left, so the register carries `passing_year`
 * (the batch they left with) but no `syear` to filter on. Every figure is an
 * institute total and the screen says so, rather than letting the header's year
 * look like a filter that was applied.
 *
 * ── WHAT "REACHABLE" MEANS ──────────────────────────────────────────────────
 *
 * An alumnus can be written to only if there is an address (email or mobile) AND
 * a recorded consent for at least one channel. A record with a mobile number and
 * no consent is not reachable, and counting it as contactable would send
 * unconsented messages the moment someone trusted the figure.
 *
 * ── VERIFIED ────────────────────────────────────────────────────────────────
 *
 * `status` runs registered → pending_verification → verified → active. Verified
 * means the last two; "awaiting verification" is the first two, because a
 * self-registered record that nobody has looked at is not yet verified either.
 */
final class AlumniIntelligence
{
    private const ALUMNI = 'alumni';

    private const VERIFIED = ['verified', 'active'];

    private const AWAITING = ['registered', 'pending_verification'];

    private const EMPLOYED = ['employed', 'government_employee', 'entrepreneur'];

    /** Share awaiting verification above which it is reported. */
    private const AWAITING_SHARE = 25.0;

    /** Share not reachable above which it is reported. */
    private const UNREACHABLE_SHARE = 20.0;

    /** Profile completion below this is "incomplete". */
    private const COMPLETION_FLOOR = 50;

    /** Share incomplete above which it is reported. */
    private const INCOMPLETE_SHARE = 30.0;

    /** Share with no activity in the last 12 months above which it is reported. */
    private const DORMANT_SHARE = 50.0;

    /** A request or donation waiting longer than this many days is reported. */
    private const WAITING_DAYS = 14;

    private array $memo = [];

    public function __construct(private readonly string $tenantId)
    {
    }

    private function alumni()
    {
        return DB::table(self::ALUMNI)->where('sub_institute_id', $this->tenantId);
    }

    private function reachableClause($q)
    {
        return $q
            ->where(fn ($w) => $w->where(fn ($e) => $e->whereNotNull('email')->where('email', '!=', ''))
                ->orWhere(fn ($m) => $m->whereNotNull('mobile')->where('mobile', '!=', '')))
            ->where(fn ($c) => $c->where('consent_email', 1)->orWhere('consent_sms', 1)
                ->orWhere('consent_whatsapp', 1)->orWhere('consent_push', 1));
    }

    private function memo(string $k, callable $fn)
    {
        return $this->memo[$k] ??= $fn();
    }

    public function coverage(): array
    {
        return $this->memo('coverage', function () {
            if (! SchemaCache::hasTable(self::ALUMNI)) {
                return $this->unavailable('This installation has no '.self::ALUMNI.' table.');
            }

            $n = (int) $this->alumni()->count();
            if ($n === 0) {
                return $this->unavailable('No alumni have been added or imported for this institute yet.');
            }

            return [
                'available' => true,
                'reason' => null,
                'syear' => null,
                'sources' => [
                    'alumni' => true,
                    'career' => SchemaCache::hasTable('alumni_career'),
                    'mentorship' => SchemaCache::hasTable('alumni_mentorship_requests'),
                    'events' => SchemaCache::hasTable('alumni_events'),
                    'donations' => SchemaCache::hasTable('alumni_donations'),
                ],
                'counts' => ['alumni' => $n],
            ];
        });
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'syear' => null, 'sources' => [], 'counts' => []];
    }

    public function position(): ?array
    {
        return $this->memo('position', function () {
            if (! $this->coverage()['available']) {
                return null;
            }

            $total = (int) $this->coverage()['counts']['alumni'];
            $verified = (int) $this->alumni()->whereIn('status', self::VERIFIED)->count();
            $awaiting = (int) $this->alumni()->whereIn('status', self::AWAITING)->count();
            $reachable = (int) $this->reachableClause($this->alumni())->count();
            $known = (int) $this->alumni()->whereNotNull('employment_status')->where('employment_status', '!=', '')->count();
            $employed = (int) $this->alumni()->whereIn('employment_status', self::EMPLOYED)->count();
            $active = (int) $this->alumni()->where('last_activity_at', '>=', now()->subMonths(12))->count();
            $avgCompletion = $this->alumni()->whereNotNull('profile_completion')->avg('profile_completion');

            $mentors = SchemaCache::hasTable('alumni_mentor_profiles')
                ? (int) DB::table('alumni_mentor_profiles')->where('sub_institute_id', $this->tenantId)->where('status', 'active')->count()
                : 0;
            $openRequests = SchemaCache::hasTable('alumni_mentorship_requests')
                ? (int) DB::table('alumni_mentorship_requests')->where('sub_institute_id', $this->tenantId)->where('status', 'open')->count()
                : 0;
            $upcoming = SchemaCache::hasTable('alumni_events')
                ? (int) DB::table('alumni_events')->where('sub_institute_id', $this->tenantId)->where('status', 'published')
                    ->whereDate('event_date', '>=', now()->toDateString())->count()
                : 0;
            $raised = SchemaCache::hasTable('alumni_donations')
                ? (float) DB::table('alumni_donations')->where('sub_institute_id', $this->tenantId)
                    ->whereIn('status', ['received', 'verified', 'completed', 'confirmed'])->sum('amount')
                : 0.0;

            return [
                'alumni' => $total,
                'verified' => $verified,
                'verifiedRate' => round($verified / $total * 100, 1),
                'awaiting' => $awaiting,
                'awaitingRate' => round($awaiting / $total * 100, 1),
                'reachable' => $reachable,
                'reachableRate' => round($reachable / $total * 100, 1),
                'employmentKnown' => $known,
                // Over the alumni whose employment status is recorded at all.
                'employmentRate' => $known > 0 ? round($employed / $known * 100, 1) : null,
                'activeLast12' => $active,
                'activeRate' => round($active / $total * 100, 1),
                'profileCompletion' => $avgCompletion !== null ? round((float) $avgCompletion, 1) : null,
                'mentors' => $mentors,
                'openMentorRequests' => $openRequests,
                'upcomingEvents' => $upcoming,
                'donationsRaised' => $raised,
            ];
        });
    }

    /** Alumni per batch (the year they left). */
    public function byBatch(): array
    {
        return $this->memo('byBatch', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            return $this->alumni()->whereNotNull('passing_year')
                ->selectRaw("passing_year AS y, COUNT(*) AS n,
                    SUM(status IN ('verified','active')) AS verified,
                    SUM(employment_status IN ('employed','government_employee','entrepreneur')) AS employed")
                ->groupBy('passing_year')->orderByDesc('passing_year')->get()
                ->map(fn ($r) => [
                    'key' => (string) $r->y, 'label' => 'Batch '.$r->y,
                    'alumni' => (int) $r->n, 'verified' => (int) $r->verified, 'employed' => (int) $r->employed,
                ])->all();
        });
    }

    /** Alumni by what they are doing now. */
    public function byEmployment(): array
    {
        return $this->memo('byEmployment', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            return $this->alumni()->whereNotNull('employment_status')->where('employment_status', '!=', '')
                ->selectRaw('employment_status AS s, COUNT(*) AS n')
                ->groupBy('employment_status')->orderByDesc('n')->get()
                ->map(fn ($r) => [
                    'key' => (string) $r->s,
                    'label' => ucfirst(str_replace('_', ' ', (string) $r->s)),
                    'alumni' => (int) $r->n,
                ])->all();
        });
    }

    public function dataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $p = $this->position();
        $total = $p['alumni'];

        $noContact = (int) $this->alumni()
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->where(fn ($q) => $q->whereNull('mobile')->orWhere('mobile', ''))->count();
        $noBatch = (int) $this->alumni()->where(fn ($q) => $q->whereNull('passing_year')->orWhere('passing_year', 0))->count();
        $noEmployment = $total - $p['employmentKnown'];
        $noConsent = (int) $this->alumni()
            ->where(fn ($q) => $q->whereNull('consent_email')->orWhere('consent_email', 0))
            ->where(fn ($q) => $q->whereNull('consent_sms')->orWhere('consent_sms', 0))
            ->where(fn ($q) => $q->whereNull('consent_whatsapp')->orWhere('consent_whatsapp', 0))
            ->where(fn ($q) => $q->whereNull('consent_push')->orWhere('consent_push', 0))->count();

        $check = fn (string $key, string $label, int $value, string $bad, string $ok) => [
            'key' => $key, 'label' => $label, 'value' => $value, 'format' => 'count',
            'sharePercent' => $total > 0 ? round($value / $total * 100, 2) : null,
            'state' => $value > 0 ? 'attention' : 'ok',
            'note' => $value > 0 ? $bad : $ok,
        ];

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                $check('no_contact', 'Alumni with no email and no mobile', $noContact,
                    'These records cannot be contacted by any channel.', 'Every alumnus has at least one address.'),
                $check('no_consent', 'Alumni with no consent recorded', $noConsent,
                    'No channel has consent for these records, so they are excluded from every campaign.',
                    'Every alumnus has consent on at least one channel.'),
                $check('no_batch', 'Alumni with no passing year', $noBatch,
                    'These records do not appear in any batch breakdown.', 'Every alumnus names a batch.'),
                $check('no_employment', 'Alumni with no employment status', $noEmployment,
                    'The employment rate is computed only over alumni whose status is recorded.',
                    'Every alumnus has an employment status.'),
            ],
        ];
    }

    /** @return array{findings: array, ruleStatus: array} */
    public function findings(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $p = $this->position();
        $total = $p['alumni'];
        $findings = [];
        $status = [];

        // 1. Records nobody has verified.
        $raised = false;
        if ($p['awaitingRate'] >= self::AWAITING_SHARE) {
            $raised = true;
            $findings[] = $this->finding(
                'alumni-awaiting-verification', $p['awaitingRate'] >= 50 ? 'high' : 'medium',
                $p['awaiting'].' alumni records are not yet verified',
                $p['awaiting'].' of '.$total.' records ('.$p['awaitingRate'].'%) are still registered or pending verification.',
                'Unverified records are self-reported. Until someone confirms them they should not drive the directory, mentor matching or campaigns.',
                [
                    ['label' => 'Awaiting verification', 'value' => (string) $p['awaiting']],
                    ['label' => 'Verified', 'value' => $p['verified'].' ('.$p['verifiedRate'].'%)'],
                    ['label' => 'Total alumni', 'value' => (string) $total],
                ],
                'Verification is a manual step with no owner or deadline.',
                'Work through the Pending Verification list and confirm or reject each record.',
                'Alumni coordinator', ['band' => 'High', 'value' => 0.9],
                ['count' => $p['awaiting'], 'total' => $total, 'unit' => 'alumni'],
            );
        }
        $status[] = ['key' => 'verification', 'label' => 'Records verified', 'checked' => true, 'raised' => $raised];

        // 2. Records that cannot be written to.
        $unreachable = $total - $p['reachable'];
        $raised = false;
        if ($total > 0 && $unreachable / $total * 100 >= self::UNREACHABLE_SHARE) {
            $raised = true;
            $share = round($unreachable / $total * 100, 1);
            $findings[] = $this->finding(
                'alumni-unreachable', $share >= 50 ? 'high' : 'medium',
                $unreachable.' alumni cannot be contacted',
                $unreachable.' of '.$total.' records ('.$share.'%) have no address or no consent on any channel.',
                'An alumni campaign reaches only the reachable share. The rest are silently skipped, so engagement figures overstate how many people were written to.',
                [
                    ['label' => 'Reachable', 'value' => $p['reachable'].' ('.$p['reachableRate'].'%)'],
                    ['label' => 'Not reachable', 'value' => (string) $unreachable],
                ],
                'Consent was never asked, or contact details were not collected when the record was created.',
                'Collect consent and a current email or mobile at the next touchpoint — an event, a survey or the portal login.',
                'Alumni coordinator', ['band' => 'High', 'value' => 0.88],
                ['count' => $unreachable, 'total' => $total, 'unit' => 'alumni'],
            );
        }
        $status[] = ['key' => 'reachable', 'label' => 'Alumni reachable', 'checked' => true, 'raised' => $raised];

        // 3. Thin profiles.
        $incomplete = (int) $this->alumni()->whereNotNull('profile_completion')
            ->where('profile_completion', '<', self::COMPLETION_FLOOR)->count();
        $raised = false;
        if ($total > 0 && $incomplete / $total * 100 >= self::INCOMPLETE_SHARE) {
            $raised = true;
            $share = round($incomplete / $total * 100, 1);
            $findings[] = $this->finding(
                'alumni-incomplete-profiles', 'medium',
                $incomplete.' alumni profiles are under '.self::COMPLETION_FLOOR.'% complete',
                $incomplete.' of '.$total.' profiles ('.$share.'%) are below the completeness floor.',
                'Thin profiles cannot be matched to mentors, opportunities or surveys, so the directory looks fuller than it is useful.',
                [
                    ['label' => 'Incomplete profiles', 'value' => (string) $incomplete],
                    ['label' => 'Average completion', 'value' => ($p['profileCompletion'] ?? 0).'%'],
                ],
                'Records created by import or by staff with only the minimum fields.',
                'Invite these alumni to complete their profile, or fill the gaps from the original student record.',
                'Alumni coordinator', ['band' => 'Medium', 'value' => 0.75],
                ['count' => $incomplete, 'total' => $total, 'unit' => 'profiles'],
            );
        }
        $status[] = ['key' => 'completion', 'label' => 'Profile completeness', 'checked' => $p['profileCompletion'] !== null, 'raised' => $raised];

        // 4. Alumni who have gone quiet.
        $raised = false;
        if ($total > 0 && (100 - $p['activeRate']) >= self::DORMANT_SHARE) {
            $raised = true;
            $dormant = $total - $p['activeLast12'];
            $findings[] = $this->finding(
                'alumni-dormant', 'medium',
                $dormant.' alumni show no activity in the last 12 months',
                'Only '.$p['activeLast12'].' of '.$total.' alumni ('.$p['activeRate'].'%) have any recorded activity in the last year.',
                'An alumni body that is mostly silent is a directory, not a community. Mentoring, events and giving all depend on the active share.',
                [
                    ['label' => 'Active in the last 12 months', 'value' => (string) $p['activeLast12']],
                    ['label' => 'No recent activity', 'value' => (string) $dormant],
                ],
                'No recent event, survey or message has drawn these alumni back.',
                'Run a re-engagement campaign to the reachable dormant alumni and measure who responds.',
                'Alumni coordinator', ['band' => 'Medium', 'value' => 0.7],
                ['count' => $dormant, 'total' => $total, 'unit' => 'alumni'],
            );
        }
        $status[] = ['key' => 'activity', 'label' => 'Recent activity', 'checked' => true, 'raised' => $raised];

        // 5. Students waiting on a mentor.
        $waiting = 0;
        $checked = SchemaCache::hasTable('alumni_mentorship_requests');
        if ($checked) {
            $waiting = (int) DB::table('alumni_mentorship_requests')->where('sub_institute_id', $this->tenantId)
                ->where('status', 'open')->where('created_at', '<', now()->subDays(self::WAITING_DAYS))->count();
        }
        $raised = false;
        if ($waiting > 0) {
            $raised = true;
            $findings[] = $this->finding(
                'alumni-mentor-requests-waiting', 'high',
                $waiting.' mentorship request'.($waiting === 1 ? ' has' : 's have').' waited over '.self::WAITING_DAYS.' days',
                $waiting.' open request'.($waiting === 1 ? '' : 's').' from students have no mentor matched.',
                'A student who asks for guidance and hears nothing stops asking. The wait is the cost to the programme\'s credibility.',
                [
                    ['label' => 'Open requests over '.self::WAITING_DAYS.' days', 'value' => (string) $waiting],
                    ['label' => 'Active mentors', 'value' => (string) $p['mentors']],
                ],
                $p['mentors'] === 0 ? 'There are no active mentors to match against.' : 'Matching is manual and has not been done.',
                'Match or decline each open request, and recruit mentors for the career areas that are asked for.',
                'Mentorship lead', ['band' => 'High', 'value' => 0.85],
                ['count' => $waiting, 'total' => max($waiting, $p['openMentorRequests']), 'unit' => 'requests'],
            );
        }
        $status[] = ['key' => 'mentor_requests', 'label' => 'Mentorship requests answered', 'checked' => $checked, 'raised' => $raised];

        // 6. Gifts nobody has confirmed.
        $pending = 0;
        $checked = SchemaCache::hasTable('alumni_donations');
        if ($checked) {
            $pending = (int) DB::table('alumni_donations')->where('sub_institute_id', $this->tenantId)
                ->where('status', 'pending_verification')->where('created_at', '<', now()->subDays(self::WAITING_DAYS))->count();
        }
        $raised = false;
        if ($pending > 0) {
            $raised = true;
            $findings[] = $this->finding(
                'alumni-donations-unverified', 'medium',
                $pending.' donation'.($pending === 1 ? '' : 's').' waiting over '.self::WAITING_DAYS.' days for verification',
                $pending.' donation'.($pending === 1 ? '' : 's').' reported by alumni have not been confirmed against a payment.',
                'A donor who has paid and receives no receipt rarely gives again.',
                [['label' => 'Pending verification', 'value' => (string) $pending]],
                'Verification needs someone to check the bank or payment reference by hand.',
                'Confirm or reject each pending donation and issue the receipt.',
                'Accounts', ['band' => 'Medium', 'value' => 0.75],
                ['count' => $pending, 'total' => $pending, 'unit' => 'donations'],
            );
        }
        $status[] = ['key' => 'donations', 'label' => 'Donations verified', 'checked' => $checked, 'raised' => $raised];

        return ['findings' => $findings, 'ruleStatus' => $status];
    }

    private function finding(
        string $id, string $severity, string $title, string $what, string $why,
        array $evidence, string $cause, string $recommendation, string $owner,
        array $confidence, array $affected,
    ): array {
        return [
            'id' => $id,
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => $title,
            'whatHappened' => $what,
            'whyItMatters' => $why,
            'evidence' => $evidence,
            'likelyCause' => $cause,
            'causeConfirmed' => false,
            'recommendation' => $recommendation,
            'owner' => $owner,
            'priority' => $severity,
            'confidence' => $confidence,
            'affected' => $affected,
            'impact' => null,
            'status' => 'open',
        ];
    }
}
