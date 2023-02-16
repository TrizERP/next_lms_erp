<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Models\errLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ResponseAlias;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Illuminate\Support\Facades\URL;
use Throwable;

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
     * @param Exception|Throwable $exception
     * @return void
     * @throws Throwable
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function report(Exception|Throwable $exception)
    {

        // 13/08/2021 START code for Insert Exception & Error in DB table

        // $data = Session::all();
        // dd($data);
        $u_id = session()->get("user_id");
        if ($u_id != '') {
            $userId = $u_id;
        } else {
            $userId = 0;
        }

        if ($exception->getCode() != 0) {
            $random_no = rand(10000, 99999);
            $image_name = $random_no . '.jpg';
            $path = URL::current();
            $save_path = $_SERVER['DOCUMENT_ROOT'] . '/storage/error_screenshort/' . $image_name;
            // exec('wkhtmltoimage --width 2000 --height 1000 '.$path.' '.$save_path);

            $errLog = new errLog([
                'user_id' => $userId,
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
                'screen_short' => $path //  '/storage/error_screenshort/'.$image_name
            ]);
            $errLog->save();
        }
        // 13/08/2021 END code for Insert Exception & Error in DB table

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Exception|Throwable $exception
     * @return ResponseAlias
     * @throws Throwable
     */
    public function render($request, Exception|Throwable $exception)
    {
        if ($this->isHttpException($exception)) {
            if ($exception->getStatusCode() == 404) {
                return response()->view('errors.' . '404', [], 404);
            }
        }
        //dd($exception->getMessage());
        //return response()->view('errors.500', ['error' => $exception->getMessage()], 500);
        return parent::render($request, $exception);
    }
}
