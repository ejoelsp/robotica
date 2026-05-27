<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Http\Middleware\EnsureRole;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Publico\LiveResultadosController;
use App\Http\Controllers\Admin\ControlAccesoController;
use App\Modules\Admin\Competencias\Http\Controllers\CompetenciaController;
use App\Modules\Admin\Competencias\Http\Controllers\ComiteOrganizadorController;
use App\Modules\Admin\Categorias\Http\Controllers\CategoriaController;
use App\Modules\Admin\Inscripciones\Http\Controllers\AdminInscripcionController;
use App\Modules\Admin\AsignacionJueces\Http\Controllers\JuezController;
use App\Modules\Admin\AsignacionJueces\Http\Controllers\AsignacionJuezController;
use App\Modules\Admin\Notificaciones\Http\Controllers\NotificacionController;
use App\Modules\Admin\Resultados\Http\Controllers\ResultadoController as AdminResultadoController;
use App\Modules\Admin\Reportes\Http\Controllers\ReporteActaController as AdminReporteActaController;
use App\Modules\Admin\Certificados\Http\Controllers\CertificadoController as AdminCertificadoController;
use App\Modules\Admin\AnalisisHistorico\Http\Controllers\AnalisisHistoricoController as AdminAnalisisHistoricoController;
use App\Modules\Publico\Resultados\Http\Controllers\ResultadoPublicoController;
use App\Http\Controllers\Auth\ActivacionCuentaController;
use App\Modules\Juez\Http\Controllers\CompletarPerfilController;
use App\Modules\Juez\Http\Controllers\EvaluacionController as JuezEvaluacionController;


use App\Mail\PruebaCorreo;
use Illuminate\Support\Facades\Mail;
use App\Services\BrevoMailService;



use App\Modules\Competidor\Inscripciones\Http\Controllers\InscripcionController;
use App\Modules\Competidor\Resultados\Http\Controllers\ResultadoController as CompetidorResultadoController;
use App\Modules\Competidor\Reclamos\Http\Controllers\ReclamoController as CompetidorReclamoController;
use App\Modules\Competidor\Certificados\Http\Controllers\CertificadoController as CompetidorCertificadoController;



use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


// Home
Route::get('/', fn () => Inertia::render('Home'));
Route::get('/competencias', function () {
    $competencias = DB::table('catalogo.competencias')
        ->select([
            'id',
            'nombre',
            'descripcion',
            'fecha_inicio',
            'fecha_fin',
            'enlace_evento',
            'tipo_competencia',
            'imagen_url',
            'estado',
        ])
        ->orderByDesc('estado')
        ->orderByDesc('fecha_inicio')
        ->orderByDesc('id')
        ->get()
        ->map(fn ($competencia) => [
            'id' => (int) $competencia->id,
            'nombre' => (string) $competencia->nombre,
            'descripcion' => $competencia->descripcion,
            'fecha_inicio' => $competencia->fecha_inicio,
            'fecha_fin' => $competencia->fecha_fin,
            'enlace_evento' => $competencia->enlace_evento,
            'tipo_competencia' => $competencia->tipo_competencia,
            'imagen_url' => $competencia->imagen_url ? Storage::url($competencia->imagen_url) : null,
            'estado' => (bool) $competencia->estado,
        ]);

    return Inertia::render('Publico/Competencias', [
        'competencias' => $competencias,
    ]);
})->name('public.competencias');

Route::get('/contacto', function () {
    $competencia = DB::table('catalogo.competencias')
        ->where('estado', true)
        ->orderByDesc('id')
        ->first(['id', 'nombre']);

    if (! $competencia) {
        $competencia = DB::table('catalogo.competencias')
            ->orderByDesc('id')
            ->first(['id', 'nombre']);
    }

    $integrantes = collect();

    if ($competencia) {
        $integrantes = DB::table('catalogo.comite_organizadores')
            ->where('competencia_id', $competencia->id)
            ->where('estado', true)
            ->orderBy('orden')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get()
            ->map(fn ($integrante) => [
                'id' => (int) $integrante->id,
                'nombres' => (string) $integrante->nombres,
                'apellidos' => (string) $integrante->apellidos,
                'correo' => $integrante->correo,
                'rol_comite' => (string) $integrante->rol_comite,
                'foto_url' => $integrante->foto ? Storage::url($integrante->foto) : null,
                'orden' => (int) $integrante->orden,
            ]);
    }

    return Inertia::render('Publico/Contacto', [
        'competencia' => $competencia ? [
            'id' => (int) $competencia->id,
            'nombre' => (string) $competencia->nombre,
        ] : null,
        'integrantes' => $integrantes,
    ]);
})->name('public.contacto');

