<?php

namespace App\Domain\AI\Conversation;

/**
 * Natural language in, one intent and its slots out.
 *
 * This is deliberately deterministic. Intent routing decides whether an agent runs
 * against a school's real records and whether an approval is recorded, so it is not a
 * place for a model that answers differently on Tuesday. Every intent declares the
 * phrasings it accepts, and the trace reports exactly which of them matched — so when
 * a question is misread, the fix is a visible line in this file rather than a prompt
 * nobody can inspect.
 *
 * Robustness to wording comes from breadth: each intent carries a lexicon of the ways
 * people actually ask for it, so "find students at academic risk", "who is struggling"
 * and "show me at-risk kids" all land on the same intent, which is the behaviour the
 * test plan checks by rephrasing.
 */
class IntentClassifier
{
    /**
     * Below this, the system says it did not understand rather than guessing. Acting on
     * a coin-flip reading of a question is worse than asking again.
     */
    private const CONFIDENCE_FLOOR = 0.34;

    /**
     * The least evidence an intent needs before it may claim a question.
     *
     * Confidence alone cannot carry this. It is the winner's *share* of everything that
     * scored, so an intent matching one generic word — "which", "who" — reports 100%
     * whenever nothing else matched at all. That is uncontestedness, not confidence, and
     * it sent "which students are in class 5?" to the academic-risk agent on the
     * strength of the word "which", answering a roster question with a risk scan.
     *
     * 2.5 is set just above the generic vocabulary: the words that merely make an intent
     * plausible are weighted 0.5 to 2.0, while the terms that actually distinguish one —
     * "struggling", "evidence", "approve", "academic risk" — start at 3.0. So an intent
     * must match at least one word that means something in its own domain.
     */
    private const MINIMUM_SCORE = 2.5;

