<?php
Route::group(['prefix' => 'fees','middleware' => ['session','menu','logRoute']], function() {
    Route::resource('fees_config_master', 'fees\tblfeesConfigController');
    Route::resource('fees_circular_master', 'fees\fees_circular\feesCircularMasterController');    
    Route::resource('fees_late_master', 'fees\tblfeesLateController');    
    Route::resource('fees_head_type_master', 'fees\tblfeesHeadTypeMasterController');    
    Route::resource('fees_receipt_book_master', 'fees\feesReceiptBookMasterController');    
    Route::resource('fees_circular', 'fees\fees_circular\feesCircularController');    
    Route::resource('fees_collection_report', 'fees\fees_report\feesReportController');    
    Route::resource('other_fees_report', 'fees\fees_report\otherfeesReportController');    
    Route::resource('otherNew_fees_report', 'fees\fees_report\otherNewfeesReportController');
    Route::resource('NACH_s1excel_export', 'fees\NACH\s1excel_exportController');
    Route::resource('NACH_s2excel_import', 'fees\NACH\s2excel_importController');
    Route::resource('NACH_s3excel_export', 'fees\NACH\s3excel_exportController');
    Route::resource('NACH_s4excel_import', 'fees\NACH\s4excel_importController');
    Route::resource('daily_voucher', 'fees\fees_report\daily_voucherController');

    Route::GET('ajax_ledgerData', 'fees\fees_report\otherNewfeesReportController@ajax_ledgerData')->name('ajax_ledgerData');

    Route::resource('otherNew_cancel_fees_report', 'fees\fees_report\otherNew_CancelFeesReportController');    
    Route::resource('fees_type_wise_report', 'fees\fees_report\feesTypewiseReportController');    
    Route::resource('tally_export_fees_report', 'fees\fees_report\tallyExportReportController');    
    Route::resource('fees_overall_headwise_report', 'fees\fees_report\feesOverallHeadwiseReportController');    
    Route::resource('fees_headwise_pending_report', 'fees\fees_report\feesOverallHeadwisePendingReportController');    
    Route::resource('fees_overall_report', 'fees\fees_report\feesOverallReportController');    
    Route::resource('fees_status_report', 'fees\fees_report\feesStatusController'); 

    // Remain fees send SMS
    Route::post('fees_remain_sms', 'fees\fees_report\feesStatusController@ajaxRemainFeesSMSsend')->name('remainFeesNotification');    
    
    Route::resource('fees_title', 'fees\fees_title\fees_title_controller');

    Route::resource('other_fees_title', 'fees\other_fees_title\other_fees_title_controller');
    Route::resource('other_fees_collect', 'fees\other_fees_collect\other_fees_collect_controller');
    Route::resource('other_fees_cancel', 'fees\other_fees_cancel\other_fees_cancel_controller');

    Route::resource('other_fee_map', 'fees\other_fee_map\other_fee_map_controller');
    Route::resource('cheque_cash', 'fees\cheque_cash\cheque_cash_controller');
    Route::resource('map_year', 'fees\map_year\map_year_controller');
    Route::resource('fees_breackoff', 'fees\fees_breackoff\fees_breackoff_controller');
    Route::resource('bank_master', 'fees\bank_master\bank_master_controller');
    Route::resource('fees_collect', 'fees\fees_collect\fees_collect_controller');
    Route::resource('college_fees_collect', 'fees\college_fees_collect\college_fees_collect_controller');
    Route::resource('online_fees', 'fees\online_fees\online_fees_settigs_controller');
    Route::resource('online_fees_split', 'fees\online_fees\online_fees_split_controller');
    // Route::get('online_fees\show_online_type', 'fees\online_fees\online_fees_collect_controller@showTypes')->name('online_show_type');
    
    Route::get('hdfcpayment', function ($id=null) {
        return view('fees/online_fees/hdfcpayment', ['name' => 'James']);    
    })->name('hdfcpayment');
    Route::get('aggre_pay', function ($id=null) {
        return view('fees/online_fees/aggre_pay', ['name' => 'James']);    
    })->name('aggre_pay');
    Route::get('axis', function ($id=null) {
        return view('fees/online_fees/axis', ['name' => 'James']);    
    })->name('axis');
    Route::get('icici', function ($id=null) {
        return view('fees/online_fees/icici', ['name' => 'James']);    
    })->name('icici');
    Route::get('razorpay', function ($id=null) {
        return view('fees/online_fees/razorpay', ['name' => 'James']);    
    })->name('razorpay');

    
    Route::POST('api/get-fees-list', 'AJAXController@getFees')->name('get-fees-list');
    Route::resource('update_fees_breackoff', 'fees\update_fees_breackoff\update_fees_breackoff_controller');
    
    Route::post('fees_collect/show_student', ['as' => 'fees_collect.show_student', 'uses' => 'fees\fees_collect\fees_collect_controller@show_student']);
    Route::post('college_fees_collect/show_student', ['as' => 'college_fees_collect.show_student', 'uses' => 'fees\college_fees_collect\college_fees_collect_controller@show_student']);

    Route::post('fees_circular/show_student', ['as' => 'fees_circular.show_student', 'uses' => 'fees\fees_circular\feesCircularController@showStudent']);

    Route::post('fees_circular/show_circular', 'fees\fees_circular\feesCircularController@showCircular')->name('show_circular');
    

    Route::resource('fees_cancel', 'fees\fees_cancel\feesCancelController'); 
    Route::post('fees/fees_cancel', 'fees\fees_cancel\feesCancelController@showFees')->name('show_cancel_fees');
    Route::post('fees/cancel_fees', 'fees\fees_cancel\feesCancelController@cancelFees')->name('cancel_fees');

    Route::resource('fees_refund', 'fees\fees_cancel\feesRefundController');
    Route::post('fees/fees_refund', 'fees\fees_cancel\feesRefundController@showFees')->name('show_fees');
    Route::post('fees/save_fees_refund', 'fees\fees_cancel\feesRefundController@saveFeesRefund')->name('save_fees_refund');


    Route::resource('imprest_fees_cancel', 'fees\imprest_fees_cancel\imprestFeesCancelController'); 
    Route::post('show_imprest_cancel_fees', 'fees\imprest_fees_cancel\imprestFeesCancelController@showImprestFees')->name('show_imprest_cancel_fees');
    Route::post('cancel_imprest_fees', 'fees\imprest_fees_cancel\imprestFeesCancelController@cancelImprestFees')->name('cancel_imprest_fees');

    Route::post('fees/fees_collection_report', 'fees\fees_report\feesReportController@showFees')->name('show_fees_collection_report');

    Route::post('fees/fees_overall_report', 'fees\fees_report\feesOverallReportController@showFeesOverall')->name('show_fees_overall_report');

    Route::post('fees/fees_status_report', 'fees\fees_report\feesStatusController@feesStatusReport')->name('show_fees_status_report');
    
    Route::get('pdfview',array('as'=>'pdfview','uses'=>'ItemController@pdfview'));

    Route::GET('fees_structure_report_index', 'fees\fees_report\feesStructureReportController@feesStructureReportIndex')->name("fees_structure_report_index");
    Route::POST('fees_structure_report', 'fees\fees_report\feesStructureReportController@feesStructureReport')->name("fees_structure_report");

    Route::GET('fees_cancel_report_index', 'fees\fees_report\feesCancelReportController@feesCancelReportIndex')->name("fees_cancel_report_index");
    Route::POST('fees_cancel_report', 'fees\fees_report\feesCancelReportController@feesCancelReport')->name("fees_cancel_report");

    Route::GET('fees_refund_report_index', 'fees\fees_report\feesRefundReportController@feesRefundReportIndex')->name("fees_refund_report_index");
    Route::POST('fees_refund_report', 'fees\fees_report\feesRefundReportController@feesRefundReport')->name("fees_refund_report");

	Route::resource('fees_monthly_report', 'fees\fees_report\feesMonthlyReportController');
	Route::POST('getfeesMonthlyReport', 'fees\fees_report\feesMonthlyReportController@getfeesMonthlyReport')->name('getfeesMonthlyReport');

      Route::GET('institute_wise_fees_paid_report_index', 'fees\fees_report\feesInstituteWiseFeesReportController@instituteWiseFeesPaidReportIndex')->name("institute_wise_fees_paid_report_index");
    Route::POST('institute_wise_fees_paid_report', 'fees\fees_report\feesInstituteWiseFeesReportController@instituteWiseFeesPaidReport')->name("institute_wise_fees_paid_report");

    Route::GET('fees_fine_discount_report_index', 'fees\fees_report\feesFineDiscountReportController@feesFineDiscountReportIndex')->name("fees_fine_discount_report_index");
    Route::POST('fees_fine_discount_report', 'fees\fees_report\feesFineDiscountReportController@feesFineDiscountReport')->name("fees_fine_discount_report");

    Route::resource('imprest_refund_report', 'fees\fees_report\imprestRefundReportController');
    
});

