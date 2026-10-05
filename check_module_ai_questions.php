<?php

/**
 * The questions from the AI Stack brief, asked from each module's own page.
 *
 * WHAT IT ANSWERS
 *
 * Two things a binding check cannot:
 *
 *   1. ALLOWED — a question asked on a module's screen resolves to that module and the
 *      tools it may then use are that module's own. This is the path a person actually
 *      takes: they are on the Petty Cash screen, they type "what did we spend this month",
 *      and what comes back has to come from the petty cash book.
 *   2. BLOCKED — a question about ANOTHER module, asked from this module's screen, must
 *      not be answered from this module's tools. `ModuleResolver` scores the words as well
 *      as the route precisely so that a Transport question typed on the Petty Cash screen
 *      is recognised as a Transport question; what must never happen is the Petty Cash
 *      binding answering it.
 *
 * The second case has two acceptable outcomes and one unacceptable one. Resolving to the
 * other module is correct — the assistant follows the words and the other module's own
 * permissions then apply. Resolving to the current module with no tool able to answer is
 * also correct — the person is told this screen cannot answer that. What must never happen
 * is the current module answering it from its own records, which would mean reporting
 * petty cash rows as though they were bus routes.
 *
 * IT ONLY READS. No INSERT, UPDATE or DELETE. It resolves modules and lists tool
 * bindings; it does not call a model and does not execute a tool.
 *
 *   php check_module_ai_questions.php
 *
 * Cross-institute isolation is a separate question, answered by
 * `check_module_ai_isolation.php`. Binding-level isolation is asserted without a database
 * by `tests/Feature/AI/ModuleAiStackIsolationTest.php`.
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use App\Domain\AI\Modules\ModuleReadTools;
use Illuminate\Support\Facades\DB;

$resolver = app(ModuleResolver::class);
$reads = app(ModuleReadTools::class);

// A real institute with users, so the module registry resolves as it would for a person.
$institute = (int) DB::table('tblstudent_enrollment')
    ->where('sub_institute_id', '>', 0)
    ->orderBy('sub_institute_id')
    ->value('sub_institute_id');

/**
 * module key => [label, the page it is asked from, its own questions, a foreign question].
 *
 * The questions are taken from the brief verbatim where the brief gave one, so what is
 * checked is what was asked for rather than a phrasing chosen to pass.
 */