Route::get('/resultados', [ResultadoPublicoController::class, 'index'])
    ->name('public.resultados');
Route::get('/sorteos', [ResultadoPublicoController::class, 'sorteos'])
    ->name('public.sorteos');
Route::get('/resultados-en-vivo', [ResultadoPublicoController::class, 'enVivo'])
    ->name('public.resultados.live.page');
Route::get('/resultados/en-vivo', [LiveResultadosController::class, 'snapshot'])
    ->name('public.resultados.live');
Route::get('/resultados/en-vivo/stream', [LiveResultadosController::class, 'stream'])
    ->name('public.resultados.live.stream');

// Pantallas de autenticación
Route::get('/login', function () {
    return Inertia::render('Auth/Login', [
        'registerSuccess' => session('registerSuccess'),
        'loginError'      => session('loginError'),
    ]);
})->name('login');



// Registro
Route::get('/register', fn () => Inertia::render('Auth/Register'))
    ->name('register');

Route::post('/register', [RegisterController::class, 'store'])
    ->name('register.store');

// Login
Route::post('/login', [LoginController::class, 'store'])
    ->name('login.store');

Route::get('/recuperar-contrasena', [PasswordResetController::class, 'request'])
    ->name('password.request');

Route::post('/recuperar-contrasena', [PasswordResetController::class, 'sendCode'])
    ->name('password.email');

Route::get('/restablecer-contrasena', [PasswordResetController::class, 'resetForm'])
    ->name('password.reset.show');

Route::post('/restablecer-contrasena/verificar-codigo', [PasswordResetController::class, 'verifyCode'])
    ->name('password.verify-code');

Route::post('/restablecer-contrasena', [PasswordResetController::class, 'updatePassword'])
    ->name('password.update');

// Activación de cuenta de juez
Route::get('/activar-cuenta', [ActivacionCuentaController::class, 'show'])
    ->name('activation.show');

Route::post('/activar-cuenta', [ActivacionCuentaController::class, 'store'])
    ->name('activation.store');


