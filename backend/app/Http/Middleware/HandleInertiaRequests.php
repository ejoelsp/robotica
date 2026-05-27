<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'errors' => fn () => (object) collect(
                $request->session()->get('errors')?->getBag('default')->messages() ?? []
            )->map(fn (array $messages) => $messages[0] ?? null)->toArray(),

            'auth.user' => fn () => $request->user()
                ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'last_name' => $request->user()->last_name,
                    'email' => $request->user()->email,
                    'telefono' => $request->user()->telefono,
                    'role_id' => $request->user()->role_id,
                    'photo_path' => $request->user()->photo_path,
                    'photo_url' => $request->user()->photo_path
                        ? Storage::url($request->user()->photo_path)
                        : null,
                ]
                : null,

            'competenciaActual' => fn () => $request->session()->get('competenciaActual')
                ?? DB::table('catalogo.competencias')
                    ->where('estado', true)
                    ->select([
                        'id',
                        'nombre',
                    ])
                    ->first(),

            'loginError' => fn () => $request->session()->get('loginError'),

            'adminNotificaciones' => fn () => $request->user()
                ? [
                    'recibidas' => DB::table('comunicacion.notificaciones')
                        ->where('user_id', $request->user()->id)
                        ->count(),
                    'noLeidas' => DB::table('comunicacion.notificaciones')
                        ->where('user_id', $request->user()->id)
                        ->where('leido', false)
                        ->count(),
                ]
                : [
                    'recibidas' => 0,
                    'noLeidas' => 0,
                ],

            'notificacionesNoLeidas' => fn () => $request->user()
                ? DB::table('comunicacion.notificaciones')
                    ->where('user_id', $request->user()->id)
                    ->where('leido', false)
                    ->count()
                : 0,

            'flash.success' => fn () => $request->session()->get('success'),
            'flash.error' => fn () => $request->session()->get('error'),
        ]);
    }
}
