<?php


use App\Http\Controllers\AJAXController;
use App\Http\Controllers\fees\bank_master\bank_master_controller;
use App\Http\Controllers\fees\cheque_cash\cheque_cash_controller;
use App\Http\Controllers\fees\college_fees_collect\college_fees_collect_controller;
use App\Http\Controllers\fees\fees_breackoff\fees_breackoff_controller;
use App\Http\Controllers\fees\cheque_reconciliation\ChequeReconciliationController;
use App\Http\Controllers\fees\fees_cancel\feesCancelController;
use App\Http\Controllers\fees\fees_cancel\feesRefundController;
use App\Http\Controllers\fees\fees_circular\feesCircularController;
use App\Http\Controllers\fees\fees_circular\feesCircularMasterController;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Http\Controllers\fees\fees_report\daily_voucherController;
use App\Http\Controllers\fees\fees_report\feesCancelReportController;
use App\Http\Controllers\fees\fees_report\feesFineDiscountReportController;
use App\Http\Controllers\fees\fees_report\feesInstituteWiseFeesReportController;
use App\Http\Controllers\fees\fees_report\feesMonthlyReportController;
use App\Http\Controllers\fees\fees_report\feesOverallHeadwisePendingReportController;
use App\Http\Controllers\fees\fees_report\feesOverallHeadwiseReportController;
use App\Http\Controllers\fees\fees_report\feesOverallReportController;
use App\Http\Controllers\fees\fees_report\feesDefaulterReportController;
use App\Http\Controllers\fees\fees_report\feesReportController;
use App\Http\Controllers\fees\fees_report\feesPayoutController;
use App\Http\Controllers\fees\fees_report\feesStatusController;
use App\Http\Controllers\fees\fees_report\feesStructureReportController;
use App\Http\Controllers\fees\fees_report\feesTypewiseReportController;
use App\Http\Controllers\fees\fees_report\imprestRefundReportController;
use App\Http\Controllers\fees\fees_report\otherfeesReportController;
use App\Http\Controllers\fees\fees_report\otherNew_CancelFeesReportController;
use App\Http\Controllers\fees\fees_report\otherNewfeesReportController;
use App\Http\Controllers\fees\fees_report\tallyExportReportController;
use App\Http\Controllers\fees\fees_title\fees_title_controller;
use App\Http\Controllers\fees\feesReceiptBookMasterController;
use App\Http\Controllers\fees\imprest_fees_cancel\imprestFeesCancelController;
use App\Http\Controllers\fees\map_year\map_year_controller;
use App\Http\Controllers\fees\NACH\s1excel_exportController;
use App\Http\Controllers\fees\NACH\s2excel_importController;
use App\Http\Controllers\fees\NACH\s3excel_exportController;
use App\Http\Controllers\fees\NACH\s4excel_importController;
use App\Http\Controllers\fees\online_fees\online_fees_collect_controller;
use App\Http\Controllers\fees\online_fees\online_fees_settigs_controller;
use App\Http\Controllers\fees\online_fees\online_fees_split_controller;
use App\Http\Controllers\fees\other_fee_map\other_fee_map_controller;
use App\Http\Controllers\fees\other_fees_cancel\other_fees_cancel_controller;
use App\Http\Controllers\fees\other_fees_collect\other_fees_collect_controller;
use App\Http\Controllers\fees\other_fees_title\other_fees_title_controller;
use App\Http\Controllers\fees\tblfeesConfigController;
use App\Http\Controllers\fees\tblfeesHeadTypeMasterController;
use App\Http\Controllers\fees\tblfeesLateController;
use App\Http\Controllers\fees\update_fees_breackoff\update_fees_breackoff_controller;
use App\Http\Controllers\fees\fees_breackoff\monthlyBreakoffController;
use App\Http\Controllers\fees\fees_month_header\feesMonthHeadercontroller;
use App\Http\Controllers\fees\fees_report\studentBreakoffReportController;

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'fees', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('fees_config_master', tblfeesConfigController::class);
    Route::resource('fees_circular_master', feesCircularMasterController::class);
    Route::resource('fees_late_master', tblfeesLateController::class);
    Route::resource('fees_head_type_master', tblfeesHeadTypeMasterController::class);
    Route::resource('fees_receipt_book_master', feesReceiptBookMasterController::class);
    Route::resource('fees_circular', feesCircularController::class);
    Route::resource('fees_collection_report', feesReportController::class);
    Route::resource('fees_payout_report', feesPayoutController::class);
    Route::resource('other_fees_report', otherfeesReportController::class);
    Route::resource('otherNew_fees_report', otherNewfeesReportController::class);
    Route::resource('NACH_s1excel_export', s1excel_exportController::class);
    Route::resource('NACH_s2excel_import', s2excel_importController::class);
    Route::resource('NACH_s3excel_export', s3excel_exportController::class);
    Route::resource('NACH_s4excel_import', s4excel_importController::class);
    Route::resource('daily_voucher', daily_voucherController::class);
    Route::resource('monthly_breakoff', monthlyBreakoffController::class);    
    Route::resource('fees_month_header', feesMonthHeadercontroller::class);    
    Route::resource('student_breakoff_report', studentBreakoffReportController::class);    

    Route::get('ajax_ledgerData', [otherNewfeesReportController::class, 'ajax_ledgerData'])->name('ajax_ledgerData');

    Route::resource('otherNew_cancel_fees_report', otherNew_CancelFeesReportController::class);
    Route::resource('fees_type_wise_report', feesTypewiseReportController::class);
    Route::resource('tally_export_fees_report', tallyExportReportController::class);
    Route::resource('fees_overall_headwise_report', feesOverallHeadwiseReportController::class);
    Route::resource('fees_headwise_pending_report', feesOverallHeadwisePendingReportController::class);
    Route::resource('fees_overall_report', feesOverallReportController::class);
    Route::resource('fees_defaulter_report', feesDefaulterReportController::class);
    Route::resource('fees_status_report', feesStatusController::class);

    // Remain fees send SMS
    Route::post('fees_remain_sms', [feesStatusController::class, 'ajaxRemainFeesSMSsend'])->name('remainFeesNotification');

    Route::resource('fees_title', fees_title_controller::class);

    Route::resource('other_fees_title', other_fees_title_controller::class);
    Route::resource('other_fees_collect', other_fees_collect_controller::class);
    Route::resource('other_fees_cancel', other_fees_cancel_controller::class);

    Route::resource('other_fee_map', other_fee_map_controller::class);
    Route::resource('cheque_cash', cheque_cash_controller::class);
    Route::resource('map_year', map_year_controller::class);
    Route::resource('fees_breackoff', fees_breackoff_controller::class);
    Route::resource('bank_master', bank_master_controller::class);
    Route::resource('fees_collect', fees_collect_controller::class);
    Route::resource('college_fees_collect', college_fees_collect_controller::class);
    Route::resource('online_fees', online_fees_settigs_controller::class);
    Route::resource('online_fees_split', online_fees_split_controller::class);
    Route::resource('cheque_reconciliation', ChequeReconciliationController::class);

    // Route::get('online_fees\show_online_type', 'fees\online_fees\online_fees_collect_controller@showTypes')->name('online_show_type');
    Route::get('show_details', [ChequeReconciliationController::class, 'show_details'])->name('show_details');
    Route::get('search_details', [ChequeReconciliationController::class, 'search_details'])->name('search_details');

    Route::get('hdfcpayment', function ($id = null) {
        return view('fees/online_fees/hdfcpayment', ['name' => 'James']);
    })->name('hdfcpayment');
    Route::get('aggre_pay', function ($id = null) {
        return view('fees/online_fees/aggre_pay', ['name' => 'James']);
    })->name('aggre_pay');
    Route::get('axis', function ($id = null) {
        return view('fees/online_fees/axis', ['name' => 'James']);
    })->name('axis');
    Route::get('icici', function ($id = null) {
        return view('fees/online_fees/icici', ['name' => 'James']);
    })->name('icici');
    Route::get('razorpay', function ($id = null) {
        return view('fees/online_fees/razorpay', ['name' => 'James']);
    })->name('razorpay');