// ==============================
// RUTAS PROTEGIDAS (auth)
// ==============================
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', fn () => redirect()->route('competidor.mis-inscripciones'))
        ->name('dashboard.redirect');

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');


    // ==============================
    // RUTAS ADMIN (EnsureRole:admin)
    // ==============================
    Route::prefix('admin')
        ->name('admin.')
        ->middleware(EnsureRole::class . ':admin')
        ->group(function () {

            Route::get('/dashboard', fn () => redirect()->route('admin.competencias.index'))
                ->name('dashboard.redirect');

            // Perfil admin
            Route::get('/profile', function (Request $request) {
                return Inertia::render('Admin/Profile', [
                    'user' => $request->user(),
                ]);
            })->name('profile');


            // ===============
            // COMPETENCIAS 
            // ===============
            Route::get('/competencias', [CompetenciaController::class, 'index'])
                ->name('competencias.index');

            Route::post('/competencias', [CompetenciaController::class, 'store'])
                ->name('competencias.store');

            Route::put('/competencias/{id}', [CompetenciaController::class, 'update'])
                ->name('competencias.update');

            Route::delete('/competencias/{id}', [CompetenciaController::class, 'destroy'])
                ->name('competencias.destroy');

            Route::patch('/competencias/{id}/toggle', [CompetenciaController::class, 'toggle'])
                ->name('competencias.toggle');

            Route::get('/competencias/{competencia}/comite', [ComiteOrganizadorController::class, 'index'])
                ->name('competencias.comite.index');

            Route::post('/competencias/{competencia}/comite', [ComiteOrganizadorController::class, 'store'])
                ->name('competencias.comite.store');

            Route::put('/competencias/{competencia}/comite/{integrante}', [ComiteOrganizadorController::class, 'update'])
                ->name('competencias.comite.update');

            Route::delete('/competencias/{competencia}/comite/{integrante}', [ComiteOrganizadorController::class, 'destroy'])
                ->name('competencias.comite.destroy');

            Route::patch('/competencias/{competencia}/comite/{integrante}/toggle', [ComiteOrganizadorController::class, 'toggle'])
                ->name('competencias.comite.toggle');

            // ===============
            // CATEGORÍAS
            // ===============
            Route::get('/categorias', [CategoriaController::class, 'index'])
                ->name('categorias.index');

            Route::post('/categorias', [CategoriaController::class, 'store'])
                ->name('categorias.store');

            Route::put('/categorias/{id}', [CategoriaController::class, 'update'])
                ->name('categorias.update'); 

            Route::put('/categorias/{categoria}/formato-registro', [CategoriaController::class, 'updateFormato'])
                ->name('categorias.formato.update');

            Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])
                ->name('categorias.destroy');

            Route::get('/categorias/{categoria}/rondas', [CategoriaController::class, 'rondas'])
                ->name('categorias.rondas.index');

            Route::post('/categorias/{categoria}/rondas', [CategoriaController::class, 'storeRonda'])
                ->name('categorias.rondas.store');

            Route::put('/categorias/{categoria}/rondas/{ronda}', [CategoriaController::class, 'updateRonda'])
                ->name('categorias.rondas.update');

            Route::post('/categorias/{categoria}/rondas/{ronda}/clasificar', [CategoriaController::class, 'clasificarRonda'])
                ->name('categorias.rondas.clasificar');

            Route::delete('/categorias/{categoria}/rondas/{ronda}', [CategoriaController::class, 'destroyRonda'])
                ->name('categorias.rondas.destroy');

            // INSCRIPCIONES
            Route::get('/inscripciones', [AdminInscripcionController::class, 'index'])
                ->name('inscripciones.index');

            Route::put('/inscripciones', [AdminInscripcionController::class, 'guardarConfiguracionPago'])
                ->name('inscripciones.configuracion-pago.fallback');

            Route::put('/inscripciones/configuracion-pago', [AdminInscripcionController::class, 'guardarConfiguracionPago'])
                ->name('inscripciones.configuracion-pago.update');

            Route::post('/inscripciones/{id}/aprobar', [AdminInscripcionController::class, 'approve'])
                ->name('inscripciones.approve');

            Route::post('/inscripciones/{id}/rechazar', [AdminInscripcionController::class, 'reject'])
                ->name('inscripciones.reject');

            Route::post('/inscripciones/{id}/corregir-decision', [AdminInscripcionController::class, 'corregirDecision'])
                ->name('inscripciones.corregirDecision');

            Route::get('/inscripciones/export', [AdminInscripcionController::class, 'export'])
                ->name('inscripciones.export');

            // ===============
            // AsignacionJueces
            // ===============

            Route::post('/jueces', [JuezController::class, 'store'])
                ->name('jueces.store');

            Route::post('/jueces/{juez}/reenviar-activacion', [JuezController::class, 'resendActivation'])
                ->name('jueces.activation.resend');

            Route::patch('/jueces/{juez}/estado', [JuezController::class, 'updateEstado'])
                ->name('jueces.estado.update');
 
            Route::get('/asignacion-jueces', [AsignacionJuezController::class, 'index'])
                ->name('asignacion_jueces.index');

            Route::put('/asignacion-jueces/configuracion', [AsignacionJuezController::class, 'updateConfig'])
                ->name('asignacion_jueces.configuracion.update');

            Route::post('/asignaciones-jueces', [AsignacionJuezController::class, 'store'])
                ->name('asignaciones-jueces.store');

            Route::put('/asignaciones-jueces/{id}', [AsignacionJuezController::class, 'update'])
                ->name('asignaciones-jueces.update');

            Route::put('/asignacion-jueces', [AsignacionJuezController::class, 'update'])
                ->name('asignacion_jueces.update');



            //PARA CORREO DE PRUEBA
            Route::get('/test-correo', function () {
                Mail::to('ericksanchez0708@gmail.com')->send(new PruebaCorreo());

                return 'Correo enviado';
            });

            
            Route::get('/test-correo-api', function (BrevoMailService $brevoMailService) {
                $brevoMailService->sendEmail(
                    'ericksanchez0708@gmail.com',
                    'Prueba',
                    'Prueba API Brevo - Club de Robótica ESPOCH',
                    '<h2>Correo de prueba</h2><p>Brevo API funciona correctamente.</p>'
                );

                return 'Correo enviado por API';
            });

            



            // Notificaciones
            Route::get('/notificaciones', [NotificacionController::class, 'adminIndex'])
                ->name('notificaciones');

            Route::get('/notificaciones/contador', [NotificacionController::class, 'contadorAdmin'])
                ->name('notificaciones.contador');

            Route::post('/notificaciones', [NotificacionController::class, 'store'])
                ->name('notificaciones.store');
        

            
            //Resultados 
            Route::get('/resultados', [AdminResultadoController::class, 'index'])
                ->name('resultados');

            Route::get('/resultados/evaluaciones', [AdminResultadoController::class, 'evaluaciones'])
                ->name('resultados.evaluaciones');

            Route::patch('/resultados/evaluaciones/{resultado}', [AdminResultadoController::class, 'actualizarEvaluacion'])
                ->name('resultados.evaluaciones.update');

            Route::get('/resultados/consolidado', [AdminResultadoController::class, 'consolidado'])
                ->name('resultados.consolidado');

            Route::post('/resultados/consolidar', [AdminResultadoController::class, 'consolidar'])
                ->name('resultados.consolidar');

            Route::post('/resultados/publicar', [AdminResultadoController::class, 'publicar'])
                ->name('resultados.publicar');

            Route::get('/resultados/en-vivo', [LiveResultadosController::class, 'snapshotAdmin'])
                ->name('resultados.live');

            Route::get('/resultados/en-vivo/stream', [LiveResultadosController::class, 'streamAdmin'])
                ->name('resultados.live.stream');

            Route::get('/reportes', [AdminReporteActaController::class, 'index'])
                ->name('reportes.index');

            Route::get('/reportes/listado', [AdminReporteActaController::class, 'listado'])
                ->name('reportes.listado');

            Route::post('/reportes', [AdminReporteActaController::class, 'store'])
                ->name('reportes.store');

            Route::post('/reportes/{acta}/firmado', [AdminReporteActaController::class, 'subirFirmado'])
                ->name('reportes.firmado.store');

            Route::get('/reportes/{acta}/descargar', [AdminReporteActaController::class, 'downloadGenerado'])
                ->name('reportes.download.generado');

            Route::get('/reportes/{acta}/descargar-firmado', [AdminReporteActaController::class, 'downloadFirmado'])
                ->name('reportes.download.firmado');

            Route::get('/certificados', [AdminCertificadoController::class, 'index'])
                ->name('certificados.index');

            Route::post('/certificados', [AdminCertificadoController::class, 'store'])
                ->name('certificados.store');

            Route::patch('/certificados/{plantilla}', [AdminCertificadoController::class, 'update'])
                ->name('certificados.update');

            Route::get('/certificados/{plantilla}/preview', [AdminCertificadoController::class, 'preview'])
                ->name('certificados.preview');

            Route::delete('/certificados/{plantilla}', [AdminCertificadoController::class, 'destroy'])
                ->name('certificados.destroy');

            
            // Análisis Histórico
            Route::get('/analisis-historico', [AdminAnalisisHistoricoController::class, 'index'])
                ->name('analisis_historico.index');

            Route::post('/analisis-historico/generar', [AdminAnalisisHistoricoController::class, 'generar'])
                ->name('analisis_historico.generar');


            // Control de Acceso
            Route::get('/control-acceso', [ControlAccesoController::class, 'index'])
                ->name('control_acceso.index');

            Route::post('/control-acceso/usuarios', [ControlAccesoController::class, 'store'])
                ->name('control_acceso.usuarios.store');

            Route::patch('/control-acceso/usuarios/{usuario}/estado', [ControlAccesoController::class, 'updateEstado'])
                ->name('control_acceso.usuarios.estado');
        
    });
  
    
    // ==============================
    // RUTAS JUEZ
    // ==============================

    Route::middleware(['auth', 'juez.profile.completed'])->group(function () {
        $buildJuezContext = function () {
            $user = auth()->user();

            return [
                'juez' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'role_id' => $user->role_id,
                    'photo_path' => $user->photo_path,
                    'photo_url' => $user->photo_path ? Storage::url($user->photo_path) : null,
                ],
                'competenciaActual' => DB::table('catalogo.competencias')
                    ->where('estado', true)
                    ->select(['id', 'nombre'])
                    ->first(),
            ];
        };

        Route::get('/juez/dashboard', function () use ($buildJuezContext) {
            return Inertia::render('Juez/Dashboard', $buildJuezContext());
        })->name('juez.dashboard');

        Route::get('/juez/evaluaciones', function () use ($buildJuezContext) {
            return Inertia::render('Juez/Dashboard', $buildJuezContext());
        })->name('juez.evaluaciones');

        Route::get('/juez/evaluaciones/contexto', [JuezEvaluacionController::class, 'contexto'])
            ->name('juez.evaluaciones.contexto');

        Route::get('/juez/evaluaciones/formulario', [JuezEvaluacionController::class, 'formulario'])
            ->name('juez.evaluaciones.formulario');

        Route::post('/juez/evaluaciones/bloqueo/heartbeat', [JuezEvaluacionController::class, 'heartbeat'])
            ->name('juez.evaluaciones.bloqueo.heartbeat');

        Route::post('/juez/evaluaciones/bloqueo/liberar', [JuezEvaluacionController::class, 'liberarBloqueo'])
            ->name('juez.evaluaciones.bloqueo.liberar');

        Route::post('/juez/evaluaciones/sorteo', [JuezEvaluacionController::class, 'sorteo'])
            ->name('juez.evaluaciones.sorteo');

        Route::post('/juez/evaluaciones', [JuezEvaluacionController::class, 'guardar'])
            ->name('juez.evaluaciones.guardar');

        Route::post('/juez/evaluaciones/terminar-encuentro', [JuezEvaluacionController::class, 'terminarEncuentro'])
            ->name('juez.evaluaciones.terminar-encuentro');

       

        Route::get('/juez/profile', function () use ($buildJuezContext) {
            return Inertia::render('Juez/Profile', $buildJuezContext());
        })->name('juez.profile');
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('/juez/completar-perfil', [CompletarPerfilController::class, 'edit'])
            ->name('juez.completar-perfil');

        Route::post('/juez/completar-perfil', [CompletarPerfilController::class, 'update'])
            ->name('juez.completar-perfil.update');
    });

    

    // ==============================
    // RUTAS COMPETIDOR
    // ==============================

    // Mis Inscripciones del competidor
    Route::get('/competidor/mis-inscripciones', [InscripcionController::class, 'index'])
        ->name('competidor.mis-inscripciones');

    Route::get('/competidor/notificaciones', [NotificacionController::class, 'competidorIndex'])
        ->name('competidor.notificaciones');

    Route::get('/competidor/resultados', [CompetidorResultadoController::class, 'index'])
        ->name('competidor.resultados');

    Route::get('/competidor/certificados', [CompetidorCertificadoController::class, 'index'])
        ->name('competidor.certificados');

    Route::get('/competidor/certificados/{integrante}/descargar', [CompetidorCertificadoController::class, 'download'])
        ->name('competidor.certificados.download');

    Route::get('/competidor/reclamos', [CompetidorReclamoController::class, 'index'])
        ->name('competidor.reclamos');

    Route::get('/competidor/reclamos/preview', [CompetidorReclamoController::class, 'preview'])
        ->name('competidor.reclamos.preview');

    Route::post('/competidor/reclamos', [CompetidorReclamoController::class, 'store'])
        ->name('competidor.reclamos.store');

    Route::get('/competidor/reclamos/{incidencia}/formato', [CompetidorReclamoController::class, 'formato'])
        ->name('competidor.reclamos.formato');

    Route::patch('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'markAsRead'])
        ->name('notificaciones.leer');

    // Registrar inscripción del competidor
    Route::post('/competidor/inscripciones', [InscripcionController::class, 'store'])
        ->name('competidor.inscripciones.store');

    Route::put('/competidor/inscripciones/{id}', [InscripcionController::class, 'update'])
        ->name('competidor.inscripciones.update');

    Route::post('/competidor/inscripciones/{id}/editar', [InscripcionController::class, 'update'])
        ->name('competidor.inscripciones.update.post');

    Route::delete('/competidor/inscripciones/{id}', [InscripcionController::class, 'destroy'])
        ->name('competidor.inscripciones.destroy');

    Route::match(['post', 'delete'], '/competidor/inscripciones/{id}/eliminar', [InscripcionController::class, 'destroy'])
        ->name('competidor.inscripciones.destroy.post');

    Route::post('/competidor/inscripciones/comprobante', [InscripcionController::class, 'storeComprobante'])
    ->name('competidor.inscripciones.comprobante.store');

    
});
