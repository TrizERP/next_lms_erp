<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Covering-note bodies for the admission-confirmed mail, one per session.
 *
 * C   = Morning Session
 * C/A = Afternoon Session
 *
 * Both attach the standard's letter as a PDF (attach_as_pdf = 1,
 * pdf_template_id = NULL, i.e. the layout already registered for that standard).
 * Seeded for Hills High (254) only; every other institute keeps the old
 * full-letter-in-the-body behaviour until it creates its own templates.
 */
return new class extends Migration
{
    private const SUB_INSTITUTE_ID = 254;

    public function up(): void
    {
        if (!Schema::hasTable('email_templates') || !Schema::hasColumn('email_templates', 'attach_as_pdf')) {
            return;
        }

        foreach ($this->rows() as $row) {
            $exists = DB::table('email_templates')
                ->where('sub_institute_id', self::SUB_INSTITUTE_ID)
                ->where('event_key', 'admission_confirmed')
                ->where('status_code', $row['status_code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('email_templates')->insert($row + [
                'sub_institute_id' => self::SUB_INSTITUTE_ID,
                'module'           => 'admission',
                'event_key'        => 'admission_confirmed',
                'subject'          => 'ADMISSION PROCEDURE',
                'standard_ids'     => null,
                'attach_as_pdf'    => 1,
                'pdf_template_id'  => null,
                'pdf_filename'     => 'Admission_Letter_<< enquiry_no >>.pdf',
                'status'           => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->where('sub_institute_id', self::SUB_INSTITUTE_ID)
            ->where('event_key', 'admission_confirmed')
            ->whereIn('status_code', ['C', 'C/A'])
            ->delete();
    }

    private function rows(): array
    {
        return [
            [
                'name'         => 'Admission Confirmed - Morning Session',
                'status_code'  => 'C',
                'html_content' => $this->body('Morning'),
                'remarks'      => 'Covering note; the letter is attached as a PDF.',
            ],
            [
                'name'         => 'Admission Confirmed - Afternoon Session',
                'status_code'  => 'C/A',
                'html_content' => $this->body('Afternoon'),
                'remarks'      => 'Covering note; the letter is attached as a PDF.',
            ],
        ];
    }

    private function body(string $session): string
    {
        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#333;line-height:1.6;">
    <p>Dear Parent,</p>

    <p>Congratulations!! Your ward's provisional admission has been granted in the <strong>{$session} Session</strong>.</p>

    <p>Please find the attachment for << admission_std >>, Stage - 4 process.</p>

    <p>Please complete the documentation process within the given date and time to confirm your ward's
       admission at Hills High School.</p>

    <p style="margin-top:24px;">
        Regards,<br>
        Hills High School, Surat<br>
        09033093477
    </p>
</div>
HTML;
    }
};
