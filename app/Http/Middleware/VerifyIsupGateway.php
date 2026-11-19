<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the ISUP gateway (which knows the shared token) may call its endpoints.
 */
class VerifyIsupGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('isup.token');
        $given = (string) $request->header('X-Gateway-Token');

        abort_if($expected === '' || ! hash_equals($expected, $given), 401);

        return $next($request);
    }
}