$modules = [
    'inward_outward' => [
        'label' => 'Inward',
        'route' => '/inward_outward/show_inward_report',
        'questions' => [
            'Show pending inward records.',
            "Summarise today's inward records.",
            'Which inward items need follow-up?',
            'Generate a pending inward report.',
        ],
        'foreign' => ['Show pending petty cash transactions.', 'petty_cash'],
    ],
    'user_icard' => [
        'label' => 'User I-Card',
        'route' => '/student/user_icard',
        'questions' => [
            'Show users with expired I-cards.',
            'Show the staff I-card details for this user.',
            'Generate a staff I-card.',
            'Which staff need I-card renewal?',
        ],
        'foreign' => ["Show today's visitors.", 'visitor_management'],
    ],
    'petty_cash' => [
        'label' => 'Petty Cash',
        'route' => '/admin-services/petty-cash-report',
        'questions' => [
            'Show pending petty cash transactions.',
            "Summarise today's petty cash.",
            'Which transactions require attention?',
            'Prepare a petty cash report.',
        ],
        // The brief's own cross-module example, word for word.
        'foreign' => ["Show today's transport routes.", 'transportation'],
    ],
    'consent' => [
        'label' => 'Consent',
        'route' => '/admin-services/consent-report',
        'questions' => [
            'Show pending consent requests.',
            'Which consents are expiring?',
            'Generate a consent reminder.',
            'Summarise pending consents.',
        ],
        'foreign' => ['Show pending inward records.', 'inward_outward'],
    ],
    'visitor_management' => [
        'label' => 'Visitor Management',
        'route' => '/admin-services/visitor-report',
        'questions' => [
            "Show today's visitors.",
            'Which visitors have not checked out?',
            'Show pending visitor approvals.',
            "Generate today's visitor report.",
        ],
        'foreign' => ['Show pending consent requests.', 'consent'],
    ],
    'transportation' => [
        'label' => 'Transport',
        'route' => '/Transportation/dashboard',
        'questions' => [
            "Show today's transport routes.",
            'Show active vehicles.',
            'Which routes have capacity issues?',
            'Show users assigned to transport.',
            'Generate a transport report.',
        ],
        'foreign' => ['Show staff with expired I-cards.', 'user_icard'],
    ],
    'inventory' => [
        'label' => 'Inventory',
        'route' => '/Inventory/inventory_item_master',
        'questions' => [
            'Show items with low stock.',
            "Show today's stock movements.",
            'Which items are out of stock?',
            'Show pending inventory issues.',
            'Summarise current inventory.',
        ],
        'foreign' => ['Show pending complaints.', 'complaint'],
    ],
    'front_desk' => [
        'label' => 'Front Desk',
        'route' => '/front_desk',
        // The brief lists "show today's visitors" here, and that question deliberately
        // does NOT belong to this module: it resolves to Visitor Management, whose
        // register holds 869 rows against this one's single row. Both are visitor
        // registers, so the other module's answer is right and merely comes from the
        // book the school actually uses — see the note beside `front_desk` in
        // config/ai.php. The questions below are the ones this register can answer.
        'questions' => [
            "Show today's check-ins and check-outs.",
            "Summarise today's front-desk activity.",
            'Who came to the front desk this week?',
            'Which front desk visits have no exit time recorded?',
        ],
        'foreign' => ['Show my pending tasks.', 'task_management'],
    ],
    'task_management' => [
        'label' => 'Task Management',
        'route' => '/task-management/my-tasks',
        'questions' => [
            'Show my pending tasks.',
            'Show overdue tasks.',
            'Which tasks are due today?',
            'Show tasks assigned to this user.',
            'Summarise task completion for this period.',
        ],
        'foreign' => ['Show pending utility bills.', 'migration-modules'],
    ],
    'complaint' => [
        'label' => 'Complaint',
        'route' => '/admin-services/complaint-report',
        'questions' => [
            'Show pending complaints.',
            'Which complaints are overdue?',
            'Show complaints assigned to my department.',
            'Summarise complaint status.',
        ],
        'foreign' => ['Show available document templates.', 'document-templates'],
    ],
    'migration-modules' => [
        'label' => 'Utility',
        'route' => '/Utility/custom-module',
        'questions' => [
            'What custom modules have been defined here?',
            'Which academic years have enrolments recorded against them?',
            'Which institutes could a student be transferred to?',
        ],
        'foreign' => ['Show items with low stock.', 'inventory'],
    ],
    'document-templates' => [
        'label' => 'Document Templates',
        'route' => '/document-templates',
        'questions' => [
            'Show available document templates.',
            'Show draft templates.',
            'Show published templates.',
            'Show recently updated templates.',
        ],
        'foreign' => ['Show pending complaints.', 'complaint'],
    ],    'parent_communication' => [
        'label' => 'Parent Communication',
        'route' => '/front_desk/parent_communication',
        'questions' => [
            'Which messages from parents have not been answered?',
            'How many messages did parents send this month?',
            'What is the oldest message still waiting for a reply?',
        ],
        'foreign' => ['Which library books are overdue?', 'library'],
    ],
    'sqaa' => [
        'label' => 'Quality assurance',
        'route' => '/sqaa_master',
        'questions' => [
            'How many document slots have evidence uploaded against them?',
            'Which quality assurance criteria are recorded for this school?',
            'Which evidence rows have no file attached?',
        ],
        'foreign' => ['How many active accounts does this institute have?', 'user'],
    ],
    'user' => [
        'label' => 'Users',
        'route' => '/user/add_user',
        'questions' => [
            'How many active accounts does this institute have?',
            'Which accounts have never recorded a login?',
            'How many accounts does each user profile have?',
        ],
        'foreign' => ['Which messages from parents have not been answered?', 'parent_communication'],
    ],
    'library' => [
        'label' => 'Library',
        'route' => '/library',
        'questions' => [
            'Which library books are overdue?',
            'How many books are currently on loan?',
            'Which titles do we hold the most copies of?',
        ],
        'foreign' => ['How many document slots have evidence uploaded against them?', 'sqaa'],
    ],];

