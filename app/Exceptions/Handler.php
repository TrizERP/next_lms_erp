<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ResponseAlias;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;
use Illuminate\Support\Facades\File;
use Storage;
use function App\Helpers\is_mobile;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  Exception|Throwable  $exception
     * @throws Throwable
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return void
     */
    public function report(Exception|Throwable $exception)
    {

        // 13/08/2021 START code for Insert Exception & Error in DB table

        $u_id = session()->get("user_id");
        if ($u_id != '') {
            $userId = $u_id;
        } else {
            $userId = 0;
        }

//        if ($exception->getCode() != 0) {
//            $random_no = rand(10000, 99999);
//            $image_name = $random_no.'.jpg';
//            $path = URL::current();
//            $save_path = $_SERVER['DOCUMENT_ROOT'].'/storage/error_screenshort/'.$image_name;
//
//            $errLog = new errLog([
//                'user_id'      => $userId,
//                'code'         => $exception->getCode(),
//                'file'         => $exception->getFile(),
//                'line'         => $exception->getLine(),
//                'message'      => $exception->getMessage(),
//                'screen_short' => $path //  '/storage/error_screenshort/'.$image_name
//            ]);
//            $errLog->save();
//        }
        // 13/08/2021 END code for Insert Exception & Error in DB table

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @param  Exception|Throwable  $exception
     * @throws Throwable
     * @return ResponseAlias
     */
    public function render($request, Exception|Throwable $exception)
    {
        // 13-06-23
        // $errorLog =  storage_path('logs\laravel-'.date('Y-m-d').'.log');
        // $errorFound = File::get($errorLog);
        // if (!empty($errorFound)) {
        //     // Extract the error message from the log file
        // $errorMessage = trim($errorFound);
        // preg_match('/.*\.php:\d+/', $errorMessage, $matches);
        // $fileNameLine = isset($matches[0]) ? $matches[0] : '';

        // $res['status_code'] = 0;
        // $res['message'] = "Error Occurred: " . $fileNameLine;

        // return redirect('/dashboard')->with(['data' => $res]);
        // }
        
        // another code

        if ($this->isHttpException($exception)) {
            if ($exception->getStatusCode() == 404) {
                return response()->view('errors.'.'404', [], 404);
            }
        }
        else {
            // Redirect to dashboard with error message, filename, and line number
            $type='';
            $res['status_error']=0;
            $res['message_error']="Error Occured :".$exception->getMessage()." on line number ".$exception->getLine(); //$exception->getFile(),
            // return redirect('/dashboard')->with(['data' => $res]);
        return redirect('/dashboard?err=1&err_msg='.$res['message_error']);
            
        }

        return parent::render($request, $exception);
    }
}
