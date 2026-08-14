<?php

namespace App\Http\Controllers\api\easy_com;

use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Send SMS to staff.
 *
 * Fixed relative to the Blade flow (send_sms_staff_controller):
 *
 *  - groups() exposes the tbluserprofilemaster list the Blade show screen used
 *    to populate its dropdown. Next.js previously asked the operator to type a
 *    raw "staff group ID" into a text box;
 *  - staff_id logging bug: the Blade store() ran
 *        foreach ($data as $id => $arr) { if ($arr['mobile'] == $number) $id = $arr['id']; }
 *    which reuses $id as BOTH the loop key and the result, so after the loop
 *    $id is the last array index, not the matched user. Every sms_sent_staff
 *    row was therefore stamped with the wrong staff_id. Resolved here with a
 *    mobile -> id map;
 *  - the staff sendSMS() did not urlencode the message (the parents one does),
 *    so any '&', '#' or space in the text corrupted the gateway URL. This uses
 *    the parents' encoder for both;
 *  - selection, mobile validity and empty-selection are validated;
 *  - per-recipient outcome is reported instead of a blanket "SMS Sent".
 */
class SendSmsStaffApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/send-sms-staff/groups */
    public function groups(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = DB::table('tbluserprofilemaster')
                ->where('sub_institute_id', $this->subInstituteId())
                ->orderBy('name')
                ->get(['id', 'name']);

            return $this->success($rows, 'Success');
        });
    }

    /** GET /api/easy_com/send-sms-staff/recipients?staff=<user_profile_id> */
    public function recipients(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'staff' => 'required',
            ], [], ['staff' => 'staff group']);

            $rows = [];
            foreach ($this->staffOfGroup($data['staff']) as $index => $user) {
                $mobile = trim((string) ($user->mobile ?? ''));

                $rows[] = [
                    'sr_no'      => $index + 1,
                    'student_id' => $user->id,     // key name kept for UI parity
                    'staff_id'   => $user->id,
                    'name'       => $this->fullName($user),
                    'mobile'     => $mobile,
                    'user_name'  => $user->user_name ?? '',
                    'eligible'   => $this->isValidMobile($mobile),
                ];
            }

            return $this->success([
                'group_id' => $data['staff'],
                'stu_data' => $rows,
            ], 'Success');
        });
    }

    /**
     * POST /api/easy_com/send-sms-staff
     * Body: group_id, smsText, sendsms[<mobile>]=on
     */
    public function send(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'group_id' => 'required',
                'smsText'  => 'required|string|max:1000',
            ], [], [
                'group_id' => 'staff group',
                'smsText'  => 'SMS text',
            ]);

            $numbers = $this->selectionKeys($request, 'sendsms');

            if (empty($numbers)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sendsms' => ['Select at least one recipient.'],
                ]);
            }

            $subInstituteId = $this->subInstituteId();
            $syear = $this->syear();
            $text = $data['smsText'];

            $staffByMobile = [];
            foreach ($this->staffOfGroup($data['group_id']) as $user) {
                $mobile = trim((string) ($user->mobile ?? ''));
                if ($mobile !== '') {
                    $staffByMobile[$mobile] = $user->id;
                }
            }

            // Reuse the parents' sender: identical gateway assembly, but it
            // urlencodes the message body.
            $legacy = app(send_sms_parents_controller::class);

            $sent = $failed = $skipped = [];

            foreach ($numbers as $number) {
                if (! $this->isValidMobile($number)) {
                    $skipped[] = ['mobile' => $number, 'reason' => 'Invalid mobile number.'];
                    continue;
                }

                if (! array_key_exists($number, $staffByMobile)) {
                    $skipped[] = ['mobile' => $number, 'reason' => 'Number does not belong to the selected staff group.'];
                    continue;
                }

                $response = $legacy->sendSMS($number, $text, $subInstituteId);

                if (! empty($response['error'])) {
                    $failed[] = ['mobile' => $number, 'reason' => $response['message'] ?? 'Gateway error.'];

                    if (($response['message'] ?? '') === 'Please add api details first.') {
                        break;
                    }

                    continue;
                }

                DB::table('sms_sent_staff')->insert([
                    'syear'            => $syear,
                    'sub_institute_id' => $subInstituteId,
                    'staff_id'         => $staffByMobile[$number],
                    'sms_text'         => $text,
                    'sms_no'           => $number,
                    'module_name'      => 'SENT SMS STAFF',
                ]);

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

    /* ------------------------------------------------------------------ */

    /** Active staff of a profile group, scoped to the institute. */
    private function staffOfGroup($groupId)
    {
        return DB::table('tbluser')
            ->where('sub_institute_id', $this->subInstituteId())
            ->where('user_profile_id', $groupId)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get()
            ->values();
    }

    private function fullName($user): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $user->first_name ?? '',
            $user->middle_name ?? '',
            $user->last_name ?? '',
        ])));
    }
}
