namespace App\Http\Controllers;

   use Botman\Botman\Botman;
   use Botman\Botman\BotmanFactory;
   use Botman\Botman\Middleware\Dialogflow;
   use Illuminate\Http\Request;

   class ChatbotController extends Controller
   {
       public function handle(Request $request)
       {
           $botman = BotmanFactory::create($request->all());

           $botman->hears('Hi|Hello', function ($bot) {
               $bot->reply('Hello! How can I assist you today?');
           });

           $botman->fallback(function ($bot) {
               $bot->reply('Sorry, I did not understand that.');
           });

           $botman->listen();
       }
   }