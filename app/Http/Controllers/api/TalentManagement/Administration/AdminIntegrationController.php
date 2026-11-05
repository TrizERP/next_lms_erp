<?php

namespace App\Http\Controllers\api\TalentManagement\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Talent Management -> Administration -> Integrations: real connections.
 *
 *  - OAuth providers (Google, Microsoft, LinkedIn, DocuSign): `connect` returns the provider's own consent
 *    page URL. The provider redirects back to `callback`, which exchanges the code for tokens, stores them
 *    encrypted and marks the integration Connected. Nothing is marked connected until the provider says so.
 *  - SMTP: `connect` takes the server settings and only connects when a real SMTP login succeeds.
 *  - ERP Staff Directory is built in and always connected.
 *  - Integrations with no connector yet (provider null) cannot be connected.
 */
class AdminIntegrationController extends Controller
{
    /** POST talent/admin/integrations/{id}/connect */
    public function connect(Request $request, int $id)
    {
        $row = $this->owned($id);

        if ($row->provider === 'builtin') {
            return $this->fail('This integration is built in and is always connected.', 422);
        }
        if ($row->provider === 'smtp') {
            return $this->connectSmtp($request, $row);
        }
        $cfg = self::oauth((string) $row->provider, $this->tenant());
        if (! $cfg) {
            return $this->fail("{$row->name} cannot be connected yet - no connector has been built for it.", 422);
        }
        if (! $cfg['client_id'] || ! $cfg['client_secret']) {
            return $this->fail(
                "{$cfg['label']} sign-in is not enabled yet.",
                422,
                ['setup_required' => true, 'can_setup' => true, 'scope' => $this->isSuperAdmin() ? 'platform' : 'school', 'provider' => $row->provider, 'provider_label' => $cfg['label'],
                    'redirect_uri' => $this->redirectUri(), 'console_url' => $cfg['console_url']]
            );
        }

        $state = Crypt::encryptString(json_encode([
            'i' => $row->id, 't' => $this->tenant(), 'u' => $this->actorId(), 'n' => Str::random(16),
            'r' => $this->safeReturnUrl((string) $request->input('return_url')), 'e' => now()->addMinutes(15)->timestamp,
        ]));

        $url = $cfg['authorize_url'] . '?' . http_build_query(array_merge([
            'response_type' => 'code', 'client_id' => $cfg['client_id'], 'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $cfg['scopes']), 'state' => $state,
        ], $cfg['extra_authorize']));

        return $this->ok(['url' => $url]);
    }

    /** POST talent/admin/integrations/{id}/test - proves a connected integration still works. */
    public function test(int $id)
    {
        $row = $this->owned($id);
        if ($row->provider !== 'google' || $row->status !== 'Connected') {
            return $this->fail('Only a connected Google Calendar can be tested.', 422);
        }
        $result = \App\Services\TalentManagement\GoogleCalendarSync::test($this->tenant());

        return $result['ok'] ? $this->ok(['ok' => true], $result['message']) : $this->fail($result['message'], 422);
    }

    /** POST talent/admin/integrations/{id}/disconnect */
    public function disconnect(int $id)
    {
        $row = $this->owned($id);
        if ($row->provider === 'builtin') {
            return $this->fail('This integration is built in and cannot be disconnected.', 422);
        }

        DB::table('talent_admin_integrations')->where('id', $row->id)->update([
            'status' => 'Disconnected', 'connected_account' => null, 'credentials' => null, 'token_expires_at' => null,
            'last_sync_note' => null, 'updated_by' => $this->actorId(), 'updated_at' => now(),
        ]);
        $this->audit('disconnected', $row->name, "Disconnected the {$row->name} integration.");

        return $this->ok(['id' => $id], 'Disconnected');
    }

