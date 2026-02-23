<?php

namespace App\Http\Middleware;

use App\Models\InternalApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return response()->json([
                'message' => 'Unauthorized. Use Authorization: Bearer <accessToken>.',
            ], 401);
        }

        $plainToken = trim($matches[1] ?? '');
        if ($plainToken === '') {
            return response()->json([
                'message' => 'Unauthorized. Empty access token.',
            ], 401);
        }

        $token = InternalApiToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null) {
            return response()->json([
                'message' => 'Unauthorized. Invalid access token.',
            ], 401);
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return response()->json([
                'message' => 'Unauthorized. Access token has expired.',
            ], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
