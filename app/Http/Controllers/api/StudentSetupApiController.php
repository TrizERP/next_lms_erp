<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentSetupApiController extends Controller
{
    use GetsJwtToken;

    private const RESOURCES = [
        'quota' => ['table' => 'student_quota', 'name' => 'title', 'year' => false],
        'house' => ['table' => 'house_master', 'name' => 'house_name', 'year' => true],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        if ($error = $this->guard($request, $resource)) return $error;
        $config = self::RESOURCES[$resource];
        $query = DB::table($config['table'])->where('sub_institute_id', $request->sub_institute_id);
        if ($config['year']) $query->where('syear', $request->syear);
        return response()->json(['status' => 1, 'message' => 'Success', 'data' => $query->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        if ($error = $this->guard($request, $resource, true)) return $error;
        $config = self::RESOURCES[$resource];
        $data = [$config['name'] => trim($request->input('name')), 'sort_order' => $request->input('sort_order'),
            'sub_institute_id' => $request->sub_institute_id, 'created_by' => $request->user_id];
        if ($config['year']) $data['syear'] = $request->syear;
        $id = DB::table($config['table'])->insertGetId($data);
        return response()->json(['status' => 1, 'message' => ucfirst($resource).' added successfully.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        if ($error = $this->guard($request, $resource, true)) return $error;
        $config = self::RESOURCES[$resource];
        $updated = DB::table($config['table'])->where('id', $id)->where('sub_institute_id', $request->sub_institute_id)
            ->update([$config['name'] => trim($request->input('name')), 'sort_order' => $request->input('sort_order')]);
        return response()->json(['status' => $updated ? 1 : 0, 'message' => $updated ? ucfirst($resource).' updated successfully.' : 'Record not found.', 'data' => []], $updated ? 200 : 404);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        if ($error = $this->guard($request, $resource)) return $error;
        $config = self::RESOURCES[$resource];
        $deleted = DB::table($config['table'])->where('id', $id)->where('sub_institute_id', $request->sub_institute_id)->delete();
        return response()->json(['status' => $deleted ? 1 : 0, 'message' => $deleted ? ucfirst($resource).' deleted successfully.' : 'Record not found.', 'data' => []], $deleted ? 200 : 404);
    }

    private function guard(Request $request, string $resource, bool $payload = false): ?JsonResponse
    {
        try { if (! $this->jwtToken()->validate()) return response()->json(['status' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401); }
        catch (\Exception $e) { return response()->json(['status' => 2, 'message' => $e->getMessage(), 'data' => []], 401); }
        if (! isset(self::RESOURCES[$resource])) return response()->json(['status' => 0, 'message' => 'Unknown resource.', 'data' => []], 404);
        $rules = ['sub_institute_id' => 'required|integer', 'syear' => 'required'];
        if ($payload) $rules += ['name' => 'required|string|max:255', 'sort_order' => 'nullable|integer|min:0'];
        $validator = Validator::make($request->all(), $rules);
        return $validator->fails() ? response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'data' => []], 422) : null;
    }
}
