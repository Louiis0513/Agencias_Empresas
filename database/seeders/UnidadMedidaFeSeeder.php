<?php

namespace Database\Seeders;

use App\Models\UnidadMedidaFe;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Carga el catálogo DIAN de unidades de medida FE desde Excel (export Siigo).
 */
class UnidadMedidaFeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/unidades_medida_fe.xlsx');

        if (! is_file($path)) {
            $this->command?->error('No se encontró el Excel: '.$path);

            return;
        }

        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, true, true);

        $started = false;
        $batch = [];
        $nowCount = 0;

        foreach ($rows as $row) {
            $codigo = trim((string) ($row['A'] ?? ''));
            $nombre = trim((string) ($row['B'] ?? ''));

            // Quitar NBSP y espacios raros del Excel Siigo
            $nombre = preg_replace('/\x{00A0}/u', ' ', $nombre) ?? $nombre;
            $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre);

            if (! $started) {
                if ($codigo === 'Código unidad de medida') {
                    $started = true;
                }

                continue;
            }

            if ($codigo === '' || $nombre === '') {
                continue;
            }

            $batch[] = [
                'codigo' => mb_substr($codigo, 0, 40),
                'nombre' => mb_substr($nombre, 0, 120),
            ];
            $nowCount++;

            if (count($batch) >= 200) {
                UnidadMedidaFe::query()->upsert($batch, ['codigo'], ['nombre']);
                $batch = [];
            }
        }

        if ($batch !== []) {
            UnidadMedidaFe::query()->upsert($batch, ['codigo'], ['nombre']);
        }

        $this->command?->info("Unidades de medida FE cargadas/actualizadas: {$nowCount}");
    }
}
