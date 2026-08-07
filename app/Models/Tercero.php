<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tercero extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TIPO_PERSONA_NATURAL = 'natural';

    public const TIPO_PERSONA_JURIDICA = 'juridica';

    public const TIPOS_PERSONA = [
        self::TIPO_PERSONA_NATURAL,
        self::TIPO_PERSONA_JURIDICA,
    ];

    public const ID_CC = 'CC';

    public const ID_NIT = 'NIT';

    public const ID_CE = 'CE';

    public const ID_PAS = 'PAS';

    public const ID_TI = 'TI';

    public const ID_RC = 'RC';

    public const ID_OTRO = 'OTRO';

    public const TIPOS_IDENTIFICACION = [
        self::ID_CC,
        self::ID_NIT,
        self::ID_CE,
        self::ID_PAS,
        self::ID_TI,
        self::ID_RC,
        self::ID_OTRO,
    ];

    public const ROL_CLIENTE = 'cliente';

    public const ROL_PROVEEDOR = 'proveedor';

    public const ROL_TRABAJADOR = 'trabajador';

    public const ROL_OTRO = 'otro';

    public const ROLES = [
        self::ROL_CLIENTE,
        self::ROL_PROVEEDOR,
        self::ROL_TRABAJADOR,
        self::ROL_OTRO,
    ];

    public const CONSUMIDOR_FINAL_DOCUMENT = '222222222222';

    public const CONSUMIDOR_FINAL_NAME = 'Consumidor Final .';

    public const CONSUMIDOR_FINAL_ADDRESS = 'N/A';

    protected $table = 'terceros';

    protected $fillable = [
        'store_id',
        'user_id',
        'tipo_persona',
        'tipo_identificacion',
        'numero_identificacion',
        'digito_verificacion',
        'nombre',
        'nombre_comercial',
        'email',
        'telefono',
        'telefono_secundario',
        'direccion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(TerceroRol::class, 'tercero_id');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(TerceroContacto::class, 'tercero_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(TerceroDireccion::class, 'tercero_id');
    }

    public function perfilCliente(): HasOne
    {
        return $this->hasOne(TerceroClientePerfil::class, 'tercero_id');
    }

    public function perfilProveedor(): HasOne
    {
        return $this->hasOne(TerceroProveedorPerfil::class, 'tercero_id');
    }

    public function perfilTrabajador(): HasOne
    {
        return $this->hasOne(TerceroTrabajadorPerfil::class, 'tercero_id');
    }

    public function contratosLaborales(): HasMany
    {
        return $this->hasMany(ContratoLaboral::class, 'tercero_id');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'producto_tercero', 'tercero_id', 'product_id')
            ->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'tercero_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkerSchedule::class, 'tercero_id');
    }

    public function scopeDeStore(Builder $query, Store|int $store): Builder
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeConRol(Builder $query, string $rol): Builder
    {
        return $query->whereHas('roles', function ($q) use ($rol) {
            $q->where('rol', $rol)->where('activo', true);
        });
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (! $termino) {
            return $query;
        }

        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
                ->orWhere('nombre_comercial', 'like', "%{$termino}%")
                ->orWhere('email', 'like', "%{$termino}%")
                ->orWhere('numero_identificacion', 'like', "%{$termino}%")
                ->orWhere('telefono', 'like', "%{$termino}%");
        });
    }

    public function tieneRol(string $rol): bool
    {
        return $this->roles->contains(fn (TerceroRol $r) => $r->rol === $rol && $r->activo);
    }

    public function esCliente(): bool
    {
        return $this->tieneRol(self::ROL_CLIENTE);
    }

    public function esProveedor(): bool
    {
        return $this->tieneRol(self::ROL_PROVEEDOR);
    }

    public function esTrabajador(): bool
    {
        return $this->tieneRol(self::ROL_TRABAJADOR);
    }

    public static function consumidorFinalEmailForStore(int $storeId): string
    {
        return 'consumidorfinal.'.$storeId.'@placeholder.invalid';
    }

    /**
     * Alias de compatibilidad: name ≈ nombre.
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['nombre'] ?? null;
    }

    public function getDocumentNumberAttribute(): ?string
    {
        return $this->attributes['numero_identificacion'] ?? null;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->attributes['telefono'] ?? null;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->attributes['direccion'] ?? null;
    }

    public function getNitAttribute(): ?string
    {
        return $this->attributes['numero_identificacion'] ?? null;
    }

    public function getNumeroCelularAttribute(): ?string
    {
        return $this->attributes['telefono'] ?? null;
    }

    /** Alias legacy usado por las vistas de trabajadores. */
    public function getRoleAttribute(): ?Role
    {
        return $this->perfilTrabajador?->role;
    }
}
