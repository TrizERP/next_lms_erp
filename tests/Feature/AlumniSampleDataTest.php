<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * alumni:sample-data must fill every Alumni screen, never issue a receipt number, be safe to re-run, and
 * --remove must take out exactly its own rows and leave real ones alone. In-memory sqlite (see AlumniTestHelpers).
 */
class AlumniSampleDataTest extends TestCase
{
    use AlumniTestHelpers;

    private const TABLES = [
        'alumni_mentor_profiles', 'alumni_mentorship_requests', 'alumni_mentorship_sessions', 'alumni_mentorship_feedback', 'alumni_opportunities',
        'alumni_events', 'alumni_event_registrations', 'alumni_event_feedback', 'alumni_surveys', 'alumni_survey_questions', 'alumni_survey_participants',
        'alumni_survey_submissions', 'alumni_survey_answers', 'alumni_message_templates', 'alumni_campaigns', 'alumni_message_log',
        'alumni_donation_campaigns', 'alumni_donations',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
        if (! Schema::hasTable('tblstudent')) {
            Schema::create('tblstudent', function ($t) {
                $t->increments('id');
                $t->integer('sub_institute_id');
            });
        }
        DB::table('tblstudent')->insert([['sub_institute_id' => 7], ['sub_institute_id' => 7], ['sub_institute_id' => 7], ['sub_institute_id' => 7]]);
    }

    public function test_it_fills_every_module_without_issuing_receipts_then_removes_only_its_own_rows(): void
    {
        $real = $this->alum('Realperson');
        $this->artisan('alumni:sample-data', ['tenant' => 7])->assertExitCode(0);

        foreach (self::TABLES as $table) {
            $this->assertGreaterThan(0, DB::table($table)->where('sub_institute_id', 7)->count(), $table . ' has no sample rows');
        }
        $this->assertSame(0, DB::table('alumni_donations')->whereNotNull('receipt_no')->count(), 'sample data must never carry a receipt number');
        $this->assertSame(0, DB::table('alumni_donations')->where('status', 'verified')->count());
        $this->assertSame(0, DB::table('alumni_message_log')->where('status', 'sent')->count(), 'sample data must not claim anything was sent');

        $counts = array_map(fn ($t) => DB::table($t)->count(), self::TABLES);
        $this->artisan('alumni:sample-data', ['tenant' => 7])->assertExitCode(0);
        $this->assertSame($counts, array_map(fn ($t) => DB::table($t)->count(), self::TABLES), 're-running must not duplicate');

        $this->artisan('alumni:sample-data', ['tenant' => 7, '--remove' => true])->assertExitCode(0);
        foreach (self::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table . ' left rows behind after --remove');
        }
        $this->assertSame(1, DB::table('alumni')->count());
        $this->assertSame($real->id, DB::table('alumni')->value('id'));
    }

    public function test_it_skips_other_institutes(): void
    {
        $this->artisan('alumni:sample-data', ['tenant' => 7])->assertExitCode(0);
        $this->assertSame(0, DB::table('alumni')->where('sub_institute_id', '!=', 7)->count());
        $this->assertSame(0, DB::table('alumni_events')->where('sub_institute_id', '!=', 7)->count());
    }
}