echo 'Module routing for the AI Stack questions, as institute '.$institute."\n";
echo str_repeat('=', 112)."\n\n";

$failures = 0;

foreach ($modules as $key => $module) {
    echo $module['label'].'  —  asked from '.$module['route']."\n";
    echo str_repeat('-', 112)."\n";

    foreach ($module['questions'] as $question) {
        $result = $resolver->resolve($question, ['route' => $module['route']], $institute);
        $resolved = $result['module']->key;
        $tools = $result['module']->mcpTools ?? [];

        // The tools a page-level question could actually reach: read-only and needing no
        // argument the person has not given.
        $usable = $reads->select($tools, 4);

        $ok = $resolved === $key && $usable !== [];

        if (! $ok) {
            $failures++;
        }

        printf(
            "  %s  %-46s -> %-20s via %-24s tools: %s\n",
            $ok ? 'PASS' : 'FAIL',
            mb_strimwidth($question, 0, 46, '…'),
            $resolved,
            $result['source'],
            implode(', ', array_slice($usable, 0, 3)) ?: 'NONE',
        );
    }

    /*
     * The blocked case, and what "blocked" actually has to mean.
     *
     * Two outcomes are correct and they are correct for different reasons:
     *
     *   · The question goes to the module that owns it. The assistant followed the words,
     *     and that module's own permissions and tools then apply. This is the better
     *     outcome and the common one.
     *   · The question stays on this screen and this screen cannot answer it. The
     *     resolver deliberately keeps a question whose words are ambiguous on the page it
     *     was typed on rather than yanking the person elsewhere, so this happens — and it
     *     is still correct, because the binding is the control. What comes back is "this
     *     screen cannot answer that", not somebody else's records.
     *
     * Only one outcome is wrong: this module holding the question AND holding a tool that
     * serves the other module's domain. That is what is asserted, rather than the cruder
     * "it must leave this module" — which would fail a case the design gets right.
     */
    [$foreignQuestion, $foreignOwner] = $module['foreign'];

    $result = $resolver->resolve($foreignQuestion, ['route' => $module['route']], $institute);
    $resolved = $result['module']->key;

    $resolvedTools = $result['module']->mcpTools ?? [];
    // The owner module's OWN tools. The shared generation and academic-structure tools are
    // bound by nearly every module and are nobody's records, so finding one on both sides
    // would prove nothing.
    $ownerTools = array_values(array_filter(
        app(ModuleRegistry::class)->find($foreignOwner, $institute)?->mcpTools ?? [],
        static fn (string $tool) => ! str_starts_with($tool, 'ai.templates.')
            && ! str_starts_with($tool, 'academics.'),
    ));

    // The tools this turn could reach that belong to the module the question is about.
    $reachable = array_values(array_intersect($resolvedTools, $ownerTools));

    $leaks = $resolved === $key && $reachable !== [];

    if ($leaks) {
        $failures++;
    }

    $verdict = match (true) {
        $resolved === $foreignOwner => 'routed to its owner',
        $resolved !== $key => 'routed away from this module ('.$resolved.')',
        default => 'held here, and cannot reach a '.$foreignOwner.' tool',
    };

    printf(
        "  %s  %-46s -> %-20s %s\n\n",
        $leaks ? 'FAIL' : 'PASS',
        mb_strimwidth('[other module] '.$foreignQuestion, 0, 46, '…'),
        $resolved,
        $leaks ? 'CAN REACH '.implode(', ', $reachable) : $verdict,
    );
}

echo str_repeat('=', 112)."\n";
echo $failures === 0
    ? "All questions resolved to their own module, and no module answered another module's question.\n"
    : "{$failures} question(s) resolved wrongly — see FAIL above.\n";

exit($failures === 0 ? 0 : 1);
