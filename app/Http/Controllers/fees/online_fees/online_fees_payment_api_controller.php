<?php

namespace App\Http\Controllers\fees\online_fees;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use App\Services\Fees\FeeAuditService;
use Throwable;

/** New ERP API adapter; gateway-specific legacy controller remains unchanged. */
class online_fees_payment_api_controller extends online_fees_collect_controller
{
    public function preview(Request $request, string $gateway)
    {
        abort_unless(in_array($gateway, ['razorpay', 'icici', 'axis', 'hdfc', 'aggre_pay', 'payphi', 'icici_orange', 'hdfcrazorpay']), 404);
        try {
            return response()->json(['status' => 1, 'data' => $this->get_fees($request)]);
        } catch (Throwable $exception) {
            Log::error('Online fees preview failed.', [
                'gateway' => $gateway,
                'student_id' => $request->input('student_id'),
                'sub_institute_id' => $request->input('sub_institute_id'),
                'syear' => $request->input('syear'),
                'exception' => $exception,
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Unable to load online fee details. Check the Laravel log for the underlying error.',
                'data' => null,
            ], 422);
        }
    }
    public function initiate(Request $request, string $gateway)
    {
        $methods = [
            'razorpay' => 'razorpay', 'icici' => 'icici', 'axis' => 'axis',
            'hdfc' => 'hdfc', 'aggre_pay' => 'aggre_pay', 'payphi' => 'payphi',
            'icici_orange' => 'icici_orange', 'hdfcrazorpay' => 'hdfcrazorpay',
        ];
        abort_unless(isset($methods[$gateway]), 404);
        try {
            if ($gateway === 'razorpay') {
                return response()->json(['status' => 1, 'data' => $this->createRazorpayOrder($request)]);
            }
            return $this->{$methods[$gateway]}($request);
        } catch (Throwable $exception) {
            return response()->json(['status' => 0, 'message' => 'Online fee data is not configured for this institute.'], 422);
        }
    }

    private function createRazorpayOrder(Request $request): array
    {
        $studentId = $request->input('student_id');
        $amount = (float) ($request->input('pay_amount') ?: $request->input('total'));
        if (!$studentId || $amount <= 0) {
            throw new \InvalidArgumentException('Student and payment amount are required.');
        }

        $student = DB::table('tblstudent as t')
            ->join('tblstudent_enrollment as e', function ($join) {
                $join->on('e.student_id', '=', 't.id')->on('e.sub_institute_id', '=', 't.sub_institute_id');
            })
            ->leftJoin('academic_section as a', 'a.id', '=', 'e.grade_id')
            ->where('t.id', $studentId)
            ->orderByDesc('e.syear')
            ->first(['t.first_name', 't.middle_name', 't.last_name', 't.uniqueid', 'a.medium']);
        if (!$student) throw new \RuntimeException('Student enrollment was not found.');

        $mapping = DB::table('fees_online_maping')
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->first();
        if (!$mapping) throw new \RuntimeException('Online payment mapping is not configured.');

        $credentials = DB::table('fees_razorpay')
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->when($student->medium, fn ($query) => $query->where('medium', $student->medium))
            ->first();
        if (!$credentials) throw new \RuntimeException('Razorpay credentials are not configured.');

        $order = (new Api($credentials->key_id, $credentials->key_secret))->order->create([
            'receipt' => 'order_' . uniqid(),
            'amount' => (int) round($amount * 100),
            'currency' => 'INR',
            'payment_capture' => 1,
        ]);

        $paymentId = DB::table('fees_payment')->insertGetId([
            'student_id' => $studentId,
            'syear' => session()->get('syear'),
            'amount' => (int) round($amount * 100),
            'razorpay_order_id' => $order['id'],
            'razorpay_payment_status' => 'PR',
            'razorpay_payment_date' => now(),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        FeeAuditService::logOrderCreated('razorpay', $order['id'], $studentId, session()->get('sub_institute_id'), $amount);

        return [
            'order_id' => (string) $order['id'],
            'key_id' => (string) $credentials->key_id,
            'payment_id' => (string) $paymentId,
            'amount' => (string) $amount,
            'student_name' => trim("{$student->first_name} {$student->middle_name} {$student->last_name}"),
            'student_unique_id' => (string) $student->uniqueid,
        ];
    }
}
