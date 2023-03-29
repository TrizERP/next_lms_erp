<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentDocumentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class tblstudentDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return string
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $file_name = "";
        if ($request->hasFile('file_name')) {
            $file = $request->file('file_name');
            $originalname = $file->getClientOriginalName();
            $name = $request->input('student_id').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/student_document/', $file_name);
        }

        $request->request->add(['file_name' => $file_name]); //add request

        $data = [
            'student_id'       => $request->get('student_id'),
            'document_title'   => $request->get('document_title'),
            'document_type_id' => $request->get('document_type_id'),
            'file_name'        => $request->get('file_name'),
            'sub_institute_id' => $sub_institute_id,
        ];

        tblstudentDocumentModel::insert($data);

        return "Document Uploaded";
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
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
