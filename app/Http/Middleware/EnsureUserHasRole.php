<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian: ->middleware('role:admin') atau 'role:admin,opd'.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            in_array($request->user()?->role?->value, $roles, true),
            403,
            'Halaman ini bukan untuk peran akun Anda.',
        );

        return $next($request);
    }
}
