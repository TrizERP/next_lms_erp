<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Modules\ModuleReadTools;
use App\Domain\AI\Templates\ReportDataSourceCatalog;
use App\Domain\AI\Workspace\RouteMatcher;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\UserIcardService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Module isolation for the twenty-three AI Stacks added in 2026-09: Exam, PTM, Hostel,
 * Student Request, Circular, Users Mobile Apps, Student I-Card, Certificate, Communication,
 * Time Table, Student Medical, Inward, User I-Card, Petty Cash, Consent, Visitor Management
 * Transport, Inventory, Front Desk, Task Management, Complaint, Utility and Document
 * Templates.
 *
 * WHAT THIS TEST IS FOR
 *
 * The whole promise of a per-module AI Stack is that a question asked in one module is
 * answered from that module's records and no other's. That promise is kept in three
 * places, and all three are asserted here:
 *
 *   1. The tool bindings in `config/ai.php`. A module can only read what it binds, so a
 *      binding naming another module's tool IS the leak — nothing downstream would catch
 *      it, because a correctly-scoped tool returning another module's data is exactly what
 *      it was asked for.
 *   2. The `read_only` annotations. These tools are reachable from a report layout, from
 *      the conversational fallback and from an agent allow-list without anybody naming
 *      one, so a write tool among them would mean reading a screen changed a record.
 *   3. The route patterns. `/students/requests` has to resolve to the Student Request
 *      module and not to the Students module whose `/students/**` also matches it, and
 *      `/student/student_infirmary` has to resolve to Student Medical for the same reason.
 *
 * NO DATABASE. Everything asserted here is configuration and code, which is deliberate:
 * this must keep passing on a machine that cannot reach the estate, and the properties it
 * checks are true or false regardless of what any school has recorded. Cross-institute
 * scoping is enforced inside each service by `$context->selectedInstituteId` and is
 * exercised by `check_module_ai_isolation.php` where a database is available.
 */
class ModuleAiStackIsolationTest extends TestCase
{
    /**
     * The modules this test is about, and the tool prefix each one owns.
     *
     * Twenty-six of them, so the matrix below is 676 cells rather than 25. That is the
     * point: the more modules share one AI Stack, the more places a binding could reach
     * across, and the check has to grow with them.
     *
     * SEVEN KEYS DO NOT MATCH THEIR TOOL PREFIX, AND ALL SEVEN ARE DELIBERATE
     *
     * `easy_com` owns `communication.`, `inward_outward` owns `inward.`,
     * `visitor_management` owns `visitor.`, `transportation` owns `transport.`,
     * `task_management` owns `tasks.`, `migration-modules` owns `utility.` and
     * `document-templates` owns `doc_templates.`. In each case the key is what
     * `ai_modules` has called that module since the workspace was seeded and the prefix is
     * what its tools are named.
     *
     * Two of those keys carry a HYPHEN — `migration-modules` and `document-templates` —
     * which is worth stating because everything downstream builds from the key verbatim.
     * `migration-modules` is the UTILITY module: in this ERP, Utility is bulk data
     * operations and not electricity or water, which this estate records nowhere. Both are correct, and the
     * mismatch is declared here rather than tidied away — tidying it would mean renaming
     * either a live module key or a set of registered tools.
     *
     * It is also exactly the kind of mismatch that hides a leak, because a prefix check
     * that assumed key == prefix would pass every one of these four vacuously. The map
     * below is what stops that.
     *
     * @var array<string, string>
     */
    private const MODULES = [
        'exam' => 'exams.',
        'ptm' => 'ptm.',
        'hostel' => 'hostel.',
        'student_request' => 'student_requests.',
        'circular' => 'circulars.',
        'mobile_apps' => 'mobile_apps.',
        'student_icard' => 'student_icard.',
        'certificate' => 'certificate.',
        'easy_com' => 'communication.',
        'timetable' => 'timetable.',
        'student_medical' => 'student_medical.',
        'inward_outward' => 'inward.',
        'user_icard' => 'user_icard.',
        'petty_cash' => 'petty_cash.',
        'consent' => 'consent.',
        'visitor_management' => 'visitor.',
        'transportation' => 'transport.',
        'inventory' => 'inventory.',
        'front_desk' => 'front_desk.',
        'task_management' => 'tasks.',
        'complaint' => 'complaints.',
        'migration-modules' => 'utility.',
        'document-templates' => 'doc_templates.',
        'parent_communication' => 'parent_communication.',
        'sqaa' => 'sqaa.',
        'library' => 'library.',
    ];

