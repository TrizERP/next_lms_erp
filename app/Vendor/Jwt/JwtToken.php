<?php

namespace App\Vendor\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtToken
{
    protected $secret;
    protected $algo;
    protected $leeway;

    public function __construct()
    {
        $this->secret = config('jwt.secret', env('JWT_SECRET', 'secret'));
        $this->algo = config('jwt.algo', env('JWT_ALGO', 'HS256'));
        $this->leeway = config('jwt.leeway', env('JWT_LEEWAY', 0));
    }

    public function make(array $payload, int $expiration = null): string
    {
        if ($expiration) {
            $payload['exp'] = $expiration;
        }
        
        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }
        
        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function create(array $payload, int $expiration = null): string
    {
        return $this->make($payload, $expiration);
    }

    public function decode(string $token): ?object
    {
        try {
            JWT::$leeway = $this->leeway;
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function check(string $token): bool
    {
        return $this->decode($token) !== null;
    }

    public function getPayload(string $token): ?array
    {
        $decoded = $this->decode($token);
        return $decoded ? (array) $decoded : null;
    }
}
