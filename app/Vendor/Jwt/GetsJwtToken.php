<?php

namespace App\Vendor\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

trait GetsJwtToken
{
    protected $jwt;

    public function getJwtFromRequest(Request $request)
    {
        $header = $request->header('Authorization', '');
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return $request->input('jwt', '');
    }

    public function jwtFromRequest(Request $request)
    {
        return $this->getJwtFromRequest($request);
    }

    public function validateJwt(string $token): bool
    {
        try {
            $secret = config('jwt.secret', env('JWT_SECRET', 'secret'));
            $algo = config('jwt.algo', env('JWT_ALGO', 'HS256'));
            
            JWT::decode($token, new Key($secret, $algo));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function decodeJwt(string $token): ?object
    {
        try {
            $secret = config('jwt.secret', env('JWT_SECRET', 'secret'));
            $algo = config('jwt.algo', env('JWT_ALGO', 'HS256'));
            
            return JWT::decode($token, new Key($secret, $algo));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPayload(string $token): ?array
    {
        $decoded = $this->decodeJwt($token);
        return $decoded ? (array) $decoded : null;
    }

    public function getPayloadValue(string $token, string $key, $default = null)
    {
        $payload = $this->getPayload($token);
        return $payload[$key] ?? $default;
    }
}
