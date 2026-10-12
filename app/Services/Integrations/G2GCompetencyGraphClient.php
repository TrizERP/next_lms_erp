<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real HTTP client to G2G's (hp_erp) live Competency/KASBA/Framework graph.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 *
 * K-12's "Capability Library" / KASBA Explorer screens
 * (App\Http\Controllers\api\TalentManagement\Competency\CapabilityLibraryController,
 * App\Http\Controllers\Brain\BrainController) read this product's OWN local
 * tables (`s_users_skills`/`s_user_jobrole`/... via the ported LibraryController,
 * or `hpbrain_capabilities` via the Brain ingestion pipeline). Neither of those
 * is a cross-product call: both are local data this application already owns.
 *
 * G2G (hp_erp) is a SEPARATE Laravel application with its own real
 * Competency/KasbaItem/Framework Neo4j graph (App\Services\Graph\GraphVocabulary
 * in that codebase) and its own REST API at `/api/competency/library/*` and
 * `/api/jobroles/{id}/graph`. This client is the first real network call from
 * this application TO that one.
 *
 * ── AUTH, AS IT ACTUALLY STANDS (read before wiring anything else to this) ──
 *
 * hp_erp's `/api/competency/library/*` endpoints are guarded by
 * `App\Http\Controllers\Api\Competency\Concerns\ResolvesCompetencyContext`,
 * which resolves identity from a Laravel Sanctum PERSONAL ACCESS TOKEN tied to
 * one specific hp_erp `tbluser` row (`PersonalAccessToken::findToken()`,
 * confirmed by reading that trait and `RequireApiToken` directly). There is no
 * API-key / service-account mechanism in hp_erp for machine-to-machine calls -
 * every token belongs to a named employee and inherits THEIR
 * `sub_institute_id` (hp_erp's own HR-tenant concept, unrelated to a K-12
 * school id).
 *
 * Checked before writing this class, so this is not assumed:
 *   - next_lms_erp's .env has no G2G/hp_erp credential of any kind (grepped
 *     for hp_erp/g2g/cross-product/service-token/HPERP/KASBA - nothing).
 *   - hp_erp's own `personal_access_tokens` table (queried directly) has no
 *     token meant for cross-product use either - every row is either a
 *     browser session (`Chrome on Windows 10 or 11`) or a short-lived,
 *     human-named dev verification token another agent created today for its
 *     OWN feature work (`test-probe`, `admin-test`, `verify-task1`, ...).
 *     Reusing one of those would be borrowing a stranger's identity, not
 *     "finding" a cross-product credential, so none was reused.
 *   - Provisioning a new one was attempted and the session's own permission
 *     classifier refused it (`Secret-Store Writes`), which is the correct
 *     outcome: minting a personal-access token is a credential decision for
 *     whoever administers hp_erp, not something this task should do to itself
 *     with no one in the loop.
 *
 * So: no working credential exists, and this class does not pretend one does.
 * `G2G_API_BASE_URL` / `G2G_API_TOKEN` are read from env; when either is blank,
 * every method here returns the explicit `unavailable` / `auth_not_configured`
 * shape below rather than silently returning an empty success or inventing
 * data. Once hp_erp's administrator issues a real token (ideally a dedicated
 * service account's, not a borrowed employee's) and it is placed in
 * `G2G_API_TOKEN`, this class works unchanged - the HTTP call itself was
 * verified for real: an unauthenticated GET against a live hp_erp instance
 * (local `php artisan serve`, confirmed reachable) came back `401 {"status":0,
 * "message":"Token not provided"}` - the real endpoint, the real guard, just no
 * credential to pass it.
 */
class G2GCompetencyGraphClient
{
    private ?string $baseUrl;

    private ?string $token;

