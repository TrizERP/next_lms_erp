<?php

namespace App\Http\Controllers\frontdesk;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\taskModel;
use App\Models\user\tbluserModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class taskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {

        $type = $request->input("type");
        $from_date = $request->input("from_date");
        $to_date = $request->input("to_date");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        $user_profile_name = $request->session()->get("user_profile_name");
        $user_id = $request->session()->get("user_id");
        $taskType = $request->taskType;

        $data = DB::table("task as t")
            ->join('tbluser as u', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.TASK_ALLOCATED = u.id AND u.sub_institute_id = '".$sub_institute_id."'")->where('u.status',1); // 23-04-24 by uma
            })
            ->join('tbluser as u1', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.CREATED_BY = u1.id AND u1.sub_institute_id = '".$sub_institute_id."'")->where('u1.status',1); // 23-04-24 by uma
            })
            ->join('tbluser as u2', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.TASK_ALLOCATED_TO = u2.id AND u2.sub_institute_id = '".$sub_institute_id."'")->where('u2.status',1); // 23-04-24 by uma
            })
            ->leftJoin('tbluser as u3', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.approved_by = u3.id AND u3.sub_institute_id = '".$sub_institute_id."'")->where('u3.status',1); // 23-04-24 by uma
            })
            ->selectRaw("t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS manageby, 
            CONCAT_WS(' ',u1.first_name,u1.middle_name,u1.last_name) AS ALLOCATOR,
            CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS ALLOCATED_TO,
            CONCAT_WS(' ',u3.first_name,u3.middle_name,u3.last_name) AS approved_by")
            ->where("t.SYEAR", "=", $syear);

        if (isset($from_date)) {
            $data = $data->where('t.TASK_DATE', '>=', $from_date);
            $res['from_date'] = $from_date;
        }
        
        if (isset($to_date)) {
            $data = $data->where('t.TASK_DATE', '<=', $from_date);
            $res['to_date'] = $to_date;
        }
        if(isset($taskType)){
            $data = $data->where('t.task_type',$taskType);
            $res['taskType']=$taskType;
        }

        if (strtoupper($user_profile_name) != 'ADMIN') {
            $data = $data->whereRaw("(t.TASK_ALLOCATED_TO = '".$user_id."' OR t.TASK_ALLOCATED = '".$user_id."')");
        }
        $data = $data->orderBy('t.ID', 'desc');
        $data = $data->get()->toArray();

        $res['checkList'] = DB::table('task')->where('TASK_ALLOCATED',$user_id)->where('task_type','=','Daily Task')->where('TASK_DATE',date('Y-m-d'))->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "frontdesk.show_task", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        $user_id = $request->session()->get("user_id");

        $users = tbluserModel::where(["sub_institute_id" => $sub_institute_id, 'status' => 1])
            ->whereRaw("id != '".$user_id."'")
            ->where('status',1)
            ->get()
            ->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['userList'] = $users;
        $res['skillLists'] = DB::table('tblemp_skills')->whereIn('sub_institute_id',[0,$sub_institute_id])->get()->toArray();

        return is_mobile($type, "frontdesk.add_task", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input("type");
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $term_id = 0;
            $user_id = $request->user_id;
            $manageby = $request->input("manageby");
        }else{
            $sub_institute_id = $request->session()->get("sub_institute_id");
            $syear = $request->session()->get("syear");
            $term_id = $request->session()->get("term_id");
            $user_id = $request->session()->get("user_id");
            $manageby = $request->session()->get("user_id");
        }

        // Double-submit guard, ported from hp_erp's active `store()` (this
        // method previously matched hp_erp's OLDER, commented-out version -
        // see git history - which never had idempotency support at all).
        // A retried create (impatient second click, a browser retry after a
        // slow response) used to produce a duplicate task; callers that send
        // `idempotency_key` now get the first attempt's task id back instead.
        $idempotencyKey = trim((string) $request->input('idempotency_key'));
        if ($idempotencyKey !== '' && $sub_institute_id) {
            $existing = DB::table('task_management_idempotency_keys')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'status_code' => "1",
                    'message' => 'Task already created for this request.',
                    'task_id' => (string) $existing->task_id,
                    'task_ids' => [(string) $existing->task_id],
                    'replayed' => true,
                ]);
            }
        }

        // TASK_ALLOCATED_TO arrives as a single comma-joined string from the
        // "multiUser" create flow (one HTML field, several ids) - see
        // `my-tasks-api.ts`'s `createLegacyTask` - but also accept a real
        // array for any other caller of this legacy endpoint.
        $rawAllocatedTo = $request->input("TASK_ALLOCATED_TO");
        if (is_array($rawAllocatedTo)) {
            $TASK_ALLOCATED_TO = array_values(array_filter($rawAllocatedTo, fn ($value) => $value !== null && $value !== ''));
        } else {
            $TASK_ALLOCATED_TO = array_values(array_filter(array_map('trim', explode(',', (string) $rawAllocatedTo)), fn ($value) => $value !== ''));
        }

        $KRA = $request->input("KRA") ?? '';
        $KPA = $request->input("KPA") ?? '';
        $observation_point = $request->input("observation_point") ?? '';
        $task_type = $request->input("selType") ?? '';
        // Column is `required_skills` (plural) - see
        // `2026_08_20_100800_add_task_management_missing_columns_to_task_table.php`.
        // No `skill_id` column exists on this target's `task` table at all
        // (only hp_erp's own separately-migrated table has one) - excluded
        // below rather than inserted, to avoid an "Unknown column" error.
        $required_skills = $request->input("skills") ?? '';
        $dates = $this->getDatesWithoutSundays();
        // `repeat_until` (the "Repeat until" date field) has no column of
        // its own here either - like hp_erp, it is consumed only to compute
        // `TASK_DATE` below, never stored raw.
        $repeatUntil = $request->input('repeat_until');

        $data = $request->except([
            '_method', '_token', 'submit', 'TASK_ATTACHMENT', 'formName', 'selDepartment', 'selSubDepartment',
            'selType', 'add', 'type', 'syear', 'sub_institute_id', 'user_id', 'manageby', 'KRA', 'KPA', 'skills',
            'skill_id', 'observation_point', 'TASK_DATE', 'idempotency_key', 'formType', 'token', 'org_type',
            'repeat_until',
        ]);

        $file_name = $ext = $file_size = "";
        if ($request->hasFile('TASK_ATTACHMENT')) {
            $file = $request->file('TASK_ATTACHMENT');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = "task_".date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/frontdesk/', $file_name);
        }

        $createdTaskIds = [];
        foreach ($TASK_ALLOCATED_TO as $value) {
            $data['KRA'] = $KRA;
            $data['KPA'] = $KPA;
            $data['observation_point'] = $observation_point;
            $data['task_type'] = $task_type;

            $data['SYEAR'] = $syear;
            $data['MARKING_PERIOD_ID'] = $term_id;
            $data['CREATED_BY'] = $user_id;
            $data['TASK_ALLOCATED'] = $manageby;
            $data['TASK_ALLOCATED_TO'] = $value;
            $data['required_skills'] = $required_skills;
            $data['CREATED_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
            $data['CREATED_ON'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;

            if ($file_name != '') {
                $data['TASK_ATTACHMENT'] = $file_name;
                $data['FILE_SIZE'] = $file_size;
                $data['FILE_TYPE'] = $ext;
            }
            if($task_type=="Daily Task"){
                foreach ($dates as $k => $date) {
                    $data['TASK_DATE']=$date;
                    $createdTaskIds[] = DB::table('task')->insertGetId($data, 'ID');
                }
            }else{
                // The create-task modal sends `repeat_until` ("Repeat until"),
                // not `TASK_DATE` - fall back to it like hp_erp's active
                // `store()` does (`$request->repeat_until ?? now()`).
                $data['TASK_DATE'] = $request->get('TASK_DATE') ?? $repeatUntil ?? now();
                $createdTaskIds[] = DB::table('task')->insertGetId($data, 'ID');
            }
        }

        // Remember the key so a retry replays this create instead of
        // repeating it. Best-effort: a bookkeeping failure must not turn a
        // successful create into an error.
        if ($idempotencyKey !== '' && $sub_institute_id && $createdTaskIds) {
            try {
                DB::table('task_management_idempotency_keys')->insert([
                    'sub_institute_id' => $sub_institute_id,
                    'idempotency_key' => $idempotencyKey,
                    'task_id' => (int) $createdTaskIds[0],
                    'created_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                // A duplicate key here means two requests raced; the second
                // one still created a task, which is the pre-existing
                // behaviour, so there is nothing to undo.
            }
        }

        // Bypass `is_mobile()` for the API path: it renames `status_code` to
        // `status` (uppercased) and drops the original key entirely, but the
        // create-task modal's `submit()` reads `response.status_code` and
        // `response.task_id`/`task_ids` directly - both would silently
        // disappear through that helper. The plain web-form path (Blade's
        // `resources/views/frontdesk/add_task.blade.php`, `type` unset)
        // keeps using `is_mobile()` for its redirect-with-flash behaviour,
        // unaffected by any of the fixes above.
        if ($type === 'API') {
            return response()->json([
                'status_code' => "1",
                'message' => "Added successfully",
                'task_id' => $createdTaskIds[0] ?? null,
                'task_ids' => array_map('strval', $createdTaskIds),
            ]);
        }

        $res['status_code'] = "1";
        $res['message'] = "Added successfully";

        return is_mobile($type, "task.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $user_id = $request->session()->get("user_id");
        $syear = $request->session()->get("syear");
        $user_profile_name = $request->session()->get("user_profile_name");

        $result = DB::table("task as t")
            ->join('tbluser as u', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.TASK_ALLOCATED = u.id AND u.sub_institute_id = '".$sub_institute_id."'")->where('u.status',1); // 23-04-24 by uma
            })
            ->join('tbluser as u1', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.CREATED_BY = u1.id AND u1.sub_institute_id = '".$sub_institute_id."'")->where('u1.status',1); // 23-04-24 by uma
            })
            ->join('tbluser as u2', function ($join) use ($sub_institute_id) {
                $join->whereRaw("t.TASK_ALLOCATED_TO = u2.id AND u2.sub_institute_id = '".$sub_institute_id."'")->where('u2.status',1); // 23-04-24 by uma
            })
            ->selectRaw("t.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS manageby, 
            CONCAT_WS(' ',u1.first_name,u1.middle_name,u1.last_name) AS ALLOCATOR,
            CONCAT_WS(' ',u2.first_name,u2.middle_name,u2.last_name) AS ALLOCATED_TO")
            ->where("t.ID", "=", $id)
            ->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $editData = $result[0];
       
        $dataResult = DB::table("complaint_status")
            ->where("TYPE", "=", 'TASK')
            ->get()->toarray();

        $dataResult = array_map(function ($value) {
            return (array) $value;
        }, $dataResult);

        $taskStatus = $dataResult;
        $editData['skillLists'] = DB::table('tblemp_skills')->whereIn('sub_institute_id',[0,$sub_institute_id])->get()->toArray();

        return view('frontdesk/edit_task', ['data' => $editData, 'taskStatus' => $taskStatus]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $type = $request->input("type");
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $term_id = 0;
            $user_id = $request->user_id;
            $manageby = $request->input("manageby");
        }else{
            $sub_institute_id = $request->session()->get("sub_institute_id");
            $syear = $request->session()->get("syear");
            $term_id = $request->session()->get("term_id");
            $user_id = $request->session()->get("user_id");
            $manageby = $request->session()->get("user_id");
        }
        
        $TASK_ALLOCATED_TO = $request->input("TASK_ALLOCATED_TO");
        $KRA = $request->input("KRA");
        $KPA = $request->input("KPA");
        $task_type = $request->input("selType");
        $required_skill = $request->skills ?? '';
        $observation_point = $request->observation_point;
        // store skills

        $data = $request->except(['_method', '_token', 'submit', 'TASK_ATTACHMENT','formName','selDepartment','selSubDepartment','selType','add','type','syear','sub_institute_id','user_id','manageby','KRA','KPA','skills']);

        $data['KRA'] = $KRA;
        $data['KPA'] = $KPA;
        $data['task_type'] = $task_type;
        $data['observation_point'] = $observation_point;

        $data['SYEAR'] = $syear;
        $data['MARKING_PERIOD_ID'] = $term_id;
        $data['CREATED_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
        $data['CREATED_ON'] = date('Y-m-d H:i:s');
        $data['approved_by'] = $user_id;
        $data['approved_on'] = date('Y-m-d H:i:s');

        $TASK_ALLOCATED_TO = $request->input("TASK_ALLOCATED_TO");

        $file_name = $ext = $file_size = "";
        if ($request->hasFile('TASK_ATTACHMENT')) {
            $file = $request->file('TASK_ATTACHMENT');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = "task_".date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/frontdesk/', $file_name);
        }

        if ($file_name != '') {
            $data['TASK_ATTACHMENT'] = $file_name;
            $data['FILE_SIZE'] = $file_size;
            $data['FILE_TYPE'] = $ext;
        }

        $data = taskModel::where(['id' => $id])->update($data);

        $res['status_code'] = "1";
        $res['message'] = "Updated successfully";

        return is_mobile($type, "task.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        taskModel::where(["id" => $id])->delete();

        $res['status_code'] = "1";
        $res['message'] = "Deleted successfully";

        return is_mobile($type, "task.index", $res);
    }

    public function taskReportIndex(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "frontdesk.task_report", $res, "view");
    }

    function getDatesWithoutSundays() {
        $startDate = Carbon::now();
        $endDate = Carbon::create($startDate->year, $startDate->month)->endOfMonth();  
        
        $dates = [];
        
        $period = CarbonPeriod::create($startDate, $endDate);
        
        foreach ($period as $date) {
            if ($date->isSunday()) {
                continue;
            }
            $dates[] = $date->format('Y-m-d');
        }
        
        return $dates;
    }

}