Route::POST('api/get-online-fees-list', 'AJAXController@getOnlineFees')->name('get-online-fees-list');
	Route::POST('fees/PaidUnpaid', 'fees\fees_collect\fees_collect_controller@PaidUnpaid');
	Route::POST('fees/PaidUnpaidTeacher', 'fees\fees_collect\fees_collect_controller@PaidUnpaidTeacher');
	Route::group(['prefix' => 'report','middleware' => ['session','menu','logRoute']], function () {
	   Route::resource('report_module', 'report\report_module\report_module_controller');
    });
    //online routes

    Route::GET('fees/online_fees_collect', 'fees\online_fees\online_fees_collect_controller@index');

    Route::POST('fees/hdfc/online_fees_collect', 'fees\online_fees\online_fees_collect_controller@hdfc')->name("hdfc_fees_collect");
    Route::POST('fees/hdfc/online_fees_hdfcRequestHandler', 'fees\online_fees\online_fees_collect_controller@hdfc_request_handler')->name("hdfc_request_handler");
    Route::POST('fees/hdfc/online_fees_hdfcResponseHandler', 'fees\online_fees\online_fees_collect_controller@hdfc_response_handler')->name("hdfc_response_handler");

    Route::POST('fees/axis/online_fees_collect', 'fees\online_fees\online_fees_collect_controller@axis')->name("axis_fees_collect");
    Route::POST('fees/axis/online_fees_axisRequestHandler', 'fees\online_fees\online_fees_collect_controller@axis_request_handler')->name("axis_request_handler");
    Route::GET('fees/axis/online_fees_axisResponseHandler', 'fees\online_fees\online_fees_collect_controller@axis_response_handler')->name("axis_response_handler");

    Route::POST('fees/aggre_pay/online_fees_collect', 'fees\online_fees\online_fees_collect_controller@aggre_pay')->name("aggre_pay_fees_collect");
    Route::POST('fees/aggre_pay/online_fees_aggre_payRequestHandler', 'fees\online_fees\online_fees_collect_controller@aggre_pay_request_handler')->name("aggre_pay_request_handler");
    Route::POST('fees/aggre_pay/online_fees_aggre_payResponseHandler', 'fees\online_fees\online_fees_collect_controller@aggre_pay_response_handler')->name("aggre_pay_response_handler");

    Route::POST('fees/icici/online_fees_collect', 'fees\online_fees\online_fees_collect_controller@icici')->name("icici_fees_collect");
    Route::POST('fees/icici/online_fees_iciciRequestHandler', 'fees\online_fees\online_fees_collect_controller@icici_request_handler')->name("icici_request_handler");
    Route::POST('fees/icici/online_fees_iciciResponseHandler', 'fees\online_fees\online_fees_collect_controller@icici_response_handler')->name("icici_response_handler");
    
    Route::POST('fees/razorpay/online_fees_collect', 'fees\online_fees\online_fees_collect_controller@razorpay')->name("razorpay_fees_collect");
    Route::POST('fees/razorpay/online_fees_razorpayRequestHandler', 'fees\online_fees\online_fees_collect_controller@razorpay_request_handler')->name("razorpay_request_handler");
    Route::POST('fees/razorpay/online_fees_razorpayResponseHandler', 'fees\online_fees\online_fees_collect_controller@razorpay_response_handler')->name("razorpay_response_handler");

    Route::GET('fees/get-student', 'AJAXController@getStudentFromMobile')->name('get-student');
    // Route::get('admission/online-admission/{id}/{title}', 'admission\admissionEnquiryController@onlineEnquiryFirst')->name('onlineEnquiryFirst');

    Route::GET('ajax_PDF_FeesReceipt', 'AJAXController@ajax_PDF_FeesReceipt')->name('ajax_PDF_FeesReceipt');
    Route::GET('ajax_PDF_Bulk_OtherFeesReceipt', 'AJAXController@ajax_PDF_Bulk_OtherFeesReceipt')->name('ajax_PDF_Bulk_OtherFeesReceipt');

    Route::POST('/studentFeesDetailAPI', 'fees\fees_collect\fees_collect_controller@studentFeesDetailAPI');

    Route::GET('ajax_checkFeesStructure', 'fees\fees_breackoff\fees_breackoff_controller@ajax_checkFeesStructure')->name('ajax_checkFeesStructure');

    Route::GET('ajax_checkFeesBreakoff', 'AJAXController@ajax_checkFeesBreakoff')->name('ajax_checkFeesBreakoff');
    

?>