<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ImportacionPucService
{
    /**
     * Importa el PUC base (códigos de hasta 6 dígitos). Omite auxiliares de empresa.
     *
     * @return array{importadas:int, actualizadas:int, omitidas_auxiliar:int, omitidas_vacias:int}
     */
    public function importarDesdeExcel(Store $store, ?string $path = null, bool $soloBase = true): array
    {
        $path = $path ?: base_path('docs/cuentas-contables-puc.xlsx');

        if (! is_file($path)) {
            throw new Exception('No se encontró el archivo PUC en: '.$path);
        }

        $filas = $this->leerFilas($path);

        $stats = [
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas_auxiliar' => 0,
            'omitidas_vacias' => 0,
        ];

        DB::transaction(function () use ($store, $filas, $soloBase, &$stats) {
            foreach ($filas as $fila) {
                $codigo = $this->normalizarCodigo($fila['codigo'] ?? '');
                $nombre = trim((string) ($fila['nombre'] ?? ''));

                if ($codigo === '' || $nombre === '') {
                    $stats['omitidas_vacias']++;

                    continue;
                }

                if ($soloBase && CuentaContable::esCodigoAuxiliar($codigo)) {
                    $stats['omitidas_auxiliar']++;

                    continue;
                }

                $esAuxiliar = CuentaContable::esCodigoAuxiliar($codigo);
                $nivel = $this->normalizarNivel($fila['nivel_agrupacion'] ?? null);

                // En import base, las cuentas ≤6 dígitos quedan como estructura (no auxiliares).
                // Si el Excel marcaba Transaccional en un código corto, lo respetamos.
                if ($soloBase && ! $esAuxiliar && $nivel === CuentaContable::NIVEL_TRANSACCIONAL) {
                    // Subcuentas de 6 a veces vienen como Transaccional en exports sucios;
                    // en plantilla limpia las tratamos como agrupadoras salvo que tengan 6 y el Excel diga Transaccional.
                    // Dejamos el valor del Excel si viene; si no, null (agrupadora).
                }

                if ($soloBase && ! $esAuxiliar) {
                    // La plantilla base no debe quedar como auxiliar; el usuario creará auxiliares.
                    $esAuxiliar = false;
                    if ($nivel === CuentaContable::NIVEL_TRANSACCIONAL && strlen(preg_replace('/\D/', '', $codigo) ?? '') <= CuentaContable::MAX_CODIGO_BASE) {
                        $nivel = null;
                    }
                }

                $payload = [
                    'nombre' => $nombre,
                    'clase' => CuentaContable::claseDesdeCodigo($codigo) ?? $this->limpiarTexto($fila['clase'] ?? null),
                    'categoria' => $this->limpiarTexto($fila['categoria'] ?? null),
                    'relacion_con' => $this->limpiarTexto($fila['relacion_con'] ?? null),
                    'maneja_vencimientos' => $this->limpiarTexto($fila['maneja_vencimientos'] ?? null),
                    'diferencia_fiscal' => $this->aBool($fila['diferencia_fiscal'] ?? false),
                    'activo' => $this->aBool($fila['activo'] ?? true, true),
                    'nivel_agrupacion' => $nivel,
                    'es_auxiliar' => $esAuxiliar,
                    'origen' => CuentaContable::ORIGEN_PLANTILLA,
                ];

                $existente = CuentaContable::query()
                    ->deStore($store)
                    ->where('codigo', $codigo)
                    ->first();

                if ($existente) {
                    // No pisar auxiliares/manuales creados por el usuario
                    if ($existente->origen === CuentaContable::ORIGEN_MANUAL || $existente->es_auxiliar) {
                        continue;
                    }
                    $existente->update($payload);
                    $stats['actualizadas']++;
                } else {
                    CuentaContable::create(array_merge($payload, [
                        'store_id' => $store->id,
                        'codigo' => $codigo,
                    ]));
                    $stats['importadas']++;
                }
            }

            app(CuentaContableService::class)->reconstruirPadres($store);
        });

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerFilas(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);

        $filas = [];
        $headerMap = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();
                $cells = array_map(fn ($c) => is_scalar($c) || $c === null ? $c : (string) $c, $cells);

                if ($headerMap === null) {
                    $map = $this->detectarHeader($cells);
                    if ($map !== null) {
                        $headerMap = $map;
                    }

                    continue;
                }

                $filas[] = [
                    'codigo' => $cells[$headerMap['codigo']] ?? null,
                    'nombre' => $cells[$headerMap['nombre']] ?? null,
                    'categoria' => $cells[$headerMap['categoria']] ?? null,
                    'clase' => $cells[$headerMap['clase']] ?? null,
                    'relacion_con' => $cells[$headerMap['relacion_con']] ?? null,
                    'maneja_vencimientos' => $cells[$headerMap['maneja_vencimientos']] ?? null,
                    'diferencia_fiscal' => $cells[$headerMap['diferencia_fiscal']] ?? null,
                    'activo' => $cells[$headerMap['activo']] ?? null,
                    'nivel_agrupacion' => $cells[$headerMap['nivel_agrupacion']] ?? null,
                ];
            }
            break; // solo primera hoja
        }

        $reader->close();

        return $filas;
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, int>|null
     */
    private function detectarHeader(array $cells): ?array
    {
        $normalized = [];
        foreach ($cells as $i => $cell) {
            $key = Str::lower(Str::ascii(trim((string) $cell)));
            $normalized[$key] = $i;
        }

        if (! isset($normalized['codigo']) || ! isset($normalized['nombre'])) {
            return null;
        }

        return [
            'codigo' => $normalized['codigo'],
            'nombre' => $normalized['nombre'],
            'categoria' => $normalized['categoria'] ?? -1,
            'clase' => $normalized['clase'] ?? -1,
            'relacion_con' => $normalized['relacion con'] ?? $normalized['relacion_con'] ?? -1,
            'maneja_vencimientos' => $normalized['maneja vencimientos'] ?? -1,
            'diferencia_fiscal' => $normalized['diferencia fiscal'] ?? -1,
            'activo' => $normalized['activo'] ?? -1,
            'nivel_agrupacion' => $normalized['nivel agrupacion'] ?? $normalized['nivel agrupación'] ?? -1,
        ];
    }

    private function normalizarCodigo(mixed $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return '';
        }

        // Excel puede devolver float (ej. 110505.0)
        if (is_numeric($codigo)) {
            $codigo = (string) (int) $codigo;
        }

        return preg_replace('/\D/', '', (string) $codigo) ?? '';
    }

    private function normalizarNivel(mixed $nivel): ?string
    {
        $t = $this->limpiarTexto($nivel);
        if ($t === null) {
            return null;
        }

        if (Str::lower(Str::ascii($t)) === 'transaccional') {
            return CuentaContable::NIVEL_TRANSACCIONAL;
        }

        return $t;
    }

    private function limpiarTexto(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }

    private function aBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        $t = Str::lower(Str::ascii(trim((string) $value)));

        if (in_array($t, ['1', 'true', 'si', 'sí', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($t, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return $default;
    }
}