    /**
     * GET talent/admin/integrations/callback - the provider sends the browser here, so this route has no
     * bearer token. The signed `state` created by `connect` carries the tenant, user and integration.
     */
    public function callback(Request $request)
    {
        try {
            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return response('This connection link is invalid or has expired. Close this tab and try Connect again.', 400);
        }
        if (($state['e'] ?? 0) < now()->timestamp) {
            return response('This connection link has expired. Close this tab and try Connect again.', 400);
        }

        $return = $state['r'];
        $row = DB::table('talent_admin_integrations')->where('id', $state['i'])->where('sub_institute_id', $state['t'])->first();
        $cfg = $row ? self::oauth((string) $row->provider, (int) $state['t']) : null;
        if (! $row || ! $cfg) {
            return redirect()->away($this->withResult($return, 'error', 'Integration not found'));
        }
        if ($request->query('error')) {
            return redirect()->away($this->withResult($return, 'error', (string) ($request->query('error_description') ?: $request->query('error'))));
        }

        try {
            $tokenRequest = Http::asForm()->acceptJson();
            if (! empty($cfg['basic_auth_token'])) {
                $tokenRequest = $tokenRequest->withBasicAuth($cfg['client_id'], $cfg['client_secret']);
                $body = ['grant_type' => 'authorization_code', 'code' => $request->query('code'), 'redirect_uri' => $this->redirectUri()];
            } else {
                $body = ['grant_type' => 'authorization_code', 'code' => $request->query('code'), 'redirect_uri' => $this->redirectUri(),
                    'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret']];
            }
            $token = $tokenRequest->post($cfg['token_url'], $body);
            if (! $token->successful() || ! $token->json('access_token')) {
                return redirect()->away($this->withResult($return, 'error', $token->json('error_description') ?: 'The provider did not accept the sign-in.'));
            }

            $account = null;
            $info = Http::withToken($token->json('access_token'))->acceptJson()->get($cfg['userinfo_url']);
            if ($info->successful()) {
                $account = $info->json($cfg['account_field']) ?: $info->json('userPrincipalName') ?: $info->json('name');
            }

            DB::table('talent_admin_integrations')->where('id', $row->id)->update([
                'status' => 'Connected', 'connected_account' => $account,
                'credentials' => Crypt::encryptString(json_encode([
                    'access_token' => $token->json('access_token'), 'refresh_token' => $token->json('refresh_token'),
                    'scope' => $token->json('scope'), 'token_type' => $token->json('token_type'),
                ])),
                'token_expires_at' => $token->json('expires_in') ? now()->addSeconds((int) $token->json('expires_in')) : null,
                'last_sync_at' => now(), 'last_sync_note' => $account ? "Connected as {$account}" : 'Connected', 'updated_by' => $state['u'], 'updated_at' => now(),
            ]);
            $this->audit('connected', $row->name, "Connected the {$row->name} integration" . ($account ? " as {$account}." : '.'), $state['t'], $state['u']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->away($this->withResult($return, 'error', 'Could not reach the provider. Try again.'));
        }

        return redirect()->away($this->withResult($return, 'connected', $row->name));
    }

    private function connectSmtp(Request $request, object $row)
    {
        $v = $request->validate([
            'host' => 'required|string|max:191', 'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:191', 'password' => 'required|string|max:500',
            'encryption' => 'required|in:tls,ssl,none',
        ]);

        try {
            $transport = new EsmtpTransport($v['host'], (int) $v['port'], $v['encryption'] === 'ssl');
            $transport->setUsername($v['username']);
            $transport->setPassword($v['password']);
            $transport->start(); // opens the connection and logs in; throws if the server refuses
            $transport->stop();
        } catch (\Throwable $e) {
            return $this->fail('Could not sign in to that mail server: ' . Str::limit($e->getMessage(), 160), 422);
        }

        DB::table('talent_admin_integrations')->where('id', $row->id)->update([
            'status' => 'Connected', 'connected_account' => $v['username'], 'credentials' => Crypt::encryptString(json_encode($v)),
            'last_sync_at' => now(), 'last_sync_note' => "Signed in to {$v['host']}", 'updated_by' => $this->actorId(), 'updated_at' => now(),
        ]);
        $this->audit('connected', $row->name, "Connected the {$row->name} integration as {$v['username']}.");

        return $this->ok(['id' => $row->id], 'Connected');
    }

    /**
     * OAuth app settings for a provider, most specific first: the school's own saved keys, then the
     * platform-wide keys a Super Admin saved (school 0), then .env.
     */
    public static function oauth(string $provider, int $tenant = 0): ?array
    {
        $cfg = config("talent_integrations.oauth.{$provider}");
        if (! $cfg) {
            return null;
        }
        $saved = DB::table('talent_admin_oauth_apps')->where('provider', $provider)->whereIn('sub_institute_id', [$tenant, 0])
            ->orderByDesc('sub_institute_id')->first();
        if ($saved) {
            try {
                $cfg['client_id'] = $saved->client_id;
                $cfg['client_secret'] = Crypt::decryptString($saved->client_secret);
            } catch (\Throwable) {
                // A secret that no longer decrypts (APP_KEY changed) counts as not set up.
                $cfg['client_id'] = $cfg['client_secret'] = null;
            }
        }

        return $cfg;
    }

    public static function isSuperAdmin(): bool
    {
        return strtolower((string) session()->get('user_profile_name')) === 'super admin';
    }

    /** The school the keys are saved for: platform wide (0) for a Super Admin, otherwise the caller's own school. */
    private function keyScope(): int
    {
        return self::isSuperAdmin() ? 0 : $this->tenant();
    }