    /**
     * Tools every module may bind, because they are about the AI layer or the shape of
     * the school rather than about one module's records.
     *
     * `academics.structure` returns standards and divisions; `students.directory` and
     * `students.search` return who is enrolled and where they sit. A module that groups
     * its records by class needs those, and needing them is not a leak — what would be a
     * leak is Exam binding `ptm.bookings`, which is what the matrix below forbids.
     *
     * `student_request`, `student_icard` and `student_medical` deliberately bind none of
     * the student tools: a person reading a request queue, printing a card or running an
     * infirmary has not thereby been given the student directory.
     *
     * @var array<int, string>
     */
    private const SHARED_TOOLS = [
        'ai.templates.list',
        'ai.templates.render',
        'ai.templates.generate',
        'academics.structure',
        'academics.subjects',
        'academics.class_teachers',
        'students.directory',
        'students.search',
        'students.history',
        'teachers.directory',
    ];

    /**
     * Modules that must bind NOTHING outside their own prefix and the AI template tools.
     *
     * Most modules legitimately need the shape of the school. These must not have it:
     *
     *   · `student_medical` holds clinical records about children, and a binding that let
     *     a medical question also read the student directory would turn the most
     *     restricted module in the product into a route through the school.
     *   · `user_icard` sits on `tbluser`, which carries payroll, bank and government
     *     identity numbers beside the six fields a card prints. Its two tools return an
     *     explicit column list; binding anything wider would be a second way in.
     *   · `petty_cash`, `inward_outward`, `visitor_management` and `transportation` simply
     *     do not need the school's shape to answer their own questions, and a module that
     *     does not need a tool should not hold one. Transport names the students on a bus
     *     from its own mapping table, which is why `students.directory` is absent: a
     *     transport question must not be able to walk the whole roll.
     *
     * `consent` and `task_management` are NOT in this list, and both are deliberate.
     * `consent` binds `academics.structure` so a question naming a class resolves to a
     * standard id, and `task_management` binds `teachers.directory` because a task is
     * allocated to a person and that is the only lookup a task question reaches for.
     * Neither is a child's record.
     *
     * @var array<int, string>
     */
    private const OWN_TOOLS_ONLY = [
        'student_medical',
        'user_icard',
        'petty_cash',
        'inward_outward',
        'visitor_management',
        'transportation',
        // Added 2026-09-25. Inventory needs none of the school's shape to describe a
        // store; Front Desk names the one student a visit concerns and must not be able
        // to walk the roll; Complaint is about the people handling it; Utility operates
        // on structures rather than on children; and the template register is text.
        'inventory',
        'front_desk',
        'complaint',
        'migration-modules',
        'document-templates',
        // Added 2026-09-26. A parent's letter names one child and the service joins
        // that child; SQAA is about documents; the library is about books. None of them
        // needs the school's shape, and a module that does not need a tool should not
        // hold one.
        'parent_communication',
        'sqaa',
        'library',
    ];

