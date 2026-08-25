<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\lms\h5p\h5pFlashcard;
use App\Models\AuditLog;
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

        if (in_array($type, ['API', 'JSON'])) {
            // sub_institute_id must come from the authenticated session (hydrated
            // from a verified JWT), not the client-supplied request value, which
            // is spoofable. Still require it on the payload for validation.
            $request->validate([
                'sub_institute_id' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'chapter_id' => 'required|integer',
            ]);
        }

        $res = $request->all();
        $res['flashCards'] = h5pFlashcard::where([
            'sub_institute_id' => $sub_institute_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
        ])->get();
        // return $res;
        if(in_array(session()->get('user_profile_name'),['student','Student','STUDENT']))
        {
            // return redirect()->route('h5p_flashacard.show', [
            //     'id' => 0,
            //     'chapter_id'  => $request->chapter_id,
            //     'standard_id' => $request->standard_id,
            //     'subject_id'  => $request->subject_id
            // ] + $request->query());
            return $this->show($request,0);
        }
        return is_mobile($type, 'lms/h5p/flashcard/index', $res, 'view');
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
        if (in_array($type, ['API', 'JSON'])) {
            // sub_institute_id must come from the authenticated session (hydrated
            // from a verified JWT), not the client-supplied request value, which
            // is spoofable. Still require it on the payload for validation.
            $request->validate([
                'sub_institute_id' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'chapter_id' => 'required|integer',
            ]);
        }

        $res = $request->all();
        // return view('flashcards.index', compact('res'));
        return is_mobile($type, 'lms/h5p/flashcard/create', $res, 'view');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // return $request->all();
        // exit;
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if (in_array($type, ['API', 'JSON'])) {
            // sub_institute_id / user_id must come from the authenticated session
            // (hydrated from a verified JWT), not the client-supplied request
            // values, which are spoofable. Still require them on the payload for
            // validation.
            $request->validate([
                'sub_institute_id' => 'required|integer',
                'user_id' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'chapter_id' => 'required|integer',
            ]);
        }

        // Every field written into h5p_flashcard below (question, correct_answer)
        // must be present on each card entry, regardless of request type.
        $request->validate([
            'cards' => 'required|array|min:1',
            'cards.*.question' => 'required|string',
            'cards.*.correct_answer' => 'required|string',
        ]);

        $createdCards = [];

        foreach ($request->cards as $card) {
            $flashcard = h5pFlashcard::create([
                'sub_institute_id' => $sub_institute_id,
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'content' => $card['content'] ?? null,
                'question' => $card['question'],
                'correct_answer' => strtolower(trim($card['correct_answer'])),
                'hint' => $card['hint'] ?? null,
                'created_by' => $user_id,
                'created_at' => now(),
            ]);

            $createdCards[] = $flashcard;

            AuditLog::record([
                'module' => 'lms',
                'action' => 'h5p_flashcard_created',
                'entity_type' => 'h5p_flashcard',
                'entity_id' => $flashcard->id,
                'new_values' => $flashcard->toArray(),
            ]);
        }

        if(in_array($type, ['API', 'JSON'])){
            return response()->json([
                'status' => true,
                'message' => count($createdCards) . ' flashcard(s) created successfully!',
                'data' => $createdCards
            ]);
        }
        return redirect()->route('h5p_flashacard.index', [
            'chapter_id'  => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id'  => $request->subject_id
        ])->with('data', [
            'status'  => true,
            'message' => count($createdCards) . ' flashcard(s) created successfully!'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
       $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if (in_array($type, ['API', 'JSON'])) {
            // sub_institute_id must come from the authenticated session (hydrated
            // from a verified JWT), not the client-supplied request value, which
            // is spoofable. Still require it on the payload for validation.
            $request->validate([
                'sub_institute_id' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'chapter_id' => 'required|integer',
            ]);
        }

        $res = $request->all();
        $res['flashCards'] = h5pFlashcard::where([
            'sub_institute_id' => $sub_institute_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
        ])->get();
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        // return $res;
        return is_mobile($type, 'lms/h5p/flashcard/show', $res, 'view');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if(in_array($type, ['API', 'JSON'])){
            // sub_institute_id must come from the authenticated session (hydrated
            // from a verified JWT), not the client-supplied request value, which
            // is spoofable. Still require it on the payload for validation.
            $request->validate([
                'id' => 'required|integer',
                'sub_institute_id' => 'required|integer',
            ]);
        }
        $res['card'] = h5pFlashcard::findOrFail($id);
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;
        return is_mobile($type, 'lms/h5p/flashcard/edit', $res, 'view');
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
        // return $request->all();
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        if(in_array($type, ['API', 'JSON'])){
            // sub_institute_id / user_id must come from the authenticated session
            // (hydrated from a verified JWT), not the client-supplied request
            // values, which are spoofable. Still require sub_institute_id on the
            // payload for validation.
            $request->validate([
                'id' => 'required|integer',
                'sub_institute_id' => 'required|integer',
            ]);
        }
        $card = h5pFlashcard::findOrFail($id);

        // correct_answer is read via strtolower(trim(...)) below, so it must be
        // present and a string regardless of request type.
        $request->validate([
            'cards' => 'required|array|min:1',
            'cards.0.question' => 'required|string',
            'cards.0.correct_answer' => 'required|string',
        ]);

        $card->update([
            'content' => $request->cards[0]['content'] ?? '-',
            'question' => $request->cards[0]['question'] ?? '-',
            'correct_answer' => strtolower(trim($request->cards[0]['correct_answer'])),
            'hint' => $request->cards[0]['hint'] ?? '-',
            'updated_by' => $user_id,
            'updated_at' => now(),
        ]);

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_flashcard_updated',
            'entity_type' => 'h5p_flashcard',
            'entity_id' => $card->id,
            'new_values' => $card->toArray(),
        ]);

        if(in_array($type, ['API', 'JSON'])){
            return response()->json([
                'status' => true,
                'message' => 'Flashcard updated successfully!',
            ]);
        }
        return redirect()->route('h5p_flashacard.index', [
            'chapter_id'  => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id'  => $request->subject_id
        ])->with('data', [
            'status'  => $card ? 1 : 0,
            'message' => $card ? 'Flashcard updated successfully!' : 'Flashcard not found!'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->type;
         $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        if(in_array($type, ['API', 'JSON'])){
            // sub_institute_id / user_id must come from the authenticated session
            // (hydrated from a verified JWT), not the client-supplied request
            // values, which are spoofable. Still require sub_institute_id on the
            // payload for validation.
            $request->validate([
                'id' => 'required|integer',
                'sub_institute_id' => 'required|integer',
            ]);
        }
        $card = h5pFlashcard::findOrFail($id);
        $card->deleted_by = $user_id;
        $card->save();
        $card->delete();

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_flashcard_deleted',
            'entity_type' => 'h5p_flashcard',
            'entity_id' => $card->id,
            'new_values' => $card->toArray(),
        ]);

         if(in_array($type, ['API', 'JSON'])){
            return response()->json([
                'status' => true,
                'message' => 'Flashcard updated successfully!',
            ]);
        }
        return redirect()->route('h5p_flashacard.index', [
            'chapter_id'  => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id'  => $request->subject_id
        ])->with('data', [
            'status'  => $card ? 1 : 0,
            'message' => $card ? 'Flashcard deleted successfully!' : 'Flashcard not found!'
        ]);
    }
}
