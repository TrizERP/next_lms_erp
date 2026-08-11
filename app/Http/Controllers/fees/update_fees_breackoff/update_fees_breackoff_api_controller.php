<?php

namespace App\Http\Controllers\fees\update_fees_breackoff;

use Illuminate\Http\Request;

/**
 * Stateless API adapter for the legacy Update Fees Breakoff controller.
 * The Blade controller remains unchanged and continues using its web session.
 */
class update_fees_breackoff_api_controller extends update_fees_breackoff_controller
{
    public function store(Request $request)
    {
        if ($request->input('action') === 'insert') {
            $request->session()->put('req', [
                'grade' => $request->input('grade'),
                'standard' => $request->input('standard'),
                'month' => $request->input('month_id'),
            ]);
        }

        return parent::store($request);
    }
}
