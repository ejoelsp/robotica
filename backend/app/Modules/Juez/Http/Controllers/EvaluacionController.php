<?php

namespace App\Modules\Juez\Http\Controllers;

use App\Exceptions\EvaluacionConcurrencyException;
use App\Http\Controllers\Controller;
use App\Modules\Juez\Requests\GuardarEvaluacionRequest;
use App\Services\EvaluacionJuezService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvaluacionController extends Controller
{
    public function __construct(
        private readonly EvaluacionJuezService $service
    ) {
    }

    public function contexto(Request $request): JsonResponse
    {
        $request->validate([
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $contexto = $this->service->getContextoJuez(
            $request->user(),
            $request->integer('categoria_id') ?: null,
            $request->integer('ronda_id') ?: null,
            $request->session()->getId()
        );

        return response()->json($contexto);
    }

    public function formulario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ronda_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['required', 'integer', 'min:1'],
            'intento_numero' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        return response()->json(
            $this->service->construirFormulario(
                $request->user(),
                (int) $validated['ronda_id'],
                (int) $validated['equipo_id'],
                (int) ($validated['intento_numero'] ?? 1),
                $request->session()->getId()
            )
        );
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categoria_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'bloqueo_registro' => $this->service->renovarBloqueoRegistro(
                $request->user(),
                (int) $validated['categoria_id'],
                $request->session()->getId()
            ),
        ]);
    }

    public function liberarBloqueo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categoria_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->liberarBloqueoRegistro(
            $request->user(),
            (int) $validated['categoria_id'],
            $request->session()->getId()
        );

        return response()->json(['liberado' => true]);
    }

    public function sorteo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ronda_id' => ['required', 'integer', 'min:1'],
            'regenerar' => ['sometimes', 'boolean'],
        ]);

        return response()->json(
            $this->service->generarSorteo(
                $request->user(),
                (int) $validated['ronda_id'],
                (bool) ($validated['regenerar'] ?? false),
                $request->session()->getId()
            )
        );
    }

    public function excluirParticipanteSorteo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ronda_id' => ['required', 'integer', 'min:1'],
            'inscripcion_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->service->excluirParticipanteDelSorteo(
                $request->user(),
                (int) $validated['ronda_id'],
                (int) $validated['inscripcion_id'],
                $request->session()->getId()
            )
        );
    }

    public function guardar(GuardarEvaluacionRequest $request): JsonResponse
    {
        try {
            $payload = $this->service->guardarEvaluacion(
                $request->user(),
                $request->validated(),
                $request->session()->getId()
            );

            return response()->json($payload);
        } catch (EvaluacionConcurrencyException $exception) {
            $resultado = $exception->resultado();

            return response()->json([
                'message' => $exception->getMessage(),
                'conflict' => true,
                'resultado_actual' => [
                    'id' => (int) $resultado->id,
                    'version' => (int) $resultado->version,
                    'estado' => (string) $resultado->estado,
                    'updated_at' => optional($resultado->updated_at)?->toIso8601String(),
                ],
            ], 409);
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function terminarEncuentro(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ronda_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['required', 'integer', 'min:1'],
            'payload' => ['nullable', 'array'],
        ]);

        return response()->json(
            $this->service->terminarEncuentro(
                $request->user(),
                (int) $validated['ronda_id'],
                (int) $validated['equipo_id'],
                $validated['payload'] ?? [],
                $request->session()->getId()
            )
        );
    }
}