    /**
     * Every module's route patterns, as the registration migrations write them.
     *
     * Declared once because three tests read them, and because a pattern list that drifts
     * from the migration would assert something no estate actually has.
     *
     * @return array<string, array<int, string>>
     */
    private function routePatterns(): array
    {
        return [
            'exam' => ['/exam', '/exam/**', '/result', '/result/**', '/modules/exam', '/modules/exam/**'],
            'ptm' => [
                '/modules/ptm', '/modules/ptm/**',
                '/admin-services/ptm-attended-status', '/admin-services/ptm-report',
                '/admin-services/ptm-time-slot-master', '/admin-services/ptm-ai-stack',
            ],
            'hostel' => ['/hostel', '/hostel/**', '/modules/hostel', '/modules/hostel/**'],
            'student_request' => [
                '/modules/student-request', '/modules/student-request/**',
                '/students/requests', '/students/requests/**', '/student/report/student_request_report',
            ],
            'circular' => [
                '/modules/circular', '/modules/circular/**',
                '/front_desk/circular', '/front_desk/circular/**',
            ],
            'mobile_apps' => ['/modules/mobile-apps', '/modules/mobile-apps/**', '/mobile-apps', '/mobile-apps/**'],
            'student_icard' => [
                '/modules/student-i-card', '/modules/student-i-card/**',
                '/student/student_icard', '/student/student_icard/**', '/student/my_icard',
            ],
            'certificate' => [
                '/modules/certificate', '/modules/certificate/**',
                '/student/student_certificate', '/student/student_certificate/**',
                '/student/student_certificate_report',
            ],
            'easy_com' => ['/easy_com', '/easy_com/**', '/modules/communication', '/modules/communication/**'],
            'timetable' => [
                '/modules/timetable', '/modules/timetable/**',
                '/front_desk/create-timetable', '/front_desk/classwisetimetable', '/front_desk/facultywisetimetable',
            ],
            'student_medical' => [
                '/modules/student-medical', '/modules/student-medical/**',
                '/student/student_infirmary', '/student/student_vaccination',
                '/student/student_hw', '/student/student_health',
                '/student/report/student_health_report',
            ],
            'inward_outward' => [
                '/inward_outward', '/inward_outward/**',
                '/modules/inward-outward', '/modules/inward-outward/**',
            ],
            'user_icard' => [
                '/modules/user-i-card', '/modules/user-i-card/**',
                // Named one page at a time, each more specific than the Students module's
                // `/student/**`. `/student/student_icard` belongs to the separate Student
                // I-Card module and is deliberately absent.
                '/student/user_icard', '/student/user_icard/**',
                '/student/teacher_icard', '/student/teacher_icard/**',
            ],
            'petty_cash' => [
                '/modules/petty-cash', '/modules/petty-cash/**',
                '/admin-services/petty-cash', '/admin-services/petty-cash-master',
                '/admin-services/petty-cash-report', '/admin-services/petty-cash-ai-stack',
            ],
            'consent' => [
                '/modules/consent', '/modules/consent/**',
                '/admin-services/consent-master', '/admin-services/consent-report',
                '/admin-services/delete-consent-master', '/admin-services/consent-ai-stack',
            ],
            'visitor_management' => [
                '/modules/visitor-management', '/modules/visitor-management/**',
                '/admin-services/add-visitor', '/admin-services/visitor-report',
                '/admin-services/visitor-ai-stack',
                // NOT /hostel/visitor-details or /hostel/visitor-report. Those are the
                // Hostel module's own screens over its own table.
            ],
            'transportation' => ['/Transportation', '/Transportation/**', '/modules/transport', '/modules/transport/**'],
            'inventory' => ['/Inventory', '/Inventory/**', '/modules/inventory', '/modules/inventory/**'],
            // `/front_desk/circular` and `/front_desk/create-timetable` are NOT here: they
            // belong to Circular and Time Table, which name them literally and so beat this
            // module's wildcard.
            'front_desk' => ['/front_desk', '/front_desk/**', '/modules/front-desk', '/modules/front-desk/**'],
            'task_management' => [
                '/modules/task-management', '/modules/task-management/**',
                '/task-management', '/task-management/**',
            ],
            'complaint' => [
                '/modules/complaint', '/modules/complaint/**',
                '/admin-services/complaint-management', '/admin-services/complaint-report',
                '/admin-services/complaint-ai-stack',
            ],
            // The Utility module. `/Utility/**` has been on this row since the workspace
            // was seeded.
            'migration-modules' => [
                '/migration-modules', '/migration-modules/**',
                '/Utility', '/Utility/**',
                '/modules/utility', '/modules/utility/**',
            ],
            'document-templates' => [
                '/document-templates', '/document-templates/**',
                '/modules/document-templates', '/modules/document-templates/**',
            ],
            // Added 2026-09-26. Parent Communication sits inside the front_desk tree and
            // names its page literally, the way Circular and Time Table do.
            'parent_communication' => [
                '/modules/parent-communication', '/modules/parent-communication/**',
                '/front_desk/parent_communication', '/front_desk/parent_communication/**',
            ],
            'sqaa' => [
                '/sqaa', '/sqaa/**', '/sqaa_master', '/sqaa_master/**',
                '/modules/sqaa', '/modules/sqaa/**',
                '/modules/sqaa-report', '/modules/sqaa-report/**',
            ],
            'library' => [
                '/library', '/library/**', '/modules/library', '/modules/library/**',
                '/modules/library-report', '/modules/library-report/**',
            ],
            // The modules that already had an AI Stack, so a new pattern cannot have
            // quietly taken one of their routes.
            'students' => ['/students', '/students/**', '/student', '/student/**', '/modules/student', '/modules/student/**'],
            'admissions' => ['/admissions', '/admissions/**', '/modules/admission', '/modules/admission/**'],
            'attendance' => ['/attendance', '/attendance/**', '/modules/attendance', '/modules/attendance/**'],
            'fees' => ['/fees', '/fees/**'],
        ];
    }

    public function test_every_module_is_registered_with_its_own_tools(): void
    {
        $registry = app(ModuleRegistry::class);

        foreach (array_keys(self::MODULES) as $key) {
            $module = $registry->find($key);

            $this->assertNotNull($module, "The {$key} module resolves to nothing, so its AI Stack has no bindings.");
            $this->assertNotEmpty(
                $module->mcpTools,
                "The {$key} module binds no tools, so every question asked in it would be answered from nothing."
            );
        }
    }

