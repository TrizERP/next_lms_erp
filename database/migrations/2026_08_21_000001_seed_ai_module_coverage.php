<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maps the rest of the estate into the AI Workspace.
 *
 * `..._000012_seed_ai_workspace_config` mapped the eight modules the intelligence work
 * needed: dashboard, student, students, fees, attendance, admissions, course, exam. The
 * frontend has fifty-six route folders. Everywhere else the panel resolved no module,
 * fell through to conversation-only, and offered the same four hardcoded prompts — so
 * "the assistant is available on any page" was true, and "the assistant knows where you
 * are" was not.
 *
 * These rows close that gap. Every module here declares:
 *
 *   - route patterns, so the panel can name the module the user is in;
 *   - the conversational capability, because reading and asking is always available;
 *   - two or three curated prompts in the module's own vocabulary.
 *
 * Deliberately *not* here:
 *
 *   - entity bindings. A route only earns `entity_key` once its id is confirmed to be
 *     the ontology's id for that entity. Guessing produces an entity that resolves to
 *     nothing, and the workspace correctly drops it — but the user sees a record name
 *     flicker and vanish. Module-level context is honest and useful; a wrong entity is
 *     neither.
 *   - agent, workflow and ontology capabilities. Those bind to registered agents,
 *     workflow definitions and relationship views. Declaring a capability with nothing
 *     behind it produces an empty tab, which CapabilityResolver would hide anyway.
 *
 * `match_priority` is 80 for everything here — broader than the eight curated modules
 * (10–70), so a specific pattern such as `/fees/collect/:studentId` still wins over the
 * module-level `/fees/**` it sits inside.
 *
 * Idempotent, and scoped to baseline rows: tenant overrides (`sub_institute_id` set)
 * are never read or written.
 */
