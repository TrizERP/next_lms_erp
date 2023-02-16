<?php
Route::group(['prefix' => 'user','middleware' => ['session','menu','logRoute']], function() {
Route::resource('add_user_profile', 'user\tbluserprofilemasterController');    
Route::resource('add_user', 'user\tbluserController');
Route::resource('add_groupwise_rights', 'user\tblgroupwise_rightsController');
Route::resource('add_mobileapp_menu_rights', 'user\mobileapp_menu_rightsController');
Route::resource('add_user_past_education', 'user\tbluserPastEducationController');
Route::resource('user_report', 'user\userReportController');
Route::POST('show_user_report', 'user\userReportController@searchUser')->name("show_user_report");
Route::GET('ajax_groupwiserights', 'user\tblgroupwise_rightsController@displayGroupwiseRights')->name('ajax_groupwiserights');
Route::GET('ajax_pasteducation', 'user\tbluserPastEducationController@addUpdateUserPastEducation')->name('ajax_pasteducation');
Route::resource('add_individual_rights', 'user\tblindividual_rightsController');
Route::GET('ajax_profileWiseUsers', 'user\tblindividual_rightsController@profileWiseUsers')->name('ajax_profileWiseUsers');
Route::GET('ajax_individualrights', 'user\tblindividual_rightsController@displayIndividualRights')->name('ajax_individualrights');
});
Route::POST('/teacherListAPI', 'user\tbluserController@teacherListAPI');

?>