<?php

namespace App\Http\Controllers\Api\Concerns;

use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;

/**
 * G-SEC-29: the tenant a caller acts as must come from their verified token,
 * never from client-supplied request input - the same rule already enforced
 * for every session()-reading controller by
 * App\Http\Middleware\Concerns\HydratesLegacyApiSession. This trait covers
 * the handful of controllers that are routed outside that middleware and
 * read the request directly.
 */
trait ResolvesApiIdentity
{
    use GetsJwtToken;

    /**
     * The caller's organisation, resolved from the verified JWT payload only.
     * Returns null when the token is missing/invalid, so callers bind an
     * unidentified caller to a `where sub_institute_id = ?` that matches
     * nothing rather than a tenant the request named itself.
     */
    protected function apiTenantId(Request $request): ?int
    {
        try {
            if (! $this->jwtToken($request)->validate()) {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        $subInstituteId = $this->jwtPayload('sub_institute_id', $request);

        return is_numeric($subInstituteId) ? (int) $subInstituteId : null;
    }
}
