<?php

namespace App\Http\Controllers\api\easy_com;

use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use function App\Helpers\SearchStudent;

/**
 * Send SMS to parents.
 *
 * Business logic (gateway URL assembly + sms_sent_parents logging) is REUSED
 * from the existing send_sms_parents_controller::sendSMS()/saveParentLog(),
 * both of which take their tenant context as arguments and are safe to call.
 *
 * Fixed relative to the Blade flow:
 *  - grade/standard/division are validated instead of read from $_REQUEST
 *    unguarded (a missing key produced a 500, and a NULL sub_institute_id
 *    produced the "ts.sub_institute_id = and ..." SQL syntax error);
 *  - the recipient list now carries enrollment_no (GR No.), which the Blade
 *    payload omitted, so the listing column is no longer blank;
 *  - mobile numbers are validated before dispatch, and the student lookup is
 *    done once via a map rather than an O(n^2) scan per recipient;
 *  - per-recipient results are reported (sent / failed / skipped) instead of
 *    aborting the whole run on the first gateway error and still answering
 *    status_code = 1 ("success");
 *  - a send with no selected recipients is rejected rather than crashing on
 *    an undefined $responce['error'].
 */
class SendSmsParentsApiController extends BaseEasyComApiController
{
    /**
     * GET /api/easy_com/send-sms-parents/recipients?grade=&standard=&division=
     */
    public function recipients(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ], [], [
                'grade'    => 'academic section',
                'division' => 'division',
            ]);

            $students = SearchStudent(
                $data['grade'],
                $data['standard'],
                $data['division'],
                $this->subInstituteId(),
                $this->syear()
            );

            $rows = [];
            foreach ($students as $index => $student) {
                $mobile = trim((string) ($student['mobile'] ?? ''));

                $rows[] = [
                    'sr_no'         => $index + 1,
                    'student_id'    => $student['student_id'] ?? null,
                    'enrollment_no' => $student['enrollment_no'] ?? '',
                    'name'          => $this->fullName($student),
                    'mobile'        => $mobile,
                    'standard_name' => $student['standard_name'] ?? '',
                    'division_name' => $student['division_name'] ?? '',
                    'eligible'      => $this->isValidMobile($mobile),
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
     * POST /api/easy_com/send-sms-parents
     * Body: grade, standard, division, smsText, sendsms[<mobile>]=on
     */
    public function send(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
                'smsText'  => 'required|string|max:1000',
            ], [], ['smsText' => 'SMS text']);

            $numbers = $this->selectionKeys($request, 'sendsms');

            if (empty($numbers)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sendsms' => ['Select at least one recipient.'],
                ]);
            }

            $subInstituteId = $this->subInstituteId();
            $syear = $this->syear();
            $text = $data['smsText'];

            // Build mobile -> student_id once instead of rescanning per number.
            $students = SearchStudent(
                $data['grade'],
                $data['standard'],
                $data['division'],
                $subInstituteId,
                $syear
            );

            $studentByMobile = [];
            foreach ($students as $student) {
                $mobile = trim((string) ($student['mobile'] ?? ''));
                if ($mobile !== '') {
                    $studentByMobile[$mobile] = $student['student_id'] ?? 0;
                }
            }

            $legacy = app(send_sms_parents_controller::class);

            $sent = $failed = $skipped = [];

            foreach ($numbers as $number) {
                if (! $this->isValidMobile($number)) {
                    $skipped[] = ['mobile' => $number, 'reason' => 'Invalid mobile number.'];
                    continue;
                }

                if (! array_key_exists($number, $studentByMobile)) {
                    // Guards against a client posting a number outside the
                    // searched class - the Blade flow logged it as student 0.
                    $skipped[] = ['mobile' => $number, 'reason' => 'Number does not belong to the selected class.'];
                    continue;
                }

                $response = $legacy->sendSMS($number, $text, $subInstituteId);

                if (! empty($response['error'])) {
                    $failed[] = ['mobile' => $number, 'reason' => $response['message'] ?? 'Gateway error.'];

                    // "Please add api details first." is fatal for every number.
                    if (($response['message'] ?? '') === 'Please add api details first.') {
                        break;
                    }

                    continue;
                }

                $legacy->saveParentLog($studentByMobile[$number], $text, $number, $subInstituteId, $syear);
                $sent[] = $number;
            }

            $summary = [
                'requested' => count($numbers),
                'sent'      => count($sent),
                'failed'    => $failed,
                'skipped'   => $skipped,
            ];

            if (empty($sent)) {
                $reason = $failed[0]['reason'] ?? ($skipped[0]['reason'] ?? 'No SMS could be sent.');

                return $this->error($reason, 422, $summary);
            }

            $message = count($sent).' SMS sent.';
            if ($failed || $skipped) {
                $message .= ' '.(count($failed) + count($skipped)).' not sent.';
            }

            return $this->success($summary, $message);
        });
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
