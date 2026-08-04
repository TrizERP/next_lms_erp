<?php

namespace Database\Seeders;

use App\Models\onboarding\OnboardingModuleModel;
use App\Models\onboarding\OnboardingStepModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the global onboarding journey template (sub_institute_id = 0).
 *
 * Every module gets the same eight-step spine. The spine is data, not code —
 * this replaces the legacy approach where "deep" onboarding existed only for
 * two hardcoded menu ids (6 = Fees, 48 = Transportation) branched on inside a
 * Blade view's jQuery. Adding a module is now one row here, not a view edit.
 *
 * Idempotent: re-running updates the template in place and never touches
 * `onboarding_progress`, so tenant progress survives a re-seed.
 *
 * Every `proof_table` below was verified to exist and to carry a
 * `sub_institute_id` column; `proof_scope = tenant_year` is only set on tables
 * that actually have a `syear`/`academic_year` column.
 */
class OnboardingJourneySeeder extends Seeder
{
    /**
     * The eight-step spine. `proof` names which slot of the module definition
     * supplies the proof table: master | config | data, or null for a step that
     * has no table to point at and is signed off manually.
     */
    private const SPINE = [
        [
            'key' => 'training',
            'title' => 'Training & documentation',
            'description' => 'Walk the school team through the module and hand over the training material.',
            'owner' => 'TRIZ',
            'proof' => null,
            'triz_role' => 'Implementation Consultant',
        ],
        [
            'key' => 'master_setup',
            'title' => '%s master setup',
            'description' => 'Create the master records the rest of the module depends on.',
            'owner' => 'SCHOOL',
            'proof' => 'master',
            'triz_role' => 'Implementation Consultant',
        ],
        [
            'key' => 'configuration',
            'title' => '%s configuration',
            'description' => 'Configure the module to match how the school actually works.',
            'owner' => 'TRIZ',
            'proof' => 'config',
            'triz_role' => 'Implementation Consultant',
        ],
        [
            'key' => 'integrations',
            'title' => 'Integrations',
            'description' => 'Connect the module to payment gateways, SMS/WhatsApp and any external system in scope.',
            'owner' => 'TRIZ',
            'proof' => null,
            'triz_role' => 'Technical Consultant',
        ],
        [
            'key' => 'validation',
            'title' => 'Validation & testing',
            'description' => 'Run the agreed test cases end to end and record the outcome.',
            'owner' => 'TRIZ',
            'proof' => null,
            'triz_role' => 'QA Consultant',
        ],
        [
            'key' => 'data_upload',
            'title' => 'Upload existing %s data',
            'description' => 'Import the school\'s existing records using the bulk upload templates.',
            'owner' => 'SCHOOL',
            'proof' => 'data',
            'triz_role' => 'Data Migration Analyst',
        ],
        [
            'key' => 'data_verification',
            'title' => 'Verify the %s data',
            'description' => 'Reconcile the imported records against the school\'s own registers and sign off.',
            'owner' => 'SCHOOL',
            'proof' => null,
            'triz_role' => 'QA Consultant',
        ],
        [
            'key' => 'communication',
            'title' => 'Communication',
            'description' => 'Announce go-live to staff and parents and confirm the support path.',
            'owner' => 'SCHOOL',
            'proof' => null,
            'triz_role' => 'Customer Success',
        ],
    ];