    /**
     * Intent definitions, scoped to the Student Profiles module.
     *
     * `anchors` are the terms that make an intent plausible at all; `signals` are the
     * terms that distinguish it from its neighbours and are therefore weighted higher.
     * `patterns` are full-phrase matches that settle a case outright.
     */
    private const INTENTS = [
        'student_risk_scan' => [
            'label' => 'Find students at academic risk',
            'description' => 'Runs the academic risk agent across the students in scope.',
            'anchors' => ['student', 'students', 'child', 'children', 'kid', 'kids', 'learner', 'learners', 'pupil'],
            'signals' => [
                'risk' => 3.0, 'at risk' => 4.0, 'at-risk' => 4.0, 'struggling' => 3.5,
                'failing' => 3.0, 'falling behind' => 3.5, 'behind' => 2.0, 'weak' => 2.0,
                'concern' => 2.5, 'concerns' => 2.5, 'worried' => 2.5, 'attention' => 2.0,
                'academic risk' => 5.0, 'need help' => 2.5, 'needs help' => 2.5,
                'poor performance' => 3.0, 'underperforming' => 3.5, 'not doing well' => 3.0,
                'flag' => 2.0, 'identify' => 1.5, 'find' => 1.0, 'list' => 1.0,
                'which' => 1.0, 'who' => 1.0, 'show' => 1.0, 'scan' => 2.0, 'analyse' => 1.5,
                'analyze' => 1.5, 'check' => 1.0,
            ],
            'patterns' => [
                '/\b(which|what|who|any)\b.{0,30}\b(student|students|children|kids|learners)\b.{0,40}\b(risk|struggl|behind|fail|concern)/i',
                '/\b(find|show|list|identify|flag|scan for)\b.{0,30}\b(at[\s-]?risk|risk|struggling|failing)\b/i',
                '/\backademic risk\b/i',
            ],
            'slots' => [],
        ],

        'student_risk_explain' => [
            'label' => 'Explain why one student is at risk',
            'description' => 'Reads back the stored, evidence-cited explanation for one student\'s case.',
            'anchors' => ['why', 'reason', 'explain', 'cause', 'because', 'how come', 'what makes'],
            'signals' => [
                'why' => 4.0, 'explain' => 4.0, 'reason' => 3.5, 'reasons' => 3.5,
                'cause' => 3.0, 'how come' => 4.0, 'what makes' => 3.5, 'justify' => 3.0,
                'at risk' => 2.0, 'flagged' => 2.5, 'raised' => 1.5,
            ],
            'patterns' => [
                '/\bwhy\b.{0,40}\b(at[\s-]?risk|risk|flagged|struggling|behind)\b/i',
                '/\b(explain|reason for|reasons for|justify)\b.{0,30}\b(risk|case|flag)/i',
                '/\bwhy is\b/i',
                '/\bwhy was\b/i',
            ],
            'slots' => ['student'],
        ],

        'evidence_inspect' => [
            'label' => 'Show the evidence behind the conclusion',
            'description' => 'Lists the verified, source-linked evidence rows cited by the case.',
            'anchors' => ['evidence', 'proof', 'data', 'basis', 'support', 'back'],
            'signals' => [
                'evidence' => 5.0, 'proof' => 4.5, 'prove' => 4.0, 'basis' => 3.5,
                'what data' => 4.0, 'which data' => 4.0, 'back this' => 4.0,
                'backs this' => 4.0, 'support this' => 4.0, 'supports this' => 4.0,
                'based on' => 3.0, 'source' => 3.0, 'sources' => 3.0, 'figures' => 2.5,
                'numbers' => 2.5, 'show me the' => 1.5,
            ],
            'patterns' => [
                '/\bwhat (evidence|data|proof)\b/i',
                '/\b(evidence|proof|data)\b.{0,25}\b(support|supports|back|backs|behind|for this)\b/i',
                '/\bhow do you know\b/i',
                '/\bwhere (did|does) (this|that) come from\b/i',
            ],
            'slots' => ['case'],
        ],

        'recommendation_advice' => [
            'label' => 'What should be done about it',
            'description' => 'Returns the drafted recommendation and the action it would take.',
            'anchors' => ['do', 'action', 'recommend', 'suggest', 'next', 'help', 'fix', 'plan'],
            'signals' => [
                'what should' => 4.5, 'recommend' => 4.5,
                'recommendation' => 5.0, 'recommendations' => 5.0, 'suggest' => 4.0,
                'suggestion' => 4.0, 'advice' => 3.5, 'next step' => 4.0,
                'next steps' => 4.0, 'how do i help' => 4.0,
                'how can we help' => 4.0, 'intervention' => 3.5, 'action plan' => 4.0,
                'plan' => 2.0, 'teacher do' => 4.0, 'teacher should' => 4.5,
            ],
            'patterns' => [
                '/\bwhat should (the )?(teacher|we|i|school)\b/i',
                '/\b(recommend|recommendation|suggest|advice|next steps?)\b/i',
                '/\bhow (do|can) (i|we)\b.{0,20}\b(help|support|fix|improve)\b/i',
                // "what can/do I do" only counts once it points at a situation — "what
                // can I do about it", "what do I do to help him" name a case. Scored as
                // a bare signal, "what can" and "what do i do" matched "what can I do
                // in this system?" outright: nothing else in that sentence scored, so
                // an unrelated capability question was classified as this intent at
                // 100% confidence. Requiring "about/for/with/to help" after the second
                // "do" keeps the real phrasing and drops the false one.
                '/\bwhat (can|do) (i|we)\b.{0,10}\bdo\b.{0,20}\b(about|for|with|to help)\b/i',
            ],
            'slots' => ['case'],
        ],

        'approve_recommendation' => [
            'label' => 'Approve the recommendation',
            'description' => 'Records a human approval and starts the intervention workflow.',
            'anchors' => ['approve', 'accept', 'agree', 'yes', 'go ahead', 'proceed', 'confirm'],
            'signals' => [
                'approve' => 6.0, 'approved' => 5.5, 'accept' => 4.5, 'accepted' => 4.0,
                'agree' => 3.5, 'go ahead' => 5.0, 'proceed' => 4.5, 'confirm' => 4.5,
                'do it' => 4.0, 'apply it' => 4.0, 'sign off' => 5.0, 'authorise' => 5.0,
                'authorize' => 5.0, 'ok do' => 3.0, 'yes' => 2.0,
            ],
            'patterns' => [
                '/\bapprove\b/i',
                '/\b(go ahead|proceed|sign[\s-]?off|do it)\b/i',
                '/\b(accept|confirm)\b.{0,25}\brecommendation\b/i',
                // A sentence that opens with the verb is a command, not a question.
                // Without this, "approve the workflow step" scores as a status enquiry,
                // because "workflow" and "step" are heavy words in that intent.
                '/^\s*(please\s+)?(approve|confirm|accept|authorise|authorize)\b/i',
                '/\b(approve|confirm)\b.{0,25}\b(step|activities|activity|draft|workflow)\b/i',
            ],
            'slots' => ['recommendation'],
        ],

        'reject_recommendation' => [
            'label' => 'Reject the recommendation',
            'description' => 'Records a human rejection; nothing downstream runs.',
            'anchors' => ['reject', 'decline', 'no', 'cancel', 'dismiss', 'refuse'],
            'signals' => [
                'reject' => 6.0, 'decline' => 5.0, 'dismiss' => 4.5, 'refuse' => 4.5,
                'cancel' => 4.0, 'do not' => 3.0, "don't" => 3.0, 'not now' => 3.0,
                'disagree' => 4.0, 'no thanks' => 3.5,
            ],
            'patterns' => [
                '/\b(reject|decline|dismiss|refuse)\b/i',
                '/\b(do not|don\'t)\b.{0,20}\b(approve|proceed|do it)\b/i',
                '/^\s*(please\s+)?(reject|decline|dismiss|refuse)\b/i',
                '/\b(reject|decline)\b.{0,25}\b(step|activities|activity|draft|workflow)\b/i',
            ],
            'slots' => ['recommendation'],
        ],

        'workflow_status' => [
            'label' => 'What happened after approval',
            'description' => 'Reads the workflow run: every step, in order, with its state.',
            'anchors' => ['workflow', 'process', 'status', 'happened', 'progress', 'step', 'activity', 'activities'],
            'signals' => [
                'workflow' => 5.0, 'process' => 3.5, 'status' => 3.5, 'progress' => 4.0,
                'what happened' => 4.5, 'steps' => 4.0, 'step' => 3.0,
                'after approval' => 4.5, 'after i approved' => 4.5, 'running' => 2.5,
                'stage' => 2.5, 'where are we' => 3.5, 'activity' => 3.5,
                'activities' => 3.5, 'assigned activities' => 5.0,
            ],
            'patterns' => [
                '/\b(workflow|process)\b.{0,20}\b(status|progress|steps?|state)\b/i',
                '/\bwhat happened\b/i',
                '/\bwhat (is|was) (created|done|assigned)\b/i',
                '/\bshow (me )?(the )?(steps|progress)\b/i',
                '/\b(show|list|what are)\b.{0,25}\bactivities?\b.{0,25}\b(assign|given|set)\b/i',
            ],
            'slots' => ['case'],
        ],

        'outcome_status' => [
            'label' => 'Did the intervention work',
            'description' => 'Compares the recorded baseline against the latest measurement.',
            'anchors' => ['outcome', 'result', 'work', 'improve', 'better', 'effect', 'impact'],
            'signals' => [
                'outcome' => 5.0, 'outcomes' => 5.0, 'did it work' => 5.5,
                'is it working' => 5.0, 'improve' => 4.0, 'improved' => 4.0,
                'improvement' => 4.0, 'better' => 3.0, 'effect' => 3.5,
                'impact' => 3.5, 'result' => 3.0, 'results' => 3.0,
                'before and after' => 5.0, 'baseline' => 4.5, 'measure' => 3.5,
                'measured' => 3.5, 'progress since' => 4.0,
            ],
            'patterns' => [
                '/\bdid (it|this|the intervention) work\b/i',
                '/\b(outcome|baseline|before and after)\b/i',
                '/\b(has|did|have)\b.{0,25}\bimprove/i',
                '/\bany (change|difference|improvement)\b/i',
            ],
            'slots' => ['case'],
        ],

        /*
        | A fee question that wants the agent, not a table read.
        |
        | Deliberately a separate key rather than a mode of `fees_query`. `fees_query`
        | routes to the module's own read tools and must keep doing so — a question like
        | "who owes money" is answered by reading `fees.arrears`, and making it claim an
        | agent run would drop those tools and answer worse. This key exists for the other
        | kind of fee question: the one that asks the platform to *analyse* rather than
        | list, and so needs a case opened, evidence cited and a recommendation drafted.
        |
        | It is also what stops such a question being claimed by `student_risk_scan`.
        | "Which students are at risk of non-payment?" scores highly for the student
        | module on the bare word "students", and used to resolve there — running the
        | academic-risk agent, whose detectors know nothing about money, against a
        | question about fees. Every anchor here names money, so the fees module wins the
        | sentence outright.
        */
        'fees_risk_scan' => [
            'label' => 'Analyse fee payment risk',
            'description' => 'Runs the fees agent across the students in scope: opens a case per '
                . 'student carrying arrears, cites the unpaid heads, and drafts a collection review.',
            'anchors' => ['fee', 'fees', 'payment', 'payments', 'defaulter', 'defaulters', 'arrears', 'dues'],
            'signals' => [
                'payment risk' => 5.0, 'non-payment' => 5.0, 'non payment' => 5.0,
                'fee risk' => 5.0, 'defaulter analysis' => 5.0, 'defaulter report' => 4.0,
                'analyse' => 2.5, 'analyze' => 2.5, 'assess' => 2.5, 'scan' => 2.5,
                'at risk' => 3.5, 'at-risk' => 3.5, 'likely to default' => 4.5,
                'will default' => 4.0, 'highest risk' => 3.5, 'collection review' => 4.0,
                'review' => 1.5, 'identify' => 1.5, 'which' => 1.0, 'who' => 1.0,
            ],
            'patterns' => [
                '/\b(analyse|analyze|assess|scan|review)\b.{0,40}\b(fee|fees|defaulters?|arrears|payment)\b/i',
                '/\b(fee|fees|payment)\b.{0,20}\brisk\b/i',
                '/\brisk of (non[\s-]?payment|default)\b/i',
                '/\b(likely to|will) default\b/i',
                '/\b(fee|fees)\b.{0,30}\bcollection review\b/i',
            ],
            'slots' => [],
        ],
        /*
        | Fee payment reminders: an ACTION, not a question about fees.
        |
        | `fees_query` also lists "reminder" and "send a fee reminder" among its phrases, so
        | before this key existed "send reminders to the defaulters" was answered by reading
        | out the list of defaulters — which looked like a reminder having been sent and was
        | not. Every sentence that asks for something to be SENT, with a fee noun beside it,
        | belongs here, where the answer is a preview that waits for a person's approval.
        |
        | Note what is NOT matched: "send a notification to parents about the holiday" names
        | no fee noun and is the Communication module's, and a bare "remind me" is not about
        | fees at all.
        */
        'fees_send_reminders' => [
            'label' => 'Send fee payment reminders',
            'description' => 'Previews, then on approval emails a payment reminder with a deadline to students who owe fees.',
            'anchors' => ['fee', 'fees', 'outstanding', 'defaulter', 'defaulters', 'owe', 'owing', 'dues', 'reminder', 'reminders'],
            // Signals name the thing, never just the verb: "notify" and "remind" on their
            // own score nothing here, because "notify parents about the holiday" and "remind
            // me to review attendance" are not about fees. The patterns below do the work,
            // and each of them requires a fee noun.
            'signals' => [
                'send a fee reminder' => 5.0, 'send fee reminders' => 5.0, 'fee reminder' => 3.0, 'fee reminders' => 3.0,
                'payment reminder' => 3.0, 'payment reminders' => 3.0, 'pay by' => 2.0, 'pending fees' => 1.0,
            ],
            'patterns' => [
                // A sending verb, then a fee noun a few words on.
                '/\b(send|email|mail|notify|remind|alert|message|text)\b.{0,60}\b(fees?|outstanding|owe|owes|owing|dues?|defaulters?|arrears|unpaid)\b/i',
                // The same, with the order reversed: "defaulters ... send them a reminder".
                '/\b(fees?|outstanding|dues?|defaulters?|arrears|unpaid)\b.{0,60}\b(send|email|mail|notify|remind|alert)\b/i',
                '/\b(send|give)\b.{0,15}\b(a\s+)?(fee|payment)\s+reminders?\b/i',
                // "Prepare a WhatsApp fee reminder for this student's parent", "draft an SMS fee reminder".
                '/\b(prepare|draft|compose|create|write|set up)\b.{0,30}\b(fee|payment)\s+reminders?\b/i',
                '/\bwhats\s?app\b.{0,40}\b(fee|payment)\s+reminders?\b/i',
                '/\bremind\b.{0,50}\b(to\s+pay|pay|fees?|dues?)\b/i',
                // A notifying verb and a fee noun anywhere in the sentence, in either order:
                // "notify students whose fees are outstanding", "email the defaulters about ...".
                '/^(?=.*\b(fees?|outstanding|dues?|defaulters?|arrears|unpaid|owe|owes|owing)\b).*\b(notify|remind|email|mail|alert)\b/i',
                '/^(?=.*\b(fees?|outstanding|dues?|defaulters?|arrears|unpaid|owe|owes|owing)\b).*\bsend\b.*\b(reminders?|notifications?|notices?|messages?|emails?|sms|texts?)\b/i',
                // "pending fees" is also what the read intent weighs most, so a request to notify about
                // them needs its own say to be heard as the action it is. Both still require the verb.
                '/^(?=.*\b(pending|outstanding|unpaid|overdue|due)\b)(?=.*\b(fees?|dues?|payments?)\b).*\b(notify|remind|email|mail|alert)\b/i',
                '/\b(notify|remind|email|mail|alert)\b.{0,40}\b(students?|parents?|guardians?|families|defaulters?)\b.{0,60}\b(fees?|dues?|outstanding|unpaid|owe|owes|owing|pay)\b/i',
            ],
            'slots' => [],
        ],

        /*
        | Whether those reminders went. A question about a record of past actions, answered
        | from the ledger the sending writes to — never from the list of defaulters.
        |
        | It needs the word "reminder" (or a "fee" notification), so "did the notifications go
        | out?" on the Communication screen stays Communication's question.
        */
        'fees_reminder_status' => [
            'label' => 'Fee reminder status',
            'description' => 'Shows which fee reminders were sent or failed, from the record of past sends.',
            'anchors' => ['reminder', 'reminders'],
            'signals' => [
                'reminder status' => 7.0, 'reminders status' => 7.0, 'reminder history' => 7.0,
                'history of fee reminders' => 7.0, 'fee reminder status' => 7.5, 'fee notification status' => 6.0,
                'reminders sent' => 5.0, 'reminders failed' => 5.5, 'were the reminders' => 5.5,
                'did the reminders' => 5.5, 'were the fee reminders' => 6.0, 'did the fee reminders' => 6.0,
            ],
            'patterns' => [
                '/\b(status|history|log|record)\b.{0,40}\b(fee\s+)?reminders?\b/i',
                '/\b(fee\s+)?reminders?\b.{0,30}\b(status|history|log)\b/i',
                '/\b(did|were|have|has|was)\b.{0,40}\breminders?\b.{0,40}\b(sent|send|go|went|deliver\w*|reach\w*|fail\w*)\b/i',
                '/\b(which|what|how many)\b.{0,30}\breminders?\b.{0,30}\b(sent|failed|bounced)\b/i',
                // "Was the SMS delivered?", "Did the message reach them?", "Has the reminder been received?"
                // The Fees planner takes it only on the Fees module, so on any other screen it is left alone.
                '/\b(was|were|is|are|did|has|have)\b.{0,30}\b(sms|whats\s?app|text|e-?mail|message|reminder)s?\b.{0,30}\b(deliver\w*|received|reach\w*|arriv\w*|read|seen)\b/i',
                '/\b(delivery|delivered)\b.{0,25}\b(status|report|confirmation|of the (sms|reminder|message))\b/i',
                '/\b(report|summary|list)\b.{0,30}\b(fee\s+)?reminders?\b/i',
                '/\bstudents?\W{0,2}s?\s+(sms|fee|text)\s+(notifications?|messages?|reminders?)\b.{0,25}\b(failed|sent|bounced|went)\b/i',
                '/\b(which|what|how many)\b.{0,40}\b(sms|text)\s+(notifications?|messages?)\b.{0,25}\b(failed|sent|bounced)\b/i',
            ],
            'slots' => [],
        ],
        'fees_query' => [
            'label' => 'Answer a fee query',
            'description' => 'Recognises fees, pending payments, defaulters, fee collection, reminders, and fee summary questions.',
            'anchors' => ['fee', 'fees', 'payment', 'payments', 'invoice', 'invoices', 'receipt', 'receipts', 'defaulter', 'defaulters', 'collection', 'demand', 'dues', 'outstanding', 'unpaid'],
            'signals' => [
                'pending fee' => 5.0, 'pending fees' => 5.0, 'outstanding' => 4.0,
                'outstanding dues' => 4.5, 'defaulter' => 4.5, 'defaulters' => 4.5,
                'arrears' => 4.0, 'unpaid' => 4.0, 'due' => 2.0, 'collection' => 2.5,
                'total fee collection' => 4.0, 'what is the total fee collection' => 5.0,
                'fee summary' => 4.5, 'fee report' => 4.0, 'collection report' => 4.0,
                'overdue' => 3.5, 'outstanding amount' => 4.0, 'what is due' => 4.0,
                'balance' => 3.0, 'paid' => 2.0, 'refund' => 3.0,
                'reminder' => 2.5, 'reminders' => 2.5, 'remind' => 2.5,
                'details' => 2.0, 'detail' => 2.0,
                'which' => 1.0, 'who' => 1.0, 'show' => 1.0, 'list' => 1.0,
                'find' => 1.0,
            ],
            'patterns' => [
                '/\b(which|what|show|list|find|any)\b.{0,30}\b(fee|fees|payment|payments|invoice|invoices|receipt|receipts)\b/i',
                '/\b(pending fees?|unpaid fees?|outstanding dues?|defaulters?|arrears?|fee collection)\b/i',
                '/\b(student|students|child|children|kid|kids|learner|learners|pupil)\b.{0,40}\b(pending fees?|unpaid fees?|outstanding dues?|defaulters?|arrears?)\b/i',
                '/\bhow (much|many)\b.{0,40}\b(fee|fees|due|dues|outstanding|arrears|payment|payments)\b/i',
                '/\b(fee|fees|due|dues|amount|balance)\b.{0,20}\b(is|are|remains?|still)\b.{0,15}\b(pending|unpaid|outstanding|due|owed)\b/i',
                // Direct fee-specific phrases that don't fit generic patterns.
                '/\bfee\s+(details?|detail)\b/i',
                '/\bfee\s+summary\b/i',
                '/\bfee\s+reminder\b/i',
                '/\bsend\s+(a\s+)?fee\s+reminder\b/i',
                '/\bcollection\s+report\b/i',
            ],
            'slots' => [],
        ],

        /*
        | An attendance question that wants the agent, not a table read.
        |
        | The same split `fees_risk_scan` makes against `fees_query`, and for the same
        | reason. "What is 8B's attendance?" is answered by reading `attendance.overview`,
        | and claiming an agent run for it would drop that tool and answer worse. This key
        | is for the other kind of question — the one that asks the platform to *act on*
        | poor attendance rather than report it — which needs a case opened, the recorded
        | absences cited, and a follow-up drafted for somebody to approve.
        |
        | It also has to win the sentence against `student_risk_scan`, which scores highly
        | on the bare word "students" and would otherwise run the academic-risk agent —
        | whose detectors read assessments and homework as well as attendance, and whose
        | recommendation is an academic intervention rather than a conversation about
        | absence. Every anchor here names attendance, so an attendance sentence resolves
        | here outright.
        |
        | Note what is NOT an anchor: "below 75", "low" and "worst" on their own. A
        | question phrased only as a threshold is ambiguous between marks and attendance,
        | and an intent that grabbed it would answer the wrong question confidently. Such a
        | sentence falls through to whichever module its other words name, which is the
        | honest outcome. A threshold that appears *beside the word attendance* is a
        | different matter and is matched by the last two patterns below — "students with
        | attendance below 75%" names its own subject and is the single most common way
        | this question is asked.
        */
        'attendance_risk_scan' => [
            'label' => 'Analyse attendance risk',
            'description' => 'Runs the attendance agent across the students in scope: opens a case per '
                . 'student attending below the bar, cites the days they were recorded absent, and drafts '
                . 'a follow-up for approval.',
            'anchors' => ['attendance', 'absent', 'absence', 'absences', 'absenteeism', 'attending', 'truancy'],
            'signals' => [
                'attendance risk' => 5.0, 'poor attendance' => 5.0, 'low attendance' => 5.0,
                'attendance below' => 5.0, 'attendance under' => 5.0,
                'attendance problem' => 4.5, 'chronic absence' => 5.0, 'chronically absent' => 5.0,
                'persistent absence' => 5.0, 'absenteeism' => 4.5, 'truancy' => 4.5,
                'attendance follow-up' => 4.5, 'attendance follow up' => 4.5,
                'missing school' => 4.5, 'not attending' => 4.0, 'keeps missing' => 4.0,
                'analyse' => 2.5, 'analyze' => 2.5, 'assess' => 2.5, 'scan' => 2.5,
                'at risk' => 3.5, 'at-risk' => 3.5, 'flag' => 2.0, 'follow up' => 2.0,
                'review' => 1.5, 'identify' => 1.5, 'which' => 1.0, 'who' => 1.0,
            ],
            'patterns' => [
                '/\b(analyse|analyze|assess|scan|review|flag)\b.{0,40}\b(attendance|absence|absences|absenteeism|truancy)\b/i',
                '/\b(attendance|absence)\b.{0,20}\brisk\b/i',
                // And the other way round. "Who is at risk on attendance?" is an
                // attendance sentence, but the words arrive in the order `student_risk_scan`
                // is built for, so without this it resolves there and runs the
                // academic-risk agent against a question about absence.
                '/\brisk\b.{0,25}\b(attendance|absence|absences|absenteeism)\b/i',
                '/\b(poor|low|chronic|persistent|concerning)\b.{0,15}\battendance\b/i',
                '/\b(chronically|persistently|repeatedly)\s+absent\b/i',
                '/\b(follow[\s-]?up|action)\b.{0,30}\battendance\b/i',
                '/\battendance\b.{0,30}\bfollow[\s-]?up\b/i',
                // A threshold, but only where "attendance" says what it is a threshold on.
                '/\battendance\b.{0,25}\b(below|under|less than|lower than|worse than)\b/i',
                '/\b(below|under|less than)\b.{0,25}\battendance\b/i',
            ],
            'slots' => [],
        ],

        /*
        | An admission question that wants the agent, not a table read.
        |
        | Deliberately a separate key rather than a mode of `admission_enquiry_list`, for
        | exactly the reason `fees_risk_scan` is separate from `fees_query`. Listing the
        | enquiries is answered by reading `admissions.listEnquiries`, and making that
        | route claim an agent run would drop the tool and answer worse. This key is for
        | the other kind of admission question: the one that asks the platform to
        | *analyse* rather than list, and so needs a case opened, the recorded follow-up
        | date cited as evidence, and a follow-up drafted for a person to approve.
        |
        | WHY IT HAD TO EXIST
        |
        | Without it, "analyse the pending admission enquiries and identify which
        | applicants need follow-up" scored as `admission_enquiry_list` on the bare words
        | "admission" and "enquiries", and came back as a list of ten rows with the Agent
        | rung reported as not needed. The agent was registered, bound and runnable from
        | the AI Stack, and no typed sentence could reach it — which is the same gap
        | `attendance_risk_scan` was added to close for the register.
        |
        | The anchors all name admissions, so a fee or attendance sentence cannot land
        | here; and `DeterministicPlanner::plan()` still declines the agent route outright
        | for a module with no verified agent, so this key is inert on an estate that has
        | not registered `k12_admissions`.
        */
        'admission_followup_scan' => [
            'label' => 'Analyse admission follow-ups',
            'description' => 'Runs the admissions agent across the enquiries in scope: opens a case per '
                . 'enquiry past the follow-up date the school recorded for it, cites that date and what '
                . 'the confirmation check still wants, and drafts a follow-up for approval.',
            'anchors' => ['admission', 'admissions', 'enquiry', 'enquiries', 'applicant', 'applicants'],
            'signals' => [
                'admission follow-up' => 5.0, 'admission follow up' => 5.0,
                'enquiry follow-up' => 5.0, 'enquiry follow up' => 5.0,
                // These weights are what separate this key from `admission_enquiry_list`,
                // and they are deliberately higher than the equivalents on the other scan
                // intents. Both admission keys share every anchor — "admission",
                // "enquiry", "applicant" — so the anchors cannot break the tie and the
                // verbs have to. None of these words appears in `admission_enquiry_list`
                // at all, so raising them can only ever move an *analyse* sentence; a
                // "show me the enquiries" sentence scores zero on all of them and is
                // unaffected. Measured: analyse-and-follow-up 30.5 here against 28.0
                // there, and "show me the admission enquiries" 1.0 here against 13.5.
                'follow-up' => 4.5, 'follow up' => 4.5, 'chase' => 3.5, 'chased' => 3.5,
                'stalled' => 4.5, 'gone quiet' => 4.5, 'no response' => 3.5,
                'overdue' => 4.0, 'not come back' => 4.0, 'never replied' => 4.0,
                'need attention' => 3.0, 'needs attention' => 3.0,
                'analyse' => 4.0, 'analyze' => 4.0, 'assess' => 4.0, 'scan' => 4.0,
                'at risk' => 3.0, 'at-risk' => 3.0, 'review' => 1.5, 'identify' => 1.5,
                'which' => 1.0, 'who' => 1.0,
            ],
            'patterns' => [
                // The sentence this key exists for: analyse, over admissions.
                '/\b(analyse|analyze|assess|scan|review|flag)\b.{0,40}\b(admission|admissions|enquir|applicant)/i',
                // And the follow-up phrasings, either way round.
                '/\b(admission|enquir|applicant)\w*\b.{0,30}\bfollow[\s-]?up\b/i',
                '/\bfollow[\s-]?up\b.{0,30}\b(admission|enquir|applicant)/i',
                '/\b(admission|enquir|applicant)\w*\b.{0,25}\b(overdue|stalled|gone quiet|no response)\b/i',
                '/\b(overdue|stalled)\b.{0,25}\b(admission|enquir|applicant)/i',
                // "…enquiries that require follow-up", with or without a leading question
                // word. The earlier version demanded "which/who/what" first and so missed
                // every imperative phrasing — "Find the enquiries that require follow-up".
                '/\b(admission|enquir|applicant)\w*\b.{0,40}\b(need|needs|require|requires|requiring|awaiting|waiting)\b.{0,20}\bfollow/i',
                // "Which admission enquiries have gone quiet?" — the question word is far
                // from the phrase that makes it an analysis, so the two are matched
                // together rather than relying on either alone.
                '/\b(which|what|who)\b.{0,45}\b(gone quiet|no response|not responded|unanswered|overdue|stalled)\b/i',
                // All three elements at once: a question word, an admission noun, and a
                // phrase that asks about the state of the follow-up rather than for a
                // list. `admission_enquiry_list` carries two patterns that both fire on
                // any "which … enquiries" sentence, which is correct for it and is why a
                // question like "which admission enquiries need follow-up?" needed a
                // match this specific to be recognised as the analysis it is. Nothing
                // without all three parts can reach it, so "show me the enquiries" is
                // untouched.
                '/\b(which|what|who)\b.{0,40}\b(admission|enquir|applicant)\w*\b.{0,40}\b(gone quiet|no response|not responded|unanswered|overdue|stalled|follow[\s-]?up|chas(e|ing|ed))\b/i',
            ],
            'slots' => [],
        ],

        'admission_enquiry_list' => [
            'label' => 'List admission enquiries',
            'description' => 'Shows the admission enquiries on record and which are still pending.',
            'anchors' => ['admission', 'admissions', 'enquiry', 'enquiries', 'applicant', 'applicants'],
            'signals' => [
                'admission' => 3.0, 'admissions' => 3.0, 'enquiry' => 3.5, 'enquiries' => 3.5,
                'applicant' => 3.0, 'applicants' => 3.0, 'pending admission' => 5.0,
                'admission list' => 4.5, 'new admission' => 3.5, 'registration' => 2.0,
            ],
            'patterns' => [
                '/\b(which|what|show|list|any)\b.{0,30}\b(admission|enquir|applicant)/i',
                '/\badmission (enquiries|enquiry|list)\b/i',
            ],
            'slots' => [],
        ],

        'admission_confirm' => [
            'label' => 'Confirm an admission',
            'description' => 'Collects any missing details and, once a person approves, confirms the admission.',
            'anchors' => ['confirm', 'admission', 'enquiry', 'admit', 'enrol', 'enroll'],
            'signals' => [
                'confirm admission' => 6.0, 'confirm the admission' => 6.0,
                'admission confirm' => 5.5, 'admit' => 4.0, 'enrol' => 3.5, 'enroll' => 3.5,
                'finalise admission' => 5.0, 'finalize admission' => 5.0,
                'complete admission' => 5.0, 'confirm' => 2.5, 'admission' => 2.0,
            ],
            'patterns' => [
                '/\bconfirm\b.{0,25}\badmission\b/i',
                '/\badmission\b.{0,25}\bconfirm/i',
                '/^\s*(please\s+)?(confirm|admit|enrol|enroll)\b.{0,30}\b(enquiry|admission|applicant)\b/i',
            ],
            'slots' => ['enquiry'],
        ],

        /*
        | The two intents below carry no anchors, signals or patterns, and that is not an
        | oversight — it is what makes them safe.
        |
        | "Show the details of the first candidate" and "show enquiries with new status"
        | cannot be read from the words alone. There is no such thing as "the first
        | candidate" until a previous answer has listed candidates, and a classifier that
        | tried to match them on wording would fire on a thread that never showed a list
        | and route to a record nobody chose. So they score zero and are unreachable from
        | `classify()`; the only thing that can produce them is `ConversationStore`
        | resolving the sentence against the rows the previous turn actually returned.
        |
        | They are declared here rather than invented in the store so that the catalogue
        | the console publishes stays the complete list of what the system understands.
        */
        'record_detail' => [
            'label' => 'Show one record from the previous answer',
            'description' => 'Opens a row the last answer listed, selected by position or by the name shown.',
            'anchors' => [],
            'signals' => [],
            'patterns' => [],
            'slots' => [],
        ],

        'record_filter' => [
            'label' => 'Narrow the previous answer',
            'description' => 'Filters the rows the last answer returned by a value those rows hold.',
            'anchors' => [],
            'signals' => [],
            'patterns' => [],
            'slots' => [],
        ],

        /*
        | A message to parents by SMS or WhatsApp. Never about fees: a fee notice has its own flow, with its
        | own amounts and deadline, so every pattern here refuses a sentence that carries a fee word ("due" alone is not one: "closed due to rain") and
        | leaves it to `fees_send_reminders`. The planner also declines this intent on any module that does
        | not bind `communication.send_parent_message`, so a stray match elsewhere answers nothing and
        | sends nothing.
        */
        'parent_message_send' => [
            'label' => 'Message parents by SMS or WhatsApp',
            'description' => 'Previews, then on approval sends an SMS or WhatsApp message to the parents of a class or a student.',
            'anchors' => ['sms', 'whatsapp', 'parents', 'parent', 'guardians'],
            'signals' => [
                // Only phrases that name the parents. A bare "send an SMS" is also how a fee follow-up
                // begins ("send an SMS to all 10 by 31 October"), so it must not score here.
                'sms to parents' => 3.0, 'whatsapp to parents' => 3.0, 'message the parents' => 3.0,
                'notify the parents' => 3.0, 'notify parents' => 3.0,
            ],
            'patterns' => [
                '/^(?!\s*(?:was|were|did|do|does|has|have|had|is|are|how|which|what|why|who|when)\b)(?!.*\b(fees?|dues|outstanding|unpaid|arrears|defaulters?|owe[sd]?|owing)\b).*\b(sms|whats\s?app|text|message|notify|inform|tell|alert|send)\b.{0,60}\b(parents?|guardians?)\b/i',
                '/^(?!\s*(?:was|were|did|do|does|has|have|had|is|are|how|which|what|why|who|when)\b)(?!.*\b(fees?|dues|outstanding|unpaid|arrears|defaulters?|owe[sd]?|owing)\b).*\b(parents?|guardians?)\b.{0,60}\b(sms|whats\s?app)\b/i',
                '/^(?!\s*(?:was|were|did|do|does|has|have|had|is|are|how|which|what|why|who|when)\b)(?!.*\b(fees?|dues|outstanding|unpaid|arrears|defaulters?|owe[sd]?|owing)\b).*\b(sms|whats\s?app)\b.{0,50}\b(class|standard|grade|std)\s*\d/i',
                // The two options by name, as the whole sentence: "Send SMS", "Send WhatsApp Message".
                '/^\s*(please\s+)?(send|open|start)\s+(an?\s+)?(sms|whats\s?app(\s+message)?)\s*$/i',
                // The third option: "Send SMS and WhatsApp".
                '/^\s*(please\s+)?(send|open|start)\s+(an?\s+)?(sms\s*(and|&|\+)\s*whats\s?app(\s+message)?|whats\s?app(\s+message)?\s*(and|&|\+)\s*sms)\s*$/i',
                // "Send SMS to Aarav Shah": a name straight after the channel. No digit and no group word, so a
                // fee follow-up such as "send an SMS to all 10 by 31 October" is never taken.
                '/^(?!.*\d)(?!.*\b(fees?|dues|outstanding|unpaid|arrears|defaulters?|owe[sd]?|owing)\b)\s*(please\s+)?(send|text)\s+(an?\s+)?(sms|whats\s?app(\s+message)?)\s+to\s+(?!(all|them|him|her|these|those|everyone|the|class|parents?|guardians?|each)\b)[a-z]+/i',
            ],
            'slots' => [],
        ],

        /*
        | Whether the SMS / WhatsApp messages sent to parents went. Needs a channel or "parent message" so it never
        | takes a fee-reminder question (those say "reminder" and have their own status intent) or a Communication
        | report question that names no send. The planner takes it only where `communication.parent_message_history`
        | is bound.
        */
        'parent_message_status' => [
            'label' => 'Parent message status',
            'description' => 'Shows which SMS or WhatsApp messages to parents were sent, failed or delivered, from the saved record.',
            'anchors' => ['sms', 'whatsapp', 'message', 'messages'],
            'signals' => [
                'message status' => 4.0, 'messages status' => 4.0, 'sms status' => 4.5, 'whatsapp status' => 4.5,
                'parent message status' => 6.0,
            ],
            'patterns' => [
                '/^(?!.*\breminders?\b)(?!.*\b(fees?|dues|outstanding)\b).*\b(status|history|log|record)\b.{0,25}\b(sms|whats\s?app|parent)\s*(messages?|texts?)?\b/i',
                '/^(?!.*\breminders?\b)(?!.*\b(fees?|dues|outstanding)\b).*\b(sms|whats\s?app)\b.{0,25}\b(status|history)\b/i',
                '/^(?!.*\breminders?\b)(?!.*\b(fees?|dues|outstanding)\b).*\b(was|were|is|are|did|has|have)\b.{0,30}\b(the\s+)?(sms|whats\s?app|text|message)s?\b.{0,30}\b(sent|deliver\w*|received|reach\w*|read|seen|fail\w*)\b/i',
                '/^(?!.*\breminders?\b)(?!.*\b(fees?|dues|outstanding)\b)\s*(show|list|view)\b.{0,15}\b(message|sms|whats\s?app)\s+(status|history)\b/i',
            ],
            'slots' => [],
        ],

        /*
        | "Check whether the WhatsApp configuration and template are valid", "is SMS set up properly", "why are the messages not arriving".
        | Asked of the providers; the planner takes it only where `communication.channel_health` is bound.
        */
        'channel_health' => [
            'label' => 'Check messaging channels',
            'description' => 'Checks whether SMS and WhatsApp are configured and able to reach parents, asking the providers themselves.',
            'anchors' => ['sms', 'whatsapp'],
            'signals' => [
                'whatsapp configuration' => 5.0, 'sms configuration' => 5.0, 'whatsapp template valid' => 5.0,
                'not arriving' => 4.0, 'did not arrive' => 4.0, 'didnt arrive' => 4.0, "didn't arrive" => 7.0, 'not arrive' => 4.0,
                'sms arrive' => 3.0, 'whatsapp arrive' => 3.0, 'message arrive' => 3.0,
            ],
            'patterns' => [
                '/\b(check|verify|validate|test|diagnose|inspect)\b.{0,50}\b(whats\s?app|sms|messaging|channels?)\b.{0,50}\b(config\w*|set\s?up|template|credentials?|provider|gateway|valid|working|health|status)\b/i',
                '/\b(is|are)\b.{0,20}\b(whats\s?app|sms)\b.{0,30}\b(config\w*|set\s?up|working|valid|connected)\b/i',
                '/\bwhy\b.{0,30}\b(sms|whats\s?app|messages?)\b.{0,30}\b(not|never|n\x27t|didn\x27t|didnt|haven\x27t|havent)\b.{0,20}\b(arriv\w*|deliver\w*|reach\w*|receiv\w*|work\w*)\b/i',
                '/\b(sms|whats\s?app|messages?)\b.{0,30}\b(not|never|n\x27t|didn\x27t|didnt|haven\x27t|havent)\s+(arriv\w*|deliver\w*|reach\w*|receiv\w*)\b/i',
            ],
            'slots' => [],
        ],

        'learning_effectiveness' => [
            'label' => 'What the system has learned',
            'description' => 'Aggregate effectiveness of each action type, from measured outcomes.',
            'anchors' => ['learn', 'learned', 'effective', 'effectiveness', 'history', 'usually'],
            'signals' => [
                'learn' => 4.5, 'learned' => 5.0, 'learning' => 5.0,
                'effective' => 5.0, 'effectiveness' => 5.5, 'success rate' => 5.0,
                'how often' => 4.0, 'usually' => 3.0, 'historically' => 4.0,
                'track record' => 4.5, 'does this work' => 3.5, 'in general' => 2.5,
                'across students' => 3.0, 'over time' => 3.0,
            ],
            'patterns' => [
                '/\b(what has|what have)\b.{0,25}\blearn/i',
                '/\b(effectiveness|success rate|track record)\b/i',
                '/\bhow (well|often)\b.{0,30}\b(work|works|worked|effective)\b/i',
            ],
            'slots' => [],
        ],

        'lms_course_query' => [
            'label' => 'Course count or catalogue query',
            'description' => 'Find how many or which courses/subjects are available for a standard or grade.',
            'anchors' => ['course', 'courses', 'subject', 'subjects', 'standard', 'grade', 'class', 'std', 'corse', 'ધોરણ', 'વિષય', 'કોર્સ', 'કક્ષા', 'पाठ्यक्रम'],
            'signals' => [
                'how many courses' => 5.0, 'course count' => 4.5, 'number of courses' => 4.5,
                'courses available' => 4.0, 'which courses' => 4.0, 'what courses' => 4.0,
                'list courses' => 4.0, 'show courses' => 4.0, 'all courses' => 3.5,
                'courses in standard' => 5.0, 'courses in grade' => 5.0, 'courses in class' => 5.0,
                'available courses' => 4.0, 'available in this standard' => 5.5,
                'courses are available' => 4.5, 'how many courses are available' => 6.0,
                'available in standard' => 5.0, 'available in grade' => 5.0,
                'in this standard' => 4.0, 'in this class' => 4.0, 'in this grade' => 4.0,
                'how many course' => 5.0, 'courses in this' => 4.5,
                'કેટલા કોર્સ' => 5.0, 'કોર્સ ઉપલબ્ધ' => 4.5, 'કેટલા વિષયો' => 4.0, 'કયા કોર્સ' => 4.5,
                'આ ધોરણમાં' => 4.5, 'ધોરણમાં કેટલા' => 4.5, 'કેટલા કોર્સ ઉપલબ્ધ' => 5.5, 'કુલ કોર્સ' => 4.0,
                'કેટલા કોર્સ છે' => 5.5, 'આ ધોરણમાં કેટલા કોર્સ' => 6.0,
                'कितने कोर्स' => 5.0, 'कोर्स उपलब्ध' => 4.5, 'कितने विषय' => 4.0, 'कौन से कोर्स' => 4.5,
                'इस कक्षा में' => 4.5, 'कक्षा में कितने' => 4.5, 'कितने कोर्स उपलब्ध' => 5.5, 'कुल कोर्स' => 4.0,
                'कितने कोर्स हैं' => 5.5, 'इस कक्षा में कितने कोर्स' => 6.0,
            ],
            'patterns' => [
                '/\bhow\s+many\s+courses?\b/i',
                '/\b(?:number|count)\s+of\s+courses?\b/i',
                '/\b(?:which|what|list|show)\s+(?:all\s+)?courses?\b/i',
                '/\bcourses?\s+(?:are\s+)?available\b/i',
                '/\bcourses?\s+(?:in|for)\s+(?:standard|grade|class|std|this)\b/i',
                '/\bavailable\s+in\s+this\s+(?:standard|grade|class)\b/i',
                '/કેટલા\s+કોર્સ/u',
                '/કોર્સ\s+ઉપલબ્ધ/u',
                '/આ\s+ધોરણમાં\s+કેટલા/u',
                '/ધોરણ\s*\d+.*કેટલા\s+કોર્સ/u',
                '/कितने\s+कोर्स/u',
                '/कोर्स\s+उपलब्ध/u',
                '/इस\s+कक्षा\s+में\s+कितने/u',
                '/कक्षा\s*\d+.*कितने\s+कोर्स/u',
            ],
            'slots' => ['standard', 'query_type'],
        ],

        'lms_subject_query' => [
            'label' => 'Subject list query',
            'description' => 'Find which subjects are included or available in a standard or course catalogue.',
            'anchors' => ['subject', 'subjects', 'included', 'include', 'taught', 'sub', 'વિષય', 'વિષયો', 'સમાવિષ્ટ', 'विषय', 'शामिल'],
            'signals' => [
                'which subjects' => 5.0, 'what subjects' => 4.5, 'subjects are included' => 5.5,
                'subjects included' => 5.0, 'which subjects are included' => 6.0,
                'list subjects' => 4.0, 'show subjects' => 4.0, 'all subjects' => 4.0,
                'what are the subjects' => 5.0, 'subjects in this' => 4.5, 'subjects in standard' => 4.5,
                'included in' => 4.0, 'are included' => 4.5,
                'કયા વિષયો' => 5.0, 'વિષયો સમાવિષ્ટ' => 5.0, 'કયા વિષયો સમાવિષ્ટ છે' => 6.0, 'બધા વિષયો' => 4.5,
                'કયા વિષયો છે' => 5.0, 'કયા કયા વિષયો' => 5.0,
                'कौन से विषय' => 5.0, 'विषय शामिल' => 5.0, 'कौन से विषय शामिल हैं' => 6.0, 'सभी विषय' => 4.5,
                'कौन कौन से विषय' => 5.0,
            ],
            'patterns' => [
                '/\bwhich\s+subjects?(?:\s+are)?\s+included\b/i',
                '/\b(?:which|what|list|show)\s+(?:all\s+)?subjects?\b/i',
                '/\bsubjects?\s+(?:in|for)\s+(?:standard|grade|class|std|this)\b/i',
                '/\bwhat\s+are\s+the\s+subjects?\b/i',
                '/કયા\s+વિષયો/u',
                '/વિષયો\s+સમાવિષ્ટ/u',
                '/कौन\s+से\s+विषय/u',
                '/विषय\s+शामिल/u',
            ],
            'slots' => ['standard', 'query_type'],
        ],

        'lms_chapter_query' => [
            'label' => 'Chapter query',
            'description' => 'Find chapter count or list chapters for a specific course or subject.',
            'anchors' => ['chapter', 'chapters', 'lesson', 'lessons', 'unit', 'units', 'chp', 'ch', 'પ્રકરણ', 'પ્રકરણો', 'અધ્યાય', 'पाठ'],
            'signals' => [
                'how many chapters' => 5.0, 'chapter count' => 4.5, 'number of chapters' => 4.5,
                'which chapters' => 4.5, 'what chapters' => 4.0, 'show all the chapters' => 5.5,
                'show me all the chapters' => 6.0, 'list chapters' => 4.5, 'all the chapters' => 4.5,
                'all chapters' => 4.5, 'chapters in' => 4.0, 'chapters of' => 4.0,
                'chapters available' => 4.0, 'chapters are available' => 4.5,
                'how many chapters are available' => 6.0, 'available in this course' => 5.0,
                'in this course' => 4.0, 'in science' => 4.0, 'in mathematics' => 4.0,
                'show all chapters' => 5.0, 'list all chapters' => 5.0,
                'કેટલા પ્રકરણ' => 5.0, 'કેટલા પ્રકરણો' => 5.0, 'કયા પ્રકરણો' => 4.5, 'બધા પ્રકરણો' => 5.0,
                'બધા પ્રકરણો બતાવો' => 5.5, 'આ કોર્સમાં કયા પ્રકરણો' => 5.5, 'પ્રકરણો બતાવો' => 4.5,
                'કેટલા પ્રકરણ છે' => 5.0, 'કેટલા પ્રકરણો ઉપલબ્ધ છે' => 6.0,
                'कितने अध्याय' => 5.0, 'कितने पाठ' => 5.0, 'कौन से अध्याय' => 4.5, 'सभी अध्याय' => 5.0,
                'सभी अध्याय दिखाएं' => 5.5, 'इस कोर्स में कौन से अध्याय' => 5.5, 'अध्याय दिखाएं' => 4.5,
                'कितने अध्याय हैं' => 5.0, 'कितने अध्याय उपलब्ध हैं' => 6.0,
            ],
            'patterns' => [
                '/\bhow\s+many\s+chapters?\b/i',
                '/\b(?:number|count)\s+of\s+chapters?\b/i',
                '/\b(?:which|what|list|show|display)\s+(?:me\s+)?(?:all\s+(?:the\s+)?)?chapters?\b/i',
                '/\bchapters?\s+(?:are\s+)?available\b/i',
                '/\bchapters?\s+(?:in|of|for)\b/i',
                '/કેટલા\s+પ્રકરણ/u',
                '/કયા\s+પ્રકરણ/u',
                '/બધા\s+પ્રકરણ/u',
                '/કેટલા\s+અધ્યાય/u',
                '/कितने\s+अध्याय/u',
                '/कौन\s+से\s+अध्याय/u',
                '/सभी\s+अध्याय/u',
            ],
            'slots' => ['standard', 'subject', 'course', 'query_type'],
        ],

        'lms_topic_query' => [
            'label' => 'Topic query',
            'description' => 'Find topic count or list topics within a particular chapter.',
            'anchors' => ['topic', 'topics', 'concept', 'concepts', 'subtopic', 'subtopics', 'ટોપિક', 'ટોપિક્સ', 'વિષયાંગ', 'મુદ્દા', 'મુદ્દે', 'टॉपिक', 'टॉपिक्स'],
            'signals' => [
                'how many topics' => 5.0, 'topic count' => 4.5, 'number of topics' => 4.5,
                'which topics' => 4.5, 'what topics' => 4.0, 'show all topics' => 5.0,
                'list topics' => 4.5, 'topics in' => 4.0, 'topics of' => 4.0,
                'topics within' => 4.5, 'topics available' => 4.0, 'topics in this chapter' => 5.5,
                'topics within a particular chapter' => 6.0, 'available in this chapter' => 5.0,
                'in this chapter' => 4.5, 'topics for chapter' => 5.0, 'topics of chapter' => 5.0,
                'show topics' => 4.5, 'list all topics' => 5.0,
                'કેટલા ટોપિક' => 5.0, 'કયા ટોપિક' => 4.5, 'આ પ્રકરણમાં કયા ટોપિક' => 5.5,
                'ટોપિક્સ બતાવો' => 4.5, 'ટોપિક બતાવો' => 4.5, 'આ પ્રકરણમાં કેટલા ટોપિક' => 5.5,
                'કેટલા ટોપિક્સ' => 5.0,
                'कितने टॉपिक' => 5.0, 'कौन से टॉपिक' => 4.5, 'इस अध्याय में कौन से टॉपिक' => 5.5,
                'टॉपिक दिखाएं' => 4.5, 'इस अध्याय में कितने टॉपिक' => 5.5, 'कितने टॉपिक्स' => 5.0,
            ],
            'patterns' => [
                '/\bhow\s+many\s+topics?\b/i',
                '/\b(?:number|count)\s+of\s+topics?\b/i',
                '/\b(?:which|what|list|show|display)\s+(?:me\s+)?(?:all\s+(?:the\s+)?)?topics?\b/i',
                '/\btopics?\s+(?:are\s+)?available\b/i',
                '/\btopics?\s+(?:in|within|of|for)\b/i',
                '/કેટલા\s+ટોપિક/u',
                '/કયા\s+ટોપિક/u',
                '/આ\s+પ્રકરણમાં/u',
                '/कितने\s+टॉपिक/u',
                '/कौन\s+से\s+टॉपिक/u',
                '/इस\s+अध्याय\s+में/u',
            ],
            'slots' => ['chapter', 'subject', 'standard', 'query_type'],
        ],
    ];

