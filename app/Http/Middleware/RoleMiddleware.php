<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Sirf diye gaye role (jaise 'admin') wale user ko aage jaane do.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (Auth::user()->role !== $role) {
            abort(403, 'Aapke paas is page ka access nahi hai.');
        }

        return $next($request);
    }
}
