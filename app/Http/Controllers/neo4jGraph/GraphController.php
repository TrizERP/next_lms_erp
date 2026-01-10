<?php

namespace App\Http\Controllers\neo4jGraph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use function App\Helpers\is_mobile;

class GraphController extends Controller
{
    public function showAvionicsGraph(Request $request)
    {
        $type = $request->input('type');
        // Fetch graph data from API
        $response = Http::get('https://hp.triz.co.in/api/departments/26/graph');

        if ($response->successful()) {
            $graphData = $response->json();
        } else {
            // Fallback to empty or handle error
            $graphData = [
                "rootNode" => [],
                "nodes" => [],
                "relationships" => []
            ];
        }

        // return view('graph-view', compact('graphData'));
        return is_mobile($type, "graph-view", $graphData, "view");

    }
}