    /**
     * Classify one utterance.
     *
     * @param  array<string, mixed>  $memory  Referents carried from earlier turns.
     */
    public function classify(string $utterance, array $memory = []): Intent
    {
        $normalised = $this->normalise($utterance);

        if ($normalised === '') {
            return $this->unknown();
        }

        // Extracted before any routing decision, and kept even when nothing matches.
        //
        // "Show me CASE-2026-000001" names a record unambiguously. Whether the sentence
        // resolves to an intent is a separate question from whether it identified a
        // record, and discarding the reference because the surrounding words were weak
        // throws away the one certain thing in the message.
        $slots = $this->extractSlots($utterance, $normalised);

        $scores = [];
        $matches = [];

        foreach (self::INTENTS as $key => $definition) {
            [$score, $matched] = $this->score($normalised, $definition);

            if ($score > 0) {
                $scores[$key] = $score;
                $matches[$key] = $matched;
            }
        }

        if ($scores === []) {
            return $this->unknown([], $slots);
        }

        arsort($scores);
        $best = array_key_first($scores);
        $bestScore = $scores[$best];

        // Confidence is the winner's share of everything that scored. A sentence that
        // fits one intent and nothing else scores near 1; a genuinely ambiguous one
        // sits near 0.5 and, if it drops below the floor, is refused rather than run.
        $confidence = $bestScore / max(0.001, array_sum($scores));

        // A decisive phrase match is worth more than the arithmetic suggests: "approve"
        // is not ambiguous just because the sentence also contains "student".
        if (! empty($matches[$best]['patterns'])) {
            $confidence = min(1.0, $confidence + 0.25);
        }

        if ($confidence < self::CONFIDENCE_FLOOR) {
            return $this->unknown(array_slice(array_keys($scores), 0, 3), $slots);
        }

        // Being the only intent that matched is not evidence. Without this an intent
        // could win on one generic word and report perfect confidence for it.
        if ($bestScore < self::MINIMUM_SCORE) {
            return $this->unknown(array_slice(array_keys($scores), 0, 3), $slots);
        }

        return new Intent(
            key: $best,
            label: self::INTENTS[$best]['label'],
            confidence: $confidence,
            slots: $slots,
            // The raw score travels with the match so the trace can show *why* an intent
            // won, not just that it did. A reader comparing two turns needs to be able
            // to tell a decisive match from a bare one.
            matched: $matches[$best] + ['score' => round($bestScore, 2), 'minimum_score' => self::MINIMUM_SCORE],
        );
    }

