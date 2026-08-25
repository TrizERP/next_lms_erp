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
];
