<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\TalentManagement\TalentInterviewPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported 1:1 from hp_erp's
 * `App\Http\Controllers\talent\interview_panel\talent_interviewpanelController`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input (source's `panelTenantId()` token-first/session-fallback
 * resolution collapses to a single session read here, same as every other
 * controller in this port). `deleted_by` comes from `session()->get('user_id')`.
 */
class InterviewPanelController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * GET /interview-panel/list
     * Ported from talent_interviewpanelController@getInterviewPanel.
     */
    public function getInterviewPanel(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $panels = TalentInterviewPanel::with([
            'schedules:id,panel_id,interview_date,time',
        ])
            ->where('sub_institute_id', $subInstituteId)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $panels,
        ]);
    }

    /**
     * GET /interview-panel/users
     * Ported from talent_interviewpanelController@getInterviewers.
     */
    public function getInterviewers(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $users = DB::table('tbluser')
            ->leftJoin(
                's_user_jobrole',
                'tbluser.allocated_standards',
                '=',
                's_user_jobrole.id'
            )
            ->leftJoin(
                's_users_skills',
                's_user_jobrole.department_id',
                '=',
                's_users_skills.department_id'
            )
            ->select(
                'tbluser.id',
                'tbluser.first_name',
                'tbluser.last_name',
                DB::raw("TRIM(CONCAT(
                    COALESCE(tbluser.first_name, ''),
                    ' ',
                    COALESCE(tbluser.last_name, '')
                )) as name"),
                's_user_jobrole.id as jobrole_id',
                's_user_jobrole.jobrole as job_role',
                's_users_skills.title as skill'
            )
            ->where('tbluser.status', 1)
            ->where('tbluser.sub_institute_id', $subInstituteId)
            ->get()
            ->groupBy('id')
            ->map(function ($group) {
                $user = $group->first();

                $user->skills = $group
                    ->pluck('skill')
                    ->filter()
                    ->unique()
                    ->values()
                    ->take(3)
                    ->toArray();

                unset($user->skill);

                return $user;
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $users,
        ]);
    }

    /**
     * POST /interview-panel/store
     * Ported from talent_interviewpanelController@storeinterviewer.
     */
    public function storeinterviewer(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $request->validate([
            'panel_name' => 'required|string',
            'target_positions' => 'nullable|string',
            'description' => 'nullable|string',
            'available_interviewers' => 'nullable',
            'status' => 'nullable|string|in:available,assigned,inactive,active',
        ]);

        $availableInterviewers = null;

        if (is_array($request->available_interviewers)) {
            $availableInterviewers = json_encode($request->available_interviewers);
        } elseif (is_string($request->available_interviewers)) {
            $availableInterviewers = json_encode(
                array_map('trim', explode(',', $request->available_interviewers))
            );
        }

        $panel = TalentInterviewPanel::create([
            'sub_institute_id' => $subInstituteId,
            'panel_name' => $request->panel_name,
            'target_positions' => $request->target_positions,
            'description' => $request->description,
            'available_interviewers' => $availableInterviewers,
            'status' => $request->status ?? 'available',
            'created_by' => $this->actorId(),
        ]);

        return response()->json([
            'message' => 'Interview Panel Created Successfully',
            'data' => $panel,
        ], 201);
    }

    /**
     * PUT /interview-panel/update/{id}
     * Ported from talent_interviewpanelController@update.
     */
    public function update(Request $request, $id)
    {
        $panel = TalentInterviewPanel::find($id);
        if (!$panel) {
            return response()->json(['message' => 'Interview Panel Not Found'], 404);
        }

        $request->validate([
            'panel_name' => 'nullable|string',
            'target_positions' => 'nullable|string',
            'description' => 'nullable|string',
            'available_interviewers' => 'nullable',
            'status' => 'nullable|string|in:available,assigned,inactive,active',
        ]);

        $availableInterviewers = null;
        if (is_array($request->available_interviewers)) {
            $availableInterviewers = json_encode($request->available_interviewers);
        } elseif (is_string($request->available_interviewers)) {
            $availableInterviewers = json_encode(
                array_map('trim', explode(',', $request->available_interviewers))
            );
        }

        $panel->update([
            'panel_name' => $request->panel_name,
            'target_positions' => $request->target_positions,
            'description' => $request->description,
            'available_interviewers' => $availableInterviewers,
            'status' => $request->status ?? 'available',
            'updated_by' => $this->actorId(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Interview Panel Updated Successfully',
            'data' => $panel,
        ], 200);
    }

    /**
     * DELETE /interview-panel/delete/{id}
     * Ported from talent_interviewpanelController@destroy.
     */
    public function destroy(Request $request, $id)
    {
        $panel = TalentInterviewPanel::find($id);

        if (!$panel) {
            return response()->json(['message' => 'Interview Panel Not Found'], 404);
        }

        $panel->update(['deleted_by' => $this->actorId()]);
        $panel->delete();

        return response()->json([
            'message' => 'Interview Panel Deleted Successfully',
        ], 200);
    }
}
