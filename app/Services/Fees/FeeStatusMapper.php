<?php

namespace App\Services\Fees;

/**
 * Read-only normalization of each gateway's ad hoc fees_payment status
 * columns into one canonical enum. Does not write to fees_payment and does
 * not change any gateway's own status logic — it only gives audit/reporting
 * code a single vocabulary to reason about instead of 8 gateway-specific
 * string columns (PR/PS/PF, Razorpay's captured/authorized/failed, etc.).
 */
class FeeStatusMapper
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @var array<string,array<string,string>>
     */
    private static array $ccavenueStyleMap = [
        'PS' => self::STATUS_SUCCESS,
        'PF' => self::STATUS_FAILED,
        'PR' => self::STATUS_PENDING,
    ];

    public static function normalize(string $gateway, ?string $rawStatus): string
    {
        if ($rawStatus === null || $rawStatus === '') {
            return self::STATUS_PENDING;
        }

        // Known debug/noise sentinels found in production data (never real
        // gateway statuses) — see icici_bank_res being overwritten with the
        // literal "cron" by the Razorpay poller, and a stray "rajesh" test
        // value in the Razorpay status-exclusion list.
        if (in_array($rawStatus, ['rajesh', 'cron'], true)) {
            return self::STATUS_UNKNOWN;
        }

        switch ($gateway) {
            case 'hdfc':
            case 'hdfc_ssmission':
            case 'icici':
            case 'icici_orange':
            case 'axis':
            case 'aggre_pay':
            case 'payphi':
                return self::$ccavenueStyleMap[$rawStatus] ?? self::STATUS_UNKNOWN;

            case 'razorpay':
            case 'hdfcrazorpay':
                switch ($rawStatus) {
                    case 'PS':
                    case 'captured':
                        return self::STATUS_SUCCESS;
                    case 'PF':
                    case 'failed':
                        return self::STATUS_FAILED;
                    case 'PR':
                    case 'created':
                    case 'authorized':
                        return self::STATUS_PENDING;
                    case 'refunded':
                        return self::STATUS_REFUNDED;
                    default:
                        return self::STATUS_UNKNOWN;
                }

            default:
                return self::STATUS_UNKNOWN;
        }
    }
}
