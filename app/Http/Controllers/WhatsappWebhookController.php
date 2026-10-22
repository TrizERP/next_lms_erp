<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Meta's WhatsApp Cloud API tells an application what happened to a message ONLY by calling it back — there is no call that
 * asks. A message the API "accepts" (HTTP 200 and a `wamid`) can still fail later (the number is not on WhatsApp, the
 * recipient has not opted in, Meta holds it), and the only record of that is the status callback this receives:
 * sent -> delivered -> read, or failed with an error code.
 *
 * Until this existed no delivery receipt for a Cloud API message was ever recorded here, so every message stayed "accepted"
 * for ever and a failed one looked the same as a delivered one.
 *
 * Two endpoints, which Meta is pointed at under WhatsApp -> Configuration -> Webhook (callback URL
 * `<app url>/api/whatsapp/webhook`, subscribed to the `messages` field):
 *   GET   verification: echoes `hub.challenge` when `hub.verify_token` equals WHATSAPP_WEBHOOK_VERIFY_TOKEN.
 *   POST  statuses: updates `whatsapp_sent_messages.message_status` / `message_error` for the message id (`uri`).
 *
 * SECURITY. Verification is refused unless a verify token is configured. A POST is accepted only with a valid
 * `X-Hub-Signature-256` (HMAC-SHA256 of the raw body with the Meta app secret, WHATSAPP_APP_SECRET); without a configured
 * secret nothing is accepted, so an unconfigured server cannot have its records rewritten by anyone who finds the URL.
 * A status can only move forward (sent -> delivered -> read); a late "sent" never overwrites "read".
 */
class WhatsappWebhookController extends Controller
{
    /** The order a message moves through; a status never moves to an earlier one. `failed` is terminal. */
    private const RANK = ['accepted' => 0, 'unknown' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    /** GET: Meta's one-time check that this URL is ours. */
    public function verify(Request $request)
    {
        $expected = (string) config('services.whatsapp.verify_token');

        if ($expected !== '' && $request->query('hub_mode', $request->query('hub.mode')) === 'subscribe'
            && hash_equals($expected, (string) $request->query('hub_verify_token', $request->query('hub.verify_token')))) {
            return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** POST: status callbacks. Always answers 200 for an authentic request, so Meta does not retry a payload that held nothing for us. */
    public function receive(Request $request)
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response('Forbidden', 403);
        }

        $updated = 0;

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    $updated += $this->record((array) $status) ? 1 : 0;
                }
            }
        }

        return response()->json(['received' => true, 'updated' => $updated]);
    }

    /**
     * Apply one status to the message it is about.
     *
     * @param  array<string, mixed>  $status  `id`, `status`, `errors[]`, `timestamp` as Meta sends them.
     */
    private function record(array $status): bool
    {
        $id = (string) ($status['id'] ?? '');
        $new = mb_strtolower((string) ($status['status'] ?? ''));

        if ($id === '' || ! isset(self::RANK[$new]) || ! Schema::hasTable('whatsapp_sent_messages')) {
            return false;
        }

        try {
            $row = DB::table('whatsapp_sent_messages')->where('uri', $id)->orderByDesc('id')->first(['id', 'message_status']);

            if ($row === null) {
                return false;
            }

            $current = mb_strtolower(trim(explode(' ', (string) $row->message_status)[0]));

            // Forward only: a delayed "sent" must not undo "delivered", and nothing leaves "failed" or "read".
            if (isset(self::RANK[$current]) && (self::RANK[$current] >= self::RANK[$new]) && $current !== $new) {
                return false;
            }

            $error = null;

            if ($new === 'failed') {
                $first = (array) (($status['errors'] ?? [])[0] ?? []);
                $error = Str::limit(trim(sprintf(
                    '%s%s%s',
                    isset($first['code']) ? '(#'.$first['code'].') ' : '',
                    (string) ($first['title'] ?? $first['message'] ?? 'WhatsApp could not deliver the message'),
                    isset($first['error_data']['details']) ? ': '.$first['error_data']['details'] : (isset($first['message']) && isset($first['title']) ? ': '.$first['message'] : '')
                )), 250, '');
            }

            DB::table('whatsapp_sent_messages')->where('id', $row->id)->update([
                'message_status' => Str::limit($new, 50, ''),
                'message_error' => $error,
                'updated_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            // Never fail the callback over our own bookkeeping — Meta would retry it for days.
            Log::warning('WhatsApp status callback could not be recorded', ['message' => $exception->getMessage()]);

            return false;
        }
    }
}
