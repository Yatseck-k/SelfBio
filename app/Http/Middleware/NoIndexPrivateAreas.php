<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Private areas (admin, sign-in, the private catalog, the player's own data) say «noindex» in the header too, so files and JSON are covered as well. */
class NoIndexPrivateAreas
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (Seo::isPrivate($request->path())) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
