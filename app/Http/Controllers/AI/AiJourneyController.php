<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;

/**
 * The AI Journey console — the page a person opens to watch the architecture work.
 *
 * This is a thin shell. It renders one blade view and hands it a short-lived token so
 * the page can call the same `/api/ai/*` endpoints any other client would. Nothing
 * about the pipeline is reimplemented for the browser: the console asks a question
 * over HTTP and draws whatever trace comes back, which is exactly why the console can
 * be trusted as evidence that the pipeline ran.
 */
class AiJourneyController extends Controller
{
    public function index(Request $request, JwtToken $jwt)
    {
        $subInstituteId = session('sub_institute_id');
        $userId = session('user_id');

        if (empty($userId) || empty($subInstituteId)) {
            return redirect('/')->with('error', 'Please sign in again before opening the AI Journey console.');
        }

        $isAdmin = (int) session('is_admin', 0);

        // The same payload shape McpAuth expects, minted for the signed-in user. The
        // page therefore has exactly the scope its user already has — no more.
        $token = $jwt->createToken([
            'id' => (int) $userId,
            'sub_institute_id' => (string) $subInstituteId,
            'user_profile_id' => session('user_profile_id'),
            'client_id' => session('client_id'),
            'is_admin' => $isAdmin,
            'is_student' => strtolower((string) session('user_profile_name')) === 'student',
        ]);

        return view('ai.journey', [
            'aiToken' => (string) $token,
            'aiBase' => '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/'),
            'instituteId' => $subInstituteId,
            'academicYear' => session('syear'),
            'termId' => session('term_id'),
            'schoolName' => session('school_name'),
            'role' => $isAdmin >= 1 ? 'admin' : 'staff',
        ]);
    }
}
