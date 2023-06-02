<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryItem;
use Exception;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('library.books');
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
    public function show($id)
    {
        //
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
