<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryBookCirculation;
use App\Models\LibraryItem;
use App\Models\student\tblstudentModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use Yajra\DataTables\DataTables;
use Picqer\Barcode\BarcodeGeneratorPNG;
use function App\Helpers\is_mobile;
use DB;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $subjects = LibraryBook::groupBy('subject')->pluck('subject', 'id');
        $publisher_names = LibraryBook::groupBy('publisher_name')->pluck('publisher_name', 'id');
        $author_names = LibraryBook::groupBy('author_name')->pluck('author_name', 'id');
        $sub_institute_id = session()->get('sub_institute_id');
        
        // ->with('items')
        if ($request->ajax()) {
            
            $data = LibraryBook::where('sub_institute_id', $sub_institute_id)
            ->when(request('subject'),function($q){
                $q->where('subject',request('subject'));
            })
            ->when(request('publisher_name'),function($q){
                $q->where('publisher_name',request('publisher_name'));
            })
            ->when(request('author_name'),function($q){
                $q->where('author_name',request('author_name'));
            }) 
            ->when(request('search_item'), function ($q) {
                $q->whereHas('items', function ($subquery) {
                    $subquery->where('item_code', request('search_item'));
                });
            })
            // 12-08-2024
            ->when(request('classification_no'),function($q){
                $q->where('classification',request('classification_no'));
            }) 
            ->when(request('isbn_issn'),function($q){
                $q->where('isbn_issn',request('isbn_issn'));
            }) 
            //12-08-2024
            ->when(request('book_status'),function($q){
                $q->whereHas('book_circulations', function ($q) {
                    switch (request('book_status')) {
                        case 'issued':
                            $q->whereNotNull('issued_date')->whereNull('return_date');
                            break;
                        case 'due':
                            $q->whereDate('due_date', now())->whereNull('return_date');
                            break;
                        case 'overdue':
                            $q->whereDate('due_date', '<', now())->whereNull('return_date');
                            break;
                    }
                });
            })
            ->select(['library_books.*', DB::raw('(SELECT GROUP_CONCAT(item_code) FROM library_items WHERE book_id = library_books.id  and sub_institute_id = '.$sub_institute_id.' and deleted_at IS NULL) as item_codes')])
            ->groupBy('library_books.id')
            ->latest()->get();

            return DataTables::of($data)
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" id="' . $row->id . '" name="someCheckbox" class="checkSingle" />';
                })
                ->addColumn('image', function ($row) {
                    return '<img src="' . Storage::disk('books')->url($row->image) . '" height="100" width="100" alt="">';
                })
                ->addColumn('   ',function($row){
                    return $row->item_codes;
                })
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $actionBtn = '<a href="javascript:void(0)" class="show m-2 btn btn-success btn-library-item" title="Show Book" data-id="' . $row->id . '"><i class="fa fa-eye"></i></a><a href="javascript:void(0)" class="delete m-2 btn btn-danger btn-delete d-none" title="Delete Book" data-id="' . $row->id . '"><i class="fa fa-trash"></i></a><a href="javascript:void(0)" class="m-2 btn btn-warning btn-edit ml-1" title="Edit Book" data-id="' . $row->id . '"><i class="fa fa-pencil"></i></a><a href="javascript:void(0)" class="m-2 btn btn-primary print-barcode ml-1 d-none" title="Print Barcode" data-id="' . $row->id . '"><i class="fa fa-barcode"></i></a><a href="javascript:void(0)" class="m-2 btn btn-info circulation ml-1" title="Issue/Return Book" data-id="' . $row->id . '"><i class="fa fa-retweet"></i></a>';
                    return $actionBtn;
                })
                ->rawColumns(['checkbox', 'image','item_codes', 'action'])
                ->make(true);
        }
        $DonateCode = '';
        if(!in_array($sub_institute_id,[47,254])){
            $lastItem = LibraryItem::where('sub_institute_id',$sub_institute_id)->where('item_code','like','%L%')->orderBy('id', 'desc')->first();

            if ($lastItem) {
                // Extract the numeric part of the item_code and increment it
                $lastItemCode = substr($lastItem->item_code, 1); // Remove the 'L' prefix
                $nextItemCode = (int)$lastItemCode + 1;
                $nextItemCode = str_pad($nextItemCode, 6, '0', STR_PAD_LEFT); // Ensure it's 5 digits
                $nextItemCode = 'L' . $nextItemCode;
            }else{
                $nextItemCode = "L000001";
            }
        }elseif(in_array($sub_institute_id,[47])){
            $purchase = LibraryItem::where('sub_institute_id',$sub_institute_id)->where('item_code','like','%A%')->orderBy('id', 'desc')->first();
            $Donate = LibraryItem::where('sub_institute_id',$sub_institute_id)->where('item_code','like','%D%')->orderBy('id', 'desc')->first();
            if ($purchase) {
                // Extract the numeric part of the item_code and increment it
                $lastItemCode = substr($purchase->item_code, 1); // Remove the 'L' prefix
                $nextItemCode = (int)$lastItemCode + 1;
                $nextItemCode = str_pad($nextItemCode, 6, '0', STR_PAD_LEFT); // Ensure it's 5 digits
                $nextItemCode = 'A' . $nextItemCode;
            }else{
                $nextItemCode = "A000001";
            }

            if ($Donate) {
                // Extract the numeric part of the item_code and increment it
                $lastDonateCode = substr($Donate->item_code, 1); // Remove the 'L' prefix
                $DonateCode = (int)$lastDonateCode + 1;
                $DonateCode = str_pad($DonateCode, 6, '0', STR_PAD_LEFT); // Ensure it's 5 digits
                $DonateCode = 'D' . $DonateCode;
            }else{
                $DonateCode = "D000001";
            }
        }else{
            $lastItem = LibraryItem::where('sub_institute_id',$sub_institute_id)->orderBy('id', 'desc')->first();

            if ($lastItem) {
              $nextItemCode = ($lastItem->item_code + 1);
            }else{
                $nextItemCode = "0";
            }
        }

        return view('library.books',compact('subjects','publisher_names','author_names','nextItemCode','DonateCode'));
    }

    public function generateBarcode(Request $request, $id)
    {
        $book = LibraryBook::find($id);
        // Barcode content
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new ImagickImageBackEnd()
        );
        $writer = new Writer($renderer);

        return base64_encode($writer->writeString($book->title));
    }

    public function circulation()
    {
        return view('library.circulation');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {         
    
        try {
            $sub_institute_id = session()->get('sub_institute_id');

            $createBook = LibraryBook::find($request->id) ?? new LibraryBook();
            $createBook->title = $request->title;
            $createBook->sub_title = $request->sub_title;
            $createBook->material_resource_type = $request->material_resource_type;
            $createBook->edition = $request->edition;
            $createBook->tags = $request->tags;
            $createBook->author_name = $request->author_name;
            $createBook->isbn_issn = $request->isbn_issn;
            $createBook->classification = $request->classification;
            $createBook->publisher_name = $request->publisher_name;
            $createBook->publish_year = $request->publish_year;
            $createBook->publish_place = $request->publish_place;
            $createBook->pages = $request->pages;
            $createBook->series_title = $request->series_title;
            $createBook->call_number = $request->call_number;
            $createBook->language = $request->language;
            $createBook->source = $request->source;
            $createBook->subject = $request->subject;
            $createBook->price = $request->price;
            $createBook->price_currency = $request->price_currency;
            $createBook->notes = $request->notes;
            $createBook->review = $request->review;
            $createBook->sub_institute_id = $sub_institute_id;
            if ($request->image) {
                $img = $request->image;
                $filename = $img->getClientOriginalName();
                $filepath = Storage::disk('books')->put($filename, file_get_contents($img->getRealPath()));
                $createBook->image = $filepath ? $filename : '';
            }
            if ($request->file_att) {
                $file_att = $request->file_att;
                $filename = $file_att->getClientOriginalName();
                $filepath = Storage::disk('books')->put($filename, file_get_contents($img->getRealPath()));
                $createBook->file_att = $filepath ? $filename : '';
            }
            if ($createBook->save()) {
                // only for add
                if(!isset($request->id)){
                    $itemCount = LibraryItem::where(['book_id' => $createBook->id, 'sub_institute_id' => $sub_institute_id])->get()->count();
                    if ($request->no_of_items < $itemCount) {
                        LibraryItem::where(['book_id' => $createBook->id, 'sub_institute_id' => $sub_institute_id])->where('item_code', '<', $request->no_of_items)->delete();
                    }
                    if($request->no_of_items!=0){
                        for ($i = 1; $i <= $request->no_of_items; $i++) {
                            $lastItem = LibraryItem::orderBy('id', 'desc')->where('sub_institute_id',$sub_institute_id)->where('item_code','like','%L%')->first();
                                if($sub_institute_id!=47){
                                if ($lastItem) {
                                    // Extract the numeric part of the item_code and increment it
                                    $lastItemCode = substr($lastItem->item_code, 1); // Remove the 'L' prefix
                                    $nextItemCode = (int)$lastItemCode + 1;
                                    $nextItemCode = str_pad($nextItemCode, 5, '0', STR_PAD_LEFT); // Ensure it's 5 digits
                                    $nextItemCode = 'L' . $nextItemCode;
                                } else {
                                    // If no previous items exist, start with L00001
                                    $nextItemCode = 'L00001';
                                }
                            }else{
                                if($i==1){
                                    $nextItemCode = $request->item_code_value;
                                }else{
                                    $first =substr($request->item_code_value, 0,1);
                                    $nextItemCode = (int)substr($request->item_code_value, 1) + 1;
                                    $nextItemCode = str_pad($nextItemCode, 5, '0', STR_PAD_LEFT); // Ensure it's 5 digits
                                    $nextItemCode = $first. $nextItemCode;
                                }
                            }
                            $objItem = LibraryItem::updateOrCreate([
                                'book_id' => $createBook->id,
                                'call_number' => $createBook->call_number,
                                'item_code' => $nextItemCode,
                                'sub_institute_id'=>$sub_institute_id,
                            ]);
                        }
                    }
                }else{
                    $objItem = LibraryItem::where(['book_id' => $createBook->id,'sub_institute_id'=>$sub_institute_id])->update([
                        'call_number' => $createBook->call_number,
                    ]);
                }
                // if ($objItem) {
                    if(isset($request->id)){
                    return response()->json(['message' => 'Book Updated Successfully !!', 'status' => true], 200);
                    }else{
                        return response()->json(['message' => 'Book created Successfully !!', 'status' => true], 200);
                    }
                // }
            }
        } catch (Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($enroll,Request $request)
    {
        try {
            $message ='';
            $type=$request->type;	
            $sub_institute_id = $request->sub_institute_id ?? session()->get('sub_institute_id');    
            if($sub_institute_id==254){
                $issue_status = $this->checkIssue($request);            
            } else{
                $issue_status = 0;            
            }     
            $details = tblstudentModel::where('enrollment_no', $enroll)->where('sub_institute_id',$sub_institute_id)->with('issuedBookItem')->first();
            $item_codes= DB::table('library_items')->where('book_id',$request->book_id)->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            if($request->type!="API"){
                $view = View::make('library.user_detail', compact('details','item_codes','message','issue_status'))->render();
                return response()->json(['data' => $view], 200);
            }else{
                $res=['status'=>1,'data'=>$details];
                return $res;
            }
           
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $sub_institute_id=session()->get('sub_institute_id');
        try {
            $details = LibraryBook::where('library_books.sub_institute_id', $sub_institute_id)
            ->where('library_books.id',$id)
            ->select(['library_books.*', DB::raw('(SELECT GROUP_CONCAT(item_code) FROM library_items WHERE book_id = library_books.id) as item_codes'), DB::raw('(SELECT count(item_code) FROM library_items WHERE book_id = library_books.id) as no_of_items')])
            ->groupBy('library_books.id')->get()->toArray();
            // return $details[0];exit;
            return response()->json(['data' => $details], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $ids = $request->id;
        $del = LibraryBook::whereIn('id', $ids)->delete();
        return response()->json(['message'=>'Book Deleted Successfully !'],200);
    }
    public function deleteItem($id)
    {
        $del = LibraryItem::find($id);
        $book_id = $del->book_id;
        if($del){
            $del->delete();
            $message = 'Book Item Deleted Successfully !';
        }else{
            $message = 'failed!';
        }
        return response()->json(['message'=>$message,'book_id'=>$book_id],200);
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function returnBook($id, Request $request)
    {
        $enroll = $request->enroll_no;
        $book_id=$request->book_id;
        $sub_institute_id=session()->get('sub_institute_id');
        $book = LibraryBookCirculation::find($id);
        $message='';
        if ($book) {
            $book->update(['return_date' => now()]); 
            $message='Return Date Updated Successfully !!';
        }

        // return $book;exit;
        $details = tblstudentModel::where('enrollment_no', $enroll)->with('issuedBookItem')->first();
        $item_codes= DB::table('library_items')->where('book_id',$book_id)->where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->get()->toArray();
        $view = View::make('library.user_detail', compact('details','item_codes','message'))->render();
        return response()->json(['data' => $view], 200);
    }

    public function item($id)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $book = LibraryBook::with('items')->where('sub_institute_id',$sub_institute_id)->findOrFail($id);
        $view = View::make('library.item_detail', compact('book'))->render();
        return response()->json(['data' => $view], 200);
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function issueBook(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $sub_institute_id = session()->get('sub_institute_id');
        $request->validate([
            'student_id' => 'required|exists:tblstudent,id',
            'bookId' => 'required|exists:library_books,id',
            'issue_date' => 'required|date',
            'return_date' => 'required|date|after:issue_date',
        ]);
        $ids = $request->id;
        $check_data = LibraryBookCirculation::where(
            [
                'student_id' => $request->student_id,
                'book_id' => $request->bookId,
                'item_code' => $request->item_codes,                                
            ]
        )->whereNull('return_date')->get()->toArray();

        if(!empty($check_data)){
            $update = LibraryBookCirculation::where(
                [
                    'student_id' => $request->student_id,
                    'book_id' => $request->bookId,
                    'item_code' => $request->item_codes,                                
                ]
            )->whereNull('return_date')->update([
                    'issued_date' => \Carbon\Carbon::parse($request->issue_date)->format('Y-m-d'),
                    'due_date' => \Carbon\Carbon::parse($request->return_date)->format('Y-m-d'),
                    'sub_institute_id' => $sub_institute_id,
                    'updated_at'=>now(),
            ]);
            $issueBook ='update';
        }else{
            $insert = LibraryBookCirculation::insert(
                [
                    'student_id' => $request->student_id,
                    'book_id' => $request->bookId,
                    'item_code' => $request->item_codes, 
                    'issued_date' => \Carbon\Carbon::parse($request->issue_date)->format('Y-m-d'),
                    'due_date' => \Carbon\Carbon::parse($request->return_date)->format('Y-m-d'),
                    'sub_institute_id' => $sub_institute_id,
                    'created_at'=>now(),                    
            ]);
            $issueBook ='insert';
        }
        
        $message = "";
        if(isset($issueBook) && $issueBook=="insert"){
            $message = "Book Issued Successfully";
        }
        else if(isset($issueBook) && $issueBook=="update"){
            $message = "Book Issue Updated Successfully";
        }
        $details = tblstudentModel::where('enrollment_no', $request->enroll_no)->with('issuedBook')->first();
        $item_codes= DB::table('library_items')->where('book_id',$request->bookId)->where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->get()->toArray();
        $view = View::make('library.user_detail', compact('details','item_codes','message'))->render();
        // $view = View::make('library.user_detail')->with(['details' => $details, 'message' => $message])->render();

        return response()->json(['data' => $view], 200);
    }

    public function QuickReturn(Request $request){
        $type = $request->type;
        $res=[];
        if (session()->has('data')) {
            $data_arr = session('data');
            if (isset($data_arr['message']) && isset($data_arr['status_code'])) {
                $res['message'] = $data_arr['message'];
                $res['status_code'] = $data_arr['status_code'];                
            }
        }
        return is_mobile($type, 'library/quick_return', $res, 'view');

    }

    public function QuickReturnSearch(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $item_code = $request->item_code;
        $type= $request->type;

        $res['message'] = "This is item already returned or not exists in loan database";
        $res['status_code']=0;

        $check_data = DB::table('library_book_circulations as lbc')
        ->selectRaw('lbc.id as circulation_id, lbc.student_id, lbc.issued_date, lbc.due_date, lbc.return_date, li.item_code, li.received_date, li.order_date, li.order_no, li.item_status, lb.id as book_id, lb.title as book_name, lb.publisher_name, lb.author_name, lb.edition')
        ->join('library_books as lb', 'lb.id', '=', 'lbc.book_id')
        ->join('library_items as li', function($join){
            $join->on('lbc.book_id', '=', 'li.book_id')->on('lbc.item_code', '=', 'li.id');
        })
        ->where('lb.sub_institute_id', $sub_institute_id)
        ->where('li.item_code', $item_code)
        ->whereRaw(' (lbc.return_date IS NULL OR lbc.return_date like "0000-00%" )')   
        ->get();
        
        $return_date = 0;
        if(!empty($check_data) && isset($check_data[0])){
            $return_date = DB::table('library_book_circulations')->where(['book_id'=>$check_data[0]->book_id,'student_id'=>$check_data[0]->student_id,'id'=>$check_data[0]->circulation_id])->update([
                'return_date'=>now()
            ]);
            $res['message'] = "Book Return Successfully";
            $res['status_code']=1;

            $libraryCirculations = DB::table('library_book_circulations as lbc')
            ->selectRaw('CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) as student_name,s.enrollment_no,s.mobile,std.name as standard,d.name as division, lbc.id as circulation_id, lbc.student_id, lbc.issued_date, lbc.due_date, lbc.return_date, li.item_code, li.received_date, li.order_date, li.order_no, li.item_status, lb.id as book_id, lb.title as book_name, lb.publisher_name, lb.author_name, lb.edition')
            ->join('library_books as lb', 'lb.id', '=', 'lbc.book_id')
            ->join('library_items as li', function($join){
                $join->on('lbc.book_id', '=', 'li.book_id')->on('lbc.item_code', '=', 'li.id');
            })
            ->join('tblstudent as s', 's.id', '=', 'lbc.student_id')
            ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
            ->join('standard as std', 'std.id', '=', 'se.standard_id')
            ->join('division as d', 'd.id', '=', 'se.section_id')
            ->where('se.syear', $syear)
            ->where(['lbc.book_id'=>$check_data[0]->book_id,'lbc.student_id'=>$check_data[0]->student_id,'lbc.id'=>$check_data[0]->circulation_id])
            ->whereNull('se.end_date')
            ->get();
            $res['circulation_data'] = $libraryCirculations;
        }

        $res['item_code'] = $request->item_code;
        return is_mobile($type, 'library/quick_return', $res, 'view');    
    }

    public function checkIssue(Request $request){
        $syear= session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $issuedBookDetails = DB::table('library_items as li')
        ->selectRaw('CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) as student_name,s.enrollment_no,s.mobile,std.name as standard,d.name as division, lbc.id as circulation_id, lbc.student_id, lbc.issued_date, lbc.due_date, lbc.return_date, li.item_code, li.received_date, li.order_date, li.order_no, li.item_status, lb.id as book_id, lb.title as book_name, lb.publisher_name, lb.author_name, lb.edition')
        ->join('library_book_circulations as lbc',function($join){
            $join->on('lbc.book_id', '=', 'li.book_id')->on('lbc.item_code', '=', 'li.id');
        } )
        ->join('library_books as lb', 'lb.id', '=', 'lbc.book_id')        
        ->join('tblstudent as s', 's.id', '=', 'lbc.student_id')
        ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
        ->join('standard as std', 'std.id', '=', 'se.standard_id')
        ->join('division as d', 'd.id', '=', 'se.section_id')
        ->where('lbc.book_id',$request->book_id)
        ->when($request->item_code,function($q) use($request){
            $q->where('lbc.item_code',$request->item_code);
        })
        ->whereNull('lbc.return_date')
        ->whereNull('se.end_date')
        ->get();
        
        return $issuedBookDetails;
    }

    public function allBookLists(Request $request){
        try{
            $type= $request->type;
            $sub_institute_id = session()->get('sub_institute_id');
            if($type=="API"){
                $sub_institute_id = $request->sub_institute_id; 
            }
            $bookLists = DB::table('library_books as lb')
            ->leftJoin('library_items as li', 'li.book_id', '=', 'lb.id')
            ->select('lb.*', 'li.id as item_id', 'li.item_code')
            ->where('lb.sub_institute_id', $sub_institute_id)
            ->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] ="Success";

            if(empty($bookLists)){
                $res['status_code'] = 0;
                $res['message'] = "No Books Found for sub_institute_id ".$sub_institute_id;
            }
            $res['bookLists'] = $bookLists;
            return $res;
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
