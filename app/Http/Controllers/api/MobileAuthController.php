<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\loginModel;
use App\Models\student\tblstudentModel;
use App\Support\MobileAuth\LoginSession;
use App\Support\ReviewOtp;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mobile number + mPIN sign-in for the app. The web login (api/api-login) is not touched.
 *
 * A mobile number can belong to a teacher (tbluser) AND to several children (tblstudent), so the
 * number is what gets proven (mPIN, or Face ID / fingerprint through a device token) and the person
 * then picks which profile to open:
 *
 *   status  -> does this number have an mPIN yet?              (pin | activate)
 *   otp/send, otp/verify -> prove the number by SMS code       (first time, or forgotten mPIN)
 *   pin/set -> choose the mPIN                                 (needs the verified code)
 *   pin/login | device/login -> returns a short ticket + the profiles on that number
 *   device/register | device/revoke -> biometric device token
 *   select  -> ticket + profile -> the same session api-login returns
 *
 * Nothing here reveals whether a number exists: unknown numbers behave like new ones until a code is
 * checked, and every failure to sign in says the same thing.
 */
class MobileAuthController extends Controller
{
    private const PIN_LENGTH = 4;

    private const MAX_PIN_FAILURES = 5;

    private const LOCK_MINUTES = 15;

    private const OTP_MINUTES = 5;

    private const OTP_MAX_TRIES = 5;

    private const OTP_MAX_PER_HOUR = 5;

    private const OTP_COOLDOWN_SECONDS = 30;

    private const TICKET_MINUTES = 5;

    private const SETUP_MINUTES = 10;

    private const MAX_DEVICES = 5;

    // ------------------------------------------------------------------ status

    public function status(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        if (! $mobile) {
            return $this->fail('Enter a valid 10 digit mobile number.', 422);
        }

        $has = DB::table('mobile_auth_credentials')->where('mobile', $mobile)->exists();

        return $this->ok(['state' => $has ? 'pin' : 'activate']);
    }

    // --------------------------------------------------------------------- otp

    public function sendOtp(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        if (! $mobile) {
            return $this->fail('Enter a valid 10 digit mobile number.', 422);
        }

        $recent = DB::table('mobile_auth_otps')->where('mobile', $mobile)->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= self::OTP_MAX_PER_HOUR) {
            return $this->fail('Too many codes requested. Try again in an hour.', 429);
        }
        $last = DB::table('mobile_auth_otps')->where('mobile', $mobile)->max('created_at');
        // Plain timestamps: in Carbon 3 now()->diffInSeconds($earlier) is NEGATIVE, which made this check true for
        // every number that had ever been sent a code, so a second code could never be requested.
        if ($last && (now()->timestamp - \Carbon\Carbon::parse($last)->timestamp) < self::OTP_COOLDOWN_SECONDS) {
            return $this->fail('Please wait a few seconds before asking for another code.', 429);
        }

        // Same answer whether or not the number is registered.
        $generic = $this->ok(['expires_in' => self::OTP_MINUTES * 60], 'If this number is registered, a code has been sent.');

        $schools = $this->schoolsFor($mobile);
        if (! $schools) {
            return $generic;
        }

        $code = ReviewOtp::matches($mobile) ? (string) ReviewOtp::code() : (string) random_int(100000, 999999);
        DB::table('mobile_auth_otps')->where('mobile', $mobile)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        DB::table('mobile_auth_otps')->insert([
            'mobile' => $mobile,
            'otp_hash' => $this->hashOtp($mobile, $code),
            'purpose' => 'activate',
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
            'created_at' => now(),
        ]);

        if (ReviewOtp::matches($mobile)) {
            return $generic; // fixed review code, nothing to send
        }
        if (app()->environment('local') && ! config('mobile_auth.send_in_local')) {
            // A dev machine points at real school data: never message a real parent. The code is in the log.
            Log::warning('mobile-auth OTP (local, not sent)', ['mobile' => $mobile, 'otp' => $code]);

            return $generic;
        }

        // WhatsApp first; SMS when WhatsApp is not set up for the school, is refused, or SMS was asked for.
        $channel = $request->input('channel') === 'sms' ? 'sms' : 'whatsapp';
        $sent = $channel === 'whatsapp' && $this->sendWhatsapp($mobile, $code, $schools);
        if (! $sent) {
            $sent = $this->sendSms($mobile, $code, $schools);
        }
        if (! $sent) {
            Log::warning('mobile-auth OTP could not be delivered', ['mobile' => $this->mask($mobile), 'schools' => $schools]);
        }

