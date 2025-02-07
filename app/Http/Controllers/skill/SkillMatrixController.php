<?php 

namespace App\Http\Controllers\skill;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\skill\skill;
use App\Models\skill\matrix;
use App\Models\lms\counselling\OnetCareerCluster;
use Illuminate\Support\Facades\Auth;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class SkillMatrixController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $skills = skill::all();
        $completedCount = matrix::where('user_id', $user_id)->count();
        $totalSkills = $skills->count();
        $progress = $totalSkills > 0 ? round(($completedCount / $totalSkills) * 100) : 0;

        return view('skill.matrix.index', compact('skills', 'progress', 'completedCount', 'totalSkills'));
    }

    public function store(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        matrix::updateOrCreate(
            ['user_id' => $user_id, 'skill_id' => $request->skill_id],
            ['skill_level' => $request->skill_level, 'interest_level' => $request->interest_level]
        );

        return response()->json(['success' => true]);
    }

    public function JobRole()
    {
        $skills = DB::table('onet_career_cluster')
        ->select(
            'career_cluster',
            'onetsoc_code AS JobCode',
            'career_pathway AS CareerPath',
            'title AS JobRole',
            'description',
            DB::raw('FLOOR(RAND() * 9) + 7 AS Skill'),
            DB::raw('FLOOR(RAND() * 9) + 5 AS Knowledge'),
            DB::raw('FLOOR(RAND() * 20) + 8 AS Ability'),
            DB::raw('FLOOR(RAND() * 4) + 9 AS Tasks')
        )
        ->where('career_id', 8)
        ->get();

        return view('skill.jobrole.index', compact('skills'));
    }

    public function JobDescription(Request $request)
    {
        // Retrieve the code from the request
        $onetsoc_code = $request->query('code'); // Safer way to get query parameters

        // Fetch career details based on the code
        $career = OnetCareerCluster::where('onetsoc_code', $onetsoc_code)->first();

        // Pass the career data to the view
        return view('skill.jobrole.jobdescription', compact('career'));
    }
}
