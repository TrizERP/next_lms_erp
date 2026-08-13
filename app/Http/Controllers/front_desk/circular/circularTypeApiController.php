<?php

namespace App\Http\Controllers\front_desk\circular;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Standalone lookup endpoint for populating the "Circular type" dropdown on
 * the /front_desk/circular page. Added separately from circularController so
 * its existing store()/index()/getData() logic stays untouched.
 */
class circularTypeApiController extends Controller
{
    public function index(Request $request)
    {
        $data = DB::table('circular_type')
            ->select('id', 'type as name')
            ->orderBy('type')
            ->get();

        return response()->json([
            'status'  => '1',
            'success' => true,
            'message' => '',
            'data'    => $data,
        ]);
    }
}
