<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    //Apunta a la tabla real con esquema
    protected $table = 'seguridad.users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'documento',
        'telefono',
        'institucion',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        //Importante: NO usar 'password' => 'hashed' si vamos a hacer Hash::make manual
    ];

    // Requeridos por JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