    /**
     * Module registry.
     *
     * subject       — the word substituted into the step titles.
     * menu_title    — joins to tblmenumaster.menu_title so the journey can pull
     *                 the real screen link, youtube_link and pdf_link.
     * master/config/data — [table, scope]; scope 'tenant_year' only where the
     *                 table genuinely has a year column.
     * extras        — additional master-setup steps for modules whose setup is
     *                 genuinely more than one table. Inserted directly after the
     *                 main master step, which is what makes the spine variable
     *                 length: a small module keeps the reference's eight steps,
     *                 a deep one such as Hostel or Result grows to match its real
     *                 sub-module surface. Each entry is
     *                 [step_key, title, table, scope, owner?].
     *
     * A table is never used by two modules — the proof would be ambiguous — so
     * e.g. `period` belongs to Attendance and `std_div_map` to School, and
     * neither is repeated elsewhere.
     */
    private const MODULES = [
        [
            'key' => 'school', 'name' => 'School & academic setup', 'subject' => 'Academic',
            'menu_title' => 'School', 'icon' => 'school', 'school_role' => 'Admin',
            'description' => 'Standards, divisions, subjects and the class structure everything else hangs off.',
            'master' => ['standard', 'tenant'],
            'config' => ['std_div_map', 'tenant'],
            'data' => ['class_teacher', 'tenant_year'],
            'extras' => [
                ['master_batch', 'Batch setup', 'batch', 'tenant_year'],
            ],
        ],
        [
            'key' => 'student', 'name' => 'Student', 'subject' => 'Student',
            'menu_title' => 'Student', 'icon' => 'users', 'school_role' => 'Clerk',
            'description' => 'Student records, enrolment, houses and quota.',
            'master' => ['student_quota', 'tenant'],
            'config' => ['house_master', 'tenant_year'],
            'data' => ['tblstudent', 'tenant'],
        ],
        [
            'key' => 'admission', 'name' => 'Admission', 'subject' => 'Admission',
            'menu_title' => 'Admission', 'icon' => 'user-plus', 'school_role' => 'Clerk',
            'description' => 'Enquiry, registration and the admission form the school publishes.',
            'master' => ['admission_category_master', 'tenant'],
            'config' => ['admission_form', 'tenant'],
            'data' => ['admission_enquiry', 'tenant_year'],
        ],
        [
            'key' => 'fees', 'name' => 'Fees', 'subject' => 'Fees',
            'menu_title' => 'Fees', 'icon' => 'receipt-indian-rupee', 'school_role' => 'Accountant',
            'description' => 'Fee heads, structure, receipt books and collection.',
            'master' => ['fees_title', 'tenant_year'],
            'config' => ['fees_receipt_book_master', 'tenant_year'],
            'data' => ['fees_collect', 'tenant_year'],
            'extras' => [
                ['master_config', 'Fees config master', 'fees_config_master', 'tenant_year', 'TRIZ'],
                ['master_breakoff', 'Fee structure (break-off)', 'fees_breackoff', 'tenant_year'],
            ],
        ],
        [
            'key' => 'lms', 'name' => 'LMS', 'subject' => 'LMS',
            'menu_title' => 'LMS', 'icon' => 'book-open', 'school_role' => 'Teacher',
            'description' => 'Curriculum, chapters, content and lesson plans.',
            'master' => ['chapter_master', 'tenant_year'],
            'config' => ['content_master', 'tenant_year'],
            'data' => ['lms_lesson_plan', 'tenant_year'],
            'extras' => [
                ['master_curriculum', 'Curriculum plan', 'lms_curriculum', 'tenant_year'],
                ['master_syllabus', 'Syllabus', 'syllabus', 'tenant_year'],
            ],
        ],
        [
            'key' => 'result', 'name' => 'Result & exam', 'subject' => 'Result',
            'menu_title' => 'Result', 'icon' => 'graduation-cap', 'school_role' => 'Teacher',
            'description' => 'Grades, exam schedule and marks entry.',
            'master' => ['grade_master', 'tenant'],
            'config' => ['result_create_exam', 'tenant_year'],
            'data' => ['result_marks', 'tenant'],
            'extras' => [
                ['master_grade_map', 'Standard-grade mapping', 'result_std_grd_maping', 'tenant'],
                ['master_result_config', 'Result master configuration', 'result_master_confrigration', 'tenant_year', 'TRIZ'],
                ['master_result_book', 'Result book master', 'result_book_master', 'tenant'],
            ],
        ],
        [
            'key' => 'hrms', 'name' => 'HRMS & payroll', 'subject' => 'HRMS',
            'menu_title' => 'HRMS', 'icon' => 'briefcase', 'school_role' => 'Admin',
            'description' => 'Departments, job titles, leave policy and attendance.',
            'master' => ['hrms_departments', 'tenant'],
            'config' => ['hrms_leave_types', 'tenant'],
            'data' => ['hrms_attendances', 'tenant'],
        ],
        [
            'key' => 'inventory', 'name' => 'Inventory', 'subject' => 'Inventory',
            'menu_title' => 'Inventory', 'icon' => 'package', 'school_role' => 'Store Keeper',
            'description' => 'Item categories, vendors, stores and stock.',
            'master' => ['inventory_item_category_master', 'tenant_year'],
            'config' => ['inventory_master_setup', 'tenant'],
            'data' => ['inventory_item_master', 'tenant_year'],
            'extras' => [
                ['master_sub_category', 'Item sub-category master', 'inventory_item_sub_category_master', 'tenant_year'],
            ],
        ],
        [
            'key' => 'transportation', 'name' => 'Transportation', 'subject' => 'Transport',
            'menu_title' => 'Transportation', 'icon' => 'bus', 'school_role' => 'Transport',
            'description' => 'Vehicles, drivers, routes, stops and student mapping.',
            'master' => ['transport_vehicle', 'tenant'],
            'config' => ['transport_route', 'tenant_year'],
            'data' => ['transport_map_student', 'tenant_year'],
            'extras' => [
                ['master_driver', 'Drivers & conductors', 'transport_driver_detail', 'tenant'],
                ['master_route_bus', 'Route-bus mapping', 'transport_route_bus', 'tenant_year'],
                ['master_route_stop', 'Route stops', 'transport_route_stop', 'tenant_year'],
            ],
        ],
        [
            'key' => 'hostel', 'name' => 'Hostel', 'subject' => 'Hostel',
            'menu_title' => 'Hostel', 'icon' => 'building-2', 'school_role' => 'Admin',
            'description' => 'Buildings, floors, rooms and allocation.',
            'master' => ['hostel_master', 'tenant'],
            'config' => ['hostel_room_master', 'tenant'],
            'data' => ['hostel_room_allocation', 'tenant_year'],
            'extras' => [
                ['master_hostel_type', 'Hostel type master', 'hostel_type_master', 'tenant'],
                ['master_building', 'Building master', 'hostel_building_master', 'tenant'],
                ['master_floor', 'Floor master', 'hostel_floor_master', 'tenant'],
            ],
        ],
        [
            'key' => 'library', 'name' => 'Library', 'subject' => 'Library',
            'menu_title' => 'Library', 'icon' => 'library-big', 'school_role' => 'Librarian',
            'description' => 'Item types, catalogue and circulation.',
            'master' => ['library_items', 'tenant'],
            'config' => ['library_books', 'tenant_year'],
            'data' => ['library_book_circulations', 'tenant_year'],
        ],
        [
            'key' => 'front_desk', 'name' => 'Front desk', 'subject' => 'Front desk',
            'menu_title' => 'Front Desk', 'icon' => 'bell-ring', 'school_role' => 'Clerk',
            'description' => 'Front-office desk, circulars and complaints.',
            'master' => ['front_desk', 'tenant'],
            'config' => ['circular', 'tenant_year'],
            'data' => ['complaint', 'tenant'],
        ],
        [
            'key' => 'visitor', 'name' => 'Visitor management', 'subject' => 'Visitor',
            'menu_title' => 'Visitor', 'icon' => 'id-card', 'school_role' => 'Security',
            'description' => 'Visitor types, gate settings and the visitor register.',
            'master' => ['visitor_type', 'tenant'],
            'config' => ['visitor_master_settings', 'tenant'],
            'data' => ['visitor_master', 'tenant'],
        ],
        [
            'key' => 'inward_outward', 'name' => 'Inward / outward', 'subject' => 'Inward-outward',
            'menu_title' => 'Inward-Outward', 'icon' => 'mail', 'school_role' => 'Clerk',
            'description' => 'Dispatch places, physical file locations and the inward register.',
            'master' => ['place_master', 'tenant'],
            'config' => ['physical_file_location', 'tenant'],
            'data' => ['inward', 'tenant_year'],
        ],
        [
            'key' => 'ptm', 'name' => 'Parent-teacher meeting', 'subject' => 'PTM',
            'menu_title' => 'PTM', 'icon' => 'calendar-check', 'school_role' => 'Teacher',
            'description' => 'Meeting slots and parent bookings.',
            'master' => ['ptm_time_slots_master', 'tenant_year'],
            'config' => null,
            'data' => ['ptm_booking_master', 'tenant'],
        ],
        [
            'key' => 'consent', 'name' => 'Consent', 'subject' => 'Consent',
            'menu_title' => 'Consent', 'icon' => 'file-check', 'school_role' => 'Admin',
            'description' => 'Consent forms issued to parents and their responses.',
            'master' => ['consent_master', 'tenant_year'],
            'config' => null,
            'data' => null,
        ],
        [
            'key' => 'communication', 'name' => 'Communication', 'subject' => 'Communication',
            'menu_title' => 'Communication', 'icon' => 'message-square', 'school_role' => 'Admin',
            'description' => 'SMS and WhatsApp credentials and the parent contact base.',
            'master' => ['sms_api_details', 'tenant'],
            'config' => ['whatapp_user_details', 'tenant'],
            'data' => null,
        ],
        [
            'key' => 'user', 'name' => 'Users & rights', 'subject' => 'User',
            'menu_title' => 'User', 'icon' => 'shield-check', 'school_role' => 'Admin',
            'description' => 'Staff profiles, role rights and user accounts.',
            'master' => ['tbluserprofilemaster', 'tenant'],
            'config' => ['tblgroupwise_rights', 'tenant'],
            'data' => ['tbluser', 'tenant'],
        ],
        [
            'key' => 'petty_cash', 'name' => 'Petty cash', 'subject' => 'Petty cash',
            'menu_title' => 'Petty Cash', 'icon' => 'wallet', 'school_role' => 'Accountant',
            'description' => 'Expense heads and the petty cash register.',
            'master' => ['petty_cash_master', 'tenant'],
            'config' => null,
            'data' => ['petty_cash', 'tenant'],
        ],
        [
            'key' => 'attendance', 'name' => 'Attendance', 'subject' => 'Attendance',
            'menu_title' => 'Attendance', 'icon' => 'calendar-days', 'school_role' => 'Teacher',
            'description' => 'Periods, timetable and daily student attendance capture.',
            'master' => ['period', 'tenant'],
            'config' => ['timetable', 'tenant_year'],
            'data' => ['student_capture_attendance', 'tenant_year'],
        ],

        // --- Modules added in the coverage pass -------------------------------
        // These menu groups exist in tblmenumaster but had no journey. Their menu
        // rows mostly leave `database_table` empty, so the proof tables below were
        // taken from the modules' own model/migration set and each was verified to
        // exist and carry a tenant column.
        [
            'key' => 'institute', 'name' => 'Institute profile', 'subject' => 'Institute',
            'menu_title' => 'Institute', 'icon' => 'building', 'school_role' => 'Admin',
            'description' => 'Institute detail, statutory identifiers and compliance records.',
            'master' => ['institute_detail', 'tenant'],
            'config' => null,
            'data' => null,
        ],
        [
            'key' => 'skill', 'name' => 'Skill & competency', 'subject' => 'Skill',
            'menu_title' => 'Skill', 'icon' => 'target', 'school_role' => 'Admin',
            'description' => 'Skill library, job roles and the tasks mapped to them.',
            'master' => ['master_skills', 'tenant'],
            'config' => ['s_jobrole', 'tenant'],
            'data' => ['s_jobrole_task', 'tenant'],
        ],
        [
            'key' => 'sqaa', 'name' => 'SQAA', 'subject' => 'SQAA',
            'menu_title' => 'SQAA', 'icon' => 'clipboard-check', 'school_role' => 'Admin',
            'description' => 'School quality assessment and accreditation evidence.',
            'master' => ['sqaa_master', 'tenant'],
            'config' => ['sqaa_documents', 'tenant'],
            'data' => ['sqaa_marks', 'tenant'],
        ],
        [
            'key' => 'donation', 'name' => 'Donation', 'subject' => 'Donation',
            'menu_title' => 'Donation', 'icon' => 'hand-coins', 'school_role' => 'Accountant',
            'description' => 'Donation heads and collection records.',
            'master' => ['donation_collection', 'tenant'],
            'config' => null,
            'data' => null,
        ],
        [
            'key' => 'counselling', 'name' => 'Career counselling', 'subject' => 'Counselling',
            'menu_title' => 'Career Counseling', 'icon' => 'compass', 'school_role' => 'Counseller',
            'description' => 'Counselling courses and the question bank behind the assessments.',
            'master' => ['counselling_course', 'tenant'],
            'config' => ['counselling_question_master', 'tenant'],
            'data' => null,
        ],
        [
            'key' => 'homework', 'name' => 'Homework', 'subject' => 'Homework',
            'menu_title' => 'Homework', 'icon' => 'notebook-pen', 'school_role' => 'Teacher',
            'description' => 'Homework assigned to classes and tracked against the academic year.',
            'master' => ['homework', 'tenant_year'],
            'config' => null,
            'data' => null,
        ],
        [
            // No menu_title: `calendar_events` is surfaced under the "Mobile App"
            // group, which is a delivery channel rather than a module. Left
            // unmapped so the journey does not claim ownership of that group; the
            // steps still derive, they just carry no deep link.
            'key' => 'calendar', 'name' => 'Academic calendar', 'subject' => 'Calendar',
            'menu_title' => null, 'icon' => 'calendar-range', 'school_role' => 'Admin',
            'description' => 'Term dates, holidays and events published to the school calendar.',
            'master' => ['calendar_events', 'tenant_year'],
            'config' => null,
            'data' => null,
        ],
    ];

