<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Http\Middleware\EnsureRole;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Publico\LiveResultadosController;
use App\Modules\Admin\Competencias\Http\Controllers\CompetenciaController;
use App\Modules\Admin\Competencias\Http\Controllers\ComiteOrganizadorController;
use App\Modules\Admin\Categorias\Http\Controllers\CategoriaController;
use App\Modules\Admin\Inscripciones\Http\Controllers\AdminInscripcionController;
use App\Modules\Admin\AsignacionJueces\Http\Controllers\JuezController;
use App\Modules\Admin\AsignacionJueces\Http\Controllers\AsignacionJuezController;
use App\Modules\Admin\Resultados\Http\Controllers\ResultadoController as AdminResultadoController;
use App\Modules\Publico\Resultados\Http\Controllers\ResultadoPublicoController;
use App\Http\Controllers\Auth\ActivacionCuentaController;
use App\Modules\Juez\Http\Controllers\CompletarPerfilController;
use App\Modules\Juez\Http\Controllers\EvaluacionController as JuezEvaluacionController;


use App\Mail\PruebaCorreo;
use Illuminate\Support\Facades\Mail;
use App\Services\BrevoMailService;



use App\Modules\Competidor\Inscripciones\Http\Controllers\InscripcionController;



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
Route::get('/resultados-en-vivo', fn () => redirect()->route('public.resultados'))
    ->name('public.resultados.live.page');
Route::get('/resultados/en-vivo', fn () => abort(404))
    ->name('public.resultados.live');
Route::get('/resultados/en-vivo/stream', fn () => abort(404))
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

// Activación de cuenta de juez
Route::get('/activar-cuenta', [ActivacionCuentaController::class, 'show'])
    ->name('activation.show');

Route::post('/activar-cuenta', [ActivacionCuentaController::class, 'store'])
    ->name('activation.store');


// ==============================
// RUTAS PROTEGIDAS (auth)
// ==============================
Route::middleware('auth')->group(function () {

    // Dashboard del competidor
    Route::get('/dashboard', fn () => Inertia::render('Competidor/Dashboard'))
        ->name('dashboard');

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

            // Dashboard admin
            Route::get('/dashboard', AdminDashboardController::class)
                ->name('dashboard');

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

            Route::delete('/categorias/{categoria}/rondas/{ronda}', [CategoriaController::class, 'destroyRonda'])
                ->name('categorias.rondas.destroy');

            // INSCRIPCIONES
            Route::get('/inscripciones', [AdminInscripcionController::class, 'index'])
                ->name('inscripciones.index');

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

            



            //Notificaciones
            Route::get('/notificaciones', fn () => Inertia::render('Admin/Notificaciones'))
                ->name('notificaciones');
        

            
            //Resultados 
            Route::get('/resultados', [AdminResultadoController::class, 'index'])
                ->name('resultados');

            Route::get('/resultados/evaluaciones', [AdminResultadoController::class, 'evaluaciones'])
                ->name('resultados.evaluaciones');

            Route::patch('/resultados/evaluaciones/{resultado}/estado', [AdminResultadoController::class, 'cambiarEstadoEvaluacion'])
                ->name('resultados.evaluaciones.estado');

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

            
            // Análisis Histórico
            Route::get('/analisis-historico', fn () => Inertia::render('Admin/AnalisisHistorico'))
                ->name('analisis_historico.index');


            // Control de Acceso
            Route::get('/control-acceso', fn () => Inertia::render('Admin/ControldeAcceso'))
                ->name('control_acceso.index');
        
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

        Route::post('/juez/evaluaciones/sorteo', [JuezEvaluacionController::class, 'sorteo'])
            ->name('juez.evaluaciones.sorteo');

        Route::post('/juez/evaluaciones', [JuezEvaluacionController::class, 'guardar'])
            ->name('juez.evaluaciones.guardar');

       

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

    // Registrar inscripción del competidor
    Route::post('/competidor/inscripciones', [InscripcionController::class, 'store'])
        ->name('competidor.inscripciones.store');

    Route::post('/competidor/inscripciones/comprobante', [InscripcionController::class, 'storeComprobante'])
    ->name('competidor.inscripciones.comprobante.store');

    
});
