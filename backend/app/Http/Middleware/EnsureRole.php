<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Uso en rutas web:
     *   ->middleware(['auth', 'role:admin'])
     *
     * Uso en rutas api (si quieres):
     *   ->middleware(['auth:api', 'role:admin,juez'])
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1) Obtener usuario según el guard que esté usando la ruta
        //    - En web: usa el guard "web" → $request->user()
        //    - En api (si usas auth:api): también funciona si ese guard está configurado como default para esa ruta
        $user = $request->user();

        // 401: sin autenticación
        if (! $user) {
            // Si es una petición tipo API / JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'No autenticado'], 401);
            }

            // Si es una ruta web normal → redirigir a login
            return redirect()->route('login');
        }

        // 2) Convertir role_id -> nombre, usando tu tabla seguridad.roles
        $nombreRol = \DB::table('seguridad.roles')
            ->where('id', $user->role_id)
            ->value('nombre');

        // 3) 403: autenticado pero sin permiso
        // Soporta múltiples roles: role:admin,juez
        if (! $nombreRol || ! in_array($nombreRol, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Prohibido'], 403);
            }

            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
