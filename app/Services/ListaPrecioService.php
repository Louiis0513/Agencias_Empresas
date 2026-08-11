<?php

namespace App\Services;

use App\Models\ListaPrecio;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class ListaPrecioService
{
    /**
     * Asegura las 12 listas estilo Siigo por tienda.
     * Idempotente: no duplica; crea faltantes; no renombra las existentes.
     *
     * @return array{creadas: int, omitidas: int}
     */
    public function asegurarListasPorDefecto(Store $store): array
    {
        $stats = ['creadas' => 0, 'omitidas' => 0];

        return DB::transaction(function () use ($store, $stats) {
            $existentes = ListaPrecio::query()
                ->deStore($store)
                ->get()
                ->keyBy('numero');

            for ($n = 1; $n <= ListaPrecio::MAX_POR_TIENDA; $n++) {
                if ($existentes->has($n)) {
                    $stats['omitidas']++;

                    continue;
                }

                ListaPrecio::create([
                    'store_id' => $store->id,
                    'numero' => $n,
                    'nombre' => 'Precio de venta '.$n,
                    'activo' => $n <= 2,
                ]);
                $stats['creadas']++;
            }

            return $stats;
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ListaPrecio>
     */
    public function listar(Store $store)
    {
        $this->asegurarListasPorDefecto($store);

        return ListaPrecio::query()
            ->deStore($store)
            ->orderBy('numero')
            ->get();
    }

    /**
     * Actualiza nombre/activo de una lista de la tienda.
     *
     * @param  array{nombre?: string, activo?: bool}  $data
     */
    public function actualizar(Store $store, ListaPrecio $lista, array $data): ListaPrecio
    {
        if ((int) $lista->store_id !== (int) $store->id) {
            throw new \InvalidArgumentException('La lista de precios no pertenece a esta tienda.');
        }

        if (array_key_exists('nombre', $data)) {
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '') {
                throw new \InvalidArgumentException('El nombre de la lista no puede estar vacío.');
            }
            $lista->nombre = mb_substr($nombre, 0, 120);
        }

        if (array_key_exists('activo', $data)) {
            $lista->activo = (bool) $data['activo'];
        }

        $lista->save();

        return $lista->fresh();
    }
}
