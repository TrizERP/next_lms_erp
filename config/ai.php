<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared intelligence layer
    |--------------------------------------------------------------------------
    |
    | Route prefix mirrors config/mcp.php so the two AI surfaces sit alongside each
    | other rather than under unrelated paths.
    |
    */
    'route_prefix' => env('AI_ROUTE_PREFIX', 'api/ai'),

    'rate_limit' => [
        'per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page types
    |--------------------------------------------------------------------------
    |
    | Which AI capabilities a page offers is a property of what *kind* of page it is,
    | not which module it belongs to. An exam list, an exam dashboard and an exam
    | creation form want three different things from the assistant, and `ai_modules`
    | cannot express that — it carries one capability set for the whole module.
    |
    | So the page type is resolved first, and the capabilities follow from it. The
    | rules live here rather than in code so a route can be reclassified without a
    | deploy, and so this never becomes a chain of `if ($module === 'exam')`.
    |
    | Resolution order, most trusted first:
    |
    |   1. the page's own declaration (a page knows what it is)
    |   2. a route pattern below
    |   3. a resolved entity  -> detail
    |   4. `default_type`
    |
    */
    'page_types' => [
        'default_type' => 'list',

        /*
        | Route patterns, checked most specific first. Same syntax as
        | ai_modules.route_patterns: `:param` captures a segment, `*` matches one,
        | `**` matches the rest.
        |
        | These cover the naming conventions actually used across this estate's 56
        | route folders — `/lms/dashboard`, `/fees/reports/...`, `/students/requests/new`,
        | `/teacher_daily_report`. A folder that names itself differently gets the
        | default until someone adds a line here, or the page declares its own type.
        */
        'patterns' => [
            'dashboard' => ['/', '/dashboard', '/dashboard/**', '/*/dashboard', '/*/dashboard/**'],
            /*
            | Listed literally rather than as a `*_report` suffix, because a `*` in a
            | route pattern matches a whole segment and not part of one. These are the
            | report routes this estate actually has; adding another is a line here.
            */
            'report' => [
                '/reports', '/reports/**',
                '/*/report', '/*/report/**', '/*/reports', '/*/reports/**',
                '/teacher_daily_report', '/teacher_daily_report/**',
                '/proxy_report', '/proxy_report/**',
                '/todays_proxy_report', '/todays_proxy_report/**',
                '/classteacherReport', '/classteacherReport/**',
                '/sqaa_document_report', '/sqaa_document_report/**',
                '/lms/question-wise-report', '/lms/submission-report',
                '/lms/homework/report', '/lms/homework/submission-report',
            ],
            'form' => [
                '/*/new', '/*/create', '/*/edit', '/**/new', '/**/create', '/**/edit',
                '/*/:id/edit', '/*/add', '/**/add',
            ],
            'settings' => [
                '/settings', '/settings/**', '/school_setup', '/school_setup/**',
                '/academic_setup', '/academic_setup/**', '/*/master', '/*/master/**',
            ],
        ],

        /*
        | What each page type offers, before role and content filtering.
        |
        | This is a *floor*, not a ceiling: it is unioned with whatever the module
        | declares, so a module can add a capability its page type does not imply
        | (the student record page offering workflow, say) without this list growing a
        | special case. Over-enabling is safe — a tab that resolves to no suggestions
        | is hidden rather than shown empty, so a capability with nothing behind it
        | never reaches the user.
        |
        | `agent` is the Analyse tab. `generative` is Create.
        */
        'capabilities' => [
            'dashboard' => ['conversational' => true, 'agent' => true],
            'report' => ['conversational' => true, 'agent' => true],
            'list' => ['conversational' => true, 'agent' => true],
            'detail' => ['conversational' => true, 'agent' => true, 'generative' => true],
            'form' => ['conversational' => true, 'generative' => true],
            'settings' => ['conversational' => true],
        ],

        /*
        | The analysis template each page type runs, and what to call it.
        |
        | One template per page type, shared by every module — this is what stops
        | "Analysis" needing a bespoke implementation per page. The template is
        | rendered against the page snapshot, so the same `k12.analyse.list` produces
        | an analysis of fee defaulters on one page and of library loans on another.
        |
        | A page type with no entry here simply offers no analysis action.
        */
        'analysis' => [
            'dashboard' => ['template' => 'k12.analyse.dashboard', 'label' => 'Analyse this dashboard'],
            'report' => ['template' => 'k12.analyse.report', 'label' => 'Analyse this report'],
            'list' => ['template' => 'k12.analyse.list', 'label' => 'Analyse these records'],
            'detail' => ['template' => 'k12.analyse.detail', 'label' => 'Analyse this record'],
        ],

        /*
        | Create-tab actions offered by page type. Forms get drafting help; detail
        | pages get a summary worth pasting into a note or an email.
        */
        'generation' => [
            'form' => ['template' => 'k12.assist.form', 'label' => 'Help me fill this in'],
            'detail' => ['template' => 'k12.summarise.record', 'label' => 'Write a summary of this record'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge Graph
    |--------------------------------------------------------------------------
    |
    | Neo4j is preferred for edges the migration has actually landed
    | (ontology_relationships.in_graph). Learner-side edges are SQL until migration
    | phases 7 (People) and 8 (Assessment) complete — see
    | docs/neo4j-migration-status.md. Turning this off forces SQL everywhere, which
    | is the safe fallback if the graph is unavailable.
    |
    */
    'knowledge_graph' => [
        'prefer_graph' => (bool) env('AI_KG_PREFER_GRAPH', true),
        'max_depth' => (int) env('AI_KG_MAX_DEPTH', 4),
        'default_limit' => (int) env('AI_KG_DEFAULT_LIMIT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation
    |--------------------------------------------------------------------------
    |
    | Reuses the estate's existing OpenRouter configuration and the ai_api_keys
    | rotation pool. No second provider is introduced.
    |
    */
    'generation' => [
        'default_provider' => env('AI_GENERATION_PROVIDER', 'openrouter'),
        'default_model' => env('AI_GENERATION_MODEL', 'deepseek/deepseek-chat'),
        'timeout_seconds' => (int) env('AI_GENERATION_TIMEOUT', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance
    |--------------------------------------------------------------------------
    |
    | These are guard rails, not switches. There is deliberately no setting that
    | disables the human approval gate: a consequential action always requires a
    | decision row, and that is enforced in GovernanceValidator rather than here.
    |
    */
    'governance' => [
        // How long a drafted recommendation stays actionable before it expires.
        'recommendation_ttl_days' => (int) env('AI_RECOMMENDATION_TTL_DAYS', 30),
        // Default window for a workflow approval step.
        'approval_ttl_hours' => (int) env('AI_APPROVAL_TTL_HOURS', 168),
    ],

    /*
    |--------------------------------------------------------------------------
    | The twelve-stage lifecycle
    |--------------------------------------------------------------------------
    |
    | One pipeline answers every question: Conversational AI -> Generative AI -> Agent
    | -> Planning -> MCP Tool Selection -> Laravel MCP -> Real Data -> Evidence ->
    | Reasoning -> Recommendation -> Human Approval -> Action.
    |
    | `enabled` is the cutover switch. While it is false, /ask keeps using the previous
    | AskService; while it is true, the same endpoint runs the standardised pipeline and
    | returns the same wire shape. Both write turns to the same tables, so it can be
    | turned on and off without stranding history.
    |
    */
    'lifecycle' => [
        'enabled' => (bool) env('AI_LIFECYCLE_ENABLED', false),

        /*
        | Module depth bindings.
        |
        | These live in config rather than in `ai_modules` because they name things that
        | only exist in code — an agent key, a workflow key, a registered tool. Putting
        | them in a table would let an administrator bind a module to an agent that does
        | not exist; ModuleRegistry verifies every binding against `ai_agents` and
        | `workflow_definitions` before a module claims the depth.
        |
        | A module with no `agent_key` reports stages 10-12 as not-reached with
        | `depth_reason` as the explanation. That is the honest state for most modules
        | today, and the ladder stays twelve rungs long either way.
        */
        'modules' => [
            'student' => [
                'agent_key' => 'k12_academic_risk',
                'workflow_key' => 'k12_academic_intervention',
                'case_type' => 'academic_risk',
                'mcp_tools' => [
                    'students.search',
                    'students.directory',
                    'academics.structure',
                    'academics.subjects',
                    'students.history',
                    'attendance.student',
                    'homework.list',
                    'lms.activities',
                    'exams.results',
                    'fees.getPending',
                    'ai.templates.list',
                    'ai.templates.render',
                ],
            ],

            'students' => [
                'agent_key' => 'k12_academic_risk',
                'workflow_key' => 'k12_academic_intervention',
                'case_type' => 'academic_risk',
                'mcp_tools' => [
                    'students.search',
                    'students.directory',
                    'students.history',
                    'academics.structure',
                    'attendance.overview',
                    'homework.list',
                ],
            ],

            'fees' => [
                'mcp_tools' => [
                    'fees.getPending',
                    'fees.arrears',
                    'fees.collection_report',
                    'students.search',
                    'students.directory',
                    'academics.structure',
                ],
                'depth_reason' => 'Fees questions are answered from live records, but no agent owns a '
                    . 'fees case type yet — so nothing here opens a case, recommends an action, or '
                    . 'asks for an approval.',
            ],

            'admissions' => [
                'mcp_tools' => [
                    'admissions.today',
                    'admissions.listEnquiries',
                    'admissions.getEnquiryDetails',
                    'admissions.validateConfirmation',
                    'admissions.updateEnquiry',
                    'admissions.confirm',
                    'academics.structure',
                ],
                'depth_reason' => 'Admissions has read tools and a confirmation flow of its own, but no '
                    . 'agent that opens cases — so the lifecycle stops at reasoning for this module. '
                    . 'Confirming an admission runs through its own confirmable MCP tool, which carries '
                    . 'its own human gate and is never reachable from a model-written plan.',
            ],

            'attendance' => [
                'mcp_tools' => [
                    'attendance.overview',
                    'attendance.student',
                    'students.search',
                    'students.directory',
                    'academics.structure',
                ],
                'depth_reason' => 'Attendance data feeds the academic-risk agent through its detectors, '
                    . 'but the attendance module itself has no agent — ask about a student to reach the '
                    . 'deeper stages.',
            ],

            'exam' => [
                'mcp_tools' => [
                    'exams.list',
                    'exams.results',
                    'academics.structure',
                    'academics.subjects',
                    'students.directory',
                ],
                'depth_reason' => 'Assessment data feeds the academic-risk agent through its detectors, '
                    . 'but the exams module itself has no agent of its own yet.',
            ],

            'course' => [
                'mcp_tools' => ['academics.subjects', 'ai.templates.list', 'ai.templates.render'],
                'depth_reason' => 'The course module can generate from templates but has no agent, so '
                    . 'nothing here opens a case or requires an approval.',
            ],

            'hr' => [
                'label' => 'Staff & departments',
                'description' => 'Teachers, staff and the departments they sit in.',
                'mcp_tools' => ['teachers.directory', 'teachers.daily_report', 'hr.departments'],
                'depth_reason' => 'Staff questions are answered from the directory and department '
                    . 'records. The estate holds no training, competency or appraisal data, so this '
                    . 'module can report who and how many but cannot judge capability or need.',
            ],

            /*
            | The remaining modules that existing tools genuinely serve.
            |
            | `ai_modules` carries 41 rows; most describe screens the lifecycle has no
            | data tool for, and those are deliberately left unbound so they say so
            | rather than pretending. These are the ones where a tool that already
            | exists answers the questions the screen invites.
            */
            'lms' => [
                'mcp_tools' => [
                    'homework.list',
                    'lms.activities',
                    'students.directory',
                    'academics.structure',
                    'academics.subjects',
                ],
            ],

            'subjects' => [
                'mcp_tools' => ['academics.subjects', 'academics.structure'],
            ],

            'chapters' => [
                'mcp_tools' => ['academics.subjects'],
            ],

            'course-master' => [
                'mcp_tools' => ['academics.subjects', 'academics.structure'],
            ],

            'classteacher' => [
                'mcp_tools' => [
                    'teachers.daily_report',
                    'teachers.directory',
                    'students.directory',
                    'academics.structure',
                ],
            ],

            'teacher_daily_report' => [
                'mcp_tools' => ['teachers.daily_report', 'teachers.directory', 'academics.structure'],
            ],

            'teachertransfer' => [
                'mcp_tools' => ['teachers.directory'],
            ],

            'proxy' => [
                'mcp_tools' => ['teachers.directory', 'academics.structure'],
            ],

            'user' => [
                'mcp_tools' => ['teachers.directory'],
            ],

            'academic_setup' => [
                'mcp_tools' => ['academics.structure', 'academics.subjects'],
            ],

            'school_setup' => [
                'mcp_tools' => ['academics.structure', 'academics.subjects'],
            ],

            'institute' => [
                'mcp_tools' => ['academics.structure', 'hr.departments', 'teachers.directory'],
            ],

            'reports' => [
                'mcp_tools' => [
                    'fees.collection_report',
                    'attendance.overview',
                    'exams.results',
                    'students.directory',
                    'academics.structure',
                ],
            ],

            'dashboard' => [
                'mcp_tools' => [
                    'admissions.today',
                    'fees.collection_report',
                    'attendance.overview',
                    'academics.structure',
                ],
                'depth_reason' => 'The dashboard summarises other modules; it owns no case type of its '
                    . 'own, so the deep stages belong to the module the figure came from.',
            ],
        ],

        /*
        | The words that route a question to a module, when neither the caller nor the
        | page said which one. Weighted, and a winner must beat the runner-up by a clear
        | margin before the words alone decide — an ambiguous question goes to the
        | general module, which is honest about having no depth.
        |
        | These live beside the tool bindings on purpose: the words that mean "fees" and
        | the tools that answer a fees question are one decision, and splitting them
        | across a table and a file is how they drift apart.
        */
        'module_keywords' => [
            'student' => [
                'student' => 2.0, 'pupil' => 2.0, 'learner' => 2.0, 'child' => 1.5,
                'at risk' => 3.0, 'at-risk' => 3.0, 'struggling' => 3.0, 'intervention' => 2.5,
                'academic risk' => 4.0, 'failing' => 2.0, 'falling behind' => 2.5,
            ],
            'students' => [
                'students' => 2.0, 'kids' => 1.5, 'children' => 1.5, 'cohort' => 2.0,
                'class list' => 2.5, 'directory' => 2.0,
            ],
            'fees' => [
                'fee' => 3.0, 'fees' => 3.0, 'payment' => 2.5, 'paid' => 2.0, 'unpaid' => 3.0,
                'defaulter' => 3.5, 'defaulters' => 3.5, 'collection' => 2.5, 'outstanding' => 2.5,
                'invoice' => 2.5, 'receipt' => 2.0, 'pending fees' => 4.0, 'due' => 1.5,
            ],
            'attendance' => [
                'attendance' => 3.5, 'absent' => 3.0, 'absence' => 3.0, 'present' => 2.0,
                'leave' => 1.5, 'late' => 1.5, 'punctuality' => 2.5,
            ],
            'admissions' => [
                'admission' => 3.5, 'admissions' => 3.5, 'enquiry' => 3.0, 'enquiries' => 3.0,
                'enrol' => 2.5, 'enroll' => 2.5, 'registration' => 2.5, 'applicant' => 3.0,
                'prospective' => 2.5,
            ],
            'exam' => [
                'exam' => 3.0, 'exams' => 3.0, 'result' => 2.5, 'results' => 2.5, 'marks' => 3.0,
                'grade' => 2.5, 'grades' => 2.5, 'score' => 2.0, 'report card' => 3.5,
                'assessment' => 2.0,
            ],
            'course' => [
                'course' => 3.0, 'syllabus' => 3.0, 'curriculum' => 3.0, 'chapter' => 2.5,
                'lesson plan' => 3.5, 'subject' => 1.5,
            ],
            'hr' => [
                'teacher' => 3.0, 'teachers' => 3.0, 'staff' => 3.5, 'employee' => 3.0,
                'employees' => 3.0, 'department' => 3.5, 'departments' => 3.5,
                'faculty' => 3.0, 'headcount' => 3.0,
            ],
            'dashboard' => [
                'dashboard' => 3.5, 'overview' => 2.0, 'kpi' => 3.0, 'summary' => 1.5,
                'today' => 1.5,
            ],
        ],
    ],
];
