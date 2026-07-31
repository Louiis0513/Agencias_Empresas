<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'plan_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }
    
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function terceros()
    {
        return $this->hasMany(Tercero::class);
    }

    /** Alias de compatibilidad para terceros con rol cliente. */
    public function customers()
    {
        return $this->terceros()->conRol(Tercero::ROL_CLIENTE);
    }

    /**
     * Relación: Un usuario puede ser trabajador en múltiples tiendas
     * Registros de la tabla workers vinculados a este usuario
     */
    public function workerRecords()
    {
        return $this->terceros()->conRol(Tercero::ROL_TRABAJADOR);
    }
}
