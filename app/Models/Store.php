<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    // Permitimos que se puedan llenar estos campos masivamente
    protected $fillable = ['name', 'slug', 'user_id'];

    // Relación: Una tienda tiene muchos usuarios (trabajadores)
    public function workers()
    {
        return $this->belongsToMany(User::class, 'store_user')
                    ->withPivot('role_id')
                    ->withTimestamps();
    }

    // Relación: Una tienda tiene muchos roles personalizados
    public function roles()
    {
        return $this->hasMany(Role::class);
    }
    
    // Relación: Dueño propietario (quien paga)
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}