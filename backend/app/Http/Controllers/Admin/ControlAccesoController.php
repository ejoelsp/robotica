<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarCorreoActivacionCuentaJob;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ControlAccesoController extends Controller
{
    public function index(): Response
    {
        $roles = DB::table('seguridad.roles')
            ->whereIn('nombre', ['admin', 'juez', 'competidor'])
            ->orderBy('id')
            ->get(['id', 'nombre']);

        $roleIds = $roles->pluck('id')->all();
        $activeSessionCutoff = now()
            ->subMinutes((int) config('session.lifetime'))
            ->timestamp;

        $activeSessionsQuery = DB::table('seguridad.sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $activeSessionCutoff);

        $sessionsByUser = (clone $activeSessionsQuery)
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $usuarios = User::query()
            ->leftJoin('seguridad.roles as roles', 'roles.id', '=', 'seguridad.users.role_id')
            ->whereIn('seguridad.users.role_id', $roleIds)
            ->orderBy('roles.id')
            ->orderBy('seguridad.users.name')
            ->get([
                'seguridad.users.id',
                'seguridad.users.name',
                'seguridad.users.last_name',
                'seguridad.users.email',
                'seguridad.users.telefono',
                'seguridad.users.role_id',
                'seguridad.users.estado',
                'seguridad.users.must_change_password',
                'seguridad.users.email_verified_at',
                'seguridad.users.created_at',
                'seguridad.users.updated_at',
                'roles.nombre as rol',
            ])
            ->map(fn ($usuario) => [
                'id' => (int) $usuario->id,
                'name' => (string) $usuario->name,
                'last_name' => (string) $usuario->last_name,
                'nombre_completo' => trim((string) $usuario->name . ' ' . (string) $usuario->last_name),
                'email' => (string) $usuario->email,
                'telefono' => $usuario->telefono,
                'role_id' => (int) $usuario->role_id,
                'rol' => (string) $usuario->rol,
                'rol_label' => $this->roleLabel((string) $usuario->rol),
                'estado' => (bool) $usuario->estado,
                'must_change_password' => (bool) $usuario->must_change_password,
                'email_verified_at' => $usuario->email_verified_at ? (string) $usuario->email_verified_at : null,
                'created_at' => $usuario->created_at ? (string) $usuario->created_at : null,
                'updated_at' => $usuario->updated_at ? (string) $usuario->updated_at : null,
                'sesiones_activas' => (int) ($sessionsByUser[$usuario->id] ?? 0),
            ]);

        $auditorias = DB::table('auditoria.auditorias as auditorias')
            ->leftJoin('seguridad.users as users', 'users.id', '=', 'auditorias.user_id')
            ->orderByDesc('auditorias.ocurrio_en')
            ->limit(100)
            ->get([
                'auditorias.id',
                'auditorias.tabla',
                'auditorias.accion',
                'auditorias.modulo',
                'auditorias.descripcion',
                'auditorias.estado',
                'auditorias.user_id',
                'auditorias.ip_address',
                'auditorias.user_agent',
                'auditorias.payload',
                'auditorias.ocurrio_en',
                'users.name',
                'users.last_name',
                'users.email',
            ])
            ->map(fn ($auditoria) => [
                'id' => (int) $auditoria->id,
                'tabla' => $auditoria->tabla,
                'accion' => (string) $auditoria->accion,
                'modulo' => $auditoria->modulo,
                'descripcion' => $auditoria->descripcion,
                'estado' => (string) ($auditoria->estado ?? 'exitoso'),
                'user_id' => $auditoria->user_id ? (int) $auditoria->user_id : null,
                'usuario' => $auditoria->user_id
                    ? trim((string) $auditoria->name . ' ' . (string) $auditoria->last_name)
                    : 'Sistema',
                'email' => $auditoria->email,
                'ip_address' => $auditoria->ip_address,
                'user_agent' => $auditoria->user_agent,
                'payload' => $auditoria->payload,
                'ocurrio_en' => $auditoria->ocurrio_en ? (string) $auditoria->ocurrio_en : null,
            ]);

        return Inertia::render('Admin/ControldeAcceso', [
            'usuarios' => $usuarios,
            'roles' => $roles->map(fn ($rol) => [
                'id' => (int) $rol->id,
                'nombre' => (string) $rol->nombre,
                'label' => $this->roleLabel((string) $rol->nombre),
            ]),
            'auditorias' => $auditorias,
            'seguridad' => [
                'intentos_maximos' => 5,
                'bloqueo_minutos' => 5,
                'session_lifetime_minutos' => (int) config('session.lifetime'),
                'sesiones_activas' => (int) (clone $activeSessionsQuery)->count(),
                'driver_sesion' => (string) config('session.driver'),
                'autenticacion' => 'Correo y contraseña',
                'segundo_factor' => 'No habilitado',
            ],
        ]);
    }

    public function store(Request $request, AuditoriaService $auditoria): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', 'regex:/^[\pL\s]+$/u'],
            'last_name' => ['required', 'string', 'max:30', 'regex:/^[\pL\s]+$/u'],
            'email' => ['required', 'string', 'email', 'max:100', Rule::unique(User::class, 'email')],
            'telefono' => ['required', 'string', 'regex:/^\+\d{1,14}$/', 'max:15'],
            'rol' => ['required', Rule::in(['admin', 'juez'])],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras y espacios.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.regex' => 'El teléfono debe iniciar con + y contener solo números.',
            'telefono.max' => 'El teléfono no puede superar 15 caracteres incluido el signo +.',
            'rol.in' => 'Solo se pueden crear usuarios administradores o jueces desde este módulo.',
        ]);

        $roleId = DB::table('seguridad.roles')
            ->where('nombre', $data['rol'])
            ->value('id');

        if (! $roleId) {
            return back()->withErrors([
                'rol' => 'El rol seleccionado no existe en la base de datos.',
            ]);
        }

        $usuario = DB::transaction(function () use ($data, $roleId) {
            $usuario = User::query()->create([
                'name' => trim((string) $data['name']),
                'last_name' => trim((string) $data['last_name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'telefono' => filled($data['telefono'] ?? null) ? trim((string) $data['telefono']) : null,
                'password' => Hash::make(Str::random(32)),
                'role_id' => $roleId,
                'must_change_password' => true,
                'estado' => true,
                'email_verified_at' => null,
            ]);

            return $usuario;
        });

        $activationUrl = $this->generarEnlaceActivacion($usuario);
        EnviarCorreoActivacionCuentaJob::dispatch((int) $usuario->id, $activationUrl)->afterCommit();

        $auditoria->registrar(
            accion: 'crear_usuario',
            tabla: 'seguridad.users',
            modulo: 'control_acceso',
            descripcion: 'Creación de usuario desde Control de Acceso.',
            payload: [
                'usuario_id' => $usuario->id,
                'rol' => $data['rol'],
                'email' => $usuario->email,
            ],
            request: $request
        );

        return redirect()
            ->route('admin.control_acceso.index')
            ->with('success', 'Usuario creado correctamente. Se envió el enlace de activación.')
            ->setStatusCode(303);
    }

    public function updateEstado(Request $request, User $usuario, AuditoriaService $auditoria): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'boolean'],
        ]);

        if ((int) $request->user()->id === (int) $usuario->id && ! (bool) $data['estado']) {
            return back()->withErrors([
                'estado' => 'No puedes desactivar tu propia cuenta.',
            ]);
        }

        $rol = DB::table('seguridad.roles')->where('id', $usuario->role_id)->value('nombre');

        if (! in_array($rol, ['admin', 'juez', 'competidor'], true)) {
            return back()->withErrors([
                'estado' => 'Este usuario no pertenece a un rol gestionable desde Control de Acceso.',
            ]);
        }

        $estadoAnterior = (bool) $usuario->estado;
        $usuario->forceFill(['estado' => (bool) $data['estado']])->save();

        $auditoria->registrar(
            accion: (bool) $data['estado'] ? 'activar_usuario' : 'desactivar_usuario',
            tabla: 'seguridad.users',
            modulo: 'control_acceso',
            descripcion: (bool) $data['estado'] ? 'Activación de usuario.' : 'Desactivación de usuario.',
            payload: [
                'usuario_id' => $usuario->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => (bool) $data['estado'],
            ],
            request: $request
        );

        return redirect()
            ->route('admin.control_acceso.index')
            ->with('success', (bool) $data['estado'] ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.')
            ->setStatusCode(303);
    }

    private function generarEnlaceActivacion(User $usuario): string
    {
        DB::table('seguridad.user_activation_tokens')
            ->where('user_id', $usuario->id)
            ->delete();

        $token = Str::random(64);

        DB::table('seguridad.user_activation_tokens')->insert([
            'user_id' => $usuario->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return route('activation.show', ['token' => $token]);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrador',
            'juez' => 'Juez',
            'competidor' => 'Competidor',
            default => Str::headline($role),
        };
    }
}
