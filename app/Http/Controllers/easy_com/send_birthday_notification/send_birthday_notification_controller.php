<?php

namespace App\Http\Controllers\easy_com\send_birthday_notification;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;

class send_birthday_notification_controller extends Controller
{
    // send Birthday Notification
    public function send_birthday_notification()
    {

        // get student Data
        $students = DB::table('tblstudent')
            ->select('tblstudent.id', 'tblstudent.first_name', 'tblstudent.middle_name', 'tblstudent.last_name',
                'tblstudent.sub_institute_id', 'tblstudent.dob', 'school_setup.SchoolName', 'school_setup.Logo',
                'gcm_users.gcm_regid')
            ->join('school_setup', 'school_setup.Id', '=', 'tblstudent.sub_institute_id')
            ->join('gcm_users', function ($join) {
                $join->on('tblstudent.mobile', '=', 'gcm_users.mobile_no')
                    ->on('tblstudent.sub_institute_id', '=', 'gcm_users.sub_institute_id');
            })->where('tblstudent.status', 1)
            ->where(DB::raw('DAY(tblstudent.dob)'), DB::raw('DAY(CURDATE())'))
            ->where(DB::raw('MONTH(tblstudent.dob)'), DB::raw('MONTH(CURDATE())'))
            ->get();

        // get user Data
        $users = DB::table('tbluser')
            ->select('tbluser.id', 'tbluser.first_name', 'tbluser.middle_name', 'tbluser.last_name',
                'tbluser.sub_institute_id',
                'tbluser.birthdate', 'school_setup.SchoolName', 'school_setup.Logo', 'gcm_users.gcm_regid')
            ->join('school_setup', 'school_setup.Id', '=', 'tbluser.sub_institute_id')
            ->join('gcm_users', function ($join) {
                $join->on('tbluser.mobile', '=', 'gcm_users.mobile_no')
                    ->on('tbluser.sub_institute_id', '=', 'gcm_users.sub_institute_id');
            })->where('status', 1)
            ->where(DB::raw('DAY(birthdate)'), DB::raw('DAY(CURDATE())'))
            ->where(DB::raw('MONTH(birthdate)'), DB::raw('MONTH(CURDATE())'))
            ->get();


        // Student
        if (! empty($students)) {
            foreach ($students as $student) {
                $description = "Happy Birthday ".strtoupper($student->first_name)." ".strtoupper($student->middle_name)
                    ." ".strtoupper($student->last_name);
                // app notification content
                $app_notification_content = [
                    'NOTIFICATION_TYPE'        => 'Birthday',
                    'NOTIFICATION_DATE'        => now(),
                    'STUDENT_ID'               => $student->id,
                    'NOTIFICATION_DESCRIPTION' => $description,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => $student->sub_institute_id,
                    'SYEAR'                    => 0,
                    'SCREEN_NAME'              => 'general',
                    'CREATED_BY'               => $student->id,
                    'CREATED_IP'               => 'student',
                ];

                $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/birthday.jpg';

                if (isset($student, $description)) {
                    $type = 'Happy Birthday';
                    $message = [
                        'body'    => $description,
                        'TYPE'    => $type,
                        'USER_ID' => $student->id,
                        'title'   => $student->SchoolName,
                        'image'   => $schoolLogo,
                    ];

                    $pushStatus = send_FCM_Notification([$student->gcm_regid], $message, $student->sub_institute_id);
                    sendNotification($app_notification_content);
                }
            }
        }

        // users
        if (! empty($users)) {
            foreach ($users as $user) {
                $description = "Happy Birthday ".strtoupper($user->first_name)." ".strtoupper($user->middle_name)." ".($user->last_name);
                //app notification content
                $app_notification_content = [
                    'NOTIFICATION_TYPE'        => 'Birthday',
                    'NOTIFICATION_DATE'        => now(),
                    'STUDENT_ID'               => $user->id,
                    'NOTIFICATION_DESCRIPTION' => $description,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => $user->sub_institute_id,
                    'SYEAR'                    => 0,
                    'SCREEN_NAME'              => 'general',
                    'CREATED_BY'               => $user->id,
                    'CREATED_IP'               => 'staff',
                ];

                $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/birthday.jpg';

                if (isset($user)) {
                    $type = 'Happy Birthday';
                    $message = [
                        'body'    => $description,
                        'TYPE'    => $type,
                        'USER_ID' => $user->id,
                        'title'   => $user->SchoolName,
                        'image'   => $schoolLogo,
                    ];

                    $pushStatus = send_FCM_Notification($user, $message, $user->sub_institute_id);
                    sendNotification($app_notification_content);
                }
            }
        }
    }
}
