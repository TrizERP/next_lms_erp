<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\Platform\PlatformIntegrationConfig;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * The Integrations store — provider credentials per institute.
 *
 * SECRETS: any config key that looks like a credential is encrypted with
 * Crypt::encryptString before it reaches the database (stored as "enc:<cipher>")
 * and is returned as '********' in EVERY response — list, show, store, update and
 * test. The plaintext only ever exists inside this class while a test runs.
 * On update, sending '********' back (what a form that round-trips the masked
 * value does) keeps the stored secret; any other value replaces it.
 *
 * READS need a session only (the response never carries a secret, so seeing which
 * providers are configured is harmless); WRITES need `perm:platform.integration`.
 */
class IntegrationController extends PlatformController
{
    private const MASK = '********';

    private const SECRET_PATTERN = '/(password|passwd|secret|token|api_?key|server_?key|private_?key|auth|credential|account_number)/i';

    private const CATEGORIES = ['sms', 'email', 'whatsapp', 'push', 'payment', 'biometric', 'bank'];

    private const STATUSES = ['active', 'inactive', 'error'];

    /** Required config fields per category. */
    private const REQUIRED = [
        'sms' => ['api_url', 'api_key', 'sender_id'],
        'email' => ['host', 'port', 'username', 'password', 'from_address'],
        'whatsapp' => ['api_url', 'access_token', 'phone_number_id'],
        'push' => ['server_key', 'project_id'],
        'payment' => ['merchant_id', 'api_key', 'api_secret'],
        'biometric' => ['device_url', 'api_key'],
        'bank' => ['api_url', 'account_number', 'api_key'],
    ];

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $query = PlatformIntegrationConfig::forTenant($tenantId);
        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            if (! in_array($category, self::CATEGORIES, true)) {
                return $this->fail('"'.$category.'" is not an integration category.', 404);
            }
            $query->where('category', $category);
        }

        $rows = $query->orderBy('category')->orderBy('display_name')->get();

        return $this->ok($rows->map(fn ($r) => $this->present($r))->values()->all(), [
            'categories' => self::CATEGORIES,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $row = PlatformIntegrationConfig::forTenant($tenantId)->find($id);

        return $row ? $this->ok($this->present($row)) : $this->fail('That integration no longer exists.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $providerKey = strtolower(trim((string) $request->input('provider_key', '')));
        if (! preg_match('/^[a-z0-9][a-z0-9_.-]{1,63}$/', $providerKey)) {
            return $this->fail('provider_key must be 2-64 characters: lowercase letters, numbers, dot, dash or underscore.', 422);
        }

        $name = trim((string) $request->input('display_name', ''));
        if ($name === '' || mb_strlen($name) > 150) {
            return $this->fail('Give the integration a name of up to 150 characters.', 422);
        }

        $category = (string) $request->input('category', '');
        if (! in_array($category, self::CATEGORIES, true)) {
            return $this->fail('category must be one of: '.implode(', ', self::CATEGORIES).'.', 422);
        }

        $status = (string) $request->input('status', 'inactive');
        if (! in_array($status, self::STATUSES, true)) {
            return $this->fail('status must be active, inactive or error.', 422);
        }

        $config = $request->input('config', []);
        if (! is_array($config)) {
            return $this->fail('config must be an object of settings.', 422);
        }

        if (PlatformIntegrationConfig::forTenant($tenantId)->where('provider_key', $providerKey)->exists()) {
            return $this->fail('An integration with the key "'.$providerKey.'" already exists for this institute.', 409);
        }

        $problem = $this->activationProblem($status, $category, $config);
        if ($problem !== null) {
            return $this->fail($problem, 422);
        }

        $actor = $this->actorLabel($request);
        $row = PlatformIntegrationConfig::create([
            'sub_institute_id' => $tenantId,
            'provider_key' => $providerKey,
            'display_name' => $name,
            'category' => $category,
            'description' => mb_substr((string) $request->input('description', ''), 0, 500),
            'status' => $status,
            'config_json' => json_encode($this->seal($config, [])),
            'updated_by' => $actor,
            'is_sample' => 0,
        ]);

        AuditTrail::record($tenantId, 'platform', 'platform.integration', 'create', [
            'entity_type' => 'integration', 'entity_id' => $row->id,
            'after' => ['provider_key' => $providerKey, 'category' => $category, 'status' => $status],
        ], $request);

        return $this->ok($this->present($row), [], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $row = PlatformIntegrationConfig::forTenant($tenantId)->find($id);
        if ($row === null) {
            return $this->fail('That integration no longer exists.', 404);
        }

        $before = ['display_name' => $row->display_name, 'status' => $row->status];

        if ($request->has('display_name')) {
            $name = trim((string) $request->input('display_name', ''));
            if ($name === '' || mb_strlen($name) > 150) {
                return $this->fail('Give the integration a name of up to 150 characters.', 422);
            }
            $row->display_name = $name;
        }

        if ($request->has('description')) {
            $row->description = mb_substr((string) $request->input('description', ''), 0, 500);
        }

        $stored = $this->stored($row);
        $config = $request->input('config');
        if ($request->has('config')) {
            if (! is_array($config)) {
                return $this->fail('config must be an object of settings.', 422);
            }
            $stored = $this->seal($config, $stored);
        }

        $status = $row->status;
        if ($request->has('status')) {
            $status = (string) $request->input('status');
            if (! in_array($status, self::STATUSES, true)) {
                return $this->fail('status must be active, inactive or error.', 422);
            }
        }

        $problem = $this->activationProblem($status, $row->category, $this->open($stored));
        if ($problem !== null) {
            return $this->fail($problem, 422);
        }

        $row->status = $status;
        $row->config_json = json_encode($stored);
        $row->updated_by = $this->actorLabel($request);
        $row->is_sample = false; // an edited sample row is real configuration now
        $row->save();

        AuditTrail::record($tenantId, 'platform', 'platform.integration', 'update', [
            'entity_type' => 'integration', 'entity_id' => $row->id,
            'before' => $before,
            'after' => ['display_name' => $row->display_name, 'status' => $row->status,
                'config_changed' => $request->has('config')],
        ], $request);

        return $this->ok($this->present($row->refresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $row = PlatformIntegrationConfig::forTenant($tenantId)->find($id);
        if ($row === null) {
            return $this->fail('That integration no longer exists.', 404);
        }

        $snapshot = ['provider_key' => $row->provider_key, 'category' => $row->category, 'status' => $row->status];
        $row->delete();

        AuditTrail::record($tenantId, 'platform', 'platform.integration', 'delete', [
            'entity_type' => 'integration', 'entity_id' => $id, 'before' => $snapshot,
        ], $request);

        return $this->ok(['deleted' => $id]);
    }

    /**
     * POST /api/platform/integrations/{id}/test
     *
     * Never fakes a success. Order of checks:
     *   1. Every required field for the category must be present (else fail).
     *   2. Where there is something to contact — an SMTP host:port, or an
     *      http(s) endpoint — it is actually contacted. Success needs that to
     *      answer. A reachable endpoint proves the address, NOT that the
     *      credentials are accepted; the message says so.
     *   3. Otherwise (push) only field validation is possible, and the result is
     *      labelled mode=validation so nobody reads it as a live check.
     * Private, loopback and reserved addresses are refused so a tenant cannot
     * use this endpoint to probe the server's own network.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $row = PlatformIntegrationConfig::forTenant($tenantId)->find($id);
        if ($row === null) {
            return $this->fail('That integration no longer exists.', 404);
        }

        $config = $this->open($this->stored($row));
        $result = $this->probe($row->category, $config);

        $row->last_tested_at = now();
        $row->last_tested_by = $this->actorLabel($request);
        if (! $result['ok']) {
            $row->status = 'error';
        } elseif ($row->status === 'error') {
            $row->status = 'inactive';
        }
        $row->save();

        AuditTrail::record($tenantId, 'platform', 'platform.integration', 'test', [
            'entity_type' => 'integration', 'entity_id' => $row->id,
            'after' => ['ok' => $result['ok'], 'mode' => $result['mode']],
        ], $request);

        return $this->ok(array_merge($result, ['integration' => $this->present($row->refresh())]));
    }

    // ── Testing ─────────────────────────────────────────────────────────────

    /** @return array{ok:bool,mode:string,message:string} */
    private function probe(string $category, array $config): array
    {
        $missing = $this->missing($category, $config);
        if ($missing !== []) {
            return ['ok' => false, 'mode' => 'validation',
                'message' => 'Missing required settings: '.implode(', ', $missing).'.'];
        }

        if ($category === 'email') {
            $host = (string) $config['host'];
            $port = (int) $config['port'];
            if ($port < 1 || $port > 65535) {
                return ['ok' => false, 'mode' => 'validation', 'message' => 'The port must be between 1 and 65535.'];
            }
            if ($refusal = $this->unsafeHost($host)) {
                return ['ok' => false, 'mode' => 'connectivity', 'message' => $refusal];
            }
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, $port, $errno, $errstr, 6);
            if ($socket === false) {
                return ['ok' => false, 'mode' => 'connectivity',
                    'message' => "Could not connect to {$host}:{$port} ({$errstr}). Check the host, port and firewall."];
            }
            fclose($socket);

            return ['ok' => true, 'mode' => 'connectivity',
                'message' => "Connected to {$host}:{$port}. The server is reachable; the username and password were not tried."];
        }

        $url = $config['api_url'] ?? $config['device_url'] ?? null;
        if (is_string($url) && $url !== '') {
            $parts = parse_url($url);
            if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
                return ['ok' => false, 'mode' => 'validation', 'message' => 'The URL must start with http:// or https:// and include a host.'];
            }
            if ($refusal = $this->unsafeHost($parts['host'])) {
                return ['ok' => false, 'mode' => 'connectivity', 'message' => $refusal];
            }
            try {
                $response = Http::timeout(6)->withoutRedirecting()->get($url);
            } catch (\Throwable $e) {
                return ['ok' => false, 'mode' => 'connectivity',
                    'message' => "Could not reach {$parts['host']}. Check the address and that the service is online."];
            }

            return ['ok' => true, 'mode' => 'connectivity',
                'message' => "{$parts['host']} answered with HTTP {$response->status()}. The address is reachable; the credentials were not verified."];
        }

        return ['ok' => true, 'mode' => 'validation',
            'message' => 'All required settings are present. This provider has no address to contact, so nothing was sent to it and the credentials were not verified.'];
    }

    private function unsafeHost(string $host): ?string
    {
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return "The host \"{$host}\" does not resolve to an address.";
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Private and internal addresses cannot be tested from here.';
        }

        return null;
    }

    // ── Validation ──────────────────────────────────────────────────────────

    /** @return list<string> */
    private function missing(string $category, array $config): array
    {
        $missing = [];
        foreach (self::REQUIRED[$category] ?? [] as $field) {
            $value = $config[$field] ?? null;
            if ($value === null || $value === '' || $value === self::MASK) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /** An active integration must be complete — an active one with no key would fail silently later. */
    private function activationProblem(string $status, string $category, array $config): ?string
    {
        if ($status !== 'active') {
            return null;
        }
        $missing = $this->missing($category, $config);

        return $missing === [] ? null : 'An active integration needs: '.implode(', ', $missing).'. Fill them in, or save it as inactive.';
    }

    // ── Secrets ─────────────────────────────────────────────────────────────

    private function isSecret(string $key): bool
    {
        return (bool) preg_match(self::SECRET_PATTERN, $key);
    }

    /** Stored (sealed) config as an array. */
    private function stored(PlatformIntegrationConfig $row): array
    {
        $decoded = $row->config_json ? json_decode($row->config_json, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /** Merge incoming settings over the stored ones, encrypting secrets. */
    private function seal(array $incoming, array $stored): array
    {
        foreach ($incoming as $key => $value) {
            $key = (string) $key;
            if (! preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key) || is_array($value) || is_object($value)) {
                continue; // flat, simply-named settings only
            }
            $value = $value === null ? '' : (string) $value;

            if ($this->isSecret($key)) {
                if ($value === self::MASK) {
                    continue; // round-tripped mask: keep what is stored
                }
                $stored[$key] = $value === '' ? '' : 'enc:'.Crypt::encryptString($value);
            } else {
                $stored[$key] = mb_substr($value, 0, 500);
            }
        }

        return $stored;
    }

    /** Decrypt for internal use only. Never returned from the API. */
    private function open(array $stored): array
    {
        $plain = [];
        foreach ($stored as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'enc:')) {
                try {
                    $plain[$key] = Crypt::decryptString(substr($value, 4));
                } catch (\Throwable $e) {
                    $plain[$key] = ''; // unreadable (key rotated): treat as missing
                }
            } else {
                $plain[$key] = $value;
            }
        }

        return $plain;
    }

    /** The only shape that leaves this class: secrets masked. */
    private function present(PlatformIntegrationConfig $row): array
    {
        $config = [];
        foreach ($this->stored($row) as $key => $value) {
            $config[$key] = $this->isSecret((string) $key)
                ? (($value === '' || $value === null) ? '' : self::MASK)
                : $value;
        }

        return [
            'id' => (int) $row->id,
            'provider_key' => $row->provider_key,
            'display_name' => $row->display_name,
            'category' => $row->category,
            'description' => $row->description,
            'status' => $row->status,
            'config' => $config,
            'required_fields' => self::REQUIRED[$row->category] ?? [],
            'last_tested_at' => $row->last_tested_at?->toIso8601String(),
            'last_tested_by' => $row->last_tested_by,
            'updated_by' => $row->updated_by,
            'is_sample' => (bool) $row->is_sample,
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
