<?php

namespace App\Services\Mcp\Concerns;

use App\Http\Controllers\WhatsappController;
use App\Models\WhatappUserDetail;
use App\Models\WhatsappSentMessage;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sending one WhatsApp message to a parent through the Communication module's own Cloud API sender, recording it where
 * the Communication module records WhatsApp, and reading back what WhatsApp has reported since.
 *
 * Shared by every assistant flow that can message a parent on WhatsApp (parent messages and fee reminders), so they send
 * the same request, log to the same table and report delivery the same way. Nothing here is a new gateway: the call is
 * `WhatsappController::sendWhatsappCloudApi()` (approved templates), the log is `whatsapp_sent_messages`, and the
 * delivery webhook that updates that table is the source of "delivered" and "read".
 *
 * "Accepted" means WhatsApp returned a message id. It is not delivery, and the receipt reader says so.
 */
trait DeliversWhatsapp
{
    /**
     * The institute's WhatsApp Cloud API credentials, or null when either half is missing. Never returned to a caller.
     *
     * @return array<string, mixed>|null
     */
    protected function whatsappConfiguration(McpRequestContext $context): ?array
    {
        try {
            $row = WhatappUserDetail::where('sub_institute_id', $context->selectedInstituteId)->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }

        if ($row === null || trim((string) $row->cloud_api_access_token) === '' || trim((string) $row->cloud_api_phone_number_id) === '') {
            return null;
        }

        return $row->only(['cloud_api_access_token', 'cloud_api_phone_number_id']);
    }

    /**
     * @param  array<string, mixed>  $recipient  `student_id`, `mobile` (10 digits), `standard_id`, `division_id`.
     * @param  array<string, mixed>  $configuration
     * @return array{status:string, detail:string, outage:bool, provider_id?:?string}
     */
    protected function deliverWhatsapp(McpRequestContext $context, array $recipient, string $text, array $configuration): array
    {
        try {
            $reply = $this->sendWhatsappRequest('91'.$recipient['mobile'], $text, (string) $configuration['cloud_api_access_token'], (string) $configuration['cloud_api_phone_number_id']);
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'failed', 'detail' => 'The WhatsApp request failed before it could be made ('.class_basename($exception).').', 'outage' => true];
        }

        $error = $reply['error'] ?? ($reply['messages'][0]['error'] ?? null);
        $id = $reply['messages'][0]['id'] ?? null;

        // Accepted only with a message id. A reply with neither an error nor an id is not evidence of anything.
        if ($error !== null || ! is_string($id) || $id === '') {
            $reason = is_array($error)
                ? trim((string) ($error['error_user_msg'] ?? $error['message'] ?? 'WhatsApp rejected the message.')).(isset($error['code']) ? ' (code '.$error['code'].')' : '')
                : 'WhatsApp returned no message id, so there is no evidence it accepted the message.';

            $this->logWhatsapp($context, $recipient, $text, 'failed', $reason, null);

            return ['status' => 'failed', 'detail' => 'WhatsApp did not accept the message: '.Str::limit($reason, 200, '…'), 'outage' => false, 'provider_id' => null];
        }

        $status = (string) ($reply['messages'][0]['message_status'] ?? 'accepted');
        $this->logWhatsapp($context, $recipient, $text, $status, null, $id);

