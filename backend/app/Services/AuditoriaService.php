<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditoriaService
{
    public function registrar(
        string $accion,
        ?string $tabla = null,
        ?string $modulo = null,
        ?string $descripcion = null,
        array $payload = [],
        string $estado = 'exitoso',
        ?int $userId = null,
        ?Request $request = null
    ): void {
        try {
            $request ??= request();

            DB::table('auditoria.auditorias')->insert([
                'tabla' => $tabla,
                'accion' => $accion,
                'modulo' => $modulo,
                'descripcion' => $descripcion,
                'estado' => $estado,
                'user_id' => $userId ?? optional($request->user())->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ocurrio_en' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo registrar auditoria.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