    /**
     * The full module-by-module matrix, expressed as one assertion per leak.
     *
     * Exam -> Exam passes; Exam -> every other module is blocked, and so on for all
     * eleven. "Blocked" here means the binding does not name the tool at all — stronger
     * than a filter, because a tool a module never binds cannot be selected by the
     * planner, cannot be bound to a report layout and cannot appear in its data-source
     * picker.
     */
    public function test_no_module_binds_another_target_modules_tools(): void
    {
        $registry = app(ModuleRegistry::class);
        $leaks = [];
        $ownership = [];

        foreach (self::MODULES as $key => $prefix) {
            $module = $registry->find($key);
            $own = [];

            foreach ($module?->mcpTools ?? [] as $tool) {
                if (in_array($tool, self::SHARED_TOOLS, true)) {
                    continue;
                }

                foreach (self::MODULES as $otherKey => $otherPrefix) {
                    if (str_starts_with($tool, $otherPrefix)) {
                        if ($otherKey === $key) {
                            $own[] = $tool;
                        } else {
                            $leaks[] = sprintf('%s binds "%s", which belongs to %s', $key, $tool, $otherKey);
                        }
                    }
                }
            }

            $ownership[$key] = $own;
        }

        $this->assertSame([], $leaks, implode("\n", $leaks));

        // The other half of the same claim: each module actually binds its own tools, so
        // "no leaks" is not passing because nothing is bound at all.
        foreach ($ownership as $key => $own) {
            $this->assertNotEmpty(
                $own,
                "The {$key} module binds none of its own tools, so it could not answer a question about itself."
            );
        }
    }

