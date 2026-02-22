<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Store;
use App\Models\StorePlan;
use App\Models\SubscriptionEntry;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    /**
     * Lista los planes de la tienda ordenados por nombre.
     */
    public function getPlansForStore(Store $store): Collection
    {
        return $store->storePlans()->orderBy('name')->get();
    }

    /**
     * Obtiene un plan por ID dentro de la tienda (para edición).
     */
    public function getPlanForStore(Store $store, int $planId): ?StorePlan
    {
        return StorePlan::where('store_id', $store->id)
            ->where('id', $planId)
            ->first();
    }

    /**
     * Normaliza los datos del plan para create/update.
     */
    private function normalizePlanData(array $data): array
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        $description = isset($data['description']) ? trim((string) $data['description']) : null;
        if ($description === '') {
            $description = null;
        }

        $daily = $data['daily_entries_limit'] ?? null;
        $total = $data['total_entries_limit'] ?? null;
        if ($daily !== null && $daily !== '') {
            $daily = (int) $daily;
        } else {
            $daily = null;
        }
        if ($total !== null && $total !== '') {
            $total = (int) $total;
        } else {
            $total = null;
        }

        return [
            'name' => $name,
            'description' => $description,
            'price' => (float) ($data['price'] ?? 0),
            'duration_days' => (int) ($data['duration_days'] ?? 0),
            'daily_entries_limit' => $daily,
            'total_entries_limit' => $total,
        ];
    }

    /**
     * Crea un plan de suscripción en la tienda.
     */
    public function createPlan(Store $store, array $data): StorePlan
    {
        $normalized = $this->normalizePlanData($data);

        return StorePlan::create([
            'store_id' => $store->id,
            'name' => $normalized['name'],
            'description' => $normalized['description'],
            'price' => $normalized['price'],
            'duration_days' => $normalized['duration_days'],
            'daily_entries_limit' => $normalized['daily_entries_limit'],
            'total_entries_limit' => $normalized['total_entries_limit'],
        ]);
    }

    /**
     * Actualiza un plan existente de la tienda.
     */
    public function updatePlan(Store $store, int $planId, array $data): StorePlan
    {
        $plan = StorePlan::where('store_id', $store->id)
            ->where('id', $planId)
            ->firstOrFail();

        $normalized = $this->normalizePlanData($data);

        $plan->update($normalized);

        return $plan->fresh();
    }

    /**
     * Elimina un plan de la tienda.
     */
    public function deletePlan(Store $store, int $planId): void
    {
        $plan = StorePlan::where('store_id', $store->id)
            ->where('id', $planId)
            ->firstOrFail();

        $plan->delete();
    }

    /**
     * Historial de suscripciones (membresías) de la tienda.
     */
    public function getSubscriptionHistoryForStore(Store $store): Collection
    {
        return CustomerSubscription::where('store_id', $store->id)
            ->with(['customer', 'storePlan'])
            ->orderBy('starts_at', 'desc')
            ->get();
    }

    /**
     * Suscripción activa del cliente en la tienda en una fecha/hora dada.
     * Filtro explícito: starts_at <= $at y expires_at >= $at (vigente en ese momento).
     */
    public function getActiveSubscriptionForCustomer(Store $store, int $customerId, ?Carbon $at = null): ?CustomerSubscription
    {
        $at = $at ?? now();

        $query = CustomerSubscription::where('store_id', $store->id)
            ->where('customer_id', $customerId)
            ->where('starts_at', '<=', $at)
            ->where('expires_at', '>=', $at);

        return $query->orderBy('starts_at', 'desc')->first();
    }

    /**
     * Crea una suscripción (membresía) para un cliente con un plan.
     */
    public function createSubscription(Store $store, int $customerId, int $planId, Carbon $startsAt): CustomerSubscription
    {
        $customer = Customer::where('id', $customerId)->where('store_id', $store->id)->first();
        if (! $customer) {
            throw new InvalidArgumentException('El cliente no existe o no pertenece a esta tienda.');
        }

        $plan = $this->getPlanForStore($store, $planId);
        if (! $plan) {
            throw new InvalidArgumentException('El plan no existe o no pertenece a esta tienda.');
        }

        $expiresAt = $startsAt->copy()->addDays($plan->duration_days);

        // Validar que no exista una suscripción cruzada (mismo cliente/tienda, rangos solapados)
        $existente = CustomerSubscription::where('store_id', $store->id)
            ->where('customer_id', $customerId)
            ->where('starts_at', '<=', $expiresAt)
            ->where('expires_at', '>=', $startsAt)
            ->first();

        if ($existente) {
            throw new InvalidArgumentException(
                'El cliente ya tiene una suscripción vigente en ese período (desde ' .
                $existente->starts_at->format('d/m/Y') . ' hasta ' . $existente->expires_at->format('d/m/Y') . '). No se permiten suscripciones cruzadas.'
            );
        }

        return CustomerSubscription::create([
            'store_id' => $store->id,
            'customer_id' => $customerId,
            'store_plan_id' => $planId,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'entries_used' => 0,
            'last_entry_at' => null,
        ]);
    }

    /**
     * Registra una asistencia (entrada) para el cliente en la fecha/hora indicada.
     * Usa transacción y lockForUpdate para evitar condiciones de carrera (doble clic).
     */
    public function recordAttendance(Store $store, int $customerId, Carbon $dateTime): CustomerSubscription
    {
        return DB::transaction(function () use ($store, $customerId, $dateTime) {
            $subscription = CustomerSubscription::where('store_id', $store->id)
                ->where('customer_id', $customerId)
                ->where('starts_at', '<=', $dateTime)
                ->where('expires_at', '>=', $dateTime)
                ->orderBy('starts_at', 'desc')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                $ultima = CustomerSubscription::where('store_id', $store->id)
                    ->where('customer_id', $customerId)
                    ->orderBy('expires_at', 'desc')
                    ->first();
                if ($ultima && $ultima->expires_at->isPast()) {
                    throw new InvalidArgumentException('No hay suscripción activa para la fecha indicada. Última suscripción vencida el ' . $ultima->expires_at->format('d/m/Y') . '.');
                }
                throw new InvalidArgumentException('El cliente no tiene una suscripción activa en esa fecha.');
            }

            $subscription->load('storePlan');
            $plan = $subscription->storePlan;

            if ($plan->daily_entries_limit !== null) {
                $dayStart = $dateTime->copy()->startOfDay();
                $dayEnd = $dateTime->copy()->endOfDay();
                $entriesThatDay = SubscriptionEntry::where('customer_subscription_id', $subscription->id)
                    ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                    ->count();
                if ($entriesThatDay >= $plan->daily_entries_limit) {
                    throw new InvalidArgumentException('Ya alcanzó el límite de entradas para ese día (' . $plan->daily_entries_limit . ' por día).');
                }
            }

            if ($plan->total_entries_limit !== null && $subscription->entries_used >= $plan->total_entries_limit) {
                throw new InvalidArgumentException('No le quedan entradas (usadas ' . $subscription->entries_used . ' de ' . $plan->total_entries_limit . ').');
            }

            $subscription->entries_used++;
            $subscription->last_entry_at = $dateTime;
            $subscription->save();

            SubscriptionEntry::create([
                'customer_subscription_id' => $subscription->id,
                'store_id' => $store->id,
                'customer_id' => $customerId,
                'recorded_at' => $dateTime,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Historial de asistencias de la tienda con filtros opcionales por rango de fechas y cliente.
     */
    public function getAttendanceHistoryForStore(Store $store, ?Carbon $from = null, ?Carbon $to = null, ?int $customerId = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = SubscriptionEntry::where('store_id', $store->id)
            ->with(['customer', 'customerSubscription.storePlan'])
            ->orderBy('recorded_at', 'desc');

        if ($from !== null) {
            $query->where('recorded_at', '>=', $from->copy()->startOfDay());
        }
        if ($to !== null) {
            $query->where('recorded_at', '<=', $to->copy()->endOfDay());
        }
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $query->paginate($perPage);
    }
}
