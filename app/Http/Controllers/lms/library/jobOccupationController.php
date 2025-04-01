<?php

namespace App\Http\Controllers\lms\library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\lms\library\jobOccupation;
use GenTux\Jwt\GetsJwtToken;
use Validator;
use Illuminate\Support\Facades\DB;

class jobOccupationController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_id = $request->get('user_id');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $jobroleData = jobOccupation::when($request->has('industries'),function($query) use($request){
            $query->where('industries',$request->industries);
        })
        ->when($request->has('sector'),function($query) use($request){
            $query->where('sector',$request->sector);
        })
        ->when($request->has('track'),function($query) use($request){
            $query->where('track',$request->track);
        })
        ->when($request->has('jobrole'),function($query) use($request){
            $query->where('jobrole',$request->jobrole);
        })
        ->when($request->has('status'),function($query) use($request){
            $query->where('status',$request->status);
        })
        ->when($request->has('type'),function($query) use($request){
            $query->where('type',$request->type);
        })
        ->when($request->has('code'),function($query) use($request){
            $query->where('code',$request->code);
        })
        ->get();
        
        $res['user_id'] = $user_id;
        $res['jobroleData'] = $jobroleData;
        
        return is_mobile($type, "lms/library/skill_library/index", $res, "view");      

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
        //
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
