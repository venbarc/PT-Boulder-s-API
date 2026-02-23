<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternalApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthTokenController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $expectedUsername = (string) config('internal_api.username');
        $expectedPassword = (string) config('internal_api.password');

        if (
            ! hash_equals($expectedUsername, (string) $validated['username'])
            || ! hash_equals($expectedPassword, (string) $validated['password'])
        ) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $plainToken = Str::random(80);
        $ttlMinutes = max(5, (int) config('internal_api.token_ttl_minutes', 1440));

        InternalApiToken::query()->create([
            'name' => 'joberto24',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return response()->json([
            'accessToken' => $plainToken,
        ]);
    }
}
