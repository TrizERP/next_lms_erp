<?php

use App\Http\Controllers\api\easy_com\EmailReportApiController;
use App\Http\Controllers\api\easy_com\NotificationReportApiController;
use App\Http\Controllers\api\easy_com\RegisterParentReportApiController;
use App\Http\Controllers\api\easy_com\SendEmailParentsApiController;
use App\Http\Controllers\api\easy_com\SendNotificationParentsApiController;
use App\Http\Controllers\api\easy_com\SendSmsParentsApiController;
use App\Http\Controllers\api\easy_com\SendSmsStaffApiController;
use App\Http\Controllers\api\easy_com\SendWhatsappParentsApiController;
use App\Http\Controllers\api\easy_com\SmsApiMasterApiController;
use App\Http\Controllers\api\easy_com\SmsReportApiController;
use App\Http\Controllers\api\easy_com\SmtpApiController;
use App\Http\Controllers\api\easy_com\WhatsappApiConfigApiController;
use App\Http\Controllers\api\easy_com\WhatsappReportApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Easy Communication (easy_com) REST API - Next.js frontend
|--------------------------------------------------------------------------
|
| Mounted under the /api prefix with the `api.session` middleware, which
| validates the JWT ("Authorization: Bearer <token>") and hydrates the exact
| session keys web login sets. That is the difference that matters: the Blade
| easy_com routes live on the `web` group and read session()->get(
| 'sub_institute_id') directly, which is NULL for a stateless call - the cause
| of the "ts.sub_institute_id = and ..." SQL syntax error, the empty listings
| and the HTML-instead-of-JSON responses the Next.js module was hitting.
|
| The Blade routes in routes/result.php, routes/settings.php and routes/web.php
| are untouched, so the existing Laravel screens keep working exactly as before.
|
*/

Route::group(['prefix' => 'easy_com', 'middleware' => ['api.session']], function () {

    /* ---------------------------------------------------------------
     | Masters - full Edit / Update flow
     * -------------------------------------------------------------*/

    // SMS API Master (sms_api_details)
    Route::get('sms-api', [SmsApiMasterApiController::class, 'index']);
    Route::post('sms-api', [SmsApiMasterApiController::class, 'store']);
    Route::get('sms-api/{id}', [SmsApiMasterApiController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'sms-api/{id}', [SmsApiMasterApiController::class, 'update'])->whereNumber('id');
    Route::delete('sms-api/{id}', [SmsApiMasterApiController::class, 'destroy'])->whereNumber('id');

    // SMTP Email (smtp_details)
    Route::get('smtp', [SmtpApiController::class, 'index']);
    Route::post('smtp', [SmtpApiController::class, 'store']);
    Route::post('smtp/test', [SmtpApiController::class, 'test']);
    Route::get('smtp/{id}', [SmtpApiController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'smtp/{id}', [SmtpApiController::class, 'update'])->whereNumber('id');
    Route::delete('smtp/{id}', [SmtpApiController::class, 'destroy'])->whereNumber('id');

    // WhatsApp API credentials (whatapp_user_details)
    Route::get('whatsapp-api', [WhatsappApiConfigApiController::class, 'index']);
    Route::post('whatsapp-api', [WhatsappApiConfigApiController::class, 'store']);
    Route::get('whatsapp-api/{id}', [WhatsappApiConfigApiController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'whatsapp-api/{id}', [WhatsappApiConfigApiController::class, 'update'])->whereNumber('id');
    Route::delete('whatsapp-api/{id}', [WhatsappApiConfigApiController::class, 'destroy'])->whereNumber('id');

    /* ---------------------------------------------------------------
     | Send screens
     * -------------------------------------------------------------*/

    Route::get('send-sms-parents/recipients', [SendSmsParentsApiController::class, 'recipients']);
    Route::post('send-sms-parents', [SendSmsParentsApiController::class, 'send']);

    Route::get('send-sms-staff/groups', [SendSmsStaffApiController::class, 'groups']);
    Route::get('send-sms-staff/recipients', [SendSmsStaffApiController::class, 'recipients']);
    Route::post('send-sms-staff', [SendSmsStaffApiController::class, 'send']);

    Route::get('send-notification-parents/options', [SendNotificationParentsApiController::class, 'options']);
    Route::get('send-notification-parents/recipients', [SendNotificationParentsApiController::class, 'recipients']);
    Route::post('send-notification-parents', [SendNotificationParentsApiController::class, 'send']);

    Route::get('send-whatsapp-parents/recipients', [SendWhatsappParentsApiController::class, 'recipients']);
    Route::post('send-whatsapp-parents', [SendWhatsappParentsApiController::class, 'send']);

    Route::get('send-email-parents/recipients', [SendEmailParentsApiController::class, 'recipients']);
    Route::post('send-email-parents', [SendEmailParentsApiController::class, 'send']);

    /* ---------------------------------------------------------------
     | Reports
     * -------------------------------------------------------------*/

    Route::get('reports/sms/options', [SmsReportApiController::class, 'options']);
    Route::get('reports/sms', [SmsReportApiController::class, 'index']);

    Route::get('reports/email/options', [EmailReportApiController::class, 'options']);
    Route::get('reports/email', [EmailReportApiController::class, 'index']);

    Route::get('reports/notification/options', [NotificationReportApiController::class, 'options']);
    Route::get('reports/notification', [NotificationReportApiController::class, 'index']);

    Route::get('reports/register-parent', [RegisterParentReportApiController::class, 'index']);

    Route::get('reports/whatsapp', [WhatsappReportApiController::class, 'index']);
});
