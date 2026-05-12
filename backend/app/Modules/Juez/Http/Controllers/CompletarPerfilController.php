<?php

namespace App\Modules\Juez\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Juez\Requests\UpdateJuezProfilePhotoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompletarPerfilController extends Controller
{
    public function edit(): Response
    {
        $user = Auth::user();

        return Inertia::render('Juez/CompletarPerfil', [
            'user' => [
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'photo_path' => $user->photo_path,
            ],
        ]);
    }

    public function update(UpdateJuezProfilePhotoRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $path = $request->file('photo')->store('jueces/perfiles', 'public');

        $user->photo_path = $path;
        $user->save();

        return redirect()->route('juez.dashboard')->with([
            'success' => 'Foto de perfil actualizada correctamente.',
        ]);
    }
}