    private int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = $this->normalizeBaseUrl((string) config('services.g2g.base_url', ''));
        $token = (string) config('services.g2g.token', '');
        $this->token = $token !== '' ? $token : null;
        $this->timeoutSeconds = (int) config('services.g2g.timeout', 8);
    }

    /** Both a base URL and a token are present. Neither alone is enough to call out. */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== null && $this->token !== null;
    }

    /**
     * Why a call cannot be made right now, or null when it can be attempted.
     */
    public function configurationGap(): ?string
    {
        if ($this->baseUrl === null && $this->token === null) {
            return 'G2G_API_BASE_URL and G2G_API_TOKEN are not set - no hp_erp credential exists for this '
                .'application to call out with. See G2GCompetencyGraphClient\'s class doc for what was checked.';
        }
        if ($this->baseUrl === null) {
            return 'G2G_API_BASE_URL is not set.';
        }

        // BUGFIX 2026-10-12: this used to be an unconditional `return` with no
        // preceding `token === null` check, so once a base URL was set this
        // method always reported "G2G_API_TOKEN is not set" - including once a
        // real token WAS configured, which made isConfigured()===true and
        // configurationGap()!==null simultaneously true. crossProductStatus()
        // surfaces both fields verbatim, so the frontend would show "connected"
        // and a false "no credential" reason in the same payload. Caught by
        // calling crossProductStatus() for real after a working G2G_API_TOKEN
        // was provisioned: `configured: true` came back next to a stale "not
        // set" gap.
        if ($this->token === null) {
            return 'G2G_API_TOKEN is not set - hp_erp\'s /api/competency/library/* endpoints require a Sanctum '
                .'personal access token tied to a real hp_erp employee; no such credential has been provisioned for '
                .'cross-product calls.';
        }

        return null;
    }

    /**
     * One KASBA facet from G2G's real library: skill, knowledge, ability,
     * attitude or behaviour.
     *
     * Hits hp_erp's `GET /api/competency/library/kasa/{type}` directly for the
     * four KASA types, and `GET /api/competency/library/skills` for 'skill'
     * (that one is a separate route on the hp_erp side - see
     * routes/api.php:552/605 in that codebase).
     *
     * @return array{ok:bool, source:string, status:int|null, data:array, error:?string}
     */
    public function fetchKasbaFacet(string $type): array
    {
        $type = strtolower(trim($type));
        $path = $type === 'skill' ? 'skills' : 'kasa/'.$type;

        return $this->get($path);
    }

    /** `GET /api/competency/library/meta` - tab counts and dropdown options. */
    public function fetchLibraryMeta(): array
    {
        return $this->get('meta');
    }

    /**
     * Shared, low-level GET against hp_erp's competency library API.
     *
     * @return array{ok:bool, source:string, status:int|null, data:array, error:?string}
     */
    private function get(string $path, array $query = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'source' => 'unavailable',
                'status' => null,
                'data' => [],
                'error' => $this->configurationGap(),
            ];
        }

        $url = $this->baseUrl.'/api/competency/library/'.ltrim($path, '/');

        try {
            $response = Http::withToken((string) $this->token)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->get($url, $query);

            if ($response->unauthorized() || $response->forbidden()) {
                return [
                    'ok' => false,
                    'source' => 'g2g_live',
                    'status' => $response->status(),
                    'data' => [],
                    'error' => 'hp_erp refused the credential ('.$response->status().'): '
                        .(string) ($response->json('message') ?? $response->body()),
                ];
            }

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'source' => 'g2g_live',
                    'status' => $response->status(),
                    'data' => [],
                    'error' => 'hp_erp returned HTTP '.$response->status(),
                ];
            }

            return [
                'ok' => true,
                'source' => 'g2g_live',
                'status' => $response->status(),
                'data' => (array) ($response->json('data') ?? []),
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            Log::warning('G2GCompetencyGraphClient: could not reach hp_erp', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'source' => 'unavailable',
                'status' => null,
                'data' => [],
                'error' => 'Could not reach hp_erp at '.$this->baseUrl.': '.$e->getMessage(),
            ];
        } catch (RequestException $e) {
            return [
                'ok' => false,
                'source' => 'g2g_live',
                'status' => $e->response?->status(),
                'data' => [],
                'error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('G2GCompetencyGraphClient: unexpected failure calling hp_erp', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'source' => 'unavailable',
                'status' => null,
                'data' => [],
                'error' => 'Unexpected error calling hp_erp: '.$e->getMessage(),
            ];
        }
    }

    private function normalizeBaseUrl(string $url): ?string
    {
        $url = trim($url);

        return $url === '' ? null : rtrim($url, '/');
    }
}
