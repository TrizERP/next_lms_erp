<?php
Route::group(['prefix' => 'settings', 'middleware' => ['session', 'menu','logRoute']], function () {
	Route::resource('add_fields', 'settings\tblcustomfieldsController');
	Route::resource('smtp_setting', 'settings\smtpController');
	Route::resource('biomatrix', 'settings\biomatrixController');
	Route::get('setsession', 'settings\tblcustomfieldsController@setsession')->name('setsession');
	Route::get('setinstitute', 'settings\tblcustomfieldsController@setinstitute')->name('setinstitute');

	Route::resource('templatemaster', 'settings\templateMasterController');
	Route::get('view_all_tag', 'settings\templateMasterController@viewAllTag')->name('view_all_tag');
	Route::resource('institute_detail', 'settings\instituteDetailController');	
	Route::resource('manage_institute', 'settings\manageInstituteController');
	
});
?>