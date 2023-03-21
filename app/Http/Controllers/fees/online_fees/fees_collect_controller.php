<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

// use App\Http\Controllers\fees\fees_collect\fees_collect_controller;


class fees_collect_controller extends Controller
{
    public function index(Request $request)
    {
        $school_data = array();
        $type = "web";
        return is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
    }


    public function hdfc12(Request $request)
    {
        // $controller = new app/http/fees/fees_collect/fees_collect_controller;
        // print $controller->edit();
    }
}
