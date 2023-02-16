<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Carbon\Carbon;
use Mail;
use Hash;
use Illuminate\Support\Str;
use App\Models\user\tbluserModel;
use PHPMailer\PHPMailer;

class ForgotPasswordController extends Controller
{
    /**
       * Write code on Method
       *
       * @return response()
       */

      public function showForgetPasswordForm()
      {
         return view('auth.forgetPassword');
      }

      /**
       * Write code on Method
       *
       * @return response()
       */

      public function submitForgetPasswordForm(Request $request)
      {
          $request->validate([
              'email' => 'required|email|exists:tbluser',
          ]);

          $token = Str::random(64);

          DB::table('password_resets')->insert([
              'email' => $request->email, 
              'token' => $token, 
              'created_at' => Carbon::now()
            ]);

          Mail::send('email.forgetPassword', ['token' => $token,'email' => $request->email], function($message) use($request){
              $message->to($request->email);
              $message->subject('Reset Password');
          });

          return back()->with('message', 'We have e-mailed your password reset link!');
      }

      /**
       * Write code on Method
       *
       * @return response()
       */

      public function showResetPasswordForm($token,$email) 
      { 
         return view('auth.forgetPasswordLink', ['token' => $token,'email' => $email]);
      }

      /**
       * Write code on Method
       *
       * @return response()
       */

      public function submitResetPasswordForm(Request $request)
      {
          $request->validate([
              'email' => 'required|email|exists:tbluser',
              'password' => 'required|string|min:6|confirmed',
              'password_confirmation' => 'required'
          ]);

          $updatePassword = DB::table('password_resets')
                              ->where([
                                'email' => $request->email, 
                                'token' => $request->token
                              ])
                              ->first();

          if(!$updatePassword)
          {
              return back()->withInput()->with('error', 'Invalid token!');
          }

          $user = tbluserModel::where('email', $request->email)->update(['password' => $request->password]);
          DB::table('password_resets')->where(['email'=> $request->email])->delete();

          return view('login')->with('successMsg', 'Your password has been changed!');
      }
}
