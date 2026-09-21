<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\HrIntelligence;
use App\Http\Controllers\Brain\BrainHrIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HrIntelligenceTest extends TestCase
{
    private function tenantWithStaff(): ?string
    {
        try {
            $row = DB::table('tbluser')
                ->select('sub_institute_id', DB::raw('COUNT(*) as c'))
                ->whereNotNull('sub_institute_id')
                ->where('sub_institute_id', '>', 0)
                ->groupBy('sub_institute_id')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? (string) $row->sub_institute_id : null;
    }

    private function payload(string $tenant): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/hr/intelligence', 'GET');

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainHrIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithStaff();
        if ($tenant === null) {
            $this->markTestSkipped('No staff data in this database.');
        }

        $payload = $this->payload($tenant);

        foreach ([
            'tenantId', 'organization', 'source', 'academicYear', 'coverage',
            'freshness', 'execution', 'summary', 'position', 'breakdowns',
            'findings', 'priorities', 'recommendations', 'decisionTrail',
            'learning', 'dataQuality', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Payload is missing '{$key}'");
        }

        $this->assertEquals($tenant, $payload['tenantId']);
    }

    public function test_empty_tenant_reports_honest_coverage_state(): void
    {
        $analytics = new HrIntelligence('999999', null);
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
    }

    public function test_tenant_isolation_never_leaks_records(): void
    {
        $tenant = $this->tenantWithStaff();
        if ($tenant === null) {
            $this->markTestSkipped('No staff data in this database.');
        }

        $analytics = new HrIntelligence($tenant, null);
        $pos = $analytics->position();

        if ($pos !== null) {
            $rawStaff = DB::table('tbluser')
                ->where('sub_institute_id', $tenant)
                ->count();

            $rawActive = DB::table('tbluser')
                ->where('sub_institute_id', $tenant)
                ->where('status', 1)
                ->count();

            $this->assertSame($rawStaff, $pos['staff'], 'Staff records do not reconcile against the raw table.');
            $this->assertSame($rawActive, $pos['activeStaff'], 'Active staff does not reconcile.');

            // Reporting every row as the staff body overstates one institute by
            // two hundred people; both figures have to be present and distinct.
            $this->assertLessThanOrEqual($pos['staff'], $pos['activeStaff']);
        }
    }

    public function test_every_finding_carries_evidence_and_hypothesis_tag(): void
    {
        $tenant = $this->tenantWithStaff();
        if ($tenant === null) {
            $this->markTestSkipped('No staff data in this database.');
        }

        $payload = $this->payload($tenant);

        $this->assertIsArray($payload['findings']);
        $this->assertIsArray($payload['ruleStatus']);
        $this->assertNotEmpty($payload['ruleStatus']);

        foreach ($payload['findings'] as $finding) {
            $this->assertArrayHasKey('id', $finding);
            $this->assertArrayHasKey('severity', $finding);
            $this->assertArrayHasKey('title', $finding);
            $this->assertArrayHasKey('whatHappened', $finding);
            $this->assertArrayHasKey('whyItMatters', $finding);
            $this->assertArrayHasKey('evidence', $finding);
            $this->assertArrayHasKey('confidence', $finding);
            $this->assertFalse($finding['causeConfirmed']);
        }
    }

    /* ---------------------------------------------------- the punch register */

    /**
     * Every institute-year that has a usable punch register, and the ones that
     * do not, so both halves of the behaviour are exercised against real data.
     *
     * @return array<int,array{0:string,1:string}>
     */
    private function punchScopes(): array
    {
        try {
            $rows = DB::table('hrms_attendances')
                ->select('sub_institute_id', DB::raw('YEAR(day) as y'), DB::raw('COUNT(DISTINCT user_id) as staff'))
                ->whereNull('deleted_at')
                ->whereBetween('day', ['2000-01-01', '2030-12-31'])
                ->groupBy('sub_institute_id', DB::raw('YEAR(day)'))
                ->orderByDesc('staff')
                ->limit(6)
                ->get();
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            static fn ($r) => [(string) $r->sub_institute_id, (string) $r->y],
            $rows->all(),
        );
    }

    /**
     * THE DEFECT THIS PINS: an absent or unusable register reported as zero.
     *
     * "0% staff attendance" is a statement about the staff. "This institute does
     * not run a punch register" is a statement about the institute, and only the
     * second one is true. Every punch figure is therefore null-or-real, and a
     * null always arrives with the backend's own reason attached.
     */
    public function test_an_unusable_punch_register_is_null_and_never_zero(): void
    {
        $scopes = $this->punchScopes();
        if ($scopes === []) {
            $this->markTestSkipped('No punch register in this database.');
        }

        $checked = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HrIntelligence($tenant, $syear);
            $punch = $analytics->punchProfile();

            if ($punch['available']) {
                $this->assertNull($punch['reason'], "{$tenant}/{$syear}: an available register still carries a reason");
                $this->assertGreaterThanOrEqual(
                    HrIntelligence::MIN_PUNCH_REGISTER,
                    $punch['staff'],
                    "{$tenant}/{$syear}: a register below the floor was reported as usable",
                );
                $checked++;

                continue;
            }

            $this->assertNotNull(
                $punch['reason'],
                "{$tenant}/{$syear}: the register is unavailable and says nothing about why",
            );

            foreach (['workingDays', 'medianDaysPresent', 'medianAttendance', 'openShifts'] as $key) {
                $this->assertNull(
                    $punch[$key],
                    "{$tenant}/{$syear}: {$key} is a figure where there is no usable register — it must be null, "
                        .'because a zero reads as a claim about the staff',
                );
            }

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'No punch scope was actually examined.');
    }

    /**
     * THE DEFECT THIS PINS: a denominator of every day the register holds a row.
     *
     * Security and boarding staff punch on Sundays, so the register spans 359 of
     * a 361-day year. Dividing by that makes every teacher look absent a fifth of
     * the year. The working calendar is derived from the register — days on which
     * a real share of the staff appeared — and must be materially shorter than
     * the raw span while remaining a plausible school year.
     */
    public function test_working_days_exclude_the_days_only_a_skeleton_crew_worked(): void
    {
        $scopes = $this->punchScopes();
        $examined = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HrIntelligence($tenant, $syear);
            $punch = $analytics->punchProfile();
            if (! $punch['available'] || (int) $punch['daysRecorded'] < 100) {
                continue;
            }

            $this->assertLessThanOrEqual(
                (int) $punch['daysRecorded'],
                (int) $punch['workingDays'],
                "{$tenant}/{$syear}: more working days were derived than the register holds days",
            );
            $this->assertGreaterThan(
                0,
                (int) $punch['workingDays'],
                "{$tenant}/{$syear}: a register spanning a full year yielded no working day at all",
            );
            // A six-day school year is roughly 260 days and a five-day one
            // roughly 220. Anything above 300 means holidays are being counted.
            $this->assertLessThan(
                310,
                (int) $punch['workingDays'],
                "{$tenant}/{$syear}: the working-day count is longer than any school year, so the register's quiet "
                    .'days are being counted as days the institute ran',
            );
            $examined++;
        }

        if ($examined === 0) {
            $this->markTestSkipped('No institute-year holds a full year of punch records.');
        }
    }

    /**
     * THE RULE THIS PINS: attendance describes roles and bands, never people.
     *
     * A per-person league table of who came in least would be the most misusable
     * thing this database could put on a screen, and it would not even be true —
     * approved leave and a school trip are indistinguishable from absence here.
     * A role below the cohort floor therefore keeps its counts and loses its
     * median, because the median of four people is four people.
     */
    public function test_attendance_never_describes_a_cohort_small_enough_to_be_a_person(): void
    {
        $scopes = $this->punchScopes();
        $examined = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HrIntelligence($tenant, $syear);
            if (! $analytics->punchProfile()['available']) {
                continue;
            }

            foreach ($analytics->punchesByRole() as $role) {
                $examined++;

                if ($role['staff'] < HrIntelligence::MIN_ROLE_COHORT) {
                    $this->assertTrue(
                        $role['suppressed'],
                        "{$tenant}/{$syear}: role {$role['label']} has {$role['staff']} staff and was not suppressed",
                    );
                    $this->assertNull(
                        $role['medianDaysPresent'],
                        "{$tenant}/{$syear}: role {$role['label']} has {$role['staff']} staff and still reports a "
                            .'median, which describes those individuals',
                    );
                    $this->assertNull($role['attendance']);

                    continue;
                }

                $this->assertFalse($role['suppressed']);
                $this->assertNotNull($role['medianDaysPresent']);
            }

            // A band is a count of people, never a name.
            foreach ($analytics->attendanceBands() as $band) {
                $this->assertIsInt($band['staff']);
                $this->assertArrayNotHasKey('names', $band);
                $this->assertArrayNotHasKey('staffList', $band);
            }
        }

        if ($examined === 0) {
            $this->markTestSkipped('No institute-year has a usable punch register.');
        }
    }

    /**
     * THE RULE THIS PINS: a punch finding may not name an individual.
     *
     * The evidence rows carry role names, counts and shares. Nothing in a punch
     * finding may resolve to one member of staff — so no evidence label may match
     * a name held in the staff master.
     */
    public function test_no_punch_finding_names_a_member_of_staff(): void
    {
        $scopes = $this->punchScopes();
        $examined = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HrIntelligence($tenant, $syear);
            if (! $analytics->punchProfile()['available']) {
                continue;
            }

            $rules = new \App\Brain\Intelligence\HrSignalRules($analytics, $syear);
            $findings = array_filter(
                $rules->run()['findings'],
                static fn ($f) => str_starts_with((string) $f['rule'], 'punch_'),
            );

            foreach ($findings as $finding) {
                $examined++;
                $this->assertNotEmpty(
                    $finding['evidence'],
                    "{$tenant}/{$syear}: {$finding['rule']} raised a finding with no evidence",
                );

                $text = $finding['title'].' '.$finding['whatHappened'];
                foreach ($finding['evidence'] as $row) {
                    $text .= ' '.($row['label'] ?? '').' '.($row['note'] ?? '');
                }

                $named = DB::table('tbluser')
                    ->where('sub_institute_id', $tenant)
                    ->whereRaw('LENGTH(TRIM(COALESCE(first_name, ""))) >= 4')
                    ->whereRaw('? LIKE CONCAT("%", TRIM(first_name), " ", TRIM(COALESCE(last_name, "")), "%")', [$text])
                    ->count();

                $this->assertSame(
                    0,
                    $named,
                    "{$tenant}/{$syear}: {$finding['rule']} names an individual member of staff. Attendance findings "
                        .'describe roles and bands — nothing in this register can tell approved leave from absence.',
                );
            }
        }

        if ($examined === 0) {
            $this->markTestSkipped('No punch finding was raised at any institute-year in this database.');
        }
    }

    /**
     * THE RULE THIS PINS: a contradiction with two opposite readings gets no
     * hypothesis at all.
     *
     * A member of staff marked inactive who is still punching means either that
     * the master is stale or that the door is admitting somebody who has left.
     * The two need opposite responses and nothing in either table decides between
     * them — so the rule is deliberately absent from the approved-cause
     * catalogue, and the screen says "undetermined" rather than composing one.
     */
    public function test_the_stale_master_contradiction_is_left_undetermined(): void
    {
        $this->assertArrayNotHasKey(
            'mod_hr_punch_stale_staff_master',
            \App\Brain\Intelligence\RuleCatalogue::CAUSES,
            'A cause was approved for the stale-staff-master rule. Its two readings need opposite responses and the '
                .'data decides neither, so it must explain nothing rather than pick one.',
        );

        // And the rules it sits beside DO have one, so this is a deliberate
        // omission rather than a catalogue nobody filled in.
        foreach (['mod_hr_punch_open_shifts', 'mod_hr_punch_register_gap', 'mod_hr_punch_attendance_spread'] as $rule) {
            $this->assertArrayHasKey($rule, \App\Brain\Intelligence\RuleCatalogue::CAUSES, "{$rule} has no approved cause");
        }
    }

    /**
     * THE RULE THIS PINS: no punch figure carries an IP address or a photograph.
     *
     * `hrms_attendances` records where a member of staff punched from and a
     * picture of them doing it. Neither belongs anywhere near an Intelligence
     * payload, and the whole payload is checked rather than the columns, so a
     * future join cannot reintroduce them quietly.
     */
    public function test_the_payload_carries_no_punch_ip_address_or_photograph(): void
    {
        $scopes = $this->punchScopes();
        if ($scopes === []) {
            $this->markTestSkipped('No punch register in this database.');
        }

        [$tenant] = $scopes[0];
        $encoded = json_encode($this->payload($tenant));

        foreach (['ipaddress_in', 'ipaddress_out', 'photo_in', 'photo_out'] as $column) {
            $this->assertStringNotContainsString($column, (string) $encoded, "{$column} reached the payload");
        }

        // The values themselves, not only the column names.
        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            (string) $encoded,
            'The payload contains something shaped like an IP address.',
        );
    }
}

