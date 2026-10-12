<?php

namespace App\Console\Commands;

use App\Services\Alumni\AlumniService;
use App\Services\Alumni\DuplicateAlumniException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds (or removes) clearly-labelled SAMPLE alumni for one institute so the Alumni screens
 * have something to show during development and demos.
 *
 *   php artisan alumni:sample-data 1            add ~30 sample alumni to institute 1
 *   php artisan alumni:sample-data 1 --remove   delete exactly those rows again
 *
 * Every sample row has an email ending in @sample.invalid (a reserved TLD that can never be
 * delivered), which is also what --remove matches, so real alumni are never touched. Rows go
 * through AlumniService, so IDs, completion and audit behave like real data. Re-running adds
 * nothing (duplicates are detected and skipped). Not for production tenants.
 */
class AlumniSampleData extends Command
{
    protected $signature = 'alumni:sample-data {tenant : sub_institute_id} {--remove : delete the sample rows instead of adding them}';

    protected $description = 'Add or remove labelled sample alumni for one institute';

    private const DOMAIN = '@sample.invalid';

    public function handle(AlumniService $alumni): int
    {
        $tenant = (int) $this->argument('tenant');
        if ($tenant <= 0) {
            $this->error('Give a valid institute id.');

            return self::FAILURE;
        }

        return $this->option('remove') ? $this->remove($tenant) : $this->add($tenant, $alumni);
    }

    private function remove(int $tenant): int
    {
        $ids = DB::table('alumni')->where('sub_institute_id', $tenant)->where('email', 'like', '%' . self::DOMAIN)->pluck('id')->all();
        $this->removeModuleData($tenant, $ids);
        foreach (['alumni_education', 'alumni_career', 'alumni_business', 'alumni_skills', 'alumni_achievements', 'alumni_documents', 'alumni_privacy', 'alumni_activity'] as $table) {
            DB::table($table)->where('sub_institute_id', $tenant)->whereIn('alumni_id', $ids)->delete();
        }
        DB::table('alumni')->where('sub_institute_id', $tenant)->whereIn('id', $ids)->delete();
        $this->info(count($ids) . ' sample alumni removed from institute ' . $tenant . '.');

        return self::SUCCESS;
    }

