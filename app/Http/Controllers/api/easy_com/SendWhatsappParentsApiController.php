<?php

namespace App\Http\Controllers\api\easy_com;

use App\Http\Controllers\WhatsappController;
use App\Models\student\tblstudentModel;
use App\Models\WhatappUserDetail;
use App\Models\WhatsappSentMessage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use function App\Helpers\SearchStudent;

/**
 * Send WhatsApp Cloud API messages to parents.
 *
 * The Cloud API call itself is REUSED from WhatsappController
 * (sendWhatsappCloudApi / mediaFound) so template handling, language fallback
 * and payload shape stay identical to the Blade screen.
 *
 * Fixed relative to the Blade flow (whatsappSendMessageStore):
 *  - $request->validate() inside a web route answered a 302 redirect (HTML) on
 *    failure; validation errors are JSON 422 here;
 *  - the recipient payload now carries standard_name / division_name, which the
 *    Blade create() omitted, so those listing columns are no longer blank;
 *  - students whose number fails the WhatsApp eligibility test are reported as
 *    skipped instead of silently vanishing (the Blade loop just did nothing and
 *    reported "Oops ! something went wrong" if none matched);
 *  - message/attachment persistence remains identical, but standard_id and
 *    division_id are validated so the report screen can filter on them.
 */
class SendWhatsappParentsApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/send-whatsapp-parents/recipients?grade=&standard=&division= */
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
                $mobile = trim((string) ($student['mobile'] ?? ''));

                $rows[] = [
                    'sr_no'         => $index + 1,
                    'student_id'    => $student['student_id'] ?? null,
                    'enrollment_no' => $student['enrollment_no'] ?? '',
                    'name'          => $this->fullName($student),
                    'mobile'        => $mobile,
                    'standard_name' => $student['standard_name'] ?? '',
                    'division_name' => $student['division_name'] ?? '',
                    'eligible'      => $this->isWhatsappEligible($mobile),
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
     * POST /api/easy_com/send-whatsapp-parents
     * Body: standard, division, message, sendNotification[<student_id>]=on
     */
    public function send(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'standard' => 'required',
                'division' => 'required',
                'message'  => 'required|string',
            ]);

            $studentIds = $this->selectionKeys($request, 'sendNotification');

            if (empty($studentIds)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sendNotification' => ['Select at least one recipient.'],
                ]);
            }

            $subInstituteId = $this->subInstituteId();

            $token = WhatappUserDetail::where('sub_institute_id', $subInstituteId)->first();

            if (! $token || empty($token->cloud_api_access_token) || empty($token->cloud_api_phone_number_id)) {
                return $this->error('WhatsApp configuration missing. Add the Cloud API credentials first.', 422);
            }

            $whatsapp = app(WhatsappController::class);
            [$textArray, $hrefArray] = $whatsapp->mediaFound($data['message']);

            if (! isset($textArray[0])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'message' => ['The message body is empty.'],
                ]);
            }

            $students = tblstudentModel::where('sub_institute_id', $subInstituteId)
                ->whereIn('id', $studentIds)
                ->get();

            $attachment = ! empty($hrefArray) ? $hrefArray[0] : null;
            $sent = $failed = $skipped = [];
            $dispatched = 0;

            foreach ($students as $student) {
                $mobile = trim((string) $student->mobile);

                if (! $this->isWhatsappEligible($mobile)) {
                    $skipped[] = [
                        'student_id' => $student->id,
                        'reason'     => 'Invalid or missing WhatsApp number.',
                    ];
                    continue;
                }

                // Same inter-message pacing the Blade flow used.
                if ($dispatched > 0) {
                    usleep(300000);
                }
                $dispatched++;

                $cloudResponse = $whatsapp->sendWhatsappCloudApi(
                    '91'.$mobile,
                    $data['message'],
                    $token->cloud_api_access_token,
                    $token->cloud_api_phone_number_id,
                    $attachment
                );

                $hasError = isset($cloudResponse['error'])
                    || ! empty($cloudResponse['messages'][0]['error']);

                if ($hasError) {
                    $errorStatus = json_encode($cloudResponse['error'] ?? $cloudResponse['messages'][0]['error']);
                    $messageStatus = 'failed';
                    $messageId = null;
                    $failed[] = [
                        'student_id' => $student->id,
                        'reason'     => $cloudResponse['error']['message'] ?? 'WhatsApp Cloud API rejected the message.',
                    ];
                } else {
                    $errorStatus = null;
                    $messageStatus = $cloudResponse['messages'][0]['status'] ?? 'unknown';
                    $messageId = $cloudResponse['messages'][0]['id'] ?? null;
                    $sent[] = $student->id;
                }

                $row = new WhatsappSentMessage();
                $row->sub_institute_id = $subInstituteId;
                $row->syear = $this->syear();
                $row->standard_id = $data['standard'];
                $row->division_id = $data['division'];
                $row->student_id = $student->id;
                $row->message = $data['message'];
                $row->whatsapp_number = '+91'.$mobile;
                $row->attachment = $attachment;
                $row->sent_date = Carbon::today();
                $row->message_status = $messageStatus;
                $row->message_error = $errorStatus;
                $row->uri = $messageId;
                $row->api_type = 'cloud_api';
                $row->created_by = $this->userId();
                $row->created_by_name = session()->get('name') ?: '';
                $row->save();
            }

            $summary = [
                'requested' => count($studentIds),
                'sent'      => count($sent),
                'failed'    => $failed,
                'skipped'   => $skipped,
            ];

            if (empty($sent)) {
                $reason = $failed[0]['reason'] ?? ($skipped[0]['reason'] ?? 'No WhatsApp message could be sent.');

                return $this->error($reason, 422, $summary);
            }

            $message = count($sent).' WhatsApp message(s) sent.';
            if ($failed || $skipped) {
                $message .= ' '.(count($failed) + count($skipped)).' not sent.';
            }

            return $this->success($summary, $message);
        });
    }

    /* ------------------------------------------------------------------ */

    /**
     * Same rule the Blade flow applied before dispatch: exactly 10 digits and
     * a leading digit in 6-9 (Indian mobile series).
     */
    private function isWhatsappEligible(string $mobile): bool
    {
        return $this->isValidMobile($mobile);
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
