<?php
namespace App\Http\Controllers;

use App\Botman\OnboardingConversation;

class BotManController extends Controller
{
    /**
     * Place your BotMan logic here.
     */
    public function handle()
    {
        $botman = app('botman');

        $botman->hears('{message}', function ($botman, $message) {
            $botman->startConversation(new OnboardingConversation);
        });
            
        $botman->listen();
    }
}