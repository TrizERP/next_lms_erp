<?php

namespace App\Services\Fees;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Central action vocabulary and write helpers for fees-module audit events.
 * Every gateway/reconciliation/reprint call site should go through this
 * class instead of calling AuditLog::record() directly, so the action names
 * stay consistent and no call site accidentally logs a secret.
 *
 * Never pass gateway credentials, encrypted/plain gateway request payloads,
 * card data, or raw bank response bodies into these methods — only the
 * already-parsed identifiers (order id, student id, amount, status).
 *
 * Every public method here is guaranteed to never throw: writes go through
 * safeRecord(), which wraps AuditLog::record() in its own try/catch. This is
 * intentionally redundant with AuditLog::record()'s own fail-safe guarantee
 * — this class must stay side-effect-free from the caller's perspective even
 * if AuditLog's internals ever change, since it sits directly in payment,
 * receipt, and reconciliation code paths.
 */
class FeeAuditService
{
    public const GATEWAY_ORDER_CREATED = 'GATEWAY_ORDER_CREATED';
    public const GATEWAY_CALLBACK_RECEIVED = 'GATEWAY_CALLBACK_RECEIVED';
    public const GATEWAY_PAYMENT_SUCCESS = 'GATEWAY_PAYMENT_SUCCESS';
    public const GATEWAY_PAYMENT_FAILED = 'GATEWAY_PAYMENT_FAILED';
    public const GATEWAY_PAYMENT_PENDING = 'GATEWAY_PAYMENT_PENDING';
    public const RECONCILIATION_CONFIRMED = 'RECONCILIATION_CONFIRMED';
    public const RECEIPT_REPRINTED = 'RECEIPT_REPRINTED';

    /**
     * A gateway order/txn was created and a pending row was written to
     * fees_payment (before the browser is sent to the gateway).
     */
    public static function logOrderCreated(string $gateway, $orderId, $studentId, $subInstituteId, $amount): void
    {
        self::safeRecord([
            'module' => 'fees',
            'action' => self::GATEWAY_ORDER_CREATED,
            'entity_type' => 'fees_payment',
            'entity_id' => $orderId,
            'sub_institute_id' => $subInstituteId,
            'new_values' => [
                'gateway' => $gateway,
                'order_id' => $orderId,
                'student_id' => $studentId,
                'amount' => $amount,
                'status' => 'PR',
            ],
        ]);
    }

    /**
     * The gateway hit our response/callback route. Logged before signature
     * verification / status determination so a callback attempt is on
     * record even if it later fails validation.
     */
    public static function logCallbackReceived(string $gateway, $orderId, $studentId = null, $subInstituteId = null): void
    {
        self::safeRecord([
            'module' => 'fees',
            'action' => self::GATEWAY_CALLBACK_RECEIVED,
            'entity_type' => 'fees_payment',
            'entity_id' => $orderId,
            'sub_institute_id' => $subInstituteId,
            'new_values' => [
                'gateway' => $gateway,
                'order_id' => $orderId,
                'student_id' => $studentId,
            ],
        ]);
    }

    /**
     * The callback's outcome, after fees_payment has been updated.
     * $canonicalStatus should come from FeeStatusMapper::normalize().
     */
    public static function logPaymentOutcome(
        string $gateway,
        $orderId,
        $studentId,
        $subInstituteId,
        string $canonicalStatus,
        ?string $rawStatus = null
    ): void {
        $action = self::GATEWAY_PAYMENT_PENDING;
        if ($canonicalStatus === FeeStatusMapper::STATUS_SUCCESS) {
            $action = self::GATEWAY_PAYMENT_SUCCESS;
        } elseif ($canonicalStatus === FeeStatusMapper::STATUS_FAILED) {
            $action = self::GATEWAY_PAYMENT_FAILED;
        }

        self::safeRecord([
            'module' => 'fees',
            'action' => $action,
            'entity_type' => 'fees_payment',
            'entity_id' => $orderId,
            'sub_institute_id' => $subInstituteId,
            'new_values' => [
                'gateway' => $gateway,
                'order_id' => $orderId,
                'student_id' => $studentId,
                'status' => $canonicalStatus,
                'raw_status' => $rawStatus,
            ],
        ]);
    }

    /**
     * Manual reconciliation confirm (confirmOnlineFeesController::store())
     * flipped a fees_payment row's pending flag to confirmed.
     */
    public static function logReconciliationConfirmed($paymentId, $studentId, $subInstituteId, $orderId = null): void
    {
        self::safeRecord([
            'module' => 'fees',
            'action' => self::RECONCILIATION_CONFIRMED,
            'entity_type' => 'fees_payment',
            'entity_id' => $paymentId,
            'sub_institute_id' => $subInstituteId,
            'new_values' => [
                'payment_id' => $paymentId,
                'student_id' => $studentId,
                'order_id' => $orderId,
                'axis_order_id' => '2',
            ],
        ]);
    }

    /**
     * A previously issued receipt was reprinted via the reprint API.
     */
    public static function logReceiptReprinted($studentId, $receiptId, string $reprintAction, $subInstituteId = null): void
    {
        self::safeRecord([
            'module' => 'fees',
            'action' => self::RECEIPT_REPRINTED,
            'entity_type' => 'fees_receipt',
            'entity_id' => $receiptId,
            'sub_institute_id' => $subInstituteId,
            'new_values' => [
                'student_id' => $studentId,
                'receipt_id' => $receiptId,
                'reprint_action' => $reprintAction,
            ],
        ]);
    }

    /**
     * Write path every method above funnels through. Deliberately swallows
     * every Throwable — an audit-log failure (bad connection, full disk, a
     * future change to AuditLog that removes its own try/catch) must never
     * surface into a payment, receipt, or reconciliation code path.
     *
     * @param array<string,mixed> $attributes
     */
    private static function safeRecord(array $attributes): void
    {
        try {
            AuditLog::record($attributes);
        } catch (Throwable $e) {
            Log::error('FeeAuditService::safeRecord failed: ' . $e->getMessage());
        }
    }
}
