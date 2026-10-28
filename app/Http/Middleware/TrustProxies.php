<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request as RequestAlias;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Set TRUSTED_PROXIES to the load balancer's address(es), comma separated, or `*` when the app
     * is only reachable through one. Without it the real client address is never seen, so every
     * per-IP limit (login, OTP, fee pay) counts all users behind the proxy as one.
     */
    protected function proxies()
    {
        $configured = trim((string) env('TRUSTED_PROXIES', ''));
        if ($configured === '') {
            return parent::proxies();
        }

        return $configured === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $configured))));
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        RequestAlias::HEADER_X_FORWARDED_FOR |
        RequestAlias::HEADER_X_FORWARDED_HOST |
        RequestAlias::HEADER_X_FORWARDED_PORT |
        RequestAlias::HEADER_X_FORWARDED_PROTO |
        RequestAlias::HEADER_X_FORWARDED_AWS_ELB;
}
