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
            $request->integer('ronda_id') ?: null
        );

        return response()->json($contexto);
    }

    public function formulario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ronda_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->service->construirFormulario(
                $request->user(),
                (int) $validated['ronda_id'],
                (int) $validated['equipo_id']
            )
        );
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
                (bool) ($validated['regenerar'] ?? false)
            )
        );
    }

    public function guardar(GuardarEvaluacionRequest $request): JsonResponse
    {
        try {
            $payload = $this->service->guardarEvaluacion($request->user(), $request->validated());

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
}
