<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryItem;
use App\Models\student\tblstudentModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use Yajra\DataTables\DataTables;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = LibraryBook::latest()
                ->get();
            return DataTables::of($data)
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" id="' . $row->id . '" name="someCheckbox" class="checkSingle" />';
                })
                ->addColumn('image', function ($row) {
                    return '<img src="' . Storage::disk('books')->url($row->image) . '" height="100" width="100" alt="">';
                })
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $actionBtn = '<a href="javascript:void(0)" class="delete m-2 btn btn-danger btn-delete" title="Delete Book" data-id="' . $row->id . '"><i class="fa fa-trash"></i></a><a href="javascript:void(0)" class="m-2 btn btn-warning btn-edit ml-1" title="Edit Book" data-id="' . $row->id . '"><i class="fa fa-pencil"></i></a><a href="javascript:void(0)" class="m-2 btn btn-primary print-barcode ml-1" title="Print Barcode" data-id="' . $row->id . '"><i class="fa fa-barcode"></i></a><a href="javascript:void(0)" class="m-2 btn btn-info circulation ml-1" title="Issue/Return Book" data-id="' . $row->id . '"><i class="fa fa-retweet"></i></a>';
                    return $actionBtn;
                })
                ->rawColumns(['checkbox', 'image', 'action'])
                ->make(true);
        }
        return view('library.books');
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
        $request->validate([
            "title" => "required|string",
            "sub_title" => "required|string",
            "material_resource_type" => "required|in:book,magazine,reference,comic,class_book,newspaper,other",
            "edition" => "required|string",
            "tags" => "required|string",
            "no_of_items" => "required|numeric",
            "author_name" => "required|string",
            "isbn_issn" => "required|string",
            "classification" => "required|string",
            "publisher_name" => "required|string",
            "publish_year" => "required|numeric",
            "publish_place" => "required|string",
            "collation" => "required|numeric",
            "series_title" => "required|string",
            "call_number" => "required|numeric",
            "language" => "required|string",
            "source" => "required|string",
            "subject" => "required|string",
            "price" => "required|numeric",
            "price_currency" => "required|string",
            "notes" => "required|string",
            "review" => "required|string",
        ]);
        try {
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
            $createBook->collation = $request->collation;
            $createBook->series_title = $request->series_title;
            $createBook->call_number = $request->call_number;
            $createBook->language = $request->language;
            $createBook->source = $request->source;
            $createBook->subject = $request->subject;
            $createBook->price = $request->price;
            $createBook->price_currency = $request->price_currency;
            $createBook->notes = $request->notes;
            $createBook->review = $request->review;
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
                $itemCount = LibraryItem::where('book_id', $createBook->id)->get()->count();
                if ($request->no_of_items < $itemCount) {
                    LibraryItem::where('book_id', $createBook->id)->where('item_code', '<', $request->no_of_items)->delete();
                }
                for ($i = 1; $i <= $request->no_of_items; $i++) {
                    $objItem = LibraryItem::updateOrCreate([
                        'book_id' => $createBook->id,
                        'call_number' => $createBook->call_number,
                        'item_code' => $i,
                    ]);
                }
                if ($objItem) {
                    return response()->json(['message' => 'Book created Successfully !!', 'status' => true], 200);
                }
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
    public function show($enroll)
    {
        try {
            $details = tblstudentModel::where('enrollment_no', $enroll)->first();
            
        } catch (Exception $e) {
            return response()->json($e->getMessage(),500);
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
        //
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
    public function destroy($id)
    {
        //
    }
}
