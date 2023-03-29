<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\studentQuotaModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class studentQuotaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = studentQuotaModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;


        return is_mobile($type, "student/show_student_quota", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Application|Factory|View
     */
    public function create()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $get_sort_order = DB::table('student_quota as s')
            ->selectRaw('(IFNULL(MAX(CAST(s.sort_order AS INT)),0) + 1) AS max_sort_order')
            ->where('s.sub_institute_id', $sub_institute_id)->get()->toArray();

        view()->share('max_sort_order', $get_sort_order[0]->max_sort_order);

        return view('student/add_student_quota');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');

        $data = $this->saveData($request);


        $res['status_code'] = "1";
        $res['message'] = "Student Quota Added successfully";

        return is_mobile($type, "student_quota.index", $res);
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
    public function edit($id)
    {
        $editData = studentQuotaModel::find($id)->toArray();

        return view('student/edit_student_quota', ['data' => $editData]);
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');

        $request->request->add(['id' => $id]); //add request

        $this->updateData($request);

        $res['status_code'] = "1";
        $res['message'] = "Student Quota Updated successfully";

        return is_mobile($type, "student_quota.index", $res);
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
        studentQuotaModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Student Quota deleted successfully";

        return is_mobile($type, "student_quota.index", $res);
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['created_by'] = $user_id;

        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        studentQuotaModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();
    }


    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $id = $newRequest['id'];
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        return studentQuotaModel::where(['id' => $id])->update($finalArray);
    }
}
