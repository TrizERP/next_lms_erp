<?php
Route::group(['prefix' => 'consent', 'middleware' => ['session', 'menu','logRoute']], function() {
    Route::resource('add_consent_master', 'consent\consent_masterController');
    Route::resource('delete_consent_master', 'consent\delete_consent_masterController');
    Route::resource('report_consent_master', 'consent\report_consent_masterController');
});
Route::POST('/consentListAPI', 'consent\consent_masterController@consentListAPI');
?>