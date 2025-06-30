<?php

use App\Http\Controllers\user\mobileapp_menu_rightsController;
use App\Http\Controllers\user\tblgroupwise_rightsController;
use App\Http\Controllers\user\tblindividual_rightsController;
use App\Http\Controllers\user\tbluserController;
use App\Http\Controllers\user\tbluserPastEducationController;
use App\Http\Controllers\user\tbluserprofilemasterController;
use App\Http\Controllers\user\tbluserContactDetails;
use App\Http\Controllers\user\userReportController;
use App\Http\Controllers\user\tbluserProfileWiseMenuController;
use App\Http\Controllers\user\tblmobileAppMenuRightsController;
use Illuminate\Support\Facades\Route;

// all persmissions
Route::group(['prefix' => 'user', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('add_groupwise_rights', tblgroupwise_rightsController::class);
    Route::resource('add_mobileapp_menu_rights', mobileapp_menu_rightsController::class);
    Route::resource('add_user_past_education', tbluserPastEducationController::class);
    Route::resource('user_profile_wise_menu_rights', tbluserProfileWiseMenuController::class);

    Route::resource('user_contact_details', tbluserContactDetails::class);
    
    Route::get('mobile_app_menu_rights', [tblmobileAppMenuRightsController::class, 'create'])->name("mobile_app_menu_rights");
    Route::post('mobile_app_menu_rights/store', [tblmobileAppMenuRightsController::class, 'store'])->name("mobile_app_menu_rights.store");
    Route::post('mobile_app_menu_rights/update', [tblmobileAppMenuRightsController::class, 'store'])->name("mobile_app_menu_rights.update");
    Route::get('ajax_groupwiserights',
        [tblgroupwise_rightsController::class, 'displayGroupwiseRights'])->name('ajax_groupwiserights');
    Route::get('ajax_pasteducation',
        [tbluserPastEducationController::class, 'addUpdateUserPastEducation'])->name('ajax_pasteducation');
    Route::resource('add_individual_rights', tblindividual_rightsController::class);
    Route::get('ajax_profileWiseUsers',
        [tblindividual_rightsController::Class, 'profileWiseUsers'])->name('ajax_profileWiseUsers');
    Route::get('ajax_individualrights',
        [tblindividual_rightsController::class, 'displayIndividualRights'])->name('ajax_individualrights');
    Route::get('ajax_user_profile_wise_rights',
        [tbluserProfileWiseMenuController::class, 'displayUserProfileWiseRights'])->name('ajax_user_profile_wise_rights');
    Route::get('ajax_mobile_app_menu_rights',
        [tblmobileAppMenuRightsController::class, 'displayMobileAppMenuRights'])->name('ajax_mobile_app_menu_rights');
    Route::post('add_experience/store',
        [tbluserController::class, 'add_experience'])->name('add_experience.store');
    Route::post('/experience-detail/{id}', [tbluserController::class, 'experience_destroy'])->name('experience_detail.destroy');


});
// chek permission
Route::group(['prefix' => 'user', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('add_user_profile', tbluserprofilemasterController::class);
    Route::post('ajax_userProfile_Data_Create',[tblgroupwise_rightsController::class, 'ajax_userProfile_Data_Create'])->name('ajax_userProfile_Data_Create');
    Route::resource('add_user', tbluserController::class);
    Route::post('show_user_report', [userReportController::class, 'searchUser'])->name("show_user_report");
    Route::resource('user_report', userReportController::Class);
    Route::post('user_document/{id}',[tbluserController::class, 'addUserDocument'])->name('user_document');
});

Route::post('/teacherListAPI', [tbluserController::class, 'teacherListAPI']);


