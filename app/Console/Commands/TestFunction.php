<?php

namespace App\Console\Commands;

use Dompdf\Exception;
use Illuminate\Console\Command;
use Twilio\Rest\Client;


class TestFunction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test function';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       /*$sid    = env('TWILIO_SID');
        $token  = env('TWILIO_AUTH_TOKEN');
        $twilio = new Client($sid, $token);
        $d = $twilio->messages->create(
            'whatsapp:+917621070302', // recipient's phone number
            [
                'from' => 'whatsapp:+919909906512', // your Twilio phone number
                'body' => 'This is a test message from Twilio in Laravel.',
            ]
        );
        dd($d);*/

        $accountSid = env('TWILIO_SID');
        $authToken = env('TWILIO_AUTH_TOKEN');

        $client = new Client($accountSid, $authToken);

        $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
        $templateLanguage = 'en';
        $templateParameters =["Alice", "Product XYZ"];

        $templateName = 'welcome_template1';
        $languageCode = 'en'; // Specify language code (e.g., 'en' for English)

// Recipient phone number in international format (e.g., +1234567890)
        $recipientPhone = 'whatsapp:+917621070302';

// Sender phone number in international format (e.g., +0987654321)
        $senderPhone = 'whatsapp:+919909906512';

// Define the parameters for the template (if any)
        $parameters = [
            'body' => 'Hello, this is a test message using an approved template.',
        ];

// Send the message using the template
        try {
            $message = $client->messages->create(
                'whatsapp:+919638141767',
                [
                    "contentSid" => "HX02a86c824bbf747808744e76ac5795d3",
                    "messagingServiceSid" => $messagingServiceSid,
                        "from" => "whatsapp:+919909906512",
                    "contentVariables" => json_encode([
                        "1" => "Name"
                    ])
                ]
            );
        } catch (Exception $exception) {
            report($exception);
        }
        dd($message->status());

// Output the message SID for reference
        echo 'Message SID: ' . $message->sid;



        dd('done');




    }
}