        return [
            'status' => 'sent',
            'detail' => sprintf('Accepted by WhatsApp (message id %s, status %s). Delivery and read receipts are recorded later.', Str::limit($id, 24, '…'), $status),
            'outage' => false,
            'provider_id' => $id,
        ];
    }

    /**
     * The Communication module's own WhatsApp Cloud API sender. Overridable so tests need no network.
     *
     * @return array<string, mixed>
     */
    protected function sendWhatsappRequest(string $to, string $text, string $accessToken, string $phoneNumberId): array
    {
        return (array) app(WhatsappController::class)->sendWhatsappCloudApi($to, $text, $accessToken, $phoneNumberId, null);
    }

    /** The Communication-module WhatsApp log, so the existing report and the delivery webhook see this message. */
    protected function logWhatsapp(McpRequestContext $context, array $recipient, string $text, string $status, ?string $error, ?string $providerId): void
    {
        try {
            $row = new WhatsappSentMessage();
            $row->sub_institute_id = $context->selectedInstituteId;
            $row->syear = $context->academicYear ?? 0;
            $row->standard_id = $recipient['standard_id'] ?? null;
            $row->division_id = $recipient['division_id'] ?? null;
            $row->student_id = $recipient['student_id'];
            $row->message = $text;
            $row->whatsapp_number = '+91'.$recipient['mobile'];
            $row->attachment = null;
            $row->sent_date = Carbon::today();
            $row->message_status = Str::limit($status, 50, '');
            $row->message_error = $error === null ? null : Str::limit($error, 250, '');
            $row->uri = $providerId;
            $row->api_type = 'cloud_api';
            $row->created_by = $context->userId;
            $row->created_by_name = 'AI assistant';
            $row->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * What WhatsApp has reported about a message since it accepted it, from the Communication module's own log
     * (the delivery webhook writes `message_status` there against the message id).
     *
     * @return array{0:string, 1:string}  [state, plain-words note]; state is read, delivered, failed or pending.
     */
    protected function whatsappReceipt(?string $providerId, ?string $sentAt = null): array
    {
        $pending = ['pending', 'WhatsApp accepted it and has not reported delivery yet.'];

        if ($providerId === null || $providerId === '') {
            return $pending;
        }

        $reported = mb_strtolower(trim((string) $this->reportedWhatsappStatus($providerId)));

        $known = match (true) {
            str_starts_with($reported, 'read') => ['read', 'WhatsApp reports the message was delivered and read.'],
            str_starts_with($reported, 'delivered') => ['delivered', 'WhatsApp reports the message was delivered to the phone.'],
            str_starts_with($reported, 'failed') => ['failed', 'WhatsApp accepted it, then reported that it could not be delivered'.$this->whatsappFailureReason($providerId).'.'],
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        // Accepted, and no receipt. Within a few minutes that is normal. After that it is a fact worth saying: either WhatsApp
        // has not delivered it, or this system never hears about deliveries — and which of the two is checkable.
        $minutes = $sentAt === null ? null : (int) \Illuminate\Support\Carbon::parse($sentAt)->diffInMinutes(now());

        if ($minutes === null || $minutes < self::WHATSAPP_RECEIPT_WAIT_MINUTES) {
            return $pending;
        }

        return ['not_confirmed', $this->whatsappReceiptsArriving()
            ? sprintf('WhatsApp accepted it %d minutes ago and has not reported it delivered. Its receipts do reach this system, so it is likely undelivered: the number may not be on WhatsApp, the parent may not have opted in, or Meta may have held it.', $minutes)
            : sprintf('WhatsApp accepted it %d minutes ago, but this system has recorded no WhatsApp delivery receipt for any message in the last 30 days, so it is not receiving Meta\'s status callbacks. Delivery is unknown. Configure the webhook in Meta (WhatsApp → Configuration) to %s.', $minutes, $this->whatsappWebhookUrl())];
    }

    /** The address Meta is told to call, falling back to the path alone where there is no application to build a full URL. */
    protected function whatsappWebhookUrl(): string
    {
        try {
            return url('/api/whatsapp/webhook');
        } catch (Throwable) {
            return '/api/whatsapp/webhook';
        }
    }

    /** How long an accepted message may go without a receipt before that is worth reporting. */
    public const WHATSAPP_RECEIPT_WAIT_MINUTES = 15;

    /**
     * Whether any WhatsApp delivery receipt has reached this system lately.
     *
     * A receipt is a status the webhook wrote AFTER the send: `updated_at` later than `created_at`. A status written at send time
     * (accepted, failed) has the two equal, so it proves nothing about receipts.
     */
    protected function whatsappReceiptsArriving(): bool
    {
        try {
            if (! Schema::hasTable('whatsapp_sent_messages')) {
                return false;
            }

            // Compared here rather than in SQL, so it reads the same on any database.
            return DB::table('whatsapp_sent_messages')
                ->whereIn('message_status', ['delivered', 'read', 'failed'])
                ->where('updated_at', '>=', now()->subDays(30))
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get(['created_at', 'updated_at'])
                ->contains(fn ($row) => \Illuminate\Support\Carbon::parse($row->updated_at)->diffInSeconds(\Illuminate\Support\Carbon::parse($row->created_at), false) < -5);
        } catch (Throwable) {
            return false;
        }
    }

    /** What WhatsApp said when it could not deliver, from the error the webhook recorded, for the sentence above. */
    protected function whatsappFailureReason(string $providerId): string
    {
        try {
            $error = DB::table('whatsapp_sent_messages')->where('uri', $providerId)->orderByDesc('id')->value('message_error');
        } catch (Throwable) {
            return '';
        }

        return is_string($error) && trim($error) !== '' ? ' ('.Str::limit(trim($error), 200, '…').')' : '';
    }

    /** The `message_status` the delivery webhook last wrote for this WhatsApp message id, or null. */
    protected function reportedWhatsappStatus(string $providerId): ?string
    {
        try {
            if (! Schema::hasTable('whatsapp_sent_messages')) {
                return null;
            }

            $status = DB::table('whatsapp_sent_messages')->where('uri', $providerId)->orderByDesc('id')->value('message_status');

            return $status === null ? null : (string) $status;
        } catch (Throwable) {
            return null;
        }
    }
}