Route::get('payphi', function ($id = null) {
        return view('fees/online_fees/payphi', ['name' => 'James']);
    })->name('payphi');

    Route::post('api/get-fees-list', [AJAXController::class, 'getFees'])->name('get-fees-list');
    Route::resource('update_fees_breackoff', update_fees_breackoff_controller::class);

    Route::post('fees_collect/show_student', ['as' => 'fees_collect.show_student', 'uses' => 'fees\fees_collect\fees_collect_controller@show_student']);
    Route::post('college_fees_collect/show_student', ['as' => 'college_fees_collect.show_student', 'uses' => 'fees\college_fees_collect\college_fees_collect_controller@show_student']);

    Route::controller(feesCircularController::class)->group(function () {
        Route::post('fees_circular/show_student', 'showStudent')->name('fees_circular.show_student');
//    Route::post('fees_circular/show_student', ['as' => 'fees_circular.show_student', 'uses' => 'fees\fees_circular\feesCircularController@showStudent']);

        Route::post('fees_circular/show_circular', 'showCircular')->name('show_circular');
    });


    Route::resource('fees_cancel', feesCancelController::class);
    Route::controller(feesCancelController::class)->group(function () {
        Route::post('fees/fees_cancel', 'showFees')->name('show_cancel_fees');
        Route::post('fees/cancel_fees', 'cancelFees')->name('cancel_fees');
    });

    Route::resource('fees_refund', feesRefundController::class);
    Route::controller(feesRefundController::class)->group(function () {
        Route::post('fees/fees_refund', 'showFees')->name('show_fees');
        Route::post('fees/save_fees_refund', 'saveFeesRefund')->name('save_fees_refund');
    });


    Route::resource('imprest_fees_cancel', imprestFeesCancelController::class);
    Route::controller(imprestFeesCancelController::class)->group(function () {
        Route::post('show_imprest_cancel_fees', 'showImprestFees')->name('show_imprest_cancel_fees');
        Route::post('cancel_imprest_fees', 'cancelImprestFees')->name('cancel_imprest_fees');
    });

    Route::post('fees/fees_collection_report', [feesReportController::class, 'showFees'])->name('show_fees_collection_report');

    Route::post('fees/fees_payout_report', [feesPayoutController::class, 'showFeesPayout'])->name('show_fees_payout_report');

    Route::post('fees/fees_overall_report', [feesOverallReportController::class, 'showFeesOverall'])->name('show_fees_overall_report');

    Route::post('fees/fees_defaulter_report', [feesDefaulterReportController::class, 'showFeesDefaulter'])->name('show_fees_defaulter_report');

    Route::post('fees/fees_status_report', [feesStatusController::class, 'feesStatusReport'])->name('show_fees_status_report');

    Route::get('pdfview', array('as' => 'pdfview', 'uses' => 'ItemController@pdfview'));

    Route::controller(feesStructureReportController::class)->group(function () {
        Route::get('fees_structure_report_index', 'feesStructureReportIndex')->name("fees_structure_report_index");
        Route::post('fees_structure_report', 'feesStructureReport')->name("fees_structure_report");
    });

    Route::controller(feesCancelReportController::class)->group(function () {
        Route::get('fees_cancel_report_index', 'feesCancelReportIndex')->name("fees_cancel_report_index");
        Route::post('fees_cancel_report', 'feesCancelReport')->name("fees_cancel_report");
    });


    Route::get('fees_refund_report_index', [feesRefundController::class,'index'])->name("fees_refund_report_index");
    Route::post('fees_refund_report', [feesRefundController::class,'feesRefundReport'])->name("fees_refund_report");

    Route::resource('fees_monthly_report', feesMonthlyReportController::class);
    Route::post('getfeesMonthlyReport', [feesMonthlyReportController::class, 'getfeesMonthlyReport'])->name('getfeesMonthlyReport');


    Route::controller(feesInstituteWiseFeesReportController::class)->group(function () {
        Route::get('institute_wise_fees_paid_report_index', 'instituteWiseFeesPaidReportIndex')->name("institute_wise_fees_paid_report_index");
        Route::post('institute_wise_fees_paid_report', 'instituteWiseFeesPaidReport')->name("institute_wise_fees_paid_report");
    });

    Route::controller(feesFineDiscountReportController::class)->group(function () {
        Route::get('fees_fine_discount_report_index', 'feesFineDiscountReportIndex')->name("fees_fine_discount_report_index");
        Route::post('fees_fine_discount_report', 'feesFineDiscountReport')->name("fees_fine_discount_report");
    });

    Route::resource('imprest_refund_report', imprestRefundReportController::class);

});

