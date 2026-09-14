<?php

namespace App\Services\Fees;

/**
 * Read-only interpretation helper for the existing fees_payment table.
 * fees_payment stores one column-group per gateway rather than a
 * discriminator column, and some sub-gateways share a column-group
 * (hdfc/hdfc_ssmission; razorpay/hdfcrazorpay; icici/icici_orange) — so this
 * class produces a best-effort GROUP label, never a precise sub-gateway
 * identification. Used only by new read-only reporting controllers; never
 * reads from or writes to any table itself.
 */
class FeePaymentGatewayResolver
{
    /**
     * @var array<string,array{order:string,status:string,date:string,label:string}>
     */
    public const GATEWAY_GROUPS = [
        'hdfc' => [
            'order' => 'hdfc_order_id',
            'status' => 'hdfc_payment_status',
            'date' => 'hdfc_payment_date',
            'label' => 'HDFC (CCAvenue-style; covers hdfc and hdfc_ssmission — indistinguishable in this table)',
        ],
        'icici' => [
            'order' => 'icici_order_id',
            'status' => 'icici_payment_status',
            'date' => 'icici_payment_date',
            'label' => 'ICICI (EazyPay; covers icici and icici_orange — indistinguishable in this table)',
        ],
        'payphi' => [
            'order' => 'payphi_order_id',
            'status' => 'payphi_payment_status',
            'date' => 'payphi_payment_date',
            'label' => 'PayPhi',
        ],
        'razorpay' => [
            'order' => 'razorpay_order_id',
            'status' => 'razorpay_payment_status',
            'date' => 'razorpay_payment_date',
            'label' => 'Razorpay (covers razorpay and hdfcrazorpay — indistinguishable in this table)',
        ],
        'aggre_pay' => [
            'order' => 'aggre_pay_order_id',
            'status' => 'aggre_pay_payment_status',
            'date' => 'aggre_pay_payment_date',
            'label' => 'Aggre Pay',
        ],
        'axis' => [
            'order' => 'axis_order_id',
            'status' => 'axis_payment_status',
            'date' => 'axis_payment_date',
            'label' => 'Axis',
        ],
    ];

    /**
     * Best-effort gateway group detection for one fees_payment row.
     *
     * hdfc/icici/payphi/razorpay/aggre_pay are checked before axis because
     * axis_order_id is reused by pre-existing (unmodified) logic for the
     * HDFC split-payout hack and by confirmOnlineFeesController's pending/
     * confirmed flag ("1"/"2") whenever hdfc_order_id is already set on the
     * same row — so it is not a reliable Axis-gateway indicator in that
     * case, and "1"/"2" alone (no other gateway order id set) is treated as
     * the reconciliation flag, not a real Axis order.
     */
    public static function detect($row): ?string
    {
        if (! empty($row->hdfc_order_id ?? null)) {
            return 'hdfc';
        }
        if (! empty($row->icici_order_id ?? null)) {
            return 'icici';
        }
        if (! empty($row->payphi_order_id ?? null)) {
            return 'payphi';
        }
        if (! empty($row->razorpay_order_id ?? null)) {
            return 'razorpay';
        }
        if (! empty($row->aggre_pay_order_id ?? null)) {
            return 'aggre_pay';
        }
        if (! in_array($row->axis_order_id ?? null, [null, '', '1', '2'], true)) {
            return 'axis';
        }

        return null;
    }
}