    /**
     * The modules that must reach nothing but their own records.
     *
     * A stricter check than the matrix above, for the module where even a legitimate
     * shared read would be wrong. See `OWN_TOOLS_ONLY`.
     */
    public function test_the_most_restricted_modules_bind_only_their_own_tools(): void
    {
        $registry = app(ModuleRegistry::class);
        $templateTools = ['ai.templates.list', 'ai.templates.render', 'ai.templates.generate'];
        $problems = [];

        foreach (self::OWN_TOOLS_ONLY as $key) {
            $prefix = self::MODULES[$key];

            foreach ($registry->find($key)?->mcpTools ?? [] as $tool) {
                if (str_starts_with($tool, $prefix) || in_array($tool, $templateTools, true)) {
                    continue;
                }

                $problems[] = sprintf('%s binds "%s", which is not its own and not a template tool', $key, $tool);
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Student Medical's tools belong to Student Medical and to nothing else.
     *
     * The reverse of the check above, and the one that actually matters for a clinical
     * record: it is not enough that the medical module reads nothing else — no other
     * module may read the medical tools. Asserted across every module the lifecycle knows,
     * not only the eleven in this file's list.
     */
    public function test_no_module_anywhere_can_reach_the_student_medical_tools(): void
    {
        $registry = app(ModuleRegistry::class);
        $leaks = [];

        foreach ($registry->all() as $key => $module) {
            if ($key === 'student_medical') {
                continue;
            }

            foreach ($module->mcpTools as $tool) {
                if (str_starts_with($tool, 'student_medical.')) {
                    $leaks[] = sprintf('%s binds "%s"', $key, $tool);
                }
            }
        }

        $this->assertSame(
            [],
            $leaks,
            "A module other than student_medical can read a child's clinical record:\n".implode("\n", $leaks)
        );
    }

    /**
     * Every tool these modules bind is read-only.
     *
     * Not a list of names — the tool's own `read_only` annotation, which is what
     * `ModuleReadTools` and `ReportDataSourceCatalog` filter on. A tool that gained a write
     * path later would fail here without anybody remembering to update a list.
     */
    public function test_every_tool_these_modules_bind_is_read_only(): void
    {
        $registry = app(ModuleRegistry::class);
        $tools = app(ToolRegistry::class);
        $writers = [];

        foreach (array_keys(self::MODULES) as $key) {
            foreach ($registry->find($key)?->mcpTools ?? [] as $name) {
                $tool = $tools->tool($name);

                $this->assertNotNull($tool, "{$key} binds \"{$name}\", which is not registered.");

                if (($tool->definition()['annotations']['read_only'] ?? false) !== true) {
                    $writers[] = sprintf('%s binds "%s", which is not read-only', $key, $name);
                }
            }
        }

        $this->assertSame([], $writers, implode("\n", $writers));
    }

    /**
     * Each module's report data-source picker offers that module's tools and no other's.
     *
     * `ReportDataSourceCatalog::forModule()` matches loosely on singular/plural and
     * separators, which is what lets the `exam` module find the `exams.` tools. This
     * asserts that the looseness does not reach across modules — `student_request`,
     * `student_icard`, `student_medical` and `students` all begin with the same word and
     * must not be confused for one another.
     *
     * `easy_com` is the case that found a real hole. Its tools are named `communication.`,
     * so the catalogue's loose match cannot pair them with the key, and the fallback used
     * to be the WHOLE catalogue — which offered `fees.arrears` as a data source for a
     * Communication layout. `forModule()` now falls back to the module's own declared
     * bindings instead, so the assertion below is the same for every module: a picker may
     * offer this module's tools and the shared ones, and nothing else.
     */
    public function test_each_modules_report_sources_are_its_own(): void
    {
        $catalog = app(ReportDataSourceCatalog::class);
        $problems = [];

        foreach (self::MODULES as $key => $prefix) {
            $sources = array_column($catalog->forModule($key), 'name');

            $this->assertNotEmpty($sources, "The {$key} module offers no report data source.");

            foreach ($sources as $name) {
                if (str_starts_with($name, $prefix) || in_array($name, self::SHARED_TOOLS, true)) {
                    continue;
                }

                $problems[] = sprintf('%s offers "%s" as a report source', $key, $name);
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * No module's picker offers another target module's tools.
     *
     * The check above allows the shared tools, which is right — a report grouped by class
     * needs `academics.structure`. This one closes the gap that allowance leaves: shared
     * or not, a picker must never offer a tool belonging to one of the other modules in
     * this file, because binding a layout to it is how a Communication report ends up
     * rendering fee records.
     */
    public function test_no_modules_picker_offers_another_modules_tools(): void
    {
        $catalog = app(ReportDataSourceCatalog::class);
        $leaks = [];

        foreach (self::MODULES as $key => $prefix) {
            foreach (array_column($catalog->forModule($key), 'name') as $name) {
                foreach (self::MODULES as $otherKey => $otherPrefix) {
                    if ($otherKey !== $key && str_starts_with($name, $otherPrefix)) {
                        $leaks[] = sprintf('%s offers "%s", which belongs to %s', $key, $name, $otherKey);
                    }
                }
            }
        }

        $this->assertSame([], $leaks, implode("\n", $leaks));
    }

    /**
     * A page-level question in each module reaches at least one usable read tool.
     *
     * `ModuleReadTools::select()` skips a tool with a required argument, because a page has
     * no id to fill one with. `student_requests.details` and `student_icard.card_details`
     * both require an id and are correctly skipped; if either were the only tool bound,
     * the module would have an AI Stack that could never answer anything from the page it
     * sits on.
     */
    public function test_each_module_has_a_read_tool_a_page_level_question_can_use(): void
    {
        $registry = app(ModuleRegistry::class);
        $reads = app(ModuleReadTools::class);

        foreach (array_keys(self::MODULES) as $key) {
            $usable = $reads->select($registry->find($key)?->mcpTools ?? [], 3);

            $this->assertNotEmpty(
                $usable,
                "No tool bound to {$key} can be called without an argument, so a question asked on one of its "
                    .'pages could not be answered.'
            );
        }
    }

    /**
     * The pages that live under another module's tree resolve to their own module.
     *
     * Five modules' screens sit inside a tree another module claims with a wildcard:
     * I-Card, Certificate and Student Medical under `/student/**`, Student Request under
     * `/students/**`, and Time Table under `/front_desk/**`. `RouteMatcher::best()` scores
     * literal segments above wildcards, which is the whole reason the specific patterns
     * can be added without removing anything from the other module — but it is the kind of
     * property that is true until somebody edits a pattern, so it is asserted.
     */
    public function test_pages_inside_another_modules_tree_resolve_to_their_own_module(): void
    {
        $matcher = new RouteMatcher();
        $patterns = $this->routePatterns();

        $cases = [
            'student_request' => ['/students/requests', '/students/requests/ai-stack', '/students/requests/new'],
            'student_icard' => ['/student/student_icard', '/student/student_icard/ai-stack'],
            'certificate' => ['/student/student_certificate', '/student/student_certificate/ai-stack'],
            'student_medical' => ['/student/student_infirmary', '/student/student_vaccination', '/student/student_hw'],
            'timetable' => ['/front_desk/create-timetable', '/front_desk/classwisetimetable'],
            // The staff card, under the same `/student/**` tree as the child's card — and
            // a different module from it.
            'user_icard' => ['/student/user_icard', '/student/user_icard/ai-stack', '/student/teacher_icard'],
        ];

        $wildcardOwner = [
            'student_request' => 'students',
            'student_icard' => 'students',
            'certificate' => 'students',
            'student_medical' => 'students',
            'timetable' => 'circular',
            'user_icard' => 'students',
        ];

        foreach ($cases as $owner => $routes) {
            foreach ($routes as $route) {
                $mine = $matcher->best($patterns[$owner], $route);

                $this->assertTrue($mine['matched'], "{$owner} does not match {$route}.");

                // The module whose wildcard also covers this path must lose it. Time Table
                // is checked against Circular, which is the other `/front_desk/**` holder
                // in this list.
                $rival = $matcher->best($patterns[$wildcardOwner[$owner]], $route);

                if ($rival['matched']) {
                    $this->assertGreaterThan(
                        $rival['specificity'],
                        $mine['specificity'],
                        "{$wildcardOwner[$owner]} would win {$route}, which belongs to {$owner}."
                    );
                }
            }
        }

        // And the reverse: the student modules must not claim a general student page.
        foreach (['student_request', 'student_icard', 'certificate', 'student_medical'] as $owner) {
            foreach (['/students', '/student', '/modules/student/ai-stack'] as $route) {
                $this->assertFalse(
                    $matcher->best($patterns[$owner], $route)['matched'],
                    "{$owner} claims {$route}, which belongs to the Students module."
                );
            }
        }
    }

    /**
     * PTM names the three Admin Services pages it owns, and claims nothing else there.
     *
     * Admin Services is a module of its own with visitors, complaints, consent and petty
     * cash under it. A wildcard would have routed all of those to PTM.
     */
    public function test_ptm_does_not_claim_the_whole_admin_services_tree(): void
    {
        $matcher = new RouteMatcher();
        $ptmPatterns = $this->routePatterns()['ptm'];

        foreach (['/admin-services/ptm-report', '/admin-services/ptm-ai-stack', '/modules/ptm/ai-stack'] as $route) {
            $this->assertTrue($matcher->best($ptmPatterns, $route)['matched'], "PTM does not match {$route}.");
        }

        foreach ([
            '/admin-services',
            '/admin-services/visitor-report',
            '/admin-services/complaint-management',
            '/admin-services/petty-cash',
        ] as $route) {
            $this->assertFalse(
                $matcher->best($ptmPatterns, $route)['matched'],
                "PTM claims {$route}, which belongs to the Admin Services module."
            );
        }
    }

    /**
     * Users Mobile Apps claims none of its own level-3 menu targets.
     *
     * The module's menus point at Calendar, Photo Gallery, Leave Application and Exam
     * Schedule — pages the front_desk and students modules own. Claiming them would make a
     * question asked on the calendar resolve to the app configuration module, which is
     * both wrong and the exact shape of a cross-module leak.
     */
    public function test_mobile_apps_claims_no_other_modules_pages(): void
    {
        $matcher = new RouteMatcher();
        $patterns = $this->routePatterns()['mobile_apps'];

        foreach (['/modules/mobile-apps/ai-stack', '/mobile-apps/ai-stack'] as $route) {
            $this->assertTrue($matcher->best($patterns, $route)['matched'], "Mobile Apps does not match {$route}.");
        }

        foreach ([
            '/front_desk/calendar',
            '/front_desk/photo_video_gallary',
            '/front_desk/exam_schedule',
            '/students/leave',
        ] as $route) {
            $this->assertFalse(
                $matcher->best($patterns, $route)['matched'],
                "Users Mobile Apps claims {$route}, which belongs to another module."
            );
        }
    }

    /**
     * Each AI Stack category route resolves to its own module and no other.
     *
     * One `/modules/<slug>/ai-stack` route per module, checked against every other
     * module's pattern list. `/modules/student-request/…`, `/modules/student-medical/…`
     * and `/modules/student/…` are different segments, which `RouteMatcher` compares
     * literally — but "student-medical starts with student" is exactly the mistake a
     * reader makes, so it is asserted.
     */
    public function test_each_ai_stack_route_resolves_to_one_module_only(): void
    {
        $matcher = new RouteMatcher();
        $patterns = $this->routePatterns();

        $routes = [
            'exam' => '/modules/exam/ai-stack',
            'ptm' => '/modules/ptm/ai-stack',
            'hostel' => '/modules/hostel/ai-stack',
            'student_request' => '/modules/student-request/ai-stack',
            'circular' => '/modules/circular/ai-stack',
            'mobile_apps' => '/modules/mobile-apps/ai-stack',
            'student_icard' => '/modules/student-i-card/ai-stack',
            'certificate' => '/modules/certificate/ai-stack',
            'easy_com' => '/modules/communication/ai-stack',
            'timetable' => '/modules/timetable/ai-stack',
            'student_medical' => '/modules/student-medical/ai-stack',
            'inward_outward' => '/modules/inward-outward/ai-stack',
            'user_icard' => '/modules/user-i-card/ai-stack',
            'petty_cash' => '/modules/petty-cash/ai-stack',
            'consent' => '/modules/consent/ai-stack',
            'visitor_management' => '/modules/visitor-management/ai-stack',
            'transportation' => '/modules/transport/ai-stack',
            'inventory' => '/modules/inventory/ai-stack',
            'front_desk' => '/modules/front-desk/ai-stack',
            'task_management' => '/modules/task-management/ai-stack',
            'complaint' => '/modules/complaint/ai-stack',
            'migration-modules' => '/modules/utility/ai-stack',
            'document-templates' => '/modules/document-templates/ai-stack',
            'parent_communication' => '/modules/parent-communication/ai-stack',
            'sqaa' => '/modules/sqaa/ai-stack',
            'library' => '/modules/library/ai-stack',
            'students' => '/modules/student/ai-stack',
            'admissions' => '/modules/admission/ai-stack',
            'attendance' => '/modules/attendance/ai-stack',
            'fees' => '/fees/ai-stack',
        ];

        foreach ($routes as $owner => $route) {
            $this->assertTrue(
                $matcher->best($patterns[$owner], $route)['matched'],
                "{$owner} does not match its own AI Stack route {$route}."
            );

            foreach ($patterns as $other => $list) {
                if ($other === $owner) {
                    continue;
                }

                $this->assertFalse(
                    $matcher->best($list, $route)['matched'],
                    "{$other} also matches {$route}, which belongs to {$owner}."
                );
            }
        }
    }

    /**
     * The two identity-card modules cannot reach each other.
     *
     * `student_icard` prints a card for a child from `tblstudent`; `user_icard` prints one
     * for a member of staff from `tbluser`. They do the same job for different people, and
     * a reader skimming the config could very reasonably assume one set of tools serves
     * both — which is why the separation is asserted rather than left to the prefix map.
     *
     * The route half matters too: both live under `/student/…`, one segment apart.
     */
    public function test_the_two_identity_card_modules_cannot_reach_each_other(): void
    {
        $registry = app(ModuleRegistry::class);

        $student = $registry->find('student_icard')?->mcpTools ?? [];
        $staff = $registry->find('user_icard')?->mcpTools ?? [];

        $this->assertNotEmpty($student, 'student_icard is not registered.');
        $this->assertNotEmpty($staff, 'user_icard is not registered.');

        foreach ($student as $tool) {
            $this->assertStringStartsNotWith(
                'user_icard.',
                $tool,
                "Student I-Card binds {$tool}, which reads the staff record."
            );
        }

        foreach ($staff as $tool) {
            $this->assertStringStartsNotWith(
                'student_icard.',
                $tool,
                "User I-Card binds {$tool}, which reads a child's record."
            );
        }

        $matcher = new RouteMatcher();
        $patterns = $this->routePatterns();

        foreach (['/student/student_icard' => 'student_icard', '/student/user_icard' => 'user_icard'] as $route => $owner) {
            $rival = $owner === 'student_icard' ? 'user_icard' : 'student_icard';

            $this->assertTrue($matcher->best($patterns[$owner], $route)['matched'], "{$owner} does not match {$route}.");
            $this->assertFalse(
                $matcher->best($patterns[$rival], $route)['matched'],
                "{$rival} also matches {$route}, which belongs to {$owner}."
            );
        }
    }

    /**
     * Visitor Management does not claim the Hostel module's visitor screens.
     *
     * `hostel_visitor_master` is a separate register over a separate table, kept by
     * different people for visitors to boarders. Claiming `/hostel/visitor-details` would
     * take two of the Hostel module's pages and, worse, invite a total that summed two
     * registers which must stay apart. Neither module binds the other's tools either.
     */
    public function test_visitor_management_does_not_claim_the_hostel_visitor_screens(): void
    {
        $matcher = new RouteMatcher();
        $patterns = $this->routePatterns()['visitor_management'];

        foreach (['/admin-services/add-visitor', '/admin-services/visitor-report', '/modules/visitor-management/ai-stack'] as $route) {
            $this->assertTrue($matcher->best($patterns, $route)['matched'], "Visitor Management does not match {$route}.");
        }

        foreach (['/hostel/visitor-details', '/hostel/visitor-report', '/hostel/visitor_details', '/hostel'] as $route) {
            $this->assertFalse(
                $matcher->best($patterns, $route)['matched'],
                "Visitor Management claims {$route}, which belongs to the Hostel module."
            );
        }

        $registry = app(ModuleRegistry::class);

        foreach ($registry->find('visitor_management')?->mcpTools ?? [] as $tool) {
            $this->assertStringStartsNotWith('hostel.', $tool, "Visitor Management binds {$tool}.");
        }

        foreach ($registry->find('hostel')?->mcpTools ?? [] as $tool) {
            $this->assertStringStartsNotWith('visitor.', $tool, "Hostel binds {$tool}.");
        }
    }

    /**
     * The two visitor registers stay apart, and so do the two "front desk" trees.
     *
     * `front_desk` and `visitor_master` are different tables kept by different people, and
     * the Front Desk module holds one row across this whole estate while Visitor Management
     * holds 869. A module that could read both would report one register's emptiness as the
     * school's, or sum two counts that must stay separate.
     *
     * The route half matters just as much: Circular and Time Table both live inside
     * `/front_desk/`, and the Front Desk module claims that tree with a wildcard. Each of
     * their pages has to keep resolving to its own module.
     */
    public function test_front_desk_does_not_absorb_the_visitor_register_or_its_neighbours(): void
    {
        $registry = app(ModuleRegistry::class);

        foreach ($registry->find('front_desk')?->mcpTools ?? [] as $tool) {
            $this->assertStringStartsNotWith('visitor.', $tool, "Front Desk binds {$tool}.");
        }

        foreach ($registry->find('visitor_management')?->mcpTools ?? [] as $tool) {
            $this->assertStringStartsNotWith('front_desk.', $tool, "Visitor Management binds {$tool}.");
        }

        $matcher = new RouteMatcher();
        $patterns = $this->routePatterns();

        // The two pages inside `/front_desk/` that belong to other modules.
        foreach (['/front_desk/circular' => 'circular', '/front_desk/create-timetable' => 'timetable'] as $route => $owner) {
            $mine = $matcher->best($patterns[$owner], $route);
            $desk = $matcher->best($patterns['front_desk'], $route);

            $this->assertTrue($mine['matched'], "{$owner} does not match {$route}.");
            $this->assertTrue($desk['matched'], "front_desk's wildcard no longer covers {$route}, which is unexpected.");
            $this->assertGreaterThan(
                $desk['specificity'],
                $mine['specificity'],
                "front_desk would win {$route}, which belongs to {$owner}."
            );
        }
    }

    /**
     * The Utility module is bound to no utilities data, because there is none.
     *
     * This ERP's Utility module is bulk data operations — rollover, student transfer,
     * custom modules. The estate holds no electricity, water, gas, meter-reading or
     * utility-bill table anywhere, and the module is keyed `migration-modules` because that
     * row has owned `/Utility/**` since the workspace was seeded.
     *
     * The risk this guards is specific: somebody adding a "utilities" feature later, or a
     * keyword that routes a bill question here, would give the module a question it can
     * only answer by inventing. So the keyword list is asserted to contain none of those
     * words — a bill question must find no module and be told so, rather than landing here.
     */
    public function test_utility_claims_no_utilities_vocabulary(): void
    {
        $keywords = (array) config('ai.lifecycle.module_keywords.migration-modules', []);

        $this->assertNotEmpty($keywords, 'The Utility module has no vocabulary configured at all.');

        foreach (['utility', 'utilities', 'electricity', 'electric', 'water', 'gas', 'meter',
            'meter reading', 'bill', 'bills', 'consumption', 'units consumed'] as $word) {
            $this->assertArrayNotHasKey(
                $word,
                $keywords,
                "The Utility module scores for \"{$word}\", but this estate holds no utilities data — "
                    .'a question about one would be routed to a module that can only answer it by inventing.'
            );
        }

        // And it binds only its own two reads plus the shared generation tools.
        foreach (app(ModuleRegistry::class)->find('migration-modules')?->mcpTools ?? [] as $tool) {
            $this->assertTrue(
                str_starts_with($tool, 'utility.') || str_starts_with($tool, 'ai.templates.'),
                "The Utility module binds {$tool}, which is not one of its own reads."
            );
        }
    }
    /**
     * The staff card tools cannot return a payroll, bank or government identity column.
     *
     * `tbluser` is the staff master record. Beside the six fields a card prints it carries
     * bank account and IFSC, PAN and Aadhaar, provident fund and ESIC numbers, salary,
     * every statutory deduction, termination and notice reasons, and a stored password. A
     * tool that selected `*` would hand a model a teacher's bank account because somebody
     * asked whose card needs printing, and no prompt rule downstream could put that back.
     *
     * `UserIcardService::columns()` is the only thing standing between those two facts, so
     * it is asserted directly — the same way the Student Medical withholding rule is,
     * because a restriction that lives in one method needs a test that names that method.
     */
    public function test_the_staff_card_tools_cannot_return_a_payroll_or_identity_column(): void
    {
        $service = app(UserIcardService::class);

        $columns = new ReflectionMethod($service, 'columns');
        $columns->setAccessible(true);

        $selected = strtolower((string) $columns->invoke($service));

        $this->assertStringContainsString('employee_no', $selected, 'The card fields are not being selected at all.');

        foreach ([
            'account_no', 'ifsc_code', 'bank_name', 'pan_no', 'aadhar_no', 'pf_no', 'esic_no', 'uan_no',
            'per_hours_amount', 'plain_password', 'password', 'tds_deduction', 'pf_deduction',
            'pt_deduction', 'esic_deduction', 'termination_reason', 'noticereason', 'employee_deposite',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $selected,
                "UserIcardService selects {$forbidden}, which is not a field an identity card prints."
            );
        }

        // `u.*` would defeat the whole list above without naming any column in it.
        $this->assertStringNotContainsString('u.*', $selected, 'UserIcardService selects the whole staff row.');
    }
}
