<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Errors\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireJson
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasBody($request) && ! $this->isJsonObjectOrArray($request)) {
            throw new ApiException('request.not_json', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        return $next($request);
    }

    private function hasBody(Request $request): bool
    {
        return $request->getContent() !== ''
            || $request->request->count() > 0
            || $request->files->count() > 0;
    }

    private function isJsonObjectOrArray(Request $request): bool
    {
        $content = $request->getContent();

        if (! $request->isJson() || ! json_validate($content)) {
            return false;
        }

        $decoded = json_decode($content);

        return is_array($decoded) || is_object($decoded);
    }
}
