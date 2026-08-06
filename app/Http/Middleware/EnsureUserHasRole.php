<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic per-role route guard: Route::middleware('role:administrator').
 * Introduced in the MGA pivot (Fase 05) as the roster of roles grew beyond
 * user/grafolog - inline `abort_unless($user->isGrafolog())` checks in
 * controllers (still used by ScoringController, SampleController,
 * UserLookupController for the original 2 roles) don't scale to 4+ roles.
 * New role-gated routes should use this; existing inline checks weren't
 * migrated since they still work and touching them risks regressions.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->role, $roles, true), 403);

        return $next($request);
    }
}
