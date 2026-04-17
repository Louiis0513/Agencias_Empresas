<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalizadorPagos
{
    /**
     * Procesa un archivo Excel y genera un informe de conciliación
     *
     * @param  UploadedFile  $archivo  Archivo Excel subido
     * @return StreamedResponse Respuesta con el archivo para descargar
     *
     * @throws Exception
     */
    public function procesarArchivo(UploadedFile $archivo): StreamedResponse
    {
        // Aumentar límites de PHP para archivos grandes
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300); // 5 minutos
        set_time_limit(300); // 5 minutos

        Log::info('[AnalizadorPagos] Iniciando procesamiento de archivo', [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]);

        // Validar que sea un archivo Excel
        $extension = $archivo->getClientOriginalExtension();
        if (! in_array(strtolower($extension), ['xlsx', 'xls'])) {
            Log::error('[AnalizadorPagos] Extensión de archivo inválida', ['extension' => $extension]);
            throw new Exception('El archivo debe ser un Excel (.xlsx o .xls)');
        }

        // Guardar temporalmente el archivo para procesarlo
        Log::info('[AnalizadorPagos] Guardando archivo temporalmente');
        $rutaTemporal = $archivo->storeAs('temp', 'archivo_'.time().'.'.$extension);
        $rutaCompleta = Storage::path($rutaTemporal);

        Log::info('[AnalizadorPagos] Archivo guardado temporalmente', [
            'ruta_temporal' => $rutaTemporal,
            'ruta_completa' => $rutaCompleta,
        ]);

        try {
            // Procesar el archivo
            Log::info('[AnalizadorPagos] Iniciando lectura y procesamiento del archivo');
            $tabla3 = $this->leerYProcesarArchivo($rutaCompleta);

            Log::info('[AnalizadorPagos] Archivo procesado exitosamente', [
                'total_registros' => count($tabla3),
            ]);

            // Generar el archivo Excel de salida en memoria
            Log::info('[AnalizadorPagos] Generando archivo Excel de salida');

            return $this->generarArchivoExcel($tabla3);

        } catch (Exception $e) {
            Log::error('[AnalizadorPagos] Error durante el procesamiento', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            // Eliminar el archivo temporal
            Log::info('[AnalizadorPagos] Eliminando archivo temporal', ['ruta' => $rutaTemporal]);
            Storage::delete($rutaTemporal);
        }
    }

    /**
     * Lee y procesa el archivo Excel
     *
     * @return array Tabla3 con los datos procesados
     *
     * @throws Exception
     */
    private function leerYProcesarArchivo(string $rutaArchivo): array
    {
        Log::info('[AnalizadorPagos] Abriendo archivo Excel para lectura', ['ruta' => $rutaArchivo]);

        try {
            $reader = new XlsxReader;
            $reader->open($rutaArchivo);
            Log::info('[AnalizadorPagos] Archivo abierto correctamente');

            $tabla1 = [];
            $tabla2 = [];
            $rowIndex = 0;

            // Leer la primera hoja
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() === 0) {
                    Log::info('[AnalizadorPagos] Procesando primera hoja del archivo');

                    foreach ($sheet->getRowIterator() as $row) {
                        $rowIndex++;
                        if ($rowIndex < 2) {
                            continue;
                        } // Saltar encabezado

                        $cells = $row->getCells();

                        // Procesar Tabla 1 (Columnas A-D)
                        $prefijoT1 = isset($cells[0]) ? trim((string) $cells[0]->getValue()) : '';
                        if (! empty($prefijoT1)) {
                            $fechaVal = isset($cells[2]) ? $cells[2]->getValue() : null;
                            if ($fechaVal instanceof \DateTime || $fechaVal instanceof \DateTimeImmutable) {
                                // Ya es una fecha
                            } elseif (is_numeric($fechaVal)) {
                                // Convertir número serial de Excel a DateTime
                                // Excel cuenta desde 1900-01-01, pero tiene un bug: considera 1900 como año bisiesto
                                $fechaVal = \DateTime::createFromFormat('Y-m-d', '1900-01-01')
                                    ->modify('+'.(intval($fechaVal) - 2).' days');
                            }

                            $tabla1[] = [
                                'prefijo' => $prefijoT1,
                                'folio' => isset($cells[1]) ? trim((string) $cells[1]->getValue()) : '',
                                'fecha' => $fechaVal,
                                'total' => isset($cells[3]) ? $cells[3]->getValue() : '',
                            ];
                        }

                        // Procesar Tabla 2 (Columnas F-I)
                        $prefijoT2 = isset($cells[5]) ? trim((string) $cells[5]->getValue()) : '';
                        if (! empty($prefijoT2)) {
                            $fechaPago = isset($cells[7]) ? $cells[7]->getValue() : null;
                            if ($fechaPago instanceof \DateTime || $fechaPago instanceof \DateTimeImmutable) {
                                // Ya es una fecha
                            } elseif (is_numeric($fechaPago)) {
                                // Convertir número serial de Excel a DateTime
                                // Excel cuenta desde 1900-01-01, pero tiene un bug: considera 1900 como año bisiesto
                                $fechaPago = \DateTime::createFromFormat('Y-m-d', '1900-01-01')
                                    ->modify('+'.(intval($fechaPago) - 2).' days');
                            }

                            $tabla2[] = [
                                'prefijo' => $prefijoT2,
                                'numero' => isset($cells[6]) ? trim((string) $cells[6]->getValue()) : '',
                                'fechaPago' => $fechaPago,
                                'valorPagado' => isset($cells[8]) ? $cells[8]->getValue() : '',
                            ];
                        }

                        // Log cada 1000 filas procesadas
                        if ($rowIndex % 1000 == 0) {
                            Log::debug('[AnalizadorPagos] Filas procesadas', [
                                'filas' => $rowIndex,
                                'tabla1_registros' => count($tabla1),
                                'tabla2_registros' => count($tabla2),
                            ]);
                        }
                    }
                }
            }
            $reader->close();

            Log::info('[AnalizadorPagos] Lectura del archivo completada', [
                'total_filas_leidas' => $rowIndex,
                'tabla1_total' => count($tabla1),
                'tabla2_total' => count($tabla2),
            ]);

            // Realizar la conciliación
            Log::info('[AnalizadorPagos] Iniciando conciliación de datos');

            return $this->conciliarDatos($tabla1, $tabla2);

        } catch (Exception $e) {
            Log::error('[AnalizadorPagos] Error al leer el archivo', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Concilia las facturas con los pagos
     *
     * @param  array  $tabla1  Facturas
     * @param  array  $tabla2  Pagos
     * @return array Tabla3 con datos conciliados
     */
    private function conciliarDatos(array $tabla1, array $tabla2): array
    {
        Log::info('[AnalizadorPagos] Iniciando conciliación', [
            'facturas' => count($tabla1),
            'pagos' => count($tabla2),
        ]);

        // Helper para convertir valores
        $toStr = function ($o) {
            if ($o === null || $o === '') {
                return '';
            }
            if ($o instanceof \DateTime || $o instanceof \DateTimeImmutable) {
                return $o->format('Y-m-d');
            }

            return trim((string) $o);
        };

        // Construir Lookup de pagos agrupados por Numero
        $pagosLookup = [];
        foreach ($tabla2 as $pago) {
            $numeroKey = $toStr($pago['numero']);
            if (! isset($pagosLookup[$numeroKey])) {
                $pagosLookup[$numeroKey] = [];
            }
            $pagosLookup[$numeroKey][] = $pago;
        }

        Log::info('[AnalizadorPagos] Pagos agrupados por número', [
            'grupos_pagos' => count($pagosLookup),
        ]);

        // Máximo de pagos para columnas dinámicas
        $maxPagos = 0;
        foreach ($pagosLookup as $pagos) {
            $count = count($pagos);
            if ($count > $maxPagos) {
                $maxPagos = $count;
            }
        }

        Log::info('[AnalizadorPagos] Máximo de pagos por factura', ['max_pagos' => $maxPagos]);

        // Helper para limpiar números a float
        $cleanNum = function ($val) {
            if (is_numeric($val)) {
                return (float) $val;
            }
            if (is_string($val)) {
                if (strpos($val, ',') !== false) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                }

                return (float) $val;
            }

            return 0.0;
        };

        // Helper para convertir fechas
        $formatDate = function ($val) {
            if ($val instanceof \DateTime || $val instanceof \DateTimeImmutable) {
                return $val->format('Y-m-d');
            }
            if ($val === null || $val === '') {
                return '';
            }

            return trim((string) $val);
        };

        // Crear tabla3 (resultado de la conciliación)
        $tabla3 = [];

        // Rellenar filas para TODAS las facturas
        foreach ($tabla1 as $cobro) {
            $folioKey = $toStr($cobro['folio']);
            $pagos = isset($pagosLookup[$folioKey]) ? $pagosLookup[$folioKey] : [];

            $newRow = [
                'Prefijo' => $toStr($cobro['prefijo']),
                'Folio' => $folioKey,
                'FechaEmision' => $formatDate($cobro['fecha']),
                'MontoCobro' => $cleanNum($cobro['total']),
            ];

            // Agregar columnas dinámicas de pagos
            for ($idx = 1; $idx <= $maxPagos; $idx++) {
                if (isset($pagos[$idx - 1])) {
                    $pago = $pagos[$idx - 1];
                    $newRow["PrefijoPago$idx"] = $toStr($pago['prefijo']);
                    $newRow["NumeroPago$idx"] = $toStr($pago['numero']);
                    $newRow["FechaPago$idx"] = $formatDate($pago['fechaPago']);
                    $newRow["MontoPago$idx"] = $cleanNum($pago['valorPagado']);
                } else {
                    $newRow["PrefijoPago$idx"] = '';
                    $newRow["NumeroPago$idx"] = '';
                    $newRow["FechaPago$idx"] = '';
                    $newRow["MontoPago$idx"] = '';
                }
            }

            $tabla3[] = $newRow;
        }

        // Agregar pagos sin factura
        $foliosProcesados = [];
        foreach ($tabla1 as $cobro) {
            $folioKey = $toStr($cobro['folio']);
            $foliosProcesados[$folioKey] = true;
        }

        foreach ($pagosLookup as $numeroKey => $pagos) {
            if (! isset($foliosProcesados[$numeroKey])) {
                foreach ($pagos as $pago) {
                    $orphanRow = [
                        'Prefijo' => '',
                        'Folio' => '',
                        'FechaEmision' => '',
                        'MontoCobro' => '',
                    ];

                    $orphanRow['PrefijoPago1'] = $toStr($pago['prefijo']);
                    $orphanRow['NumeroPago1'] = $toStr($pago['numero']);
                    $orphanRow['FechaPago1'] = $formatDate($pago['fechaPago']);
                    $orphanRow['MontoPago1'] = $cleanNum($pago['valorPagado']);

                    for ($idx = 2; $idx <= $maxPagos; $idx++) {
                        $orphanRow["PrefijoPago$idx"] = '';
                        $orphanRow["NumeroPago$idx"] = '';
                        $orphanRow["FechaPago$idx"] = '';
                        $orphanRow["MontoPago$idx"] = '';
                    }

                    $tabla3[] = $orphanRow;
                }
            }
        }

        // Contar estadísticas finales
        $facturasConPagos = 0;
        $facturasSinPagos = 0;
        $pagosSinFacturas = 0;

        foreach ($tabla3 as $row) {
            if (! empty($row['Prefijo']) && ! empty($row['PrefijoPago1'])) {
                $facturasConPagos++;
            } elseif (! empty($row['Prefijo']) && empty($row['PrefijoPago1'])) {
                $facturasSinPagos++;
            } elseif (empty($row['Prefijo']) && ! empty($row['PrefijoPago1'])) {
                $pagosSinFacturas++;
            }
        }

        Log::info('[AnalizadorPagos] Conciliación completada', [
            'total_registros' => count($tabla3),
            'facturas_con_pagos' => $facturasConPagos,
            'facturas_sin_pagos' => $facturasSinPagos,
            'pagos_sin_facturas' => $pagosSinFacturas,
        ]);

        return $tabla3;
    }

    /**
     * Genera el archivo Excel con los datos procesados
     *
     * @param  array  $tabla3  Datos procesados
     * @return StreamedResponse Respuesta con el archivo para descargar
     */
    private function generarArchivoExcel(array $tabla3): StreamedResponse
    {
        Log::info('[AnalizadorPagos] Iniciando generación de archivo Excel', [
            'total_registros' => count($tabla3),
        ]);

        if (empty($tabla3)) {
            Log::error('[AnalizadorPagos] No hay datos para procesar');
            throw new Exception('No hay datos para procesar');
        }

        // Calcular sumas
        $suma1 = 0;
        $suma2 = 0;
        $sumaFacturasSinPagos = 0;
        $sumaPagosSinFacturas = 0;

        $val = function ($str) {
            if (is_numeric($str)) {
                return (float) $str;
            }
            $str = str_replace(',', '', (string) $str);
            $str = trim($str);

            return floatval($str);
        };

        // Calcular sumas
        foreach ($tabla3 as $r) {
            if (! empty($r['MontoCobro'])) {
                $suma1 += $val($r['MontoCobro']);
            }
            if (! empty($r['PrefijoPago1'])) {
                $suma2 += $val($r['MontoPago1'] ?? 0);
            }
            if (empty($r['PrefijoPago1']) && ! empty($r['Prefijo'])) {
                $sumaFacturasSinPagos += $val($r['MontoCobro']);
            }
            if (empty($r['Prefijo']) && ! empty($r['PrefijoPago1'])) {
                $sumaPagosSinFacturas += $val($r['MontoPago1'] ?? 0);
            }
        }

        // Filtrar facturas sin pagos y pagos sin facturas
        $facturasSinPagos = array_filter($tabla3, function ($r) {
            return empty($r['PrefijoPago1']) && ! empty($r['Prefijo']);
        });

        $pagosSinFacturas = array_filter($tabla3, function ($r) {
            return empty($r['Prefijo']) && ! empty($r['PrefijoPago1']);
        });

        Log::info('[AnalizadorPagos] Estadísticas calculadas', [
            'suma_facturas' => $suma1,
            'suma_pagos' => $suma2,
            'suma_facturas_sin_pagos' => $sumaFacturasSinPagos,
            'suma_pagos_sin_facturas' => $sumaPagosSinFacturas,
            'total_facturas_sin_pagos' => count($facturasSinPagos),
            'total_pagos_sin_facturas' => count($pagosSinFacturas),
        ]);

        // Obtener columnas
        $columnas = array_keys($tabla3[0]);
        $numColumnas = count($columnas);

        Log::info('[AnalizadorPagos] Preparando escritura del Excel', [
            'total_columnas' => $numColumnas,
            'columnas' => $columnas,
        ]);

        // Estilos
        $styleTitulo = (new Style)
            ->setFontBold()
            ->setFontSize(18)
            ->setFontColor(Color::rgb(54, 96, 146))
            ->setCellAlignment(CellAlignment::CENTER);

        $styleHeader = (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(54, 96, 146))
            ->setCellAlignment(CellAlignment::CENTER);

        $styleResumen = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setBackgroundColor(Color::rgb(231, 230, 230));

        // Crear respuesta de descarga
        return new StreamedResponse(function () use ($tabla3, $columnas, $numColumnas, $suma1, $suma2, $sumaFacturasSinPagos, $sumaPagosSinFacturas, $facturasSinPagos, $pagosSinFacturas, $styleTitulo, $styleHeader, $styleResumen) {
            // Limpiar todos los buffers de salida para evitar que se corrompa el archivo
            while (@ob_get_level()) {
                @ob_end_clean();
            }

            // Deshabilitar errores visibles para evitar que corrompan el archivo binario
            $oldErrorReporting = error_reporting(0);
            $oldDisplayErrors = ini_set('display_errors', '0');

            Log::info('[AnalizadorPagos] Iniciando escritura del archivo Excel');

            try {
                $options = new Options;
                $writer = new XlsxWriter($options);
                $writer->openToFile('php://output');

                // ===== SHEET 1: CONCILIACIÓN TOTAL =====
                // Título
                $tituloRow = array_fill(0, $numColumnas, '');
                $tituloRow[0] = 'CONCILIACIÓN DE FACTURAS Y PAGOS';
                $writer->addRow(Row::fromValues($tituloRow, $styleTitulo));

                // Resumen
                $resumenTextos = [
                    'Total Facturas (Suma de Montos a Cobrar): '.number_format($suma1, 2, ',', '.'),
                    'Total Pagos Recibidos: '.number_format($suma2, 2, ',', '.'),
                ];

                foreach ($resumenTextos as $texto) {
                    $resumenRow = array_fill(0, $numColumnas, '');
                    $resumenRow[0] = $texto;
                    $writer->addRow(Row::fromValues($resumenRow, $styleResumen));
                }

                // Encabezados
                $headers = [];
                foreach ($columnas as $columna) {
                    $headers[] = $columna;
                }
                $writer->addRow(Row::fromValues($headers, $styleHeader));

                // Datos
                foreach ($tabla3 as $registro) {
                    $filaDatos = [];
                    foreach ($columnas as $columna) {
                        $filaDatos[] = isset($registro[$columna]) ? $registro[$columna] : '';
                    }
                    $writer->addRow(Row::fromValues($filaDatos));
                }

                // ===== SHEET 2: FACTURAS SIN PAGOS =====
                if (! empty($facturasSinPagos)) {
                    $writer->addNewSheetAndMakeItCurrent();

                    $tituloRow = array_fill(0, $numColumnas, '');
                    $tituloRow[0] = 'FACTURAS SIN PAGOS';
                    $writer->addRow(Row::fromValues($tituloRow, $styleTitulo));

                    $sumaRow = array_fill(0, $numColumnas, '');
                    $sumaRow[0] = 'Total Facturas sin Pagos: '.number_format($sumaFacturasSinPagos, 2, ',', '.');
                    $writer->addRow(Row::fromValues($sumaRow, $styleResumen));

                    $writer->addRow(Row::fromValues($headers, $styleHeader));

                    foreach ($facturasSinPagos as $registro) {
                        $filaDatos = [];
                        foreach ($columnas as $columna) {
                            $filaDatos[] = isset($registro[$columna]) ? $registro[$columna] : '';
                        }
                        $writer->addRow(Row::fromValues($filaDatos));
                    }
                }

                // ===== SHEET 3: PAGOS SIN FACTURAS =====
                if (! empty($pagosSinFacturas)) {
                    $writer->addNewSheetAndMakeItCurrent();

                    $tituloRow = array_fill(0, $numColumnas, '');
                    $tituloRow[0] = 'PAGOS SIN FACTURAS';
                    $writer->addRow(Row::fromValues($tituloRow, $styleTitulo));

                    $sumaRow = array_fill(0, $numColumnas, '');
                    $sumaRow[0] = 'Total Pagos sin Facturas: '.number_format($sumaPagosSinFacturas, 2, ',', '.');
                    $writer->addRow(Row::fromValues($sumaRow, $styleResumen));

                    $writer->addRow(Row::fromValues($headers, $styleHeader));

                    foreach ($pagosSinFacturas as $registro) {
                        $filaDatos = [];
                        foreach ($columnas as $columna) {
                            $filaDatos[] = isset($registro[$columna]) ? $registro[$columna] : '';
                        }
                        $writer->addRow(Row::fromValues($filaDatos));
                    }
                }

                $writer->close();
                Log::info('[AnalizadorPagos] Archivo Excel generado exitosamente');

                // Restaurar configuración de errores
                error_reporting($oldErrorReporting);
                if ($oldDisplayErrors !== false) {
                    ini_set('display_errors', $oldDisplayErrors);
                }

            } catch (Exception $e) {
                // Restaurar configuración de errores antes de lanzar el error
                error_reporting($oldErrorReporting);
                if ($oldDisplayErrors !== false) {
                    ini_set('display_errors', $oldDisplayErrors);
                }

                Log::error('[AnalizadorPagos] Error al generar el archivo Excel', [
                    'mensaje' => $e->getMessage(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                    'trace' => substr($e->getTraceAsString(), 0, 500),
                ]);

                // Limpiar cualquier output previo
                while (@ob_get_level()) {
                    @ob_end_clean();
                }

                throw $e;
            }
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="InformeDeAnalizador.xlsx"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Accel-Buffering' => 'no', // Deshabilitar buffering en Nginx si está presente
        ]);
    }
}
