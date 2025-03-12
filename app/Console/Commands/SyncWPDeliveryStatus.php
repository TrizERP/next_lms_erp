<?php

namespace App\Console\Commands;

use App\Models\WhatappUserDetail;
use App\Models\WhatsappSentMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Twilio\Rest\Client;

class SyncWPDeliveryStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:deliveryStatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $updateStatus = WhatsappSentMessage::whereDate('created_at', Carbon::today())
            ->whereNotNull('uri')
            ->get(['id', 'uri', 'sub_institute_id']);
        $groupedMessages = $updateStatus->groupBy('sub_institute_id');

        foreach ($groupedMessages as $subInstituteId => $messages) {
            $token = WhatappUserDetail::where('sub_institute_id', $subInstituteId)
                ->orderByDesc('id')->first(['user_whatsapp_sid', 'user_whatsapp_token']);
            if ($token) {
                $client = new Client($token->user_whatsapp_sid, $token->user_whatsapp_token);

                foreach ($messages as $message) {
                    $messageSid = $message->uri; // SID
                    $fetchedMessage = $client->messages($messageSid)->fetch();
                    $messageStatus = $fetchedMessage->status;

                    // Update the message status
                    WhatsappSentMessage::where('id', $message->id)->update([
                        'message_status' => $messageStatus,
                    ]);
                }
            }
        }
    }
}
