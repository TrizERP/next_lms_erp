<?php

namespace App\Support;

/**
 * THE SINGLE GATE EVERY OUTBOUND MAIL PATH PASSES THROUGH.
 *
 * Ported verbatim from hp_erp (G2G). Already referenced by
 * App\Http\Controllers\api\TalentManagement\Recruitment\OfferController,
 * which was written against this exact contract in anticipation of this
 * file existing.
 */
final class MailGate
{
    /**
     * May this process send mail at all?
     */
    public static function allowed(): bool
    {
        return filter_var(env('G2G_NOTIFY_EMAIL', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Why a send was refused, for logs and for API responses.
     */
    public static function reason(): string
    {
        return 'Outbound email is disabled for this environment (G2G_NOTIFY_EMAIL).';
    }
}
