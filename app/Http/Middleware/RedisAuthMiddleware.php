<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RedisAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request): (Response) $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization');

        /*  if (empty($authorizationHeader) || !str_starts_with($authorizationHeader, 'Bearer ')) {
              return response()->json(['error' => 'errors.unaauthorized'], 401);
          }

          $token = substr($authorizationHeader, 7);

          $authId = Redis::get("auth:token:{$token}");
          if (!$authId) {
              return response()->json(['error' => 'Invalid or expired token'], 401);
          }


          $request->merge(['auth_id' => $authId]);
*/

        $request->merge(['auth_id' => '$authId']);

        return $next($request);
    }
}
