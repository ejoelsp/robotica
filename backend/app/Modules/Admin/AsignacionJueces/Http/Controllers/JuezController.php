<?php

namespace App\Modules\Admin\AsignacionJueces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarCorreoActivacionJuezJob;
use App\Models\User;
use App\Modules\Admin\AsignacionJueces\Requests\StoreJuezRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class JuezController extends Controller
{
    public function store(StoreJuezRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $rolJuezId = DB::table('seguridad.roles')
            ->where('nombre', 'juez')
            ->value('id');

        if (! $rolJuezId) {
            return $this->validationResponse($request, [
                'general' => 'No existe el rol juez en seguridad.roles.',
            ]);
        }

        try {
            $juez = DB::transaction(function () use ($data, $rolJuezId) {
                $juez = new User();
                $juez->name = trim((string) $data['name']);
                $juez->last_name = trim((string) $data['last_name']);
                $juez->email = mb_strtolower(trim((string) $data['email']));
                $juez->telefono = filled($data['telefono'] ?? null)
                    ? trim((string) $data['telefono'])
                    : null;
                $juez->password = Hash::make(Str::random(32));
                $juez->role_id = $rolJuezId;
                $juez->must_change_password = true;
                $juez->email_verified_at = null;
                $juez->save();

                return $juez;
            });

            $activationUrl = $this->generarEnlaceActivacion($juez);
            EnviarCorreoActivacionJuezJob::dispatch((int) $juez->id, $activationUrl)->afterCommit();
        } catch (QueryException $exception) {
            Log::warning('Conflicto al crear juez desde Asignacion de Jueces.', [
                'email' => $data['email'] ?? null,
                'sql_state' => $exception->errorInfo[0] ?? null,
                'message' => $exception->getMessage(),
            ]);

            $message = str_contains(mb_strtolower($exception->getMessage()), 'email')
                ? ['email' => 'Ya existe un usuario con ese correo.']
                : ['general' => 'No se pudo crear el juez. Revisa los datos e intenta nuevamente.'];

            return $this->validationResponse($request, $message);
        } catch (Throwable $exception) {
            Log::error('No se pudo crear el juez desde Asignacion de Jueces.', [
                'email' => $data['email'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return $this->validationResponse($request, [
                'general' => 'No se pudo crear el juez. Revisa los datos e intenta nuevamente.',
            ]);
        }

        $payload = [
            'success' => 'Juez creado correctamente y correo de activación encolado.',
            'created_juez_id' => $juez->id,
            'activation_url' => app()->isLocal() ? $activationUrl : null,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $payload['success'],
                'created_juez_id' => $payload['created_juez_id'],
                'activation_url' => $payload['activation_url'],
            ], 201);
        }

        return back()->with($payload);
    }

    public function resendActivation(Request $request, int $juezId): RedirectResponse|JsonResponse
    {
        $rolJuezId = DB::table('seguridad.roles')
            ->where('nombre', 'juez')
            ->value('id');

        $juez = User::query()
            ->where('id', $juezId)
            ->where('role_id', $rolJuezId)
            ->firstOrFail();

        if ($juez->email_verified_at !== null && ! $juez->must_change_password) {
            return $this->validationResponse($request, [
                'general' => 'La cuenta del juez ya esta activada.',
            ]);
        }

        try {
            $activationUrl = $this->generarEnlaceActivacion($juez);
            EnviarCorreoActivacionJuezJob::dispatch((int) $juez->id, $activationUrl)->afterCommit();
        } catch (Throwable $exception) {
            Log::error('No se pudo reenviar enlace de activacion de juez.', [
                'juez_id' => $juez->id,
                'email' => $juez->email,
                'message' => $exception->getMessage(),
            ]);

            return $this->validationResponse($request, [
                'general' => 'No se pudo reenviar el enlace de activación.',
            ]);
        }

        $payload = [
            'success' => 'Enlace de activación encolado correctamente.',
            'activation_url' => app()->isLocal() ? $activationUrl : null,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $payload['success'],
                'activation_url' => $payload['activation_url'],
            ]);
        }

        return back()->with($payload);
    }

    public function updateEstado(Request $request, int $juezId): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'boolean'],
        ]);

        $rolJuezId = DB::table('seguridad.roles')
            ->where('nombre', 'juez')
            ->value('id');

        $updated = User::query()
            ->where('id', $juezId)
            ->where('role_id', $rolJuezId)
            ->update([
                'estado' => (bool) $data['estado'],
                'updated_at' => now(),
            ]);

        $message = (bool) $data['estado']
            ? 'Juez activado correctamente.'
            : 'Juez desactivado correctamente.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $updated ? $message : 'No se encontro el juez seleccionado.',
            ], $updated ? 200 : 404);
        }

        return back()->with($updated ? 'success' : 'error', $updated ? $message : 'No se encontro el juez seleccionado.');
    }

    private function generarEnlaceActivacion(User $juez): string
    {
        DB::table('seguridad.user_activation_tokens')
            ->where('user_id', $juez->id)
            ->delete();

        $token = Str::random(64);

        DB::table('seguridad.user_activation_tokens')->insert([
            'user_id' => $juez->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return route('activation.show', ['token' => $token]);
    }

    private function validationResponse(Request $request, array $messages): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $field = array_key_first($messages);

            return response()->json([
                'message' => $messages[$field],
                'errors' => collect($messages)
                    ->map(fn (string $message) => [$message])
                    ->all(),
            ], 422);
        }

        return back()->withErrors($messages);
    }
}
