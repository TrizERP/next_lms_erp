<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\lms\h5p\h5pFlashcard;
use function App\Helpers\is_mobile;

class H5PFlashcardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if(in_array($type,['API','JSON'])){
            $sub_institute_id = $request->sub_institute_id;
            $request->validate([
                'sub_institute_id'=>'required|integer',
                'standard_id'=>'required|integer',
                'subject_id'=>'required|integer',
                'chapter_id'=>'required|integer',
            ]);
        }

        $res = $request->all();
        $res['flashCards'] = h5pFlashcard::where([
            'sub_institute_id'=>$sub_institute_id,
            'standard_id'=>$request->standard_id,
            'subject_id'=>$request->subject_id,
            'chapter_id'=>$request->chapter_id,
        ])->latest()->get();
        // return view('flashcards.index', compact('res'));
        return is_mobile($type,'lms/h5p/flashcard/index',$res,'view');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
       $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if(in_array($type,['API','JSON'])){
            $sub_institute_id = $request->sub_institute_id;
            $request->validate([
                'sub_institute_id'=>'required|integer',
                'standard_id'=>'required|integer',
                'subject_id'=>'required|integer',
                'chapter_id'=>'required|integer',
            ]);
        }

        $res = $request->all();
        // return view('flashcards.index', compact('res'));
        return is_mobile($type,'lms/h5p/flashcard/create',$res,'view');
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
        'cards' => 'required|array|min:1',
        'cards.*.question' => 'required|string',
        'cards.*.correct_answer' => 'required|string',
        'cards.*.content' => 'nullable|string',
        'cards.*.hint' => 'nullable|string',
        'standard_id' => 'required',
        'subject_id' => 'required',
        'chapter_id' => 'required'
    ]);

    $createdCards = [];
    
    foreach ($request->cards as $card) {
        $flashcard = h5pFlashcard::create([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'content' => $card['content'] ?? null,
            'question' => $card['question'],
            'correct_answer' => strtolower(trim($card['correct_answer'])),
            'hint' => $card['hint'] ?? null,
            'created_by' => auth()->id()
        ]);
        
        $createdCards[] = $flashcard;
    }

    return redirect()->route('flashcards.index')->with('data', [
        'status' => true,
        'message' => count($createdCards) . ' flashcard(s) created successfully!'
    ]);
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
        $card = h5pFlashcard::findOrFail($id);

        $card->update([
            'content' => $request->content,
            'question' => $request->question,
            'correct_answer' => strtolower(trim($request->correct_answer)),
            'hint' => $request->hint,
            'updated_by' => auth()->id()
        ]);

        return redirect()->route('flashcards.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $card = h5pFlashcard::findOrFail($id);
        $card->deleted_by = auth()->id();
        $card->save();
        $card->delete();

        return back();
    }
}
