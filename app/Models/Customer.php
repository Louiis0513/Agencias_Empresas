<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use HasFactory;

    /** NIT estándar «consumidor final» (Colombia, facturación electrónica). */
    public const CONSUMIDOR_FINAL_DOCUMENT = '222222222222';

    /** Nombre mostrado; el punto cubre apellido por defecto en un solo campo. */
    public const CONSUMIDOR_FINAL_NAME = 'Consumidor Final .';

    public const CONSUMIDOR_FINAL_ADDRESS = 'N/A';

    protected $fillable = [
        'store_id',
        'user_id',
        'name',
        'email',
        'phone',
        'document_number',
        'address',
        'gender',
        'blood_type',
        'eps',
        'birth_date',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    // --- RELACIONES ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    // --- SCOPES (Consultas Reutilizables) ---

    /**
     * Filtra clientes estrictamente por tienda
     */
    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    /**
     * Buscador inteligente: Nombre, Email, Documento o Teléfono
     */
    public function scopeBuscar(Builder $query, ?string $termino): void
    {
        if ($termino) {
            $query->where(function($q) use ($termino) {
                $q->where('name', 'like', "%{$termino}%")
                  ->orWhere('email', 'like', "%{$termino}%")
                  ->orWhere('document_number', 'like', "%{$termino}%")
                  ->orWhere('phone', 'like', "%{$termino}%");
            });
        }
    }

    public static function consumidorFinalEmailForStore(int $storeId): string
    {
        return 'consumidorfinal.' . $storeId . '@placeholder.invalid';
    }

    /**
     * Garantiza un cliente «consumidor final» por tienda (migración y creación de tienda).
     */
    public static function ensureConsumidorFinalForStore(int $storeId): Customer
    {
        return static::firstOrCreate(
            [
                'store_id' => $storeId,
                'document_number' => self::CONSUMIDOR_FINAL_DOCUMENT,
            ],
            [
                'name' => self::CONSUMIDOR_FINAL_NAME,
                'email' => self::consumidorFinalEmailForStore($storeId),
                'address' => self::CONSUMIDOR_FINAL_ADDRESS,
                'phone' => null,
                'user_id' => null,
            ]
        );
    }
}
