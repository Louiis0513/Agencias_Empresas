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

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function attributes()
    {
        return $this->hasMany(Attribute::class);
    }

    public function attributeGroups()
    {
        return $this->hasMany(AttributeGroup::class, 'store_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function bolsillos()
    {
        return $this->hasMany(Bolsillo::class);
    }

    public function proveedores()
    {
        return $this->hasMany(Proveedor::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function accountsPayables()
    {
        return $this->hasMany(AccountPayable::class);
    }
}