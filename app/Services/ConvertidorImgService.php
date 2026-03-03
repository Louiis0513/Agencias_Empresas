<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class ConvertidorImgService
{
    public function convertPublicImageToWebp(string $relativePath, int $quality = 80): string
    {
        if ($relativePath === '') {
            throw new RuntimeException('La ruta de la imagen no puede estar vacía.');
        }

        $disk = Storage::disk('public');

        $inputPath = $disk->path($relativePath);

        if (! file_exists($inputPath)) {
            throw new RuntimeException("No se encontró el archivo de entrada para convertir: {$relativePath}");
        }

        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        if ($extension === 'webp') {
            return $relativePath;
        }

        $directory = trim(dirname($relativePath), '/');
        $filename = pathinfo($relativePath, PATHINFO_FILENAME);

        $outputRelativePath = ($directory !== '' ? $directory.'/' : '').$filename.'.webp';
        $outputPath = $disk->path($outputRelativePath);

        $quality = max(0, min(100, $quality));

        $scriptPath = base_path('python/convert_to_webp.py');

        $process = new Process([
            'python',
            $scriptPath,
            $inputPath,
            $outputPath,
        ]);

        $process->run();

        if (! $process->isSuccessful() || ! file_exists($outputPath)) {
            $errorOutput = trim($process->getErrorOutput() ?: $process->getOutput());
            $errorMessage = $errorOutput !== ''
                ? $errorOutput
                : 'El script de conversión a WebP con Python no se pudo ejecutar correctamente.';

            throw new RuntimeException("Error al convertir la imagen a WebP con Python: {$errorMessage}");
        }

        $disk->delete($relativePath);

        return $outputRelativePath;
    }
}

