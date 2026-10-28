<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Shared-secret check for provider webhooks (WhatsApp incoming and delivery status).
 *
 * Set WHATSAPP_WEBHOOK_SECRET and give the provider the callback URL with `?secret=<value>`
 * (or have it send an `X-Webhook-Secret` header). Once the secret is set, a call without it
 * is refused. While it is unset the webhooks stay open so existing delivery does not stop,
 * and every such call is logged as a warning so the gap is visible.
 */
class VerifyWebhookSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) env('WHATSAPP_WEBHOOK_SECRET', '');

        if ($secret === '') {
            \Illuminate\Support\Facades\Log::warning('Webhook accepted without a configured secret', ['path' => $request->path()]);

            return $next($request);
        }

        $given = (string) ($request->header('X-Webhook-Secret') ?: $request->query('secret', ''));
        if (!hash_equals($secret, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
