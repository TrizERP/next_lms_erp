<?php

namespace App\Http\Controllers\result\hpc_activity_transfer;

use App\Http\Controllers\Controller;
use App\Services\result\HpcActivityTransferService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;
use function App\Helpers\is_mobile;

class HpcActivityTransferController extends Controller
{
    protected HpcActivityTransferService $service;

    public function __construct(HpcActivityTransferService $service)
    {
        $this->service = $service;
    }

    /**
     * Show the transfer page.
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = 'Success';
        $res['sub_institutes'] = $this->service->getSubInstitutes();

        return is_mobile($type, 'result/hpc_activity_transfer/index', $res, 'view');
    }

    /**
     * AJAX: standards belonging to a sub institute.
     */
    public function standards(Request $request)
    {
        $request->validate(['sub_institute_id' => 'required|integer']);

        return response()->json([
            'status' => 1,
            'standards' => $this->service->getStandards((int) $request->input('sub_institute_id')),
        ]);
    }

    /**
     * AJAX: dry-run preview - nothing is written to the database.
     */
    public function preview(Request $request)
    {
        $validated = $this->validateTransferRequest($request);

        try {
            $result = $validated['transfer_type'] === 'standard'
                ? $this->service->previewStandardTransfer(
                    $validated['source_sub_institute_id'],
                    $validated['source_standard_id'],
                    $validated['target_standard_id']
                )
                : $this->service->previewSubInstituteTransfer(
                    $validated['source_sub_institute_id'],
                    $validated['target_sub_institute_id']
                );

            return response()->json(['status' => 1, 'message' => 'Preview generated successfully.', 'data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Failed to generate preview: '.$e->getMessage()], 500);
        }
    }

    /**
     * AJAX: execute the confirmed transfer inside a DB transaction.
     */
    public function transfer(Request $request)
    {
        $validated = $this->validateTransferRequest($request);
        $createdBy = session()->get('user_id') ?: config('hpc.default_created_by');

        try {
            $result = $validated['transfer_type'] === 'standard'
                ? $this->service->transferStandardToStandard(
                    $validated['source_sub_institute_id'],
                    $validated['source_standard_id'],
                    $validated['target_standard_id'],
                    $createdBy
                )
                : $this->service->transferSubInstituteToSubInstitute(
                    $validated['source_sub_institute_id'],
                    $validated['target_sub_institute_id'],
                    $createdBy
                );

            return response()->json(['status' => 1, 'message' => 'Transfer completed successfully.', 'data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Transfer failed and was rolled back: '.$e->getMessage()], 500);
        }
    }

    protected function validateTransferRequest(Request $request): array
    {
        $rules = [
            'transfer_type' => 'required|in:standard,sub_institute',
            'source_sub_institute_id' => 'required|integer',
        ];

        if ($request->input('transfer_type') === 'standard') {
            $rules['source_standard_id'] = 'required|integer';
            $rules['target_standard_id'] = 'required|integer|different:source_standard_id';
        } else {
            $rules['target_sub_institute_id'] = 'required|integer|different:source_sub_institute_id';
        }

        return $request->validate($rules);
    }
}
