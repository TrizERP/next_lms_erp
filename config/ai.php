<?php

// Google retires model ids and then answers them with 404 NOT_FOUND, which reaches
// the user as a failed generation rather than as a config error. Any retired id
// still pinned in a deployed .env is mapped forward to its replacement here, so a
// stale environment keeps working without an .env edit on every server.
$geminiModel = (static function (): string {
    $model = trim((string) env('GEMINI_MODEL', ''));

    $retired = [
        'gemini-2.5-flash' => 'gemini-3.6-flash',
        'gemini-1.5-flash' => 'gemini-3.6-flash',
        'gemini-1.5-pro'   => 'gemini-3.6-pro',
    ];

    return $retired[$model] ?? ($model !== '' ? $model : 'gemini-3.6-flash');
})();

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

    /*
    |--------------------------------------------------------------------------
    | Model provider — the single source of truth
    |--------------------------------------------------------------------------
    |
    | Everything that talks to a model reads its credentials, endpoint, model name
    | and timeout from here. config/gemini.php and config/openrouter.php now derive
    | from this block rather than owning their own copies of the same env vars, so a
    | provider change is one edit instead of a hunt through three files that had
    | drifted into disagreeing about the default model.
    |
    | `driver` selects the client the AI brain uses: `gemini` (default) or
    | `openrouter` for a rollback. Both implement App\Domain\AI\Support\ModelClient.
    |
    | Not everything here is on the brain's path. PAL keeps its own OpenRouter
    | subsystem and question generation keeps its DeepSeek prompt pack; both now read
    | their keys from this block while keeping their own domain settings where they
    | belong. See config/deepseek.php.
    |
    */
    'provider' => [
        'driver' => env('AI_PROVIDER', 'gemini'),

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            // Left without a version segment on purpose: the client appends
            // /models/{model}:generateContent, which is how Google's REST API is shaped.
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => $geminiModel,
            'timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 45),
            'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1466),
            // The api_type used to look the key up in the ai_api_keys pool, which
            // takes precedence over the env value above. Lowercase `gemini` is not a
            // typo — it is what the rows in that table are actually tagged with, and
            // guessing 'GEMINI_API_KEY' by symmetry with OpenRouter finds nothing and
            // silently degrades the brain to its deterministic fallback.
            'api_type' => env('GEMINI_API_TYPE', 'gemini'),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-chat'),
            'timeout' => (int) env('OPENROUTER_TIMEOUT', 45),
            'max_output_tokens' => (int) env('OPENROUTER_MAX_OUTPUT_TOKENS', 1466),
            'api_type' => env('OPENROUTER_API_TYPE', 'OPENROUTER_API_KEY'),
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-pro'),
            'timeout' => (int) env('DEEPSEEK_TIMEOUT_SECONDS', 600),
            'max_output_tokens' => (int) env('DEEPSEEK_MAX_OUTPUT_TOKENS', 0),
            'api_type' => env('DEEPSEEK_API_TYPE', 'DEEPSEEK_API_KEY'),
        ],
    ],

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
    | It defaults to **true**. The lifecycle is the only pipeline that resolves a module
    | per turn, so it is the only one that can answer from the page a question was asked
    | on — AskService contains no reference to a module or a route at all. Page context,
    | the module registry, dynamic follow-ups and agentic depth therefore all exist on
    | this side of the flag only.
    |
    | Whichever way it is set, it must be set the SAME in every environment. The two
    | pipelines answer the same question with visibly different products, so a
    | deployment that disagrees with its neighbour is not subtly different — it is a
    | different application. That is not hypothetical: two environments of one commit
    | once answered "Top 4 students at academic risk / Ranked by current risk priority"
    | and "5 students are currently showing academic risk signals / Breakdown, Students,
    | Evidence, Recommended action" from the same rows in the same database, purely
    | because one of them had never set the variable.
    |
    | The regression that argued for false is fixed rather than avoided: the ranked scan
    | now returns the breakdown, the full list with each case's score, and the evidence
    | and recommendation behind the highest-priority case. What it still will not do is
    | arm that recommendation for approval — see RecommendationStage::forRankedRiskScan.
    |
    | Set it to false to fall back deliberately, in every environment at once. The flag
    | goes away in Phase 3 of docs/lifecycle-cutover-plan.md.
    |
    */
    'lifecycle' => [
        'enabled' => (bool) env('AI_LIFECYCLE_ENABLED', true),

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
        /*
        | `detail_tools` — how a module opens one row of a list it just showed.
        |
        | Selecting a record off an answer ("show the details of the first candidate") is
        | resolved deterministically against the rows the previous turn returned, and the
        | id it lands on then needs a lookup that reads that record in full. Which tool
        | that is differs per module, and several tools usually accept the same id: half a
        | dozen tools take a student_id, and only one of them is "who is this person".
        |
        | RecordDetail derives an answer when nothing is configured — a read-only tool
        | that takes this id and requires nothing else is, by construction, the lookup for
        | it — so a module left out of this block still gets selection working. The entries
        | here exist where derivation would be ambiguous or would pick the wrong one, and
        | a configured tool is still ignored unless the module is bound to it.
        |
        | A module with no lookup at all is not broken: the turn answers from the fields
        | the previous answer already showed, and says that is what it did.
        */
        'modules' => [
            'student' => [
                'agent_key' => 'k12_academic_risk',
                'workflow_key' => 'k12_academic_intervention',
                'case_type' => 'academic_risk',
                'detail_tools' => ['student_id' => 'students.search'],
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
                'detail_tools' => ['student_id' => 'students.search'],
                // `students.directory` leads, and only in THIS block.
                //
                // `ModuleReadTools::select()` and `ModuleReadPlanner` both take the first
                // usable tools in this order, and both of those paths answer a question
                // about the PAGE — a cohort — not about one named child. `students.search`
                // returns a person's contact details and admission date and carries no
                // class placement at all, so with it leading, a page-level summary could
                // report how many students were on screen and then say the rows contained
                // no standard or division to group them by. `students.directory` returns
                // the placement and counts the whole cohort before applying its limit,
                // which is what a cohort question actually needs.
                //
                // Finding one named student is unaffected: that arrives through the intent
                // router or through `detail_tools` above, both of which name
                // `students.search` explicitly rather than taking whatever leads here. The
                // entity-bound `student` module a few blocks up is not touched, and
                // neither is any other module.
                'mcp_tools' => [
                    'students.directory',
                    'students.search',
                    'students.history',
                    'academics.structure',
                    'attendance.overview',
                    'homework.list',
                    // Appended, never reordered. `ModuleReadTools::select()` takes the
                    // first two usable tools in this order for grounding, so adding these
                    // at the end gives the module's Templates tab the same template reach
                    // Fees and Attendance have without changing which tools a student
                    // question is already answered from.
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
            ],

            'fees' => [
                'agent_key' => 'k12_fees',
                'workflow_key' => 'fees_collection',
                'case_type' => 'fee_collection',
                'detail_tools' => ['student_id' => 'fees.getPending'],
                'mcp_tools' => [
                    'fees.getPending',
                    'fees.arrears',
                    'fees.collection_report',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                    'students.search',
                    'students.directory',
                    'academics.structure',
                ],
            ],

            /*
            | Admissions now reaches the full depth, on the same three bindings Fees uses.
            |
            | It previously carried a `depth_reason` saying the module had read tools and a
            | confirmation flow but no agent that opens cases — true at the time, and the
            | reason an admissions question could be answered but never acted on.
            | `k12_admissions`, `admissions_followup` and the `admission_follow_up` case
            | type are registered by
            | 2026_09_21_100400_register_admissions_agent_signal_and_workflow.php, and
            | `ModuleRegistry` verifies all three against `ai_agents` and
            | `workflow_definitions` before the module claims the depth — so if that
            | migration has not run on an estate, this block degrades to the behaviour it
            | replaced and reports why, rather than promising something that is not there.
            |
            | Confirming an admission is unchanged: it still runs through its own
            | confirmable MCP tool with its own human gate, and `k12_admissions` is not
            | licensed for that tool at all.
            */
            'admissions' => [
                'agent_key' => 'k12_admissions',
                'workflow_key' => 'admissions_followup',
                'case_type' => 'admission_follow_up',
                'detail_tools' => ['enquiry_id' => 'admissions.getEnquiryDetails'],
                'mcp_tools' => [
                    'admissions.today',
                    'admissions.listEnquiries',
                    'admissions.getEnquiryDetails',
                    'admissions.validateConfirmation',
                    'admissions.updateEnquiry',
                    'admissions.confirm',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                    'academics.structure',
                ],
            ],

            /*
            | Attendance now reaches the full depth, on the same three bindings Fees uses.
            |
            | It previously carried a `depth_reason` saying the module had no agent of its
            | own — true at the time, and the reason an attendance question could be
            | answered but never acted on. `k12_attendance`, `attendance_followup` and the
            | `attendance_follow_up` case type are registered by
            | 2026_09_19_100000_register_attendance_agent_signal_and_workflow.php, and
            | `ModuleRegistry` verifies all three against `ai_agents` and
            | `workflow_definitions` before the module claims the depth — so if that
            | migration has not run on an estate, this block degrades to exactly the
            | behaviour it replaced rather than promising something that is not there.
            */
            'attendance' => [
                'agent_key' => 'k12_attendance',
                'workflow_key' => 'attendance_followup',
                'case_type' => 'attendance_follow_up',
                'detail_tools' => ['student_id' => 'attendance.student'],
                'mcp_tools' => [
                    'attendance.overview',
                    'attendance.student',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                    'students.search',
                    'students.directory',
                    'academics.structure',
                ],
            ],

            /*
            | Exam keeps the two read tools and the class context it has always had, with
            | the three template tools appended so its AI Stack's Templates tab can build a
            | report and its Prompts tab can be grounded.
            |
            | APPENDED, NEVER REORDERED. `ModuleReadTools::select()` takes the first usable
            | tools in this order for grounding a page-level question, so `exams.list` still
            | leads and an exam question is still answered from the exam masters. Adding the
            | template tools at the end gives the module the same template reach Fees and
            | Attendance have without changing which tool answers anything.
            |
            | `exams.results` sits second and requires no argument, so a cohort question
            | reads recorded marks rather than failing for want of a student id.
            */
            'exam' => [
                'detail_tools' => ['student_id' => 'exams.results'],
                'mcp_tools' => [
                    'exams.list',
                    'exams.results',
                    'academics.structure',
                    'academics.subjects',
                    'students.directory',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Assessment data feeds the academic-risk agent through its detectors, '
                    . 'but the exams module itself has no agent of its own yet.',
            ],

            /*
            | The five modules under the LMS + PAL menu group.
            |
            | `teach_learn` binds the SAME `lms.courses`/`lms.activities` tools the `lms`
            | module binds a few sections down — it is a teacher-facing lens on the identical
            | course/chapter/activity records, not a second copy of them.
            |
            | `curriculum_planning`, `engagement`, `interactions` and `new_pal` bind new tools
            | registered in app/Mcp/Tools and app/Providers/McpServiceProvider.php. None of
            | the four has a domain agent yet, so each carries a `depth_reason` the same way
            | `exam` above does.
            */
            'teach_learn' => [
                'detail_tools' => ['standard_id' => 'lms.courses'],
                'mcp_tools' => [
                    'lms.courses',
                    'lms.activities',
                    'academics.structure',
                    'academics.subjects',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Course and activity configuration could feed a learning-support agent, '
                    . 'but Teach/Learn itself has no agent of its own yet.',
            ],

            'curriculum_planning' => [
                'detail_tools' => ['standard_id' => 'curriculum_planning.status'],
                'mcp_tools' => [
                    'curriculum_planning.status',
                    'curriculum_planning.outcomes',
                    'academics.structure',
                    'academics.subjects',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Curriculum coverage could feed a planning agent, but Curriculum Planning '
                    . 'itself has no agent of its own yet.',
            ],

            'engagement' => [
                'detail_tools' => ['student_id' => 'engagement.student_summary'],
                'mcp_tools' => [
                    'engagement.student_summary',
                    'engagement.students_needing_attention',
                    'students.directory',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Engagement signals could feed the academic-risk agent alongside '
                    . 'attendance, but Engagement itself has no agent of its own yet. No engagement '
                    . 'score is stored anywhere — every figure is computed live from attendance, '
                    . 'homework and assignment records at read time.',
            ],

            'interactions' => [
                'detail_tools' => ['related_id' => 'interactions.list'],
                'mcp_tools' => [
                    'interactions.list',
                    'interactions.summary',
                    'students.directory',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Logged interactions could feed a follow-up agent, but Interactions '
                    . 'itself has no agent of its own yet.',
            ],

            'new_pal' => [
                'detail_tools' => ['student_id' => 'new_pal.gamification_summary'],
                'mcp_tools' => [
                    'new_pal.gamification_summary',
                    'new_pal.content_model_status',
                    'new_pal.coherence_gaps',
                    'academics.structure',
                    'academics.subjects',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Personalised-learning signals could feed a recommendation agent, but '
                    . 'New PAL itself has no agent of its own yet. New PAL reads its own content-model, '
                    . 'gamification and coherence tables — never the older pal module’s tables.',
            ],

            /*
            | Parent-teacher meetings.
            |
            | `ptm.meetings` leads because a PTM question asked from a PTM page is almost
            | always about the programme - which meetings are scheduled, how many families
            | booked - and the schedule is what answers it. `ptm.bookings` follows for the
            | per-family detail, and `students.directory` gives the class context a booking
            | is read against.
            |
            | No agent and no workflow: nothing in this module detects a condition or opens
            | a case, and claiming otherwise would offer a stage the module cannot reach.
            | `depth_reason` says so, which is what the AI Stack's Automations tab shows.
            */
            'ptm' => [
                'label' => 'PTM',
                'description' => 'Parent-teacher meeting slots, bookings and attendance.',
                'detail_tools' => ['student_id' => 'ptm.bookings'],
                'mcp_tools' => [
                    'ptm.meetings',
                    'ptm.bookings',
                    'students.directory',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Meetings, bookings and the attendance a teacher recorded can all be '
                    . 'read and reported. The module has no agent that opens a case, so nothing here '
                    . 'reaches an approval.',
            ],

            /*
            | Hostel: rooms, allocation and occupancy.
            |
            | `hostel.occupancy` leads because it is the whole-estate view a hostel question
            | usually wants. `hostel.allocations` and `hostel.available_rooms` follow for
            | "who is where" and "what is free".
            */
            'hostel' => [
                'detail_tools' => ['student_id' => 'hostel.allocations'],
                'mcp_tools' => [
                    'hostel.occupancy',
                    'hostel.allocations',
                    'hostel.available_rooms',
                    'students.directory',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Occupancy, allocation and free rooms are all readable. Rooms carry no '
                    . 'bed capacity in this schema, so nothing here judges whether a hostel is full, and '
                    . 'the module has no agent that opens a case.',
            ],

            /*
            | Student change requests.
            |
            | `student_requests.list` leads and takes no required argument, so "what is
            | pending" is answerable from the page. `student_requests.details` requires a
            | request id and is therefore skipped by `ModuleReadTools` for a page-level
            | question and reached through `detail_tools` instead - which is exactly the
            | split `admissions.getEnquiryDetails` already uses.
            |
            | `students.directory` is deliberately ABSENT. A request names the child it is
            | about and this module reports that; it is not a second route into the student
            | directory, and binding one here would make "show me the requests" able to
            | answer "show me the students".
            */
            'student_request' => [
                'label' => 'Student requests',
                'description' => 'Change requests raised against a student record, and their approvals.',
                'detail_tools' => ['request_id' => 'student_requests.details'],
                'mcp_tools' => [
                    'student_requests.list',
                    'student_requests.types',
                    'student_requests.details',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The queue, each request in full and the request types are readable. '
                    . 'Approving a request stays a human act on the Student Request screen; no agent '
                    . 'here may decide one.',
            ],

            /*
            | Circulars.
            |
            | One row is one circular to one class, so `circulars.list` reports both the row
            | count and the count of distinct circulars. `circulars.types` resolves a type
            | named in a question into the id the list filters on.
            */
            'circular' => [
                'label' => 'Circulars',
                'description' => 'Circulars published to classes, with their types and attachments.',
                'mcp_tools' => [
                    'circulars.list',
                    'circulars.types',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Published circulars and their types are readable. The record holds no '
                    . 'read receipt, so nothing here can report who saw a circular, and the module has '
                    . 'no agent that opens a case.',
            ],

            /*
            | Communication.
            |
            | THE KEY IS `easy_com`, AND IT IS DELIBERATELY NOT A NEW ONE
            |
            | `ai_modules` has carried `easy_com` since the workspace was seeded, with the
            | route patterns `/easy_com` and `/easy_com/**` — which is exactly where the
            | Communication menu's five screens live. Registering a second `communication`
            | module would split one module across two keys: two policy scopes, two
            | template lists, two ledgers, and a question answered from whichever the page
            | happened to resolve to. So this binds the key the estate already has, and the
            | level-2 menu slug `communication` is what the AI Stack route carries. The two
            | spellings name different things and neither has to match the other.
            |
            | `communication.messages` leads because it takes no required argument, so "what
            | did we send" is answerable from the page. `communication.channels` follows and
            | is what answers "which of these do we even use".
            */
            'easy_com' => [
                'label' => 'Communication',
                'description' => 'SMS, WhatsApp and app notifications the school has sent.',
                'detail_tools' => ['student_id' => 'communication.messages'],
                'mcp_tools' => [
                    'communication.messages',
                    'communication.channels',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Everything the school has sent is readable, per channel. Only WhatsApp '
                    . 'records whether a message arrived, so nothing here reports reach for the others, and '
                    . 'the module has no agent that opens a case.',
            ],

            /*
            | Users Mobile Apps.
            |
            | The module's records are the app's configured navigation, per user profile —
            | not app users, sessions or devices, none of which this estate stores. So the
            | bound tools are the two home-screen reads and nothing else: binding a student
            | or staff directory here would let "who uses the app" be answered from a list
            | of people who merely exist, which is the single wrong answer this module
            | invites.
            */
            'mobile_apps' => [
                'label' => 'Users Mobile Apps',
                'description' => 'The parent, student and teacher apps, and the home screens configured for them.',
                'mcp_tools' => [
                    'mobile_apps.homescreen',
                    'mobile_apps.sections',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The app\'s configured navigation can be read and reported. This estate '
                    . 'records no session, device or login, so nothing here can report app usage, and the '
                    . 'module has no agent of its own.',
            ],

            /*
            | Student I-Card.
            |
            | `students.directory` is deliberately ABSENT, and that absence is the module
            | boundary rather than an oversight. `student_icard.roster` returns only the
            | fields a card prints; the directory returns admission dates, addresses and
            | quota codes. Binding it here would make the I-card module a second,
            | ungoverned route into the student file for anybody who can print a card.
            */
            'student_icard' => [
                'label' => 'Student I-Card',
                'description' => 'The students a card can be printed for, and the fields a card carries.',
                'detail_tools' => ['student_id' => 'student_icard.card_details'],
                'mcp_tools' => [
                    'student_icard.roster',
                    'student_icard.card_details',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The card roster and one student\'s card fields are readable. This estate '
                    . 'records no card issue history, so nothing here can report when a card was printed, '
                    . 'and the module has no agent of its own.',
            ],

            /*
            | Certificate.
            |
            | `certificate.issued` leads because a certificate question asked from the
            | screen is almost always about what has been issued. `certificate.templates`
            | resolves a type named in a question into the layout that serves it.
            */
            'certificate' => [
                'label' => 'Certificate',
                'description' => 'Certificates issued, and the layouts they are issued from.',
                'mcp_tools' => [
                    'certificate.issued',
                    'certificate.templates',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Issued certificates and the available layouts are readable. Issuing one '
                    . 'stays a person\'s act on the Certificate screen, and the module has no agent that '
                    . 'opens a case.',
            ],

            /*
            | Time Table.
            |
            | `timetable.schedule` leads and takes no required argument, so "what is on
            | today" is answerable from the page. `timetable.conflicts` is the one judgement
            | the table can actually support — a teacher in two places at once — and it is
            | bound second so a general question is answered from the schedule itself.
            */
            'timetable' => [
                'label' => 'Time Table',
                'description' => 'The published class timetable, its periods, subjects and teachers.',
                'detail_tools' => ['teacher_id' => 'timetable.schedule'],
                'mcp_tools' => [
                    'timetable.schedule',
                    'timetable.conflicts',
                    'academics.structure',
                    'academics.subjects',
                    'teachers.directory',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The published schedule and teacher clashes are readable. The table records '
                    . 'no room and no teacher availability, so nothing here judges a timetable beyond a '
                    . 'clash, and the module has no agent of its own.',
            ],

            /*
            | Student Medical — the most restricted binding in this file.
            |
            | Four read tools, all of them this module's own, and NOTHING ELSE. No student
            | directory, no attendance, no academics: a clinical record is not a place to
            | start browsing the school from, and every tool absent here is a tool no
            | medical question can reach.
            |
            | The reverse holds by construction too — these four tools appear in no other
            | module's block, so no other AI Stack, report layout or agent allow-list can
            | read a child's infirmary record.
            */
            'student_medical' => [
                'label' => 'Student Medical',
                'description' => 'Infirmary visits, vaccinations, growth measurements and health notes.',
                'detail_tools' => ['student_id' => 'student_medical.visits'],
                'mcp_tools' => [
                    'student_medical.visits',
                    'student_medical.vaccinations',
                    'student_medical.growth',
                    'student_medical.health_records',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Visits, vaccinations, measurements and notes are readable by those the '
                    . 'existing rights allow. Nothing here interprets a record, and the module has no agent '
                    . 'that opens a case — a clinical judgement is a clinician\'s.',
            ],

            /*
            | Inward.
            |
            | The module is keyed `inward_outward` because that row has existed since the
            | workspace was seeded and already claims `/inward_outward/**`, which is where
            | every one of the module's screens lives. A second `inward` key would split one
            | module across two policy scopes and two ledgers.
            |
            | `inward.register` leads and takes no required argument, so "what came in this
            | week" is answerable straight from the page. `inward.unfiled` is the honest
            | answer to "what is pending": the table records no status, no owner and no due
            | date, so what it can show is where the REGISTER has a gap.
            |
            | `outward` is deliberately unbound. It is the other half of the same screen and
            | a different register, and mixing the two would make "how many did we receive"
            | wrong.
            */
            'inward_outward' => [
                'label' => 'Inward',
                'description' => 'Documents received and entered in the inward register.',
                'mcp_tools' => [
                    'inward.register',
                    'inward.unfiled',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The register and its gaps are readable. The table records no status, '
                    . 'owner, due date or reply, so nothing here can call a document pending or answered, '
                    . 'and the module has no agent of its own.',
            ],

            /*
            | User I-Card — the staff card, not the student one.
            |
            | `student_icard` a few blocks up is a different module reading a different
            | table, and neither can reach the other's tools. This one reads `tbluser`,
            | where the card fields sit beside payroll, bank and government identity
            | numbers — which is why `UserIcardService` selects an explicit column list and
            | why nothing broader than these two tools is bound here.
            |
            | `hr.departments` is NOT bound. The roster already resolves a department name
            | through a scoped join, and the wider HR reads have nothing a card needs.
            */
            'user_icard' => [
                'label' => 'User I-Card',
                'description' => 'The staff a card can be printed for, and the fields a card carries.',
                // Keyed `staff_id`, which is both the roster row's identifier and the
                // lookup's argument. Never `user_id` — see the note in `UserIcardService`.
                'detail_tools' => ['staff_id' => 'user_icard.card_details'],
                'mcp_tools' => [
                    'user_icard.roster',
                    'user_icard.card_details',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The card roster and one member of staff\'s card fields are readable, and '
                    . 'nothing else on the staff record is. This estate records no card issue or expiry '
                    . 'date, so nothing here can report a card as due for renewal, and the module has no '
                    . 'agent of its own.',
            ],

            /*
            | Petty Cash.
            |
            | Two reads, both over the same book. `petty_cash.transactions` leads because a
            | question asked from the screen is usually about a spend; `petty_cash.summary`
            | answers the totals question without a model adding anything up.
            |
            | No write, and no approval tool, because there is no approval to make: the
            | table has no approval column and the estate has no approval table. That is
            | also why this module's policy leaves `use_ai_for_generating_answers` off — see
            | the example policy migration.
            */
            'petty_cash' => [
                'label' => 'Petty Cash',
                'description' => 'Petty cash spends, their heads and their totals.',
                'mcp_tools' => [
                    'petty_cash.transactions',
                    'petty_cash.summary',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Spends and their totals are readable. The book records no approval and '
                    . 'no opening float, so nothing here is pending approval and no balance exists to '
                    . 'report; the module has no agent that opens a case.',
            ],

            /*
            | Consent.
            |
            | `academics.structure` is bound so a question naming a class resolves to a
            | standard id, which is the only lookup a consent question reaches for. Nothing
            | that reads a student's wider record is bound: a consent is about one
            | permission, not about the child.
            */
            'consent' => [
                'label' => 'Consent',
                'description' => 'Consents raised for students and whether a decision has been recorded.',
                'detail_tools' => ['student_id' => 'consent.records'],
                'mcp_tools' => [
                    'consent.records',
                    'consent.summary',
                    'academics.structure',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Consents and their decision states are readable. An unanswered consent is '
                    . 'reported as unanswered and never as a refusal; the table records no expiry and no '
                    . 'reminder, and the module has no agent of its own.',
            ],

            /*
            | Visitor Management.
            |
            | The front gate's own register. `hostel.*` is not bound and must not be: the
            | Hostel module keeps a separate `hostel_visitor_master` for visitors to
            | boarders, and the two registers are kept by different people.
            */
            'visitor_management' => [
                'label' => 'Visitor Management',
                'description' => 'Visits to the school, who they were for, and the times recorded at the gate.',
                'mcp_tools' => [
                    'visitor.visits',
                    'visitor.without_exit',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The visitor register and the visits with no exit recorded are readable. A '
                    . 'missing exit time is a missing record and not a person in the building, the table '
                    . 'records no approval at all, and the module has no agent of its own.',
            ],

            /*
            | Transport.
            |
            | Keyed `transportation` for the same reason Inward is keyed `inward_outward`:
            | the row already exists and already claims `/Transportation/**`.
            |
            | Three reads, because a transport question is about one of three things — the
            | route, the bus, or the child on it. `students.directory` is not bound: a
            | transport answer names the students on a bus from the mapping itself, and
            | binding the directory would let a transport question walk the whole roll.
            */
            'transportation' => [
                'label' => 'Transport',
                'description' => 'Routes, stops, vehicles and the students assigned to them.',
                'detail_tools' => ['student_id' => 'transport.assignments'],
                'mcp_tools' => [
                    'transport.routes',
                    'transport.vehicles',
                    'transport.assignments',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Routes, vehicles and assignments are readable, and seats against '
                    . 'assignments is the one judgement the data supports. Nothing records a boarding, a '
                    . 'live position or a vehicle\'s fitness, and the module has no agent of its own.',
            ],

            /*
            | Inventory.
            |
            | `inventory.items` leads because a question asked from the store screen is
            | almost always about an item. The module's whole difficulty is that the stock
            | column is not a stock balance — see `InventoryService` — so every tool here
            | returns the figure under a name that says what it is, and the depth reason
            | below says the same thing to anybody reading the config.
            |
            | The vendor master is NOT bound as a tool of its own. A purchase order needs
            | the vendor's name, which `inventory.purchase_orders` already joins; a tool
            | over the vendor table would put bank accounts, PAN and registration numbers
            | one question away.
            */
            'inventory' => [
                'label' => 'Inventory',
                'description' => 'Items, the stock figure recorded against them, requisitions and purchase orders.',
                'detail_tools' => ['item_id' => 'inventory.items'],
                'mcp_tools' => [
                    'inventory.items',
                    'inventory.requisitions',
                    'inventory.purchase_orders',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Items, requisitions and purchase orders are readable. This estate keeps '
                    . 'NO running stock balance — the stock column is increased by purchase and never '
                    . 'decreased when stock is issued — so nothing here can say what is on the shelf, and '
                    . 'the module has no agent of its own.',
            ],

            /*
            | Front Desk.
            |
            | One read, over a register that holds a single row across the whole estate.
            | The school's actual visitor log belongs to Visitor Management and is not
            | bound here: they are different registers, and the front desk one is about a
            | visit concerning a named student.
            |
            | `students.directory` is deliberately absent. The register names the student
            | each visit concerns and joins that one child; a front desk question must not
            | be able to walk the roll.
            */
            'front_desk' => [
                'label' => 'Front Desk',
                'description' => 'People coming in to meet staff about a student, and the times recorded.',
                'mcp_tools' => [
                    'front_desk.visits',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The front desk register is readable, and a non-admin sees only the visits '
                    . 'they were the subject of, which is the rule the screen applies. The register is '
                    . 'nearly empty across this estate and is not the school\'s whole visitor log, so an '
                    . 'empty answer never means nobody visited.',
            ],

            /*
            | Task Management.
            |
            | `tasks.list` leads and takes no required argument. `tasks.overdue` is the one
            | judgement the data supports, and it is a real derivation from a date and a
            | status rather than an opinion.
            |
            | `teachers.directory` is bound so a question naming a colleague resolves to a
            | user id — a task is allocated to a person, and that is the only lookup a task
            | question reaches for.
            */
            'task_management' => [
                'label' => 'Task Management',
                'description' => 'Tasks allocated to people, their dates and whether they are finished.',
                'detail_tools' => ['task_id' => 'tasks.list'],
                'mcp_tools' => [
                    'tasks.list',
                    'tasks.overdue',
                    'tasks.projects',
                    'teachers.directory',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Tasks, the overdue ones and the project structure beside them are '
                    . 'readable. The status column holds two spellings of "complete", so every count is '
                    . 'made on a normalised value; nothing records why a task is late, and the module has '
                    . 'no agent of its own.',
            ],

            /*
            | Complaint.
            |
            | Two reads. The module is defined by a column whose NAME is wrong:
            | `COMPLAINT_SOLUTION` holds the status and no resolution text exists anywhere,
            | which is why the depth reason says so rather than leaving it to a prompt.
            |
            | Nothing about students is bound. A complaint names the member of staff who
            | raised it and belongs to the people handling it.
            */
            'complaint' => [
                'label' => 'Complaint',
                'description' => 'Complaints raised, the group they were assigned to and whether they are closed.',
                'detail_tools' => ['complaint_id' => 'complaints.list'],
                'mcp_tools' => [
                    'complaints.list',
                    'complaints.summary',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Complaints and their counts are readable. The column named '
                    . '`COMPLAINT_SOLUTION` is the STATUS field and no resolution text is recorded at '
                    . 'all, and the table holds no priority, due date, SLA or escalation — so nothing '
                    . 'here can rank a complaint or say how one was resolved.',
            ],

            /*
            | Utility — which in this ERP is bulk data operations, NOT utilities.
            |
            | Keyed `migration-modules` because that row has claimed `/Utility/**` since the
            | workspace was seeded, the same call Inward and Transport got. The menu slug
            | stays `utility`.
            |
            | There is no electricity, water, gas, meter or utility-bill table anywhere in
            | this estate; the module is student transfer, academic-year rollover, bulk
            | update and the custom-module builder. What can be read is what those
            | operations act ON. Nothing records that any of them has run.
            */
            'migration-modules' => [
                'label' => 'Utility',
                'description' => 'Bulk data operations: rollover, student transfer and custom modules.',
                'mcp_tools' => [
                    'utility.custom_modules',
                    'utility.rollover_scope',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The custom modules defined here and the years and institutes a rollover '
                    . 'or transfer would act on are readable. NO operation history exists — no rollover '
                    . 'log, no transfer log, no bulk-update audit — so nothing can say what has been run, '
                    . 'and this module is not about electricity, water or utility bills, none of which '
                    . 'this estate records.',
            ],

            /*
            | Document Templates.
            |
            | Two reads over tables that are empty across this whole estate. That is worth
            | binding anyway: the tabs, the policy and the prompts are what a school needs
            | in place BEFORE it creates its first template, and an empty list answered
            | honestly is more useful than a module that says it is not available.
            */
            'document-templates' => [
                'label' => 'Document Templates',
                'description' => 'Templates a document can be generated from, and their saved revisions.',
                'detail_tools' => ['template_id' => 'doc_templates.versions'],
                'mcp_tools' => [
                    'doc_templates.list',
                    'doc_templates.versions',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Templates and their revisions are readable, and the merge fields are '
                    . 'parsed from the stored content rather than assumed. These tables are empty across '
                    . 'this estate today, so an empty answer is the correct one; the module records no '
                    . 'reviewer or approval, and has no agent of its own.',
            ],

            /*
            | Parent Communication — the INBOUND direction.
            |
            | `easy_com` a few blocks up is what the school SENDS. This is what parents
            | write in: 22,729 rows across this estate. Two tables, two directions, two
            | modules, and neither binds the other's tools — a "message" means something
            | different in each and a combined total would be meaningless.
            |
            | `students.directory` is NOT bound. A message names the one child it concerns
            | and the service joins that child; a parent-message question must not be able
            | to walk the roll.
            */
            'parent_communication' => [
                'label' => 'Parent Communication',
                'description' => 'Messages parents wrote to the school, and whether anybody has replied.',
                'detail_tools' => ['student_id' => 'parent_communication.messages'],
                'mcp_tools' => [
                    'parent_communication.messages',
                    'parent_communication.summary',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Messages from parents and their answered state are readable. The message '
                    . 'body is returned only for a read naming one student or one message, an unanswered '
                    . 'message means nobody has replied yet, and the module has no agent of its own.',
            ],

            /*
            | SQAA — quality assurance and accreditation.
            |
            | Two reads over the criteria tree and the evidence uploaded against it.
            | Nothing here scores a school: the marks table holds six rows estate-wide and
            | no rubric or weighting is recorded anywhere, which the depth reason says so
            | that a reader of the config knows before a reader of a prompt does.
            */
            'sqaa' => [
                'label' => 'Quality assurance',
                'description' => 'SQAA criteria and the evidence uploaded against them.',
                'mcp_tools' => [
                    'sqaa.criteria',
                    'sqaa.evidence',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The criteria tree and the uploaded evidence are readable, with the '
                    . 'number of document slots always reported beside the number of uploads. No rubric, '
                    . 'weighting or grade boundary exists, so nothing here scores a school or judges it '
                    . 'ready for assessment.',
            ],

            /*
            | Users — the ERP accounts.
            |
            | `user_icard` reads the CARD fields of `tbluser`; this reads the ACCOUNT
            | fields of the same table. Both use an explicit column list and neither can
            | reach the payroll, bank and government identity columns beside them.
            |
            | `teachers.directory` stays bound because it was already: the module answered
            | staff-directory questions from it before this AI Stack existed, and removing
            | it would be a change to working behaviour.
            */
            'user' => [
                'label' => 'Users',
                'description' => 'Who has an ERP account in this institute, and what state it is in.',
                'detail_tools' => ['user_id' => 'user_accounts.directory'],
                'mcp_tools' => [
                    'user_accounts.directory',
                    'teachers.directory',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'Accounts, their profiles and their status are readable; payroll, bank '
                    . 'and government identity columns are not. The last login is the only activity this '
                    . 'table records, so nothing here can describe how much anybody uses the system.',
            ],

            /*
            | Library.
            |
            | `library.catalogue` leads for a question about a book and
            | `library.circulation` for a question about a loan. Overdue is the one
            | judgement the data supports and it is exact — a due date and a missing return
            | date, both recorded.
            */
            'library' => [
                'label' => 'Library',
                'description' => 'The catalogue, the copies held, and the loans out against them.',
                'detail_tools' => ['book_id' => 'library.catalogue', 'student_id' => 'library.circulation'],
                'mcp_tools' => [
                    'library.catalogue',
                    'library.circulation',
                    'ai.templates.list',
                    'ai.templates.render',
                    'ai.templates.generate',
                ],
                'depth_reason' => 'The catalogue and the loans are readable, and a loan that is out past '
                    . 'its due date is an exact derivation. No fine, reservation or renewal is recorded '
                    . 'anywhere, so nothing here states what a borrower owes, and the module has no agent '
                    . 'of its own.',
            ],

            'course' => [
                'mcp_tools' => ['academics.subjects', 'lms.courses', 'ai.templates.list', 'ai.templates.render'],
                'depth_reason' => 'The course module can generate from templates but has no agent, so '
                    . 'nothing here opens a case or requires an approval.',
            ],

            'hr' => [
                'label' => 'Staff & departments',
                'description' => 'Teachers, staff and the departments they sit in.',
                'mcp_tools' => ['teachers.directory', 'teachers.daily_report', 'hr.departments', 'academics.class_teachers'],
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
                'detail_tools' => ['student_id' => 'homework.list'],
                'mcp_tools' => [
                    'homework.list',
                    'lms.activities',
                    'lms.courses',
                    'students.directory',
                    'academics.structure',
                    'academics.subjects',
                ],
            ],

            'subjects' => [
                'mcp_tools' => ['academics.subjects', 'academics.structure'],
            ],

            'chapters' => [
                'mcp_tools' => ['academics.subjects', 'lms.courses'],
            ],

            'course-master' => [
                'mcp_tools' => ['academics.subjects', 'academics.structure', 'lms.courses'],
            ],

            'classteacher' => [
                'mcp_tools' => [
                    'teachers.daily_report',
                    'teachers.directory',
                    'academics.class_teachers',
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
                'mcp_tools' => ['teachers.directory', 'academics.structure', 'academics.class_teachers'],
            ],

            // `user` was declared here as a one-line stub binding `teachers.directory`. It
            // now has a full block further up, beside the other modules with an AI Stack.
            // Both could not stay: PHP keeps the LAST duplicate key in an array literal
            // and discards the rest silently, so the stub was quietly winning and the
            // Users module was binding one tool instead of five. `AiConfigIntegrityTest`
            // now fails on any duplicate in this array.

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
            /*
            | The compound phrases below are not decoration, and the fees block above is
            | why they are here. "How many students have pending fees?" routes to fees
            | because `pending fees` scores 4.0 on top of the individual words; the same
            | sentence about admissions scored 3.5 for `admission` against 4.0 for the
            | word "students" — which every module's records are about — and so resolved
            | to the general module, where nothing is bound and the question died at
            | planning. Fees was only ever surviving that collision because somebody had
            | already written its phrase down.
            |
            | A domain noun has to outweigh the population noun that inevitably shares
            | the sentence with it, so the phrase that names the domain outright carries
            | the weight that says so.
            */
            'admissions' => [
                'admission' => 3.5, 'admissions' => 3.5, 'enquiry' => 3.0, 'enquiries' => 3.0,
                'enrol' => 2.5, 'enroll' => 2.5, 'registration' => 2.5, 'applicant' => 3.0,
                'prospective' => 2.5,
                // "candidate" is deliberately absent. It is the word people use to point
                // at a row of a list — "show the details of the first candidate" — and an
                // elliptical follow-up like that has to score for nothing at all, or the
                // resolver stops treating it as elliptical and the panel's own screen
                // re-asserts itself over the thread the question belongs to.
                'pending admission' => 4.0, 'admission enquiry' => 4.0,
                'new admission' => 4.0, 'admission confirmation' => 4.0,
                'confirm admission' => 4.0, 'admission list' => 4.0,
            ],
            'exam' => [
                'exam' => 3.0, 'exams' => 3.0, 'result' => 2.5, 'results' => 2.5, 'marks' => 3.0,
                'grade' => 2.5, 'grades' => 2.5, 'score' => 2.0, 'report card' => 3.5,
                'assessment' => 2.0,
            ],
            /*
            | The four modules whose AI Stacks were added alongside Exam's.
            |
            | Every weight here follows the rule the admissions block above spells out: the
            | word that names the domain has to outweigh the population noun that shares the
            | sentence with it. "How many students attended the PTM?" has to reach PTM, not
            | the student directory, so `ptm` and its spellings score above the 2.0 that
            | "students" carries.
            |
            | A word that belongs to two modules is weighted for the one it is less
            | ambiguous in and given a compound phrase for the other, rather than being
            | claimed outright by both - a tie resolves to the general module, which is
            | honest, but a wrong win is not.
            */
            'ptm' => [
                'ptm' => 4.0, 'parent teacher meeting' => 4.0, 'parent-teacher meeting' => 4.0,
                'parents meeting' => 3.5, 'parent meeting' => 3.5, 'teacher meeting' => 3.0,
                // Deliberately modest: "meeting" and "slot" belong to a calendar as much as
                // to PTM, and the compound phrases above are what should win a PTM question.
                'meeting slot' => 3.0, 'booked' => 1.5,
            ],
            'hostel' => [
                'hostel' => 4.0, 'hostels' => 4.0, 'boarding' => 3.0, 'warden' => 3.5,
                'dormitory' => 3.5, 'dorm' => 3.0,
                // "room" and "bed" alone are too general - inventory and infirmary both use
                // them - so they score low and the compound phrases carry the question.
                'hostel room' => 4.0, 'room allocation' => 3.5, 'bed' => 1.5, 'room' => 1.0,
            ],
            'student_request' => [
                // "request" alone is what somebody types about any queue in the product, so
                // it scores below the phrases that name this one.
                'student request' => 4.0, 'change request' => 4.0, 'name correction' => 3.5,
                'record change' => 3.5, 'request type' => 3.0,
                'pending request' => 3.5, 'approve request' => 3.5, 'reject request' => 3.5,
                'request' => 1.5, 'requests' => 1.5,
            ],
            'circular' => [
                'circular' => 4.0, 'circulars' => 4.0, 'notice' => 2.5, 'notices' => 2.5,
                'announcement' => 2.5, 'announcements' => 2.5, 'bulletin' => 2.5,
                'circular type' => 4.0, 'published circular' => 4.0,
            ],
            /*
            | The six modules whose AI Stacks were added in 2026-09-23.
            |
            | Two collisions are handled deliberately rather than left to chance.
            |
            | `easy_com` versus `circular`: both are things the school sends. "Circular" is
            | claimed outright by the Circular module above at 4.0, so the words that mean
            | a SENT MESSAGE — sms, whatsapp, notification — carry Communication, and the
            | generic "message" scores below them. A question naming neither resolves to
            | the general module, which is the honest outcome.
            |
            | `student_icard` versus `student_medical` versus `students`: all three
            | sentences contain "student", which is why none of them scores for that word
            | alone. The phrase that names the artefact — "i-card", "infirmary",
            | "vaccination" — is what carries the question, and each outscores the 2.0 the
            | Students module gets for the population noun.
            */
            'easy_com' => [
                'sms' => 3.5, 'whatsapp' => 3.5, 'notification' => 3.0, 'notifications' => 3.0,
                'communication' => 3.5, 'communications' => 3.5,
                'send sms' => 4.0, 'send message' => 4.0, 'message sent' => 4.0,
                'messages sent' => 4.0, 'delivery status' => 3.5,
                // Below the domain words above: "message" alone is what somebody types
                // about a chat, a circular or an email just as readily.
                'message' => 1.5, 'messages' => 1.5,
            ],
            'mobile_apps' => [
                'mobile app' => 4.0, 'mobile apps' => 4.0, 'parent app' => 4.0,
                'teacher app' => 4.0, 'student app' => 4.0, 'home screen' => 3.5,
                'homescreen' => 3.5, 'app menu' => 3.5, 'app tile' => 3.5,
                // "app" alone is far too general in a product that is itself an app.
                'app' => 1.0, 'mobile' => 1.5,
            ],
            'student_icard' => [
                'i-card' => 4.0, 'icard' => 4.0, 'id card' => 4.0, 'identity card' => 4.0,
                'student i-card' => 4.5, 'student id card' => 4.5,
                'card print' => 3.5, 'print card' => 3.5,
                // Deliberately modest: a "card" is also a fee card and a report card.
                'card' => 1.0,
            ],
            'certificate' => [
                'certificate' => 3.5, 'certificates' => 3.5,
                'transfer certificate' => 4.5, 'bonafide' => 4.5, 'character certificate' => 4.5,
                'leaving certificate' => 4.5, 'no dues certificate' => 4.5,
                'certificate number' => 4.0, 'issue certificate' => 4.0,
            ],
            'timetable' => [
                'timetable' => 4.0, 'time table' => 4.0, 'schedule' => 2.5,
                'period' => 2.5, 'periods' => 2.5, 'free period' => 3.5,
                'class schedule' => 4.0, 'teacher schedule' => 4.0,
                'clash' => 3.0, 'clashes' => 3.0, 'double booked' => 4.0,
                // "subject" and "teacher" belong to other modules; they score for nothing
                // here, so a subject question is not dragged into the timetable.
            ],
            'student_medical' => [
                'medical' => 3.5, 'infirmary' => 4.0, 'sick bay' => 4.0, 'sickbay' => 4.0,
                'vaccination' => 4.0, 'vaccinated' => 4.0, 'immunisation' => 4.0, 'immunization' => 4.0,
                'health record' => 4.0, 'health records' => 4.0, 'medical record' => 4.5,
                'height and weight' => 4.0, 'medical case' => 4.0,
                // "health" and "unwell" alone are vaguer and score below the compounds.
                'health' => 2.0, 'unwell' => 2.0, 'illness' => 2.5,
            ],
            'inward_outward' => [
                'inward' => 4.0, 'inward register' => 4.5, 'inward number' => 4.5,
                'letter received' => 3.5, 'document received' => 3.5, 'dak' => 3.0,
                'file location' => 3.0, 'place master' => 3.0,
                // "outward" scores here too: a person asking about the dispatch half is on
                // this module's screen, and the module says plainly that it reads only the
                // inward side rather than answering from a register it was not given.
                'outward' => 2.0,
                // "document" alone is also a certificate, a circular and an upload.
                'document' => 1.0,
            ],
            'user_icard' => [
                // The compounds that name a staff card outright.
                'staff i-card' => 4.5, 'staff icard' => 4.5, 'staff id card' => 4.5,
                'user i-card' => 4.5, 'user icard' => 4.5, 'user id card' => 4.5,
                'teacher i-card' => 4.5, 'teacher icard' => 4.5, 'teacher id card' => 4.5,
                'employee card' => 4.0, 'employee number' => 3.0, 'employee no' => 3.0,

                /*
                | The bare card words, scored LOW — and scored at all, which is the point.
                |
                | They belong to `student_icard`, which is the far more commonly meant
                | module, and this must not take them: from a neutral page "show the
                | i-cards" should still mean the children's.
                |
                | But scoring ZERO on them was a real bug, found by
                | check_module_ai_questions.php. `ModuleResolver::withOverride()` stands a
                | page down when another module scores at least 4.0 AND beats the page's
                | own module by 4.0. A staff-card page scoring nothing against
                | `student_icard`'s 5.0 was beaten by exactly that margin — so "Show users
                | with expired I-cards", typed on the staff card screen, was routed to the
                | student card module and answered about children.
                |
                | 1.5 puts the gap at 3.0, below the floor, so the screen holds its own
                | question; and it leaves `student_icard` ahead by 3.0 elsewhere, above the
                | 2.0 margin, so nothing it used to win has moved.
                */
                'i-card' => 1.5, 'icard' => 1.5, 'id card' => 1.5, 'identity card' => 1.5,
                'card' => 0.5,
            ],
            'petty_cash' => [
                'petty cash' => 4.5, 'pettycash' => 4.5, 'imprest' => 3.5,
                'expense' => 2.5, 'expenses' => 2.5, 'reimbursement' => 2.5,
                'bill' => 1.5, 'voucher' => 2.0, 'cash book' => 3.5,
                // "fee", "payment" and "collection" belong to Fees and score for nothing
                // here, so a fee question is never dragged into the cash book.
            ],
            'consent' => [
                'consent' => 4.0, 'consents' => 4.0, 'consent form' => 4.5,
                'permission slip' => 4.0, 'parental consent' => 4.5,
                'accountable' => 2.0, 'non-accountable' => 2.5,
                // "permission" alone is also an RBAC right.
                'permission' => 1.0,
            ],
            'visitor_management' => [
                'visitor' => 4.0, 'visitors' => 4.0, 'visitor pass' => 4.0,
                'gate pass' => 3.5, 'check in' => 2.5, 'check out' => 2.5,
                'checked out' => 3.0, 'sign out' => 2.5, 'reception' => 2.5,
                'who came to meet' => 3.5, 'appointment' => 2.0,
            ],
            'transportation' => [
                'transport' => 3.5, 'bus' => 3.5, 'buses' => 3.5, 'van' => 3.0,
                'route' => 3.0, 'routes' => 3.0, 'bus stop' => 4.0, 'pickup' => 2.5,
                'drop' => 2.0, 'driver' => 3.0, 'conductor' => 3.0, 'vehicle' => 3.5,
                'sitting capacity' => 4.0, 'seats' => 2.0,
                // "stop" alone is a common verb.
                'stop' => 1.0,
            ],
            'inventory' => [
                'inventory' => 4.0, 'stock' => 3.5, 'item master' => 4.0, 'store' => 2.5,
                'requisition' => 4.0, 'requisitions' => 4.0, 'purchase order' => 4.0, 'po' => 2.0,
                'vendor' => 3.0, 'supplier' => 3.0, 'reorder' => 3.5, 'stationery' => 2.5,
                // "item" alone is also a fee head, a menu item and a checklist line.
                'item' => 1.0,
            ],
            'front_desk' => [
                'front desk' => 4.5, 'frontdesk' => 4.5, 'reception desk' => 4.0,
                'came to meet' => 3.5, 'came to see' => 3.5, 'reception' => 1.5,

                /*
                | The bare visitor words stay LOW, and a bare visitor question asked on
                | this page goes to Visitor Management. That is deliberate.
                |
                | This estate has two visitor registers: `front_desk`, which this module
                | reads and which holds ONE row in total, and `visitor_master`, which
                | Visitor Management reads and which holds 869. Somebody asking "show
                | today's visitors" — even standing here — means the register the school
                | actually uses.
                |
                | I raised these to 2.5 first, to make the page hold its own question the
                | way `user_icard` does. That was wrong, and for a reason worth recording:
                | the User I-Card case was about the wrong SUBJECT — a staff question
                | answered about children — whereas both registers here hold visitors, so
                | the other module's answer is right and merely comes from a different
                | book. The 2.5 also cost something real: it dropped
                | `visitor_management` below the decisive margin for the singular "show
                | the visitor register" from a neutral page, which used to resolve and
                | then did not.
                |
                | So the module holds its own vocabulary — "front desk", "came to meet" —
                | and lets the visitor words go where the visitors are. Its published
                | prompts say in as many words that this is not the school's whole visitor
                | log, which is the honest half of the same point.
                */
                'visitor' => 1.0, 'visitors' => 1.0,
            ],
            'task_management' => [
                'task' => 3.5, 'tasks' => 3.5, 'to-do' => 3.0, 'todo' => 3.0,
                'assigned to me' => 3.5, 'my tasks' => 4.5, 'overdue task' => 4.5,
                'due today' => 3.0, 'workstream' => 3.5, 'milestone' => 3.0,
                'kra' => 3.5, 'kpa' => 3.5,
                // "project" is also a student project and a lesson activity.
                'project' => 2.0, 'deadline' => 2.5, 'assignee' => 3.0,
            ],
            'complaint' => [
                'complaint' => 4.5, 'complaints' => 4.5, 'grievance' => 4.0,
                'complained' => 3.5, 'raised a complaint' => 4.5,
                // "issue" and "problem" are far too general to claim a module.
                'issue' => 1.0, 'problem' => 1.0,
            ],
            'migration-modules' => [
                'rollover' => 4.5, 'roll over' => 4.0, 'roll-over' => 4.0,
                'breakoff rollover' => 4.5, 'student transfer' => 4.5,
                'custom module' => 4.5, 'bulk update' => 4.0, 'data migration' => 4.5,
                'next academic year' => 3.5, 'carry forward' => 3.0,
                // "transferred" and "another institute" on their own, because the bigram
                // above only matches the exact phrase: "which institutes could a student
                // be transferred to" was answered by the Students module, which scores 4.0
                // on the bare word "student". 3.0 keeps the gap below the override floor
                // so the Utility page holds its own question, without taking a plain
                // transfer question from the teacher-transfer or fee modules.
                'transferred' => 3.0, 'another institute' => 4.0, 'transfer to' => 3.0,
                // Deliberately NOT 'utility', 'electricity', 'water', 'gas', 'meter' or
                // 'bill'. This ERP's Utility module is bulk data operations and this
                // estate records no utilities data of any kind, so a question about an
                // electricity bill must NOT be routed here to be answered — it must find
                // no module and be told so.
            ],
            'document-templates' => [
                'document template' => 4.5, 'document templates' => 4.5,
                'letter template' => 4.0, 'merge field' => 4.0, 'merge fields' => 4.0,
                'placeholder' => 2.5, 'draft template' => 4.0, 'published template' => 4.0,

                /*
                | The bare words `template` and `templates` are NOT here, and must not be
                | added.
                |
                | I put them in at 1.0 each and `QuestionRoutingTest` caught it: "Which AI
                | templates are available?" started resolving to this module instead of
                | staying general. Two 1.0 hits on one question is 2.0, which is exactly
                | the decisive margin when nothing else scores — so a question about the AI
                | template library was answered by the document-template register.
                |
                | The word is genuinely overloaded: an AI prompt template, a report layout,
                | a certificate layout and a document template are four different things in
                | four different modules. The compounds above are what actually name this
                | one, and a bare "templates" typed on this module's own page is held by the
                | page route anyway.
                */
            ],
            'parent_communication' => [
                'parent communication' => 4.5, 'parent message' => 4.5, 'parent messages' => 4.5,
                'message from a parent' => 4.5, 'parent wrote' => 4.0, 'parent enquiry' => 4.0,
                'parent feedback' => 4.0, 'reply to parent' => 4.0, 'unanswered' => 3.0,
                // "parent" alone belongs to nobody in particular; every module has parents.
                'parent' => 1.0,
            ],
            'sqaa' => [
                'sqaa' => 4.5, 'quality assurance' => 4.5, 'accreditation' => 4.0,
                'criteria' => 3.0, 'criterion' => 3.0, 'evidence' => 3.0,
                'self assessment' => 3.5, 'naac' => 3.5, 'inspection' => 2.5,
                // "document" belongs to Document Templates and Inward; "audit" to the
                // audit trail. Neither scores here.
            ],
            'user' => [
                'user account' => 4.5, 'user accounts' => 4.5, 'login' => 3.0, 'logins' => 3.0,
                'last login' => 4.0, 'never logged in' => 4.5, 'administrator' => 3.0,
                'portal user' => 4.0, 'account expiry' => 3.5, 'active users' => 3.5,
                'inactive user' => 4.0, 'user profile' => 3.5,
                // "user" and "users" alone are far too general — every module has users.
                'user' => 1.0, 'users' => 1.0,
            ],
            'library' => [
                'library' => 4.5, 'book' => 3.0, 'books' => 3.0, 'borrowed' => 3.5,
                'issued book' => 4.0, 'return book' => 4.0, 'overdue book' => 4.5,
                'catalogue' => 3.5, 'catalog' => 3.5, 'isbn' => 4.0, 'author' => 3.0,
                'call number' => 4.0, 'title' => 1.0,
            ],
            'course' => [
                'course' => 3.0, 'syllabus' => 3.0, 'curriculum' => 3.0, 'chapter' => 2.5,
                'lesson plan' => 3.5, 'subject' => 1.5,
            ],
            /*
            | The five modules under the LMS + PAL menu group.
            |
            | Page-context routing (the route a question was asked from) resolves almost
            | every one of these before a keyword weight is ever consulted — these are the
            | fallback for a question asked with no page context. Compound phrases are
            | weighted rather than the single words `course`, `chapter` and `curriculum`
            | already claimed above, so this block adds distinguishing signal instead of
            | competing with it.
            */
            'teach_learn' => [
                'teaching plan' => 3.5, 'lesson' => 2.5, 'lessons' => 2.5,
                'learning activity' => 3.5, 'learning activities' => 3.5,
                'content coverage' => 3.5, 'chapter coverage' => 3.5,
            ],
            'curriculum_planning' => [
                'curriculum plan' => 4.0, 'curriculum planning' => 4.0,
                'learning outcome' => 3.5, 'learning outcomes' => 3.5,
                'unit coverage' => 3.5, 'academic planning' => 3.0,
            ],
            'engagement' => [
                'engagement' => 4.0, 'engaged' => 3.0, 'low engagement' => 4.5,
                'participation' => 3.0, 'engagement score' => 4.0,
            ],
            'interactions' => [
                'interaction' => 4.0, 'interactions' => 4.0, 'touchpoint' => 3.5,
                'call log' => 3.0, 'meeting note' => 3.0,
            ],
            'new_pal' => [
                'personalised learning' => 4.0, 'personalized learning' => 4.0,
                'gamification' => 3.5, 'coherence map' => 4.0,
                'learning intelligence' => 3.5, 'learning progress' => 3.0,
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
