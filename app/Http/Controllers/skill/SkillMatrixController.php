<?php 

namespace App\Http\Controllers\skill;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\skill\skill;
use App\Models\skill\matrix;
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
}