return new class extends Migration
{
    /**
     * [module_key, label, description, [route patterns], icon, [prompts]]
     *
     * @return array<int, array{0:string,1:string,2:string,3:array<int,string>,4:string,5:array<int,string>}>
     */
    private function modules(): array
    {
        return [
            // ---- Teaching and learning -------------------------------------
            ['lms', 'Learning', 'Lessons, homework and learning activity.',
                ['/lms', '/lms/**'], 'graduation-cap', [
                    'Summarise learning activity for my classes this week.',
                    'Which homework is still outstanding?',
                    'Which students are falling behind in their lessons?',
                ]],
            ['pal', 'Personalised learning', 'Adaptive learning paths and learner state.',
                ['/pal', '/pal/**', '/new-pal', '/new-pal/**'], 'brain', [
                    'Which learners are struggling with their current concepts?',
                    'Summarise learner progress for this class.',
                    'Which misconceptions are showing up most often?',
                ]],
            ['subjects', 'Subjects', 'Subject catalogue and allocation.',
                ['/subjects', '/subjects/**'], 'book', [
                    'Which subjects are offered for this standard?',
                    'Which subjects have no teacher assigned?',
                ]],
            ['chapters', 'Chapters', 'Chapter and topic structure.',
                ['/chapters', '/chapters/**'], 'book-open', [
                    'Summarise the chapters set up for this subject.',
                    'Which chapters have no content attached?',
                ]],
            ['course-master', 'Courses', 'Course master and lesson plans.',
                ['/course-master', '/course-master/**'], 'library', [
                    'Which courses are running this term?',
                    'Which lesson plans are incomplete?',
                ]],
            ['learning-outcome', 'Learning outcomes', 'Outcome definitions and mapping.',
                ['/learning-outcome', '/learning-outcome/**'], 'target', [
                    'Which learning outcomes are mapped to this subject?',
                    'Which outcomes have no assessment covering them?',
                ]],
            ['quiz', 'Quizzes', 'Quiz banks and attempts.',
                ['/quiz', '/quiz/**'], 'help-circle', [
                    'Summarise recent quiz performance.',
                    'Which questions are most often answered incorrectly?',
                ]],
            ['h5p', 'Interactive content', 'H5P interactive learning content.',
                ['/h5p', '/h5p/**'], 'play-circle', [
                    'Which interactive activities are being used most?',
                    'Summarise engagement with this content.',
                ]],

            // ---- People ----------------------------------------------------
            ['teacher_daily_report', 'Teacher daily report', 'Daily teaching activity and absence.',
                ['/teacher_daily_report', '/teacher_daily_report/**'], 'clipboard-check', [
                    "Summarise today's teacher activity.",
                    'Which teachers were absent this week?',
                    'Which classes went uncovered?',
                ]],
            ['classteacher', 'Class teachers', 'Class teacher allocation and reports.',
                ['/classteacher', '/classteacher/**', '/classteacherReport', '/classteacherReport/**'], 'users', [
                    'Which classes have no class teacher assigned?',
                    'Summarise class teacher allocation.',
                ]],
            ['teachertransfer', 'Teacher transfers', 'Transfer requests and movement.',
                ['/teachertransfer', '/teachertransfer/**'], 'arrow-right-left', [
                    'Which transfer requests are pending?',
                    'Summarise recent teacher transfers.',
                ]],
            ['proxy', 'Proxy allocation', 'Substitute teacher allocation and reports.',
                ['/proxy_master', '/proxy_master/**', '/proxy_report', '/proxy_report/**',
                    '/todays_proxy_report', '/todays_proxy_report/**'], 'repeat', [
                    "Summarise today's proxy allocation.",
                    'Which periods still need a substitute?',
                ]],
            ['user', 'Users', 'User accounts, profiles and rights.',
                ['/user', '/user/**', '/user_log', '/user_log/**'], 'user-cog', [
                    'How many active users are there by profile?',
                    'Which accounts have not signed in recently?',
                ]],
            ['career-counselling', 'Career counselling', 'Career guidance and aptitude.',
                ['/career-counselling', '/career-counselling/**'], 'compass', [
                    'Summarise career interests across this class.',
                    'Which students have not completed their assessment?',
                ]],

            // ---- Operations ------------------------------------------------
            ['library', 'Library', 'Catalogue, issues and returns.',
                ['/library', '/library/**'], 'book-marked', [
                    'Which books are overdue?',
                    'Summarise library activity this month.',
                    'Which titles are most borrowed?',
                ]],
            ['hostel', 'Hostel', 'Rooms, allocation and occupancy.',
                ['/hostel', '/hostel/**'], 'bed', [
                    'What is the current hostel occupancy?',
                    'Which rooms have space available?',
                ]],
            ['transportation', 'Transport', 'Routes, vehicles and allocation.',
                ['/Transportation', '/Transportation/**'], 'bus', [
                    'Summarise transport route usage.',
                    'Which routes are over capacity?',
                ]],
            ['inventory', 'Inventory', 'Stock, purchase orders and issue.',
                ['/Inventory', '/Inventory/**'], 'package', [
                    'Which items are running low on stock?',
                    'Which purchase orders are pending?',
                    'Summarise stock movement this month.',
                ]],
            ['front_desk', 'Front desk', 'Visitors, calls and enquiries at reception.',
                ['/front_desk', '/front_desk/**'], 'concierge-bell', [
                    "Summarise today's front desk activity.",
                    'Which enquiries are still open?',
                ]],
            ['inward_outward', 'Inward / outward', 'Correspondence and dispatch register.',
                ['/inward_outward', '/inward_outward/**'], 'mail', [
                    'Summarise this week\'s inward and outward register.',
                    'Which items are awaiting dispatch?',
                ]],
            ['admin-services', 'Admin services', 'Administrative service requests.',
                ['/admin-services', '/admin-services/**'], 'briefcase', [
                    'Which service requests are pending?',
                    'Summarise request volume this month.',
                ]],
            ['easy_com', 'Communication', 'Notices, messages and circulars.',
                ['/easy_com', '/easy_com/**'], 'megaphone', [
                    'What has been communicated this week?',
                    'Which messages are still unsent?',
                ]],
            ['bazar', 'Bazar', 'Store and marketplace.',
                ['/bazar', '/bazar/**'], 'shopping-cart', [
                    'Summarise recent store activity.',
                    'Which items are most requested?',
                ]],

            // ---- Quality, reporting and setup ------------------------------
            ['reports', 'Reports', 'Cross-module reporting.',
                ['/reports', '/reports/**'], 'bar-chart', [
                    'Explain what this report is showing.',
                    'What stands out in these figures?',
                    'Which numbers here need attention?',
                ]],
            ['sqaa', 'Quality assurance', 'SQAA standards, evidence and documents.',
                ['/sqaa', '/sqaa/**', '/sqaa_master', '/sqaa_master/**',
                    '/sqaa_document_report', '/sqaa_document_report/**'], 'shield-check', [
                    'Which quality standards are still unmet?',
                    'Which evidence documents are missing?',
                    'Summarise our current compliance position.',
                ]],
            ['institute', 'Institute', 'Institute profile and details.',
                ['/Institute_Detail', '/Institute_Detail/**'], 'building', [
                    'Summarise the institute profile.',
                    'What details are incomplete?',
                ]],
            ['academic_setup', 'Academic setup', 'Standards, divisions, terms and calendar.',
                ['/academic_setup', '/academic_setup/**'], 'calendar', [
                    'Summarise how the academic year is configured.',
                    'Which classes and divisions are set up?',
                ]],
            ['school_setup', 'School setup', 'Institute configuration.',
                ['/school_setup', '/school_setup/**'], 'settings-2', [
                    'What is configured here, and what is still missing?',
                    'Explain what these settings control.',
                ]],
            ['settings', 'Settings', 'Application settings and preferences.',
                ['/settings', '/settings/**'], 'settings', [
                    'Explain what these settings control.',
                    'What would change if I turned this on?',
                ]],
            ['document-templates', 'Document templates', 'Template design and generation.',
                ['/document-templates', '/document-templates/**'], 'file-text', [
                    'Which templates are available here?',
                    'What data does this template pull in?',
                ]],
            ['ai-platforms', 'AI platforms', 'AI provider configuration.',
                ['/ai-platforms', '/ai-platforms/**'], 'cpu', [
                    'What AI capabilities are available to me?',
                    'Explain what this platform is used for.',
                ]],
            ['migration-modules', 'Data migration', 'Import and migration tooling.',
                ['/migration-modules', '/migration-modules/**', '/Utility', '/Utility/**'], 'database', [
                    'What can be imported here?',
                    'Explain what this migration does.',
                ]],
            ['general', 'General', 'General forms and shared screens.',
                ['/general', '/general/**'], 'layout', [
                    'What is this page for?',
                    'What can you help me with here?',
                ]],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $now = now();

        foreach ($this->modules() as [$key, $label, $description, $patterns, $icon, $prompts]) {
            // Never shadow a curated module. If ..._000012 already owns this key, its
            // entity binding and capabilities are the considered ones and this row
            // would only flatten them.
            $existing = DB::table('ai_modules')
                ->where('module_key', $key)
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing) {
                continue;
            }

            DB::table('ai_modules')->insert([
                'module_key' => $key,
                'label' => $label,
                'domain' => 'k12',
                'description' => $description,
                'route_patterns' => json_encode($patterns),
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => json_encode([
                    'conversational' => true,
                    'generative' => false,
                    'agent' => false,
                    'workflow' => false,
                    'ontology' => false,
                ]),
                'allowed_roles' => null,
                'icon' => $icon,
                'sort_order' => 200,
                'match_priority' => 80,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('ai_suggestions')) {
                continue;
            }

            foreach ($prompts as $index => $prompt) {
                DB::table('ai_suggestions')->insert([
                    'module_key' => $key,
                    'capability' => 'conversational',
                    // The label is the prompt. These are short enough to read as
                    // buttons, and a separate label would only be the same sentence
                    // written twice.
                    'label' => $prompt,
                    'description' => null,
                    'icon' => null,
                    'action_type' => 'prompt',
                    'action_ref' => null,
                    'prompt' => $prompt,
                    'payload' => null,
                    'requires_entity' => false,
                    'allowed_roles' => null,
                    'required_permissions' => null,
                    'sort_order' => ($index + 1) * 10,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Only the keys this migration owns. `up()` skips a key ..._000012 already
        // seeded, so rolling back must not delete that migration's rows on the way out.
        $curated = ['dashboard', 'student', 'students', 'fees', 'attendance', 'admissions', 'course', 'exam'];
        $keys = array_diff(array_column($this->modules(), 0), $curated);

        foreach (['ai_suggestions', 'ai_modules'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)
                    ->whereIn('module_key', $keys)
                    ->whereNull('sub_institute_id')
                    ->delete();
            }
        }
    }
};