    private function add(int $tenant, AlumniService $svc): int
    {
        $first = ['Aarav', 'Diya', 'Vihaan', 'Anaya', 'Kabir', 'Isha', 'Rohan', 'Meera', 'Arjun', 'Sara', 'Dev', 'Riya', 'Karan', 'Nisha', 'Yash', 'Tara', 'Ishaan', 'Kiara', 'Reyansh', 'Pooja', 'Neel', 'Avni', 'Harsh', 'Zoya', 'Parth', 'Mira', 'Om', 'Sneha', 'Jay', 'Anika'];
        $last = ['Shah', 'Patel', 'Mehta', 'Desai', 'Joshi', 'Trivedi', 'Pandya', 'Modi', 'Parikh', 'Thakkar'];
        $places = [
            ['Ahmedabad', 'Gujarat', 'India'], ['Surat', 'Gujarat', 'India'], ['Pune', 'Maharashtra', 'India'],
            ['Mumbai', 'Maharashtra', 'India'], ['Bengaluru', 'Karnataka', 'India'], ['London', 'England', 'United Kingdom'],
            ['Toronto', 'Ontario', 'Canada'], ['Dubai', 'Dubai', 'United Arab Emirates'], ['New York', 'New York', 'United States'],
        ];
        $jobs = [
            ['TCS', 'IT', 'Software Engineer'], ['Infosys', 'IT', 'Systems Analyst'], ['Google', 'IT', 'Software Engineer'],
            ['Microsoft', 'IT', 'Program Manager'], ['Deloitte', 'Consulting', 'Consultant'], ['HDFC Bank', 'Banking', 'Relationship Manager'],
            ['Apollo Hospitals', 'Healthcare', 'Resident Doctor'], ['Tata Motors', 'Manufacturing', 'Design Engineer'],
            ['Times Group', 'Media', 'Content Editor'], ['Reliance Retail', 'Retail', 'Category Manager'],
        ];
        $degrees = [
            ['B.Tech', 'Computer Engineering', 'Nirma University'], ['MBBS', 'Medicine', 'B.J. Medical College'],
            ['B.Com', 'Accounting and Finance', 'H.L. College of Commerce'], ['BBA', 'Management', 'Pandit Deendayal Energy University'],
            ['B.Sc', 'Physics', 'Gujarat University'], ['M.S.', 'Data Science', 'University of Toronto'],
        ];
        $skills = ['Python', 'Public speaking', 'Accounting', 'Design', 'Project management', 'Teaching', 'Marketing', 'Data analysis'];
        $employment = ['employed', 'employed', 'employed', 'student', 'job_seeking', 'entrepreneur', 'government_employee', 'freelancer'];

        $created = 0;
        $skipped = 0;
        for ($i = 0; $i < 30; $i++) {
            $year = 2018 + ($i % 7);
            [$city, $state, $country] = $places[$i % count($places)];
            $name = $first[$i];
            $email = strtolower($name) . '.' . strtolower($last[$i % 10]) . $i . self::DOMAIN;
            $self = $i >= 25;   // the last five are self-registrations waiting for review

            try {
                $row = $svc->create($tenant, [
                    'first_name' => $name, 'last_name' => $last[$i % 10],
                    'gender' => $i % 2 === 0 ? 'Male' : 'Female', 'dob' => ($year - 18) . '-0' . (1 + $i % 9) . '-1' . ($i % 9),
                    'email' => $email, 'mobile' => '00000' . str_pad((string) (1000 + $i), 5, '0', STR_PAD_LEFT),
                    'city' => $city, 'state' => $state, 'country' => $country,
                    'passing_year' => $year, 'admission_year' => $year - 12, 'last_class' => '12',
                    'employment_status' => $employment[$i % count($employment)],
                    'linkedin_url' => $i % 3 === 0 ? 'https://www.linkedin.com/in/sample-' . $i : null,
                ], null, $self ? 'self' : 'manual');
            } catch (DuplicateAlumniException) {
                $skipped++;

                continue;
            }
            $created++;
            $id = (int) $row->id;
            $now = now();

            if (! $self) {
                // Mix of verified / active / plain registered so the dashboard has a spread.
                $svc->changeStatus($tenant, $id, $i % 3 === 0 ? 'active' : ($i % 3 === 1 ? 'verified' : 'registered'), null);
            }
            if ($i % 5 === 4) {
                continue;   // leave a few profiles sparse, as real ones are
            }

            [$company, $industry, $title] = $jobs[$i % count($jobs)];
            DB::table('alumni_career')->insert([
                'sub_institute_id' => $tenant, 'alumni_id' => $id, 'employment_status' => 'employed', 'company_name' => $company,
                'industry' => $industry, 'job_title' => $title, 'start_date' => ($year + 4) . '-07-01', 'is_current' => 1,
                'location' => $city, 'country' => $country, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $svc->syncCurrentCareer($tenant, $id);

            [$qual, $course, $uni] = $degrees[$i % count($degrees)];
            DB::table('alumni_education')->insert([
                'sub_institute_id' => $tenant, 'alumni_id' => $id, 'qualification' => $qual, 'course' => $course, 'institution' => $uni,
                'city' => $city, 'country' => $country, 'admission_year' => $year, 'passing_year' => $year + 4,
                'current_status' => 'completed', 'score' => '8.' . ($i % 9), 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('alumni_skills')->insert([
                'sub_institute_id' => $tenant, 'alumni_id' => $id, 'skill' => $skills[$i % count($skills)],
                'level' => ['intermediate', 'advanced', 'expert'][$i % 3], 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($i % 4 === 0) {
                DB::table('alumni_achievements')->insert([
                    'sub_institute_id' => $tenant, 'alumni_id' => $id, 'category' => 'award', 'title' => 'Young Achiever Award',
                    'organization' => 'Sample Foundation', 'achieved_on' => ($year + 5) . '-01-26', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            if ($i % 7 === 0) {
                DB::table('alumni_business')->insert([
                    'sub_institute_id' => $tenant, 'alumni_id' => $id, 'business_name' => $name . ' Studio', 'business_type' => 'Startup',
                    'industry' => 'Design', 'role' => 'founder', 'established_year' => $year + 5, 'location' => $city,
                    'employee_count' => 4 + $i, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $svc->refreshCompletion($tenant, $id);
        }

        $this->info("Institute {$tenant}: {$created} sample alumni added, {$skipped} already present.");
        $this->addModuleData($tenant);
        $this->line('Remove them any time with: php artisan alumni:sample-data ' . $tenant . ' --remove');

        return self::SUCCESS;
    }

    /** Name prefix on every non-alumni sample row (events, surveys, campaigns...), which is what --remove matches. */
    private const TAG = '[Sample] ';

    private function removeModuleData(int $tenant, array $ids): void
    {
        $t = fn (string $table) => DB::table($table)->where('sub_institute_id', $tenant);
        $events = $t('alumni_events')->where('name', 'like', self::TAG . '%')->pluck('id')->all();
        foreach (['alumni_event_registrations', 'alumni_event_feedback'] as $table) {
            $t($table)->whereIn('event_id', $events)->delete();
        }
        $t('alumni_events')->whereIn('id', $events)->delete();

        $surveys = $t('alumni_surveys')->where('title', 'like', self::TAG . '%')->pluck('id')->all();
        $subs = $t('alumni_survey_submissions')->whereIn('survey_id', $surveys)->pluck('id')->all();
        $t('alumni_survey_answers')->whereIn('submission_id', $subs)->delete();
        foreach (['alumni_survey_submissions', 'alumni_survey_participants', 'alumni_survey_questions'] as $table) {
            $t($table)->whereIn('survey_id', $surveys)->delete();
        }
        $t('alumni_surveys')->whereIn('id', $surveys)->delete();

        $camps = $t('alumni_campaigns')->where('name', 'like', self::TAG . '%')->pluck('id')->all();
        $t('alumni_message_log')->whereIn('campaign_id', $camps)->delete();
        $t('alumni_campaigns')->whereIn('id', $camps)->delete();
        $t('alumni_message_templates')->where('name', 'like', self::TAG . '%')->delete();

        $dc = $t('alumni_donation_campaigns')->where('name', 'like', self::TAG . '%')->pluck('id')->all();
        $t('alumni_donations')->where(fn ($q) => $q->whereIn('alumni_id', $ids)->orWhereIn('campaign_id', $dc))->delete();
        $t('alumni_donation_campaigns')->whereIn('id', $dc)->delete();

        $reqs = $t('alumni_mentorship_requests')->where('guidance_needed', 'like', self::TAG . '%')->pluck('id')->all();
        $sess = $t('alumni_mentorship_sessions')->whereIn('request_id', $reqs)->pluck('id')->all();
        $t('alumni_mentorship_feedback')->whereIn('session_id', $sess)->delete();
        $t('alumni_mentorship_sessions')->whereIn('id', $sess)->delete();
        $t('alumni_mentorship_requests')->whereIn('id', $reqs)->delete();
        $t('alumni_mentor_profiles')->whereIn('alumni_id', $ids)->delete();
        $t('alumni_opportunities')->where('title', 'like', self::TAG . '%')->delete();
    }

    /**
     * Fills the screens that depend on more than alumni rows - mentorship, jobs, events, surveys,
     * messaging and donations. Donations stay pledged / awaiting verification on purpose: a receipt
     * number is only ever issued by a real verification, never seeded. Nothing is actually sent.
     */
    private function addModuleData(int $tenant): void
    {
        $tag = self::TAG;
        if (DB::table('alumni_events')->where('sub_institute_id', $tenant)->where('name', 'like', $tag . '%')->exists()) {
            $this->line('Module sample data already present.');

            return;
        }
        $ids = DB::table('alumni')->where('sub_institute_id', $tenant)->where('email', 'like', '%' . self::DOMAIN)
            ->where('status', '!=', 'pending_verification')->orderBy('id')->pluck('id')->all();
        if (count($ids) < 8) {
            $this->warn('Fewer than 8 reviewed sample alumni; module sample data skipped.');

            return;
        }
        $now = now();
        $base = ['sub_institute_id' => $tenant, 'created_at' => $now, 'updated_at' => $now];
        $ins = fn (string $table, array $row) => (int) DB::table($table)->insertGetId($row + $base);

        foreach (array_slice($ids, 0, 5) as $k => $aid) {
            $ins('alumni_mentor_profiles', ['alumni_id' => $aid, 'expertise' => ['Software', 'Medicine', 'Finance', 'Design', 'Teaching'][$k],
                'industry' => ['IT', 'Healthcare', 'Banking', 'Media', 'Education'][$k], 'job_role' => 'Senior professional', 'preferred_topics' => 'Career choices, interviews',
                'available_days' => 'Sat,Sun', 'mode' => 'both', 'experience_years' => 4 + $k, 'capacity' => 3, 'status' => $k === 4 ? 'paused' : 'active']);
        }
        $students = DB::table('tblstudent')->where('sub_institute_id', $tenant)->orderBy('id')->limit(4)->pluck('id')->all();
        $reqIds = [];
        foreach ($students as $k => $sid) {
            $status = ['open', 'matched', 'matched', 'declined'][$k];
            $reqIds[$k] = $ins('alumni_mentorship_requests', ['student_id' => $sid, 'career_area' => ['Engineering', 'Medicine', 'Commerce', 'Design'][$k],
                'guidance_needed' => $tag . 'How do I choose a stream and prepare for entrance exams?', 'status' => $status,
                'mentor_alumni_id' => $status === 'matched' ? $ids[$k] : null, 'created_by_role' => 'staff']);
        }
        foreach ([1, 2] as $k) {
            if (! isset($reqIds[$k])) {
                continue;
            }
            $sess = $ins('alumni_mentorship_sessions', ['request_id' => $reqIds[$k], 'mentor_alumni_id' => $ids[$k], 'student_id' => $students[$k],
                'scheduled_at' => $k === 1 ? $now->copy()->subDays(7) : $now->copy()->addDays(5), 'duration_minutes' => 45, 'mode' => 'online',
                'status' => $k === 1 ? 'completed' : 'scheduled']);
            if ($k === 1) {
                $ins('alumni_mentorship_feedback', ['session_id' => $sess, 'from_role' => 'student', 'rating' => 5, 'comments' => 'Very helpful.']);
            }
        }

        foreach ([['job', 'published'], ['internship', 'published'], ['job', 'pending_approval'], ['apprenticeship', 'pending_approval'], ['freelance', 'rejected'], ['project', 'closed']] as $k => [$type, $st]) {
            $ins('alumni_opportunities', ['alumni_id' => $ids[$k], 'type' => $type, 'company' => ['TCS', 'Infosys', 'Google', 'Deloitte', 'HDFC Bank', 'Tata Motors'][$k],
                'title' => $tag . ucfirst($type) . ' opening ' . ($k + 1), 'location' => 'Ahmedabad', 'experience' => $k % 2 ? '0-1 years' : '2-4 years', 'skills' => 'Communication, Excel',
                'last_date' => $now->copy()->addDays($st === 'closed' ? -10 : 30)->toDateString(), 'description' => 'Sample opening posted for testing.',
                'status' => $st, 'published_at' => $st === 'published' ? $now : null, 'review_remarks' => $st === 'rejected' ? 'Incomplete details.' : null]);
        }

        $evs = [];
        foreach ([['alumni_meet', 'Annual Alumni Meet', 20, 'published', 0], ['career_talk', 'Career Talk: Software', 8, 'published', 0], ['batch_reunion', 'Batch 2018 Reunion', 45, 'draft', 0],
            ['guest_lecture', 'Guest Lecture: Finance', -14, 'completed', 0], ['fundraising_event', 'Scholarship Dinner', 60, 'published', 500]] as $k => [$type, $name, $off, $st, $fee]) {
            $evs[$k] = $ins('alumni_events', ['name' => $tag . $name, 'description' => 'Sample event for testing.', 'event_type' => $type,
                'event_date' => $now->copy()->addDays($off)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '13:00:00', 'venue' => 'School auditorium', 'mode' => 'offline',
                'capacity' => 50, 'registration_required' => 1, 'auto_confirm' => 1, 'organizer' => 'Alumni Cell', 'fee_amount' => $fee, 'status' => $st]);
        }
        foreach (array_slice($ids, 0, 8) as $k => $aid) {
            $past = $k < 5;
            $evId = $past ? $evs[3] : $evs[$k % 2];
            $ins('alumni_event_registrations', ['event_id' => $evId, 'alumni_id' => $aid, 'registration_no' => 'SMP-' . $evId . '-' . str_pad((string) $aid, 5, '0', STR_PAD_LEFT),
                'participants' => 1, 'payment_status' => 'none', 'status' => $past ? ($k < 4 ? 'attended' : 'no_show') : 'confirmed', 'qr_token' => bin2hex(random_bytes(16)),
                'registered_at' => $now, 'confirmed_at' => $now, 'attended_at' => $past && $k < 4 ? $now : null, 'attendance_method' => $past && $k < 4 ? 'manual' : null]);
            if ($past && $k < 3) {
                $ins('alumni_event_feedback', ['event_id' => $evs[3], 'alumni_id' => $aid, 'rating' => 4 + ($k % 2), 'comments' => 'Well organised.']);
            }
        }

        foreach ([['Alumni Satisfaction Survey', 'alumni_satisfaction', 'published'], ['Career Progress Survey', 'career_progress', 'draft'], ['Curriculum Feedback 2025', 'curriculum_feedback', 'closed']] as [$title, $type, $st]) {
            $sv = $ins('alumni_surveys', ['title' => $tag . $title, 'survey_type' => $type, 'description' => 'Sample survey.', 'status' => $st, 'anonymous' => 0,
                'opens_at' => $now->copy()->subDays(10), 'closes_at' => $now->copy()->addDays($st === 'closed' ? -2 : 20), 'published_at' => $st === 'draft' ? null : $now]);
            $q1 = $ins('alumni_survey_questions', ['survey_id' => $sv, 'sort_order' => 1, 'question_type' => 'rating', 'prompt' => 'How satisfied are you with the school?', 'required' => 1, 'scale_min' => 1, 'scale_max' => 5]);
            $ins('alumni_survey_questions', ['survey_id' => $sv, 'sort_order' => 2, 'question_type' => 'text', 'prompt' => 'What should we improve?', 'required' => 0]);
            if ($st === 'draft') {
                continue;
            }
            foreach (array_slice($ids, 0, 6) as $j => $aid) {
                $ins('alumni_survey_participants', ['survey_id' => $sv, 'alumni_id' => $aid, 'submitted_at' => $j < 4 ? $now : null]);
                if ($j < 4) {
                    $sub = $ins('alumni_survey_submissions', ['survey_id' => $sv, 'alumni_id' => $aid, 'submitted_at' => $now]);
                    $ins('alumni_survey_answers', ['submission_id' => $sub, 'question_id' => $q1, 'answer_value' => 3 + ($j % 3)]);
                }
            }
        }

        foreach ([['Welcome', 'welcome'], ['Event invitation', 'event_invitation'], ['Survey request', 'survey_request']] as [$name, $type]) {
            $ins('alumni_message_templates', ['name' => $tag . $name, 'comm_type' => $type, 'subject' => $name . ' - {{institute}}', 'body' => "Dear {{name}},\n\nSample template body.", 'is_system' => 0]);
        }
        $camp = $ins('alumni_campaigns', ['name' => $tag . 'Reunion announcement', 'comm_type' => 'event_invitation', 'subject' => 'Annual Alumni Meet', 'body' => 'Dear {{name}}, join us.',
            'channels' => 'email', 'status' => 'sent', 'started_at' => $now, 'finished_at' => $now, 'recipient_count' => 6, 'sent_count' => 0, 'skipped_count' => 6]);
        foreach (array_slice($ids, 0, 6) as $aid) {
            $ins('alumni_message_log', ['campaign_id' => $camp, 'alumni_id' => $aid, 'comm_type' => 'event_invitation', 'channel' => 'email', 'status' => 'skipped',
                'reason' => 'sample data - nothing was sent', 'dedupe_key' => 'sample-' . $camp . '-' . $aid]);
        }
        $ins('alumni_campaigns', ['name' => $tag . 'Newsletter draft', 'comm_type' => 'newsletter', 'subject' => 'Alumni newsletter', 'body' => 'Dear {{name}}, news.', 'channels' => 'email', 'status' => 'draft']);

        $dc = $ins('alumni_donation_campaigns', ['name' => $tag . 'Scholarship Fund', 'purpose' => 'scholarship', 'goal_amount' => 500000, 'starts_on' => $now->copy()->subDays(30)->toDateString(),
            'ends_on' => $now->copy()->addDays(60)->toDateString(), 'status' => 'active']);
        $ins('alumni_donation_campaigns', ['name' => $tag . 'Library Renovation', 'purpose' => 'infrastructure', 'goal_amount' => 200000, 'status' => 'draft']);
        foreach ([['pledged', 5000], ['pledged', 2500], ['pending_verification', 10000], ['pending_verification', 1000], ['rejected', 750]] as $k => [$st, $amt]) {
            $ins('alumni_donations', ['alumni_id' => $ids[$k], 'campaign_id' => $dc, 'amount' => $amt, 'currency' => 'INR', 'purpose' => 'scholarship',
                'payment_mode' => $st === 'pledged' ? null : 'bank_transfer', 'reference_no' => $st === 'pledged' ? null : 'SAMPLE-REF-' . $k,
                'pledged_on' => $now->copy()->subDays(3)->toDateString(), 'received_on' => $st === 'pledged' ? null : $now->toDateString(), 'status' => $st, 'anonymous' => 0,
                'decision_note' => $st === 'rejected' ? 'Reference could not be matched.' : null]);
        }

        $this->info('Module sample data added: mentors' . ($students ? ', mentorship requests/sessions' : ' (no students here, mentorship requests skipped)')
            . ', jobs, events, surveys, templates/campaigns, donation campaigns/donations (no receipts).');
    }
}
