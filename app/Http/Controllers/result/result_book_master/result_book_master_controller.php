<?php

namespace App\Http\Controllers\result\result_book_master;

use App\Http\Controllers\Controller;
use App\Models\result\result_book_master\result_book_master;
use App\Models\result\result_book_master\result_trust_master;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class result_book_master_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "result/result_book_master/show", $data, "view");
    }

    public function getData()
    {
        $responce_arr = [];
        $trust_master_data = result_trust_master::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ])->get()->toArray();

        foreach ($trust_master_data as $id => $arr) {
            $responce_arr[$id]['id'] = $arr['id'];
            $responce_arr[$id]['line1'] = $arr['line1'];
            $responce_arr[$id]['line2'] = $arr['line2'];
            $responce_arr[$id]['line3'] = $arr['line3'];
            $responce_arr[$id]['line4'] = $arr['line4'];
            $responce_arr[$id]['left_logo'] = $arr['left_logo'];
            $responce_arr[$id]['right_logo'] = $arr['right_logo'];
            $responce_arr[$id]['status'] = $arr['status'];
            $where = [
                'result_book_master.sub_institute_id' => session()->get('sub_institute_id'),
                'result_book_master.trust_id'         => $arr['id'],
            ];
            $book_master_data = DB::table('result_book_master')
                ->join('standard', 'standard.id', '=', 'result_book_master.standard')
                ->join('academic_section', 'academic_section.id', '=', 'standard.grade_id')
                ->select('result_book_master.*', 'standard.id as standard', 'standard.grade_id',
                    'standard.name as std_name', 'academic_section.title as grd_name')
                ->where($where)
                ->get()->toArray();

            $grd_arr = [];
            $std_arr = [];
            $grd_name = "";
            $std_name = "";
            foreach ($book_master_data as $book_master_id => $book_master_arr) {
                if (! in_array($book_master_arr->grade_id, $grd_arr)) {
                    $grd_arr[] = $book_master_arr->grade_id;
                    $grd_name .= $book_master_arr->grd_name.",";
                }
//                if (count($book_master_data) - 1 > $book_master_id) {
//                    $grd_name .= " , ";
//                }
                $std_arr[] = $book_master_arr->standard;
                $std_name .= $book_master_arr->std_name;
                if (count($book_master_data) - 1 > $book_master_id) {
                    $std_name .= ",";
                }
            }
            $grd_name = rtrim($grd_name, ',');

            $responce_arr[$id]['standard'] = $std_arr;
            $responce_arr[$id]['grade'] = $grd_arr;
            $responce_arr[$id]['grade_name'] = $grd_name;
            $responce_arr[$id]['standard_name'] = $std_name;
        }

        return $responce_arr;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('result/result_book_master/add');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $left_logo = "";
        $right_logo = "";

        if ($request->hasFile('left_logo')) {
            $file = $request->file('left_logo');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $left_logo = $name.'.'.$ext;
            $path = $file->storeAs('public/result/left_logo/', $left_logo);
        }
        if ($request->hasFile('right_logo')) {
            $file = $request->file('right_logo');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = File::extension($originalname);
            $right_logo = $name.'.'.$ext;
            $path = $file->storeAs('public/result/right_logo/', $right_logo);
        }

        $trust_master_data = new result_trust_master([
            'line1'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line1')),
            'line2'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line2')),
            'line3'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line3')),
            'line4'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line4')),
            'left_logo'        => $left_logo,
            'right_logo'       => $right_logo,
            'status'           => $request->get('status'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ]);

        $trust_master_data->save();

        foreach ($request->get('standard') as $id => $val) {
            $book_master_data = new result_book_master([
                'trust_id'         => $trust_master_data->id,
                'standard'         => $val,
                'sub_institute_id' => session()->get('sub_institute_id'),
            ]);
            $book_master_data->save();
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "result_book_master.index", $res, "redirect");
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
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $all_data = $this->getData();
        $data = array();
        foreach ($all_data as $all_data_id => $all_data_arr) {
            if ($all_data_arr['id'] == $id) {
                $data = $all_data_arr;
            }
        }

        return is_mobile($type, "result/result_book_master/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $left_logo = "";
        $right_logo = "";
        if ($request->hasFile('left_logo')) {
            if ($_FILES['left_logo']['error'] == 0) {
                $file = $request->file('left_logo');
                $originalname = $file->getClientOriginalName();
                $name = date('YmdHis');
                $ext = File::extension($originalname);
                $left_logo = $name.'.'.$ext;
                $path = $file->storeAs('public/result/left_logo/', $left_logo);
            }
        }
        if ($request->hasFile('right_logo')) {
            if ($_FILES['right_logo']['error'] == 0) {
                $file = $request->file('right_logo');
                $originalname = $file->getClientOriginalName();
                $name = date('YmdHis');
                $ext = File::extension($originalname);
                $right_logo = $name.'.'.$ext;
                $path = $file->storeAs('public/result/right_logo/', $right_logo);
            }
        }
        $all_data = $this->getData();
        $data = array();
        foreach ($all_data as $all_data_id => $all_data_arr) {
            if ($all_data_arr['id'] == $id) {
                $data = $all_data_arr;
            }
        }
        if ($left_logo == "") {
            $left_logo = $data["left_logo"];
        }
        if ($right_logo == "") {
            $right_logo = $data["right_logo"];
        }
        result_trust_master::where(["id" => $id])->delete();
        result_book_master::where(["trust_id" => $id])->delete();
        $trust_master_data = new result_trust_master([
            'line1'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line1')),
            'line2'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line2')),
            'line3'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line3')),
            'line4'            => str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $request->get('line4')),
            'left_logo'        => $left_logo,
            'right_logo'       => $right_logo,
            'status'           => $request->get('status'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ]);

        $trust_master_data->save();

        foreach ($request->get('standard') as $val) {
            $book_master_data = new result_book_master([
                'trust_id'         => $trust_master_data->id,
                'standard'         => $val,
                'sub_institute_id' => session()->get('sub_institute_id'),
            ]);
            $book_master_data->save();
        }

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "result_book_master.index", $res, "redirect");
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
        result_trust_master::where(["id" => $id])->delete();
        result_book_master::where(["trust_id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "result_book_master.index", $res, "redirect");
    }

}
