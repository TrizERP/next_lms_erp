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
}
