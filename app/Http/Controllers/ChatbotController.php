<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use BotMan\BotMan\Drivers\DriverManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session; 
use App\Services\OpenAIService;
class ChatbotController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function handle(Request $request)
{
    // Capture the user input message
    $message = $request->input('message');


    // Handle user input and get the bot response
    $response = $this->openAIService->handleUserInput($message);

    // Return the response as JSON
    return response()->json(['message' => $response]);
}
    
    
    
    
    public function firsthandle(Request $request)
    {
        // Create BotMan instance
        $botman = BotManFactory::create([]);
        $message = $request->input('message');
        
        // Define an array of replies
        $replies = [
            "Hi there! How can I help you today?",
            "Hello! What can I do for you?",
            "Greetings! How can I assist you?",
            "Hi! Let me know how I can help.",
            "Hello! What would you like to know?"
        ];
        
        // Randomly select a reply from the array
        $randomReply = $replies[array_rand($replies)];
        
        // Respond to the message
        $botman->hears($message, function (BotMan $bot) use ($randomReply) {
            $bot->reply($randomReply);
        });
        
        $botman->listen();
        
        // Return a response (modify as per your implementation)
        return response($randomReply, 200)
                  ->header('Content-Type', 'text/plain');
    }
}