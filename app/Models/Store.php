<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    // Permitimos que se puedan llenar estos campos masivamente
    protected $fillable = [
        'name', 'slug', 'user_id',
        'rut_nit', 'currency', 'timezone', 'date_format', 'time_format',
        'country', 'department', 'city', 'address', 'phone', 'mobile',
        'domain', 'regimen', 'logo_path', 'maneja_bodegas',
    ];

    protected function casts(): array
    {
        return [
            'maneja_bodegas' => 'boolean',
        ];
    }

    // Relación: Una tienda tiene muchos usuarios (trabajadores)
    public function workers()
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function terceros()
    {
        return $this->hasMany(Tercero::class);
    }

    // Alias de compatibilidad para terceros con rol trabajador.
    public function workerRecords()
    {
        return $this->terceros()->conRol(Tercero::ROL_TRABAJADOR);
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

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function bodegas()
    {
        return $this->hasMany(Bodega::class);
    }

    public function listasPrecios()
    {
        return $this->hasMany(ListaPrecio::class);
    }

    public function customers()
    {
        return $this->terceros()->conRol(Tercero::ROL_CLIENTE);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function bolsillos()
    {
        return $this->hasMany(Bolsillo::class);
    }

    public function sesionesCaja()
    {
        return $this->hasMany(SesionCaja::class, 'store_id');
    }

    public function proveedores()
    {
        return $this->terceros()->conRol(Tercero::ROL_PROVEEDOR);
    }

    public function accountsPayables()
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function vitrinaConfig()
    {
        return $this->hasOne(VitrinaConfig::class);
    }

    public function workerSchedules()
    {
        return $this->hasMany(WorkerSchedule::class, 'store_id');
    }

    public function hourRateTemplates()
    {
        return $this->hasMany(WorkerHourRateTemplate::class, 'store_id');
    }

    public function cuentasContables()
    {
        return $this->hasMany(CuentaContable::class);
    }

    public function comprobantesContables()
    {
        return $this->hasMany(ComprobanteContable::class);
    }

    public function categoriasContables()
    {
        return $this->hasMany(CategoriaContable::class);
    }

    public function impuestos()
    {
        return $this->hasMany(Impuesto::class);
    }
}
