<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request)
    {
        $data = $request->validated();

        // Normalizar nombre e institución a mayúsculas en el servidor
        $name = mb_strtoupper($data['name'], 'UTF-8');
        $institucion = mb_strtoupper($data['institucion'], 'UTF-8');

        // Rol por defecto (ajusta el ID según tu tabla de roles)
        $defaultRoleId = 3; // ej: 3 = "participante"

        // Crear usuario
        $user = User::create([
            'name'        => $name,
            'email'       => $data['email'],
            'password'    => $data['password'],   // se hashea solo por el cast 'hashed'
            'role_id'     => $defaultRoleId,
            'telefono'    => $data['telefono'],   // viene como +5939XXXXXXX
            'institucion' => $institucion,
        ]);

        // Marcar email como verificado (para efectos del proyecto)
        $user->email_verified_at = now();
        $user->save();




        return redirect()
            ->route('login')
            ->with('success', 'Tu cuenta se creó correctamente. Ahora puedes iniciar sesión.');

    }


    
}