    /** GET talent/admin/integrations/oauth-apps. Never returns a secret. */
    public function oauthApps()
    {
        $saved = DB::table('talent_admin_oauth_apps')->where('sub_institute_id', $this->keyScope())->pluck('client_id', 'provider');
        $rows = [];
        foreach (config('talent_integrations.oauth') as $key => $cfg) {
            $app = self::oauth($key, $this->tenant());
            $rows[] = ['provider' => $key, 'label' => $cfg['label'], 'configured' => (bool) ($app['client_id'] && $app['client_secret']),
                'clientId' => $saved[$key] ?? null, 'fromEnv' => ! isset($saved[$key]) && $app['client_id'] && $app['client_secret'],
                'consoleUrl' => $cfg['console_url']];
        }

        return $this->ok(['redirectUri' => $this->redirectUri(), 'apps' => $rows]);
    }

    /** PUT talent/admin/integrations/oauth-apps/{provider} - saved for the caller's school (platform wide for a Super Admin). */
    public function saveOauthApp(Request $request, string $provider)
    {
        abort_if(! config("talent_integrations.oauth.{$provider}"), 404, 'Unknown provider');
        $v = $request->validate(['client_id' => 'required|string|max:255', 'client_secret' => 'required|string|max:1000']);

        DB::table('talent_admin_oauth_apps')->updateOrInsert(['provider' => $provider, 'sub_institute_id' => $this->keyScope()], [
            'client_id' => trim($v['client_id']), 'client_secret' => Crypt::encryptString(trim($v['client_secret'])),
            'updated_by' => $this->actorId(), 'updated_at' => now(),
        ]);
        $this->audit('updated', config("talent_integrations.oauth.{$provider}.label") . ' sign-in', 'Saved the OAuth app keys for ' . config("talent_integrations.oauth.{$provider}.label") . '.');

        return $this->ok(['provider' => $provider], 'Saved');
    }

    /** DELETE talent/admin/integrations/oauth-apps/{provider} - removes the keys saved for the caller's school (or platform wide for a Super Admin). */
    public function removeOauthApp(string $provider)
    {
        DB::table('talent_admin_oauth_apps')->where('provider', $provider)->where('sub_institute_id', $this->keyScope())->delete();
        $this->audit('deleted', config("talent_integrations.oauth.{$provider}.label", $provider) . ' sign-in', 'Removed the saved OAuth app keys.');

        return $this->ok(['provider' => $provider], 'Removed');
    }

    private function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/talent/admin/integrations/callback';
    }

    /** Only send the browser back to the app itself: a configured origin, a Vercel preview, or localhost in dev. */
    private function safeReturnUrl(string $url): string
    {
        $fallback = rtrim((string) config('mobile_page_builder.frontend_url'), '/') . '/modules/talent-management/operations';
        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $fallback;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $allowed = in_array($origin, (array) config('cors.allowed_origins'), true);
        foreach ((array) config('cors.allowed_origins_patterns') as $pattern) {
            $allowed = $allowed || (bool) @preg_match($pattern, $origin);
        }
        $allowed = $allowed || (app()->environment('local') && in_array($parts['host'], ['localhost', '127.0.0.1'], true));

        return $allowed ? $origin . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '') : $fallback;
    }

    private function withResult(string $return, string $result, string $detail): string
    {
        return $return . (str_contains($return, '?') ? '&' : '?') . http_build_query(['integration' => $result, 'detail' => Str::limit($detail, 200)]);
    }

    private function owned(int $id): object
    {
        $row = DB::table('talent_admin_integrations')->where('sub_institute_id', $this->tenant())->where('id', $id)->first();
        abort_if(! $row, 404, 'Integration not found');

        return $row;
    }

    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        return session()->get('user_id') !== null ? (int) session()->get('user_id') : null;
    }

    private function audit(string $action, string $name, string $details, ?int $tenant = null, ?int $actor = null): void
    {
        $actor = $actor ?? $this->actorId();
        $user = $actor ? DB::table('tbluser')->where('id', $actor)->first(['first_name', 'last_name']) : null;
        DB::table('talent_admin_audit_events')->insert([
            'sub_institute_id' => $tenant ?? $this->tenant(), 'actor_id' => $actor, 'actor_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : null,
            'module' => 'Integrations', 'action' => $action, 'entity_type' => 'integration', 'entity_name' => $name,
            'details' => $details, 'ip_address' => request()->ip(), 'created_at' => now(),
        ]);
    }

    private function ok($data, string $message = ''): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => 1, 'data' => $data, 'message' => $message]);
    }

    private function fail(string $message, int $code, array $extra = []): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => 0, 'message' => $message] + $extra, $code);
    }
}
