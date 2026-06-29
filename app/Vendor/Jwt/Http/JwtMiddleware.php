<?php

namespace App\Vendor\Jwt\Http;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next, $guard = null)
    {
        $header = $request->header('Authorization', '');
        
        if (!preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return response()->json(['error' => 'Token not provided'], 401);
        }
        
        $token = $matches[1];
        $secret = config('jwt.secret', env('JWT_SECRET', 'secret'));
        $algo = config('jwt.algo', env('JWT_ALGO', 'HS256'));
        
        try {
            $decoded = JWT::decode($token, new Key($secret, $algo));
            $request->attributes->set('jwt_payload', (array) $decoded);
            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }
}
