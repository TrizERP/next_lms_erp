<?php

namespace App\Http\Controllers\fees\online_fees;

use Illuminate\Http\Request;
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
            return response()->json(['status' => 0, 'message' => 'Online fee data is not configured for this institute.', 'data' => null], 422);
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
            return $this->{$methods[$gateway]}($request);
        } catch (Throwable $exception) {
            return response()->json(['status' => 0, 'message' => 'Online fee data is not configured for this institute.'], 422);
        }
    }
}
