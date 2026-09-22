<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publishes the prompts the Fees *writing* surfaces actually need.
 *
 * WHY THEY WERE MISSING AND WHAT IT BROKE
 *
 * `FeesAiAssist` resolves its prompt through `feesPromptFor($operation)`, which picks
 * the published Fees prompt whose key best matches the operation's hints. Three
 * operations write text for one person — drafting a collection remark, a fee circular,
 * a parent message — and none of them had a prompt to match. So `pick()` fell through to
 * the nearest thing published, which was `k12.fees.pending_summary`: a *cohort* summary
 * whose grounding variables are `records` and `metrics`.
 *
 * The Fee Adjustment remarks field on the collect screen sends what that screen knows —
 * a student, a total, a discount, a fine, a payment mode. It has no `records` and no
 * `metrics`, and never should: it is drafting one sentence about one receipt, not
 * summarising a list. `GroundingCheck` therefore refused every attempt, correctly, and
 * the operator saw a 422 reading "There is nothing to summarise".
 *
 * The bug was never the guard. It was asking a summary prompt to write a remark.
 *
 * WHAT THESE DECLARE AS GROUNDING
 *
 * The fields the screen genuinely supplies. One grounded variable is enough for
 * `GroundingCheck`, so a remark drafted for a named student with a real balance is
 * grounded; an empty form still refuses, which is the behaviour worth keeping.
 *
 * Nothing here invents data. Every placeholder is filled by the screen from the fee
 * record in front of the operator, and the safety rules forbid the model adding an
 * amount, a name or a date that was not passed in.
 */
return new class extends Migration
{
    private const KEYS = [
        'k12.fees.collection_remark',
        'k12.fees.circular_notice',
        'k12.fees.parent_message',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        foreach ($this->templates() as $template) {
            $existing = DB::table('ai_templates')
                ->where('template_key', $template['template_key'])
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing !== null) {
                DB::table('ai_templates')->where('id', $existing->id)
                    ->update($template + ['updated_at' => now()]);

                continue;
            }

            DB::table('ai_templates')->insert($template + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        // Retired, not deleted: a generation request row references the template it ran
        // against, and deleting the row would orphan the audit trail that explains what
        // was written and from which prompt.
        DB::table('ai_templates')
            ->whereIn('template_key', self::KEYS)
            ->update(['status' => 'retired', 'updated_at' => now()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $noInvention = json_encode([
            'Do not invent a fee amount, student name, head, receipt number or date.',
            'Use only the figures supplied. If something is missing, leave it out rather than estimating it.',
            'Do not threaten, shame or pressure. This text may be read by a parent.',
        ]);

        return [
            [
                'template_key' => 'k12.fees.collection_remark',
                'name' => 'Fee collection remark',
                'description' => 'A short, factual remark for a fee receipt or adjustment, written from the '
                    . 'figures on the collection screen.',
                'module_key' => 'fees',
                'domain' => 'k12',
                'category' => 'remark',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write the remark line on a school fee receipt. Work only from the '
                    . 'figures given. Never state an amount, name, head or date that is not in them, and never '
                    . 'estimate. Write one or two plain sentences an office clerk would be happy to have '
                    . 'printed on a receipt a parent will read. Format money in Indian rupees with Indian '
                    . 'digit grouping. Return the remark only — no preamble, no quotes, no labels.',
                'user_prompt' => "Draft the remark for this fee transaction.\n\n"
                    . "Student: {{student_name}} ({{enrollment_no}}), class {{standard_division}}\n"
                    . "Amount being collected: {{total_amount}}\n"
                    . "Discount applied: {{discount}}\n"
                    . "Fine applied: {{fine}}\n"
                    . "Payment mode: {{payment_mode}}\n"
                    . "Receipt date: {{receipt_date}}\n"
                    . "Balance still pending after this: {{pending_fees}}\n\n"
                    . "Mention the adjustment only if a discount or fine is non-zero. Mention the remaining "
                    . "balance only if it is above zero.",
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'total_amount', 'label' => 'Amount collected', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'pending_fees', 'label' => 'Balance pending', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enrollment_no', 'label' => 'Enrolment number', 'required' => false, 'type' => 'string'],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string'],
                    ['key' => 'discount', 'label' => 'Discount', 'required' => false, 'type' => 'string'],
                    ['key' => 'fine', 'label' => 'Fine', 'required' => false, 'type' => 'string'],
                    ['key' => 'payment_mode', 'label' => 'Payment mode', 'required' => false, 'type' => 'string'],
                    ['key' => 'receipt_date', 'label' => 'Receipt date', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $noInvention,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
            ],
            [
                'template_key' => 'k12.fees.circular_notice',
                'name' => 'Fee circular notice',
                'description' => 'The body of a fee circular for one student or family, written from the '
                    . 'amounts on the circular screen.',
                'module_key' => 'fees',
                'domain' => 'k12',
                'category' => 'notice',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write fee circulars sent home to parents by a school office. Work '
                    . 'only from the figures given; never state an amount, name, head or date that is not in '
                    . 'them. Be courteous and factual — a circular is a reminder, not a demand. Format money '
                    . 'in Indian rupees with Indian digit grouping. Return the notice text only.',
                'user_prompt' => "Write the circular text for this family.\n\n"
                    . "Student: {{student_name}} ({{enrollment_no}}), class {{standard_division}}\n"
                    . "Amount outstanding: {{pending_amount}}\n"
                    . "Amount requested in this circular: {{circular_amount}}\n"
                    . "Due by: {{due_date}}\n"
                    . "Existing remarks: {{remarks}}\n\n"
                    . "Keep it to a short paragraph. State the amount and the date plainly, and say how to "
                    . "pay only if that is given above.",
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'pending_amount', 'label' => 'Amount outstanding', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'circular_amount', 'label' => 'Amount requested', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enrollment_no', 'label' => 'Enrolment number', 'required' => false, 'type' => 'string'],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string'],
                    ['key' => 'due_date', 'label' => 'Due date', 'required' => false, 'type' => 'string'],
                    ['key' => 'remarks', 'label' => 'Existing remarks', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $noInvention,
                'requires_review' => 1,
                'allow_as_evidence' => 0,
            ],
            [
                'template_key' => 'k12.fees.parent_message',
                'name' => 'Fee message to a parent',
                'description' => 'A short message to one family about their fee position, for the Fees '
                    . 'communication screens.',
                'module_key' => 'fees',
                'domain' => 'k12',
                'category' => 'message',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short messages from a school office to a parent about fees. '
                    . 'Work only from the figures given; never state an amount, name or date that is not in '
                    . 'them. Warm, brief and specific. Never threaten or shame. Format money in Indian rupees '
                    . 'with Indian digit grouping. Return the message only.',
                'user_prompt' => "Write the message.\n\n"
                    . "Student: {{student_name}} ({{enrollment_no}}), class {{standard_division}}\n"
                    . "Amount outstanding: {{pending_fees}}\n"
                    . "What this message is about: {{purpose}}\n\n"
                    . "Two or three sentences. Address the parent, not the student.",
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'pending_fees', 'label' => 'Amount outstanding', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'purpose', 'label' => 'What it is about', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'enrollment_no', 'label' => 'Enrolment number', 'required' => false, 'type' => 'string'],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $noInvention,
                'requires_review' => 1,
                'allow_as_evidence' => 0,
            ],
        ];
    }
};