    /**
     * The intents this module understands — used by the console to show the user what
     * they can ask, and by the test plan as the list to work through.
     */
    public function catalogue(): array
    {
        return array_map(fn ($key, $definition) => [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'requires' => $definition['slots'],
        ], array_keys(self::INTENTS), self::INTENTS);
    }

    public static function requiredSlots(string $intentKey): array
    {
        return self::INTENTS[$intentKey]['slots'] ?? [];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array{0:float, 1:array{anchors:array, signals:array, patterns:array}}
     */
    private function score(string $normalised, array $definition): array
    {
        $matched = ['anchors' => [], 'signals' => [], 'patterns' => []];
        $score = 0.0;

        foreach ($definition['anchors'] as $anchor) {
            if ($this->contains($normalised, $anchor)) {
                $matched['anchors'][] = $anchor;
                $score += 0.5;
            }
        }

        foreach ($definition['signals'] as $term => $weight) {
            if ($this->contains($normalised, $term)) {
                $matched['signals'][] = $term;
                $score += $weight;
            }
        }

        foreach ($definition['patterns'] as $pattern) {
            if (preg_match($pattern, $normalised)) {
                $matched['patterns'][] = $pattern;
                $score += 6.0;
            }
        }

        return [$score, $matched];
    }

    /**
     * Whole-word containment, so "no" does not match "enrolment" and "do" does not
     * match "doing".
     */
    private function contains(string $haystack, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return false;
        }

        // For non-ASCII words (e.g. Gujarati, Hindi), use multibyte substring match
        if (preg_match('/[^\x00-\x7F]/', $needle)) {
            return mb_stripos($haystack, $needle) !== false;
        }

        return (bool) preg_match('/(?<![a-z])' . preg_quote($needle, '/') . '(?![a-z])/i', $haystack);
    }

