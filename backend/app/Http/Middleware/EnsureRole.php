<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Uso en rutas: ->middleware(['auth:api','role:admin'])
     * Acepta varios: ->middleware(['auth:api','role:admin,juez'])
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 401: sin autenticación
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Convertir role_id -> nombre (seguridad.roles)
        $nombreRol = \DB::table('seguridad.roles')->where('id', $user->role_id)->value('nombre');

        // 403: autenticado pero sin permiso
        // Soporta múltiples roles: role:admin,juez
        if (!$nombreRol || ! in_array($nombreRol, $roles)) {
            return response()->json(['message' => 'Prohibido'], 403);
        }

        return $next($request);
    }
}
