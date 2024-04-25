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

    public function mediaFound($message)
    {
        // Extract text parts outside anchor tags and concatenate each section
        $textPattern = '/(^|<\/a>)(.*?)(<a href="|$)/';
        $textMatches = [];
        preg_match_all($textPattern, $message, $textMatches);

        $textSections = [];
        $currentSection = '';

        foreach ($textMatches[2] as $match) {
            if (!empty(trim($match))) {
                $currentSection .= $match;
            } else {
                if (!empty($currentSection)) {
                    $textSections[] = $currentSection;
                    $currentSection = '';
                }
            }
        }
        if (!empty($currentSection)) {
            $textSections[] = $currentSection;
        }
        $hrefPattern = '/<a href="(.*?)">/';
        $hrefMatches = $hrefLinks = [];
        preg_match_all($hrefPattern, $message, $hrefMatches);
        //$hrefLinks = $hrefMatches[1]; // $matches[1] contains all href links found
        foreach ($hrefMatches[1] as $href) {
            // Use parse_url to parse the URL
            $parsedUrl = parse_url($href);

            // We want to keep the path part after the domain, remove the domain part
            if (isset($parsedUrl['path'])) {
                $path =ltrim($parsedUrl['path'], '/');

                // Concatenate the query and fragment part if they exist
                if (isset($parsedUrl['query'])) {
                    $path .= '?' . $parsedUrl['query'];
                }
                if (isset($parsedUrl['fragment'])) {
                    $path .= '#' . $parsedUrl['fragment'];
                }

                // Add the modified path to the hrefLinks array
                $hrefLinks[] = $path;
            } else {
                // If there is no path, keep the full href
                $hrefLinks[] = $href;
            }
        }

        return [$textSections,$hrefLinks];
    }
    public function handle()
    {

        $message = "Hello this is test message for me<a href=\"https://erp.triz.co.in/Images/logo.png\">https://erp.triz.co.in/Images/logo.png</a> for me ";
        list($textArray, $hrefArray) = $this->mediaFound($message);

        if (count($hrefArray) == 0) {
            $prepareMessageBody['contentVariables'] = json_encode([
                "1" => $textArray,
            ]);
            $prepareMessageBody['contentSid'] = "HX8a883dc4cbb2c566933248ed9baa6f2d";
        } else {
            $prepareMessageBody['contentVariables'] = json_encode([
                "1" => $hrefArray[0],
                "2" => isset($textArray[0]) ? $textArray[0] : " ",

            ]);

            $prepareMessageBody['contentSid'] = "HX865d745b08b3a55e94c4a43c97fbabc5";
        }


        $messagingServiceSid = 'MGdec43b1bbd9428a72fa0c7a633905319';
        $accountSid = env('TWILIO_SID');
        $authToken = env('TWILIO_AUTH_TOKEN');

        $client = new Client($accountSid, $authToken);
        $res= $client->messages->create(
            'whatsapp:+917621070302',
            [
                "contentSid" => $prepareMessageBody['contentSid'],
                "messagingServiceSid" => $messagingServiceSid,
                "from" => "whatsapp:+919909906512",
                "contentVariables" => $prepareMessageBody['contentVariables']
            ]
        );
        dd($res);

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
               /* [
                    "contentSid" => "HX02a86c824bbf747808744e76ac5795d3",
                    "messagingServiceSid" => $messagingServiceSid,
                    "from" => "whatsapp:+919909906512",
                    "contentVariables" => json_encode([
                        "1" => "hello world"
                    ])
                ]*/
                [
                    "contentSid" => "HX865d745b08b3a55e94c4a43c97fbabc5",
                    "messagingServiceSid" => $messagingServiceSid,
                        "from" => "whatsapp:+919909906512",
                    //"body" => 'test',
                    "contentVariables" => json_encode([
                        //"1" => " ",
                        "2" => 'test',

                    ])
                ]

            );
            dd($message);
        } catch (Exception $exception) {
            report($exception);
        }
        dd($message->status());

// Output the message SID for reference
        echo 'Message SID: ' . $message->sid;



        dd('done');




    }
}
