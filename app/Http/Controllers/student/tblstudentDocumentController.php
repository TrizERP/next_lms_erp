<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentDocumentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Session;

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
        $type = $request->type;
        
        if(in_array($type,["API","JSON"])){
            $sub_institute_id = $request->sub_institute_id;
        }

        $file_name = "";
        if ($request->hasFile('file_name')) {
            $file = $request->file('file_name');
            $originalname = $file->getClientOriginalName();
            $name = $request->input('student_id').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            // $path = $file->storeAs('public/student_document/', $file_name);
            Storage::disk('digitalocean')->putFileAs('public/student_document/', $file, $file_name, 'public');
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
    public function destroy(Request $request,$id)
    {
        //
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->type;
        
        if(in_array($type,["API","JSON"])){
            $sub_institute_id = $request->sub_institute_id;
        }

       $record = tblstudentDocumentModel::find($id);
       $file_path = 'public/student_document/' . $record->file_name;
       if (Storage::disk('digitalocean')->exists($file_path)) {
           Storage::disk('digitalocean')->delete($file_path);
           if (!Storage::disk('digitalocean')->exists($file_path)) {
           }   
       } 
        // Delete the record
        $record->delete();

        if($record){
            Session::flash('status', 1); 
            Session::flash('message', 'Document Deleted Successfully!'); 
        }else{
            Session::flash('status', 0); 
            Session::flash('message', 'Failed to Delete Document!'); 
        }
        return redirect()->back();

    }
}
