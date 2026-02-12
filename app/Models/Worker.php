<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Worker extends Model
{
    protected $fillable = [
        'store_id',
        'user_id',
        'role_id',
        'name',
        'email',
        'phone',
        'document_number',
        'address',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeSinVincular(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    public function scopeVinculados(Builder $query): void
    {
        $query->whereNotNull('user_id');
    }

    public function estaVinculado(): bool
    {
        return $this->user_id !== null;
    }
}
