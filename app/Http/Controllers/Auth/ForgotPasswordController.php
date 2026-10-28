<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Write code on Method
     *
     * @return Application|Factory|View()
     */

    public function showForgetPasswordForm()
    {
        return view('auth.forgetPassword');
    }

    /**
     * Write code on Method
     *
     * @return RedirectResponse()
     */

    public function submitForgetPasswordForm(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Same answer whether or not the address is registered, so this form cannot be
        // used to find out who has an account.
        $generic = response()->json([
            'success' => true,
            'message' => 'If that email is registered, a password reset link has been sent.',
        ], 200);

        if (! tbluserModel::where('email', $request->email)->exists()) {
            return $generic;
        }

        $token = Str::random(64);

        // One live token per address; only a hash is stored, the plain token is only in the email.
        DB::table('password_resets')->where('email', $request->email)->delete();
        DB::table('password_resets')->insert([
            'email'      => $request->email,
            'token'      => hash('sha256', $token),
            'created_at' => Carbon::now(),
        ]);

        try {
            Mail::send('email.forgetPassword', [
                'token' => $token,
                'email' => $request->email
            ], function ($message) use ($request) {
                $message->to($request->email);
                $message->subject('Reset Password');
            });
        } catch (\Exception $e) {
            // The cause (SMTP host, credentials) is for the log, never for the caller.
            Log::warning('Password reset email failed', ['error' => $e->getMessage()]);
        }

        return $generic;
    }

    /**
     * Write code on Method
     *
     * @return Application|Factory|View()
     */

    public function showResetPasswordForm($token, $email)
    {
        return view('auth.forgetPasswordLink', ['token' => $token, 'email' => $email]);
    }

    /**
     * Write code on Method
     *
     * @return Application|Factory|View|RedirectResponse
     */

    public function submitResetPasswordForm(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $updatePassword = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        $valid = $updatePassword
            && hash_equals((string) $updatePassword->token, hash('sha256', (string) $request->token))
            && Carbon::parse($updatePassword->created_at)->gt(Carbon::now()->subMinutes(60));

        if (! $valid) {
            return back()->withInput()->with('error', 'Invalid or expired token!');
        }

        $user = tbluserModel::where('email', $request->email)->update(['password' => $request->password]);
        DB::table('password_resets')->where(['email' => $request->email])->delete();

        return view('login')->with('successMsg', 'Your password has been changed!');
    }
}
