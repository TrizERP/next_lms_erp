<?php

namespace App\Http\Controllers\agenticAI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class agentLibraryController extends Controller
{
     public function index(Request $request)
    {
        //
       $type = $request->input('type');
        $res['isEdit'] = 0;
        $res['currentStep'] = 1;
        $res['agent'] = 0;
        return is_mobile($type, "agenticAI.agentLibrary.index", $res, "view");
    }

    public function create(Request $request){
        $type = $request->input('type');
        $agent_id = $request->input('agent_id');
        $res['agent_id'] = $agent_id;
        $res['agent_data'] = [];
        // $agents = json_decode(file_get_contents('https://trizk-12-agenticai.hf.space/agents'), true);
        // foreach ($agents as $agent) {
        //     if ($agent['id'] == $agent_id) {
        //         $res['agent_data'] = $agent;
        //         break;
        //     }
        // }
        return is_mobile($type, "agenticAI.agentLibrary.detail", $res, "view");
    }
}
