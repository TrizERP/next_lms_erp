<?php

namespace App\Domain\AI\Support;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A provider refusal that keeps what a caller needs to react correctly, not just prose.
 *
 * It is still a RuntimeException with the same message the client always raised, so every
 * existing `catch (RuntimeException)` behaves as before. What it adds is the structured part
 * of the answer that used to be thrown away:
 *
 *   status            the HTTP status
 *   retryAfter        seconds the provider asked us to wait (Retry-After header, or the
 *                     RetryInfo.retryDelay Google puts in a 429 body), when it said
 *   quotaMetric       the violated quota's id (e.g. ...PerMinute... or ...PerDay...)
 *   providerStatus    the provider's own status word (e.g. RESOURCE_EXHAUSTED)
 *
 * With those a caller can tell "you are going too fast, wait N seconds" (retryable) from "the
 * quota or billing for this key is used up" (retrying only burns time), without parsing a
 * truncated JSON blob. Nothing here carries the request, the key or the response body.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?int $retryAfter = null,
        public readonly ?string $quotaMetric = null,
        public readonly ?string $providerStatus = null,
    ) {
        parent::__construct($message, $status);
    }

    /** Pull the safe, structured parts out of a failed provider response. */
    public static function metadataFrom(Response $response): array
    {
        $retryAfter = null;
        $header = $response->header('Retry-After');
        if ($header !== null && $header !== '' && ctype_digit(trim($header))) {
            $retryAfter = (int) $header;
        }

        $quotaMetric = null;
        $providerStatus = null;
        $json = $response->json();
        if (is_array($json) && is_array($json['error'] ?? null)) {
            $providerStatus = is_string($json['error']['status'] ?? null) ? $json['error']['status'] : null;
            foreach ((array) ($json['error']['details'] ?? []) as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                foreach ((array) ($detail['violations'] ?? []) as $violation) {
                    if (is_array($violation) && is_string($violation['quotaId'] ?? null)) {
                        $quotaMetric ??= $violation['quotaId'];
                    }
                }
                if ($retryAfter === null && is_string($detail['retryDelay'] ?? null)
                    && preg_match('/^(\d+(?:\.\d+)?)s$/', $detail['retryDelay'], $m)) {
                    $retryAfter = (int) ceil((float) $m[1]);
                }
            }
        }

        return [$retryAfter, $quotaMetric, $providerStatus];
    }
}