        return $generic;
    }

    /** Sends the code on WhatsApp through the first of the number's schools that has Cloud API credentials. */
    private function sendWhatsapp(string $mobile, string $code, array $schools): bool
    {
        $graph = (string) config('mobile_auth.whatsapp_graph_version', 'v25.0');
        $template = trim((string) config('mobile_auth.whatsapp_template'));

        foreach ($schools as $school) {
            $cfg = DB::table('whatapp_user_details')->where('sub_institute_id', $school)
                ->whereNotNull('cloud_api_access_token')->where('cloud_api_access_token', '!=', '')
                ->whereNotNull('cloud_api_phone_number_id')->where('cloud_api_phone_number_id', '!=', '')
                ->orderByDesc('id')->first();
            if (! $cfg) {
                continue;
            }

            foreach ((array) config('mobile_auth.whatsapp_languages', ['en_US']) as $lang) {
                if ($template !== '') {
                    // An approved Authentication template: the code is the body variable and the copy-code button.
                    $components = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]]];
                    if (config('mobile_auth.whatsapp_template_has_copy_button')) {
                        $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]];
                    }
                    $name = $template;
                } else {
                    // The ERP's generic one-variable template carrying the whole sentence.
                    $components = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Your Teach Connect login code is '.$code.'. It is valid for '.self::OTP_MINUTES.' minutes. Do not share it with anyone.']]]];
                    $name = (string) config('mobile_auth.whatsapp_fallback_template', 'parents_');
                }

                try {
                    // Not through WhatsappController::sendWhatsappCloudApi: that logs the whole payload, and here
                    // the payload is a login code.
                    $res = \Illuminate\Support\Facades\Http::withToken((string) $cfg->cloud_api_access_token)->timeout(15)
                        ->post("https://graph.facebook.com/{$graph}/{$cfg->cloud_api_phone_number_id}/messages", [
                            'messaging_product' => 'whatsapp',
                            'to' => '91'.$mobile,
                            'type' => 'template',
                            'template' => ['name' => $name, 'language' => ['code' => $lang], 'components' => $components],
                        ]);
                } catch (\Throwable $e) {
                    Log::warning('mobile-auth WhatsApp request failed', ['school' => $school, 'error' => $e->getMessage()]);
                    break;
                }

                $body = $res->json() ?: [];
                if ($res->successful() && ! empty($body['messages'][0]['id'])) {
                    Log::info('mobile-auth OTP sent on WhatsApp', ['mobile' => $this->mask($mobile), 'school' => $school, 'wamid' => $body['messages'][0]['id']]);

                    return true;
                }

                // 132001 = the template does not exist in this language: try the next language, else give up.
                if (($body['error']['code'] ?? null) !== 132001) {
                    Log::warning('mobile-auth WhatsApp refused', ['mobile' => $this->mask($mobile), 'school' => $school, 'code' => $body['error']['code'] ?? $res->status(), 'message' => $body['error']['message'] ?? '']);
                    break;
                }
            }
        }

        return false;
    }

    /** Sends the code by SMS through the school's gateway (the same text the old app login used). */
    private function sendSms(string $mobile, string $code, array $schools): bool
    {
        foreach ($schools as $school) {
            $text = $school === 47
                ? 'Dear Parent, Your OTP is '.$code.'. MULJIM'
                : 'OTP for login is '.$code.' and is valid for 5 minutes';
            $res = app(apiController::class)->sendSMS($mobile, $text, $school);
            if (($res['error'] ?? 1) == 0) {
                return true;
            }
        }

        return false;
    }

    private function mask(string $mobile): string
    {
        return substr($mobile, 0, 2).'XXXXXX'.substr($mobile, -2);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        $code = preg_replace('/\D/', '', (string) $request->input('otp'));
        if (! $mobile || strlen($code) !== 6) {
            return $this->fail('Enter the 6 digit code.', 422);
        }

        $row = DB::table('mobile_auth_otps')->where('mobile', $mobile)->whereNull('consumed_at')
            ->where('expires_at', '>', now())->orderByDesc('id')->first();
        if (! $row || $row->attempts >= self::OTP_MAX_TRIES) {
            return $this->fail('That code is wrong or has expired. Ask for a new one.', 401);
        }
        DB::table('mobile_auth_otps')->where('id', $row->id)->increment('attempts');
        if (! hash_equals($row->otp_hash, $this->hashOtp($mobile, $code))) {
            return $this->fail('That code is wrong or has expired. Ask for a new one.', 401);
        }

        DB::table('mobile_auth_otps')->where('id', $row->id)->update(['consumed_at' => now()]);
        $token = Str::random(60);
        Cache::put($this->key('setup', $token), ['mobile' => $mobile], now()->addMinutes(self::SETUP_MINUTES));

        return $this->ok(['setup_token' => $token, 'pin_length' => self::PIN_LENGTH], 'Number verified.');
    }

    // --------------------------------------------------------------------- pin

    public function setPin(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        $pin = (string) $request->input('pin');
        $setup = $request->input('setup_token');
        $stored = is_string($setup) ? Cache::get($this->key('setup', $setup)) : null;
        if (! $mobile || ! $stored || $stored['mobile'] !== $mobile) {
            return $this->fail('Your verification expired. Start again.', 401);
        }
        if ($weak = $this->weakPin($pin, $mobile)) {
            return $this->fail($weak, 422);
        }

        Cache::forget($this->key('setup', $setup));
        DB::transaction(function () use ($mobile, $pin) {
            DB::table('mobile_auth_credentials')->updateOrInsert(['mobile' => $mobile], [
                'pin_hash' => Hash::make($pin),
                'failed_attempts' => 0,
                'locked_until' => null,
                'pin_set_at' => now(),
                'last_login_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            // A new mPIN signs every other phone out of biometric login.
            DB::table('mobile_auth_devices')->where('mobile', $mobile)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });

        return $this->signedIn($mobile, 'mPIN created.');
    }

    public function pinLogin(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        $pin = (string) $request->input('pin');
        $generic = 'Incorrect mobile number or mPIN.';
        if (! $mobile || ! preg_match('/^\d{'.self::PIN_LENGTH.'}$/', $pin)) {
            return $this->fail($generic, 401);
        }

        if ($failure = $this->checkPin($mobile, $pin)) {
            return $failure;
        }

        return $this->signedIn($mobile);
    }

    /**
     * Checks an mPIN with the lockout rules: null when it is right, otherwise the refusal to send back. Used by the
     * sign-in and by switching to a staff profile.
     */
    private function checkPin(string $mobile, string $pin): ?JsonResponse
    {
        $generic = 'Incorrect mobile number or mPIN.';
        $cred = DB::table('mobile_auth_credentials')->where('mobile', $mobile)->first();
        if (! $cred || ! preg_match('/^\d{'.self::PIN_LENGTH.'}$/', $pin)) {
            return $this->fail($generic, 401);
        }
        if ($cred->locked_until && now()->lt($cred->locked_until)) {
            $mins = max(1, (int) ceil((\Carbon\Carbon::parse($cred->locked_until)->timestamp - now()->timestamp) / 60));

            return $this->fail("Too many wrong attempts. Try again in {$mins} minute".($mins === 1 ? '' : 's').', or reset your mPIN with a code.', 423);
        }

        if (! Hash::check($pin, $cred->pin_hash)) {
            $fails = $cred->failed_attempts + 1;
            $lock = $fails >= self::MAX_PIN_FAILURES;
            DB::table('mobile_auth_credentials')->where('mobile', $mobile)->update([
                'failed_attempts' => $lock ? 0 : $fails,
                'locked_until' => $lock ? now()->addMinutes(self::LOCK_MINUTES) : null,
                'updated_at' => now(),
            ]);
            $left = self::MAX_PIN_FAILURES - $fails;

            return $this->fail($lock
                ? 'Too many wrong attempts. This number is locked for '.self::LOCK_MINUTES.' minutes. You can reset your mPIN with a code.'
                : $generic.' '.$left.' attempt'.($left === 1 ? '' : 's').' left.', $lock ? 423 : 401);
        }

        DB::table('mobile_auth_credentials')->where('mobile', $mobile)->update([
            'failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'updated_at' => now(),
        ]);

        return null;
    }

    // ------------------------------------------------------------------ device

    public function registerDevice(Request $request): JsonResponse
    {
        $mobile = $this->ticketMobile($request);
        if (! $mobile) {
            return $this->fail('Your sign-in expired. Sign in again.', 401);
        }

        $token = Str::random(64);
        DB::transaction(function () use ($mobile, $token, $request) {
            $active = DB::table('mobile_auth_devices')->where('mobile', $mobile)->whereNull('revoked_at')->orderBy('id')->pluck('id')->all();
            $excess = count($active) - (self::MAX_DEVICES - 1);
            if ($excess > 0) {
                DB::table('mobile_auth_devices')->whereIn('id', array_slice($active, 0, $excess))->update(['revoked_at' => now()]);
            }
            DB::table('mobile_auth_devices')->insert([
                'mobile' => $mobile,
                'token_hash' => hash('sha256', $token),
                'label' => Str::limit((string) $request->input('label', 'Phone'), 80, ''),
                'last_used_at' => now(),
                'created_at' => now(),
            ]);
        });

        return $this->ok(['device_token' => $token], 'Biometric sign-in is on for this phone.');
    }

    public function deviceLogin(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        $token = $request->input('device_token');
        $generic = 'Biometric sign-in is no longer valid on this phone. Use your mPIN.';
        if (! $mobile || ! is_string($token) || strlen($token) < 40) {
            return $this->fail($generic, 401);
        }

        $device = DB::table('mobile_auth_devices')->where('token_hash', hash('sha256', $token))
            ->where('mobile', $mobile)->whereNull('revoked_at')->first();
        if (! $device) {
            return $this->fail($generic, 401);
        }
        DB::table('mobile_auth_devices')->where('id', $device->id)->update(['last_used_at' => now()]);

        return $this->signedIn($mobile);
    }

    public function revokeDevice(Request $request): JsonResponse
    {
        $mobile = $this->mobile($request);
        $token = $request->input('device_token');
        if ($mobile && is_string($token) && $token !== '') {
            DB::table('mobile_auth_devices')->where('token_hash', hash('sha256', $token))
                ->where('mobile', $mobile)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }

        return $this->ok([], 'Biometric sign-in is off for this phone.');
    }

    // ------------------------------------------------------------------ select

    /** Ticket + chosen profile -> the same response api-login returns. */
    public function select(Request $request, JwtToken $jwt): JsonResponse
    {
        $mobile = $this->ticketMobile($request);
        if (! $mobile) {
            return $this->fail('Your sign-in expired. Sign in again.', 401);
        }

        $key = (string) $request->input('profile');
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');
        if (! in_array($type, ['staff', 'student'], true) || ! ctype_digit($id)) {
            return $this->fail('Choose a profile.', 422);
        }
        // Re-check against the number on every call: the ticket proves the number, not the profile.
        $allowed = collect($this->profilesFor($mobile))->firstWhere('key', $key);
        if (! $allowed) {
            return $this->fail('That profile is not on this number.', 403);
        }

        $isStudent = $type === 'student';
        $user = $isStudent ? $this->studentRow((int) $id) : $this->staffRow((int) $id);
        if (! $user) {
            return $this->fail('That profile is not active.', 403);
        }

        return LoginSession::build($user, $isStudent, ! $isStudent, $jwt);
    }

    // -------------------------------------------------------- switch profile

    /**
     * The profiles on a number, for an already signed-in profile (api.session gives the verified caller). The caller
     * must itself be one of the number's profiles, so a token cannot be used to list a number it is not on.
     * `needs_auth` marks staff profiles when the caller is a student: stepping UP to a staff account asks for the
     * mPIN (or biometrics) again, stepping sideways between children or down from staff does not.
     */
    public function profiles(Request $request): JsonResponse
    {
        $context = $this->callerOnNumber($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }
        [, $list, $currentKey, $callerIsStudent] = $context;

        return $this->ok(['profiles' => array_map(fn ($p) => $p + [
            'current' => $p['key'] === $currentKey,
            'needs_auth' => $callerIsStudent && $p['type'] === 'staff',
        ], $list)]);
    }

    /** Opens another profile on the same number: the same session api/mobile-auth/select returns. */
    public function switchProfile(Request $request, JwtToken $jwt): JsonResponse
    {
        $context = $this->callerOnNumber($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }
        [$mobile, $list, $currentKey, $callerIsStudent] = $context;

        $key = (string) $request->input('profile');
        $target = collect($list)->firstWhere('key', $key);
        if (! $target) {
            return $this->fail('That profile is not on this number.', 403);
        }
        if ($key === $currentKey) {
            return $this->fail('You are already using this profile.', 422);
        }

        if ($callerIsStudent && $target['type'] === 'staff') {
            // Moving from a child's profile to a staff account must be proven again.
            $deviceToken = $request->input('device_token');
            $proved = false;
            if (is_string($deviceToken) && strlen($deviceToken) >= 40) {
                $device = DB::table('mobile_auth_devices')->where('token_hash', hash('sha256', $deviceToken))
                    ->where('mobile', $mobile)->whereNull('revoked_at')->first();
                if ($device) {
                    DB::table('mobile_auth_devices')->where('id', $device->id)->update(['last_used_at' => now()]);
                    $proved = true;
                }
            }
            if (! $proved) {
                $pin = $request->input('pin');
                if (! is_string($pin) || $pin === '') {
                    return response()->json(['status' => 0, 'message' => 'Enter your mPIN to open a staff profile.', 'data' => ['needs_auth' => true]], 403);
                }
                if ($failure = $this->checkPin($mobile, $pin)) {
                    return $failure;
                }
            }
        }

        $isStudent = $target['type'] === 'student';
        $user = $isStudent ? $this->studentRow($target['id']) : $this->staffRow($target['id']);
        if (! $user) {
            return $this->fail('That profile is not active.', 403);
        }

        return LoginSession::build($user, $isStudent, ! $isStudent, $jwt);
    }

    /**
     * The verified caller and the number's profiles, or a refusal. [mobile, profiles, callerKey, callerIsStudent].
     *
     * @return array{0:string,1:list<array<string,mixed>>,2:string,3:bool}|JsonResponse
     */
    private function callerOnNumber(Request $request): array|JsonResponse
    {
        $mobile = $this->mobile($request);
        if (! $mobile) {
            return $this->fail('Enter a valid 10 digit mobile number.', 422);
        }
        $session = $request->session();
        $userId = (int) $session->get('user_id');
        if ($userId <= 0) {
            return $this->fail('Sign in again.', 401);
        }
        $isStudent = (bool) $session->get('is_student');
        $currentKey = ($isStudent ? 'student:' : 'staff:').$userId;

        $list = $this->profilesFor($mobile);
        if (! collect($list)->contains('key', $currentKey)) {
            return $this->fail('This profile is not on that number.', 403);
        }

        return [$mobile, $list, $currentKey, $isStudent];
    }

    // ----------------------------------------------------------------- helpers

    private function signedIn(string $mobile, string $message = 'Success'): JsonResponse
    {
        $profiles = $this->profilesFor($mobile);
        if (! $profiles) {
            return $this->fail('No active profile is linked to this number. Contact your school.', 403);
        }
        $ticket = Str::random(60);
        Cache::put($this->key('ticket', $ticket), ['mobile' => $mobile], now()->addMinutes(self::TICKET_MINUTES));

        return $this->ok(['ticket' => $ticket, 'expires_in' => self::TICKET_MINUTES * 60, 'profiles' => $profiles], $message);
    }

    private function ticketMobile(Request $request): ?string
    {
        $ticket = $request->input('ticket');
        $stored = is_string($ticket) ? Cache::get($this->key('ticket', $ticket)) : null;

        return $stored['mobile'] ?? null;
    }

    /** @return list<array<string,mixed>> */
    private function profilesFor(string $mobile): array
    {
        $out = [];

        $staff = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->leftJoin('school_setup as ss', 'ss.Id', '=', 'u.sub_institute_id')
            ->where('u.mobile', $mobile)->where('u.status', 1)
            ->selectRaw('u.id, u.sub_institute_id, TRIM(CONCAT_WS(" ", u.first_name, u.last_name)) as name, p.name as role, ss.SchoolName as school')
            ->orderBy('u.id')->get();
        foreach ($staff as $r) {
            $out[] = [
                'key' => 'staff:'.$r->id, 'type' => 'staff', 'id' => (int) $r->id, 'name' => trim((string) $r->name),
                'subtitle' => trim((string) $r->role), 'school' => (string) $r->school, 'sub_institute_id' => (int) $r->sub_institute_id,
            ];
        }

        $students = DB::table('tblstudent as s')
            ->join('school_setup as ss', 'ss.Id', '=', 's.sub_institute_id')
            ->join('tblstudent_enrollment as e', function ($join) {
                $join->on('e.student_id', '=', 's.id')->whereNull('e.end_date');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->whereColumn('e.syear', 'ss.syear')
            ->where('s.status', 1)->whereNotIn('s.sub_institute_id', [254])
            ->where(function ($q) use ($mobile) {
                $q->where('s.mobile', $mobile)->orWhere('s.mother_mobile', $mobile)->orWhere('s.student_mobile', $mobile);
            })
            ->selectRaw('s.id, s.sub_institute_id, TRIM(CONCAT_WS(" ", s.first_name, s.last_name)) as name, s.enrollment_no, ss.SchoolName as school, st.name as standard, d.name as division')
            ->orderBy('s.id')->get();
        $seen = [];
        foreach ($students as $r) {
            if (isset($seen[$r->id])) {
                continue;
            }
            $seen[$r->id] = true;
            $class = trim(($r->standard ? 'Std '.$r->standard : '').($r->division ? ' - '.$r->division : ''));
            $out[] = [
                'key' => 'student:'.$r->id, 'type' => 'student', 'id' => (int) $r->id, 'name' => trim((string) $r->name),
                'subtitle' => trim($class.($r->enrollment_no ? ' · GR '.$r->enrollment_no : ''), ' ·'),
                'school' => (string) $r->school, 'sub_institute_id' => (int) $r->sub_institute_id,
            ];
        }

        return $out;
    }

    /** @return list<int> the schools this number belongs to, ones with an SMS gateway first */
    private function schoolsFor(string $mobile): array
    {
        $ids = array_values(array_unique(array_map(fn ($p) => $p['sub_institute_id'], $this->profilesFor($mobile))));
        if (! $ids) {
            return [];
        }
        $withGateway = DB::table('sms_api_details')->whereIn('sub_institute_id', $ids)->where('is_active', 1)->pluck('sub_institute_id')->map(fn ($v) => (int) $v)->all();

        return array_values(array_unique(array_merge(array_intersect($ids, $withGateway), $ids)));
    }

    /** The columns api-login selects for a staff row. */
    private function staffRow(int $id): ?array
    {
        $row = loginModel::select(DB::raw(
            'id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(last_name,"-")) as user_name,password,name_suffix,first_name,middle_name,last_name,email,mobile,gender,
        birthdate,address,city,state,pincode,user_profile_id,join_year,image,plain_password,
        sub_institute_id,client_id,is_admin,status,created_on as last_login,expire_date'
        ))->where(['id' => $id, 'status' => '1'])->first();

        return $row ? $row->toArray() : null;
    }

    /** The columns api-login selects for a student row. */
    private function studentRow(int $id): ?array
    {
        $row = tblstudentModel::select(DB::raw(
            'id,CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(last_name,"-")) as user_name,password,"" as name_suffix,first_name,middle_name,last_name,email,
            mobile,gender,dob as birthdate,address,city,state,pincode,user_profile_id,
            admission_year as join_year,image,"student" as plain_password,
            sub_institute_id,"" as client_id,"" as is_admin,status,created_on as last_login,expire_date'
        ))->where(['id' => $id, 'status' => '1'])->first();

        return $row ? $row->toArray() : null;
    }

    private function weakPin(string $pin, string $mobile): ?string
    {
        if (! preg_match('/^\d{'.self::PIN_LENGTH.'}$/', $pin)) {
            return 'Your mPIN must be '.self::PIN_LENGTH.' digits.';
        }
        if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['0123', '1234', '2345', '3456', '4567', '5678', '6789', '9876', '8765', '7654', '6543', '5432', '4321', '3210'], true)) {
            return 'That mPIN is too easy to guess. Choose another.';
        }
        if ($pin === substr($mobile, -self::PIN_LENGTH) || $pin === substr($mobile, 0, self::PIN_LENGTH)) {
            return 'Do not use digits from your mobile number.';
        }

        return null;
    }

    private function mobile(Request $request): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $request->input('mobile'));
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return preg_match('/^\d{10}$/', $digits) ? $digits : null;
    }

    private function key(string $kind, string $token): string
    {
        return 'mobile_auth:'.$kind.':'.hash('sha256', $token);
    }

    private function hashOtp(string $mobile, string $code): string
    {
        return hash_hmac('sha256', $mobile.'|'.$code, (string) config('app.key'));
    }

    private function ok(array $data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['status' => 1, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $http): JsonResponse
    {
        return response()->json(['status' => 0, 'message' => $message, 'data' => []], $http);
    }
}
