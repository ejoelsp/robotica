<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJuezProfileCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        if ((int) $user->role_id !== 2) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (
            empty($user->photo_path) &&
            !in_array($routeName, [
                'juez.completar-perfil',
                'juez.completar-perfil.update',
                'logout',
            ])
        ) {
            return redirect()->route('juez.completar-perfil');
        }

        return $next($request);
    }
}