    private function normalise(string $utterance): string
    {
        $value = mb_strtolower(trim($utterance));
        $value = str_replace(['’', '‘'], "'", $value);
        $value = preg_replace('/[^\p{L}\p{M}\p{N}\'\-\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Pull explicit referents out of the sentence: a case reference, a recommendation
     * reference, an enrolment number, a quoted name, or "student 42".
     *
     * Anything not found here is filled from conversation memory by ConversationStore,
     * and the trace shows which was which.
     */
    private function extractSlots(string $raw, string $normalised): array
    {
        $slots = [];

        if (preg_match('/\bCASE-\d{4}-\d+\b/i', $raw, $m)) {
            $slots['case_reference'] = strtoupper($m[0]);
        }

        if (preg_match('/\bREC-\d{4}-\d+\b/i', $raw, $m)) {
            $slots['recommendation_reference'] = strtoupper($m[0]);
        }

        if (preg_match('/\bRUN-\d{4}-\d+\b/i', $raw, $m)) {
            $slots['run_reference'] = strtoupper($m[0]);
        }

        // "case 12", "recommendation 7", "student 45"
        if (preg_match('/\bcase\s+#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['case_id'] = (int) $m[1];
        }

        if (preg_match('/\brecommendation\s+#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['recommendation_id'] = (int) $m[1];
        }

        if (preg_match('/\bstudent\s+(?:id\s+)?#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['student_id'] = (int) $m[1];
        }

        // "enquiry 21", "admission 21", "enquiry #21", "application 21"
        if (preg_match('/\b(?:enquiry|inquiry|admission|application)\s+(?:id\s+|no\.?\s+|number\s+)?#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['enquiry_id'] = (int) $m[1];
        }

        // The worked example labels students "Student A" — support it explicitly so the
        // documented walkthrough works verbatim. Checked before the name rule, because
        // "Student A" would otherwise be read as somebody called A.
        if (preg_match('/\bstudent\s+([a-z])\b(?!\w)/i', $raw, $m)) {
            $slots['student_label'] = 'Student ' . strtoupper($m[1]);
        }

        // A quoted name, or a capitalised run after a phrase that introduces a person.
        // The lead-in is matched case-insensitively ("Why is …" starts a sentence) while
        // the name itself must stay capitalised, so "why is she at risk" does not
        // produce a student called "she".
        if (preg_match('/["\']([^"\']{2,60})["\']/', $raw, $m)) {
            $slots['student_name'] = trim($m[1]);
        } elseif (! isset($slots['student_label'])
            // The lead-in is case-insensitive because it often starts a sentence; the
            // name stays case-sensitive so the match stops at the first lowercase word.
            // Without that split, "Why is Ravi Kumar at risk?" yields "Ravi Kumar at risk".
            && preg_match('/(?i:why is|why was|about|for|of)\s+([A-Z][\w.\-]*(?:\s+[A-Z][\w.\-]*){0,3})/', $raw, $m)) {
            $slots['student_name'] = trim($m[1]);
        } elseif (! isset($slots['student_label'])
            // A ranked result is a picker, so permit a bare displayed name as its
            // next message. ConversationStore still requires an exact list match.
            && preg_match('/^\s*([A-Z][\w.\-]*(?:\s+[A-Z][\w.\-]*){1,3})\s*$/', $raw, $m)) {
            $slots['student_name'] = trim($m[1]);
        }

        // Scope qualifiers the agent understands.
        if (preg_match('/\bclass\s+([\w\-]{1,20})\b/i', $raw, $m)) {
            $slots['class_hint'] = $m[1];
        }

        if ($this->contains($normalised, 'high risk') || $this->contains($normalised, 'critical')) {
            $slots['severity_filter'] = 'high';
        }

        // --- LMS & PAL Curriculum Slots Extraction ---

        // 1. Standard / Grade extraction
        if (preg_match('/(?:standard|std\.?|grade|class|cbse)\s*[-:]?\s*(\d{1,2}|[ivx]+)/i', $raw, $m)) {
            $slots['standard_name'] = $this->canonicalStandardNumber($m[1]);
        } elseif (preg_match('/(?:ધોરણ|કક્ષા)\s*[-:]?\s*(\d{1,2}|[ivx]+)/u', $raw, $m)) {
            $slots['standard_name'] = $this->canonicalStandardNumber($m[1]);
        } elseif (preg_match('/(?:कक्षा)\s*[-:]?\s*(\d{1,2}|[ivx]+)/u', $raw, $m)) {
            $slots['standard_name'] = $this->canonicalStandardNumber($m[1]);
        } elseif (preg_match('/\b(?:standard|grade|class)\s+(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|eleventh|twelfth|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\b/i', $raw, $m)) {
            $slots['standard_name'] = $this->wordToStandardNumber($m[1]);
        }

        if (preg_match('/\b(?:this|the)\s+(?:standard|grade|class)\b/i', $raw)
            || preg_match('/(?:આ|આપેલ)\s+ધોરણ/u', $raw)
            || preg_match('/(?:इस|इस\s+वाली)\s+कक्षा/u', $raw)) {
            $slots['standard_context'] = 'current_page';
        }

        // 2. Subject / Course extraction
        $subjectMap = [
            'science' => 'Science',
            'સાયન્સ' => 'Science',
            'વિજ્ઞાન' => 'Science',
            'विज्ञान' => 'Science',
            'mathematics' => 'Mathematics',
            'maths' => 'Mathematics',
            'math' => 'Mathematics',
            'મેથ્સ' => 'Mathematics',
            'ગણિત' => 'Mathematics',
            'गणित' => 'Mathematics',
            'english' => 'English',
            'અંગ્રેજી' => 'English',
            'ઇંગ્લિશ' => 'English',
            'अंग्रेजी' => 'English',
            'hindi' => 'Hindi',
            'હિન્દી' => 'Hindi',
            'हिन्दी' => 'Hindi',
            'social science' => 'Social Science',
            'social' => 'Social Science',
            'sst' => 'Social Science',
            'સામાજિક વિજ્ઞાન' => 'Social Science',
            'सामाजिक विज्ञान' => 'Social Science',
            'computer' => 'Computer',
            'કમ્પ્યુટર' => 'Computer',
            'कंप्यूटर' => 'Computer',
            'evs' => 'EVS',
            'પર્યાવરણ' => 'EVS',
            'पर्यावरण' => 'EVS',
            'physics' => 'Physics',
            'chemistry' => 'Chemistry',
            'biology' => 'Biology',
            'history' => 'History',
            'civics' => 'Civics',
            'geography' => 'Geography',
        ];

        foreach ($subjectMap as $trigger => $canonical) {
            if (mb_stripos($raw, $trigger) !== false) {
                $slots['subject_name'] = $canonical;
                break;
            }
        }

        if (preg_match('/\b(?:this|the)\s+(?:course|subject)\b/i', $raw)
            || preg_match('/(?:આ|આપેલ)\s+(?:કોર્સ|વિષય)/u', $raw)
            || preg_match('/(?:इस|इस\s+वाले)\s+(?:कोर्स|विषय)/u', $raw)) {
            $slots['subject_context'] = 'current_page';
        }

        // 3. Chapter extraction
        if (preg_match('/\b(?:chapter|chp\.?|ch\.?|unit|lesson)\s*[-:#]?\s*(\d{1,3})\b/i', $raw, $m)) {
            $slots['chapter_number'] = (int) $m[1];
        } elseif (preg_match('/(?:પ્રકરણ|અધ્યાય|પાઠ)\s*[-:#]?\s*(\d{1,3})/u', $raw, $m)) {
            $slots['chapter_number'] = (int) $m[1];
        }

        // Chapter title check
        $knownChapters = [
            'the wonderful world of science',
            'diversity in the living world',
            'mindful eating',
            'exploring magnets',
            'measurement of length and motion',
            'materials around us',
            'temperature and its measurement',
            'a journey through states of water',
            'methods of separation in everyday life',
            'living creatures',
            'nature\'s treasures',
            'beyond earth',
        ];

        foreach ($knownChapters as $chTitle) {
            if (mb_stripos($raw, $chTitle) !== false) {
                $slots['chapter_name'] = ucwords($chTitle);
                break;
            }
        }

        if (preg_match('/\b(?:this|the)\s+(?:chapter|unit|lesson)\b/i', $raw)
            || preg_match('/(?:આ|આપેલ)\s+પ્રકરણ/u', $raw)
            || preg_match('/(?:इस|इस\s+वाले)\s+अध्याय/u', $raw)) {
            $slots['chapter_context'] = 'current_page';
        }

        // 4. Topic extraction
        if (preg_match('/\b(?:topic|concept)\s*[-:#]?\s*(\d{1,3})\b/i', $raw, $m)) {
            $slots['topic_number'] = (int) $m[1];
        }

        if (preg_match('/\b(?:this|the)\s+(?:topic|concept)\b/i', $raw)
            || preg_match('/(?:આ|આપેલ)\s+ટોપિક/u', $raw)
            || preg_match('/(?:इस|इस\s+वाले)\s+टॉपिक/u', $raw)) {
            $slots['topic_context'] = 'current_page';
        }

        // 5. Query type: count vs list
        if (preg_match('/\b(?:how\s+many|how\s+much|count|number\s+of|total\s+count)\b/i', $raw)
            || preg_match('/(?:કેટલા|કેટલી|કેટલું|કેટલો|કુલ|કુલ\s+કેટલા)/u', $raw)
            || preg_match('/(?:कितने|कितनी|कितना|कुल|कुल\s+कितने)/u', $raw)) {
            $slots['query_type'] = 'count';
        } elseif (preg_match('/\b(?:which|what|list|show|display|all|every)\b/i', $raw)
            || preg_match('/(?:કયા|કઈ|કયો|બતાવો|દર્શાવો|બધા|દરેક)/u', $raw)
            || preg_match('/(?:कौन|दिखाइए|दिखाएं|सूची|सभी)/u', $raw)) {
            $slots['query_type'] = 'list';
        }

        return $slots;
    }

    private function canonicalStandardNumber(string $input): string
    {
        $input = trim(mb_strtolower($input));
        $roman = [
            'i' => '1', 'ii' => '2', 'iii' => '3', 'iv' => '4', 'v' => '5',
            'vi' => '6', 'vii' => '7', 'viii' => '8', 'ix' => '9', 'x' => '10',
            'xi' => '11', 'xii' => '12',
        ];

        return $roman[$input] ?? $input;
    }

    private function wordToStandardNumber(string $word): string
    {
        $map = [
            'first' => '1', 'one' => '1', 'second' => '2', 'two' => '2',
            'third' => '3', 'three' => '3', 'fourth' => '4', 'four' => '4',
            'fifth' => '5', 'five' => '5', 'sixth' => '6', 'six' => '6',
            'seventh' => '7', 'seven' => '7', 'eighth' => '8', 'eight' => '8',
            'ninth' => '9', 'nine' => '9', 'tenth' => '10', 'ten' => '10',
            'eleventh' => '11', 'eleven' => '11', 'twelfth' => '12', 'twelve' => '12',
        ];

        return $map[mb_strtolower(trim($word))] ?? $word;
    }

    /**
     * @param  array<int, string>  $nearMisses
     * @param  array<string, mixed>  $slots  Records the sentence named, kept even though
     *                                       the intent is unknown — see classify().
     */
    private function unknown(array $nearMisses = [], array $slots = []): Intent
    {
        return new Intent(
            key: Intent::UNKNOWN,
            label: 'Not understood',
            confidence: 0.0,
            slots: $slots,
            suggestions: [
                'Which students are at academic risk?',
                'Why is <student name> at risk?',
                'What evidence supports this?',
                'What should the teacher do?',
                'Approve the recommendation.',
                'What happened after approval?',
                'Did the intervention work?',
                'What has the system learned?',
            ],
            matched: $nearMisses === [] ? [] : ['near_misses' => $nearMisses],
        );
    }
}
