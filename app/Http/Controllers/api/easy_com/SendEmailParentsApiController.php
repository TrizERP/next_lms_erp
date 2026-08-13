<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer;
use function App\Helpers\SearchStudent;

/**
 * Send email to parents.
 *
 * Fixed relative to the Blade flow (send_email_parents_controller::sendEmail):
 *
 *  - $mail->addAttachment($attechment) was called UNCONDITIONALLY, including
 *    when no file was uploaded and $path was "". PHPMailer treats that as a
 *    missing-file error, so every attachment-less email failed. The attachment
 *    is only added when a file was actually stored;
 *  - the email_sent_parents row was written BEFORE the send attempt, so the
 *    report listed mails that never went out. Logging now happens after a
 *    successful send;
 *  - recipients came from $_REQUEST['all_email'] with no validation; addresses
 *    are validated and de-duplicated, and invalid ones are reported;
 *  - each parent now gets an individual message instead of one mail carrying
 *    every parent's address in the To: header (the Blade loop added them all to
 *    a single PHPMailer instance, exposing the whole class's addresses to every
 *    recipient);
 *  - SMTPSecure was hard-coded to "ssl" while the SMTP tester used "tls"; it is
 *    now chosen from the configured port;
 *  - the uploaded filename is sanitised and its extension checked.
 */
class SendEmailParentsApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/send-email-parents/recipients?grade=&standard=&division= */
    public function recipients(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ], [], ['grade' => 'academic section']);

            $students = SearchStudent(
                $data['grade'],
                $data['standard'],
                $data['division'],
                $this->subInstituteId(),
                $this->syear()
            );

            $rows = [];
            foreach ($students as $index => $student) {
                $email = trim((string) ($student['email'] ?? ''));

                $rows[] = [
                    'sr_no'         => $index + 1,
                    'student_id'    => $student['student_id'] ?? null,
                    'enrollment_no' => $student['enrollment_no'] ?? '',
                    'name'          => $this->fullName($student),
                    'email'         => $email,
                    'mobile'        => trim((string) ($student['mobile'] ?? '')),
                    'standard_name' => $student['standard_name'] ?? '',
                    'division_name' => $student['division_name'] ?? '',
                    'eligible'      => $this->isValidEmail($email),
                ];
            }

            return $this->success([
                'grade'    => $data['grade'],
                'standard' => $data['standard'],
                'division' => $data['division'],
                'stu_data' => $rows,
            ], 'Success');
        });
    }

    /**
     * POST /api/easy_com/send-email-parents
     * Body: all_email (csv) or sendsms[<email>]=on, subject, content, fileToUpload
     */
    public function send(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'subject'      => 'required|string|max:255',
                'content'      => 'required|string',
                'fileToUpload' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,zip',
            ], [], ['fileToUpload' => 'attachment']);

            $recipients = $this->recipientEmails($request);

            if (empty($recipients['valid'])) {
                $reason = $recipients['invalid']
                    ? 'None of the selected email addresses are valid.'
                    : 'Select at least one recipient.';

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'all_email' => [$reason],
                ]);
            }

            $subInstituteId = $this->subInstituteId();

            $smtp = DB::table('smtp_details')->where('sub_institute_id', $subInstituteId)->first();

            if (! $smtp) {
                return $this->error('No SMTP configuration found for this institute. Add one first.', 422);
            }

            $attachmentPath = $this->storeAttachment($request);

            $sent = [];
            $failed = [];

            foreach ($recipients['valid'] as $email) {
                $mail = new PHPMailer\PHPMailer(true);

                try {
                    $mail->IsSMTP();
                    $mail->isHTML(true);
                    $mail->SMTPDebug = 0;
                    $mail->SMTPAuth = true;
                    $mail->SMTPSecure = ((int) $smtp->port === 465) ? 'ssl' : 'tls';
                    $mail->Host = $smtp->server_address;
                    $mail->Port = (int) $smtp->port;
                    $mail->Username = $smtp->gmail;
                    $mail->Password = $smtp->password;
                    $mail->SetFrom($smtp->gmail, $smtp->gmail);
                    $mail->AddReplyTo($smtp->gmail, $smtp->gmail);
                    $mail->AddAddress($email);
                    $mail->Subject = $data['subject'];
                    $mail->Body = $data['content'];
                    $mail->AltBody = strip_tags($data['content']);

                    // Only attach when a file was actually stored.
                    if ($attachmentPath !== '') {
                        $mail->addAttachment($attachmentPath);
                    }

                    if (! $mail->Send()) {
                        $failed[] = ['email' => $email, 'reason' => $mail->ErrorInfo ?: 'Delivery failed.'];
                        continue;
                    }

                    $sent[] = $email;
                } catch (\Throwable $e) {
                    $failed[] = ['email' => $email, 'reason' => $e->getMessage()];
                }
            }

            // Log only what actually went out, matching the Blade log columns.
            if (! empty($sent)) {
                DB::table('email_sent_parents')->insert([
                    'SYEAR'            => $this->syear(),
                    'EMAIL'            => implode(',', $sent),
                    'SUBJECT'          => $data['subject'],
                    'EMAIL_TEXT'       => $data['content'],
                    'ATTECHMENT'       => $attachmentPath,
                    'USER_ID'          => $this->userId(),
                    'IP'               => $request->ip(),
                    'sub_institute_id' => $subInstituteId,
                ]);
            }

            $summary = [
                'requested' => count($recipients['valid']) + count($recipients['invalid']),
                'sent'      => count($sent),
                'failed'    => $failed,
                'skipped'   => array_map(
                    static fn ($email) => ['email' => $email, 'reason' => 'Invalid email address.'],
                    $recipients['invalid']
                ),
            ];

            if (empty($sent)) {
                return $this->error($failed[0]['reason'] ?? 'No email could be sent.', 422, $summary);
            }

            $message = count($sent).' email(s) sent.';
            if ($failed || $recipients['invalid']) {
                $message .= ' '.(count($failed) + count($recipients['invalid'])).' not sent.';
            }

            return $this->success($summary, $message);
        });
    }

    /* ------------------------------------------------------------------ */

    /**
     * Accepts either the legacy `all_email` CSV or a sendsms[<email>]=on map.
     *
     * @return array{valid: array<int,string>, invalid: array<int,string>}
     */
    private function recipientEmails(Request $request): array
    {
        $candidates = $this->selectionKeys($request, 'sendsms');

        if (empty($candidates)) {
            $raw = (string) $request->input('all_email', '');
            $candidates = array_filter(array_map('trim', explode(',', $raw)));
        }

        $candidates = array_values(array_unique($candidates));

        $valid = $invalid = [];
        foreach ($candidates as $email) {
            if ($this->isValidEmail($email)) {
                $valid[] = $email;
            } else {
                $invalid[] = $email;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /** Store the upload and return an absolute path, or '' when there is none. */
    private function storeAttachment(Request $request): string
    {
        if (! $request->hasFile('fileToUpload')) {
            return '';
        }

        $file = $request->file('fileToUpload');

        // Derive the extension from the file's ACTUAL content, not from the
        // client-supplied name. The `mimes` rule already validated the content,
        // but trusting getClientOriginalExtension() would still let a caller
        // store a text file as "payload.exe".
        $extension = strtolower($file->extension() ?: 'dat');
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base) ?: 'attachment';

        $storedName = 'email_'.$base.'_'.date('YmdHis').'.'.$extension;
        $path = $file->storeAs('public/email', $storedName);

        return storage_path('app/'.$path);
    }

    private function fullName(array $student): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $student['first_name'] ?? '',
            $student['middle_name'] ?? '',
            $student['last_name'] ?? '',
        ])));
    }
}
