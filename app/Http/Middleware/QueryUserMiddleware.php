<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueryUserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request): (Response) $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $authId = $request->get('auth_id');

        if (!$authId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::where('auth_id', $authId)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Inject user_id into request
        $request->merge(['user_id' => $user->id]);

        return $next($request);
    }
}
