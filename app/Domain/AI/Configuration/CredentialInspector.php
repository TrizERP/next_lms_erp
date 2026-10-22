<?php

namespace App\Domain\AI\Configuration;

/**
 * Whether a credential can really be used — as a status an administrator can read.
 *
 * A row existing in `ai_api_keys` proves somebody pasted something. It does not prove the
 * provider accepts it. This asks the provider (through `ProviderModelDiscovery`, which caches
 * the answer) and reports one of:
 *
 *   ready        the provider accepted the key
 *   present      a key is stored, and there is no verdict on it — not checked yet, the provider
 *                could not be reached, or it has no way to check one
 *   invalid      the provider rejected the key
 *   absent       there is no key
 *
 * It returns a label and a sentence, never the key, and never anything derived from it beyond
 * the verdict.
 */
final class CredentialInspector
{
    public function __construct(private readonly ProviderModelDiscovery $discovery)
    {
    }

    /**
     * @param  bool  $live  Ask the provider when no verdict is cached. False reads the cache only.
     * @return array{status:string, label:string, detail:string|null}
     */
    public function inspect(string $provider, ?string $apiKey, bool $live = true): array
    {
        $apiKey = $apiKey === null ? '' : trim($apiKey);

        if ($apiKey === '' || $apiKey === '-') {
            return [
                'status' => 'absent',
                'label' => 'Absent',
                'detail' => 'No API key is stored for this provider.',
            ];
        }

        $verdict = $live
            ? $this->discovery->verify($provider, $apiKey)
            : $this->discovery->cachedVerdict($provider, $apiKey);

        return $this->describe($verdict);
    }

    /**
     * @param  array{status:string, message:string|null}|null  $verdict
     * @return array{status:string, label:string, detail:string|null}
     */
    public function describe(?array $verdict): array
    {
        $status = $verdict['status'] ?? null;

        return match ($status) {
            'valid' => [
                'status' => 'ready',
                'label' => 'Connected',
                'detail' => $verdict['message'] ?? 'The provider accepted this key.',
            ],
            'invalid' => [
                'status' => 'invalid',
                'label' => 'Invalid',
                'detail' => $verdict['message'] ?? 'The provider rejected this key.',
            ],
            'unreachable' => [
                'status' => 'present',
                'label' => 'Stored, not verified',
                'detail' => $verdict['message'] ?? 'The provider could not be reached to check this key.',
            ],
            'unverified' => [
                'status' => 'present',
                'label' => 'Stored, not verified',
                'detail' => $verdict['message'] ?? 'This provider has no way to check a key without a generation call.',
            ],
            default => [
                'status' => 'present',
                'label' => 'Stored, not verified',
                'detail' => 'A key is stored; it has not been checked with the provider yet.',
            ],
        };
    }
}
