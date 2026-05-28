<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use App\Models\AsignacionJuezCategoria;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\InscripcionIntegrante;
use App\Models\Resultado;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'seguridad.users';

    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'role_id',
        'telefono',
        'photo_path',
        'must_change_password',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'must_change_password' => 'boolean',
        'estado' => 'boolean',
        'password' => 'hashed',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function equiposCapitaneados()
    {
        return $this->hasMany(Equipo::class, 'capitan_user_id');
    }

    public function inscripcionesRegistradas()
    {
        return $this->hasMany(Inscripcion::class, 'user_id');
    }

    public function registrosComoIntegrante()
    {
        return $this->hasMany(InscripcionIntegrante::class, 'user_id');
    }

    public function asignacionesComoJuez()
    {
        return $this->hasMany(AsignacionJuezCategoria::class, 'juez_user_id');
    }

    public function resultadosComoJuez()
    {
        return $this->hasMany(Resultado::class, 'juez_user_id');
    }
}