Route::post('api/get-online-fees-list', [AJAXController::class, 'getOnlineFees'])->name('get-online-fees-list');
Route::post('fees/PaidUnpaid', [fees_collect_controller::class, 'PaidUnpaid']);
Route::post('fees/PaidUnpaidTeacher', [fees_collect_controller::class, 'PaidUnpaidTeacher']);
Route::group(['prefix' => 'report', 'middleware' => ['session', 'menu', 'logRoute']], function () {
    Route::resource('report_module', 'report\report_module\report_module_controller');
});
//online routes
Route::get('/fees/feesDetails/getDetails/{id}/{stud}', [fees_collect_controller::class, 'retrieveDataByUserId']);
Route::controller(online_fees_collect_controller::class)->group(function () {
    Route::get('fees/online_fees_collect', 'index');

    Route::post('fees/hdfc/online_fees_collect', 'hdfc')->name("hdfc_fees_collect");
    Route::post('fees/hdfc/online_fees_hdfcRequestHandler', 'hdfc_request_handler')->name("hdfc_request_handler");
    Route::post('fees/hdfc/online_fees_hdfcResponseHandler', 'hdfc_response_handler')->name("hdfc_response_handler");

    Route::post('fees/axis/online_fees_collect', 'axis')->name("axis_fees_collect");
    Route::post('fees/axis/online_fees_axisRequestHandler', 'axis_request_handler')->name("axis_request_handler");
    Route::get('fees/axis/online_fees_axisResponseHandler', 'axis_response_handler')->name("axis_response_handler");

    Route::post('fees/aggre_pay/online_fees_collect', 'aggre_pay')->name("aggre_pay_fees_collect");
    Route::post('fees/aggre_pay/online_fees_aggre_payRequestHandler', 'aggre_pay_request_handler')->name("aggre_pay_request_handler");
    Route::post('fees/aggre_pay/online_fees_aggre_payResponseHandler', 'aggre_pay_response_handler')->name("aggre_pay_response_handler");

    Route::post('fees/icici/online_fees_collect', 'icici')->name("icici_fees_collect");
    Route::post('fees/icici/online_fees_iciciRequestHandler', 'icici_request_handler')->name("icici_request_handler");
    //Route::post('fees/icici/online_fees_iciciResponseHandler', 'icici_response_handler')->name("icici_response_handler");
    Route::post('fees/online_fees_iciciresponsehandler', 'icici_response_handler')->name("icici_response_handler");

    Route::post('fees/razorpay/online_fees_collect', 'razorpay')->name("razorpay_fees_collect");
    Route::post('fees/razorpay/online_fees_razorpayRequestHandler', 'razorpay_request_handler')->name("razorpay_request_handler");
    Route::post('fees/razorpay/online_fees_razorpayResponseHandler', 'razorpay_response_handler')->name("razorpay_response_handler");

    Route::post('fees/payphi/online_fees_collect', 'payphi')->name("payphi_fees_collect");
    Route::post('fees/payphi/online_fees_payphiRequestHandler', 'payphi_request_handler')->name("payphi_request_handler");
    Route::post('fees/payphi/online_fees_handleInitiateSaleResponse', 'handle_initiatesale_response')->name("handle_initiatesale_response");
    Route::post('fees/payphi/online_fees_payphiResponseHandler', 'payphi_response_handler')->name("payphi_response_handler");

});

Route::controller(AJAXController::class)->group(function () {
    Route::get('fees/get-student', 'getStudentFromMobile')->name('get-student');
    Route::get('ajax_PDF_FeesReceipt', 'ajax_PDF_FeesReceipt')->name('ajax_PDF_FeesReceipt');
    Route::get('ajax_PDF_Bulk_OtherFeesReceipt', 'ajax_PDF_Bulk_OtherFeesReceipt')->name('ajax_PDF_Bulk_OtherFeesReceipt');
    Route::get('ajax_checkFeesBreakoff', 'ajax_checkFeesBreakoff')->name('ajax_checkFeesBreakoff');
});

Route::post('/studentFeesDetailAPI', [fees_collect_controller::class, 'studentFeesDetailAPI']);

Route::get('ajax_checkFeesStructure',
    [fees_breackoff_controller::class, 'ajax_checkFeesStructure'])->name('ajax_checkFeesStructure');


