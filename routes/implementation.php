<?php
Route::group(['prefix' => 'implementation','middleware' => ['session','menu','logRoute']], function() {
Route::resource('add_implementation', 'implementation\implementation_MasterController');
});
?>