    public function run()
    {
        $sort = 0;

        foreach (self::MODULES as $definition) {
            $sort += 10;

            $module = OnboardingModuleModel::updateOrCreate(
                ['module_key' => $definition['key'], 'sub_institute_id' => 0],
                [
                    'module_name' => $definition['name'],
                    'menu_title' => $definition['menu_title'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'sort_order' => $sort,
                    'status' => 1,
                ]
            );

            $menus = $this->menuIndex($definition['menu_title']);
            $stepSort = 0;

            foreach (self::SPINE as $spine) {
                $stepSort += 10;
                $proof = $spine['proof'] ? ($definition[$spine['proof']] ?? null) : null;

                // A module with no table for this slot (e.g. Consent has no
                // separate config store) degrades to a manual sign-off rather
                // than disappearing, so every module keeps the same spine.
                $proofType = $proof ? 'table_rows' : 'manual';
                $menu = $proof ? ($menus[$proof[0]] ?? null) : null;

                OnboardingStepModel::updateOrCreate(
                    ['module_id' => $module->id, 'step_key' => $spine['key']],
                    [
                        'title' => str_contains($spine['title'], '%s')
                            ? sprintf($spine['title'], $definition['subject'])
                            : $spine['title'],
                        'description' => $spine['description'],
                        'sort_order' => $stepSort,
                        'is_required' => 1,
                        'status' => 1,
                        'owner' => $spine['owner'],
                        'triz_role' => $spine['triz_role'],
                        'school_role' => $definition['school_role'],
                        'proof_type' => $proofType,
                        'proof_table' => $proof[0] ?? null,
                        'proof_scope' => $proof[1] ?? 'tenant',
                        'proof_min_rows' => 1,
                        'proof_conditions' => null,
                        'action_route' => $menu->link ?? null,
                        'action_menu_id' => $menu->id ?? null,
                        'help_youtube_link' => $menu->youtube_link ?? null,
                        'help_pdf_link' => $menu->pdf_link ?? null,
                    ]
                );

                // Extra master steps sit immediately after the main master step,
                // between sort_order 21..29, so they slot in ahead of
                // configuration (30) without renumbering the rest of the spine.
                if ($spine['key'] !== 'master_setup') {
                    continue;
                }

                $extraSort = $stepSort;
                foreach ($definition['extras'] ?? [] as $extra) {
                    [$extraKey, $extraTitle, $extraTable, $extraScope] = $extra;
                    $extraOwner = $extra[4] ?? 'SCHOOL';
                    $extraMenu = $menus[$extraTable] ?? null;
                    $extraSort++;

                    OnboardingStepModel::updateOrCreate(
                        ['module_id' => $module->id, 'step_key' => $extraKey],
                        [
                            'title' => $extraTitle,
                            'description' => 'Set up the ' . lcfirst($extraTitle)
                                . ' records this module depends on.',
                            'sort_order' => $extraSort,
                            'is_required' => 1,
                            'status' => 1,
                            'owner' => $extraOwner,
                            'triz_role' => $extraOwner === 'TRIZ'
                                ? 'Implementation Consultant'
                                : 'Implementation Consultant',
                            'school_role' => $definition['school_role'],
                            'proof_type' => 'table_rows',
                            'proof_table' => $extraTable,
                            'proof_scope' => $extraScope,
                            'proof_min_rows' => 1,
                            'proof_conditions' => null,
                            'action_route' => $extraMenu->link ?? null,
                            'action_menu_id' => $extraMenu->id ?? null,
                            'help_youtube_link' => $extraMenu->youtube_link ?? null,
                            'help_pdf_link' => $extraMenu->pdf_link ?? null,
                        ]
                    );
                }
            }
        }

        $this->syncFromMenuMaster();
    }

    /**
     * Discover every remaining module straight from `tblmenumaster`.
     *
     * The curated registry above exists only to give well-known modules better
     * proof tables than the menu data can supply — several groups leave
     * `database_table` empty. It is NOT the list of modules: any `menu_title`
     * group the ERP has and the registry does not gets a journey generated here,
     * so the onboarding section reflects whatever modules actually exist rather
     * than a fixed list someone has to remember to extend.
     *
     * Proof tables are taken from the group's own menu rows — MASTER rows supply
     * the setup steps, ENTRY rows the data step — and only when the table really
     * exists and carries a tenant column. A table already claimed by another
     * module is skipped, since two modules proving themselves from one table
     * would be ambiguous. Nothing usable simply leaves the step manual.
     */
    private function syncFromMenuMaster(): void
    {
        $curatedTitles = array_filter(array_column(self::MODULES, 'menu_title'));

        $titles = DB::table('tblmenumaster')
            ->where('status', 1)
            ->whereNotNull('menu_title')
            ->where('menu_title', '<>', '')
            ->distinct()
            ->orderBy('menu_title')
            ->pluck('menu_title')
            ->reject(fn ($title) => in_array($title, $curatedTitles, true))
            ->values();

        if ($titles->isEmpty()) {
            return;
        }

        // Tables already proving a step elsewhere, so they are not claimed twice.
        $claimed = OnboardingStepModel::whereNotNull('proof_table')
            ->pluck('proof_table')->unique()->flip();

        $sort = 1000;

        foreach ($titles as $title) {
            $sort += 10;

            $module = OnboardingModuleModel::updateOrCreate(
                ['module_key' => $this->moduleKey($title), 'sub_institute_id' => 0],
                [
                    'module_name' => $title,
                    'menu_title' => $title,
                    'description' => 'Set up the ' . $title . ' module for your institute.',
                    'icon' => 'layout-grid',
                    'sort_order' => $sort,
                    'status' => 1,
                ]
            );

            $rows = DB::table('tblmenumaster')
                ->select('id', 'name', 'link', 'menu_type', 'database_table', 'youtube_link', 'pdf_link')
                ->where('menu_title', $title)
                ->where('status', 1)
                ->orderBy('sort_order')
                ->get();

            $byTable = [];
            foreach ($rows as $row) {
                if (! empty($row->database_table)) {
                    $byTable[$row->database_table] ??= $row;
                }
            }

            // MASTER rows first — those are the setup screens — then ENTRY.
            $pick = function (array $types) use ($rows, &$claimed) {
                foreach ($rows as $row) {
                    $table = $row->database_table;
                    if (empty($table) || ! in_array($row->menu_type, $types, true)) {
                        continue;
                    }
                    if (isset($claimed[$table]) || ! $this->tableUsable($table)) {
                        continue;
                    }
                    $claimed[$table] = true;

                    return [$table, $this->tableScope($table)];
                }

                return null;
            };

            $slots = [
                'master' => $pick(['MASTER']) ?? $pick(['ENTRY']),
                'config' => $pick(['MASTER']),
                'data' => $pick(['ENTRY']),
            ];

            $stepSort = 0;
            foreach (self::SPINE as $spine) {
                $stepSort += 10;
                $proof = $spine['proof'] ? ($slots[$spine['proof']] ?? null) : null;
                $menu = $proof ? ($byTable[$proof[0]] ?? null) : null;

                OnboardingStepModel::updateOrCreate(
                    ['module_id' => $module->id, 'step_key' => $spine['key']],
                    [
                        'title' => str_contains($spine['title'], '%s')
                            ? sprintf($spine['title'], $title)
                            : $spine['title'],
                        'description' => $spine['description'],
                        'sort_order' => $stepSort,
                        'is_required' => 1,
                        'status' => 1,
                        'owner' => $spine['owner'],
                        'triz_role' => $spine['triz_role'],
                        'school_role' => 'Admin',
                        'proof_type' => $proof ? 'table_rows' : 'manual',
                        'proof_table' => $proof[0] ?? null,
                        'proof_scope' => $proof[1] ?? 'tenant',
                        'proof_min_rows' => 1,
                        'proof_conditions' => null,
                        'action_route' => $menu->link ?? null,
                        'action_menu_id' => $menu->id ?? null,
                        'help_youtube_link' => $menu->youtube_link ?? null,
                        'help_pdf_link' => $menu->pdf_link ?? null,
                    ]
                );
            }
        }
    }

    /** Stable slug for a menu group, used as the API and frontend route key. */
    private function moduleKey(string $title): string
    {
        $key = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $title), '_'));

        return $key === '' ? 'module_' . substr(md5($title), 0, 8) : substr($key, 0, 60);
    }

    /** Cached column lookup — the sync asks about the same tables repeatedly. */
    private array $tableColumns = [];

    private function columnsOf(string $table): array
    {
        if (! isset($this->tableColumns[$table])) {
            $this->tableColumns[$table] = count(DB::select("SHOW TABLES LIKE '" . $table . "'")) === 0
                ? []
                : array_map(
                    static fn ($c) => strtolower($c->Field),
                    DB::select("SHOW COLUMNS FROM `{$table}`")
                );
        }

        return $this->tableColumns[$table];
    }

    /** A table can only prove a step if it exists and is tenant-scoped. */
    private function tableUsable(string $table): bool
    {
        return in_array('sub_institute_id', $this->columnsOf($table), true);
    }

    private function tableScope(string $table): string
    {
        $columns = $this->columnsOf($table);

        foreach (['syear', 'academic_year', 'academic_yr'] as $year) {
            if (in_array($year, $columns, true)) {
                return 'tenant_year';
            }
        }

        return 'tenant';
    }

    /**
     * Menu rows for this module keyed by the table they own, so a step can be
     * linked to the real screen the user must visit. Reuses the existing
     * `tblmenumaster.database_table` mapping rather than inventing a new one.
     */
    private function menuIndex(?string $menuTitle): array
    {
        if (! $menuTitle) {
            return [];
        }

        $rows = DB::table('tblmenumaster')
            ->select('id', 'link', 'database_table', 'youtube_link', 'pdf_link')
            ->where('menu_title', $menuTitle)
            ->where('status', 1)
            ->whereNotNull('database_table')
            ->where('database_table', '<>', '')
            ->orderBy('sort_order')
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[$row->database_table] ??= $row;
        }

        return $index;
    }
}
