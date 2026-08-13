<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class PhotoVideoGallaryApiController extends Controller
{
    use GetsJwtToken;

    private function authenticate()
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json([
                    'status_code' => 2,
                    'message'     => 'Token Auth Failed',
                    'data'        => [],
                ], 401);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status_code' => 2,
                'message'     => $exception->getMessage(),
                'data'        => [],
            ], 401);
        }

        return null;
    }

    private function validateContext(Request $request)
    {
        return Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
            'user_id'          => 'required|integer',
        ]);
    }

    private function failed($message, $status = 422)
    {
        return response()->json([
            'status_code' => 0,
            'message'     => $message,
            'data'        => [],
        ], $status);
    }

    public function index(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        $query = DB::table('photo_video_gallary as pvg')
            ->leftJoin('standard as s', function ($join) {
                $join->on('s.id', '=', 'pvg.standard_id')
                     ->on('s.sub_institute_id', '=', 'pvg.sub_institute_id');
            })
            ->leftJoin('division as d', function ($join) {
                $join->on('d.id', '=', 'pvg.division_id')
                     ->on('d.sub_institute_id', '=', 'pvg.sub_institute_id');
            })
            ->selectRaw('pvg.*, s.name as std_name, d.name as div_name')
            ->where('pvg.syear', $syear)
            ->where('pvg.sub_institute_id', $subInstituteId);

        if ($request->filled('standard_id')) {
            $query->where('pvg.standard_id', $request->integer('standard_id'));
        }

        if ($request->filled('division_id')) {
            $query->where('pvg.division_id', $request->integer('division_id'));
        }

        // `type` doubles as the legacy is_mobile()-style API/JSON response-format
        // flag that the frontend's generic request helper always sends
        // (appendCommonParams sets type=API on every call). Only treat it as a
        // gallery media-type filter when it's actually Photo/Video.
        if ($request->filled('type') && in_array($request->input('type'), ['Photo', 'Video'], true)) {
            $query->where('pvg.type', $request->input('type'));
        }

        if ($request->filled('album_title')) {
            $query->where('pvg.album_title', 'like', '%' . $request->input('album_title') . '%');
        }

        if ($request->filled('title')) {
            $query->where('pvg.title', 'like', '%' . $request->input('title') . '%');
        }

        $rows = $query->orderByDesc('pvg.id')->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => $rows,
        ]);
    }

    public function store(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
            'user_id'          => 'required|integer',
            'title'            => 'required|string|max:250',
            'album_title'      => 'required|string|max:150',
            'type'             => 'required|in:Photo,Video',
            'date_'            => 'required|date',
            'standard'         => 'required|array',
            'division'         => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $type = $request->input('type');
        $title = $request->input('title');
        $albumTitle = $request->input('album_title');
        $date = $request->input('date_');
        $standards = $request->input('standard', []);
        $divisions = $request->input('division', []);

        $insertedIds = [];

        foreach ($standards as $std) {
            foreach ($divisions as $div) {
                $fileName = '';
                if ($type === 'Photo') {
                    if ($request->hasFile('attachment')) {
                        foreach ($request->file('attachment') as $file) {
                            $originalName = $file->getClientOriginalName();
                            $ext = File::extension($originalName);
                            $fileName = 'attachment_' . time() . '_' . uniqid() . '.' . $ext;
                            $file->storeAs('public/photo_video_gallary/', $fileName);
                        }
                    }
                } else {
                    $fileName = $request->input('youtube_link', '');
                }

                $values = [
                    'syear'            => $syear,
                    'standard_id'      => $std,
                    'division_id'      => $div,
                    'title'            => $title,
                    'album_title'      => $albumTitle,
                    'type'             => $type,
                    'file_name'        => $fileName,
                    'date_'            => $date,
                    'sub_institute_id' => $subInstituteId,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];

                $id = DB::table('photo_video_gallary')->insertGetId($values);
                $insertedIds[] = $id;
            }
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Photo video gallery added successfully.',
            'data'        => ['id' => $insertedIds],
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
            'user_id'          => 'required|integer',
            'title'            => 'required|string|max:250',
            'album_title'      => 'required|string|max:150',
            'type'             => 'required|in:Photo,Video',
            'date_'            => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');

        $existing = DB::table('photo_video_gallary')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (! $existing) {
            return $this->failed('Gallery entry not found.', 404);
        }

        $updateData = [
            'title'       => $request->input('title'),
            'album_title' => $request->input('album_title'),
            'type'        => $request->input('type'),
            'date_'       => $request->input('date_'),
            'updated_at'  => now(),
        ];

        if ($request->input('type') === 'Video') {
            $updateData['file_name'] = $request->input('youtube_link', '');
        } elseif ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalName = $file->getClientOriginalName();
            $ext = File::extension($originalName);
            $fileName = 'attachment_' . time() . '_' . uniqid() . '.' . $ext;
            $file->storeAs('public/photo_video_gallary/', $fileName);
            $updateData['file_name'] = $fileName;
        }

        DB::table('photo_video_gallary')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->update($updateData);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Gallery entry updated successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');

        $deleted = DB::table('photo_video_gallary')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->delete();

        if (! $deleted) {
            return $this->failed('Gallery entry not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Gallery entry deleted successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }
}
