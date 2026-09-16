<?php

use Illuminate\Database\Console\Seeds\WithoutOverwriting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeesPolicySeeder extends \Illuminate\Database\Seeder
{
    use WithoutOverwriting;

    public function run(): void
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            $this->command->info('knowledge_base_detail table does not exist. Skipping.');

            return;
        }

        foreach (['category', 'sub_institute_id'] as $col) {
            if (! Schema::hasColumn('knowledge_base_detail', $col)) {
                $this->command->info("{$col} column not present. Run migration 2026_09_16_000003 first.");

                return;
            }
        }

        $subInstituteId = 1;

        $policies = [
            [
                'kb_id' => 1,
                'title' => 'Late Fee Policy',
                'content' => 'A late fee of ₹50 per day will be charged on pending balances after the due date. Late fees are waived for students with active financial aid. Late fee is capped at 10% of the total outstanding amount.',
                'category' => 'fees',
                'tags' => 'late fee,due date,penalty,overdue',
                'status' => 1,
                'pdf_url' => '',
                'youtube_url' => '',
                'description' => 'Policy for late fee collection on overdue balances.',
                'created_on' => now(),
                'sub_institute_id' => $subInstituteId,
            ],
            [
                'kb_id' => 1,
                'title' => 'Installment Payment Rules',
                'content' => 'Students may pay fees in up to 3 installments per academic year. First installment must be paid before 15th July, second before 15th October, and final before 15th January. Missed installments trigger a reminder within 7 days.',
                'category' => 'fees',
                'tags' => 'installment,payment plan,monthly,due date',
                'status' => 1,
                'pdf_url' => '',
                'youtube_url' => '',
                'description' => 'Rules for installment fee payment.',
                'created_on' => now(),
                'sub_institute_id' => $subInstituteId,
            ],
            [
                'kb_id' => 1,
                'title' => 'Refund Policy',
                'content' => 'Fee refunds are processed within 15 business days. Unpaid balances are not refundable. Paid amounts for cancelled admissions are refunded minus a ₹200 processing fee. Refund requests must be submitted with the original receipt.',
                'category' => 'fees',
                'tags' => 'refund,cancellation,processing,refund policy',
                'status' => 1,
                'pdf_url' => '',
                'youtube_url' => '',
                'description' => 'Policy for fee refunds and cancellations.',
                'created_on' => now(),
                'sub_institute_id' => $subInstituteId,
            ],
            [
                'kb_id' => 1,
                'title' => 'Scholarship Eligibility',
                'content' => 'Students with 80% or higher attendance and 75% or higher academic average are eligible for merit scholarship. Scholarship covers 25%-50% of tuition fees. Applications open in June and December.',
                'category' => 'fees',
                'tags' => 'scholarship,merit,attendance,eligibility',
                'status' => 1,
                'pdf_url' => '',
                'youtube_url' => '',
                'description' => 'Merit scholarship eligibility criteria.',
                'created_on' => now(),
                'sub_institute_id' => $subInstituteId,
            ],
            [
                'kb_id' => 1,
                'title' => 'Fee Due Date Calendar',
                'content' => 'Academic year fees are due in three phases: Phase 1 (July 1 - July 15), Phase 2 (October 1 - October 15), Phase 3 (January 1 - January 15). Students must complete payment by the end of each phase window.',
                'category' => 'fees',
                'tags' => 'due date,calendar,payment schedule,phase',
                'status' => 1,
                'pdf_url' => '',
                'youtube_url' => '',
                'description' => 'Academic year fee due date calendar.',
                'created_on' => now(),
                'sub_institute_id' => $subInstituteId,
            ],
        ];

        foreach ($policies as $policy) {
            DB::table('knowledge_base_detail')->insert($policy);
        }

        $this->command->info('Inserted ' . count($policies) . ' fee policies for institute #' . $subInstituteId);
    }
}
